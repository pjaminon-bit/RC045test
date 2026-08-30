<?php
// Platformbeheer observability — uitsluitend niet-root, read-only diagnose.
// Deze laag gebruikt alleen de bestaande control-plane runtimeconfig en
// server-side statebestanden. Er worden geen processen gestart en geen
// tenant-private bestanden geopend.

function cpAdminDirectoryStatus(string $path, bool $writable = false): array
{
    if (!cp51Absoluut($path) || is_link($path) || !is_dir($path)) {
        return ['ok'=>false, 'state'=>'missing', 'writable'=>false];
    }
    $canWrite = is_writable($path);
    return [
        'ok'=>$writable ? $canWrite : is_readable($path),
        'state'=>'available',
        'writable'=>$canWrite,
    ];
}

function cpAdminVeiligeJsonBestanden(string $dir): array
{
    if (!cp51Absoluut($dir) || is_link($dir) || !is_dir($dir) || !is_readable($dir)) return [];
    $files = glob($dir . '/*.json') ?: [];
    $safe = [];
    foreach ($files as $file) {
        if (is_link($file) || !is_file($file) || preg_match('/^[0-9a-f]{32}\.json$/D', basename($file)) !== 1) continue;
        $safe[] = $file;
    }
    return $safe;
}

function cpAdminSnapshotLeeftijd(array $snapshot): ?int
{
    $raw = (string)($snapshot['generated_at_utc'] ?? '');
    $ts = $raw !== '' ? strtotime($raw) : false;
    if ($ts === false) return null;
    return max(0, time() - $ts);
}

function cpAdminPlatformStatus(array $snapshot): array
{
    $c = cp51Config();
    $pending = cpAdminDirectoryStatus($c['pending_dir'], true);
    $processing = cpAdminDirectoryStatus($c['processing_dir']);
    $results = cpAdminDirectoryStatus($c['results_dir']);
    $sessions = cpAdminDirectoryStatus($c['sessions_dir'], true);
    $snapshotOk = !is_link($c['snapshot_file']) && is_file($c['snapshot_file']) && is_readable($c['snapshot_file']);
    $age = cpAdminSnapshotLeeftijd($snapshot);

    $pendingCount = count(cpAdminVeiligeJsonBestanden($c['pending_dir']));
    $processingCount = count(cpAdminVeiligeJsonBestanden($c['processing_dir']));
    $resultCount = count(cpAdminVeiligeJsonBestanden($c['results_dir']));

    $critical = [];
    $warnings = [];
    if (!$pending['ok']) $critical[] = 'Aanvraagqueue is niet schrijfbaar door de control-plane runtime.';
    if (!$sessions['ok']) $critical[] = 'Sessiestore is niet schrijfbaar.';
    if (!$snapshotOk) $critical[] = 'Platformstatussnapshot is niet leesbaar.';
    if (!$processing['ok']) $warnings[] = 'Executor-processingqueue is vanuit de weblaag niet controleerbaar.';
    if (!$results['ok']) $warnings[] = 'Executorresultaten zijn vanuit de weblaag niet leesbaar.';
    if ($processingCount > 0) $warnings[] = $processingCount . ' aanvraag/aanvragen staan nog in verwerking.';
    if ($pendingCount > 10) $warnings[] = 'De aanvraagqueue loopt op (' . $pendingCount . ' wachtend).';
    if ($age === null) $warnings[] = 'Leeftijd van de platformsnapshot is onbekend.';
    elseif ($age > 3600) $warnings[] = 'De platformsnapshot is ouder dan één uur; tenant-health kan verouderd zijn.';

    $tenants = is_array($snapshot['tenants'] ?? null) ? $snapshot['tenants'] : [];
    $counts = [
        'total'=>count($tenants),
        'active'=>0,
        'healthy'=>0,
        'unhealthy'=>0,
        'setup'=>0,
        'suspended'=>0,
        'pending_delete'=>0,
        'invalid'=>0,
        'transitions'=>0,
    ];
    foreach ($tenants as $tenant) {
        if (!is_array($tenant)) continue;
        $status = (string)($tenant['status'] ?? '');
        if ($status === 'active') {
            $counts['active']++;
            if (($tenant['healthy'] ?? false) === true) $counts['healthy']++; else $counts['unhealthy']++;
        } elseif ($status === 'suspended') $counts['suspended']++;
        elseif ($status === 'pending_delete') $counts['pending_delete']++;
        elseif (in_array($status, ['setup_required','unmanaged'], true)) $counts['setup']++;
        elseif ($status === 'invalid') $counts['invalid']++;
        if (($tenant['transition'] ?? null) !== null) $counts['transitions']++;
    }
    if ($counts['unhealthy'] > 0) $warnings[] = $counts['unhealthy'] . ' actieve vereniging(en) rapporteren geen actuele gezonde status.';
    if ($counts['invalid'] > 0) $warnings[] = $counts['invalid'] . ' tenant(s) hebben ongeldige lifecycle/provisioningmetadata.';
    if ($counts['transitions'] > 0) $warnings[] = $counts['transitions'] . ' tenant(s) hebben een onafgeronde lifecycle-transition.';

    return [
        'ok'=>$critical === [],
        'critical'=>$critical,
        'warnings'=>$warnings,
        'snapshot_age_seconds'=>$age,
        'queue'=>[
            'pending'=>$pendingCount,
            'processing'=>$processingCount,
            'results'=>$resultCount,
            'writable'=>$pending['ok'],
        ],
        'counts'=>$counts,
    ];
}

function cpAdminRecenteResultaten(string $operator, int $limit = 8): array
{
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._@-]{1,63}$/D', $operator) !== 1) return [];
    $limit = max(1, min(20, $limit));
    $files = cpAdminVeiligeJsonBestanden(cp51Config()['results_dir']);
    usort($files, static function(string $a, string $b): int {
        return ((int)@filemtime($b)) <=> ((int)@filemtime($a));
    });
    $out = [];
    foreach ($files as $file) {
        $id = pathinfo($file, PATHINFO_FILENAME);
        $result = cp51RecentResult($id, $operator);
        if (!is_array($result)) continue;
        $out[] = $result;
        if (count($out) >= $limit) break;
    }
    return $out;
}

function cpAdminLeeftijdLabel(?int $seconds): string
{
    if ($seconds === null) return 'onbekend';
    if ($seconds < 60) return $seconds . ' sec';
    if ($seconds < 3600) return intdiv($seconds, 60) . ' min';
    if ($seconds < 86400) return intdiv($seconds, 3600) . ' uur';
    return intdiv($seconds, 86400) . ' dagen';
}
