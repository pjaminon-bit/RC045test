<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;
function p290(bool $conditie, string $label): void {
    global $ok, $fout;
    if ($conditie) { $ok++; echo "OK: {$label}\n"; return; }
    $fout++; fwrite(STDERR, "FOUT: {$label}\n");
}

require_once $root . '/leden-opslag.php';
require_once $root . '/app/leden/import-index.php';

function p290Lid(int $nummer, string $voornaam, string $achternaam, string $email = '', string $geboorte = ''): array {
    return [
        'nummer' => $nummer,
        'voornaam' => $voornaam,
        'tussenvoegsel' => '',
        'achternaam' => $achternaam,
        'email' => $email,
        'geboortedatum' => $geboorte,
    ];
}

$data = [
    'volgnummer' => 100,
    'leden' => [
        p290Lid(10, 'Jan', 'Jansen', 'gedeeld@example.nl', '1980-01-01'),
        p290Lid(11, 'Piet', 'Pieters', 'gedeeld@example.nl', '1981-01-01'),
        p290Lid(20, 'Nummer', 'Naam', 'nummer-a@example.nl', '1982-01-01'),
        p290Lid(20, 'Nummer', 'Naam', 'nummer-b@example.nl', '1983-01-01'),
        p290Lid(30, 'Datum', 'Dubbel', '', '1990-05-05'),
        p290Lid(31, 'Datum', 'Dubbel', '', '1990-05-05'),
        p290Lid(40, 'Uniek', 'Persoon', 'uniek@example.nl', '1991-06-06'),
        p290Lid(50, 'Naamgenoot', 'Test', '', '1992-07-07'),
        p290Lid(51, 'Naamgenoot', 'Test', '', '1993-08-08'),
    ],
];
$index = ledenImportIndexBouw($data);

$kandidaten = [
    p290Lid(20, 'Nummer', 'Naam', 'gedeeld@example.nl', '1982-01-01'), // e-mail wint van nummer+naam
    p290Lid(20, 'Nummer', 'Naam', '', ''),
    p290Lid(0, 'Datum', 'Dubbel', '', '1990-05-05'),
    p290Lid(0, 'Uniek', 'Persoon', '', ''),
    p290Lid(0, 'Naamgenoot', 'Test', '', ''),
    p290Lid(0, 'Uniek', 'Persoon', 'anders@example.nl', ''),
    p290Lid(999, 'Uniek', 'Persoon', '', ''),
    p290Lid(0, '', '', 'bestaat-niet@example.nl', ''),
];
foreach ($kandidaten as $i => $kandidaat) {
    $legacy = ledenZoekBestaandeMet($data, $kandidaat);
    $indexed = ledenImportIndexZoekMet($index, $data, $kandidaat);
    p290($indexed === $legacy, 'indexed matcher is semantisch gelijk aan legacy case ' . ($i + 1));
}

$prioriteit = ledenImportIndexZoekMet($index, $data, $kandidaten[0]);
p290($prioriteit['index'] === 0 && $prioriteit['reden'] === 'mailadres', 'e-mailprioriteit en first-match blijven behouden');
$nummerMatch = ledenImportIndexZoekMet($index, $data, $kandidaten[1]);
p290($nummerMatch['index'] === 2 && $nummerMatch['reden'] === 'lidnummer en naam', 'lidnummer+naam behoudt first-match');
$datumMatch = ledenImportIndexZoekMet($index, $data, $kandidaten[2]);
p290($datumMatch['index'] === 4 && $datumMatch['reden'] === 'naam en geboortedatum', 'naam+geboortedatum behoudt first-match');
p290(ledenImportIndexZoekMet($index, $data, $kandidaten[4])['index'] === null, 'dubbele naam blijft ambigu');
p290(ledenImportIndexZoekMet($index, $data, $kandidaten[5])['index'] === null, 'unieke naam met conflicterend e-mail blijft afgewezen');
p290(ledenImportIndexZoekMet($index, $data, $kandidaten[6])['index'] === null, 'unieke naam met conflicterend nummer blijft afgewezen');

// Een update op een lage array-index moet ook na herindexeren de eerste hit blijven.
$oud = $data['leden'][0];
$nieuw = $oud;
$nieuw['email'] = 'nummer-b@example.nl';
$data['leden'][0] = $nieuw;
ledenImportIndexWerkBij($index, $oud, $nieuw, 0);
$naUpdate = ledenImportIndexZoekMet($index, $data, p290Lid(0, 'X', 'Y', 'nummer-b@example.nl'));
p290($naUpdate['index'] === 0, 'indexupdate bewaart originele first-matchvolgorde');

// Een nieuw lid moet direct zichtbaar zijn voor een volgende regel uit dezelfde bevestigbatch.
$nieuwIndex = count($data['leden']);
$toegevoegd = p290Lid(60, 'Batch', 'Nieuw', 'batch@example.nl', '2000-01-01');
$data['leden'][] = $toegevoegd;
ledenImportIndexWerkBij($index, null, $toegevoegd, $nieuwIndex);
$batchMatch = ledenImportIndexZoekMet($index, $data, p290Lid(0, 'Andere', 'Naam', 'batch@example.nl'));
p290($batchMatch['index'] === $nieuwIndex, 'toevoeging is direct zichtbaar voor latere importregel');

// Toevoegen van een naamgenoot maakt de zachte naamfallback meteen ambigu.
$naamgenootIndex = count($data['leden']);
$naamgenoot = p290Lid(61, 'Uniek', 'Persoon', '', '');
$data['leden'][] = $naamgenoot;
ledenImportIndexWerkBij($index, null, $naamgenoot, $naamgenootIndex);
p290(ledenImportIndexZoekMet($index, $data, p290Lid(0, 'Uniek', 'Persoon'))['index'] === null, 'batchupdate actualiseert unieke-naamambiguïteit');

p290(ledenImportIndexNummerInGebruik($index, 20), 'nummerindex ziet bestaand lidnummer');
p290(!ledenImportIndexNummerInGebruik($index, 101), 'nummerindex ziet vrij lidnummer');
$volgend = ledenImportIndexVolgendNummer($index);
p290($volgend === 101, 'nummerallocator start na bestaand volgnummer/maxnummer');
$nummerIndex = count($data['leden']);
$metNummer = p290Lid($volgend, 'Nummer', 'Nieuw');
$data['leden'][] = $metNummer;
ledenImportIndexWerkBij($index, null, $metNummer, $nummerIndex);
p290(ledenImportIndexNummerInGebruik($index, 101), 'gegenereerd nummer wordt na toevoeging als gebruikt gezien');
p290(ledenImportIndexVolgendNummer($index) === 102, 'nummerallocator gaat zonder collectie-scan door naar volgend nummer');

$bron = (string)file_get_contents($root . '/beheer/leden-import.php');
p290(str_contains($bron, "'/app/leden/import-index.php'"), 'importcontroller laadt batchindexhelper');
p290(substr_count($bron, 'ledenImportIndexBouw(') >= 2, 'preview en bevestigen bouwen ieder eenmaal een batchindex');
p290(substr_count($bron, 'ledenImportIndexZoekMet(') >= 2, 'preview en bevestigen gebruiken indexed matcher');
p290(!str_contains($bron, 'ledenZoekBestaandeMet($data, $k)'), 'importcontroller gebruikt geen legacy volledige scan per rij meer');
p290(!str_contains($bron, 'ledenVolgendNummer($data)'), 'importcontroller gebruikt geen lineaire nummerallocator per nieuw lid meer');
p290(!str_contains($bron, "foreach (\$data['leden'] as \$ander)"), 'importcontroller heeft geen lineaire lidnummercollision-scan meer');

echo "Performance #290 ledenimport batchindex: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
