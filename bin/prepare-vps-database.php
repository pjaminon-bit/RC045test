<?php
// ============================================================
// Fase 4.5 — genereer tenantgebonden PostgreSQL databasebundle
// ============================================================
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Alleen via CLI beschikbaar.');
}

require_once dirname(__DIR__) . '/app/deployment/database-contract.php';

function prepare45Stop(string $melding, int $code = 1): void
{
    fwrite(STDERR, "FOUT: {$melding}\n");
    exit($code);
}

function prepare45Help(): void
{
    echo "Gebruik:\n";
    echo "  php bin/prepare-vps-database.php --runtime-plan=/srv/verenigingen/club/runtime/runtime-plan.json [--force] [--dry-run]\n\n";
    echo "Fase 4.5 kiest bewust één lokaal PostgreSQL-database per tenant met Unix-socket peer authentication.\n";
    echo "Er wordt geen databasewachtwoord, DSN-secret of providersecret gevraagd of opgeslagen.\n";
}

function prepare45SchrijfAtomisch(string $pad, string $inhoud): void
{
    $map = dirname($pad);
    if (!is_dir($map) || is_link($map)) prepare45Stop('Database outputmap is niet veilig beschikbaar.');
    if (is_link($pad)) prepare45Stop('Databaseartifact mag geen symlinkdoel overschrijven: ' . $pad);
    $tmp = $map . '/.' . basename($pad) . '.tmp.' . bin2hex(random_bytes(8));
    if (runtime41SymlinkInPad($tmp) !== null) prepare45Stop('Onveilig tijdelijk databaseartifactpad.');
    if (@file_put_contents($tmp, $inhoud, LOCK_EX) === false) prepare45Stop('Databaseartifact kon niet tijdelijk worden geschreven.');
    @chmod($tmp, 0640);
    clearstatcache(true, $pad);
    if (is_link($pad)) { @unlink($tmp); prepare45Stop('Databaseartifactdoel werd tijdens write een symlink.'); }
    if (!@rename($tmp, $pad)) { @unlink($tmp); prepare45Stop('Databaseartifact kon niet atomisch worden geplaatst.'); }
    @chmod($pad, 0640);
}

foreach ($_SERVER['argv'] ?? [] as $arg) {
    if (preg_match('/^--(?:password|pass|secret|dsn|db-user|db-password|token|pgpassword|pgpass)(?:=|$)/i', (string)$arg) === 1) {
        prepare45Stop('Databasecredentials/secrets horen niet in fase-4.5 CLI-argumenten.');
    }
}

$opt = getopt('', ['runtime-plan:', 'force', 'dry-run', 'help']);
if (isset($opt['help'])) { prepare45Help(); exit(0); }
$runtimePlan = trim((string)($opt['runtime-plan'] ?? ''));
if ($runtimePlan === '') prepare45Stop('--runtime-plan=/absoluut/pad/runtime-plan.json is verplicht.');

try {
    $context = database45RuntimeContext($runtimePlan);
    $plan = database45Plan($context);
    $planJson = database45Json($plan);
    $bestanden = [
        $plan['bundle']['runtime_file'] => database45RuntimeJson($plan),
        $plan['bundle']['migration_file'] => database45MigrationSql($plan),
        $plan['bundle']['hba_file'] => database45HbaConfig($plan),
        $plan['bundle']['plan_file'] => $planJson,
    ];
} catch (Throwable $e) { prepare45Stop($e->getMessage()); }

if (isset($opt['dry-run'])) {
    echo $planJson;
    exit(0);
}

$outputDir = (string)$plan['bundle']['output_dir'];
if (!is_dir($outputDir)) {
    $parent = dirname($outputDir);
    try { runtime41BestaandPad($parent, 'Parent van database outputmap', true); }
    catch (Throwable $e) { prepare45Stop($e->getMessage()); }
    if (!@mkdir($outputDir, 0750) && !is_dir($outputDir)) prepare45Stop('Database outputmap kon niet worden aangemaakt.');
}
@chmod($outputDir, 0750);
try {
    $real = runtime41BestaandPad($outputDir, 'Database outputmap', true);
    if (!hash_equals(runtime41NormPad($real), runtime41NormPad($context['tenant_root'] . '/database'))) {
        prepare45Stop('Database outputmap is niet exact de vaste <tenantroot>/database map.');
    }
} catch (Throwable $e) { prepare45Stop($e->getMessage()); }

$force = isset($opt['force']);
$wijzigingen = [];
foreach ($bestanden as $pad => $inhoud) {
    if (is_link($pad)) prepare45Stop('Bestaand databaseartifact is een symlink: ' . $pad);
    if (is_file($pad)) {
        $huidig = @file_get_contents($pad);
        if (!is_string($huidig)) prepare45Stop('Bestaand databaseartifact is niet leesbaar: ' . $pad);
        if (!hash_equals(hash('sha256', $huidig), hash('sha256', $inhoud))) {
            if (!$force) prepare45Stop('Databasebundle wijkt af; gebruik --force pas na controle. Afwijking: ' . basename($pad));
            $wijzigingen[$pad] = $inhoud;
        }
    } elseif (file_exists($pad)) {
        prepare45Stop('Databaseartifactdoel bestaat maar is geen regulier bestand: ' . $pad);
    } else {
        $wijzigingen[$pad] = $inhoud;
    }
}

if ($wijzigingen === []) {
    echo "ONGEWIJZIGD  {$plan['bundle']['plan_file']}\n";
} else {
    foreach ($wijzigingen as $pad => $inhoud) prepare45SchrijfAtomisch($pad, $inhoud);
    echo "GESCHREVEN  {$plan['bundle']['plan_file']}\n";
}

echo 'Databasebundle gereed voor tenant ' . $plan['tenant_key'] . '. ';
echo 'Database=' . $plan['isolation']['database'] . ', app-role=' . $plan['isolation']['app_role'] . ".\n";
echo "Geen databasewachtwoord gegenereerd: authenticatie is uitsluitend lokale PostgreSQL peer-auth.\n";
