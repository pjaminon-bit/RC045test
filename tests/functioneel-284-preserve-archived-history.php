<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function f284(bool $conditie, string $label): void
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

require_once $root . '/app/leden/service.php';

$leden = [
    ['id' => 'actief_a', 'gearchiveerd_op' => '', 'bestuursfunctie' => ''],
    ['id' => 'actief_b', 'gearchiveerd_op' => '', 'bestuursfunctie' => ''],
    ['id' => 'archief', 'gearchiveerd_op' => '2026-09-01T10:00:00+02:00', 'bestuursfunctie' => ''],
    ['id' => 'oud_bestuur', 'gearchiveerd_op' => '', 'bestuursfunctie' => ''],
    ['id' => 'bestuur', 'gearchiveerd_op' => '', 'bestuursfunctie' => 'bestuurslid'],
    ['id' => 'archief_bestuur', 'gearchiveerd_op' => '2026-09-01T10:00:00+02:00', 'bestuursfunctie' => 'bestuurslid'],
];

$actiefSelecteerbaar = static fn(array $lid): bool => empty($lid['gearchiveerd_op']);

$eventNieuw = [
    'actief_a' => true,
    'archief' => false,
    'bestaat_niet' => true,
];
$eventBestaand = [
    'actief_b' => true,
    'archief' => true,
    'bestaat_niet' => true,
];
$eventResultaat = ledenServiceNormaliseerRelatiesMetHistorie(
    $eventNieuw,
    $eventBestaand,
    $leden,
    $actiefSelecteerbaar
);

f284(array_keys($eventResultaat) === ['actief_a', 'archief'], 'evenement behoudt gearchiveerde historische deelnemer en verwijdert uitgevinkte actieve deelnemer');
f284(($eventResultaat['archief'] ?? null) === true, 'verborgen gearchiveerde relatie houdt bestaande waarde in plaats van gemanipuleerde nieuwe waarde');
f284(!array_key_exists('bestaat_niet', $eventResultaat), 'dangling lid-id wordt niet historisch behouden');

$bestuurSelecteerbaar = static fn(array $lid): bool => empty($lid['gearchiveerd_op']) && ledenIsBestuurslid($lid);
$vergaderingNieuw = [
    'bestuur' => 'aanwezig',
    'oud_bestuur' => 'afwezig',
    'archief_bestuur' => 'afwezig',
    'bestaat_niet' => 'aanwezig',
];
$vergaderingBestaand = [
    'bestuur' => 'afwezig',
    'oud_bestuur' => 'aanwezig',
    'archief_bestuur' => 'aanwezig',
    'bestaat_niet' => 'afwezig',
];
$vergaderingResultaat = ledenServiceNormaliseerRelatiesMetHistorie(
    $vergaderingNieuw,
    $vergaderingBestaand,
    $leden,
    $bestuurSelecteerbaar
);

f284(($vergaderingResultaat['bestuur'] ?? '') === 'aanwezig', 'actueel bestuurslid kan aanwezigheid normaal wijzigen');
f284(($vergaderingResultaat['oud_bestuur'] ?? '') === 'aanwezig', 'voormalig bestuurslid blijft als historische aanwezigheid behouden');
f284(($vergaderingResultaat['archief_bestuur'] ?? '') === 'aanwezig', 'gearchiveerd bestuurslid blijft als historische aanwezigheid behouden');
f284(!array_key_exists('bestaat_niet', $vergaderingResultaat), 'vergadering ruimt onbekende historische lid-id op');

$zonderActueelBestuur = ledenServiceNormaliseerRelatiesMetHistorie(
    [],
    ['bestuur' => 'aanwezig', 'oud_bestuur' => 'afwezig'],
    $leden,
    $bestuurSelecteerbaar
);
f284(!array_key_exists('bestuur', $zonderActueelBestuur), 'selecteerbare actieve relatie kan bewust worden verwijderd');
f284(($zonderActueelBestuur['oud_bestuur'] ?? '') === 'afwezig', 'niet-selecteerbare voormalige bestuursrelatie blijft bij overige edit staan');

$ledenSelecteerbaar = static fn(array $lid): bool => empty($lid['gearchiveerd_op']);
$ledenvergadering = ledenServiceNormaliseerRelatiesMetHistorie(
    [],
    ['oud_bestuur' => 'aanwezig', 'archief_bestuur' => 'afwezig'],
    $leden,
    $ledenSelecteerbaar
);
f284(!array_key_exists('oud_bestuur', $ledenvergadering), 'actief voormalig bestuurslid is in ledenvergadering selecteerbaar en wordt bij omissie verwijderd');
f284(($ledenvergadering['archief_bestuur'] ?? '') === 'afwezig', 'gearchiveerd lid blijft ook bij ledenvergadering historisch behouden');

$eventBron = (string)file_get_contents($root . '/beheer/evenementen.php');
$vergaderingBron = (string)file_get_contents($root . '/beheer/vergaderingen.php');
f284(str_contains($eventBron, 'ledenServiceNormaliseerRelatiesMetHistorie('), 'evenementenbeheer gebruikt centrale historische-relatiepolicy');
f284(str_contains($vergaderingBron, 'ledenServiceNormaliseerRelatiesMetHistorie('), 'vergaderingenbeheer gebruikt centrale historische-relatiepolicy');
f284(!str_contains($eventBron, "array_intersect((array)\$e['deelnemers'],\$geldige)"), 'oude actieve-only deelnemersfilter is uit evenementenbeheer verwijderd');

echo "Functioneel #284 behoud historische lidrelaties: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
