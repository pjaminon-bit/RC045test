<?php
$root = dirname(__DIR__);
require_once $root . '/app/storage/domein-repositories.php';

$errors = [];
$ok = [];
function s153(bool $cond, string $msg): void { global $errors, $ok; if ($cond) $ok[] = $msg; else $errors[] = $msg; }
function s153eq($actual, $expected, string $msg): void { s153($actual === $expected, $msg . ' (' . var_export($actual, true) . ' === ' . var_export($expected, true) . ')'); }

s153eq(otaakVolgendeUitvoering('dagelijks', '2026-01-31'), '2026-02-01', 'dagelijks blijft +1 kalenderdatum');
s153eq(otaakVolgendeUitvoering('wekelijks', '2026-03-25'), '2026-04-01', 'wekelijks blijft +7 kalenderdatums');
s153eq(otaakVolgendeUitvoering('maandelijks', '2026-01-15', 15), '2026-02-15', 'gewone maanddatum blijft op dezelfde dag');
s153eq(otaakVolgendeUitvoering('maandelijks', '2024-01-31', 31), '2024-02-29', '31 januari clipt naar schrikkel-februari');
s153eq(otaakVolgendeUitvoering('maandelijks', '2023-01-31', 31), '2023-02-28', '31 januari clipt naar gewone februari');
s153eq(otaakVolgendeUitvoering('maandelijks', '2024-01-30', 30), '2024-02-29', '30 januari clipt naar laatste geldige februaridag');
s153eq(otaakVolgendeUitvoering('maandelijks', '2024-02-29', 30), '2024-03-30', '30-daganker herstelt na korte maand');
s153eq(otaakVolgendeUitvoering('per_kwartaal', '2024-01-31', 31), '2024-04-30', 'kwartaal gebruikt +3 kalendermaanden');
s153eq(otaakVolgendeUitvoering('per_kwartaal', '2024-04-30', 31), '2024-07-31', 'kwartaal herstelt 31-daganker na korte maand');
s153eq(otaakVolgendeUitvoering('halfjaarlijks', '2023-08-31', 31), '2024-02-29', 'halfjaar gebruikt +6 kalendermaanden over schrikkeljaar');
s153eq(otaakVolgendeUitvoering('halfjaarlijks', '2024-02-29', 31), '2024-08-31', 'halfjaar herstelt maandultimo-anker');
s153eq(otaakVolgendeUitvoering('jaarlijks', '2024-02-29', 29), '2025-02-28', 'jaarlijks vanaf 29 februari clipt in niet-schrikkeljaar');
s153eq(otaakVolgendeUitvoering('jaarlijks', '2027-02-28', 29), '2028-02-29', 'jaarlijks 29-februarianker keert terug in volgend schrikkeljaar');
s153eq(otaakVolgendeUitvoering('maandelijks', '2025-12-31', 31), '2026-01-31', 'kalendercontract werkt over december-januari');
s153eq(otaakVolgendeUitvoering('naar_behoefte', '2026-01-31'), '', 'naar behoefte blijft zonder volgende datum');

$maand = ['frequentie' => 'maandelijks', 'geschiedenis' => [], 'laatst_uitgevoerd' => '', 'volgende_uitvoering' => ''];
$maand = otaakMarkeerUitgevoerd($maand, 'tester', '2024-01-31');
s153eq($maand['kalender_anker_dag'] ?? null, 31, 'eerste kalenderuitvoering legt expliciet daganker vast');
s153eq($maand['volgende_uitvoering'] ?? null, '2024-02-29', 'eerste maandultimo-uitvoering plant februari correct');
$maand = otaakMarkeerUitgevoerd($maand, 'tester', '2024-02-29');
s153eq($maand['volgende_uitvoering'] ?? null, '2024-03-31', 'opeenvolgende maandultimo-uitvoering drijft niet naar 29e');
$maand = otaakMarkeerUitgevoerd($maand, 'tester', '2024-03-31');
s153eq($maand['volgende_uitvoering'] ?? null, '2024-04-30', 'opeenvolgende maandultimo-uitvoering clipt opnieuw alleen de korte doelmaand');
$maand = otaakMarkeerUitgevoerd($maand, 'tester', '2024-04-30');
s153eq($maand['volgende_uitvoering'] ?? null, '2024-05-31', 'maandultimo herstelt na april zonder cumulatieve drift');

$legacy = [
    'frequentie' => 'maandelijks',
    'geschiedenis' => [['datum' => '2024-01-31', 'door' => 'legacy']],
    'laatst_uitgevoerd' => '2024-01-31',
    'volgende_uitvoering' => '2024-03-01',
];
$legacyVoor = $legacy['geschiedenis'];
$legacy = otaakMarkeerUitgevoerd($legacy, 'tester', '2024-02-29');
s153eq($legacy['kalender_anker_dag'] ?? null, 31, 'bestaand record zonder ankermetadata gebruikt vorige uitvoering als gecontroleerd anker');
s153eq($legacy['volgende_uitvoering'] ?? null, '2024-03-31', 'bestaand record voorkomt na eerste nieuwe uitvoering verdere maanddrift');
s153(($legacy['geschiedenis'][1] ?? null) === $legacyVoor[0], 'historische uitvoering wordt niet achteraf herschreven');

$jaar = ['frequentie' => 'jaarlijks', 'geschiedenis' => [], 'laatst_uitgevoerd' => '2024-02-29', 'volgende_uitvoering' => '2025-03-01'];
$jaar = otaakMarkeerUitgevoerd($jaar, 'tester', '2025-02-28');
s153eq($jaar['kalender_anker_dag'] ?? null, 29, 'bestaand jaarlijks record bewaart 29-februarianker uit vorige uitvoering');
$jaar = otaakMarkeerUitgevoerd($jaar, 'tester', '2026-02-28');
$jaar = otaakMarkeerUitgevoerd($jaar, 'tester', '2027-02-28');
s153eq($jaar['volgende_uitvoering'] ?? null, '2028-02-29', 'opeenvolgende jaarcycli herstellen schrikkeldag zonder cumulatieve drift');

$timezoneVoor = date_default_timezone_get();
date_default_timezone_set('Europe/Amsterdam');
$amsterdam = otaakVolgendeUitvoering('wekelijks', '2026-03-25');
date_default_timezone_set('America/New_York');
$newYork = otaakVolgendeUitvoering('wekelijks', '2026-03-25');
date_default_timezone_set($timezoneVoor);
s153eq($amsterdam, '2026-04-01', 'datumcontract blijft correct over Europese DST-overgang');
s153eq($newYork, $amsterdam, 'date-only berekening is onafhankelijk van server-timezone');

$ongeldigGegooid = false;
try { otaakVolgendeUitvoering('onbekend', '2026-01-01'); } catch (InvalidArgumentException $e) { $ongeldigGegooid = true; }
s153($ongeldigGegooid, 'onbekende frequentie faalt gesloten bij planning');
$ongeldigeTaak = ['frequentie' => 'onbekend', 'geschiedenis' => [], 'laatst_uitgevoerd' => '', 'volgende_uitvoering' => ''];
$voorOngeldig = $ongeldigeTaak;
$markeringGegooid = false;
try { otaakMarkeerUitgevoerd($ongeldigeTaak, 'tester', '2026-01-01'); } catch (InvalidArgumentException $e) { $markeringGegooid = true; }
s153($markeringGegooid && $ongeldigeTaak === $voorOngeldig, 'ongeldige frequentie wordt niet als uitvoering geregistreerd');
$normaliseerGegooid = false;
try { otaakNormaliseer(['frequentie' => 'onbekend']); } catch (InvalidArgumentException $e) { $normaliseerGegooid = true; }
s153($normaliseerGegooid, 'expliciet ongeldige frequentie wordt niet stil naar maandelijks omgezet');
$nieuwCompat = otaakNormaliseer([]);
s153eq($nieuwCompat['frequentie'] ?? null, 'maandelijks', 'bestaande default voor nieuw intern record zonder frequentie blijft compatibel');

$bestaand = [
    'id' => 'otaak_bestaand', 'nummer' => 7, 'omschrijving' => 'Bestaand', 'toelichting' => 'Historisch',
    'frequentie' => 'maandelijks', 'zichtbaarheid' => 'leden', 'toegewezen_aan' => 'lid_x', 'actief' => true,
    'laatst_uitgevoerd' => '2024-01-31', 'laatst_uitgevoerd_door' => 'tester', 'volgende_uitvoering' => '2024-02-29',
    'kalender_anker_dag' => 31, 'geschiedenis' => [['datum' => '2024-01-31', 'door' => 'tester']],
    'aangemaakt' => '2024-01-01T00:00:00+00:00', 'aangemaakt_door' => 'tester', 'gewijzigd' => '2024-01-31T00:00:00+00:00',
];
$bewerkt = otaakNormaliseer(['omschrijving' => 'Nieuwe naam', 'frequentie' => 'maandelijks', 'actief' => true], $bestaand);
s153eq($bewerkt['volgende_uitvoering'] ?? null, '2024-02-29', 'gewone edit herschrijft bestaande volgende uitvoerdatum niet');
s153eq($bewerkt['geschiedenis'] ?? null, $bestaand['geschiedenis'], 'gewone edit herschrijft historie niet');
s153eq($bewerkt['kalender_anker_dag'] ?? null, 31, 'gewone edit behoudt bestaand kalenderanker');
$frequentieGewijzigd = otaakNormaliseer(['frequentie' => 'per_kwartaal', 'actief' => true], $bestaand);
s153(!array_key_exists('kalender_anker_dag', $frequentieGewijzigd), 'expliciete frequentiewijziging reset uitsluitend nieuwe ankermetadata voor de volgende uitvoering');
s153eq($frequentieGewijzigd['volgende_uitvoering'] ?? null, '2024-02-29', 'frequentiewijziging herschrijft bestaande volgende uitvoerdatum niet stil');

$tmp = sys_get_temp_dir() . '/rc045test-153-json-' . bin2hex(random_bytes(4)) . '.php';
try {
    $payload = ['volgnummer' => 1, 'taken' => [[
        'id' => 'otaak_json', 'nummer' => 1, 'frequentie' => 'maandelijks',
        'kalender_anker_dag' => 31, 'laatst_uitgevoerd' => '2024-02-29', 'volgende_uitvoering' => '2024-03-31',
    ]]];
    s153(repoPhpJsonSchrijf($tmp, OTAKEN_VOORLOOP, $payload, null, false), 'JSON fallback schrijft operationele-taakdocument atomisch');
    $raw = (string) file_get_contents($tmp);
    $json = substr($raw, strlen(OTAKEN_VOORLOOP));
    $roundtrip = json_decode($json, true);
    s153(is_array($roundtrip) && ($roundtrip['taken'][0]['kalender_anker_dag'] ?? null) === 31 && ($roundtrip['taken'][0]['volgende_uitvoering'] ?? '') === '2024-03-31', 'JSON roundtrip behoudt kalenderanker en volgende datum');
} finally {
    @unlink($tmp);
}

$bron = (string) file_get_contents($root . '/operationele-taken-opslag.php');
s153(strpos($bron, 'otaakFrequentieDagen') === false, 'source-contract verwijdert generieke vaste-dagenfrequentiemapping');
foreach (["'maandelijks'   => 30", "'per_kwartaal'  => 91", "'halfjaarlijks' => 182", "'jaarlijks'     => 365"] as $verboden) {
    s153(strpos($bron, $verboden) === false, 'source-contract verbiedt oude kalenderdagensemantiek: ' . $verboden);
}
s153(strpos($bron, "'maandelijks'   => 1") !== false && strpos($bron, "'per_kwartaal'  => 3") !== false && strpos($bron, "'halfjaarlijks' => 6") !== false && strpos($bron, "'jaarlijks'     => 12") !== false, 'source-contract legt 1/3/6/12 kalendermaanden vast');
s153(strpos($bron, 'kalender_anker_dag') !== false && strpos($bron, 'checkdate(') !== false, 'source-contract bewaart expliciet kalenderanker en clipt naar geldige doelmaand');

$beheer = (string) file_get_contents($root . '/beheer/operationele-taken.php');
s153(strpos($beheer, "$_POST['frequentie']??''") !== false, 'beheer laat ontbrekende/ongeldige frequentie niet stil naar maandelijks vallen');
s153(strpos($beheer, 'catch(InvalidArgumentException') !== false || strpos($beheer, 'catch (InvalidArgumentException') !== false, 'beheer vangt fail-closed planningsvalidatie gecontroleerd af');

$portaal = (string) file_get_contents($root . '/leden/index.php');
s153(strpos($portaal, 'otaakVolgendeUitvoering(') === false, 'ledenweergave interpreteert frequenties niet zelfstandig');

echo 'Security #153 calendar frequency checks: ' . count($ok) . ' OK, ' . count($errors) . " fout(en)\n";
if ($errors) {
    foreach ($errors as $e) fwrite(STDERR, "FOUT: $e\n");
    exit(1);
}
foreach ($ok as $m) echo "OK: $m\n";
