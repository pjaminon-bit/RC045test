<?php
// ============================================================
// Fase 3.5 — server-neutraal VPS deploymentcontract per tenant
// ============================================================
// Deze tool installeert geen DNS/TLS/vhost en bevat geen secrets. Hij valideert
// dat een geprovisioneerde tenant fysiek klopt en schrijft daarna één
// deterministische deployment.json die latere Apache/Nginx/PHP-FPM automation
// als bron van waarheid kan gebruiken.
// ============================================================
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Alleen via CLI beschikbaar.');
}

require_once dirname(__DIR__) . '/app/core/tenant-runtime.php';

function deploy35Stop(string $melding, int $code = 1): void
{
    fwrite(STDERR, "FOUT: {$melding}\n");
    exit($code);
}

function deploy35Help(): void
{
    echo "Gebruik:\n";
    echo "  php bin/prepare-vps-deployment.php \\\n";
    echo "    --config=/srv/verenigingen/club/config.php \\\n";
    echo "    --app-root=/srv/verenigingsplatform/current [opties]\n\n";
    echo "Opties:\n";
    echo "  --output=/srv/verenigingen/club/deployment.json\n";
    echo "                         standaard: deployment.json naast config.php\n";
    echo "  --force                afwijkend bestaand deploymentbestand gecontroleerd vervangen\n";
    echo "  --dry-run              valideer volledig en toon JSON zonder te schrijven\n";
    echo "  --help                 toon deze hulp\n\n";
    echo "De tool accepteert bewust geen wachtwoorden, hashes, DSN's of andere secrets.\n";
}

function deploy35NormPad(string $pad): string
{
    $pad = str_replace('\\', '/', $pad);
    $pad = (string)preg_replace('~/+~', '/', $pad);
    if ($pad !== '/') $pad = rtrim($pad, '/');
    if (DIRECTORY_SEPARATOR === '\\') $pad = strtolower($pad);
    return $pad;
}

function deploy35Binnen(string $pad, string $root): bool
{
    $pad = deploy35NormPad($pad);
    $root = deploy35NormPad($root);
    return $pad === $root || strncmp($pad, $root . '/', strlen($root) + 1) === 0;
}

function deploy35RelatieveSegmenten(string $pad): bool
{
    foreach (explode('/', str_replace('\\', '/', $pad)) as $segment) {
        if ($segment === '.' || $segment === '..') return true;
    }
    return false;
}

function deploy35SymlinkInPad(string $pad): ?string
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

/** Tenantpaden zijn securitygrenzen en mogen nergens via een symlink lopen. */
function deploy35TenantPad(string $pad, string $label, bool $map = false): string
{
    if (!tenantRuntimeIsAbsoluutPad($pad) || deploy35RelatieveSegmenten($pad)) {
        deploy35Stop("{$label} moet een absoluut pad zonder . of .. segmenten zijn.");
    }
    $link = deploy35SymlinkInPad($pad);
    if ($link !== null) deploy35Stop("{$label} mag geen symlink bevatten: {$link}");
    $real = realpath($pad);
    if ($real === false) deploy35Stop("{$label} bestaat niet of kan niet fysiek worden opgelost.");
    if ($map ? !is_dir($real) : !is_file($real)) deploy35Stop("{$label} heeft niet het verwachte bestandstype.");
    return rtrim($real, '/\\');
}

/**
 * De gedeelde code-root mag bewust een release-symlink zoals `current` zijn.
 * We bewaren zowel het logische pad voor de webserver als het fysieke releasepad
 * voor audit/controle. Tenantpaden zelf blijven daarentegen volledig symlinkvrij.
 */
function deploy35AppRoot(string $pad): array
{
    if (!tenantRuntimeIsAbsoluutPad($pad) || deploy35RelatieveSegmenten($pad) || str_contains($pad, "\0")) {
        deploy35Stop('--app-root moet een absoluut pad zonder . of .. segmenten zijn.');
    }
    $logisch = rtrim($pad, '/\\');
    $real = realpath($logisch);
    if ($real === false || !is_dir($real)) deploy35Stop('--app-root bestaat niet of is geen map.');
    $real = rtrim($real, '/\\');

    foreach (['site-config.php', 'auth.php', 'public-content.php', 'public-asset.php', 'bin/provision-tenant.php', 'bin/bootstrap-tenant-admin.php'] as $bestand) {
        if (!is_file($real . DIRECTORY_SEPARATOR . $bestand)) {
            deploy35Stop("--app-root lijkt geen volledige verenigingsplatformrelease te zijn; ontbreekt: {$bestand}");
        }
    }
    return ['logical' => $logisch, 'real' => $real];
}

function deploy35ValideTenantKey(string $key): bool
{
    return strlen($key) >= 3
        && strlen($key) <= 63
        && $key !== 'default'
        && !str_contains($key, '--')
        && preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D', $key) === 1
        && hash_equals($key, tenantRuntimeVeiligeSleutel($key));
}

function deploy35SiteUrl(string $url): array
{
    $delen = parse_url($url);
    if (!is_array($delen) || strtolower((string)($delen['scheme'] ?? '')) !== 'https') {
        deploy35Stop('VPS-deployment vereist een canonieke https site_url.');
    }
    if (isset($delen['user']) || isset($delen['pass']) || isset($delen['port']) || isset($delen['query']) || isset($delen['fragment'])) {
        deploy35Stop('site_url voor VPS-deployment mag geen credentials, poort, query of fragment bevatten.');
    }
    $pad = (string)($delen['path'] ?? '');
    if ($pad !== '' && $pad !== '/') deploy35Stop('VPS-deployment verwacht de vereniging op de domeinroot, niet onder een URL-subpad.');

    $host = strtolower((string)($delen['host'] ?? ''));
    if ($host === '' || strlen($host) > 253 || preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $host) !== 1) {
        deploy35Stop('site_url bevat geen geldige publieke DNS-hostnaam.');
    }
    return ['url' => 'https://' . $host, 'host' => $host];
}

function deploy35RuntimeEnv(string $pad, string $configPad, string $privateRoot): array
{
    $pad = deploy35TenantPad($pad, 'runtime.env');
    $regels = preg_split('/\r\n|\n|\r/', (string)file_get_contents($pad));
    $waarden = [];
    foreach ($regels ?: [] as $regel) {
        if ($regel === '') continue;
        $pos = strpos($regel, '=');
        if ($pos === false) deploy35Stop('runtime.env bevat een ongeldige regel zonder =.');
        $key = substr($regel, 0, $pos);
        $waarde = substr($regel, $pos + 1);
        if (isset($waarden[$key])) deploy35Stop("runtime.env bevat dubbele sleutel: {$key}");
        $waarden[$key] = $waarde;
    }
    $verwacht = [
        'VERENIGING_REQUIRE_TENANT_CONFIG' => '1',
        'VERENIGING_CONFIG_FILE' => $configPad,
        'VERENIGING_PRIVATE_ROOT' => $privateRoot,
    ];
    if (array_keys($waarden) !== array_keys($verwacht)) deploy35Stop('runtime.env bevat niet exact het verwachte fail-closed runtimecontract.');
    foreach ($verwacht as $key => $waarde) {
        if (!isset($waarden[$key]) || !hash_equals($waarde, (string)$waarden[$key])) {
            deploy35Stop("runtime.env binding klopt niet voor {$key}.");
        }
    }
    return $verwacht;
}

function deploy35MasterGereed(string $privateRoot): void
{
    $masterPad = deploy35TenantPad($privateRoot . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'master.php', 'Mastercredential');
    $inhoud = (string)file_get_contents($masterPad);
    if (str_contains($inhoud, '$BEHEER_WACHTWOORD =')) deploy35Stop('Mastercredential bevat nog een plaintext compatibility-variabele.');
    if (preg_match('/\$BEHEER_WACHTWOORD_HASH\s*=\s*\'([^\']+)\'\s*;/D', $inhoud, $m) !== 1) {
        deploy35Stop('Mastercredential heeft niet het door fase 3.4 verwachte hashformaat.');
    }
    $info = password_get_info($m[1]);
    if (($info['algoName'] ?? 'unknown') === 'unknown') deploy35Stop('Mastercredential bevat geen geldige password_hash.');
}

function deploy35Context(string $configInvoer, string $appRootInvoer): array
{
    $app = deploy35AppRoot($appRootInvoer);
    $configPad = deploy35TenantPad($configInvoer, 'Tenantconfig');
    if (deploy35Binnen($configPad, $app['real'])) deploy35Stop('Tenantconfig mag niet binnen de gedeelde applicatiecode staan.');

    $config = require $configPad;
    if (!is_array($config)) deploy35Stop('Tenantconfig moet een PHP-array retourneren.');
    $tenantKey = (string)($config['vereniging']['sleutel'] ?? '');
    if (!deploy35ValideTenantKey($tenantKey)) deploy35Stop('Tenantconfig bevat geen geldige canonieke tenant-key.');

    $site = deploy35SiteUrl((string)($config['vereniging']['site_url'] ?? ''));
    try {
        $privateInvoer = tenantRuntimePrivateRoot($config);
    } catch (Throwable $e) {
        deploy35Stop('Tenantconfig bevat geen veilige private_root.');
    }
    if ($privateInvoer === null) deploy35Stop('VPS-deployment is alleen toegestaan voor externe tenants met private_root.');
    $privateRoot = deploy35TenantPad($privateInvoer, 'Private tenantroot', true);
    $tenantRoot = dirname($privateRoot);

    if (deploy35NormPad($privateRoot) !== deploy35NormPad($tenantRoot . DIRECTORY_SEPARATOR . 'private')) {
        deploy35Stop('Private root moet de vaste private/ map van de tenantroot zijn.');
    }
    if (deploy35NormPad(dirname($configPad)) !== deploy35NormPad($tenantRoot)
        || deploy35NormPad(basename($configPad)) !== 'config.php') {
        deploy35Stop('Tenantconfig en private_root horen niet bij dezelfde provisioned tenantroot.');
    }
    if (deploy35Binnen($tenantRoot, $app['real']) || deploy35Binnen($app['real'], $tenantRoot)) {
        deploy35Stop('Gedeelde applicatiecode en tenantroot moeten fysiek volledig gescheiden zijn.');
    }

    $manifestPad = deploy35TenantPad($tenantRoot . DIRECTORY_SEPARATOR . 'tenant.json', 'Tenantmanifest');
    $manifest = json_decode((string)file_get_contents($manifestPad), true);
    if (!is_array($manifest)) deploy35Stop('Tenantmanifest bevat geen geldige JSON.');
    if (!hash_equals($tenantKey, (string)($manifest['tenant_key'] ?? ''))) deploy35Stop('Tenantmanifest hoort bij een andere tenant-key.');
    if (($manifest['require_tenant_config'] ?? false) !== true) deploy35Stop('Tenantmanifest staat niet in fail-closed tenantmodus.');

    $manifestConfig = deploy35TenantPad((string)($manifest['config_file'] ?? ''), 'Manifest config_file');
    $manifestPrivate = deploy35TenantPad((string)($manifest['private_root'] ?? ''), 'Manifest private_root', true);
    if (deploy35NormPad($manifestConfig) !== deploy35NormPad($configPad)
        || deploy35NormPad($manifestPrivate) !== deploy35NormPad($privateRoot)) {
        deploy35Stop('Tenantmanifest bindt niet aan deze config/private_root combinatie.');
    }
    if (!hash_equals($site['url'], rtrim((string)($manifest['site_url'] ?? ''), '/'))) {
        deploy35Stop('Tenantmanifest en tenantconfig verschillen in site_url.');
    }

    $runtime = deploy35RuntimeEnv($tenantRoot . DIRECTORY_SEPARATOR . 'runtime.env', $configPad, $privateRoot);
    deploy35MasterGereed($privateRoot);

    $hash = substr(hash('sha256', $tenantKey), 0, 12);
    $poolLabel = substr($tenantKey, 0, 24);
    $pool = 'vst-' . $poolLabel . '-' . $hash;
    $osUser = 'vst' . substr(hash('sha256', "user\0" . $tenantKey), 0, 16);
    $socket = '/run/php/' . $pool . '.sock';

    $descriptor = [
        'schema' => 1,
        'tenant_key' => $tenantKey,
        'site_url' => $site['url'],
        'canonical_host' => $site['host'],
        'shared_code' => [
            'app_root' => $app['logical'],
            'app_root_real' => $app['real'],
            'document_root' => $app['logical'],
            'read_only_for_tenant_runtime' => true,
        ],
        'tenant' => [
            'tenant_root' => $tenantRoot,
            'config_file' => $configPad,
            'private_root' => $privateRoot,
            'private_driver' => (string)($config['opslag']['private_driver'] ?? 'json'),
        ],
        'runtime_env' => $runtime,
        'php_fpm' => [
            'pool' => $pool,
            'socket' => $socket,
            'recommended_os_user' => $osUser,
            'clear_env' => true,
            'one_pool_per_tenant' => true,
        ],
        'web' => [
            'https_required' => true,
            'canonical_host' => $site['host'],
            'serve_only_shared_app_root' => true,
            'private_root_must_never_be_document_root' => true,
            'tenant_runtime_selected_by_php_pool' => true,
        ],
        'readiness' => [
            'manifest_bound' => true,
            'runtime_env_bound' => true,
            'admin_bootstrapped' => true,
            'tenant_storage_outside_app_root' => true,
        ],
    ];

    return compact('descriptor', 'tenantRoot');
}

function deploy35Schrijf(string $pad, string $inhoud, string $tenantRoot, bool $force, bool $dryRun): string
{
    if (!tenantRuntimeIsAbsoluutPad($pad) || deploy35RelatieveSegmenten($pad)) deploy35Stop('--output moet een absoluut veilig pad zijn.');
    if (!deploy35Binnen($pad, $tenantRoot)) deploy35Stop('--output moet binnen de provisioned tenantroot blijven.');
    $parent = dirname($pad);
    $parentReal = deploy35TenantPad($parent, 'Outputmap', true);
    if (!deploy35Binnen($parentReal, $tenantRoot)) deploy35Stop('Outputmap valt buiten de tenantroot.');
    if (is_link($pad) || deploy35SymlinkInPad($pad) !== null) deploy35Stop('Deployment-output mag geen symlink bevatten.');

    if (is_file($pad)) {
        $huidig = (string)file_get_contents($pad);
        if (hash_equals(hash('sha256', $huidig), hash('sha256', $inhoud))) return 'ongewijzigd';
        if (!$force) deploy35Stop('Deployment-output bestaat al met andere inhoud; gebruik --force na controle.');
    } elseif (file_exists($pad)) {
        deploy35Stop('Deployment-output bestaat maar is geen regulier bestand.');
    }

    if ($dryRun) return is_file($pad) ? 'zou vervangen' : 'zou aanmaken';

    $tmp = $parentReal . DIRECTORY_SEPARATOR . '.deployment.json.tmp.' . bin2hex(random_bytes(8));
    if (is_link($tmp) || deploy35SymlinkInPad($tmp) !== null) deploy35Stop('Onveilig tijdelijk deploymentpad.');
    if (@file_put_contents($tmp, $inhoud, LOCK_EX) === false) deploy35Stop('Deployment-output kon niet tijdelijk worden geschreven.');
    @chmod($tmp, 0640);
    clearstatcache(true, $pad);
    if (is_link($pad)) { @unlink($tmp); deploy35Stop('Deployment-output werd tijdens write een symlink.'); }
    if (!@rename($tmp, $pad)) { @unlink($tmp); deploy35Stop('Deployment-output kon niet atomisch worden geplaatst.'); }
    @chmod($pad, 0640);
    return 'geschreven';
}

$opt = getopt('', ['config:', 'app-root:', 'output::', 'force', 'dry-run', 'help']);
if (isset($opt['help'])) { deploy35Help(); exit(0); }
foreach (['config', 'app-root'] as $vereist) {
    if (!isset($opt[$vereist]) || trim((string)$opt[$vereist]) === '') deploy35Stop("--{$vereist} is verplicht.");
}
foreach ($_SERVER['argv'] ?? [] as $arg) {
    if (preg_match('/^--(?:password|hash|secret|dsn|db-password)(?:=|$)/i', (string)$arg) === 1) {
        deploy35Stop('Secrets horen niet in het VPS deploymentcontract of in CLI-argumenten.');
    }
}

$context = deploy35Context((string)$opt['config'], (string)$opt['app-root']);
$json = json_encode($context['descriptor'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
if (!is_string($json)) deploy35Stop('Deploymentdescriptor kon niet als JSON worden opgebouwd.');
$json .= "\n";

if (isset($opt['dry-run'])) {
    echo $json;
    exit(0);
}
$output = isset($opt['output']) && trim((string)$opt['output']) !== ''
    ? (string)$opt['output']
    : $context['tenantRoot'] . DIRECTORY_SEPARATOR . 'deployment.json';
$status = deploy35Schrijf($output, $json, $context['tenantRoot'], isset($opt['force']), false);
echo strtoupper($status) . "  {$output}\n";
echo "VPS deploymentcontract gereed voor tenant {$context['descriptor']['tenant_key']} ({$context['descriptor']['canonical_host']}).\n";
