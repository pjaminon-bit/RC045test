<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function f280(bool $conditie, string $label): void
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

$types = [
    'jeugd' => [
        'id' => 'jeugd',
        'naam' => 'Jeugd',
        'actief' => true,
        'leeftijd_min' => 0,
        'leeftijd_max' => 15,
    ],
    'senior' => [
        'id' => 'senior',
        'naam' => 'Senior',
        'actief' => true,
        'leeftijd_min' => 16,
        'leeftijd_max' => null,
    ],
    'oud' => [
        'id' => 'oud',
        'naam' => 'Historisch type',
        'actief' => false,
        'leeftijd_min' => null,
        'leeftijd_max' => null,
    ],
];
$resolver = static fn(string $id): ?array => $types[$id] ?? null;
$jaar = 2026;

$nieuwSenior = ['id' => 'lid_nieuw', 'geboortedatum' => '1990-06-01', 'lidmaatschap_type' => 'senior'];
f280(
    ledenServiceLidmaatschapToewijzingGeldig($nieuwSenior, null, $types['senior'], $jaar),
    'actief type binnen leeftijdsgrens is toegestaan'
);

$nieuwJeugdTeOud = ['id' => 'lid_te_oud', 'geboortedatum' => '1990-06-01', 'lidmaatschap_type' => 'jeugd'];
f280(
    !ledenServiceLidmaatschapToewijzingGeldig($nieuwJeugdTeOud, null, $types['jeugd'], $jaar),
    'type boven maximumleeftijd wordt geweigerd'
);

$nieuwSeniorTeJong = ['id' => 'lid_te_jong', 'geboortedatum' => '2015-06-01', 'lidmaatschap_type' => 'senior'];
f280(
    !ledenServiceLidmaatschapToewijzingGeldig($nieuwSeniorTeJong, null, $types['senior'], $jaar),
    'type onder minimumleeftijd wordt geweigerd'
);

$nieuwInactief = ['id' => 'lid_inactief', 'geboortedatum' => '1990-06-01', 'lidmaatschap_type' => 'oud'];
f280(
    !ledenServiceLidmaatschapToewijzingGeldig($nieuwInactief, null, $types['oud'], $jaar),
    'inactief type kan niet nieuw worden toegewezen'
);

$nieuwOnbekend = ['id' => 'lid_onbekend', 'geboortedatum' => '1990-06-01', 'lidmaatschap_type' => 'bestaat_niet'];
f280(
    !ledenServiceLidmaatschapToewijzingGeldig($nieuwOnbekend, null, null, $jaar),
    'onbekend type kan niet nieuw worden toegewezen'
);

$zonderType = ['id' => 'lid_zonder_type', 'geboortedatum' => '1990-06-01', 'lidmaatschap_type' => ''];
f280(
    ledenServiceLidmaatschapToewijzingGeldig($zonderType, null, null, $jaar),
    'bestaand contract blijft een lege typekeuze toestaan'
);

$historisch = ['id' => 'lid_historisch', 'geboortedatum' => '1990-06-01', 'lidmaatschap_type' => 'oud'];
$historischEdit = $historisch;
$historischEdit['telefoon'] = '0612345678';
f280(
    ledenServiceLidmaatschapToewijzingGeldig($historischEdit, $historisch, null, $jaar),
    'ongewijzigd historisch/inactief type blijft bij ongerelateerde edit behouden'
);

$historischDobGewijzigd = $historisch;
$historischDobGewijzigd['geboortedatum'] = '1991-06-01';
f280(
    !ledenServiceLidmaatschapToewijzingGeldig($historischDobGewijzigd, $historisch, $types['oud'], $jaar),
    'geboortedatumwijziging heractiveert centrale validatie van historisch type'
);

$oudSenior = ['id' => 'lid_wissel', 'geboortedatum' => '1990-06-01', 'lidmaatschap_type' => 'senior'];
$wisselNaarJeugd = $oudSenior;
$wisselNaarJeugd['lidmaatschap_type'] = 'jeugd';
f280(
    !ledenServiceLidmaatschapToewijzingGeldig($wisselNaarJeugd, $oudSenior, $types['jeugd'], $jaar),
    'wijzigen naar leeftijdstechnisch ongeldig type wordt geweigerd'
);

$oudJeugd = ['id' => 'lid_geldig_wissel', 'geboortedatum' => '2015-06-01', 'lidmaatschap_type' => ''];
$wisselNaarGeldigeJeugd = $oudJeugd;
$wisselNaarGeldigeJeugd['lidmaatschap_type'] = 'jeugd';
f280(
    ledenServiceLidmaatschapToewijzingGeldig($wisselNaarGeldigeJeugd, $oudJeugd, $types['jeugd'], $jaar),
    'wijzigen naar geldig actief type wordt toegestaan'
);

$oudDocument = ['leden' => [$historisch, $oudSenior]];
$nieuwDocument = ['leden' => [$historischEdit, $wisselNaarJeugd]];
f280(
    !ledenServiceLidmaatschappenGeldig($nieuwDocument, $oudDocument, $jaar, $resolver),
    'documentvalidator blokkeert een ongeldige nieuwe toewijzing ondanks historisch geldige behoudregel'
);

$nieuwDocumentGeldig = ['leden' => [$historischEdit, $oudSenior]];
f280(
    ledenServiceLidmaatschappenGeldig($nieuwDocumentGeldig, $oudDocument, $jaar, $resolver),
    'documentvalidator accepteert ongewijzigde historische typen bij overige edits'
);

$nieuwDocumentMetNieuwLid = ['leden' => [$historischEdit, $oudSenior, $nieuwSenior]];
f280(
    ledenServiceLidmaatschappenGeldig($nieuwDocumentMetNieuwLid, $oudDocument, $jaar, $resolver),
    'documentvalidator accepteert nieuw lid met toegestaan type'
);

$nieuwDocumentInactief = ['leden' => [$historischEdit, $oudSenior, $nieuwInactief]];
f280(
    !ledenServiceLidmaatschappenGeldig($nieuwDocumentInactief, $oudDocument, $jaar, $resolver),
    'documentvalidator weigert nieuw lid met inactief type'
);

echo "Functioneel #280 lidmaatschapstypevalidatie: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
