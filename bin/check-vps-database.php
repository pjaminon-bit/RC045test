<?php
// ============================================================
// Fase 4.5 — runtime databasecheck als tenant Linux-user
// ============================================================
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Alleen via CLI beschikbaar.');
}

require_once dirname(__DIR__) . '/app/deployment/database-contract.php';

function check45Stop(string $melding, int $code = 1): never
{
    fwrite(STDERR, "FOUT: {$melding}\n");
    exit($code);
}

function check45Help(): void
{
    echo "Gebruik:\n";
    echo "  sudo -u <tenant-linux-user> php bin/check-vps-database.php --database-plan=/srv/verenigingen/club/database/database-plan.json\n\n";
    echo "De check gebruikt dezelfde kernel OS-user als PHP-FPM, verbindt zonder wachtwoord via peer-auth,\n";
    echo "controleert tenant/schema, test DML in een rollbacktransactie en bewijst dat DDL geweigerd wordt.\n";
}

foreach ($_SERVER['argv'] ?? [] as $arg) {
    if (preg_match('/^--(?:password|pass|secret|dsn|db-user|db-password|token|pgpassword|pgpass)(?:=|$)/i', (string)$arg) === 1) {
        check45Stop('Databasecredentials/secrets horen niet in fase-4.5 CLI-argumenten.');
    }
}

$opt = getopt('', ['database-plan:', 'help']);
if (isset($opt['help'])) { check45Help(); exit(0); }
$planPad = trim((string)($opt['database-plan'] ?? ''));
if ($planPad === '') check45Stop('--database-plan=/absoluut/pad/database-plan.json is verplicht.');

try { $context = database45PlanLeesEnValideer($planPad); }
catch (Throwable $e) { check45Stop($e->getMessage()); }
$plan = $context['plan'];
$user = (string)$plan['isolation']['app_role'];
if (PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid') || !function_exists('posix_getpwnam')) {
    check45Stop('Database runtimecheck vereist Linux + POSIX accountfuncties.');
}
$pw = @posix_getpwnam($user);
if (!is_array($pw) || (int)($pw['uid'] ?? -1) !== posix_geteuid()) {
    check45Stop('Voer deze check uit als exact de fase-4.1 tenant Linux-user: ' . $user);
}
if (!extension_loaded('pdo_pgsql')) check45Stop('PHP-extensie pdo_pgsql ontbreekt.');

putenv('VERENIGING_REQUIRE_TENANT_CONFIG=1');
putenv('VERENIGING_CONFIG_FILE=' . $context['context']['config_file']);
putenv('VERENIGING_PRIVATE_ROOT=' . $context['context']['private_root']);
putenv('VERENIGING_DB_DSN');
putenv('VERENIGING_DB_USER');
putenv('VERENIGING_DB_PASSWORD');

try {
    require_once dirname(__DIR__) . '/app/storage/private-store.php';
    $pdo = privateStorePdo();
    $pdo->beginTransaction();
    $probeKey = '__phase45_runtime_probe__';
    $tenant = (string)$plan['tenant_key'];
    $payload = json_encode(['probe' => true, 'tenant' => $tenant], JSON_UNESCAPED_SLASHES);
    if (!is_string($payload)) throw new RuntimeException('Probe-payload kon niet worden opgebouwd.');

    $insert = $pdo->prepare('INSERT INTO vereniging_private_store (tenant_key, collection_key, payload, updated_at) VALUES (:tenant,:collection,:payload,:updated) ON CONFLICT (tenant_key, collection_key) DO UPDATE SET payload=EXCLUDED.payload, updated_at=EXCLUDED.updated_at');
    $insert->execute(['tenant'=>$tenant,'collection'=>$probeKey,'payload'=>$payload,'updated'=>date('c')]);
    $select = $pdo->prepare('SELECT payload FROM vereniging_private_store WHERE tenant_key=:tenant AND collection_key=:collection');
    $select->execute(['tenant'=>$tenant,'collection'=>$probeKey]);
    if (!hash_equals($payload, (string)$select->fetchColumn())) throw new RuntimeException('DML probe kon eigen tenantrow niet teruglezen.');
    $update = $pdo->prepare('UPDATE vereniging_private_store SET updated_at=:updated WHERE tenant_key=:tenant AND collection_key=:collection');
    $update->execute(['updated'=>date('c'),'tenant'=>$tenant,'collection'=>$probeKey]);
    $delete = $pdo->prepare('DELETE FROM vereniging_private_store WHERE tenant_key=:tenant AND collection_key=:collection');
    $delete->execute(['tenant'=>$tenant,'collection'=>$probeKey]);

    $ddlGeweigerd = false;
    try {
        $pdo->exec('CREATE TABLE vst.__phase45_ddl_probe (id INTEGER)');
    } catch (PDOException $e) {
        $ddlGeweigerd = true;
    }
    if (!$ddlGeweigerd) throw new RuntimeException('Least-privilege faalt: app-role kon DDL uitvoeren.');
    if ($pdo->inTransaction()) $pdo->rollBack();
} catch (Throwable $e) {
    try { if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $ignored) {}
    check45Stop($e->getMessage());
}

echo 'OK: fase-4.5 database runtime READY voor tenant ' . $plan['tenant_key'] . ".\n";
echo 'Peer identity=' . $user . ', database=' . $plan['isolation']['database'] . ', schema=vst@1; DML toegestaan, DDL geweigerd.' . "\n";
