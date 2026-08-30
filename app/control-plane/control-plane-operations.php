<?php
// Control-plane operationele diagnose — niet-root en read-only.
// Deze helpers lezen uitsluitend de reeds toegestane pending queue en veilige
// snapshotvelden. Processing blijft bewust root-only en wordt niet geopend.

function cpOpsActies(): array
{
    return ['provision','adopt-active','suspend','activate','recover','export','delete','cancel-delete','purge'];
}

function cpOpsPendingRequests(string $operator, int $limit = 12): array
{
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._@-]{1,63}$/D', $operator) !== 1) return [];
    $limit = max(1, min(30, $limit));
    $files = cpAdminVeiligeJsonBestanden(cp51Config()['pending_dir']);
    usort($files, static fn(string $a, string $b): int => ((int)@filemtime($b)) <=> ((int)@filemtime($a)));
    $out = [];
    foreach ($files as $file) {
        $id = pathinfo($file, PATHINFO_FILENAME);
        $raw = @file_get_contents($file);
        try { $r = is_string($raw) ? json_decode($raw, true, 64, JSON_THROW_ON_ERROR) : null; }
        catch (Throwable $e) { $r = null; }
        if (!is_array($r)
            || (int)($r['schema'] ?? 0) !== 1
            || ($r['phase'] ?? '') !== '5.1-request'
            || !hash_equals($id, (string)($r['request_id'] ?? ''))
            || !hash_equals($operator, (string)($r['operator'] ?? ''))
            || preg_match('/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?$/D', (string)($r['tenant_key'] ?? '')) !== 1
            || !in_array((string)($r['action'] ?? ''), cpOpsActies(), true)) {
            continue;
        }
        $requested = (string)($r['requested_at_utc'] ?? '');
        $ts = strtotime($requested);
        if ($ts === false) continue;
        $out[] = [
            'request_id'=>$id,
            'tenant_key'=>(string)$r['tenant_key'],
            'action'=>(string)$r['action'],
            'requested_at_utc'=>$requested,
            'age_seconds'=>max(0, time() - $ts),
            'stale'=>time() - $ts > 900,
        ];
        if (count($out) >= $limit) break;
    }
    return $out;
}

function cpOpsTenantAttention(array $tenant): array
{
    $status = (string)($tenant['status'] ?? '');
    $redenen = [];
    if ($status === 'active' && ($tenant['healthy'] ?? false) !== true) $redenen[] = 'Geen actuele gezonde monitoringstatus.';
    if (($tenant['transition'] ?? null) !== null) $redenen[] = 'Lifecycle-transition is nog niet afgerond.';
    if ($status === 'invalid') $redenen[] = 'Provisioning- of lifecyclemetadata is ongeldig.';
    if ($status === 'suspended' && !is_array($tenant['last_export'] ?? null)) $redenen[] = 'Nog geen geverifieerde export beschikbaar.';
    if ($status === 'pending_delete') {
        $nb = strtotime((string)($tenant['purge_not_before_utc'] ?? ''));
        if ($nb === false) $redenen[] = 'Purge-wachttijd is niet geldig vastgelegd.';
    }
    return $redenen;
}

function cpOpsStatusLeeftijd(?string $utc): ?int
{
    if (!is_string($utc) || $utc === '') return null;
    $ts = strtotime($utc);
    return $ts === false ? null : max(0, time() - $ts);
}

function cpOpsExportStatus(array $tenant): array
{
    $x = $tenant['last_export'] ?? null;
    if (!is_array($x) || preg_match('/^[0-9a-f]{64}$/D', (string)($x['sha256'] ?? '')) !== 1) {
        return ['available'=>false,'created_at_utc'=>null,'age_seconds'=>null];
    }
    $created = (string)($x['created_at_utc'] ?? '');
    return ['available'=>true,'created_at_utc'=>$created !== '' ? $created : null,'age_seconds'=>cpOpsStatusLeeftijd($created)];
}
