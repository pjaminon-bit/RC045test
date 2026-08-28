<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function check54(bool $cond, string $label): void
{
    global $ok, $fout;
    if ($cond) { $ok++; echo "OK: {$label}\n"; return; }
    $fout++;
    fwrite(STDERR, "FOUT: {$label}\n");
}

function rr54(string $dir): void
{
    if (is_link($dir)) { @unlink($dir); return; }
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $pad = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($pad) && !is_link($pad)) rr54($pad); else @unlink($pad);
    }
    @rmdir($dir);
}

function run54(string $cmd): array
{
    $uit = [];
    exec($cmd . ' 2>&1', $uit, $code);
    return [$code, implode("\n", $uit)];
}

function render54(string $root, string $config): array
{
    $cmd = 'VERENIGING_REQUIRE_TENANT_CONFIG=1 VERENIGING_CONFIG_FILE=' . escapeshellarg($config)
        . ' ' . escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($root . '/index.php');
    return run54($cmd);
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vereniging-phase54-' . bin2hex(random_bytes(4));
$base = $tmp . '/tenants';
@mkdir($base, 0750, true);

try {
    $provision = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($root . '/bin/provision-tenant.php')
        . ' --key=' . escapeshellarg('noorderhaven')
        . ' --name=' . escapeshellarg('Roeivereniging Noorderhaven')
        . ' --url=' . escapeshellarg('https://noorderhaven.example')
        . ' --root=' . escapeshellarg($base)
        . ' --modules=' . escapeshellarg('website,aanmelden');
    [$provisionCode, $provisionOut] = run54($provision);
    check54($provisionCode === 0, 'neutrale testtenant wordt geprovisioned');

    $tenant = $base . '/noorderhaven';
    $config = $tenant . '/config.php';
    $homepagePad = $tenant . '/private/public-content/homepage.json';
    $contactPad = $tenant . '/private/public-content/contact.json';
    check54(is_file($homepagePad) && is_file($contactPad), 'provisioner schrijft neutrale publieke startdata');
    $seed = strtolower((string) @file_get_contents($homepagePad) . (string) @file_get_contents($contactPad));
    check54(!str_contains($seed, 'rc045') && !str_contains($seed, 'eygelshoven'), 'startdata bevat geen legacy verenigingsidentiteit');

    [$renderCode, $html] = render54($root, $config);
    $laag = strtolower($html);
    check54($renderCode === 0 && str_contains($html, 'Roeivereniging Noorderhaven'), 'server-side homepage gebruikt de tenantnaam');
    check54(str_contains($html, 'data-template="tenant-neutral-v1"') && str_contains($html, 'tenant-homepage.css'), 'externe tenant gebruikt de neutrale publieke template');
    check54(!str_contains($laag, 'rc045') && !str_contains($laag, 'bashers of the south') && !str_contains($laag, 'eygelshoven'), 'gerenderde homepage lekt geen RC045-inhoud');
    check54(!str_contains($laag, 'images/crawler') && !str_contains($laag, 'images/basher') && !str_contains($laag, 'rc045-logo'), 'gerenderde homepage vraagt geen gedeelde RC045-afbeeldingen op');
    check54(!str_contains($html, 'homepage.js'), 'externe tenant is voor kerninhoud niet afhankelijk van de legacy JavaScript-homepage');

    $veilig = json_decode((string) file_get_contents($homepagePad), true);
    $veilig['about_title']['nl'] = 'Onze eigen roeivereniging';
    file_put_contents($homepagePad, json_encode($veilig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    [$safeCode, $safeHtml] = render54($root, $config);
    check54($safeCode === 0 && str_contains($safeHtml, 'Onze eigen roeivereniging'), 'veilige tenant-eigen homepagecontent wordt gebruikt');

    $legacy = $veilig;
    $legacy['about_title']['nl'] = 'Welkom bij RC045 in Eygelshoven';
    file_put_contents($homepagePad, json_encode($legacy, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    file_put_contents($contactPad, json_encode(['email'=>'bestuur@rc045.nl','adres_postcode_plaats'=>'Eygelshoven'], JSON_PRETTY_PRINT) . "\n");
    [$legacyCode, $legacyHtml] = render54($root, $config);
    $legacyLaag = strtolower($legacyHtml);
    check54($legacyCode === 0 && !str_contains($legacyLaag, 'rc045') && !str_contains($legacyLaag, 'eygelshoven'), 'legacy tenantdata wordt fail-closed genegeerd');

    $migreer = 'VERENIGING_REQUIRE_TENANT_CONFIG=1 ' . escapeshellcmd(PHP_BINARY) . ' '
        . escapeshellarg($root . '/bin/migrate-tenant-public-template.php')
        . ' --config=' . escapeshellarg($config) . ' --apply';
    [$migrateCode, $migrateOut] = run54($migreer);
    $naMigratie = strtolower((string) file_get_contents($homepagePad) . (string) file_get_contents($contactPad));
    check54($migrateCode === 0 && str_contains($migrateOut, 'TEMPLATE MIGRATION OK'), 'bestaande tenant kan gecontroleerd worden gemigreerd');
    check54(!str_contains($naMigratie, 'rc045') && !str_contains($naMigratie, 'eygelshoven'), 'migratie verwijdert legacy publieke identiteit uit beide datasets');

    $css = (string) file_get_contents($root . '/tenant-homepage.css');
    check54(str_contains($css, 'grid-template-columns: minmax(0, 1fr)') && str_contains($css, '@media (max-width: 760px)'), 'template legt éénkoloms contentflow en mobiel breakpoint vast');
    check54(!str_contains($css, '100vw') && str_contains($css, 'overflow-x: clip'), 'responsive CSS veroorzaakt geen viewportbrede documentoverflow');
} finally {
    rr54($tmp);
}

echo "Phase 5.4 tenant public template: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
