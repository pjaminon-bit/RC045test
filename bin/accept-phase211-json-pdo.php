<?php
// ============================================================
// #211 — echte VPS-acceptatie JSON → PostgreSQL/PDO
// ============================================================
// Root-only, vaste wegwerpfixture, uitsluitend vanuit de geïnstalleerde
// immutable host-engine. Geen door de operator gekozen tenant/paden/SQL.
// ============================================================
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Alleen via CLI beschikbaar.'); }

require_once dirname(__DIR__) . '/app/deployment/process-runner.php';
require_once dirname(__DIR__) . '/app/deployment/database-contract.php';
require_once dirname(__DIR__) . '/app/core/tenant-runtime.php';
require_once dirname(__DIR__) . '/app/storage/private-json-pdo-migration.php';

const A211_TENANT = 'phase211acceptance';
const A211_TENANT_BASE = '/srv/verenigingen';
const A211_TENANT_URL = 'https://phase211acceptance.invalid';
const A211_EVIDENCE_BASE = '/var/lib/verenigingsplatform/phase211-acceptance';
const A211_FPM_BASE = '/var/lib/verenigingsplatform/phase211-acceptance-fpm';

function a211Stop(string $m, int $code = 1): never
{
    fwrite(STDERR, "FOUT: {$m}\n");
    exit($code);
}

function a211Meta(string $pad, int $mode, bool $dir, int|string $uid = 0, int|string $gid = 0): void
{
    $st = @lstat($pad);
    if (!is_array($st) || is_link($pad) || ($dir ? !is_dir($pad) : !is_file($pad))) throw new RuntimeException('Onveilig object: ' . $pad);
    $uidN = is_int($uid) ? $uid : (int)((@posix_getpwnam($uid)['uid'] ?? -1));
    $gidN = is_int($gid) ? $gid : (int)((@posix_getgrnam($gid)['gid'] ?? -1));
    if ($uidN < 0 || $gidN < 0 || (int)$st['uid'] !== $uidN || (int)$st['gid'] !== $gidN || (((int)$st['mode'] & 0777) !== $mode)) {
        throw new RuntimeException('Owner/group/mode wijkt af: ' . $pad);
    }
}

function a211SafeDir(string $pad, int $mode = 0700): void
{
    if (runtime41SymlinkInPad($pad) !== null) throw new RuntimeException('Symlink in acceptatiepad: ' . $pad);
    if (!is_dir($pad) && !@mkdir($pad, $mode, true) && !is_dir($pad)) throw new RuntimeException('Acceptatiemap kon niet worden aangemaakt: ' . $pad);
    if (!@chown($pad, 0) || !@chgrp($pad, 0) || !@chmod($pad, $mode)) throw new RuntimeException('Acceptatiemap kon niet root-only worden gemaakt: ' . $pad);
    a211Meta($pad, $mode, true);
}

function a211Run(array $cmd, ?string $stdin = null, int $timeout = 900, ?array $env = null): array
{
    [$code, $out, $err] = process521Run($cmd, $stdin, null, $env, $timeout, 4194304);
    return ['code' => $code, 'out' => $out, 'err' => $err];
}

function a211Must(array $r, string $label): string
{
    if ((int)$r['code'] !== 0) {
        $detail = trim((string)($r['err'] !== '' ? $r['err'] : $r['out']));
        throw new RuntimeException($label . ' faalde: ' . substr($detail, 0, 900));
    }
    return (string)$r['out'];
}

function a211Php(string $root, string $script, array $args = [], ?string $stdin = null, int $timeout = 900): array
{
    $target = $root . '/bin/' . $script;
    if (is_link($target) || !is_file($target)) throw new RuntimeException('Host-engine script ontbreekt: ' . $script);
    return a211Run(array_merge(['/usr/bin/php8.5', $target], $args), $stdin, $timeout);
}

function a211VerifyEngine(): array
{
    if (PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid') || posix_geteuid() !== 0) throw new RuntimeException('Acceptatie vereist Linux root.');
    $root = realpath(dirname(__DIR__));
    if (!is_string($root) || preg_match('#^/usr/local/libexec/verenigingsplatform/host-engine/([0-9a-f]{40})$#D', $root, $m) !== 1) {
        throw new RuntimeException('Acceptatie mag uitsluitend uit de geïnstalleerde immutable host-engine draaien.');
    }
    $commit = $m[1];
    a211Meta($root, 0555, true);
    foreach (['.host-engine-commit', '.host-engine-manifest.sha256'] as $name) a211Meta($root . '/' . $name, 0444, false);
    $marker = trim((string)@file_get_contents($root . '/.host-engine-commit'));
    if (!hash_equals($commit, $marker)) throw new RuntimeException('Host-engine commitmarker wijkt af.');

    $manifest = @file($root . '/.host-engine-manifest.sha256', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($manifest) || $manifest === []) throw new RuntimeException('Host-engine manifest is leeg.');
    foreach ($manifest as $regel) {
        if (preg_match('/^([0-9a-f]{64})  ([A-Za-z0-9._\/-]+)$/D', $regel, $mm) !== 1) throw new RuntimeException('Host-engine manifestregel is ongeldig.');
        $rel = $mm[2];
        if (str_starts_with($rel, '/') || str_contains('/' . $rel . '/', '/../') || str_contains('/' . $rel . '/', '/./')) throw new RuntimeException('Host-engine manifest bevat onveilig pad.');
        $pad = $root . '/' . $rel;
        if (is_link($pad) || !is_file($pad)) throw new RuntimeException('Host-engine manifestbestand ontbreekt: ' . $rel);
        $sha = @hash_file('sha256', $pad);
        if (!is_string($sha) || !hash_equals($mm[1], $sha)) throw new RuntimeException('Host-engine manifestdrift: ' . $rel);
    }

    $current = realpath('/srv/verenigingsplatform/current');
    $expected = '/srv/verenigingsplatform/releases/' . $commit;
    if (!is_string($current) || !hash_equals($expected, $current)) throw new RuntimeException('Actieve immutable release is niet exact dezelfde commit als de host-engine.');
    return ['root' => $root, 'commit' => $commit, 'current' => $current];
}

function a211Pg(string $sql, string $db = 'postgres'): string
{
    $r = a211Run(['/usr/sbin/runuser', '-u', 'postgres', '--', '/usr/bin/psql', '-X', '-w', '-v', 'ON_ERROR_STOP=1', '-At', '-d', $db, '-c', $sql]);
    return trim(a211Must($r, 'PostgreSQL-query'));
}

function a211PgExists(string $type, string $name): bool
{
    $lit = database45SqlLiteral($name);
    if ($type === 'database') return a211Pg("SELECT count(*) FROM pg_database WHERE datname={$lit}") === '1';
    if ($type === 'role') return a211Pg("SELECT count(*) FROM pg_roles WHERE rolname={$lit}") === '1';
    throw new RuntimeException('Onbekend databaseobjecttype.');
}

function a211PgMarker(string $type, string $name): string
{
    $lit = database45SqlLiteral($name);
    if ($type === 'database') return a211Pg("SELECT COALESCE(shobj_description(oid,'pg_database'),'') FROM pg_database WHERE datname={$lit}");
    if ($type === 'role') return a211Pg("SELECT COALESCE(shobj_description(oid,'pg_authid'),'') FROM pg_roles WHERE rolname={$lit}");
    throw new RuntimeException('Onbekend databaseobjecttype.');
}

function a211Json(string $raw, string $label): array
{
    try { $d = json_decode(trim($raw), true, 512, JSON_THROW_ON_ERROR); }
    catch (Throwable $e) { throw new RuntimeException($label . ' gaf geen geldige JSON.', 0, $e); }
    if (!is_array($d)) throw new RuntimeException($label . ' gaf geen JSON-object.');
    return $d;
}

function a211LastJson(string $raw, string $label): array
{
    $lines = preg_split('/\r\n|\n|\r/', trim($raw)) ?: [];
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        $line = trim((string)$lines[$i]);
        if ($line === '' || $line[0] !== '{') continue;
        try { $d = json_decode($line, true, 512, JSON_THROW_ON_ERROR); }
        catch (Throwable $ignored) { continue; }
        if (is_array($d)) return $d;
    }
    throw new RuntimeException($label . ' mist machineleesbaar JSON-bewijs.');
}

function a211WriteEvidence(string $pad, array $data): void
{
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $tmp = dirname($pad) . '/.' . basename($pad) . '.tmp.' . bin2hex(random_bytes(6));
    if (@file_put_contents($tmp, $json, LOCK_EX) === false || !@chown($tmp, 0) || !@chgrp($tmp, 0) || !@chmod($tmp, 0600)) {
        @unlink($tmp); throw new RuntimeException('Acceptatiebewijs kon niet veilig worden voorbereid.');
    }
    if (is_link($pad) || !@rename($tmp, $pad)) { @unlink($tmp); throw new RuntimeException('Acceptatiebewijs kon niet atomisch worden geplaatst.'); }
    a211Meta($pad, 0600, false);
}

function a211RetainProof(string $source, string $target, string $expectedSha): void
{
    if (!preg_match('/^[0-9a-f]{64}$/D', $expectedSha) || is_link($source) || !is_file($source)) {
        throw new RuntimeException('Finale migratieproof kan niet veilig worden bewaard.');
    }
    $sourceSha = @hash_file('sha256', $source);
    if (!is_string($sourceSha) || !hash_equals($expectedSha, $sourceSha)) throw new RuntimeException('Finale migratieproof wijkt vóór retentie af.');
    if (is_link($target) || file_exists($target)) throw new RuntimeException('Retained proof-doel bestaat al of is onveilig.');
    $raw = @file_get_contents($source);
    if (!is_string($raw)) throw new RuntimeException('Finale migratieproof kon niet worden gelezen voor retentie.');
    $tmp = dirname($target) . '/.' . basename($target) . '.tmp.' . bin2hex(random_bytes(6));
    if (@file_put_contents($tmp, $raw, LOCK_EX) === false || !@chown($tmp, 0) || !@chgrp($tmp, 0) || !@chmod($tmp, 0600)) {
        @unlink($tmp); throw new RuntimeException('Retained proof kon niet root-only worden voorbereid.');
    }
    if (!@rename($tmp, $target)) { @unlink($tmp); throw new RuntimeException('Retained proof kon niet atomisch worden geplaatst.'); }
    a211Meta($target, 0600, false);
    $targetSha = @hash_file('sha256', $target);
    if (!is_string($targetSha) || !hash_equals($expectedSha, $targetSha)) throw new RuntimeException('Retained proof wijkt na schrijven af.');
}

function a211TreeDelete(string $root): void
{
    if (is_link($root) || !is_dir($root)) throw new RuntimeException('Fixture-root is geen veilige directory.');
    $real = realpath($root);
    $expected = A211_TENANT_BASE . '/' . A211_TENANT;
    if (!is_string($real) || !hash_equals($expected, $real)) throw new RuntimeException('Fixture-root valt buiten de vaste deleteboundary.');
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $info) {
        $p = $info->getPathname();
        if (is_link($p)) throw new RuntimeException('Symlink in fixtureboom; cleanup geweigerd: ' . $p);
        if ($info->isDir()) { if (!@rmdir($p)) throw new RuntimeException('Fixturemap kon niet worden verwijderd: ' . $p); }
        elseif ($info->isFile()) { if (!@unlink($p)) throw new RuntimeException('Fixturebestand kon niet worden verwijderd: ' . $p); }
        else throw new RuntimeException('Onverwacht object in fixtureboom: ' . $p);
    }
    if (!@rmdir($root)) throw new RuntimeException('Fixture-root kon niet worden verwijderd.');
}

function a211Cleanup(array $ctx): void
{
    $tenant = A211_TENANT;
    $tenantRoot = A211_TENANT_BASE . '/' . $tenant;
    $expectedMarker = database45Marker($tenant);
    $db = database45DatabaseNaam($tenant);
    $owner = database45OwnerRol($tenant);
    $user = runtime41VerwachteOsUser($tenant);
    if (!database45PgIdentifier($db) || !database45PgIdentifier($owner) || !database45PgIdentifier($user)) throw new RuntimeException('Afgeleide cleanup-identiteiten zijn ongeldig.');

    $plan = null;
    $planPad = $tenantRoot . '/database/database-plan.json';
    if (is_file($planPad) && !is_link($planPad)) {
        $planRaw = @file_get_contents($planPad);
        $plan = is_string($planRaw) ? json_decode($planRaw, true) : null;
        if (!is_array($plan) || !hash_equals($tenant, (string)($plan['tenant_key'] ?? ''))
            || !hash_equals($db, (string)($plan['isolation']['database'] ?? ''))
            || !hash_equals($owner, (string)($plan['isolation']['owner_role'] ?? ''))
            || !hash_equals($user, (string)($plan['isolation']['app_role'] ?? ''))) {
            throw new RuntimeException('Databaseplan is niet exact aan de acceptancefixture gebonden; cleanup geweigerd.');
        }
    }

    foreach ([['database',$db],['role',$owner],['role',$user]] as [$type,$name]) {
        if (a211PgExists($type, $name) && !hash_equals($expectedMarker, a211PgMarker($type, $name))) {
            throw new RuntimeException('PostgreSQL-object heeft onverwachte tenantmarker; cleanup geweigerd: ' . $name);
        }
    }

    if (a211PgExists('role', $user)) a211Pg('ALTER ROLE ' . $user . ' NOLOGIN');
    if (a211PgExists('database', $db)) {
        a211Pg('SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE pid<>pg_backend_pid() AND datname=' . database45SqlLiteral($db));
        a211Pg('DROP DATABASE ' . $db);
    }
    if (a211PgExists('role', $user)) a211Pg('DROP ROLE ' . $user);
    if (a211PgExists('role', $owner)) a211Pg('DROP ROLE ' . $owner);

    $hba = '/etc/verenigingsplatform/postgresql/pg_hba.d/100-vp-' . $tenant . '-peer.conf';
    if (file_exists($hba) || is_link($hba)) {
        if ($plan === null || is_link($hba) || !is_file($hba)) throw new RuntimeException('Tenant-HBA bestaat zonder veilig acceptanceplan.');
        $expectedHba = database45HbaConfig($plan);
        $actualHba = @file_get_contents($hba);
        $pg = @posix_getgrnam('postgres');
        if (!is_string($actualHba) || !hash_equals(hash('sha256', $expectedHba), hash('sha256', $actualHba)) || !is_array($pg)) {
            throw new RuntimeException('Tenant-HBA wijkt af; cleanup geweigerd.');
        }
        a211Meta($hba, 0640, false, 0, (int)$pg['gid']);
        if (!@unlink($hba)) throw new RuntimeException('Tenant-HBA kon niet worden verwijderd.');
        if (a211Pg('SELECT pg_reload_conf()') !== 't') throw new RuntimeException('PostgreSQL HBA reload faalde tijdens cleanup.');
    }

    $runtimePlanPad = $tenantRoot . '/runtime/runtime-plan.json';
    if (isset($ctx['fpm_dir']) && is_string($ctx['fpm_dir']) && is_dir($ctx['fpm_dir'])) {
        if (!str_starts_with($ctx['fpm_dir'], A211_FPM_BASE . '/run-') || is_link($ctx['fpm_dir'])) throw new RuntimeException('Tijdelijke FPM-map valt buiten acceptanceboundary.');
        if (is_file($runtimePlanPad) && !is_link($runtimePlanPad)) {
            $rp = json_decode((string)file_get_contents($runtimePlanPad), true);
            if (!is_array($rp) || !hash_equals($tenant, (string)($rp['tenant_key'] ?? ''))) throw new RuntimeException('Runtimeplan wijkt af tijdens cleanup.');
            $filename = (string)($rp['php_fpm']['pool_config_filename'] ?? '');
            if ($filename === '' || basename($filename) !== $filename) throw new RuntimeException('Runtimeplan bevat onveilige FPM-bestandsnaam.');
            $target = $ctx['fpm_dir'] . '/' . $filename;
            if (file_exists($target) || is_link($target)) {
                $source = (string)($rp['bundle']['php_fpm_file'] ?? '');
                if (is_link($target) || !is_file($target) || is_link($source) || !is_file($source)) throw new RuntimeException('Tijdelijke FPM-config is onveilig.');
                $a = @file_get_contents($target); $b = @file_get_contents($source);
                if (!is_string($a) || !is_string($b) || !hash_equals(hash('sha256', $a), hash('sha256', $b))) throw new RuntimeException('Tijdelijke FPM-config wijkt af; cleanup geweigerd.');
                if (!@unlink($target)) throw new RuntimeException('Tijdelijke FPM-config kon niet worden verwijderd.');
            }
        }
        if ((scandir($ctx['fpm_dir']) ?: []) !== ['.','..']) throw new RuntimeException('Tijdelijke FPM-map bevat onverwachte objecten.');
        if (!@rmdir($ctx['fpm_dir'])) throw new RuntimeException('Tijdelijke FPM-map kon niet worden verwijderd.');
    }

    if (is_array(@posix_getpwnam($user))) {
        $p = @posix_getpwnam($user); $g = @posix_getgrnam($user);
        if (!is_array($p) || !is_array($g) || (int)$p['gid'] !== (int)$g['gid'] || (string)$p['shell'] !== '/usr/sbin/nologin') {
            throw new RuntimeException('Fixture Linux-user/group wijkt af; cleanup geweigerd.');
        }
        $pgrep = a211Run(['/usr/bin/pgrep', '-u', $user]);
        if ((int)$pgrep['code'] === 0 && trim((string)$pgrep['out']) !== '') throw new RuntimeException('Fixture Linux-user heeft nog processen; cleanup geweigerd.');
        if (!in_array((int)$pgrep['code'], [0,1], true)) throw new RuntimeException('Fixture processen konden niet betrouwbaar worden gecontroleerd.');
        a211Must(a211Run(['/usr/sbin/userdel', $user]), 'Fixture userdel');
    }
    if (is_array(@posix_getgrnam($user))) a211Must(a211Run(['/usr/sbin/groupdel', $user]), 'Fixture groupdel');

    if (file_exists($tenantRoot) || is_link($tenantRoot)) a211TreeDelete($tenantRoot);

    foreach ([['database',$db],['role',$owner],['role',$user]] as [$type,$name]) {
        if (a211PgExists($type, $name)) throw new RuntimeException('Cleanup liet PostgreSQL-object achter: ' . $name);
    }
    if (is_array(@posix_getpwnam($user)) || is_array(@posix_getgrnam($user)) || file_exists($tenantRoot) || is_link($tenantRoot)) {
        throw new RuntimeException('Cleanup liet fixture-runtimeobjecten achter.');
    }
}

function a211Preflight(): array
{
    $tenantRoot = A211_TENANT_BASE . '/' . A211_TENANT;
    $user = runtime41VerwachteOsUser(A211_TENANT);
    $db = database45DatabaseNaam(A211_TENANT);
    $owner = database45OwnerRol(A211_TENANT);
    if (!is_dir(A211_TENANT_BASE) || is_link(A211_TENANT_BASE) || realpath(A211_TENANT_BASE) !== A211_TENANT_BASE) throw new RuntimeException('Tenantbasis is niet veilig canoniek.');
    if (file_exists($tenantRoot) || is_link($tenantRoot)) throw new RuntimeException('Vaste acceptance-tenant bestaat al; niets gewijzigd.');
    if (is_array(@posix_getpwnam($user)) || is_array(@posix_getgrnam($user))) throw new RuntimeException('Vaste acceptance Linux-identiteit bestaat al; niets gewijzigd.');
    foreach ([['database',$db],['role',$owner],['role',$user]] as [$type,$name]) if (a211PgExists($type,$name)) throw new RuntimeException('Vast acceptance PostgreSQL-object bestaat al; niets gewijzigd: ' . $name);
    $hba = '/etc/verenigingsplatform/postgresql/pg_hba.d/100-vp-' . A211_TENANT . '-peer.conf';
    if (file_exists($hba) || is_link($hba)) throw new RuntimeException('Vaste acceptance HBA bestaat al; niets gewijzigd.');
    return ['tenant_root'=>$tenantRoot,'user'=>$user,'database'=>$db,'owner_role'=>$owner,'hba'=>$hba];
}

$argv = $_SERVER['argv'] ?? [];
if (count($argv) === 2 && $argv[1] === '--help') {
    echo "Gebruik: sudo /usr/bin/php8.5 <immutable-host-engine>/bin/accept-phase211-json-pdo.php --run\n";
    echo "Vaste fixture: tenant=" . A211_TENANT . "; geen operatorgekozen paden, namen of SQL.\n";
    exit(0);
}
if (count($argv) !== 2 || $argv[1] !== '--run') a211Stop('Exact --run is verplicht; deze acceptance-tool accepteert geen variabele invoer.', 64);

$evidence = [
    'schema'=>1,
    'phase'=>'211-vps-json-pdo-acceptance',
    'tenant_key'=>A211_TENANT,
    'started_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),
    'result'=>'running',
    'cleanup'=>'not-started',
];
$ctx = [];
$evidencePad = null;
$mainError = null;
try {
    $engine = a211VerifyEngine();
    $evidence['engine_commit'] = $engine['commit'];
    $evidence['release_commit'] = basename($engine['current']);
    $pre = a211Preflight();
    $ctx += $pre;

    a211SafeDir(A211_EVIDENCE_BASE, 0700);
    a211SafeDir(A211_FPM_BASE, 0700);
    $runId = gmdate('Ymd\THis\Z') . '-' . substr(bin2hex(random_bytes(8)), 0, 12);
    $fpmDir = A211_FPM_BASE . '/run-' . $runId;
    a211SafeDir($fpmDir, 0700);
    $ctx['fpm_dir'] = $fpmDir;
    $evidencePad = A211_EVIDENCE_BASE . '/acceptance-' . $runId . '.json';
    $evidence['evidence_path'] = $evidencePad;

    $root = $engine['root'];
    $tenantRoot = $pre['tenant_root'];
    $config = $tenantRoot . '/config.php';
    $private = $tenantRoot . '/private';
    $deployment = $tenantRoot . '/deployment.json';
    $runtimePlan = $tenantRoot . '/runtime/runtime-plan.json';
    $databasePlan = $tenantRoot . '/database/database-plan.json';

    a211Must(a211Php($root, 'provision-tenant.php', [
        '--key=' . A211_TENANT,
        '--name=Phase 211 migration acceptance',
        '--url=' . A211_TENANT_URL,
        '--root=' . A211_TENANT_BASE,
        '--driver=json',
        '--modules=website',
    ]), 'JSON fixture provisioning');

    $password = 'A211!' . bin2hex(random_bytes(32));
    try { a211Must(a211Php($root, 'bootstrap-tenant-admin.php', ['--config=' . $config, '--password-stdin'], $password . "\n"), 'Fixture masterbootstrap'); }
    finally { $password = str_repeat("\0", strlen($password)); unset($password); }

    $fixture = [
        'leden' => [[
            'id'=>'phase211-lid-1','naam'=>'Alpha Acceptatie','email'=>'alpha@phase211.invalid','actief'=>true,
        ],[
            'id'=>'phase211-lid-2','naam'=>'Bèta Acceptatie','email'=>'beta@phase211.invalid','actief'=>false,
        ]],
        'taken' => [[
            'id'=>'phase211-taak-1','titel'=>'Controle migratie','status'=>'open','volgorde'=>1,
        ],[
            'id'=>'phase211-taak-2','titel'=>'Rollback bewijs','status'=>'gereed','volgorde'=>2,
        ]],
        'instellingen' => ['schema'=>1,'nested'=>['b'=>2,'a'=>1],'feature'=>true],
    ];
    foreach ($fixture as $key => $data) {
        $pad = $private . '/collections/' . $key . '.json';
        $raw = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (@file_put_contents($pad, $raw, LOCK_EX) === false || !@chmod($pad, 0640)) throw new RuntimeException('Fixturecollectie kon niet worden geschreven: ' . $key);
    }
    $sourceBefore = privateMigrationSamenvatting(privateMigrationInventory($private));
    $evidence['source_before'] = $sourceBefore;

    a211Must(a211Php($root, 'prepare-vps-deployment.php', ['--config='.$config,'--app-root=/srv/verenigingsplatform/current']), 'Deploymentplan');
    a211Must(a211Php($root, 'prepare-vps-runtime.php', ['--deployment='.$deployment,'--php-version=8.5']), 'Runtimeplan');
    a211Must(a211Php($root, 'apply-vps-runtime.php', ['--plan='.$runtimePlan,'--check']), 'Runtime check');
    a211Must(a211Php($root, 'apply-vps-runtime.php', ['--plan='.$runtimePlan,'--apply','--fpm-pool-dir='.$fpmDir]), 'Runtime apply');

    a211Must(a211Php($root, 'prepare-vps-database.php', ['--runtime-plan='.$runtimePlan,'--migration-target']), 'Migration-target databaseplan');
    a211Must(a211Php($root, 'apply-vps-database.php', ['--database-plan='.$databasePlan,'--check']), 'Database check');
    a211Must(a211Php($root, 'apply-vps-database.php', ['--database-plan='.$databasePlan,'--apply'], null, 1200), 'Database apply');

    $plan = json_decode((string)file_get_contents($databasePlan), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($plan) || !hash_equals(A211_TENANT, (string)($plan['tenant_key'] ?? '')) || (($plan['source']['migration_target'] ?? false) !== true)) {
        throw new RuntimeException('Databaseplan is niet aantoonbaar een JSON migration-target.');
    }
    if (!hash_equals($pre['database'], (string)$plan['isolation']['database']) || !hash_equals($pre['user'], (string)$plan['isolation']['app_role'])) {
        throw new RuntimeException('Databaseplan wijkt af van deterministic acceptance-identiteiten.');
    }

    $checkBefore = a211Json(a211Must(a211Php($root, 'apply-private-store-migration.php', ['--tenant-root='.$tenantRoot,'--mode=check']), 'Pre-cutover check'), 'Pre-cutover check');
    if (($checkBefore['configured_driver'] ?? '') !== 'json' || ($checkBefore['effective_driver'] ?? '') !== 'json') throw new RuntimeException('Pre-cutover is niet configured=json/effective=json.');
    $evidence['pre_cutover'] = $checkBefore;

    $poisonSql = "INSERT INTO vst.vereniging_private_store (tenant_key,collection_key,payload,updated_at) VALUES (" . database45SqlLiteral(A211_TENANT) . ",'phase211_poison','[]',now())";
    a211Must(a211Run(['/usr/sbin/runuser','-u',$pre['user'],'--','/usr/bin/psql','-X','-w','-h','/var/run/postgresql','-d',$pre['database'],'-v','ON_ERROR_STOP=1','-c',$poisonSql]), 'Afwijkende target injecteren');
    $negative = a211Php($root, 'apply-private-store-migration.php', ['--tenant-root='.$tenantRoot,'--mode=apply'], null, 1200);
    if ((int)$negative['code'] === 0 || !str_contains((string)$negative['err'], 'niet leeg en wijkt af')) throw new RuntimeException('Negatieve liveproef faalde niet op de verwachte afwijkende PDO-target.');
    if (file_exists($tenantRoot . '/storage-runtime/private-store-migration.active') || is_link($tenantRoot . '/storage-runtime/private-store-migration.active')) {
        throw new RuntimeException('Negatieve liveproef liet de migratiemarker achter.');
    }
    $checkFailed = a211Json(a211Must(a211Php($root, 'apply-private-store-migration.php', ['--tenant-root='.$tenantRoot,'--mode=check']), 'Check na gefaalde apply'), 'Check na gefaalde apply');
    $sourceAfterFail = privateMigrationSamenvatting(privateMigrationInventory($private));
    if (($checkFailed['effective_driver'] ?? '') !== 'json' || $sourceAfterFail !== $sourceBefore) throw new RuntimeException('Gefaalde migratie liet JSON niet exact actief/intact.');
    $evidence['negative_apply'] = ['expected_failure'=>true,'effective_driver'=>'json','source_unchanged'=>true];

    $clearSql = 'DELETE FROM vst.vereniging_private_store WHERE tenant_key=' . database45SqlLiteral(A211_TENANT);
    a211Must(a211Run(['/usr/sbin/runuser','-u',$pre['user'],'--','/usr/bin/psql','-X','-w','-h','/var/run/postgresql','-d',$pre['database'],'-v','ON_ERROR_STOP=1','-c',$clearSql]), 'Afwijkende target opruimen');
    if (a211Pg('SELECT count(*) FROM vst.vereniging_private_store WHERE tenant_key=' . database45SqlLiteral(A211_TENANT), $pre['database']) !== '0') throw new RuntimeException('PDO-target is niet leeg na negatieve proef.');

    $firstApplyRaw = a211Must(a211Php($root, 'apply-private-store-migration.php', ['--tenant-root='.$tenantRoot,'--mode=apply'], null, 1200), 'Eerste cutover');
    $firstApply = a211LastJson($firstApplyRaw, 'Eerste cutover');
    $checkPdo1 = a211Json(a211Must(a211Php($root, 'apply-private-store-migration.php', ['--tenant-root='.$tenantRoot,'--mode=check']), 'PDO check 1'), 'PDO check 1');
    if (($checkPdo1['effective_driver'] ?? '') !== 'pdo') throw new RuntimeException('Eerste cutover activeerde PDO niet.');
    $probe1 = a211Must(a211Run([
        '/usr/sbin/runuser','-u',$pre['user'],'--','/usr/bin/env','-i',
        'PATH=/usr/sbin:/usr/bin:/sbin:/bin',
        'VERENIGING_REQUIRE_TENANT_CONFIG=1',
        'VERENIGING_CONFIG_FILE='.$config,
        'VERENIGING_PRIVATE_ROOT='.$private,
        '/usr/bin/php8.5','/srv/verenigingsplatform/current/bin/check-release-tenant.php','--expected-tenant='.A211_TENANT,
    ]), 'Read-only applicatieprobe na eerste cutover');
    if (!str_contains($probe1, 'CANDIDATE OK')) throw new RuntimeException('Applicatieprobe bevestigde PDO niet.');
    $evidence['first_cutover'] = $firstApply + ['read_only_application_probe'=>'ok'];

    $rollbackRaw = a211Must(a211Php($root, 'apply-private-store-migration.php', ['--tenant-root='.$tenantRoot,'--mode=rollback'], null, 1200), 'Rollback');
    $rollback = a211LastJson($rollbackRaw, 'Rollback');
    $checkJson2 = a211Json(a211Must(a211Php($root, 'apply-private-store-migration.php', ['--tenant-root='.$tenantRoot,'--mode=check']), 'JSON check na rollback'), 'JSON check na rollback');
    $sourceAfterRollback = privateMigrationSamenvatting(privateMigrationInventory($private));
    if (($checkJson2['effective_driver'] ?? '') !== 'json' || $sourceAfterRollback !== $sourceBefore) throw new RuntimeException('Rollback herstelde JSON niet exact.');
    $evidence['rollback'] = $rollback + ['source_unchanged'=>true,'effective_driver'=>'json'];

    $finalApplyRaw = a211Must(a211Php($root, 'apply-private-store-migration.php', ['--tenant-root='.$tenantRoot,'--mode=apply'], null, 1200), 'Finale cutover');
    $finalApply = a211LastJson($finalApplyRaw, 'Finale cutover');
    $checkPdo2 = a211Json(a211Must(a211Php($root, 'apply-private-store-migration.php', ['--tenant-root='.$tenantRoot,'--mode=check']), 'Finale PDO check'), 'Finale PDO check');
    if (($checkPdo2['effective_driver'] ?? '') !== 'pdo') throw new RuntimeException('Finale cutover activeerde PDO niet.');
    $probe2 = a211Must(a211Run([
        '/usr/sbin/runuser','-u',$pre['user'],'--','/usr/bin/env','-i',
        'PATH=/usr/sbin:/usr/bin:/sbin:/bin',
        'VERENIGING_REQUIRE_TENANT_CONFIG=1',
        'VERENIGING_CONFIG_FILE='.$config,
        'VERENIGING_PRIVATE_ROOT='.$private,
        '/usr/bin/php8.5','/srv/verenigingsplatform/current/bin/check-release-tenant.php','--expected-tenant='.A211_TENANT,
    ]), 'Read-only applicatieprobe na finale cutover');
    if (!str_contains($probe2, 'CANDIDATE OK')) throw new RuntimeException('Finale applicatieprobe bevestigde PDO niet.');

    $proofPath = (string)($finalApply['proof_path'] ?? '');
    $proofSha = (string)($finalApply['proof_sha256'] ?? '');
    if ($proofPath === '' || !is_file($proofPath) || is_link($proofPath) || !preg_match('/^[0-9a-f]{64}$/D', $proofSha)
        || !hash_equals($proofSha, (string)hash_file('sha256', $proofPath))) throw new RuntimeException('Finale cutoverproof ontbreekt of hash wijkt af.');
    $retainedProof = A211_EVIDENCE_BASE . '/acceptance-' . $runId . '-migration-proof.json';
    a211RetainProof($proofPath, $retainedProof, $proofSha);
    $evidence['final_cutover'] = $finalApply + ['read_only_application_probe'=>'ok','retained_proof_path'=>$retainedProof];
    $evidence['source_final'] = privateMigrationSamenvatting(privateMigrationInventory($private));
    if ($evidence['source_final'] !== $sourceBefore) throw new RuntimeException('JSON rollbackanker wijzigde tijdens succesvolle acceptatie.');
    $evidence['result'] = 'acceptance-passed-before-cleanup';
    a211WriteEvidence($evidencePad, $evidence);
} catch (Throwable $e) {
    $mainError = $e;
    $evidence['result'] = 'failed';
    $evidence['error_class'] = get_class($e);
    $evidence['error'] = substr($e->getMessage(), 0, 1200);
    if (is_string($evidencePad)) { try { a211WriteEvidence($evidencePad, $evidence); } catch (Throwable $ignored) {} }
} finally {
    try {
        // Alleen opruimen nadat de unieke preflight is gepasseerd en de runner zelf
        // fixturecontext heeft opgebouwd. Een vooraf bestaand object wordt nooit hier
        // binnengetrokken.
        if (isset($ctx['tenant_root'])) a211Cleanup($ctx);
        $evidence['cleanup'] = 'ok';
    } catch (Throwable $cleanupError) {
        $evidence['cleanup'] = 'failed';
        $evidence['cleanup_error'] = substr($cleanupError->getMessage(), 0, 1200);
        if ($mainError === null) $mainError = $cleanupError;
    }
    $evidence['completed_at_utc'] = gmdate('Y-m-d\TH:i:s\Z');
    if ($mainError === null) $evidence['result'] = 'success';
    if (is_string($evidencePad)) { try { a211WriteEvidence($evidencePad, $evidence); } catch (Throwable $writeError) { if ($mainError === null) $mainError = $writeError; } }
}

if ($mainError !== null) a211Stop($mainError->getMessage());
echo "PHASE211 VPS MIGRATION ACCEPTANCE OK\n";
echo json_encode([
    'tenant_key'=>A211_TENANT,
    'engine_commit'=>$evidence['engine_commit'] ?? null,
    'release_commit'=>$evidence['release_commit'] ?? null,
    'proof_sha256'=>$evidence['final_cutover']['proof_sha256'] ?? null,
    'target_aggregate_sha256'=>$evidence['final_cutover']['target_aggregate_sha256'] ?? null,
    'retained_proof_path'=>$evidence['final_cutover']['retained_proof_path'] ?? null,
    'evidence_path'=>$evidencePad,
    'cleanup'=>$evidence['cleanup'],
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
