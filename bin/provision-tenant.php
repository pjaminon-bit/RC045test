<?php
// ============================================================
// Tenant provisioner — alleen CLI
// ============================================================
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Alleen via CLI beschikbaar.');
}
require_once dirname(__DIR__) . '/app/core/tenant-runtime.php';

function provisionStop(string $melding, int $code = 1): void
{
    fwrite(STDERR, "FOUT: {$melding}\n");
    exit($code);
}

function provisionHelp(): void
{
    echo "Gebruik:\n";
    echo "  php bin/provision-tenant.php --key=vereniging --name=\"Vereniging\" --url=https://vereniging.example --root=/srv/verenigingen [opties]\n\n";
    echo "Opties:\n";
    echo "  --timezone=Europe/Amsterdam\n";
    echo "  --driver=json|pdo\n";
    echo "  --force                 bestaande config gecontroleerd vervangen\n";
    echo "  --dry-run               alleen tonen wat zou worden aangemaakt\n";
}

function provisionAbsoluut(string $pad): bool
{
    return tenantRuntimeIsAbsoluutPad($pad);
}

function provisionNormalizeBase(string $pad): string
{
    $pad = rtrim(trim($pad), '/\\');
    if ($pad === '' || !provisionAbsoluut($pad)) provisionStop('--root moet een absoluut pad zijn.');
    return $pad;
}

function provisionOnderProjectroot(string $pad): bool
{
    $project = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
    $project = rtrim(str_replace('\\', '/', $project), '/') . '/';
    $norm = rtrim(str_replace('\\', '/', $pad), '/') . '/';
    return strncmp($norm, $project, strlen($project)) === 0;
}

function provisionPhpArray(array $config): string
{
    return "<?php\n// Gegenereerd door bin/provision-tenant.php\nreturn " . var_export($config, true) . ";\n";
}

function provisionSchrijf(string $pad, string $inhoud, bool $force, bool $dryRun): string
{
    if (is_file($pad)) {
        $huidig = (string) file_get_contents($pad);
        if (hash_equals(hash('sha256', $huidig), hash('sha256', $inhoud))) return 'ongewijzigd';
        if (!$force) provisionStop("{$pad} bestaat al met andere inhoud; gebruik --force na controle.");
    }
    if ($dryRun) return is_file($pad) ? 'zou vervangen' : 'zou aanmaken';
    $map = dirname($pad);
    if (!is_dir($map) && !@mkdir($map, 0750, true)) provisionStop("map {$map} kon niet worden aangemaakt.");
    $tmp = $pad . '.tmp.' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $inhoud, LOCK_EX) === false) provisionStop("tijdelijk bestand voor {$pad} kon niet worden geschreven.");
    @chmod($tmp, 0640);
    if (!@rename($tmp, $pad)) { @unlink($tmp); provisionStop("{$pad} kon niet atomisch worden geplaatst."); }
    return is_file($pad) ? 'geschreven' : 'aangemaakt';
}

$opt = getopt('', ['key:', 'name:', 'url:', 'root:', 'timezone::', 'driver::', 'force', 'dry-run', 'help']);
if (isset($opt['help'])) { provisionHelp(); exit(0); }
foreach (['key','name','url','root'] as $vereist) if (!isset($opt[$vereist]) || trim((string)$opt[$vereist]) === '') provisionStop("--{$vereist} is verplicht.");

$key = tenantRuntimeVeiligeSleutel((string)$opt['key']);
$naam = trim((string)$opt['name']);
$url = rtrim(trim((string)$opt['url']), '/');
$baseRoot = provisionNormalizeBase((string)$opt['root']);
$timezone = trim((string)($opt['timezone'] ?? 'Europe/Amsterdam')) ?: 'Europe/Amsterdam';
$driver = strtolower(trim((string)($opt['driver'] ?? 'json')));
$force = isset($opt['force']);
$dryRun = isset($opt['dry-run']);

if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array((string)parse_url($url, PHP_URL_SCHEME), ['http','https'], true)) provisionStop('--url moet een geldige http(s)-URL zijn.');
if (!in_array($timezone, timezone_identifiers_list(), true)) provisionStop('Ongeldige timezone.');
if (!in_array($driver, ['json','pdo'], true)) provisionStop('--driver moet json of pdo zijn.');

$tenantRoot = $baseRoot . DIRECTORY_SEPARATOR . $key;
if (provisionOnderProjectroot($tenantRoot)) provisionStop('Tenantroot mag niet binnen de gedeelde applicatie/documentroot liggen.');
$privateRoot = $tenantRoot . DIRECTORY_SEPARATOR . 'private';
$configPad = $tenantRoot . DIRECTORY_SEPARATOR . 'config.php';
$envPad = $tenantRoot . DIRECTORY_SEPARATOR . 'runtime.env';
$manifestPad = $tenantRoot . DIRECTORY_SEPARATOR . 'tenant.json';

$config = [
    'vereniging' => [
        'sleutel' => $key,
        'naam' => $naam,
        'volledige_naam' => $naam,
        'slogan' => '',
        'site_url' => $url,
        'timezone' => $timezone,
        'standaard_taal' => 'nl',
    ],
    'opslag' => [
        'private_driver' => $driver,
        'private_root' => $privateRoot,
        'pdo' => ['dsn'=>'','user'=>'','password'=>''],
    ],
];

// Iedere geprovisioneerde tenant draait bewust fail-closed. Als de vhost of
// process manager runtime.env niet correct toepast, mag de applicatie nooit
// stil op RC045/defaultconfiguratie terugvallen.
$env = "VERENIGING_REQUIRE_TENANT_CONFIG=1\n"
    . "VERENIGING_CONFIG_FILE={$configPad}\n"
    . "VERENIGING_PRIVATE_ROOT={$privateRoot}\n";
$manifest = json_encode([
    'schema' => 1,
    'tenant_key' => $key,
    'name' => $naam,
    'site_url' => $url,
    'timezone' => $timezone,
    'private_driver' => $driver,
    'config_file' => $configPad,
    'private_root' => $privateRoot,
    'require_tenant_config' => true,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

$dirs = [$tenantRoot, $privateRoot, $privateRoot.'/collections', $privateRoot.'/backups'];
foreach ($dirs as $dir) {
    if ($dryRun) { echo "DIR  {$dir}\n"; continue; }
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) provisionStop("map {$dir} kon niet worden aangemaakt.");
    @chmod($dir, 0750);
}

$resultaten = [
    $configPad => provisionSchrijf($configPad, provisionPhpArray($config), $force, $dryRun),
    $envPad => provisionSchrijf($envPad, $env, $force, $dryRun),
    $manifestPad => provisionSchrijf($manifestPad, $manifest, $force, $dryRun),
];

foreach ($resultaten as $pad => $status) echo strtoupper($status) . "  {$pad}\n";
echo "\nTenant klaar: {$key}\n";
echo "Runtime: export VERENIGING_REQUIRE_TENANT_CONFIG=1\n";
echo "         export VERENIGING_CONFIG_FILE=" . escapeshellarg($configPad) . "\n";
echo "Private opslag: {$privateRoot}\n";
