<?php
$root = dirname(__DIR__);
require_once $root . '/app/taken-commissies.php';

$errors = [];
$ok = [];
function s151(bool $cond, string $msg): void { global $errors, $ok; if ($cond) $ok[] = $msg; else $errors[] = $msg; }

$groepenDoc = [
    'groepen' => [
        ['id' => 'commissie_nieuw', 'type' => 'commissie', 'naam' => 'Nieuwe commissie', 'status' => 'actief'],
        ['id' => 'commissie_archief', 'type' => 'commissie', 'naam' => 'Oude commissie', 'status' => 'gearchiveerd'],
        ['id' => 'commissie_afgerond', 'type' => 'commissie', 'naam' => 'Afgeronde commissie', 'status' => 'afgerond'],
        ['id' => 'commissie_kantine', 'type' => 'commissie', 'naam' => 'Hospitality', 'status' => 'actief'],
        ['id' => 'commissie_stabiele_id', 'type' => 'commissie', 'naam' => 'Nieuwe naam na rename', 'status' => 'actief'],
        ['id' => 'werkgroep_test', 'type' => 'werkgroep', 'naam' => 'Werkgroep', 'status' => 'actief'],
    ],
];
$ledenData = [
    'leden' => [],
    'commissies' => [
        'kantine' => 'Kantine',
        'alleen_legacy' => 'Alleen legacy',
    ],
];
$groepenDoc = taakCommissieDocument($groepenDoc);
$keuzes = taakCommissieActieveKeuzes($groepenDoc);

s151(isset($keuzes['commissie_nieuw']), 'nieuwe actieve commissie uit groepenmodel is direct selecteerbaar');
s151(isset($keuzes['commissie_kantine']), 'persistente actieve commissie blijft selecteerbaar bij legacy naamcollision');
s151(!isset($keuzes['commissie_archief']), 'gearchiveerde commissie is niet nieuw selecteerbaar');
s151(!isset($keuzes['commissie_afgerond']), 'afgeronde commissie is niet nieuw selecteerbaar');
s151(!isset($keuzes['werkgroep_test']), 'werkgroep is geen commissie-keuze voor primaire taakrelatie');

$nieuw = taakCommissieValideerVoorOpslag('commissie_nieuw', null, $groepenDoc, $ledenData);
s151($nieuw['geldig'] && $nieuw['id'] === 'commissie_nieuw', 'nieuwe taak slaat stabiele groep-id op');
$ongeldig = taakCommissieValideerVoorOpslag('commissie_bestaat_niet', null, $groepenDoc, $ledenData);
s151(!$ongeldig['geldig'], 'ongeldige commissie-id wordt voor nieuwe relatie geweigerd');
$archiefNieuw = taakCommissieValideerVoorOpslag('commissie_archief', null, $groepenDoc, $ledenData);
s151(!$archiefNieuw['geldig'], 'gearchiveerde commissie kan niet nieuw gekoppeld worden');
$archiefBestaand = taakCommissieValideerVoorOpslag('commissie_archief', ['commissie_id' => 'commissie_archief'], $groepenDoc, $ledenData);
s151($archiefBestaand['geldig'] && $archiefBestaand['id'] === 'commissie_archief', 'bestaande relatie naar gearchiveerde commissie blijft historisch behouden');

$legacyContext = taakCommissieContext($groepenDoc, $ledenData, 'alleen_legacy');
s151($legacyContext['soort'] === 'legacy' && strpos($legacyContext['label'], 'Alleen legacy') !== false, 'legacy relatie blijft begrijpelijk zichtbaar');
$legacyBewaar = taakCommissieValideerVoorOpslag('alleen_legacy', ['commissie_id' => 'alleen_legacy'], $groepenDoc, $ledenData);
s151($legacyBewaar['geldig'] && $legacyBewaar['id'] === 'alleen_legacy', 'bestaande legacy-id blijft ongewijzigd bij gewone edit');
$legacyNieuw = taakCommissieValideerVoorOpslag('alleen_legacy', null, $groepenDoc, $ledenData);
s151(!$legacyNieuw['geldig'], 'legacy commissie kan niet voor een nieuwe taak worden gekoppeld');
$migratie = taakCommissieValideerVoorOpslag('commissie_kantine', ['commissie_id' => 'kantine'], $groepenDoc, $ledenData);
s151($migratie['geldig'] && $migratie['id'] === 'commissie_kantine', 'legacy identiteit migreert gecontroleerd door expliciete keuze van persistente groep-id');
$collisionBewaar = taakCommissieValideerVoorOpslag('kantine', ['commissie_id' => 'kantine'], $groepenDoc, $ledenData);
s151($collisionBewaar['geldig'] && $collisionBewaar['id'] === 'kantine', 'slug/name collision veroorzaakt geen impliciete destructieve legacy-migratie');

$rename = taakCommissieValideerVoorOpslag('commissie_stabiele_id', ['commissie_id' => 'commissie_stabiele_id'], $groepenDoc, $ledenData);
s151($rename['geldig'] && $rename['id'] === 'commissie_stabiele_id', 'rename van commissie verandert stabiele taakrelatie niet');

$langeId = 'commissie_' . str_repeat('a', 68);
$taak = taakNormaliseer(['commissie_id' => $langeId]);
s151($taak['commissie_id'] === $langeId && strlen($taak['commissie_id']) === 78, 'taakopslag bewaart volledige groepen-id tot 80 tekens');

$tmp = sys_get_temp_dir() . '/rc045test-151-json-' . bin2hex(random_bytes(4)) . '.php';
try {
    $payload = ['volgnummer' => 1, 'taken' => [['id' => 'taak_json', 'commissie_id' => $langeId]]];
    s151(repoPhpJsonSchrijf($tmp, TAKEN_VOORLOOP, $payload, null, false), 'JSON fallback schrijft taakdocument atomisch');
    $raw = (string) file_get_contents($tmp);
    $json = substr($raw, strlen(TAKEN_VOORLOOP));
    $roundtrip = json_decode($json, true);
    s151(is_array($roundtrip) && ($roundtrip['taken'][0]['commissie_id'] ?? '') === $langeId, 'JSON roundtrip behoudt stabiele commissie groep-id');
} finally {
    @unlink($tmp);
}

$takenController = (string) file_get_contents($root . '/beheer/taken.php');
s151(strpos($takenController, 'ledenCommissies(') === false, 'beheer/taken.php valt niet meer terug op ledenCommissies()');
s151(strpos($takenController, 'taakCommissieDocument(repoGroepenLees())') !== false, 'Taken bouwt nieuwe keuzes uit persistente groepenrepository');
s151(strpos($takenController, 'groepenLeesDocument()') === false, 'Taken gebruikt geen synthetische legacy groepen voor nieuwe relaties');
s151(strpos($takenController, 'groepenRelatiesWerkBij') === false, '#151 wijzigt generiek delete/many-to-many contract van #152 niet');

$helper = (string) file_get_contents($root . '/app/taken-commissies.php');
s151(strpos($helper, "groepen['relaties']['taken']") !== false && strpos($helper, 'primaire commissie') !== false, 'primaire taakcommissie en generieke groepsrelaties zijn expliciet onderscheiden');

echo 'Security #151 task commission checks: ' . count($ok) . ' OK, ' . count($errors) . " fout(en)\n";
if ($errors) {
    foreach ($errors as $e) fwrite(STDERR, "FOUT: $e\n");
    exit(1);
}
foreach ($ok as $m) echo "OK: $m\n";
