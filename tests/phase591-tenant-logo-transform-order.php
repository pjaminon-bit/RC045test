<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function c591(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) {
        $ok++;
        echo "OK: {$label}\n";
        return;
    }
    $fout++;
    fwrite(STDERR, "FOUT: {$label}\n");
}

require_once $root . '/app/core/tenant-public-runtime.php';

$config = [
    'vereniging' => [
        'naam' => 'Testvereniging',
        'volledige_naam' => 'Testvereniging',
        'sleutel' => 'test',
        'site_url' => 'https://test.vps.holox.nl',
        'slogan' => '',
    ],
    'branding' => [
        'logo' => '',
        'favicon' => '',
        'kleuren' => [],
    ],
    'betaling' => [],
    'opslag' => [
        'private_root' => '/pad/dat/niet/bestaat',
    ],
];

$html = '<!doctype html><html><head></head><body>'
    . '<img class="nav-logo" src="rc045-logo.png" alt="RC045 logo">'
    . '<img class="footer-logo" src="rc045-logo.png" alt="RC045">'
    . '<p>Welkom bij RC045.</p>'
    . '</body></html>';

$uitvoer = tenantPublicRuntimeTransform($html, $config);

c591(
    substr_count($uitvoer, 'https://test.vps.holox.nl/images/template-placeholder.svg') >= 2,
    'legacy RC045-logo wordt vóór merkvervanging naar lokale dummy-placeholder omgezet'
);
c591(
    !str_contains($uitvoer, 'Testvereniging-logo.png'),
    'tenantnaam wordt nooit gebruikt om een niet-bestaand logobestand te construeren'
);
c591(
    !str_contains(strtolower($uitvoer), 'rc045-logo'),
    'historische logo-assetnaam lekt niet naar externe tenantuitvoer'
);
c591(
    str_contains($uitvoer, 'Welkom bij Testvereniging.'),
    'gewone merknaamtekst wordt na media-neutralisatie nog steeds tenant-specifiek gemaakt'
);
c591(
    str_contains($uitvoer, 'alt="Testvereniging logo"') && str_contains($uitvoer, 'alt="Testvereniging"'),
    'alt-teksten worden na assetneutralisatie wel naar de tenantnaam omgezet'
);

echo "Phase 5.9.1 tenant logo transform order: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
