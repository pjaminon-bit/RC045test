<?php
// ============================================================
// Fase 4.1 — genereer Linux/PHP-FPM runtimebundle per tenant
// ============================================================
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Alleen via CLI beschikbaar.');
}

require_once dirname(__DIR__) . '/app/deployment/runtime-contract.php';

function prepare41Stop(string $melding, int $code = 1): void
{
    fwrite(STDERR, "FOUT: {$melding}\n");
    exit($code);
}

function prepare41Help(): void
{
    echo "Gebruik:\n";
    echo "  php bin/prepare-vps-runtime.php \\\n";
    echo "    --deployment=/srv/verenigingen/club/deployment.json [opties]\n\n";
    echo "Opties:\n";
    echo "  --output-dir=PAD     standaard: <tenantroot>/runtime\n";
    echo "  --php-version=8.3    PHP-FPM major.minor (standaard 8.3)\n";
    echo "  --web-user=www-data  user die de FPM-socket mag openen\n";
    echo "  --web-group=www-data groep die de FPM-socket mag openen\n";
    echo "  --force              afwijkende bestaande bundle gecontroleerd vervangen\n";
    echo "  --dry-run            valideer en toon runtime-plan.json zonder te schrijven\n";
    echo "  --help               toon deze hulp\n\n";
    echo "Deze tool voert geen root-acties uit en accepteert geen secrets.\n";
}

function prepare41SchrijfAtomisch(string $pad, string $inhoud, bool $force): string
{
    $map = dirname($pad);
    if (!is_dir($map) || is_link($map)) prepare41Stop('Runtime outputmap is niet veilig beschikbaar.');
    if (is_link($pad)) prepare41Stop('Runtimebundle mag geen symlinkdoel overschrijven.');
    if (is_file($pad)) {
        $huidig = (string)file_get_contents($pad);
        if (hash_equals(hash('sha256', $huidig), hash('sha256', $inhoud))) return 'ongewijzigd';
        if (!$force) prepare41Stop('Runtimebundle bestaat al met andere inhoud; gebruik --force na controle.');
    } elseif (file_exists($pad)) {
        prepare41Stop('Runtimebundledoel bestaat maar is geen regulier bestand.');
    }
    $tmp = $map . '/.' . basename($pad) . '.tmp.' . bin2hex(random_bytes(8));
    if (is_link($tmp) || runtime41SymlinkInPad($tmp) !== null) prepare41Stop('Onveilig tijdelijk runtimebundlepad.');
    if (@file_put_contents($tmp, $inhoud, LOCK_EX) === false) prepare41Stop('Runtimebundle kon niet tijdelijk worden geschreven.');
    @chmod($tmp, 0640);
    clearstatcache(true, $pad);
    if (is_link($pad)) { @unlink($tmp); prepare41Stop('Runtimebundledoel werd tijdens write een symlink.'); }
    if (!@rename($tmp, $pad)) { @unlink($tmp); prepare41Stop('Runtimebundle kon niet atomisch worden geplaatst.'); }
    @chmod($pad, 0640);
    return 'geschreven';
}

$argvLijst = $_SERVER['argv'] ?? [];
foreach ($argvLijst as $arg) {
    if (preg_match('/^--(?:password|hash|secret|dsn|db-password|token|key)(?:=|$)/i', (string)$arg) === 1) {
        prepare41Stop('Secrets horen niet in de runtimebundle of CLI-argumenten.');
    }
}

$opt = getopt('', ['deployment:', 'output-dir::', 'php-version::', 'web-user::', 'web-group::', 'force', 'dry-run', 'help']);
if (isset($opt['help'])) { prepare41Help(); exit(0); }
$deploymentPad = trim((string)($opt['deployment'] ?? ''));
if ($deploymentPad === '') prepare41Stop('--deployment=/absoluut/pad/deployment.json is verplicht.');

try {
    $deployment = runtime41DeploymentLees($deploymentPad);
    $outputDir = isset($opt['output-dir']) && trim((string)$opt['output-dir']) !== ''
        ? (string)$opt['output-dir']
        : $deployment['tenant_root'] . '/runtime';
    $phpVersion = isset($opt['php-version']) && trim((string)$opt['php-version']) !== '' ? (string)$opt['php-version'] : '8.3';
    $webUser = isset($opt['web-user']) && trim((string)$opt['web-user']) !== '' ? (string)$opt['web-user'] : 'www-data';
    $webGroup = isset($opt['web-group']) && trim((string)$opt['web-group']) !== '' ? (string)$opt['web-group'] : 'www-data';
    $plan = runtime41Plan($deployment, $outputDir, $phpVersion, $webUser, $webGroup);
    $planJson = runtime41Json($plan);
    $fpmConfig = runtime41FpmConfig($plan);
} catch (Throwable $e) {
    prepare41Stop($e->getMessage());
}

if (isset($opt['dry-run'])) {
    echo $planJson;
    exit(0);
}

if (!is_dir($outputDir)) {
    $parent = dirname($outputDir);
    try { runtime41BestaandPad($parent, 'Parent van runtime outputmap', true); }
    catch (Throwable $e) { prepare41Stop($e->getMessage()); }
    if (!@mkdir($outputDir, 0750) && !is_dir($outputDir)) prepare41Stop('Runtime outputmap kon niet worden aangemaakt.');
}
@chmod($outputDir, 0750);
try {
    $outputDirReal = runtime41BestaandPad($outputDir, 'Runtime outputmap', true);
    if (!runtime41Binnen($outputDirReal, $deployment['tenant_root'])) prepare41Stop('Runtime outputmap valt buiten de tenantroot.');
} catch (Throwable $e) {
    prepare41Stop($e->getMessage());
}

$statusPlan = prepare41SchrijfAtomisch($plan['bundle']['plan_file'], $planJson, isset($opt['force']));
$statusFpm = prepare41SchrijfAtomisch($plan['bundle']['php_fpm_file'], $fpmConfig, isset($opt['force']));

echo strtoupper($statusPlan) . '  ' . $plan['bundle']['plan_file'] . "\n";
echo strtoupper($statusFpm) . '  ' . $plan['bundle']['php_fpm_file'] . "\n";
echo 'Runtimebundle gereed voor tenant ' . $plan['tenant_key'] . '. Gebruik apply-vps-runtime.php --check vóór root-toepassing.' . "\n";
