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
    echo "Tenant-key:\n";
    echo "  3-63 tekens; alleen a-z, 0-9 en enkele koppeltekens; geen --; 'default' is gereserveerd.\n\n";
    echo "Opties:\n";
    echo "  --timezone=Europe/Amsterdam\n";
    echo "  --driver=json|pdo\n";
    echo "  --modules=website,ledenadministratie,...\n";
    echo "                           expliciete kommagescheiden modulekeuze; zonder optie zijn alle platformmodules actief\n";
    echo "  --force                 bestaande config gecontroleerd vervangen\n";
    echo "  --dry-run               alleen tonen wat zou worden aangemaakt\n";
}

function provisionAbsoluut(string $pad): bool
{
    return tenantRuntimeIsAbsoluutPad($pad);
}

/**
 * Een tenant-key is een permanente technische identiteit, geen gebruikerslabel.
 * Daarom wordt invoer hier nooit getrimd, lowercased of anders genormaliseerd.
 */
function provisionValideTenantKey(string $waarde): string
{
    if ($waarde !== trim($waarde)) {
        provisionStop('--key mag geen voor- of achterliggende whitespace bevatten.');
    }
    if (strlen($waarde) < 3 || strlen($waarde) > 63) {
        provisionStop('--key moet tussen 3 en 63 ASCII-tekens lang zijn.');
    }
    if (preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D', $waarde) !== 1) {
        provisionStop('--key mag alleen lowercase a-z, cijfers en koppeltekens bevatten en niet met een koppelteken beginnen/eindigen.');
    }
    if (str_contains($waarde, '--')) {
        provisionStop('--key mag geen dubbele koppeltekens bevatten.');
    }
    if ($waarde === 'default') {
        provisionStop("--key 'default' is gereserveerd en mag niet als tenantidentiteit worden gebruikt.");
    }
    if (!hash_equals($waarde, tenantRuntimeVeiligeSleutel($waarde))) {
        provisionStop('--key is niet canoniek voor de tenant-runtime.');
    }
    return $waarde;
}

/** De vaste platformmodulelijst is onderdeel van het provisioningcontract. */
function provisionBeschikbareModules(): array
{
    return [
        'website',
        'ledenadministratie',
        'werkgroepen',
        'evenementen',
        'vergaderingen',
        'taken',
        'operationele_taken',
        'fotoboek',
        'sponsors',
        'media',
        'aanmelden',
    ];
}

/**
 * Zonder --modules blijft het bestaande gedrag behouden: alle platformmodules
 * zijn actief. Met --modules wordt juist iedere bekende module expliciet true
 * of false opgeslagen, zodat een tenant nooit modulekeuzes uit RC045-defaults
 * hoeft te erven.
 */
function provisionModuleKeuze(?string $waarde): array
{
    $beschikbaar = provisionBeschikbareModules();
    if ($waarde === null) {
        $actief = $beschikbaar;
    } else {
        $actief = [];
        foreach (explode(',', $waarde) as $module) {
            $module = trim($module);
            if ($module === '') continue;
            if (!in_array($module, $beschikbaar, true)) {
                provisionStop("Onbekende module in --modules: {$module}.");
            }
            if (!in_array($module, $actief, true)) $actief[] = $module;
        }
        if (!in_array('website', $actief, true)) {
            provisionStop("--modules moet de kernmodule 'website' bevatten.");
        }
    }

    $resultaat = [];
    foreach ($beschikbaar as $module) {
        $resultaat[$module] = in_array($module, $actief, true);
    }
    return $resultaat;
}

/**
 * Nieuwe VPS-tenants krijgen neutrale platformbranding. Lege assetpaden zijn
 * bewust: een nieuwe vereniging mag nooit stil het RC045-logo, favicons of
 * manifest gebruiken. Eigen branding kan daarna tenant-specifiek worden gezet.
 */
function provisionNeutraleBranding(): array
{
    return [
        'logo' => '',
        'social_image' => '',
        'favicon' => '',
        'favicon_16' => '',
        'favicon_32' => '',
        'favicon_48' => '',
        'apple_touch_icon' => '',
        'manifest' => '',
        'theme_color' => '#0F172A',
        'kleuren' => [
            'primary' => '#2563EB',
            'primary_dark' => '#1D4ED8',
            'primary_light' => '#EFF6FF',
            'accent' => '#D97706',
            'accent_light' => '#FFFBEB',
            'dark' => '#0F172A',
            'text' => '#1E293B',
            'muted' => '#64748B',
            'background' => '#F8FAFC',
        ],
    ];
}

function provisionPadVoorVergelijk(string $pad): string
{
    $norm = str_replace('\\', '/', $pad);
    $norm = (string)preg_replace('~/+~', '/', $norm);
    if ($norm !== '/') $norm = rtrim($norm, '/');
    if (DIRECTORY_SEPARATOR === '\\') $norm = strtolower($norm);
    return $norm;
}

function provisionPadBinnen(string $pad, string $root): bool
{
    $pad = provisionPadVoorVergelijk($pad);
    $root = provisionPadVoorVergelijk($root);
    return $pad === $root || strncmp($pad, $root . '/', strlen($root) + 1) === 0;
}

function provisionHeeftRelatieveSegmenten(string $pad): bool
{
    foreach (explode('/', str_replace('\\', '/', $pad)) as $segment) {
        if ($segment === '.' || $segment === '..') return true;
    }
    return false;
}

/** Geeft de eerste bestaande (ook broken) symlink in het pad of ancestors. */
function provisionSymlinkInPad(string $pad): ?string
{
    $cursor = rtrim($pad, '/\\');
    if ($cursor === '') $cursor = DIRECTORY_SEPARATOR;
    while (true) {
        if (is_link($cursor)) return $cursor;
        $parent = dirname($cursor);
        if ($parent === $cursor) break;
        $cursor = $parent;
    }
    return null;
}

/** Canonicaliseert ook een nog niet bestaand doel via de langste ancestor. */
function provisionCanoniekDoelpad(string $pad): string
{
    $cursor = rtrim($pad, '/\\');
    if ($cursor === '') $cursor = DIRECTORY_SEPARATOR;
    $staart = [];

    while (!file_exists($cursor) && !is_link($cursor)) {
        $deel = basename($cursor);
        if ($deel === '' || $deel === '.' || $deel === '..') {
            provisionStop("Pad {$pad} kan niet veilig worden gecanonicaliseerd.");
        }
        array_unshift($staart, $deel);
        $parent = dirname($cursor);
        if ($parent === $cursor) provisionStop("Geen bestaande ancestor gevonden voor {$pad}.");
        $cursor = $parent;
    }

    if (is_link($cursor)) provisionStop("Symlink in provisioningpad is niet toegestaan: {$cursor}");
    if (count($staart) > 0 && !is_dir($cursor)) provisionStop("Bestaande ancestor van provisioningpad is geen map: {$cursor}");

    $basis = realpath($cursor);
    if ($basis === false) provisionStop("Provisioningpad kon niet fysiek worden opgelost: {$cursor}");
    foreach ($staart as $deel) $basis .= DIRECTORY_SEPARATOR . $deel;
    return rtrim($basis, '/\\');
}

function provisionProjectRoot(): string
{
    $project = realpath(dirname(__DIR__));
    if ($project === false) provisionStop('Applicatieroot kon niet fysiek worden opgelost.');
    return rtrim($project, '/\\');
}

function provisionControleerBuitenProject(string $pad): void
{
    $canoniek = provisionCanoniekDoelpad($pad);
    if (provisionPadBinnen($canoniek, provisionProjectRoot())) {
        provisionStop('Tenantroot mag niet binnen de gedeelde applicatie/documentroot liggen.');
    }
}

function provisionNormalizeBase(string $pad): string
{
    $pad = rtrim(trim($pad), '/\\');
    if ($pad === '' || !provisionAbsoluut($pad)) provisionStop('--root moet een absoluut pad zijn.');
    if (str_contains($pad, "\0")) provisionStop('--root bevat een ongeldig nulkarakter.');
    if (provisionHeeftRelatieveSegmenten($pad)) provisionStop('--root mag geen . of .. padsegmenten bevatten.');
    $symlink = provisionSymlinkInPad($pad);
    if ($symlink !== null) provisionStop("--root mag geen symlink bevatten: {$symlink}");
    return provisionCanoniekDoelpad($pad);
}

/** Controleert een afgeleid tenantpad vlak voor ieder gevoelig gebruik. */
function provisionControleerTenantpad(string $pad, string $tenantRoot): void
{
    $symlink = provisionSymlinkInPad($pad);
    if ($symlink !== null) provisionStop("Symlink in tenantpad is niet toegestaan: {$symlink}");
    $canoniek = provisionCanoniekDoelpad($pad);
    if (!provisionPadBinnen($canoniek, $tenantRoot)) {
        provisionStop("Tenantpad valt buiten de goedgekeurde tenantroot: {$pad}");
    }
    if (provisionPadBinnen($canoniek, provisionProjectRoot())) {
        provisionStop("Tenantpad valt binnen de applicatie/documentroot: {$pad}");
    }
}

function provisionMaakMap(string $pad, string $tenantRoot, bool $dryRun): void
{
    provisionControleerTenantpad($pad, $tenantRoot);
    if ($dryRun) {
        echo "DIR  {$pad}\n";
        return;
    }
    if (!is_dir($pad) && !@mkdir($pad, 0750, false)) {
        provisionStop("map {$pad} kon niet worden aangemaakt.");
    }
    clearstatcache(true, $pad);
    if (is_link($pad) || !is_dir($pad)) provisionStop("map {$pad} is na aanmaak geen veilige gewone map.");
    @chmod($pad, 0750);
    provisionControleerTenantpad($pad, $tenantRoot);
}

function provisionPhpArray(array $config): string
{
    return "<?php\n// Gegenereerd door bin/provision-tenant.php\nreturn " . var_export($config, true) . ";\n";
}

function provisionSchrijf(string $pad, string $inhoud, bool $force, bool $dryRun, string $tenantRoot): string
{
    provisionControleerTenantpad($pad, $tenantRoot);
    if (is_link($pad)) provisionStop("Bestandsdoel mag geen symlink zijn: {$pad}");

    if (is_file($pad)) {
        $huidig = (string)file_get_contents($pad);
        if (hash_equals(hash('sha256', $huidig), hash('sha256', $inhoud))) return 'ongewijzigd';
        if (!$force) provisionStop("{$pad} bestaat al met andere inhoud; gebruik --force na controle.");
    }
    if ($dryRun) return is_file($pad) ? 'zou vervangen' : 'zou aanmaken';

    $map = dirname($pad);
    provisionControleerTenantpad($map, $tenantRoot);
    if (!is_dir($map)) provisionStop("schrijfmap {$map} bestaat niet.");

    $tmp = $pad . '.tmp.' . bin2hex(random_bytes(4));
    provisionControleerTenantpad($tmp, $tenantRoot);
    if (@file_put_contents($tmp, $inhoud, LOCK_EX) === false) provisionStop("tijdelijk bestand voor {$pad} kon niet worden geschreven.");
    @chmod($tmp, 0640);

    provisionControleerTenantpad($pad, $tenantRoot);
    if (is_link($pad)) {
        @unlink($tmp);
        provisionStop("Bestandsdoel werd tijdens provisioning een symlink: {$pad}");
    }
    if (!@rename($tmp, $pad)) {
        @unlink($tmp);
        provisionStop("{$pad} kon niet atomisch worden geplaatst.");
    }
    @chmod($pad, 0640);
    return is_file($pad) ? 'geschreven' : 'aangemaakt';
}

$opt = getopt('', ['key:', 'name:', 'url:', 'root:', 'timezone::', 'driver::', 'modules::', 'force', 'dry-run', 'help']);
if (isset($opt['help'])) {
    provisionHelp();
    exit(0);
}
foreach (['key', 'name', 'url', 'root'] as $vereist) {
    if (!isset($opt[$vereist]) || trim((string)$opt[$vereist]) === '') provisionStop("--{$vereist} is verplicht.");
}

$key = provisionValideTenantKey((string)$opt['key']);
$naam = trim((string)$opt['name']);
$url = rtrim(trim((string)$opt['url']), '/');
$baseRoot = provisionNormalizeBase((string)$opt['root']);
$timezone = trim((string)($opt['timezone'] ?? 'Europe/Amsterdam')) ?: 'Europe/Amsterdam';
$driver = strtolower(trim((string)($opt['driver'] ?? 'json')));
$modulesOptie = array_key_exists('modules', $opt) ? (string)$opt['modules'] : null;
$modules = provisionModuleKeuze($modulesOptie);
$force = isset($opt['force']);
$dryRun = isset($opt['dry-run']);

if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array((string)parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
    provisionStop('--url moet een geldige http(s)-URL zijn.');
}
if (!in_array($timezone, timezone_identifiers_list(), true)) provisionStop('Ongeldige timezone.');
if (!in_array($driver, ['json', 'pdo'], true)) provisionStop('--driver moet json of pdo zijn.');

$tenantRoot = rtrim($baseRoot, '/\\') . DIRECTORY_SEPARATOR . $key;
$tenantRoot = provisionCanoniekDoelpad($tenantRoot);
provisionControleerBuitenProject($tenantRoot);
$symlinkTenant = provisionSymlinkInPad($tenantRoot);
if ($symlinkTenant !== null) provisionStop("Tenantroot mag geen symlink bevatten: {$symlinkTenant}");

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
    'branding' => provisionNeutraleBranding(),
    'modules' => $modules,
    'opslag' => [
        'private_driver' => $driver,
        'private_root' => $privateRoot,
        'pdo' => ['dsn' => '', 'user' => '', 'password' => ''],
    ],
];

$env = "VERENIGING_REQUIRE_TENANT_CONFIG=1\n"
    . "VERENIGING_CONFIG_FILE={$configPad}\n"
    . "VERENIGING_PRIVATE_ROOT={$privateRoot}\n";
$actieveModules = array_keys(array_filter($modules, static fn($actief) => $actief === true));
$manifest = json_encode([
    'schema' => 1,
    'tenant_key' => $key,
    'name' => $naam,
    'site_url' => $url,
    'timezone' => $timezone,
    'private_driver' => $driver,
    'modules' => $actieveModules,
    'config_file' => $configPad,
    'private_root' => $privateRoot,
    'require_tenant_config' => true,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

if (!$dryRun && !is_dir($baseRoot)) {
    if (!@mkdir($baseRoot, 0750, true) && !is_dir($baseRoot)) provisionStop("basisroot {$baseRoot} kon niet worden aangemaakt.");
    clearstatcache(true, $baseRoot);
    $symlinkBase = provisionSymlinkInPad($baseRoot);
    if ($symlinkBase !== null) provisionStop("basisroot bevat na aanmaak een symlink: {$symlinkBase}");
    $baseReal = realpath($baseRoot);
    if ($baseReal === false || provisionPadVoorVergelijk($baseReal) !== provisionPadVoorVergelijk($baseRoot)) {
        provisionStop('Basisroot wijst na aanmaak niet naar het vooraf gecontroleerde fysieke pad.');
    }
    @chmod($baseRoot, 0750);
}

$dirs = [
    $tenantRoot,
    $privateRoot,
    $privateRoot . '/collections',
    $privateRoot . '/public-content',
    $privateRoot . '/backups',
    $privateRoot . '/backups/auth',
    $privateRoot . '/auth',
    $privateRoot . '/audit',
    $privateRoot . '/security',
    $privateRoot . '/sessions',
];
foreach ($dirs as $dir) provisionMaakMap($dir, $tenantRoot, $dryRun);

$resultaten = [
    $configPad => provisionSchrijf($configPad, provisionPhpArray($config), $force, $dryRun, $tenantRoot),
    $envPad => provisionSchrijf($envPad, $env, $force, $dryRun, $tenantRoot),
    $manifestPad => provisionSchrijf($manifestPad, $manifest, $force, $dryRun, $tenantRoot),
];

foreach ($resultaten as $pad => $status) echo strtoupper($status) . "  {$pad}\n";
echo "\nTenant klaar: {$key}\n";
echo "Runtime: export VERENIGING_REQUIRE_TENANT_CONFIG=1\n";
echo "         export VERENIGING_CONFIG_FILE=" . escapeshellarg($configPad) . "\n";
echo "Private opslag: {$privateRoot}\n";
echo "Actieve modules: " . implode(', ', $actieveModules) . "\n";
echo "Auth masterconfig: {$privateRoot}/auth/master.php (nog apart instellen)\n";
