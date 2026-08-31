<?php
// Platformbeheer observability — uitsluitend niet-root, read-only diagnose.
// Deze laag gebruikt alleen bestaande control-plane runtimeconfig, server-side
// state en niet-gevoelige Linux capaciteitsinformatie. Er worden bewust geen
// processen gestart en geen tenant-private bestanden geopend.

require_once dirname(__DIR__) . '/deployment/privileged-ops-contract.php';

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

function cpAdminProcTekst(string $pad, int $maxBytes = 262144): ?string
{
    if (!in_array($pad, ['/proc/uptime','/proc/meminfo','/proc/cpuinfo'], true)) return null;
    if (!is_file($pad) || !is_readable($pad)) return null;
    $raw = @file_get_contents($pad, false, null, 0, $maxBytes);
    return is_string($raw) && $raw !== '' ? $raw : null;
}

function cpAdminPercentage(?int $used, ?int $total): ?float
{
    if ($used === null || $total === null || $total <= 0 || $used < 0) return null;
    return round(min(100, max(0, ($used / $total) * 100)), 1);
}

function cpAdminSysteemStatus(): array
{
    $c = cp51Config();
    $realApp = realpath($c['app_root']);
    $release = null;
    if (is_string($realApp)) {
        $base = basename($realApp);
        if (preg_match('/^[0-9a-f]{40}$/D', $base) === 1) $release = $base;
    }

    $uptime = null;
    $uptimeRaw = cpAdminProcTekst('/proc/uptime', 256);
    if (is_string($uptimeRaw) && preg_match('/^([0-9]+(?:\.[0-9]+)?)/', trim($uptimeRaw), $m) === 1) {
        $uptime = max(0, (int)floor((float)$m[1]);
    }

    $memoryTotal = null;
    $memoryAvailable = null;
    $mem = cpAdminProcTekst('/proc/meminfo');
    if (is_string($mem)) {
        if (preg_match('/^MemTotal:\s+([0-9]+)\s+kB$/m', $mem, $m) === 1) $memoryTotal = (int)$m[1] * 1024;
        if (preg_match('/^MemAvailable:\s+([0-9]+)\s+kB$/m', $mem, $m) === 1) $memoryAvailable = (int)$m[1] * 1024;
    }
    $memoryUsed = $memoryTotal !== null && $memoryAvailable !== null ? max(0, $memoryTotal - $memoryAvailable) : null;

    $cpuCount = 1;
    $cpu = cpAdminProcTekst('/proc/cpuinfo');
    if (is_string($cpu)) {
        $n = preg_match_all('/^processor\s*:/m', $cpu);
        if (is_int($n) && $n > 0) $cpuCount = $n;
    }
    $loadRaw = function_exists('sys_getloadavg') ? @sys_getloadavg() : false;
    $load = is_array($loadRaw) && count($loadRaw) >= 3
        ? ['one'=>(float)$loadRaw[0], 'five'=>(float)$loadRaw[1], 'fifteen'=>(float)$loadRaw[2]]
        : ['one'=>null, 'five'=>null, 'fifteen'=>null];

    $diskTotal = null;
    $diskFree = null;
    $tenantRoot = $c['tenants_root'];
    if (cp51Absoluut($tenantRoot) && !is_link($tenantRoot) && is_dir($tenantRoot)) {
        $total = @disk_total_space($tenantRoot);
        $free = @disk_free_space($tenantRoot);
        if (is_float($total) || is_int($total)) $diskTotal = max(0, (int)$total);
        if (is_float($free) || is_int($free)) $diskFree = max(0, (int)$free);
    }
    $diskUsed = $diskTotal !== null && $diskFree !== null ? max(0, $diskTotal - $diskFree) : null;

    return [
        'release_sha'=>$release,
        'php_version'=>PHP_VERSION,
        'uptime_seconds'=>$uptime,
        'cpu_count'=>$cpuCount,
        'load'=>$load,
        'memory'=>[
            'total_bytes'=>$memoryTotal,
            'available_bytes'=>$memoryAvailable,
            'used_bytes'=>$memoryUsed,
            'used_percent'=>cpAdminPercentage($memoryUsed, $memoryTotal),
        ],
        'disk'=>[
            'path'=>$tenantRoot,
            'total_bytes'=>$diskTotal,
            'free_bytes'=>$diskFree,
            'used_bytes'=>$diskUsed,
            'used_percent'=>cpAdminPercentage($diskUsed, $diskTotal),
        ],
        'privileged_ops'=>privilegedOpsSnapshot(),
    ];
}

function cpAdminPlatformStatus(array $snapshot): array
{
    $c = cp51Config();
    $pending = cpAdminDirectoryStatus($c['pending_dir'], true);
    // processing_dir is bewust root:root 0700. De weblaag hoort die map niet
    // rechtstreeks te kunnen lezen; dat is dus geen platformfout.
    $processing = cpAdminDirectoryStatus($c['processing_dir']);
    $results = cpAdminDirectoryStatus($c['results_dir']);
    $sessions = cpAdminDirectoryStatus($c['sessions_dir'], true);
    $snapshotOk = !is_link($c['snapshot_file']) && is_file($c['snapshot_file']) && is_readable($c['snapshot_file']);
    $age = cpAdminSnapshotLeeftijd($snapshot);

    $pendingCount = count(cpAdminVeiligeJsonBestanden($c['pending_dir']));
    $processingCount = $processing['ok'] ? count(cpAdminVeiligeJsonBestanden($c['processing_dir'])) : null;
    $resultCount = count(cpAdminVeiligeJsonBestanden($c['results_dir']));
    $system = cpAdminSysteemStatus();

    $critical = [];
    $warnings = [];
    if (!$pending['ok']) $critical[] = 'Aanvraagqueue is niet schrijfbaar door de control-plane runtime.';
    if (!$sessions['ok']) $critical[] = 'Sessiestore is niet schrijfbaar.';
    if (!$snapshotOk) $critical[] = 'Platformstatussnapshot is niet leesbaar.';
    if (!$results['ok']) $warnings[] = 'Executorresultaten zijn vanuit de weblaag niet leesbaar.';
    if ($processingCount !== null && $processingCount > 0) $warnings[] = $processingCount . ' aanvraag/aanvragen staan nog in verwerking.';
    if ($pendingCount > 10) $warnings[] = 'De aanvraagqueue loopt op (' . $pendingCount . ' wachtend).';
    if ($age === null) $warnings[] = 'Leeftijd van de platformsnapshot is onbekend.';
    elseif ($age > 3600) $warnings[] = 'De platformsnapshot is ouder dan één uur; tenant-health kan verouderd zijn.';

    $diskPercent = $system['disk']['used_percent'];
    if (is_float($diskPercent) && $diskPercent >= 97.0) $critical[] = 'Platformopslag is voor ' . $diskPercent . '% gevuld; lifecyclemutaties zijn uit voorzorg geblokkeerd.';
    elseif (is_float($diskPercent) && $diskPercent >= 90.0) $warnings[] = 'Platformopslag is voor ' . $diskPercent . '% gevuld.';

    $memoryPercent = $system['memory']['used_percent'];
    if (is_float($memoryPercent) && $memoryPercent >= 90.0) $warnings[] = 'Geheugengebruik is hoog (' . $memoryPercent . '%).';
    $loadOne = $system['load']['one'];
    if (is_float($loadOne) && $system['cpu_count'] > 0 && $loadOne > ($system['cpu_count'] * 1.5)) {
        $warnings[] = 'Systeemload is verhoogd (' . round($loadOne, 2) . ' op ' . $system['cpu_count'] . ' CPU-threads).';
    }

    $ops = is_array($system['privileged_ops'] ?? null) ? $system['privileged_ops'] : [];
    foreach ($ops['tools'] ?? [] as $tool) {
        if (!is_array($tool)) continue;
        $id = (string)($tool['id'] ?? 'onbekend');
        $status = (string)($tool['status'] ?? 'unsafe');
        if ($status === 'unsafe') {
            $warnings[] = 'Privileged deploytooling ' . $id . ' heeft onveilige bestandsmetadata of kan niet veilig worden gevalideerd.';
        } elseif ($status === 'drift') {
            $warnings[] = 'Privileged deploytooling ' . $id . ' wijkt af van de versie die de actieve release verwacht.';
        } elseif ($status === 'missing') {
            $warnings[] = 'Privileged deploytooling ' . $id . ' ontbreekt op het verwachte installatiepad.';
        }
    }

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
        'system'=>$system,
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

function cpAdminBytesLabel(?int $bytes): string
{
    if ($bytes === null || $bytes < 0) return 'onbekend';
    $units = ['B','KB','MB','GB','TB'];
    $value = (float)$bytes;
    $i = 0;
    while ($value >= 1024 && $i < count($units)-1) { $value /= 1024; $i++; }
    $precision = $i < 2 ? 0 : 1;
    return number_format($value, $precision, ',', '.') . ' ' . $units[$i];
}

function cpAdminUptimeLabel(?int $seconds): string
{
    if ($seconds === null || $seconds < 0) return 'onbekend';
    $dagen = intdiv($seconds, 86400);
    $uren = intdiv($seconds % 86400, 3600);
    if ($dagen > 0) return $dagen . 'd ' . $uren . 'u';
    $minuten = intdiv($seconds % 3600, 60);
    return $uren . 'u ' . $minuten . 'm';
}
