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

function volgorde54(string $html, array $naalden): bool
{
    $vorige = -1;
    foreach ($naalden as $naald) {
        $positie = strpos($html, $naald);
        if ($positie === false || $positie <= $vorige) return false;
        $vorige = $positie;
    }
    return true;
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
    [$provisionCode] = run54($provision);
    check54($provisionCode === 0, 'neutrale testtenant wordt geprovisioned');

    $tenant = $base . '/noorderhaven';
    $config = $tenant . '/config.php';
    $homepagePad = $tenant . '/private/public-content/homepage.json';
    $contactPad = $tenant . '/private/public-content/contact.json';
    check54(is_file($homepagePad) && is_file($contactPad), 'provisioner schrijft publieke tenantdata');

    [$renderCode, $html] = render54($root, $config);
    $laag = strtolower($html);
    check54($renderCode === 0 && str_contains($html, 'Roeivereniging Noorderhaven'), 'server-side output gebruikt de tenantnaam');
    check54(str_contains($html, 'data-template="tenant-shared-v1"'), 'externe tenant gebruikt de gedeelde template');
    check54(str_contains($html, 'href="styles.css"') && str_contains($html, 'src="site-i18n.js"') && str_contains($html, 'src="homepage.js"'), 'tenant gebruikt dezelfde CSS en JavaScript als RC045');
    check54(!str_contains($html, 'tenant-homepage.css') && !is_file($root . '/tenant-homepage.css'), 'afwijkende tenantstylesheet bestaat niet');

    check54(volgorde54($html, [
        'id="nav-about"', 'id="nav-membership"', 'id="nav-track"', 'id="nav-location"',
        'id="nav-photobook"', 'id="nav-contact"', 'id="nav-join"',
    ]), 'menu behoudt exact de RC045-items en volgorde');
    check54(str_contains($html, '>De baan</a>') && str_contains($html, '>Locatie</a>') && str_contains($html, '>Fotoboek</a>'), 'kenmerkende template-menu-items blijven zichtbaar');
    check54(volgorde54($html, [
        'id="main-content"', 'id="nieuws"', 'id="over-ons"', 'id="lidmaatschap"', 'id="baan"',
        'class="photo-strip reveal"', 'id="activiteiten"', 'class="section rules"', 'id="locatie"', 'id="contact"',
    ]), 'homepage behoudt alle tien RC045-secties in dezelfde volgorde');

    check54(!str_contains($laag, 'rc045') && !str_contains($laag, 'bashers of the south') && !str_contains($laag, 'eygelshoven'), 'gerenderde homepage lekt geen RC045-identiteit');
    check54(!str_contains($laag, 'images/crawler') && !str_contains($laag, 'images/basher') && !str_contains($laag, 'rc045-logo'), 'gerenderde homepage vraagt geen RC045-media op');
    check54(str_contains($html, 'images/template-placeholder.svg'), 'ontbrekende tenantmedia gebruikt een neutrale placeholder');

    $bron = (string) file_get_contents($root . '/index.php');
    check54(str_contains($bron, '@media (max-width: 900px)') && str_contains($bron, '@media (max-width: 700px)'), 'bestaande responsive templatebreakpoints blijven actief');
    check54(str_contains($bron, '.rules-grid { grid-template-columns: 1fr; }') && str_contains($bron, '.track-layout { grid-template-columns: 1fr; }'), 'brede grids stapelen op kleine schermen');

    $veilig = ['about_title' => ['nl' => 'Onze eigen roeivereniging']];
    file_put_contents($homepagePad, json_encode($veilig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    file_put_contents($contactPad, "{}\n");
    $migreer = 'VERENIGING_REQUIRE_TENANT_CONFIG=1 ' . escapeshellcmd(PHP_BINARY) . ' '
        . escapeshellarg($root . '/bin/migrate-tenant-public-template.php')
        . ' --config=' . escapeshellarg($config) . ' --apply';
    [$migrateCode, $migrateOut] = run54($migreer);
    $na = json_decode((string) file_get_contents($homepagePad), true);
    check54($migrateCode === 0 && str_contains($migrateOut, 'TEMPLATE MIGRATION OK'), 'bestaande tenant kan gecontroleerd worden aangevuld');
    check54(($na['about_title']['nl'] ?? '') === 'Onze eigen roeivereniging', 'migratie behoudt tenant-eigen inhoud');
    check54(($na['nav_track']['nl'] ?? '') === 'De baan' && isset($na['rule7_text']), 'migratie vult alle ontbrekende templatevelden aan');

    $legacy = $na;
    $legacy['about_title']['nl'] = 'Welkom bij RC045 in Eygelshoven';
    file_put_contents($homepagePad, json_encode($legacy, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    file_put_contents($contactPad, json_encode(['email'=>'bestuur@rc045.nl'], JSON_PRETTY_PRINT) . "\n");
    [$legacyCode, $legacyHtml] = render54($root, $config);
    $legacyLaag = strtolower($legacyHtml);
    check54($legacyCode === 0 && !str_contains($legacyLaag, 'rc045') && !str_contains($legacyLaag, 'eygelshoven'), 'legacy tenantdata wordt fail-closed genegeerd');
} finally {
    rr54($tmp);
}

echo "Phase 5.4 shared RC045 template: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
