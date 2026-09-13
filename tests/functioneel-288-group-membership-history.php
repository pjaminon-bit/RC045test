<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function f288(bool $conditie, string $label): void
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

function f288Relatie(array $leden, string $lidId): ?array
{
    foreach ($leden as $relatie) {
        if (is_array($relatie) && ($relatie['lid_id'] ?? '') === $lidId) {
            return $relatie;
        }
    }
    return null;
}

require_once $root . '/app/leden/groepen.php';

$leden = [
    ['id' => 'actief_houden', 'gearchiveerd_op' => ''],
    ['id' => 'actief_verwijderen', 'gearchiveerd_op' => ''],
    ['id' => 'actief_nieuw', 'gearchiveerd_op' => ''],
    ['id' => 'archief_bestaand', 'gearchiveerd_op' => '2026-09-13T10:00:00+02:00'],
    ['id' => 'archief_afgesloten', 'gearchiveerd_op' => '2026-09-13T10:00:00+02:00'],
    ['id' => 'archief_nieuw', 'gearchiveerd_op' => '2026-09-13T10:00:00+02:00'],
];

$bestaand = [
    ['lid_id' => 'actief_houden', 'rollen' => ['trekker'], 'sinds' => '2026-01-01', 'tot' => ''],
    ['lid_id' => 'actief_verwijderen', 'rollen' => ['lid'], 'sinds' => '2026-01-02', 'tot' => ''],
    ['lid_id' => 'archief_bestaand', 'rollen' => ['trekker', 'bestuurslid'], 'sinds' => '2026-01-03', 'tot' => ''],
    ['lid_id' => 'archief_afgesloten', 'rollen' => ['lid'], 'sinds' => '2026-01-04', 'tot' => '2026-08-01'],
    ['lid_id' => 'verdwenen_lid', 'rollen' => ['lid'], 'sinds' => '2026-01-05', 'tot' => ''],
];

$gevraagd = [
    'actief_houden',
    'actief_nieuw',
    'archief_bestaand',
    'archief_afgesloten',
    'archief_nieuw',
    'onbekend_lid',
    str_repeat('x', 81),
];
$rollen = [
    'actief_houden' => ['lid'],
    'actief_nieuw' => ['trekker'],
    'archief_bestaand' => ['lid'],
    'archief_afgesloten' => ['trekker'],
    'archief_nieuw' => ['trekker'],
];

$resultaat = groepenWerkLedenBijBeheer($bestaand, $gevraagd, $rollen, $leden);

$actiefHouden = f288Relatie($resultaat, 'actief_houden');
f288(is_array($actiefHouden) && ($actiefHouden['tot'] ?? null) === '', 'geselecteerd actief lid blijft open');
f288(($actiefHouden['rollen'] ?? []) === ['lid'], 'rollen van selecteerbaar actief lid mogen expliciet wijzigen');

$actiefVerwijderen = f288Relatie($resultaat, 'actief_verwijderen');
f288(is_array($actiefVerwijderen) && ($actiefVerwijderen['tot'] ?? '') !== '', 'deselecteren van actief lid sluit deelname');

$actiefNieuw = f288Relatie($resultaat, 'actief_nieuw');
f288(is_array($actiefNieuw) && ($actiefNieuw['tot'] ?? null) === '', 'nieuw actief lid kan worden toegevoegd');
f288(($actiefNieuw['rollen'] ?? []) === ['trekker'], 'nieuw actief lid krijgt gevalideerde aangevraagde rol');

$archiefBestaand = f288Relatie($resultaat, 'archief_bestaand');
f288(is_array($archiefBestaand) && ($archiefBestaand['tot'] ?? null) === '', 'bestaande open deelname van gearchiveerd lid blijft open');
f288(($archiefBestaand['sinds'] ?? '') === '2026-01-03', 'historische startdatum van gearchiveerd lid blijft intact');
f288(($archiefBestaand['rollen'] ?? []) === ['trekker', 'bestuurslid'], 'bestaande rollen van gearchiveerd lid blijven intact ondanks gemanipuleerde POST');

$archiefAfgesloten = f288Relatie($resultaat, 'archief_afgesloten');
f288(is_array($archiefAfgesloten) && ($archiefAfgesloten['tot'] ?? '') === '2026-08-01', 'afgesloten deelname van gearchiveerd lid wordt niet heropend');
f288(($archiefAfgesloten['rollen'] ?? []) === ['lid'], 'afgesloten historische rollen worden niet gewijzigd');

f288(f288Relatie($resultaat, 'archief_nieuw') === null, 'gearchiveerd lid kan niet nieuw via POST worden toegevoegd');
f288(f288Relatie($resultaat, 'onbekend_lid') === null, 'onbekend lid kan niet via POST worden toegevoegd');
f288(f288Relatie($resultaat, str_repeat('x', 81)) === null, 'te lang lid-id kan niet via POST worden toegevoegd');

$verdwenen = f288Relatie($resultaat, 'verdwenen_lid');
f288(is_array($verdwenen) && ($verdwenen['tot'] ?? '') !== '', 'dangling open lid-id krijgt geen historische preserve-uitzondering');

$naGroepSluiten = groepenWerkLedenBij($resultaat, [], []);
$openNaSluiten = array_filter($naGroepSluiten, static fn($m) => is_array($m) && ($m['tot'] ?? '') === '');
f288(count($openNaSluiten) === 0, 'expliciet sluiten/archiveren van groep sluit ook bewaarde open deelnames');

$geslotenGroep = [
    'groepen' => [[
        'id' => 'werkgroep_test',
        'type' => 'werkgroep',
        'naam' => 'Testgroep',
        'omschrijving' => '',
        'doel' => '',
        'status' => 'gearchiveerd',
        'startdatum' => '2026-01-01',
        'einddatum' => '2026-09-13',
        'einddatum_voor_status' => '',
        'leden' => [['lid_id' => 'actief_houden', 'rollen' => ['lid'], 'sinds' => '2026-01-01', 'tot' => '2026-09-13']],
        'aangemaakt' => '2026-01-01T10:00:00+01:00',
        'gewijzigd' => '2026-09-13T10:00:00+02:00',
    ]],
];
$herstelInput = $geslotenGroep;
$herstelInput['groepen'][0]['status'] = 'actief';
$hersteld = groepenPeriodeTransities($herstelInput, $geslotenGroep, '2026-09-14');
f288(($hersteld['groepen'][0]['leden'][0]['tot'] ?? '') === '2026-09-13', '#282-contract: groep herstellen heropent afgesloten deelname niet');

$beheerBron = file_get_contents($root . '/app/beheer/groepen-beheer.php');
f288(is_string($beheerBron) && str_contains($beheerBron, 'groepenWerkLedenBijBeheer('), 'generiek groepenbeheer gebruikt centrale historische selectiehelper');
f288(is_string($beheerBron) && !str_contains($beheerBron, '$geldige[(string)$lidId]'), 'oude lokale actieve-id-filter is verwijderd');

echo "Functioneel #288 groepslidmaatschapshistorie: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
