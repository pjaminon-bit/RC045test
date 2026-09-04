<?php
// ============================================================
// Cryptografische authenticatie voor tenantbackups (#148)
// ============================================================
// De tenant-webruntime bezit bewust alleen de publieke verificatiesleutel.
// Signing gebeurt via een lokale root-owned attestor met peer-UID binding; deze
// PHP-laag kan dus geen handtekeningen maken en voert geen shell/root-commando's
// uit. Zolang de publieke sleutel nog niet is geactiveerd blijft schema 1 voor
// gecontroleerde rollout beschikbaar. Na activatie is schema 2 + attestatie
// verplicht en worden legacy schema-1 snapshots fail-closed geweigerd.
// ============================================================

function backupAttestatiePublicKeyPad(): string
{
    if (PHP_SAPI === 'cli') {
        $test = trim((string) (getenv('VERENIGING_BACKUP_ATTESTATION_TEST_PUBLIC_KEY') ?: ''));
        if ($test !== '' && str_starts_with($test, '/')) return $test;
    }
    return '/etc/verenigingsplatform/backup-attestation/public.pem';
}

function backupAttestatieSocketPad(): string
{
    if (PHP_SAPI === 'cli') {
        $test = trim((string) (getenv('VERENIGING_BACKUP_ATTESTATION_TEST_SOCKET') ?: ''));
        if ($test !== '' && str_starts_with($test, '/')) return $test;
    }
    return '/run/verenigingsplatform/backup-attestor.sock';
}

function backupAttestatieActief(): bool
{
    $pad = backupAttestatiePublicKeyPad();
    return is_file($pad) && !is_link($pad) && is_readable($pad);
}

function backupAttestatieAssoc(array $waarde): bool
{
    if ($waarde === []) return false;
    return array_keys($waarde) !== range(0, count($waarde) - 1);
}

function backupAttestatieSorteer($waarde)
{
    if (!is_array($waarde)) return $waarde;
    if (backupAttestatieAssoc($waarde)) ksort($waarde, SORT_STRING);
    foreach ($waarde as $k => $v) $waarde[$k] = backupAttestatieSorteer($v);
    return $waarde;
}

function backupAttestatieCanoniek(array $waarde): ?string
{
    try {
        return json_encode(
            backupAttestatieSorteer($waarde),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    } catch (Throwable $e) {
        return null;
    }
}

function backupAttestatieVeiligeBinding(string $waarde): bool
{
    return $waarde !== ''
        && strlen($waarde) <= 120
        && preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $waarde) === 1;
}

function backupAttestatieVeiligeTenant(string $waarde): bool
{
    return $waarde !== ''
        && strlen($waarde) <= 80
        && preg_match('/^[a-z0-9][a-z0-9-]*$/D', $waarde) === 1;
}

function backupAttestatieStatementData(string $pad, string $tenantKey, string $binding): ?array
{
    if (!backupAttestatieVeiligeTenant($tenantKey) || !backupAttestatieVeiligeBinding($binding)) return null;
    if (!is_file($pad) || is_link($pad)) return null;
    $naam = basename($pad);
    if (!preg_match('/^[A-Za-z0-9_.-]+\.json$/D', $naam)) return null;
    $raw = @file_get_contents($pad);
    if (!is_string($raw)) return null;
    return [
        'version' => 1,
        'kind' => 'data',
        'tenant_key' => $tenantKey,
        'binding' => $binding,
        'snapshot' => $naam,
        'content_sha256' => hash('sha256', $raw),
    ];
}

function backupAttestatieBestanden(string $payload): ?array
{
    if (!is_dir($payload) || is_link($payload)) return null;
    $root = realpath($payload);
    if ($root === false) return null;
    $root = rtrim(str_replace('\\', '/', $root), '/');
    $resultaat = [];
    $loop = function (string $map) use (&$loop, &$resultaat, $root): bool {
        $items = @scandir($map);
        if (!is_array($items)) return false;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $pad = $map . DIRECTORY_SEPARATOR . $item;
            if (is_link($pad)) return false;
            $real = realpath($pad);
            if ($real === false) return false;
            $realNorm = str_replace('\\', '/', $real);
            if ($realNorm !== $root && strncmp($realNorm, $root . '/', strlen($root) + 1) !== 0) return false;
            if (is_dir($pad)) {
                if (!$loop($pad)) return false;
                continue;
            }
            if (!is_file($pad)) return false;
            $rel = ltrim(substr($realNorm, strlen($root)), '/');
            if ($rel === '' || str_contains($rel, "\0") || str_contains($rel, '../')) return false;
            $hash = @hash_file('sha256', $pad);
            $size = @filesize($pad);
            if (!is_string($hash) || $hash === '' || $size === false) return false;
            $resultaat[] = ['path' => $rel, 'size' => (int) $size, 'sha256' => $hash];
        }
        return true;
    };
    if (!$loop($payload)) return null;
    usort($resultaat, static fn($a, $b) => strcmp((string) $a['path'], (string) $b['path']));
    return $resultaat;
}

function backupAttestatieStatementAsset(string $snapshot, string $tenantKey, string $scope): ?array
{
    if (!backupAttestatieVeiligeTenant($tenantKey) || !in_array($scope, ['fotoboek', 'sponsors'], true)) return null;
    if (!is_dir($snapshot) || is_link($snapshot)) return null;
    $naam = basename($snapshot);
    if ($naam === '' || !preg_match('/^[A-Za-z0-9_.-]+$/D', $naam)) return null;
    $manifest = $snapshot . DIRECTORY_SEPARATOR . 'manifest.json';
    $payload = $snapshot . DIRECTORY_SEPARATOR . 'payload';
    if (!is_file($manifest) || is_link($manifest)) return null;
    $rawManifest = @file_get_contents($manifest);
    if (!is_string($rawManifest)) return null;
    $files = backupAttestatieBestanden($payload);
    if ($files === null) return null;
    return [
        'version' => 1,
        'kind' => 'asset',
        'tenant_key' => $tenantKey,
        'binding' => $scope,
        'snapshot' => $naam,
        'manifest_sha256' => hash('sha256', $rawManifest),
        'files' => $files,
    ];
}

function backupAttestatieSidecarData(string $pad): string
{
    return $pad . '.sig';
}

function backupAttestatieSidecarAsset(string $snapshot): string
{
    return $snapshot . DIRECTORY_SEPARATOR . 'attestation.json';
}

function backupAttestatiePublicKey(): array
{
    $pad = backupAttestatiePublicKeyPad();
    if (!is_file($pad) || is_link($pad) || !is_readable($pad)) return [null, null];
    $raw = @file_get_contents($pad);
    if (!is_string($raw) || trim($raw) === '') return [null, null];
    $key = @openssl_pkey_get_public($raw);
    if ($key === false) return [null, null];
    return [$key, hash('sha256', $raw)];
}

function backupAttestatieVerifieerObject(array $attestatie, array $verwacht): bool
{
    if ((int) ($attestatie['schema'] ?? 0) !== 1
        || (string) ($attestatie['algorithm'] ?? '') !== 'rsa-sha256'
        || !is_string($attestatie['signed'] ?? null)
        || !is_string($attestatie['signature'] ?? null)
        || !is_string($attestatie['key_id'] ?? null)) return false;

    $signed = base64_decode((string) $attestatie['signed'], true);
    $signature = base64_decode((string) $attestatie['signature'], true);
    if (!is_string($signed) || $signed === '' || !is_string($signature) || $signature === '') return false;

    [$key, $keyId] = backupAttestatiePublicKey();
    if ($key === null || !is_string($keyId) || !hash_equals($keyId, (string) $attestatie['key_id'])) return false;
    if (@openssl_verify($signed, $signature, $key, OPENSSL_ALGO_SHA256) !== 1) return false;

    try { $statement = json_decode($signed, true, 128, JSON_THROW_ON_ERROR); }
    catch (Throwable $e) { return false; }
    if (!is_array($statement)) return false;
    $a = backupAttestatieCanoniek($statement);
    $b = backupAttestatieCanoniek($verwacht);
    return is_string($a) && is_string($b) && hash_equals($b, $a);
}

function backupAttestatieLeesSidecar(string $pad): ?array
{
    if (!is_file($pad) || is_link($pad)) return null;
    $raw = @file_get_contents($pad);
    if (!is_string($raw) || strlen($raw) > 1048576) return null;
    try { $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR); }
    catch (Throwable $e) { return null; }
    return is_array($data) ? $data : null;
}

function backupAttestatieVerifieerData(string $pad, string $tenantKey, string $binding): bool
{
    if (!backupAttestatieActief()) return false;
    $statement = backupAttestatieStatementData($pad, $tenantKey, $binding);
    $att = backupAttestatieLeesSidecar(backupAttestatieSidecarData($pad));
    return $statement !== null && $att !== null && backupAttestatieVerifieerObject($att, $statement);
}

function backupAttestatieVerifieerAsset(string $snapshot, string $tenantKey, string $scope): bool
{
    if (!backupAttestatieActief()) return false;
    $statement = backupAttestatieStatementAsset($snapshot, $tenantKey, $scope);
    $att = backupAttestatieLeesSidecar(backupAttestatieSidecarAsset($snapshot));
    return $statement !== null && $att !== null && backupAttestatieVerifieerObject($att, $statement);
}

function backupAttestatieVraag(string $snapshot, string $kind, string $binding): ?array
{
    if (!backupAttestatieActief() || !in_array($kind, ['data', 'asset'], true) || !backupAttestatieVeiligeBinding($binding)) return null;
    $socket = backupAttestatieSocketPad();
    if (!str_starts_with($socket, '/')) return null;
    $errno = 0; $errstr = '';
    $fp = @stream_socket_client('unix://' . $socket, $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT);
    if (!is_resource($fp)) {
        error_log('[platform] backup-attestor niet bereikbaar; backup faalt gesloten');
        return null;
    }
    stream_set_timeout($fp, 3);
    try {
        $request = json_encode([
            'action' => 'attest',
            'kind' => $kind,
            'binding' => $binding,
            'snapshot' => $snapshot,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (strlen($request) > 8192 || @fwrite($fp, $request) !== strlen($request)) return null;
        $raw = @fgets($fp, 1048577);
        if (!is_string($raw) || strlen($raw) > 1048576) return null;
        $antwoord = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($antwoord) || ($antwoord['ok'] ?? false) !== true || !is_array($antwoord['attestation'] ?? null)) return null;
        return $antwoord['attestation'];
    } catch (Throwable $e) {
        error_log('[platform] backup-attestor antwoord ongeldig: ' . $e->getMessage());
        return null;
    } finally {
        fclose($fp);
    }
}

function backupAttestatieSchrijfSidecar(string $pad, array $attestatie): bool
{
    $map = dirname($pad);
    if (!is_dir($map) || is_link($map) || file_exists($pad) || is_link($pad)) return false;
    try {
        $json = json_encode($attestatie, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
        $suffix = bin2hex(random_bytes(5));
    } catch (Throwable $e) { return false; }
    $tmp = $pad . '.tmp.' . $suffix;
    $h = @fopen($tmp, 'xb');
    if (!is_resource($h)) return false;
    $ok = false;
    try {
        if (!flock($h, LOCK_EX)) return false;
        $written = fwrite($h, $json);
        if ($written !== strlen($json) || !fflush($h)) return false;
        if (function_exists('fsync') && !fsync($h)) return false;
        $ok = true;
    } finally {
        @flock($h, LOCK_UN);
        fclose($h);
        if (!$ok) @unlink($tmp);
    }
    if (!$ok || !@chmod($tmp, 0640) || !@rename($tmp, $pad)) { @unlink($tmp); return false; }
    @chmod($pad, 0640);
    return is_file($pad) && !is_link($pad);
}

function backupAttestatieMaakData(string $pad, string $tenantKey, string $binding): bool
{
    $verwacht = backupAttestatieStatementData($pad, $tenantKey, $binding);
    if ($verwacht === null) return false;
    $att = backupAttestatieVraag($pad, 'data', $binding);
    if ($att === null || !backupAttestatieVerifieerObject($att, $verwacht)) return false;
    $sidecar = backupAttestatieSidecarData($pad);
    if (!backupAttestatieSchrijfSidecar($sidecar, $att)) return false;
    if (!backupAttestatieVerifieerData($pad, $tenantKey, $binding)) { @unlink($sidecar); return false; }
    return true;
}

function backupAttestatieMaakAsset(string $snapshot, string $tenantKey, string $scope): bool
{
    $verwacht = backupAttestatieStatementAsset($snapshot, $tenantKey, $scope);
    if ($verwacht === null) return false;
    $att = backupAttestatieVraag($snapshot, 'asset', $scope);
    if ($att === null || !backupAttestatieVerifieerObject($att, $verwacht)) return false;
    $sidecar = backupAttestatieSidecarAsset($snapshot);
    if (!backupAttestatieSchrijfSidecar($sidecar, $att)) return false;
    if (!backupAttestatieVerifieerAsset($snapshot, $tenantKey, $scope)) { @unlink($sidecar); return false; }
    return true;
}
