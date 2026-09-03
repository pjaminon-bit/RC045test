<?php
// ============================================================
// Fase 4.6 — alert delivery contract
// ============================================================
// Gedeelde fail-closed regels voor provisioning en runtime.
// De beslislogica is puur zodat transition/recovery/reminder en retry
// zonder hostprivileges regressiegetest kunnen worden.
// ============================================================

function monitoring46AlertsEnabled(array $alerts): bool
{
    // Legacy schema-1 plannen van vóór #154 hadden geen expliciet veld en
    // betekenden operationeel altijd alerting aan. Blijf die veilig als enabled
    // interpreteren totdat het plan gecontroleerd opnieuw is gegenereerd.
    if (!array_key_exists('enabled', $alerts)) return true;
    if (!is_bool($alerts['enabled'])) throw new RuntimeException('Monitoring alerts.enabled moet boolean zijn.');
    return $alerts['enabled'];
}

function monitoring46AlertAdapterMetadataFout(
    string $adapter,
    bool $bestaat,
    bool $regulier,
    bool $symlink,
    bool $executable,
    ?array $stat
): ?string {
    if ($adapter === '' || !str_starts_with($adapter, '/')) return 'adapter_not_absolute';
    if ($symlink) return 'adapter_symlink';
    if (!$bestaat) return 'adapter_missing';
    if (!$regulier) return 'adapter_not_regular';
    if (!is_array($stat)) return 'adapter_stat_failed';
    if ((int)($stat['uid'] ?? -1) !== 0) return 'adapter_not_root_owned';
    $mode = (int)($stat['mode'] ?? 0) & 0777;
    if (($mode & 0022) !== 0) return 'adapter_group_or_world_writable';
    if (!$executable) return 'adapter_not_executable';
    return null;
}

function monitoring46AlertAdapterFout(array $alerts): ?string
{
    if (!monitoring46AlertsEnabled($alerts)) return null;
    $adapter = trim((string)($alerts['adapter'] ?? ''));
    $symlink = $adapter !== '' && is_link($adapter);
    $bestaat = $adapter !== '' && file_exists($adapter);
    $regulier = $bestaat && is_file($adapter);
    $executable = $regulier && is_executable($adapter);
    $stat = $regulier && !$symlink ? @stat($adapter) : null;
    return monitoring46AlertAdapterMetadataFout($adapter, $bestaat, $regulier, $symlink, $executable, is_array($stat) ? $stat : null);
}

function monitoring46AlertAdapterFoutMelding(string $code): string
{
    return match ($code) {
        'adapter_not_absolute' => 'Alert-adapterpad is niet absoluut.',
        'adapter_symlink' => 'Alert-adapter mag geen symlink zijn.',
        'adapter_missing' => 'Alert-adapter ontbreekt terwijl alerting enabled is.',
        'adapter_not_regular' => 'Alert-adapter is geen regulier bestand.',
        'adapter_stat_failed' => 'Alert-adaptermetadata kon niet veilig worden gelezen.',
        'adapter_not_root_owned' => 'Alert-adapter is niet root-owned.',
        'adapter_group_or_world_writable' => 'Alert-adapter is group/world-writable.',
        'adapter_not_executable' => 'Alert-adapter is niet executable.',
        default => 'Alert-adapter voldoet niet aan het monitoringcontract.',
    };
}

function monitoring46AlertBeslissing(array $alerts, array $oud, string $huidig, int $epoch): array
{
    if (!in_array($huidig, ['up', 'down'], true)) throw new RuntimeException('Onbekende healthstate voor alertbeslissing.');
    $enabled = monitoring46AlertsEnabled($alerts);
    $laatstAfgeleverd = 'unknown';
    if ((int)($oud['schema'] ?? 0) >= 2 && in_array(($oud['last_delivered_state'] ?? ''), ['up', 'down'], true)) {
        $laatstAfgeleverd = (string)$oud['last_delivered_state'];
    }
    $laatsteEpoch = max(0, (int)($oud['last_alert_epoch'] ?? 0));
    $reminder = max(60, (int)($alerts['reminder_seconds'] ?? 3600));

    if (!$enabled) {
        return ['enabled'=>false,'send'=>false,'reason'=>null,'previous_delivered_state'=>$laatstAfgeleverd,'last_alert_epoch'=>$laatsteEpoch];
    }

    // Als een eerdere transition/reminder pending bleef en de healthstate
    // intussen weer veranderde, moet ook die nieuwe transition aantoonbaar
    // afgeleverd worden. Anders kan een volledige outage+recovery stil blijven.
    $vorigeDelivery = is_array($oud['delivery'] ?? null) ? $oud['delivery'] : [];
    $pendingState = (($vorigeDelivery['status'] ?? '') === 'pending' && in_array(($oud['state'] ?? ''), ['up','down'], true)) ? (string)$oud['state'] : null;
    if ($pendingState !== null && $pendingState !== $huidig) {
        return ['enabled'=>true,'send'=>true,'reason'=>$huidig === 'down' ? 'failure_transition' : 'recovery_transition','previous_delivered_state'=>$laatstAfgeleverd,'last_alert_epoch'=>$laatsteEpoch];
    }
    if ($laatstAfgeleverd !== $huidig) {
        return ['enabled'=>true,'send'=>true,'reason'=>$huidig === 'down' ? 'failure_transition' : 'recovery_transition','previous_delivered_state'=>$laatstAfgeleverd,'last_alert_epoch'=>$laatsteEpoch];
    }
    if ($huidig === 'down' && ($epoch - $laatsteEpoch) >= $reminder) {
        return ['enabled'=>true,'send'=>true,'reason'=>'reminder','previous_delivered_state'=>$laatstAfgeleverd,'last_alert_epoch'=>$laatsteEpoch];
    }
    return ['enabled'=>true,'send'=>false,'reason'=>null,'previous_delivered_state'=>$laatstAfgeleverd,'last_alert_epoch'=>$laatsteEpoch];
}

function monitoring46AlertNieuweState(
    array $oud,
    string $huidig,
    int $epoch,
    array $beslissing,
    string $deliveryStatus,
    ?string $errorCode = null
): array {
    $laatstAfgeleverd = (string)($beslissing['previous_delivered_state'] ?? 'unknown');
    $laatsteEpoch = max(0, (int)($beslissing['last_alert_epoch'] ?? 0));
    $geleverd = $deliveryStatus === 'delivered';
    if ($geleverd) {
        $laatstAfgeleverd = $huidig;
        $laatsteEpoch = $epoch;
    }
    $vorigeDelivery = is_array($oud['delivery'] ?? null) ? $oud['delivery'] : [];
    $geleverdOp = $geleverd ? gmdate('Y-m-d\TH:i:s\Z', $epoch) : ($vorigeDelivery['delivered_at_utc'] ?? null);
    return [
        'schema'=>2,
        'state'=>$huidig,
        'last_delivered_state'=>$laatstAfgeleverd,
        'last_alert_epoch'=>$laatsteEpoch,
        'delivery'=>[
            'enabled'=>(bool)($beslissing['enabled'] ?? false),
            'status'=>$deliveryStatus,
            'reason'=>$beslissing['reason'] ?? null,
            'error_code'=>$errorCode,
            'attempted_at_utc'=>in_array($deliveryStatus, ['delivered','pending'], true) ? gmdate('Y-m-d\TH:i:s\Z', $epoch) : null,
            'delivered_at_utc'=>$geleverdOp,
        ],
        'updated_at_utc'=>gmdate('Y-m-d\TH:i:s\Z', $epoch),
    ];
}
