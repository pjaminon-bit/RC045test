<?php
// ============================================================
// Fase 4.5 — valideer/provision PostgreSQL tenantdatabase
// ============================================================
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Alleen via CLI beschikbaar.');
}

require_once dirname(__DIR__) . '/app/deployment/database-contract.php';

function apply45Stop(string $melding, int $code = 1): never
{
    fwrite(STDERR, "FOUT: {$melding}\n");
    exit($code);
}

function apply45Help(): void
{
    echo "Gebruik:\n";
    echo "  php bin/apply-vps-database.php --database-plan=/srv/verenigingen/club/database/database-plan.json --check\n";
    echo "  sudo php bin/apply-vps-database.php --database-plan=/srv/verenigingen/club/database/database-plan.json --apply\n\n";
    echo "--check valideert alleen de secretvrije bundle en heeft geen root/PostgreSQL nodig.\n";
    echo "--apply vereist Linux root, PostgreSQL >=16, socket-only PostgreSQL en de fase-4.1 tenant Linux-user.\n";
    echo "Er wordt bewust geen databasewachtwoord aangemaakt: lokale Unix-socket peer-auth bindt DB-login aan de kernel OS-user.\n";
}

function apply45Exec(array $command, ?string $stdin = null): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = @proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($proc)) throw new RuntimeException('Proces kon niet worden gestart: ' . (string)($command[0] ?? '?'));
    if ($stdin !== null) fwrite($pipes[0], $stdin);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $code = proc_close($proc);
    return [$code, is_string($stdout) ? trim($stdout) : '', is_string($stderr) ? trim($stderr) : ''];
}

function apply45PgQuery(string $sql, string $database = 'postgres'): string
{
    [$code, $out, $err] = apply45Exec([
        'runuser', '-u', 'postgres', '--', 'psql', '-X', '-w', '-v', 'ON_ERROR_STOP=1', '-At', '-d', $database, '-c', $sql,
    ]);
    if ($code !== 0) throw new RuntimeException('PostgreSQL-query faalde: ' . ($err !== '' ? $err : $out));
    return trim($out);
}

function apply45PgScript(string $script, string $database): void
{
    [$code, $out, $err] = apply45Exec([
        'runuser', '-u', 'postgres', '--', 'psql', '-X', '-w', '-v', 'ON_ERROR_STOP=1', '-d', $database, '-f', '-',
    ], $script);
    if ($code !== 0) throw new RuntimeException('PostgreSQL-migratie faalde: ' . ($err !== '' ? $err : $out));
}

function apply45Bestaat(string $soort, string $naam): bool
{
    $literal = database45SqlLiteral($naam);
    if ($soort === 'role') $sql = "SELECT count(*) FROM pg_roles WHERE rolname={$literal}";
    elseif ($soort === 'database') $sql = "SELECT count(*) FROM pg_database WHERE datname={$literal}";
    else throw new RuntimeException('Onbekend PostgreSQL objecttype.');
    return apply45PgQuery($sql) === '1';
}

function apply45Marker(string $soort, string $naam): string
{
    $literal = database45SqlLiteral($naam);
    if ($soort === 'role') return apply45PgQuery("SELECT COALESCE(shobj_description(oid,'pg_authid'),'') FROM pg_roles WHERE rolname={$literal}");
    if ($soort === 'database') return apply45PgQuery("SELECT COALESCE(shobj_description(oid,'pg_database'),'') FROM pg_database WHERE datname={$literal}");
    throw new RuntimeException('Onbekend PostgreSQL objecttype.');
}

function apply45VeiligSchrijf(string $pad, string $inhoud, int $mode, ?int $uid = null, int|string|null $gid = null): void
{
    if (is_link($pad)) throw new RuntimeException('Doel mag geen symlink zijn: ' . $pad);
    $map = dirname($pad);
    if (!is_dir($map) || is_link($map)) throw new RuntimeException('Schrijfmap is niet veilig: ' . $map);
    $tmp = $map . '/.' . basename($pad) . '.tmp.' . bin2hex(random_bytes(8));
    if (@file_put_contents($tmp, $inhoud, LOCK_EX) === false) throw new RuntimeException('Tijdelijk bestand kon niet worden geschreven: ' . $pad);
    @chmod($tmp, $mode);
    if ($uid !== null && !@chown($tmp, $uid)) { @unlink($tmp); throw new RuntimeException('Owner kon niet worden gezet op tijdelijk bestand.'); }
    if ($gid !== null && !@chgrp($tmp, $gid)) { @unlink($tmp); throw new RuntimeException('Group kon niet worden gezet op tijdelijk bestand.'); }
    clearstatcache(true, $pad);
    if (is_link($pad)) { @unlink($tmp); throw new RuntimeException('Doel werd tijdens write een symlink: ' . $pad); }
    if (!@rename($tmp, $pad)) { @unlink($tmp); throw new RuntimeException('Bestand kon niet atomisch worden geplaatst: ' . $pad); }
    @chmod($pad, $mode);
}

function apply45HbaInstalleer(array $plan): array
{
    $includeDir = (string)$plan['postgresql']['hba_include_dir'];
    $includeRegel = (string)$plan['postgresql']['hba_include_directive'];
    $tenantHba = $includeDir . '/' . (string)$plan['postgresql']['tenant_hba_filename'];
    $verwachtHba = database45HbaConfig($plan);

    $postgresGroep = @posix_getgrnam('postgres');
    if (!is_array($postgresGroep) || !isset($postgresGroep['gid'])) throw new RuntimeException('Linux group postgres bestaat niet.');
    $pgGid = (int)$postgresGroep['gid'];

    if (!is_dir('/etc/verenigingsplatform') && !@mkdir('/etc/verenigingsplatform', 0755, true)) throw new RuntimeException('/etc/verenigingsplatform kon niet worden aangemaakt.');
    if (!is_dir('/etc/verenigingsplatform/postgresql') && !@mkdir('/etc/verenigingsplatform/postgresql', 0750)) throw new RuntimeException('PostgreSQL platformconfigmap kon niet worden aangemaakt.');
    if (!is_dir($includeDir) && !@mkdir($includeDir, 0750)) throw new RuntimeException('PostgreSQL HBA include_dir kon niet worden aangemaakt.');
    foreach (['/etc/verenigingsplatform/postgresql', $includeDir] as $dir) {
        if (is_link($dir) || !@chown($dir, 0) || !@chgrp($dir, $pgGid) || !@chmod($dir, 0750)) {
            throw new RuntimeException('PostgreSQL platformconfigmap heeft onveilige ownership/rechten: ' . $dir);
        }
    }

    $oudeTenantBestond = is_file($tenantHba) && !is_link($tenantHba);
    $oudeTenant = $oudeTenantBestond ? @file_get_contents($tenantHba) : null;
    if (file_exists($tenantHba) && !$oudeTenantBestond) throw new RuntimeException('Tenant HBA-doel bestaat maar is geen regulier bestand.');
    if ($oudeTenantBestond && !is_string($oudeTenant)) throw new RuntimeException('Bestaande tenant HBA kon niet worden gelezen.');

    $hbaPad = apply45PgQuery('SHOW hba_file');
    $hbaPad = runtime41BestaandPad($hbaPad, 'PostgreSQL pg_hba.conf');
    $oudeMain = @file_get_contents($hbaPad);
    $stat = @stat($hbaPad);
    if (!is_string($oudeMain) || !is_array($stat)) throw new RuntimeException('pg_hba.conf kon niet veilig worden gelezen.');
    if (str_contains($oudeMain, '/etc/verenigingsplatform/postgresql') && !str_contains($oudeMain, $includeRegel)) {
        throw new RuntimeException('pg_hba.conf bevat een afwijkende verenigingsplatform-include; handmatige inspectie vereist.');
    }
    $regels = preg_split('/\R/', $oudeMain) ?: [];
    $regels = array_values(array_filter($regels, static fn($regel) => trim((string)$regel) !== $includeRegel));
    $nieuweMain = $includeRegel . "\n" . implode("\n", $regels);
    if (!str_ends_with($nieuweMain, "\n")) $nieuweMain .= "\n";

    apply45VeiligSchrijf($tenantHba, $verwachtHba, 0640, 0, $pgGid);
    if (!hash_equals($oudeMain, $nieuweMain)) {
        apply45VeiligSchrijf($hbaPad, $nieuweMain, ((int)$stat['mode']) & 0777, (int)$stat['uid'], (int)$stat['gid']);
    }

    try {
        $fouten = apply45PgQuery("SELECT count(*) FROM pg_hba_file_rules WHERE error IS NOT NULL");
        if ($fouten !== '0') throw new RuntimeException('pg_hba_file_rules meldt syntax-/configuratiefouten; reload geweigerd.');
        $tenantLiteral = database45SqlLiteral($tenantHba);
        $dbLiteral = database45SqlLiteral((string)$plan['isolation']['database']);
        $userLiteral = database45SqlLiteral((string)$plan['isolation']['app_role']);
        $allow = apply45PgQuery("SELECT count(*) FROM pg_hba_file_rules WHERE file_name={$tenantLiteral} AND type='local' AND database=ARRAY[{$dbLiteral}]::text[] AND user_name=ARRAY[{$userLiteral}]::text[] AND auth_method='peer'");
        $deny = apply45PgQuery("SELECT count(*) FROM pg_hba_file_rules WHERE file_name={$tenantLiteral} AND type='local' AND database=ARRAY['all']::text[] AND user_name=ARRAY[{$userLiteral}]::text[] AND auth_method='reject'");
        if ($allow !== '1' || $deny !== '1') throw new RuntimeException('Exacte tenant peer-allow + cross-database reject zijn niet zichtbaar in pg_hba_file_rules.');

        // Meerdere tenantbestanden delen dezelfde include_dir. Een tenant mag dus
        // na een eerder tenantbestand komen; alle platformregels samen moeten
        // vóór iedere regel buiten de gecontroleerde platform-include staan.
        $eersteTenant = apply45PgQuery("SELECT min(rule_number) FROM pg_hba_file_rules WHERE file_name={$tenantLiteral}");
        $platformPrefix = database45SqlLiteral(rtrim($includeDir, '/') . '/');
        $eersteBuitenPlatform = apply45PgQuery("SELECT min(rule_number) FROM pg_hba_file_rules WHERE strpos(file_name, {$platformPrefix}) <> 1");
        if ($eersteTenant === '' || ($eersteBuitenPlatform !== '' && (int)$eersteTenant > (int)$eersteBuitenPlatform)) {
            throw new RuntimeException('Platform-HBA include staat niet vóór de bestaande niet-platform HBA-regels.');
        }
        if (apply45PgQuery('SELECT pg_reload_conf()') !== 't') throw new RuntimeException('PostgreSQL HBA reload kon niet worden aangevraagd.');
    } catch (Throwable $e) {
        if ($oudeTenantBestond && is_string($oudeTenant)) apply45VeiligSchrijf($tenantHba, $oudeTenant, 0640, 0, $pgGid);
        elseif (is_file($tenantHba)) @unlink($tenantHba);
        if (!hash_equals($oudeMain, (string)@file_get_contents($hbaPad))) {
            apply45VeiligSchrijf($hbaPad, $oudeMain, ((int)$stat['mode']) & 0777, (int)$stat['uid'], (int)$stat['gid']);
        }
        try { apply45PgQuery('SELECT pg_reload_conf()'); } catch (Throwable $ignored) {}
        throw $e;
    }

    return ['hba_file' => $hbaPad, 'tenant_hba' => $tenantHba, 'old_main' => $oudeMain, 'old_tenant' => $oudeTenant, 'old_tenant_existed' => $oudeTenantBestond, 'stat' => $stat, 'pg_gid' => $pgGid];
}

function apply45HbaRollback(array $state): void
{
    $hbaPad = (string)$state['hba_file'];
    $tenantHba = (string)$state['tenant_hba'];
    $stat = (array)$state['stat'];
    if (($state['old_tenant_existed'] ?? false) === true && is_string($state['old_tenant'] ?? null)) {
        apply45VeiligSchrijf($tenantHba, (string)$state['old_tenant'], 0640, 0, (int)$state['pg_gid']);
    } elseif (is_file($tenantHba) && !is_link($tenantHba)) {
        @unlink($tenantHba);
    }
    apply45VeiligSchrijf($hbaPad, (string)$state['old_main'], ((int)$stat['mode']) & 0777, (int)$stat['uid'], (int)$stat['gid']);
    try { apply45PgQuery('SELECT pg_reload_conf()'); } catch (Throwable $ignored) {}
}

function apply45PeerCheck(array $plan): void
{
    $user = (string)$plan['isolation']['app_role'];
    $database = (string)$plan['isolation']['database'];
    [$code, $out, $err] = apply45Exec([
        'runuser', '-u', $user, '--', 'psql', '-X', '-w', '-h', '/var/run/postgresql', '-U', $user, '-d', $database, '-At', '-c',
        "SELECT current_database() || '|' || current_user || '|' || tenant_key || '|' || schema_version::text FROM vst.vereniging_schema_meta WHERE component='private_store'",
    ]);
    $verwacht = $database . '|' . $user . '|' . $plan['tenant_key'] . '|1';
    if ($code !== 0 || !hash_equals($verwacht, trim($out))) {
        throw new RuntimeException('Peer-connectivity/tenantmarker-check faalde: ' . ($err !== '' ? $err : $out));
    }

    [$crossCode] = apply45Exec([
        'runuser', '-u', $user, '--', 'psql', '-X', '-w', '-h', '/var/run/postgresql', '-U', $user, '-d', 'postgres', '-At', '-c', 'SELECT 1',
    ]);
    if ($crossCode === 0) throw new RuntimeException('Cross-database HBA-reject faalt: tenantuser kan postgres database bereiken.');
}

foreach ($_SERVER['argv'] ?? [] as $arg) {
    if (preg_match('/^--(?:password|pass|secret|dsn|db-user|db-password|token|pgpassword|pgpass)(?:=|$)/i', (string)$arg) === 1) {
        apply45Stop('Databasecredentials/secrets horen niet in fase-4.5 CLI-argumenten.');
    }
}

$opt = getopt('', ['database-plan:', 'check', 'apply', 'help']);
if (isset($opt['help'])) { apply45Help(); exit(0); }
$planPad = trim((string)($opt['database-plan'] ?? ''));
if ($planPad === '') apply45Stop('--database-plan=/absoluut/pad/database-plan.json is verplicht.');
if (isset($opt['check']) === isset($opt['apply'])) apply45Stop('Kies exact één van --check of --apply.');

try { $context = database45PlanLeesEnValideer($planPad); }
catch (Throwable $e) { apply45Stop($e->getMessage()); }
$plan = $context['plan'];

if (isset($opt['check'])) {
    echo 'OK: fase-4.5 databasebundle valide voor tenant ' . $plan['tenant_key'] . ".\n";
    echo 'PostgreSQL database=' . $plan['isolation']['database'] . ', app-role=' . $plan['isolation']['app_role'] . ', auth=peer.' . "\n";
    exit(0);
}

if (PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid') || posix_geteuid() !== 0) {
    apply45Stop('Database --apply vereist Linux root (EUID 0).');
}
if (!function_exists('posix_getpwnam') || !function_exists('posix_getgrnam')) apply45Stop('POSIX accountfuncties ontbreken.');
$appUser = (string)$plan['isolation']['app_role'];
$pw = @posix_getpwnam($appUser);
$gr = @posix_getgrnam($appUser);
if (!is_array($pw) || !is_array($gr) || (int)($pw['gid'] ?? -1) !== (int)($gr['gid'] ?? -2)) {
    apply45Stop('Fase-4.1 tenant Linux-user/group bestaat niet exact; pas 4.1 eerst op de VPS toe.');
}

try {
    foreach ([['which', 'psql'], ['which', 'runuser']] as $cmd) {
        [$code] = apply45Exec($cmd);
        if ($code !== 0) throw new RuntimeException($cmd[1] . ' ontbreekt op de VPS.');
    }
    $versie = (int)apply45PgQuery('SHOW server_version_num');
    if ($versie < 160000) throw new RuntimeException('Fase 4.5 vereist PostgreSQL 16 of nieuwer voor gecontroleerde HBA includes/rule_number-validatie.');

    // De productie-DB hoort uitsluitend lokaal via Unix sockets bereikbaar te
    // zijn. Daarmee kan een brede TCP/host HBA-regel nooit een tweede loginpad
    // naar de passwordloze tenantrollen openen.
    if (trim(apply45PgQuery('SHOW listen_addresses')) !== '') {
        throw new RuntimeException("Fase 4.5 vereist socket-only PostgreSQL: zet listen_addresses='' en herstart PostgreSQL gecontroleerd vóór --apply.");
    }
    $socketDirs = array_map(static fn($v) => trim($v, " \t\n\r\0\x0B\"'"), explode(',', apply45PgQuery('SHOW unix_socket_directories')));
    if (!in_array('/var/run/postgresql', $socketDirs, true)) throw new RuntimeException('PostgreSQL luistert niet op de verplichte Unix-socket /var/run/postgresql.');

    $owner = (string)$plan['isolation']['owner_role'];
    $database = (string)$plan['isolation']['database'];
    $marker = database45Marker((string)$plan['tenant_key']);
    foreach ([['role', $owner], ['role', $appUser], ['database', $database]] as [$soort, $naam]) {
        if (apply45Bestaat($soort, $naam) && !hash_equals($marker, apply45Marker($soort, $naam))) {
            throw new RuntimeException("Bestaande PostgreSQL {$soort} {$naam} is niet aan deze tenant gemarkeerd; geen wijzigingen uitgevoerd.");
        }
    }

    if (!apply45Bestaat('role', $owner)) {
        apply45PgQuery('CREATE ROLE ' . $owner . ' NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS; COMMENT ON ROLE ' . $owner . ' IS ' . database45SqlLiteral($marker));
    }
    if (!apply45Bestaat('role', $appUser)) {
        apply45PgQuery('CREATE ROLE ' . $appUser . ' LOGIN INHERIT NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS CONNECTION LIMIT 10 PASSWORD NULL; COMMENT ON ROLE ' . $appUser . ' IS ' . database45SqlLiteral($marker));
    }
    if (!apply45Bestaat('database', $database)) {
        apply45PgQuery('CREATE DATABASE ' . $database . ' OWNER ' . $owner . " TEMPLATE template0 ENCODING 'UTF8'");
        apply45PgQuery('COMMENT ON DATABASE ' . $database . ' IS ' . database45SqlLiteral($marker));
    }

    $appProps = apply45PgQuery("SELECT rolsuper::text || '|' || rolinherit::text || '|' || rolcreaterole::text || '|' || rolcreatedb::text || '|' || rolcanlogin::text || '|' || rolreplication::text || '|' || rolbypassrls::text || '|' || rolconnlimit::text || '|' || (rolpassword IS NULL)::text FROM pg_authid WHERE rolname=" . database45SqlLiteral($appUser));
    if (!hash_equals('false|true|false|false|true|false|false|10|true', $appProps)) throw new RuntimeException('Bestaande app-role wijkt af van least-privilege/no-password contract.');
    $ownerProps = apply45PgQuery("SELECT rolsuper::text || '|' || rolcreaterole::text || '|' || rolcreatedb::text || '|' || rolcanlogin::text || '|' || rolreplication::text || '|' || rolbypassrls::text FROM pg_roles WHERE rolname=" . database45SqlLiteral($owner));
    if (!hash_equals('false|false|false|false|false|false', $ownerProps)) throw new RuntimeException('Owner-role wijkt af van NOLOGIN least-privilege contract.');
    $memberships = apply45PgQuery("SELECT count(*) FROM pg_auth_members WHERE member IN (SELECT oid FROM pg_roles WHERE rolname IN (" . database45SqlLiteral($owner) . ',' . database45SqlLiteral($appUser) . ")) OR roleid IN (SELECT oid FROM pg_roles WHERE rolname IN (" . database45SqlLiteral($owner) . ',' . database45SqlLiteral($appUser) . '))');
    if ($memberships !== '0') throw new RuntimeException('Tenant PostgreSQL-roles mogen geen role-memberships hebben.');
    $dbOwner = apply45PgQuery("SELECT r.rolname FROM pg_database d JOIN pg_roles r ON r.oid=d.datdba WHERE d.datname=" . database45SqlLiteral($database));
    if (!hash_equals($owner, $dbOwner)) throw new RuntimeException('Tenantdatabase is niet eigendom van de NOLOGIN owner-role.');

    apply45PgQuery('REVOKE ALL ON DATABASE ' . $database . ' FROM PUBLIC; GRANT CONNECT ON DATABASE ' . $database . ' TO ' . $owner . '; GRANT CONNECT ON DATABASE ' . $database . ' TO ' . $appUser . '; ALTER ROLE ' . $appUser . ' IN DATABASE ' . $database . ' SET search_path TO vst, pg_catalog;');
    $migration = @file_get_contents((string)$plan['bundle']['migration_file']);
    if (!is_string($migration)) throw new RuntimeException('Migratieartifact kon niet worden gelezen.');
    apply45PgScript($migration, $database);

    $meta = apply45PgQuery("SELECT tenant_key || '|' || schema_version::text FROM vst.vereniging_schema_meta WHERE component='private_store'", $database);
    if (!hash_equals($plan['tenant_key'] . '|1', $meta)) throw new RuntimeException('Schema tenantmarker/version wijkt af na migratie.');
    $priv = apply45PgQuery("SELECT has_schema_privilege(" . database45SqlLiteral($appUser) . ",'vst','USAGE')::text || '|' || has_schema_privilege(" . database45SqlLiteral($appUser) . ",'vst','CREATE')::text || '|' || has_table_privilege(" . database45SqlLiteral($appUser) . ",'vst.vereniging_schema_meta','SELECT')::text || '|' || has_table_privilege(" . database45SqlLiteral($appUser) . ",'vst.vereniging_schema_meta','INSERT')::text || '|' || has_table_privilege(" . database45SqlLiteral($appUser) . ",'vst.vereniging_private_store','SELECT,INSERT,UPDATE,DELETE')::text", $database);
    if (!hash_equals('true|false|true|false|true', $priv)) throw new RuntimeException('App-role databaseprivileges wijken af van het least-privilege contract.');

    $hbaState = apply45HbaInstalleer($plan);
    try { apply45PeerCheck($plan); }
    catch (Throwable $e) { apply45HbaRollback($hbaState); throw $e; }

    $bundleDir = (string)$plan['bundle']['output_dir'];
    if (is_link($bundleDir) || !@chown($bundleDir, 0) || !@chgrp($bundleDir, (int)$gr['gid']) || !@chmod($bundleDir, 0750)) {
        throw new RuntimeException('Databasebundle ownership kon niet veilig worden gezet.');
    }
    foreach (['plan_file','runtime_file','migration_file','hba_file'] as $key) {
        $pad = (string)$plan['bundle'][$key];
        if (is_link($pad) || !is_file($pad) || !@chown($pad, 0) || !@chgrp($pad, (int)$gr['gid']) || !@chmod($pad, 0640)) {
            throw new RuntimeException('Databaseartifact ownership kon niet veilig worden gezet: ' . basename($pad));
        }
    }
} catch (Throwable $e) {
    apply45Stop($e->getMessage());
}

echo 'OK: fase 4.5 toegepast voor tenant ' . $plan['tenant_key'] . ".\n";
echo 'PostgreSQL: database=' . $plan['isolation']['database'] . ', owner=' . $plan['isolation']['owner_role'] . ', app-role=' . $plan['isolation']['app_role'] . ".\n";
echo "Authenticatie: lokale Unix-socket peer; geen databasewachtwoord opgeslagen. Cross-database toegang voor de tenantuser wordt in HBA geweigerd.\n";