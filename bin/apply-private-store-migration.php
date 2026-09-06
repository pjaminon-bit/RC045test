<?php
// ============================================================
// Privileged private-store migration coordinator (#211)
// ============================================================
// Wordt uitsluitend vanuit de root-owned immutable host-engine gestart.
// De JSON/PDO-dataverwerking zelf draait via runuser als tenant-runtimeuser.
// ============================================================
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Alleen via CLI beschikbaar.'); }

require_once dirname(__DIR__) . '/app/deployment/runtime-contract.php';
require_once dirname(__DIR__) . '/app/core/tenant-runtime.php';

function cut211Stop(string $melding, int $code = 1): never
{
    fwrite(STDERR, "FOUT: {$melding}\n");
    exit($code);
}

function cut211Root(): void
{
    if (DIRECTORY_SEPARATOR !== '/') throw new RuntimeException('Storage-migratiecutover is uitsluitend voor de Linux VPS.');
    if (!function_exists('posix_geteuid') || posix_geteuid() !== 0) throw new RuntimeException('Storage-migratiecutover vereist root via de trusted host-engine.');
}

function cut211VeiligBestand(string $pad, string $label): string
{
    if (!tenantRuntimeIsAbsoluutPad($pad) || is_link($pad) || !is_file($pad)) throw new RuntimeException($label . ' ontbreekt of is onveilig.');
    $real = realpath($pad);
    if ($real === false || !hash_equals($pad, $real)) throw new RuntimeException($label . ' moet een fysiek canoniek pad zijn.');
    return $real;
}

function cut211RuntimeDir(string $tenantRoot, string $group): array
{
    $dir = $tenantRoot . '/storage-runtime';
    if (!file_exists($dir) && !@mkdir($dir, 0750)) throw new RuntimeException('Storage-runtime map kon niet worden aangemaakt.');
    if (is_link($dir) || !is_dir($dir)) throw new RuntimeException('Storage-runtime map is onveilig.');
    $real = realpath($dir);
    if ($real === false || !hash_equals($dir, $real)) throw new RuntimeException('Storage-runtime map is niet canoniek.');
    if (!@chown($dir, 'root') || !@chgrp($dir, $group) || !@chmod($dir, 0750)) throw new RuntimeException('Storage-runtime map kon niet veilig root-owned worden gemaakt.');
    $stat = @stat($dir);
    if (!is_array($stat) || (int)$stat['uid'] !== 0 || (((int)$stat['mode'] & 0777) !== 0750)) throw new RuntimeException('Storage-runtime map heeft onverwachte metadata.');

    $lock = $dir . '/private-store-migration.lock';
    if (!file_exists($lock)) {
        $h = @fopen($lock, 'x');
        if (!is_resource($h)) throw new RuntimeException('Storage-migratielock kon niet worden aangemaakt.');
        fclose($h);
    }
    if (is_link($lock) || !is_file($lock)) throw new RuntimeException('Storage-migratielock is onveilig.');
    if (!@chown($lock, 'root') || !@chgrp($lock, $group) || !@chmod($lock, 0660)) throw new RuntimeException('Storage-migratielock kon niet veilig worden ingesteld.');
    return ['dir' => $dir, 'lock' => $lock, 'marker' => $dir . '/private-store-migration.active', 'state' => $dir . '/private-store-runtime.json'];
}

function cut211MarkerAan(array $paths, string $tenant, string $group, string $mode): void
{
    $marker = $paths['marker'];
    if (file_exists($marker) || is_link($marker)) throw new RuntimeException('Er is al een private-store migratiewindow actief.');
    $data = json_encode([
        'schema' => 1,
        'phase' => 'private-json-to-pdo-window',
        'tenant_key' => $tenant,
        'mode' => $mode,
        'started_at' => gmdate('c'),
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $tmp = $paths['dir'] . '/.migration.active.tmp.' . bin2hex(random_bytes(8));
    if (@file_put_contents($tmp, $data, LOCK_EX) === false) throw new RuntimeException('Migratiemarker kon niet worden voorbereid.');
    @chown($tmp, 'root'); @chgrp($tmp, $group); @chmod($tmp, 0640);
    if (!@rename($tmp, $marker)) { @unlink($tmp); throw new RuntimeException('Migratiemarker kon niet atomisch worden geactiveerd.'); }
}

function cut211MarkerUit(array $paths): void
{
    $marker = $paths['marker'];
    if (!file_exists($marker)) return;
    if (is_link($marker) || !is_file($marker) || !@unlink($marker)) throw new RuntimeException('Migratiemarker kon niet veilig worden verwijderd.');
}

function cut211Worker(string $osUser, string $config, string $privateRoot, array $args): array
{
    $worker = __DIR__ . '/migrate-private-json-to-pdo.php';
    if (is_link($worker) || !is_file($worker)) throw new RuntimeException('Immutable migratieworker ontbreekt.');
    $runuser = '/usr/sbin/runuser'; $env = '/usr/bin/env'; $php = '/usr/bin/php';
    foreach ([$runuser, $env, $php] as $exe) if (!is_executable($exe)) throw new RuntimeException('Vereiste host-executable ontbreekt: ' . $exe);
    $cmd = [$runuser, '-u', $osUser, '--', $env, '-i',
        'PATH=/usr/sbin:/usr/bin:/sbin:/bin',
        'VERENIGING_REQUIRE_TENANT_CONFIG=1',
        'VERENIGING_CONFIG_FILE=' . $config,
        'VERENIGING_PRIVATE_ROOT=' . $privateRoot,
        $php, $worker,
    ];
    foreach ($args as $arg) $cmd[] = $arg;
    $spec = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
    $proc = proc_open($cmd, $spec, $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($proc)) throw new RuntimeException('Migratieworker kon niet worden gestart.');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $code = proc_close($proc);
    if ($code !== 0) throw new RuntimeException('Migratieworker faalde: ' . trim((string)$stderr));
    $lines = preg_split('/\r\n|\n|\r/', trim((string)$stdout));
    $last = is_array($lines) && $lines !== [] ? end($lines) : '';
    $json = is_string($last) ? json_decode($last, true) : null;
    if (!is_array($json) || (int)($json['schema'] ?? 0) !== 1 || ($json['phase'] ?? '') !== 'private-json-to-pdo-worker') {
        throw new RuntimeException('Migratieworker gaf geen geldig machineleesbaar bewijs terug.');
    }
    return $json;
}

function cut211StateSchrijf(array $paths, string $tenant, string $group, array $proof): void
{
    if (file_exists($paths['state']) || is_link($paths['state'])) throw new RuntimeException('Private-store runtime-state bestaat al; dubbele cutover geweigerd.');
    foreach (['proof_path','proof_sha256','target_aggregate_sha256'] as $key) if (trim((string)($proof[$key] ?? '')) === '') throw new RuntimeException('Migratieworker mist statebinding: ' . $key);
    $state = [
        'schema' => 1,
        'phase' => 'json-to-pdo-cutover',
        'tenant_key' => $tenant,
        'source_driver' => 'json',
        'effective_driver' => 'pdo',
        'created_at' => gmdate('c'),
        'proof_path' => (string)$proof['proof_path'],
        'proof_sha256' => (string)$proof['proof_sha256'],
        'target_aggregate_sha256' => (string)$proof['target_aggregate_sha256'],
    ];
    $json = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $tmp = $paths['dir'] . '/.private-store-runtime.tmp.' . bin2hex(random_bytes(8));
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) throw new RuntimeException('Private-store runtime-state kon niet worden voorbereid.');
    @chown($tmp, 'root'); @chgrp($tmp, $group); @chmod($tmp, 0640);
    if (!@rename($tmp, $paths['state'])) { @unlink($tmp); throw new RuntimeException('Private-store runtime-state kon niet atomisch worden geactiveerd.'); }
}

function cut211StateLees(array $paths, string $tenant): array
{
    $pad = $paths['state'];
    if (is_link($pad) || !is_file($pad)) throw new RuntimeException('Actieve private-store runtime-state ontbreekt.');
    $raw = @file_get_contents($pad); $state = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($state)
        || (int)($state['schema'] ?? 0) !== 1
        || ($state['phase'] ?? '') !== 'json-to-pdo-cutover'
        || ($state['source_driver'] ?? '') !== 'json'
        || ($state['effective_driver'] ?? '') !== 'pdo'
        || !hash_equals($tenant, (string)($state['tenant_key'] ?? ''))) {
        throw new RuntimeException('Actieve private-store runtime-state is ongeldig.');
    }
    return $state;
}

function cut211RollbackState(array $paths): string
{
    $suffix = gmdate('Ymd\THis\Z');
    $archive = $paths['dir'] . '/private-store-runtime.rolled-back-' . $suffix . '.json';
    if (file_exists($archive) || is_link($archive) || !@rename($paths['state'], $archive)) throw new RuntimeException('Runtime-state kon niet atomisch naar rollbackarchief worden verplaatst.');
    @chmod($archive, 0640);
    return $archive;
}

$opt = getopt('', ['tenant-root:', 'mode:', 'proof:', 'help']);
if (isset($opt['help'])) {
    echo "Gebruik: php bin/apply-private-store-migration.php --tenant-root=/srv/verenigingen/club --mode=check|apply|rollback [--proof=...]\n";
    exit(0);
}
try {
    cut211Root();
    $tenantRootIn = rtrim(trim((string)($opt['tenant-root'] ?? '')), '/');
    $mode = strtolower(trim((string)($opt['mode'] ?? '')));
    if ($tenantRootIn === '' || !in_array($mode, ['check','apply','rollback'], true)) throw new RuntimeException('--tenant-root en geldige --mode zijn verplicht.');
    if (!str_starts_with($tenantRootIn, '/srv/verenigingen/')) throw new RuntimeException('Tenantroot valt buiten /srv/verenigingen.');
    if (is_link($tenantRootIn) || !is_dir($tenantRootIn)) throw new RuntimeException('Tenantroot ontbreekt of is een symlink.');
    $tenantRoot = realpath($tenantRootIn);
    if ($tenantRoot === false || !hash_equals($tenantRootIn, $tenantRoot)) throw new RuntimeException('Tenantroot moet fysiek canoniek zijn.');

    $deployment = runtime41DeploymentLees($tenantRoot . '/deployment.json');
    if (!hash_equals($tenantRoot, (string)$deployment['tenant_root'])) throw new RuntimeException('deployment.json hoort niet bij de opgegeven tenantroot.');
    $tenant = (string)$deployment['tenant_key']; $osUser = (string)$deployment['os_user'];
    $configPad = cut211VeiligBestand((string)$deployment['config_file'], 'Tenantconfig');
    $privateRoot = (string)$deployment['private_root'];
    $config = require $configPad;
    if (!is_array($config) || strtolower(trim((string)($config['opslag']['private_driver'] ?? ''))) !== 'json') {
        throw new RuntimeException('Storage-migratiecutover vereist een JSON-geprovisioneerde tenant.');
    }
    $manifestPad = cut211VeiligBestand($tenantRoot . '/tenant.json', 'Tenantmanifest');
    $manifest = json_decode((string)file_get_contents($manifestPad), true);
    if (!is_array($manifest) || ($manifest['private_driver'] ?? '') !== 'json' || !hash_equals($tenant, (string)($manifest['tenant_key'] ?? ''))) {
        throw new RuntimeException('Tenantmanifest is niet consistent JSON-geprovisioneerd.');
    }
    cut211VeiligBestand($tenantRoot . '/database/database-runtime.json', 'PDO migration-target runtime');
    if (!posix_getpwnam($osUser) || !posix_getgrnam($osUser)) throw new RuntimeException('Tenant-runtimeuser/group bestaat niet.');
    $paths = cut211RuntimeDir($tenantRoot, $osUser);

    if ($mode === 'check') {
        $inventory = cut211Worker($osUser, $configPad, $privateRoot, ['--mode=inventory']);
        $state = file_exists($paths['state']) ? cut211StateLees($paths, $tenant) : null;
        echo json_encode(['tenant_key'=>$tenant,'configured_driver'=>'json','effective_driver'=>$state ? 'pdo':'json','inventory'=>$inventory], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
        exit(0);
    }

    cut211MarkerAan($paths, $tenant, $osUser, $mode);
    $markerMoetUit = true;
    try {
        if ($mode === 'apply') {
            if (file_exists($paths['state']) || is_link($paths['state'])) throw new RuntimeException('Tenant heeft al een storage runtime-state; apply geweigerd.');
            $proof = cut211Worker($osUser, $configPad, $privateRoot, ['--mode=apply']);
            $verify = cut211Worker($osUser, $configPad, $privateRoot, ['--mode=verify', '--proof=' . (string)$proof['proof_path']]);
            if (!hash_equals((string)$proof['proof_sha256'], (string)$verify['proof_sha256'])
                || !hash_equals((string)$proof['target_aggregate_sha256'], (string)$verify['target_aggregate_sha256'])) {
                throw new RuntimeException('Tweede migratieverificatie wijkt af; cutover geweigerd.');
            }
            cut211StateSchrijf($paths, $tenant, $osUser, $verify);
            cut211MarkerUit($paths); $markerMoetUit = false;
            echo "MIGRATION CUTOVER OK\n";
            echo json_encode(['tenant_key'=>$tenant,'effective_driver'=>'pdo','proof_path'=>$verify['proof_path'],'proof_sha256'=>$verify['proof_sha256'],'target_aggregate_sha256'=>$verify['target_aggregate_sha256']], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            exit(0);
        }

        $state = cut211StateLees($paths, $tenant);
        $proofPad = trim((string)($opt['proof'] ?? ''));
        if ($proofPad === '') $proofPad = (string)($state['proof_path'] ?? '');
        if (!hash_equals((string)($state['proof_path'] ?? ''), $proofPad)) throw new RuntimeException('Rollbackproof wijkt af van de actieve runtime-state.');
        $verify = cut211Worker($osUser, $configPad, $privateRoot, ['--mode=rollback-check', '--proof=' . $proofPad]);
        if (!hash_equals((string)$state['proof_sha256'], (string)$verify['proof_sha256'])
            || !hash_equals((string)$state['target_aggregate_sha256'], (string)$verify['target_aggregate_sha256'])
            || ($verify['rollback_safe'] ?? false) !== true) {
            throw new RuntimeException('Rollbackbewijs is niet exact gelijk aan de actieve cutover-state.');
        }
        $archive = cut211RollbackState($paths);
        cut211MarkerUit($paths); $markerMoetUit = false;
        echo "MIGRATION ROLLBACK OK\n";
        echo json_encode(['tenant_key'=>$tenant,'effective_driver'=>'json','state_archive'=>$archive,'proof_sha256'=>$verify['proof_sha256']], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        exit(0);
    } finally {
        if ($markerMoetUit) cut211MarkerUit($paths);
    }
} catch (Throwable $e) {
    cut211Stop($e->getMessage());
}
