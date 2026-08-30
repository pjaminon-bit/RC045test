<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;
function apf(bool $conditie, string $label): void {
    global $ok, $fout;
    if ($conditie) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}
function apfWis(string $pad): void {
    if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $naam) {
        if ($naam === '.' || $naam === '..') continue;
        apfWis($pad . DIRECTORY_SEPARATOR . $naam);
    }
    @rmdir($pad);
}

require_once $root . '/app/storage/private-store-prewrite.php';
$tmp = sys_get_temp_dir() . '/rc045-prewrite-' . bin2hex(random_bytes(5));
$private = $tmp . '/private';
@mkdir($private . '/backups', 0750, true);
try {
    // Simuleer de live bevinding: de historische normale tenantbackupnamespace
    // kan niet als directory worden gebruikt, terwijl de tenant-private
    // backup-parent zelf wel schrijfbaar is.
    file_put_contents($private . '/backups/tenant', 'legacy-owner-drift-canary');
    $data = ['leden' => [['id' => 'canary', 'naam' => 'Voor write']]];
    $pad = privatePrewriteMaak($private, 'test', 'private-leden', $data);
    apf(is_string($pad) && is_file($pad) && !is_link($pad), 'fallback schrijft een regulier duurzaam journalbestand');
    apf(is_string($pad) && str_contains($pad, '/backups/prewrite-v2/private-leden/'), 'fallback gebruikt een aparte nieuwe namespace naast de legacy tenantbackup');
    apf((string)file_get_contents($private . '/backups/tenant') === 'legacy-owner-drift-canary', 'legacy tenantbackupnamespace wordt niet gewijzigd of verwijderd');

    $env = is_string($pad) ? json_decode((string)file_get_contents($pad), true) : null;
    apf(is_array($env) && ($env['purpose'] ?? '') === 'private-store-prewrite-fallback', 'journal heeft expliciet fallbackdoel');
    apf(is_array($env) && ($env['tenant_key'] ?? '') === 'test' && ($env['backup_key'] ?? '') === 'private-leden', 'journal is tenant- en collectiegebonden');
    apf(is_array($env) && ($env['data'] ?? null) === $data, 'journal bewaart de volledige oude payload');
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    apf(is_array($env) && hash_equals(hash('sha256', $payload), (string)($env['payload_sha256'] ?? '')), 'journal bindt cryptografisch aan de oude payload');
    if (is_string($pad)) {
        $stat = lstat($pad);
        apf(is_array($stat) && (((int)$stat['mode'] & 0777) === 0640), 'journalbestand is exact 0640');
    } else apf(false, 'journalbestand is exact 0640');

    $private2 = $tmp . '/private-symlink';
    $outside = $tmp . '/outside';
    @mkdir($private2 . '/backups', 0750, true);
    @mkdir($outside, 0750, true);
    $symlinkOk = function_exists('symlink') && @symlink($outside, $private2 . '/backups/prewrite-v2');
    if ($symlinkOk) {
        apf(privatePrewriteMaak($private2, 'test', 'private-leden', $data) === null, 'symlink in fallbacknamespace wordt fail-closed geweigerd');
        apf((scandir($outside) ?: []) === ['.', '..'], 'symlinkdoel buiten private root blijft ongemoeid');
    } else {
        apf(true, 'symlinktest overgeslagen omdat testomgeving geen symlink toestaat');
        apf(true, 'extern symlinkdoel blijft ongemoeid');
    }
    apf(privatePrewriteMaak($private, '../ander', 'private-leden', $data) === null, 'ongeldige tenant-key wordt geweigerd');
    apf(privatePrewriteMaak($private, 'test', '../leden', $data) === null, 'ongeldige backup-key wordt geweigerd');

    $src = (string)file_get_contents($root . '/app/storage/private-store.php');
    apf(str_contains($src, "require_once __DIR__ . '/private-store-prewrite.php'"), 'private-store laadt de fallbackhelper expliciet');
    apf(str_contains($src, 'function privateStoreVerplichtePrebackup'), 'private-store centraliseert de verplichte pre-backuppolicy');
    apf(substr_count($src, 'privateStoreVerplichtePrebackup($collectie,') >= 2, 'zowel JSON- als PDO-write gebruikt dezelfde verplichte pre-backuppolicy');
    apf(str_contains($src, 'tenantBackupMaakArray($sleutel,$oudData)') && str_contains($src, 'privatePrewriteMaak($root,privateStoreTenant(),$sleutel,$oudData)'), 'normale backup blijft eerste route en fallback is uitsluitend tweede route');
    apf(str_contains($src, 'Geen duurzame pre-backuproute kon worden opgeslagen.') && str_contains($src, 'alle duurzame pre-backuproutes faalden'), 'beide writepaden blijven fail-closed wanneer geen herstelroute lukt');
} finally {
    apfWis($tmp);
}

echo "Audit prewrite fallback: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
