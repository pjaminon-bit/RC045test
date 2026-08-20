<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function check33(bool $cond, string $label): void
{
    global $ok, $fout;
    if ($cond) {
        $ok++;
        echo "OK: {$label}\n";
    } else {
        $fout++;
        fwrite(STDERR, "FOUT: {$label}\n");
    }
}

function rr33(string $dir): void
{
    if (is_link($dir)) { @unlink($dir); return; }
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $pad = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($pad) && !is_link($pad)) rr33($pad); else @unlink($pad);
    }
    @rmdir($dir);
}

function run33(string $cmd): array
{
    $out = [];
    exec($cmd . ' 2>&1', $out, $code);
    return [$code, implode("\n", $out)];
}

function provision33(string $script, string $base, string $key, string $name, string $url, string $modules): array
{
    $cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($script)
        . ' --key=' . escapeshellarg($key)
        . ' --name=' . escapeshellarg($name)
        . ' --url=' . escapeshellarg($url)
        . ' --root=' . escapeshellarg($base)
        . ' --modules=' . escapeshellarg($modules);
    return run33($cmd);
}

function worker33(string $worker, string $config, array $args): array
{
    $parts = [escapeshellcmd(PHP_BINARY), escapeshellarg($worker)];
    foreach ($args as $arg) $parts[] = escapeshellarg((string)$arg);
    $cmd = 'VERENIGING_REQUIRE_TENANT_CONFIG=1 VERENIGING_CONFIG_FILE=' . escapeshellarg($config) . ' ' . implode(' ', $parts);
    [$code, $raw] = run33($cmd);
    $json = json_decode($raw, true);
    if ($code !== 0 || !is_array($json)) {
        fwrite(STDERR, "WORKER FOUT [" . implode(' ', array_map('strval', $args)) . "] code={$code}: {$raw}\n");
    }
    return [$code, is_array($json) ? $json : null, $raw];
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vereniging-phase33-' . bin2hex(random_bytes(4));
$base = $tmp . '/tenants';
@mkdir($base, 0750, true);
$script = $root . '/bin/provision-tenant.php';

try {
    [$codeA, $outA] = provision33(
        $script,
        $base,
        'noorderhaven',
        'Roeivereniging Noorderhaven',
        'https://noorderhaven.example',
        'website,ledenadministratie,sponsors,fotoboek'
    );
    [$codeB, $outB] = provision33(
        $script,
        $base,
        'duinrand',
        'Wandelclub Duinrand',
        'https://duinrand.example',
        'website,aanmelden,media'
    );
    check33($codeA === 0 && $codeB === 0, 'twee fictieve verenigingen worden uit dezelfde provisioner aangemaakt');

    $tenantA = $base . '/noorderhaven';
    $tenantB = $base . '/duinrand';
    $configA = $tenantA . '/config.php';
    $configB = $tenantB . '/config.php';
    check33(is_file($configA) && is_file($configB), 'beide tenants hebben een eigen server-only configuratie');
    check33(!is_file($tenantA . '/index.php') && !is_file($tenantB . '/index.php'), 'provisioning kopieert de gedeelde applicatiecode niet per tenant');

    $cfgA = require $configA;
    $cfgB = require $configB;
    check33(
        ($cfgA['vereniging']['naam'] ?? '') === 'Roeivereniging Noorderhaven'
        && ($cfgB['vereniging']['naam'] ?? '') === 'Wandelclub Duinrand'
        && ($cfgA['vereniging']['site_url'] ?? '') !== ($cfgB['vereniging']['site_url'] ?? ''),
        'tenantidentiteit en URL zijn volledig gescheiden'
    );
    check33(
        ($cfgA['opslag']['private_root'] ?? '') === $tenantA . '/private'
        && ($cfgB['opslag']['private_root'] ?? '') === $tenantB . '/private',
        'iedere tenant heeft een eigen private root'
    );

    $brandA = $cfgA['branding'] ?? [];
    $brandB = $cfgB['branding'] ?? [];
    $assetKeys = ['logo', 'social_image', 'favicon', 'favicon_16', 'favicon_32', 'favicon_48', 'apple_touch_icon', 'manifest'];
    $geenLegacyAssets = true;
    foreach ($assetKeys as $key) {
        if (($brandA[$key] ?? null) !== '' || ($brandB[$key] ?? null) !== '') $geenLegacyAssets = false;
    }
    check33($geenLegacyAssets, 'nieuwe tenants erven geen RC045 branding-assets');
    check33(
        !str_contains(strtolower(json_encode($cfgA, JSON_UNESCAPED_SLASHES) ?: ''), 'rc045')
        && !str_contains(strtolower(json_encode($cfgB, JSON_UNESCAPED_SLASHES) ?: ''), 'rc045'),
        'gegenereerde tenantconfig bevat geen RC045-identiteit'
    );

    check33(
        ($cfgA['modules']['sponsors'] ?? false) === true
        && ($cfgA['modules']['aanmelden'] ?? true) === false
        && ($cfgB['modules']['sponsors'] ?? true) === false
        && ($cfgB['modules']['aanmelden'] ?? false) === true,
        'modulekeuze wordt per tenant expliciet true/false vastgelegd'
    );
    check33(
        count($cfgA['modules'] ?? []) === 11 && count($cfgB['modules'] ?? []) === 11,
        'moduleprofiel bevat alle bekende platformmodules en erft geen ontbrekende defaults'
    );

    $manifestA = json_decode((string)file_get_contents($tenantA . '/tenant.json'), true);
    $manifestB = json_decode((string)file_get_contents($tenantB . '/tenant.json'), true);
    check33(
        is_array($manifestA) && is_array($manifestB)
        && in_array('sponsors', $manifestA['modules'] ?? [], true)
        && !in_array('sponsors', $manifestB['modules'] ?? [], true),
        'tenantmanifest registreert de actieve modulekeuze'
    );

    [$badModuleCode, $badModuleOut] = provision33(
        $script,
        $base,
        'foutmodule',
        'Foutmodule',
        'https://foutmodule.example',
        'website,onbekend'
    );
    check33($badModuleCode !== 0 && str_contains($badModuleOut, 'Onbekende module'), 'onbekende module faalt gesloten');
    check33(!is_dir($base . '/foutmodule'), 'ongeldige modulekeuze schrijft geen tenantmap');

    [$noWebsiteCode, $noWebsiteOut] = provision33(
        $script,
        $base,
        'zonderweb',
        'Zonder Web',
        'https://zonderweb.example',
        'ledenadministratie'
    );
    check33($noWebsiteCode !== 0 && str_contains($noWebsiteOut, "kernmodule 'website'"), 'tenantprofiel zonder kernmodule website wordt geweigerd');
    check33(!is_dir($base . '/zonderweb'), 'geweigerd kernmoduleprofiel schrijft geen tenantmap');

    $worker = $tmp . '/worker.php';
    $workerCode = <<<'PHP'
<?php
$root = $argv[1];
$actie = $argv[2] ?? '';
function out33($data): void { echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }
switch ($actie) {
    case 'info':
        require $root . '/app/core/site.php';
        out33([
            'name' => siteNaam(),
            'url' => siteUrl(),
            'logo' => siteAsset('branding.logo'),
            'sponsors' => siteModuleActief('sponsors'),
            'aanmelden' => siteModuleActief('aanmelden'),
            'private' => tenantRuntimePrivateRoot(siteConfig()),
        ]);
        break;
    case 'seo':
        require $root . '/app/content/seo-head.php';
        ob_start();
        rc045SeoHead('index');
        out33(['html' => ob_get_clean()]);
        break;
    case 'public-write':
        require $root . '/app/content/public-content-store.php';
        out33(['ok' => publicContentSchrijfTenant('contact', ['tenant' => $argv[3]], false)]);
        break;
    case 'public-read':
        require $root . '/app/content/public-content-store.php';
        out33(['data' => publicContentLees('contact')]);
        break;
    case 'private-write':
        require $root . '/app/storage/private-store.php';
        out33(['ok' => privateStoreSchrijf('leden', [['tenant' => $argv[3]]], static fn($d) => false)]);
        break;
    case 'private-read':
        require $root . '/app/storage/private-store.php';
        out33(['data' => privateStoreLees('leden', static fn() => [])]);
        break;
    case 'asset-root':
        require $root . '/app/content/public-asset-store.php';
        out33(['root' => publicAssetNamespaceRoot('sponsors')]);
        break;
    default:
        out33(['error' => 'actie']);
        exit(2);
}
PHP;
    file_put_contents($worker, $workerCode);

    [$ia, $infoA] = worker33($worker, $configA, [$root, 'info']);
    [$ib, $infoB] = worker33($worker, $configB, [$root, 'info']);
    check33(
        $ia === 0 && $ib === 0
        && ($infoA['name'] ?? '') === 'Roeivereniging Noorderhaven'
        && ($infoB['name'] ?? '') === 'Wandelclub Duinrand'
        && ($infoA['logo'] ?? 'x') === '' && ($infoB['logo'] ?? 'x') === '',
        'dezelfde gedeelde runtime presenteert per proces de juiste tenantidentiteit zonder RC045-logo'
    );
    check33(
        ($infoA['sponsors'] ?? false) === true && ($infoA['aanmelden'] ?? true) === false
        && ($infoB['sponsors'] ?? true) === false && ($infoB['aanmelden'] ?? false) === true,
        'dezelfde gedeelde runtime respecteert verschillende moduleprofielen'
    );

    [$sa, $seoA] = worker33($worker, $configA, [$root, 'seo']);
    [$sb, $seoB] = worker33($worker, $configB, [$root, 'seo']);
    $htmlA = (string)($seoA['html'] ?? '');
    $htmlB = (string)($seoB['html'] ?? '');
    check33(
        $sa === 0 && $sb === 0
        && str_contains($htmlA, 'Roeivereniging Noorderhaven')
        && str_contains($htmlB, 'Wandelclub Duinrand'),
        'SEO gebruikt per externe tenant de eigen verenigingsnaam'
    );
    check33(
        !str_contains(strtolower($htmlA), 'rc045') && !str_contains(strtolower($htmlB), 'rc045')
        && !str_contains(strtolower($htmlA), 'eygelshoven') && !str_contains(strtolower($htmlB), 'eygelshoven'),
        'externe tenant-SEO lekt geen RC045- of Eygelshoven-content'
    );

    [$pwa] = worker33($worker, $configA, [$root, 'public-write', 'A']);
    [$pwb] = worker33($worker, $configB, [$root, 'public-write', 'B']);
    [$pra, $publicA] = worker33($worker, $configA, [$root, 'public-read']);
    [$prb, $publicB] = worker33($worker, $configB, [$root, 'public-read']);
    check33(
        $pwa === 0 && $pwb === 0 && $pra === 0 && $prb === 0
        && ($publicA['data']['tenant'] ?? '') === 'A'
        && ($publicB['data']['tenant'] ?? '') === 'B',
        'twee fictieve verenigingen schrijven en lezen dezelfde publieke dataset volledig gescheiden'
    );

    [$vwa] = worker33($worker, $configA, [$root, 'private-write', 'A']);
    [$vwb] = worker33($worker, $configB, [$root, 'private-write', 'B']);
    [$vra, $privateA] = worker33($worker, $configA, [$root, 'private-read']);
    [$vrb, $privateB] = worker33($worker, $configB, [$root, 'private-read']);
    check33(
        $vwa === 0 && $vwb === 0 && $vra === 0 && $vrb === 0
        && ($privateA['data'][0]['tenant'] ?? '') === 'A'
        && ($privateB['data'][0]['tenant'] ?? '') === 'B',
        'twee fictieve verenigingen gebruiken dezelfde private collectie zonder datalek'
    );

    [$ara, $assetA] = worker33($worker, $configA, [$root, 'asset-root']);
    [$arb, $assetB] = worker33($worker, $configB, [$root, 'asset-root']);
    check33(
        $ara === 0 && $arb === 0
        && is_string($assetA['root'] ?? null) && is_string($assetB['root'] ?? null)
        && $assetA['root'] !== $assetB['root']
        && str_starts_with($assetA['root'], $tenantA . '/private/')
        && str_starts_with($assetB['root'], $tenantB . '/private/'),
        'publieke uploadnamespaces resolveren naar verschillende tenantroots'
    );
} finally {
    rr33($tmp);
}

echo "Phase 3.3 second tenant proof: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
