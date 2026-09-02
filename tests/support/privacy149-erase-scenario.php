<?php
// Gedeeld regressiescenario voor #149. Wordt alleen vanuit de twee CLI-tests
// in tests/ geladen; productiecode krijgt bewust geen fault-injectionhook.

require_once dirname(__DIR__, 2) . '/app/leden/service.php';
require_once dirname(__DIR__, 2) . '/app/leden/groepen.php';
require_once dirname(__DIR__, 2) . '/app/leden/labels.php';
require_once dirname(__DIR__, 2) . '/app/leden/contributies.php';
require_once dirname(__DIR__, 2) . '/aanmeldingen-opslag.php';

const P149_LID = 'privacy149_lid';
const P149_KEEP = 'privacy149_keep';
const P149_APP = 'privacy149_app';
const P149_APP_BY_LID = 'privacy149_app_by_lid';

function p149Assert(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}

function p149BevatExact($waarde, string $naald): bool
{
    if (is_string($waarde)) return hash_equals($waarde, $naald);
    if (!is_array($waarde)) return false;
    foreach ($waarde as $sleutel => $item) {
        if (is_string($sleutel) && hash_equals($sleutel, $naald)) return true;
        if (p149BevatExact($item, $naald)) return true;
    }
    return false;
}

function p149SeedDocumenten(): array
{
    return [
        'leden' => [
            'leden' => [
                [
                    'id' => P149_LID,
                    'naam' => 'Privacy wisdoel',
                    'status' => 'opgezegd',
                    'gearchiveerd_op' => '2026-08-01T12:00:00+02:00',
                    'aanmelding_id' => P149_APP,
                    'commissies' => ['testcommissie'],
                ],
                [
                    'id' => P149_KEEP,
                    'naam' => 'Bewaarlid',
                    'status' => 'actief',
                    'gearchiveerd_op' => '',
                    'commissies' => ['testcommissie'],
                ],
            ],
            'commissies' => [
                'testcommissie' => [
                    'naam' => 'Testcommissie',
                    'bestuurslid_id' => P149_LID,
                    'hoofd_lid_id' => P149_LID,
                    'leden' => [P149_LID, P149_KEEP],
                ],
            ],
        ],
        'vergaderingen' => [
            'vergaderingen' => [[
                'id' => 'vergadering_149',
                'aanwezigheid' => [P149_LID => 'aanwezig', P149_KEEP => 'afwezig'],
                'deelnemers' => [P149_LID, P149_KEEP],
            ]],
        ],
        'taken' => [
            'taken' => [[
                'id' => 'taak_149',
                'toegewezen_aan' => P149_LID,
                'betrokkenen' => [P149_LID, P149_KEEP],
            ]],
        ],
        'operationele_taken' => [
            'taken' => [[
                'id' => 'otaak_149',
                'eigenaar_lid_id' => P149_LID,
                'leden' => [P149_LID, P149_KEEP],
            ]],
        ],
        'evenementen' => [
            'volgnummer' => 1,
            'evenementen' => [[
                'id' => 'evenement_149',
                'deelnemers' => [P149_LID, P149_KEEP],
                'contact_lid_id' => P149_LID,
            ]],
        ],
        'groepen' => [
            'schema' => 2,
            'rollen' => [['id' => 'lid', 'naam' => 'Lid', 'actief' => true]],
            'groepen' => [[
                'id' => 'commissie_privacy149',
                'type' => 'commissie',
                'naam' => 'Privacy 149',
                'status' => 'actief',
                'leden' => [
                    ['lid_id' => P149_LID, 'rollen' => ['lid'], 'sinds' => '', 'tot' => ''],
                    ['lid_id' => P149_KEEP, 'rollen' => ['lid'], 'sinds' => '', 'tot' => ''],
                ],
            ]],
            'relaties' => [],
        ],
        'ledenlabels' => [
            'schema' => 1,
            'labels' => [['id' => 'testlabel', 'naam' => 'Testlabel', 'actief' => true]],
            'toewijzingen' => [P149_LID => ['testlabel'], P149_KEEP => ['testlabel']],
        ],
        'contributies' => [
            'regels' => [
                ['lid_id' => P149_LID, 'jaar' => 2026, 'status' => 'open', 'verschuldigd_bedrag' => 100, 'inschrijfgeld' => 0, 'betaald_bedrag' => 0],
                ['lid_id' => P149_KEEP, 'jaar' => 2026, 'status' => 'open', 'verschuldigd_bedrag' => 100, 'inschrijfgeld' => 0, 'betaald_bedrag' => 0],
            ],
        ],
        'aanmeldingen' => [
            'aanmeldingen' => [
                ['id' => P149_APP, 'lid_id' => '', 'status' => 'geaccepteerd'],
                ['id' => P149_APP_BY_LID, 'lid_id' => P149_LID, 'status' => 'geaccepteerd'],
                ['id' => 'privacy149_app_keep', 'lid_id' => P149_KEEP, 'status' => 'geaccepteerd'],
            ],
        ],
    ];
}

function p149Zaai(): void
{
    $d = p149SeedDocumenten();
    p149Assert(ledenServiceSchrijf($d['leden'], false), 'Seed leden kon niet worden geschreven.');
    p149Assert(repoVergaderingenSchrijf($d['vergaderingen'], false), 'Seed vergaderingen kon niet worden geschreven.');
    p149Assert(repoTakenSchrijf($d['taken'], false), 'Seed taken kon niet worden geschreven.');
    p149Assert(repoOperationeleTakenSchrijf($d['operationele_taken'], false), 'Seed operationele taken kon niet worden geschreven.');
    p149Assert(repoEvenementenSchrijf($d['evenementen'], false), 'Seed evenementen kon niet worden geschreven.');
    p149Assert(groepenSchrijfDocument($d['groepen'], false), 'Seed groepen kon niet worden geschreven.');
    p149Assert(labelsSchrijfDocument($d['ledenlabels'], false), 'Seed ledenlabels kon niet worden geschreven.');
    p149Assert(contributiesSchrijf($d['contributies']), 'Seed contributies kon niet worden geschreven.');
    p149Assert(aanmeldingenSchrijf($d['aanmeldingen']), 'Seed aanmeldingen kon niet worden geschreven.');
}

function p149Collecties(): array
{
    return ['vergaderingen', 'taken', 'operationele_taken', 'evenementen', 'groepen', 'ledenlabels', 'contributies', 'aanmeldingen', 'leden'];
}

function p149JsonSnapshot(): array
{
    $root = privateStoreJsonRoot();
    p149Assert(is_string($root) && $root !== '', 'JSON-test mist private_root.');
    $snapshot = [];
    foreach (p149Collecties() as $collectie) {
        $pad = tenantRuntimeCollectiePad($root, $collectie);
        $snapshot[$collectie] = is_file($pad) ? file_get_contents($pad) : null;
    }
    return $snapshot;
}

function p149PdoSnapshot(): array
{
    $pdo = privateStorePdo();
    $stmt = $pdo->prepare('SELECT collection_key, payload, updated_at FROM vereniging_private_store WHERE tenant_key = :tenant ORDER BY collection_key');
    $stmt->execute(['tenant' => privateStoreTenant()]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function p149NaStap(string $stap, string $foutNa): void
{
    if ($stap === $foutNa) throw new RuntimeException('privacy149-fault:' . $stap);
}

function p149WisGeneriek(string $stap, callable $lezer, callable $schrijver, string $foutNa): void
{
    $data = $lezer();
    $voor = json_encode($data);
    ledenServicePurgeId($data, P149_LID);
    if ($voor !== json_encode($data)) {
        p149Assert((bool)$schrijver($data), 'Privacywrite faalde onverwacht voor ' . $stap . '.');
        p149NaStap($stap, $foutNa);
    }
}

/**
 * Zelfde mutatiegrenzen als ledenServiceVerwijderRelaties(), maar met een
 * testexception direct NA iedere echte repositorywrite. Zo kan JSON iedere
 * mogelijke crash-/foutgrens bewijzen zonder een fault hook aan productiecode.
 */
function p149VoerJsonMutatiesUit(string $foutNa): void
{
    p149WisGeneriek('vergaderingen', 'repoVergaderingenLees', 'repoVergaderingenSchrijf', $foutNa);
    p149WisGeneriek('taken', 'repoTakenLees', 'repoTakenSchrijf', $foutNa);
    p149WisGeneriek('operationele_taken', 'repoOperationeleTakenLees', 'repoOperationeleTakenSchrijf', $foutNa);
    p149WisGeneriek('evenementen', 'repoEvenementenLees', 'repoEvenementenSchrijf', $foutNa);

    $groepen = groepenLeesDocument();
    $voor = json_encode($groepen);
    groepenPurgeLid($groepen, P149_LID);
    if ($voor !== json_encode($groepen)) {
        p149Assert(groepenSchrijfDocument($groepen), 'Privacywrite faalde onverwacht voor groepen.');
        p149NaStap('groepen', $foutNa);
    }

    $labels = labelsLeesDocument();
    $voor = json_encode($labels);
    labelsPurgeLid($labels, P149_LID);
    if ($voor !== json_encode($labels)) {
        p149Assert(labelsSchrijfDocument($labels), 'Privacywrite faalde onverwacht voor ledenlabels.');
        p149NaStap('ledenlabels', $foutNa);
    }

    $fin = contributiesLees();
    $voor = count((array)($fin['regels'] ?? []));
    $fin['regels'] = array_values(array_filter((array)($fin['regels'] ?? []), static fn($r) => !is_array($r) || ($r['lid_id'] ?? '') !== P149_LID));
    if (count($fin['regels']) !== $voor) {
        p149Assert(contributiesSchrijf($fin), 'Privacywrite faalde onverwacht voor contributies.');
        p149NaStap('contributies', $foutNa);
    }

    $apps = aanmeldingenLees();
    $voor = count((array)($apps['aanmeldingen'] ?? []));
    $apps['aanmeldingen'] = array_values(array_filter((array)($apps['aanmeldingen'] ?? []), static function($a) {
        if (!is_array($a)) return true;
        if (($a['lid_id'] ?? '') === P149_LID) return false;
        return ($a['id'] ?? '') !== P149_APP;
    }));
    if (count($apps['aanmeldingen']) !== $voor) {
        p149Assert(aanmeldingenSchrijf($apps), 'Privacywrite faalde onverwacht voor aanmeldingen.');
        p149NaStap('aanmeldingen', $foutNa);
    }

    $leden = ledenServiceLees();
    $over = [];
    $gevonden = null;
    foreach ((array)($leden['leden'] ?? []) as $lid) {
        if (is_array($lid) && ($lid['id'] ?? '') === P149_LID) { $gevonden = $lid; continue; }
        $over[] = $lid;
    }
    p149Assert(is_array($gevonden) && !empty($gevonden['gearchiveerd_op']), 'Seed bevat geen wisbaar gearchiveerd lid.');
    if (isset($leden['commissies']) && is_array($leden['commissies'])) ledenServicePurgeId($leden['commissies'], P149_LID);
    $leden['leden'] = $over;
    p149Assert(ledenServiceSchrijf($leden), 'Privacywrite faalde onverwacht voor leden.');
    p149NaStap('leden', $foutNa);
}

function p149WerkelijkeErase(): array
{
    $resultaat = privateStoreTransactie(static function() {
        $data = ledenServiceLees();
        $gevonden = ledenServiceDefinitiefVerwijder($data, P149_LID);
        if (!is_array($gevonden)) throw new RuntimeException('Werkelijke privacy-erase vond het gearchiveerde lid niet.');
        if (!ledenServiceSchrijf($data)) throw new RuntimeException('Werkelijke privacy-erase kon leden niet opslaan.');
        return $gevonden;
    });
    p149Assert(is_array($resultaat) && ($resultaat['id'] ?? '') === P149_LID, 'Werkelijke privacy-erase leverde niet het verwijderde lid terug.');
    return $resultaat;
}

function p149DocumentenNaErase(): array
{
    return [
        'vergaderingen' => repoVergaderingenLees(),
        'taken' => repoTakenLees(),
        'operationele_taken' => repoOperationeleTakenLees(),
        'evenementen' => repoEvenementenLees(),
        'groepen' => groepenLeesDocument(),
        'ledenlabels' => labelsLeesDocument(),
        'contributies' => contributiesLees(),
        'aanmeldingen' => aanmeldingenLees(),
        'leden' => ledenServiceLees(),
    ];
}

function p149ControleerSucces(): void
{
    $docs = p149DocumentenNaErase();
    foreach ($docs as $naam => $doc) {
        p149Assert(!p149BevatExact($doc, P149_LID), 'Succesvolle erase liet lid-id achter in ' . $naam . '.');
        p149Assert(p149BevatExact($doc, P149_KEEP), 'Succesvolle erase verwijderde ongerelateerd bewaarlid uit ' . $naam . '.');
    }
    p149Assert(!p149BevatExact($docs['aanmeldingen'], P149_APP), 'Succesvolle erase liet gekoppelde aanmelding achter.');
    p149Assert(!p149BevatExact($docs['aanmeldingen'], P149_APP_BY_LID), 'Succesvolle erase liet aanmelding op lid_id achter.');
}

function p149Broncontract(): void
{
    $root = dirname(__DIR__, 2);
    $service = (string)file_get_contents($root . '/app/leden/service.php');
    $beheer = (string)file_get_contents($root . '/beheer/leden.php');

    $verwacht = [
        'repoVergaderingenSchrijf', 'repoTakenSchrijf', 'repoOperationeleTakenSchrijf', 'repoEvenementenSchrijf',
        'groepenSchrijfDocument', 'labelsSchrijfDocument', 'contributiesSchrijf', 'aanmeldingenSchrijf',
        'ledenServiceVerwijderRelaties',
    ];
    foreach ($verwacht as $symbool) p149Assert(str_contains($service, $symbool), 'Privacy-erase mist verwachte mutatiegrens ' . $symbool . '.');
    p149Assert(str_contains($beheer, "authHeeftCapability('members.erase',true)"), 'Privacy-erase moet members.erase blijven vereisen.');
    p149Assert(str_contains($beheer, 'csrfOk()'), 'Privacy-erase moet CSRF-validatie blijven gebruiken.');
    p149Assert(str_contains($beheer, "'VERWIJDER'"), 'Privacy-erase moet letterlijke VERWIJDER-bevestiging blijven vereisen.');
    p149Assert(str_contains($beheer, 'privateStoreTransactie'), 'Privacy-erase moet onder privateStoreTransactie blijven draaien.');
    p149Assert(str_contains($beheer, 'Definitief wissen is gestopt'), 'Opslag-/rollbackfouten moeten zichtbaar naar de beheerder blijven terugkomen.');
}

function p149RunJson(): void
{
    p149Broncontract();
    p149Assert(privateStoreDriver() === 'json', 'JSON-run verwacht private_driver=json.');
    p149Assert(privateStoreJsonRoot() !== null, 'JSON-run verwacht geïsoleerde private_root.');

    foreach (p149Collecties() as $stap) {
        p149Zaai();
        $voor = p149JsonSnapshot();
        $gezien = false;
        try {
            privateStoreTransactie(static function() use ($stap): void { p149VoerJsonMutatiesUit($stap); });
        } catch (RuntimeException $e) {
            $gezien = $e->getMessage() === 'privacy149-fault:' . $stap;
        }
        p149Assert($gezien, 'Fault na JSON-mutatiegrens ' . $stap . ' werd niet als fout gezien.');
        p149Assert($voor === p149JsonSnapshot(), 'JSON-rollback herstelde na fault bij ' . $stap . ' niet byte-identiek alle collecties.');
    }

    // Rollbackfalen zelf moet hard zichtbaar worden en mag nooit als succes eindigen.
    p149Zaai();
    $rollbackZichtbaar = false;
    try {
        privateStoreTransactie(static function(): void {
            $data = repoVergaderingenLees();
            ledenServicePurgeId($data, P149_LID);
            p149Assert(repoVergaderingenSchrijf($data), 'Rollbackfail-test kon eerste privacywrite niet uitvoeren.');
            $context =& privateStoreJsonTransactieContext();
            $tx = reset($context['snapshots']);
            p149Assert(is_array($tx) && isset($tx['entries'][0]['snapshot']), 'Rollbackfail-test kon snapshot niet vinden.');
            @unlink((string)$tx['entries'][0]['snapshot']);
            throw new RuntimeException('privacy149-forceer-rollback');
        });
    } catch (RuntimeException $e) {
        $rollbackZichtbaar = str_contains($e->getMessage(), 'rollback kon niet volledig worden uitgevoerd');
    }
    p149Assert($rollbackZichtbaar, 'Onvolledige JSON-rollback werd niet zichtbaar als harde fout gesignaleerd.');

    p149Zaai();
    p149WerkelijkeErase();
    p149ControleerSucces();
}

function p149RunPdo(): void
{
    p149Broncontract();
    p149Assert(privateStoreDriver() === 'pdo', 'PDO-run verwacht private_driver=pdo.');
    $pdo = privateStorePdo();
    p149Assert(strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite', 'PDO-regressie verwacht SQLite.');

    foreach (p149Collecties() as $stap) {
        p149Zaai();
        $voor = p149PdoSnapshot();
        $pdo->exec('DROP TRIGGER IF EXISTS privacy149_fail');
        $quoted = $pdo->quote($stap);
        $pdo->exec('CREATE TRIGGER privacy149_fail BEFORE UPDATE ON vereniging_private_store WHEN NEW.tenant_key = ' . $pdo->quote(privateStoreTenant()) . ' AND NEW.collection_key = ' . $quoted . " BEGIN SELECT RAISE(ABORT, 'privacy149 forced write fault'); END");
        $gezien = false;
        try {
            p149WerkelijkeErase();
        } catch (RuntimeException $e) {
            $gezien = true;
        } finally {
            $pdo->exec('DROP TRIGGER IF EXISTS privacy149_fail');
        }
        p149Assert($gezien, 'Geforceerde PDO-writefout bij ' . $stap . ' werd niet zichtbaar.');
        p149Assert($voor === p149PdoSnapshot(), 'PDO-rollback herstelde na writefout bij ' . $stap . ' niet alle payloads/timestamps.');
    }

    p149Zaai();
    p149WerkelijkeErase();
    p149ControleerSucces();
}
