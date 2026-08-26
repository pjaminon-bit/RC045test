<?php
// ============================================================
// Fase 4.5 — valideer/provision PostgreSQL tenantdatabase
// ============================================================
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Alleen via CLI beschikbaar.');
}

require_once dirname(__DIR__) . '/app/deployment/database-contract.php';
require_once dirname(__DIR__) . '/app/deployment/process-runner.php';

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
    echo "Stop vóór --apply de tenant-PHP-FPM pool: database/HBA provisioning gebeurt uitsluitend met een inactieve tenant-runtime.\n";
    echo "Er wordt bewust geen databasewachtwoord aangemaakt: lokale Unix-socket peer-auth bindt DB-login aan de kernel OS-user.\n";
}

function apply45Binary(string $name): string
{
    static $cache = [];
    if (isset($cache[$name])) return $cache[$name];
    $known = [
        'pgrep' => ['/usr/bin/pgrep'],
        'runuser' => ['/usr/sbin/runuser','/usr/bin/runuser'],
        'psql' => ['/usr/bin/psql'],
    ];
    if (!isset($known[$name])) throw new RuntimeException('Niet-toegestane database PATH-binary: ' . $name);
    foreach ($known[$name] as $candidate) {
        if (is_file($candidate) && is_executable($candidate)) return $cache[$name] = $candidate;
    }
    throw new RuntimeException('Vereiste database-executable ontbreekt: ' . $name);
}

function apply45Exec(array $command, ?string $stdin = null): array
{
    if ($command === [] || !isset($command[0])) throw new RuntimeException('Database subprocesscommando ontbreekt.');
    $command[0] = apply45Binary((string)$command[0]);
    if (basename((string)$command[0]) === 'runuser') {
        $sep = array_search('--', $command, true);
        if ($sep === false || !isset($command[$sep + 1])) throw new RuntimeException('runuser databasecommando mist exact child-executable.');
        $command[$sep + 1] = apply45Binary((string)$command[$sep + 1]);
    }
    return process521Run($command, $stdin, null, null, 900);
}

function apply45Deps(): void
{
    foreach (['pgrep','runuser','psql'] as $name) apply45Binary($name);
}

function apply45RuntimeMoetInactiefZijn(string $tenantUser): void
{
    [$code, $out, $err] = apply45Exec(['pgrep', '-u', $tenantUser]);
    if ($code === 1) return;
    if ($code === 0 && $out !== '') throw new RuntimeException('Tenant-runtimeuser heeft actieve processen. Stop eerst de tenant-PHP-FPM pool vóór database --apply.');
    throw new RuntimeException('Actieve tenantprocessen konden niet fail-closed worden gecontroleerd: ' . ($err !== '' ? $err : 'pgrep ontbreekt of gaf een onverwachte status'));
}

function apply45PgQuery(string $sql, string $database = 'postgres'): string
{
    [$code, $out, $err] = apply45Exec(['runuser', '-u', 'postgres', '--', 'psql', '-X', '-w', '-v', 'ON_ERROR_STOP=1', '-At', '-d', $database, '-c', $sql]);
    if ($code !== 0) throw new RuntimeException('PostgreSQL-query faalde: ' . ($err !== '' ? $err : $out));
    return trim($out);
}

function apply45PgScript(string $script, string $database): void
{
    [$code, $out, $err] = apply45Exec(['runuser', '-u', 'postgres', '--', 'psql', '-X', '-w', '-v', 'ON_ERROR_STOP=1', '-d', $database, '-f', '-'], $script);
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
    $link = runtime41SymlinkInPad($pad);
    if ($link !== null) throw new RuntimeException('Schrijfdoel mag geen symlink in zijn pad bevatten: ' . $link);
    $map = dirname($pad);
    if (!is_dir($map)) throw new RuntimeException('Schrijfmap bestaat niet: ' . $map);
    $tmp = $map . '/.' . basename($pad) . '.tmp.' . bin2hex(random_bytes(8));
    if (runtime41SymlinkInPad($tmp) !== null || @file_put_contents($tmp, $inhoud, LOCK_EX) === false) throw new RuntimeException('Tijdelijk bestand kon niet veilig worden geschreven: ' . $pad);
    @chmod($tmp, $mode);
    if ($uid !== null && !@chown($tmp, $uid)) { @unlink($tmp); throw new RuntimeException('Owner kon niet worden gezet op tijdelijk bestand.'); }
    if ($gid !== null && !@chgrp($tmp, $gid)) { @unlink($tmp); throw new RuntimeException('Group kon niet worden gezet op tijdelijk bestand.'); }
    clearstatcache(true, $pad);
    if (runtime41SymlinkInPad($pad) !== null) { @unlink($tmp); throw new RuntimeException('Doelpad werd tijdens write onveilig.'); }
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

    foreach (['/etc/verenigingsplatform', '/etc/verenigingsplatform/postgresql', $includeDir] as $veiligPad) {
        $link = runtime41SymlinkInPad($veiligPad);
        if ($link !== null) throw new RuntimeException('PostgreSQL platformconfigpad mag geen symlink bevatten: ' . $link);
    }
    if (!is_dir('/etc/verenigingsplatform') && !@mkdir('/etc/verenigingsplatform', 0710, true)) throw new RuntimeException('/etc/verenigingsplatform kon niet worden aangemaakt.');
    if (runtime41SymlinkInPad('/etc/verenigingsplatform') !== null
        || !@chown('/etc/verenigingsplatform', 0)
        || !@chgrp('/etc/verenigingsplatform', $pgGid)
        || !@chmod('/etc/verenigingsplatform', 0710)) {
        throw new RuntimeException('/etc/verenigingsplatform kon niet veilig root:postgres 0710 worden gemaakt voor PostgreSQL traverse.');
    }
    if (!is_dir('/etc/verenigingsplatform/postgresql') && !@mkdir('/etc/verenigingsplatform/postgresql', 0750)) throw new RuntimeException('PostgreSQL platformconfigmap kon niet worden aangemaakt.');
    if (!is_dir($includeDir) && !@mkdir($includeDir, 0750)) throw new RuntimeException('PostgreSQL HBA include_dir kon niet worden aangemaakt.');
    foreach (['/etc/verenigingsplatform/postgresql', $includeDir] as $dir) {
        if (runtime41SymlinkInPad($dir) !== null || !@chown($dir, 0) || !@chgrp($dir, $pgGid) || !@chmod($dir, 0750)) throw new RuntimeException('PostgreSQL platformconfigmap heeft onveilige ownership/rechten: ' . $dir);
    }

    $oudeTenantBestond = is_file($tenantHba) && runtime41SymlinkInPad($tenantHba) === null;
    $oudeTenant = $oudeTenantBestond ? @file_get_contents($tenantHba) : null;
    if (file_exists($tenantHba) && !$oudeTenantBestond) throw new RuntimeException('Tenant HBA-doel bestaat maar is geen veilig regulier bestand.');
    if ($oudeTenantBestond && !is_string($oudeTenant)) throw new RuntimeException('Bestaande tenant HBA kon niet worden gelezen.');

    $hbaPad = runtime41BestaandPad(apply45PgQuery('SHOW hba_file'), 'PostgreSQL pg_hba.conf');
    $oudeMain = @file_get_contents($hbaPad); $stat = @stat($hbaPad);
    if (!is_string($oudeMain) || !is_array($stat)) throw new RuntimeException('pg_hba.conf kon niet veilig worden gelezen.');
    if (str_contains($oudeMain, '/etc/verenigingsplatform/postgresql') && !str_contains($oudeMain, $includeRegel)) throw new RuntimeException('pg_hba.conf bevat een afwijkende verenigingsplatform-include; handmatige inspectie vereist.');
    $regels = preg_split('/\R/', $oudeMain) ?: [];
    $regels = array_values(array_filter($regels, static fn($regel) => trim((string)$regel) !== $includeRegel));
    $nieuweMain = $includeRegel . "\n" . implode("\n", $regels);
    if (!str_ends_with($nieuweMain, "\n")) $nieuweMain .= "\n";

    apply45VeiligSchrijf($tenantHba, $verwachtHba, 0640, 0, $pgGid);
    if (!hash_equals($oudeMain, $nieuweMain)) apply45VeiligSchrijf($hbaPad, $nieuweMain, ((int)$stat['mode']) & 0777, (int)$stat['uid'], (int)$stat['gid']);

    try {
        if (apply45PgQuery("SELECT count(*) FROM pg_hba_file_rules WHERE error IS NOT NULL") !== '0') throw new RuntimeException('pg_hba_file_rules meldt syntax-/configuratiefouten; reload geweigerd.');
        $tenantLiteral = database45SqlLiteral($tenantHba); $dbLiteral = database45SqlLiteral((string)$plan['isolation']['database']); $userLiteral = database45SqlLiteral((string)$plan['isolation']['app_role']);
        $allow = apply45PgQuery("SELECT count(*) FROM pg_hba_file_rules WHERE file_name={$tenantLiteral} AND type='local' AND database=ARRAY[{$dbLiteral}]::text[] AND user_name=ARRAY[{$userLiteral}]::text[] AND auth_method='peer'");
        $deny = apply45PgQuery("SELECT count(*) FROM pg_hba_file_rules WHERE file_name={$tenantLiteral} AND type='local' AND database=ARRAY['all']::text[] AND user_name=ARRAY[{$userLiteral}]::text[] AND auth_method='reject'");
        if ($allow !== '1' || $deny !== '1') throw new RuntimeException('Exacte tenant peer-allow + cross-database reject zijn niet zichtbaar in pg_hba_file_rules.');
        $platformPrefix = database45SqlLiteral(rtrim($includeDir, '/') . '/');
        $laatstePlatform = apply45PgQuery("SELECT max(rule_number) FROM pg_hba_file_rules WHERE type IS NOT NULL AND strpos(file_name, {$platformPrefix}) = 1");
        $eersteBuitenPlatform = apply45PgQuery("SELECT min(rule_number) FROM pg_hba_file_rules WHERE type IS NOT NULL AND strpos(file_name, {$platformPrefix}) <> 1");
        if ($laatstePlatform === '' || ($eersteBuitenPlatform !== '' && (int)$laatstePlatform > (int)$eersteBuitenPlatform)) throw new RuntimeException('Niet alle platform-HBA authregels staan vóór de bestaande niet-platform HBA-regels.');
        if (apply45PgQuery('SELECT pg_reload_conf()') !== 't') throw new RuntimeException('PostgreSQL HBA reload kon niet worden aangevraagd.');
    } catch (Throwable $e) {
        if ($oudeTenantBestond && is_string($oudeTenant)) apply45VeiligSchrijf($tenantHba, $oudeTenant, 0640, 0, $pgGid);
        elseif (is_file($tenantHba) && runtime41SymlinkInPad($tenantHba) === null) @unlink($tenantHba);
        if (!hash_equals($oudeMain, (string)@file_get_contents($hbaPad))) apply45VeiligSchrijf($hbaPad, $oudeMain, ((int)$stat['mode']) & 0777, (int)$stat['uid'], (int)$stat['gid']);
        try { apply45PgQuery('SELECT pg_reload_conf()'); } catch (Throwable $ignored) {}
        throw $e;
    }
    return ['hba_file' => $hbaPad, 'tenant_hba' => $tenantHba, 'old_main' => $oudeMain, 'old_tenant' => $oudeTenant, 'old_tenant_existed' => $oudeTenantBestond, 'stat' => $stat, 'pg_gid' => $pgGid];
}

function apply45PeerCheck(array $plan): void
{
    $user = (string)$plan['isolation']['app_role']; $database = (string)$plan['isolation']['database'];
    [$code, $out, $err] = apply45Exec(['runuser', '-u', $user, '--', 'psql', '-X', '-w', '-h', '/var/run/postgresql', '-U', $user, '-d', $database, '-At', '-c', "SELECT current_database() || '|' || current_user || '|' || tenant_key || '|' || schema_version::text FROM vst.vereniging_schema_meta WHERE component='private_store'"]);
    $verwacht = $database . '|' . $user . '|' . $plan['tenant_key'] . '|1';
    if ($code !== 0 || !hash_equals($verwacht, trim($out))) throw new RuntimeException('Peer-connectivity/tenantmarker-check faalde: ' . ($err !== '' ? $err : $out));
    [$crossCode] = apply45Exec(['runuser', '-u', $user, '--', 'psql', '-X', '-w', '-h', '/var/run/postgresql', '-U', $user, '-d', 'postgres', '-At', '-c', 'SELECT 1']);
    if ($crossCode === 0) throw new RuntimeException('Cross-database HBA-reject faalt: tenantuser kan postgres database bereiken.');
}

foreach ($_SERVER['argv'] ?? [] as $arg) if (preg_match('/^--(?:password|pass|secret|dsn|db-user|db-password|token|pgpassword|pgpass)(?:=|$)/i', (string)$arg) === 1) apply45Stop('Databasecredentials/secrets horen niet in fase-4.5 CLI-argumenten.');
$opt = getopt('', ['database-plan:', 'check', 'apply', 'help']);
if (isset($opt['help'])) { apply45Help(); exit(0); }
$planPad = trim((string)($opt['database-plan'] ?? ''));
if ($planPad === '') apply45Stop('--database-plan=/absoluut/pad/database-plan.json is verplicht.');
if (isset($opt['check']) === isset($opt['apply'])) apply45Stop('Kies exact één van --check of --apply.');
try { $context = database45PlanLeesEnValideer($planPad); } catch (Throwable $e) { apply45Stop($e->getMessage()); }
$plan = $context['plan'];
if (isset($opt['check'])) { echo 'OK: fase-4.5 databasebundle valide voor tenant ' . $plan['tenant_key'] . ".\n"; echo 'PostgreSQL database=' . $plan['isolation']['database'] . ', app-role=' . $plan['isolation']['app_role'] . ', auth=peer.' . "\n"; exit(0); }
if (PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid') || posix_geteuid() !== 0) apply45Stop('Database --apply vereist Linux root (EUID 0).');
if (!function_exists('posix_getpwnam') || !function_exists('posix_getgrnam')) apply45Stop('POSIX accountfuncties ontbreken.');
apply45Deps();
$appUser = (string)$plan['isolation']['app_role']; $pw = @posix_getpwnam($appUser); $gr = @posix_getgrnam($appUser);
if (!is_array($pw) || !is_array($gr) || (int)($pw['gid'] ?? -1) !== (int)($gr['gid'] ?? -2)) apply45Stop('Fase-4.1 tenant Linux-user/group bestaat niet exact; pas 4.1 eerst op de VPS toe.');

$appRoleTenantGebonden = false; $hbaBeschermingActief = false;
try {
    apply45RuntimeMoetInactiefZijn($appUser);
    $versie = (int)apply45PgQuery('SHOW server_version_num');
    if ($versie < 160000) throw new RuntimeException('Fase 4.5 vereist PostgreSQL 16 of nieuwer voor gecontroleerde HBA includes/rule_number-validatie.');
    if (($plan['postgresql']['socket_only_required'] ?? false) !== true || (string)($plan['postgresql']['listen_addresses_required'] ?? 'onverwacht') !== '') throw new RuntimeException('Databaseplan mist het verplichte socket-only PostgreSQL-contract.');
    if (trim(apply45PgQuery('SHOW listen_addresses')) !== '') throw new RuntimeException("Fase 4.5 vereist socket-only PostgreSQL: zet listen_addresses='' en herstart PostgreSQL gecontroleerd vóór --apply.");
    $socketDirs = array_map(static fn($v) => trim($v, " \t\n\r\0\x0B\"'"), explode(',', apply45PgQuery('SHOW unix_socket_directories')));
    if (!in_array('/var/run/postgresql', $socketDirs, true)) throw new RuntimeException('PostgreSQL luistert niet op de verplichte Unix-socket /var/run/postgresql.');

    $owner = (string)$plan['isolation']['owner_role']; $database = (string)$plan['isolation']['database']; $marker = database45Marker((string)$plan['tenant_key']);
    foreach ([['role', $owner], ['role', $appUser], ['database', $database]] as [$soort, $naam]) if (apply45Bestaat($soort, $naam) && !hash_equals($marker, apply45Marker($soort, $naam))) throw new RuntimeException("Bestaande PostgreSQL {$soort} {$naam} is niet aan deze tenant gemarkeerd; geen wijzigingen uitgevoerd.");
    if (!apply45Bestaat('role', $owner)) apply45PgQuery('CREATE ROLE ' . $owner . ' NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS; COMMENT ON ROLE ' . $owner . ' IS ' . database45SqlLiteral($marker));

    $appBestond = apply45Bestaat('role', $appUser);
    if ($appBestond) {
        $appRoleTenantGebonden = true;
        $preProps = apply45PgQuery("SELECT rolsuper::text || '|' || rolinherit::text || '|' || rolcreaterole::text || '|' || rolcreatedb::text || '|' || rolreplication::text || '|' || rolbypassrls::text || '|' || rolconnlimit::text || '|' || (rolpassword IS NULL)::text FROM pg_authid WHERE rolname=" . database45SqlLiteral($appUser));
        if (!hash_equals('false|true|false|false|false|false|10|true', $preProps)) throw new RuntimeException('Bestaande app-role heeft gevaarlijke privilege/password-drift; role wordt fail-closed gesloten.');
    } else {
        apply45PgQuery('CREATE ROLE ' . $appUser . ' NOLOGIN INHERIT NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS CONNECTION LIMIT 10 PASSWORD NULL; COMMENT ON ROLE ' . $appUser . ' IS ' . database45SqlLiteral($marker));
        $appRoleTenantGebonden = true;
    }
    $memberships = apply45PgQuery("SELECT count(*) FROM pg_auth_members WHERE member IN (SELECT oid FROM pg_roles WHERE rolname IN (" . database45SqlLiteral($owner) . ',' . database45SqlLiteral($appUser) . ")) OR roleid IN (SELECT oid FROM pg_roles WHERE rolname IN (" . database45SqlLiteral($owner) . ',' . database45SqlLiteral($appUser) . '))');
    if ($memberships !== '0') throw new RuntimeException('Tenant PostgreSQL-roles mogen geen role-memberships hebben.');

    apply45PgQuery('ALTER ROLE ' . $appUser . ' NOLOGIN INHERIT NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS CONNECTION LIMIT 10 PASSWORD NULL');
    $noLoginProps = apply45PgQuery("SELECT rolcanlogin::text || '|' || (rolpassword IS NULL)::text FROM pg_authid WHERE rolname=" . database45SqlLiteral($appUser));
    if (!hash_equals('false|true', $noLoginProps)) throw new RuntimeException('App-role kon niet aantoonbaar NOLOGIN/no-password worden gezet vóór HBA provisioning.');
    apply45HbaInstalleer($plan); $hbaBeschermingActief = true;

    if (!apply45Bestaat('database', $database)) { apply45PgQuery('CREATE DATABASE ' . $database . ' OWNER ' . $owner . " TEMPLATE template0 ENCODING 'UTF8'"); apply45PgQuery('COMMENT ON DATABASE ' . $database . ' IS ' . database45SqlLiteral($marker)); }
    $ownerProps = apply45PgQuery("SELECT rolsuper::text || '|' || rolcreaterole::text || '|' || rolcreatedb::text || '|' || rolcanlogin::text || '|' || rolreplication::text || '|' || rolbypassrls::text FROM pg_roles WHERE rolname=" . database45SqlLiteral($owner));
    if (!hash_equals('false|false|false|false|false|false', $ownerProps)) throw new RuntimeException('Owner-role wijkt af van NOLOGIN least-privilege contract.');
    $dbOwner = apply45PgQuery("SELECT r.rolname FROM pg_database d JOIN pg_roles r ON r.oid=d.datdba WHERE d.datname=" . database45SqlLiteral($database));
    if (!hash_equals($owner, $dbOwner)) throw new RuntimeException('Tenantdatabase is niet eigendom van de NOLOGIN owner-role.');

    apply45PgQuery('REVOKE ALL ON DATABASE ' . $database . ' FROM PUBLIC; REVOKE ALL ON DATABASE ' . $database . ' FROM ' . $appUser . '; GRANT CONNECT ON DATABASE ' . $database . ' TO ' . $owner . '; GRANT CONNECT ON DATABASE ' . $database . ' TO ' . $appUser . '; ALTER ROLE ' . $appUser . ' IN DATABASE ' . $database . ' SET search_path TO vst, pg_catalog;');
    $migration = @file_get_contents((string)$plan['bundle']['migration_file']); if (!is_string($migration)) throw new RuntimeException('Migratieartifact kon niet worden gelezen.'); apply45PgScript($migration, $database);
    $meta = apply45PgQuery("SELECT tenant_key || '|' || schema_version::text FROM vst.vereniging_schema_meta WHERE component='private_store'", $database);
    if (!hash_equals($plan['tenant_key'] . '|1', $meta)) throw new RuntimeException('Schema tenantmarker/version wijkt af na migratie.');
    $appLit = database45SqlLiteral($appUser); $dbLit = database45SqlLiteral($database);
    $priv = apply45PgQuery("SELECT "
        . "has_database_privilege({$appLit},{$dbLit},'CONNECT')::text || '|' || has_database_privilege({$appLit},{$dbLit},'CREATE')::text || '|' || has_database_privilege({$appLit},{$dbLit},'TEMPORARY')::text || '|' || "
        . "has_schema_privilege({$appLit},'public','USAGE')::text || '|' || has_schema_privilege({$appLit},'public','CREATE')::text || '|' || has_schema_privilege({$appLit},'vst','USAGE')::text || '|' || has_schema_privilege({$appLit},'vst','CREATE')::text || '|' || "
        . "has_table_privilege({$appLit},'vst.vereniging_schema_meta','SELECT')::text || '|' || has_table_privilege({$appLit},'vst.vereniging_schema_meta','INSERT')::text || '|' || has_table_privilege({$appLit},'vst.vereniging_schema_meta','UPDATE')::text || '|' || has_table_privilege({$appLit},'vst.vereniging_schema_meta','DELETE')::text || '|' || "
        . "has_table_privilege({$appLit},'vst.vereniging_private_store','SELECT')::text || '|' || has_table_privilege({$appLit},'vst.vereniging_private_store','INSERT')::text || '|' || has_table_privilege({$appLit},'vst.vereniging_private_store','UPDATE')::text || '|' || has_table_privilege({$appLit},'vst.vereniging_private_store','DELETE')::text || '|' || has_table_privilege({$appLit},'vst.vereniging_private_store','TRUNCATE')::text || '|' || has_table_privilege({$appLit},'vst.vereniging_private_store','REFERENCES')::text || '|' || has_table_privilege({$appLit},'vst.vereniging_private_store','TRIGGER')::text", $database);
    if (!hash_equals('true|false|false|false|false|true|false|true|false|false|false|true|true|true|true|false|false|false', $priv)) throw new RuntimeException('App-role database/schema/table privileges wijken af van het exact least-privilege contract.');

    apply45PgQuery('ALTER ROLE ' . $appUser . ' LOGIN INHERIT NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS CONNECTION LIMIT 10 PASSWORD NULL');
    $appProps = apply45PgQuery("SELECT rolsuper::text || '|' || rolinherit::text || '|' || rolcreaterole::text || '|' || rolcreatedb::text || '|' || rolcanlogin::text || '|' || rolreplication::text || '|' || rolbypassrls::text || '|' || rolconnlimit::text || '|' || (rolpassword IS NULL)::text FROM pg_authid WHERE rolname=" . database45SqlLiteral($appUser));
    if (!hash_equals('false|true|false|false|true|false|false|10|true', $appProps)) throw new RuntimeException('App-role kon niet exact volgens least-privilege/no-password LOGIN-contract worden geactiveerd.');
    apply45PeerCheck($plan);

    $bundleDir = (string)$plan['bundle']['output_dir'];
    if (runtime41SymlinkInPad($bundleDir) !== null || !@chown($bundleDir, 0) || !@chgrp($bundleDir, (int)$gr['gid']) || !@chmod($bundleDir, 0750)) throw new RuntimeException('Databasebundle ownership kon niet veilig worden gezet.');
    foreach (['plan_file','runtime_file','migration_file','hba_file'] as $key) { $pad = (string)$plan['bundle'][$key]; if (runtime41SymlinkInPad($pad) !== null || !is_file($pad) || !@chown($pad, 0) || !@chgrp($pad, (int)$gr['gid']) || !@chmod($pad, 0640)) throw new RuntimeException('Databaseartifact ownership kon niet veilig worden gezet: ' . basename($pad)); }
} catch (Throwable $e) {
    if ($appRoleTenantGebonden) { try { apply45PgQuery('ALTER ROLE ' . $appUser . ' NOLOGIN PASSWORD NULL'); } catch (Throwable $ignored) {} }
    $extra = $hbaBeschermingActief ? ' Beschermende tenant-HBA blijft actief; app-role is zo mogelijk NOLOGIN gezet.' : '';
    apply45Stop($e->getMessage() . $extra);
}

echo 'OK: fase 4.5 toegepast voor tenant ' . $plan['tenant_key'] . ".\n";
echo 'PostgreSQL: database=' . $plan['isolation']['database'] . ', owner=' . $plan['isolation']['owner_role'] . ', app-role=' . $plan['isolation']['app_role'] . ".\n";
echo "Authenticatie: lokale Unix-socket peer; geen databasewachtwoord opgeslagen. Cross-database toegang voor de tenantuser wordt vóór LOGIN in HBA geweigerd.\n";