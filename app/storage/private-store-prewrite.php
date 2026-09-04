<?php
// ============================================================
// Fail-closed prewrite-journal voor private-store writes
// ============================================================
// Dit is uitsluitend een tweede duurzame herstelroute wanneer de normale
// tenantbackupnamespace niet schrijfbaar is (bijvoorbeeld door legacy owner-
// drift op een bestaande VPS). De write mag pas doorgaan nadat ook deze route
// aantoonbaar een tenantgebonden snapshot heeft geplaatst.
//
// Zodra cryptografische backupattestatie actief is, mag deze legacy noodroute
// niet langer als voldoende herstelbewijs gelden: hij heeft bewust geen root-
// owned signature. De caller moet dan fail-closed stoppen wanneer de normale,
// geattesteerde tenantbackup niet kan worden gemaakt.
// ============================================================

function privatePrewriteNormPad(string $pad): string
{
    $pad = str_replace('\\', '/', $pad);
    $pad = (string) preg_replace('~/+~', '/', $pad);
    if (DIRECTORY_SEPARATOR === '\\') $pad = strtolower($pad);
    return rtrim($pad, '/');
}

function privatePrewriteBinnen(string $pad, string $root): bool
{
    $pad = privatePrewriteNormPad($pad);
    $root = privatePrewriteNormPad($root);
    return $pad === $root || strncmp($pad, $root . '/', strlen($root) + 1) === 0;
}

function privatePrewriteVeiligeSleutel(string $waarde, int $max): ?string
{
    $waarde = trim($waarde);
    if ($waarde === '' || strlen($waarde) > $max) return null;
    return preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $waarde) === 1 ? $waarde : null;
}

/**
 * Controleert dat een bestaand of nog aan te maken pad volledig onder de
 * fysieke private root blijft en nergens een symlinkcomponent bevat.
 */
function privatePrewritePadVeilig(string $pad, string $privateRoot): bool
{
    if ($privateRoot === '' || $privateRoot[0] !== '/' || !is_dir($privateRoot) || is_link($privateRoot)) return false;
    if (!privatePrewriteBinnen($pad, $privateRoot)) return false;

    $privateReal = realpath($privateRoot);
    if ($privateReal === false) return false;
    $privateReal = privatePrewriteNormPad($privateReal);

    $cursor = rtrim($pad, '/\\');
    while (true) {
        if (is_link($cursor)) return false;
        if (privatePrewriteNormPad($cursor) === privatePrewriteNormPad($privateRoot)) break;
        $parent = dirname($cursor);
        if ($parent === $cursor || !privatePrewriteBinnen($parent, $privateRoot)) return false;
        $cursor = $parent;
    }

    $bestaand = rtrim($pad, '/\\');
    while (!file_exists($bestaand) && !is_link($bestaand)) {
        $parent = dirname($bestaand);
        if ($parent === $bestaand || !privatePrewriteBinnen($parent, $privateRoot)) return false;
        $bestaand = $parent;
    }
    if (is_link($bestaand)) return false;
    $real = realpath($bestaand);
    return $real !== false && privatePrewriteBinnen(privatePrewriteNormPad($real), $privateReal);
}

function privatePrewriteMaakMap(string $map, string $privateRoot): bool
{
    if (!privatePrewritePadVeilig($map, $privateRoot)) return false;
    if (!is_dir($map) && !@mkdir($map, 0750, true) && !is_dir($map)) return false;
    clearstatcache(true, $map);
    if (!is_dir($map) || is_link($map) || !privatePrewritePadVeilig($map, $privateRoot)) return false;
    if (!@chmod($map, 0750)) return false;
    $stat = @lstat($map);
    return is_array($stat) && (((int)$stat['mode'] & 0777) === 0750);
}

function privatePrewriteTijd(): string
{
    $nu = microtime(true);
    $sec = (int) floor($nu);
    $micro = max(0, min(999999, (int) floor(($nu - $sec) * 1000000)));
    return gmdate('Ymd_His', $sec) . '_' . sprintf('%06d', $micro);
}

/** Houdt de noodjournal begrensd zonder de zojuist gemaakte snapshot te raken. */
function privatePrewritePrune(string $map, string $privateRoot, string $behouden): void
{
    if (!is_dir($map) || is_link($map) || !privatePrewritePadVeilig($map, $privateRoot)) return;
    $files = array_values(array_filter(
        @glob($map . DIRECTORY_SEPARATOR . '*.json') ?: [],
        static fn($p) => is_file($p) && !is_link($p)
    ));
    usort($files, static fn($a, $b) => strcmp(basename($b), basename($a)));
    foreach (array_slice($files, 200) as $file) {
        if (!hash_equals(privatePrewriteNormPad($behouden), privatePrewriteNormPad($file)) && privatePrewritePadVeilig($file, $privateRoot)) {
            @unlink($file);
        }
    }
}

/**
 * Schrijft een tenantgebonden prewrite-envelope en retourneert het finale pad.
 * null betekent: geen aantoonbaar duurzame fallback; de caller moet fail-closed
 * stoppen en mag de private-store write niet uitvoeren.
 */
function privatePrewriteMaak(string $privateRoot, string $tenantKey, string $backupKey, array $data): ?string
{
    if (function_exists('backupAttestatieActief') && backupAttestatieActief()) {
        error_log('[platform] legacy private prewrite-fallback geweigerd: cryptografische backupattestatie is actief');
        return null;
    }

    $tenantKey = privatePrewriteVeiligeSleutel($tenantKey, 80);
    $backupKey = privatePrewriteVeiligeSleutel($backupKey, 120);
    if ($tenantKey === null || $backupKey === null) return null;
    if ($privateRoot === '' || $privateRoot[0] !== '/' || !is_dir($privateRoot) || is_link($privateRoot)) return null;

    $root = rtrim($privateRoot, '/\\') . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'prewrite-v2';
    $map = $root . DIRECTORY_SEPARATOR . $backupKey;
    if (!privatePrewriteMaakMap($map, $privateRoot)) return null;

    try {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (!is_string($payload)) return null;
        $envelope = [
            'schema' => 1,
            'purpose' => 'private-store-prewrite-fallback',
            'tenant_key' => $tenantKey,
            'backup_key' => $backupKey,
            'created_at_utc' => gmdate('Y-m-d\\TH:i:s\\Z'),
            'payload_sha256' => hash('sha256', $payload),
            'data' => $data,
        ];
        $json = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
        $rand = bin2hex(random_bytes(6));
    } catch (Throwable $e) {
        return null;
    }

    $pad = $map . DIRECTORY_SEPARATOR . privatePrewriteTijd() . '_' . $rand . '.json';
    $tmp = $pad . '.tmp';
    if (!privatePrewritePadVeilig($tmp, $privateRoot) || file_exists($tmp) || is_link($tmp)) return null;

    $h = @fopen($tmp, 'xb');
    if (!is_resource($h)) return null;
    $ok = false;
    try {
        if (!flock($h, LOCK_EX)) return null;
        $geschreven = 0;
        $lengte = strlen($json);
        while ($geschreven < $lengte) {
            $n = fwrite($h, substr($json, $geschreven));
            if ($n === false || $n === 0) return null;
            $geschreven += $n;
        }
        if (!fflush($h)) return null;
        if (function_exists('fsync') && !fsync($h)) return null;
        $ok = true;
    } finally {
        @flock($h, LOCK_UN);
        fclose($h);
        if (!$ok) @unlink($tmp);
    }
    if (!$ok) return null;

    if (!@chmod($tmp, 0640)) { @unlink($tmp); return null; }
    $tmpStat = @lstat($tmp);
    if (!is_array($tmpStat) || (((int)$tmpStat['mode'] & 0777) !== 0640) || is_link($tmp)) { @unlink($tmp); return null; }
    if (!privatePrewritePadVeilig($pad, $privateRoot) || file_exists($pad) || is_link($pad) || !@rename($tmp, $pad)) {
        @unlink($tmp);
        return null;
    }
    if (!@chmod($pad, 0640)) return null;
    clearstatcache(true, $pad);
    $stat = @lstat($pad);
    $raw = @file_get_contents($pad);
    if (!is_array($stat) || (((int)$stat['mode'] & 0777) !== 0640) || !is_string($raw) || !hash_equals(hash('sha256', $json), hash('sha256', $raw))) {
        return null;
    }

    privatePrewritePrune($map, $privateRoot, $pad);
    return $pad;
}
