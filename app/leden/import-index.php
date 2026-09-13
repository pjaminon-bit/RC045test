<?php
// Batchindex voor de ledenimport. Houdt de bestaande conservatieve
// matchsemantiek uit ledenZoekBestaandeMet() intact, maar voorkomt dat
// iedere CSV-regel opnieuw de volledige ledencollectie moet doorlopen.

function ledenImportIndexSignaturen(array $lid): array
{
    $email = strtolower(trim((string)($lid['email'] ?? '')));
    $naam = strtolower(ledenVolledigeNaam($lid));
    $geboortedatum = trim((string)($lid['geboortedatum'] ?? ''));
    $nummer = (int)($lid['nummer'] ?? 0);

    return [
        'email' => $email,
        'nummer_naam' => ($nummer > 0 && $naam !== '') ? $nummer . "\0" . $naam : '',
        'naam_geboorte' => ($naam !== '' && $geboortedatum !== '') ? $naam . "\0" . $geboortedatum : '',
        'naam' => $naam,
        'nummer' => $nummer,
    ];
}

function ledenImportIndexBucketVoegToe(array &$verzameling, string $sleutel, int $lidIndex): void
{
    if ($sleutel === '') return;
    $bucket = $verzameling[$sleutel] ?? [];
    if ($bucket === []) {
        $verzameling[$sleutel] = [$lidIndex];
        return;
    }

    $laatste = $bucket[array_key_last($bucket)];
    if ($laatste === $lidIndex) return;
    // Tijdens de initiële build en bij nieuwe leden lopen indices altijd op.
    // Die dominante batchroute moet O(1) per bucket-add blijven, ook als veel
    // leden dezelfde naam of hetzelfde importkenmerk delen.
    if ($laatste < $lidIndex) {
        $bucket[] = $lidIndex;
        $verzameling[$sleutel] = $bucket;
        return;
    }

    // Alleen een wijziging van een ouder bestaand record kan een index midden
    // in een bucket terugplaatsen. Houd de array dan oplopend zodat [0]
    // hetzelfde first-matchgedrag houdt als de legacy lineaire scan.
    foreach ($bucket as $positie => $bestaandIndex) {
        if ($bestaandIndex === $lidIndex) return;
        if ($bestaandIndex > $lidIndex) {
            array_splice($bucket, $positie, 0, [$lidIndex]);
            $verzameling[$sleutel] = $bucket;
            return;
        }
    }
}

function ledenImportIndexBucketVerwijder(array &$verzameling, string $sleutel, int $lidIndex): void
{
    if ($sleutel === '' || !isset($verzameling[$sleutel])) return;
    $positie = array_search($lidIndex, $verzameling[$sleutel], true);
    if ($positie === false) return;
    array_splice($verzameling[$sleutel], (int)$positie, 1);
    if ($verzameling[$sleutel] === []) unset($verzameling[$sleutel]);
}

function ledenImportIndexVoegSignaturenToe(array &$index, array $signaturen, int $lidIndex): void
{
    ledenImportIndexBucketVoegToe($index['email'], (string)$signaturen['email'], $lidIndex);
    ledenImportIndexBucketVoegToe($index['nummer_naam'], (string)$signaturen['nummer_naam'], $lidIndex);
    ledenImportIndexBucketVoegToe($index['naam_geboorte'], (string)$signaturen['naam_geboorte'], $lidIndex);
    ledenImportIndexBucketVoegToe($index['naam'], (string)$signaturen['naam'], $lidIndex);

    $nummer = (int)$signaturen['nummer'];
    if ($nummer > 0) {
        $index['nummers'][$nummer] = (int)($index['nummers'][$nummer] ?? 0) + 1;
        if ($nummer >= $index['volgend_nummer']) $index['volgend_nummer'] = $nummer + 1;
    }
}

function ledenImportIndexVerwijderSignaturen(array &$index, array $signaturen, int $lidIndex): void
{
    ledenImportIndexBucketVerwijder($index['email'], (string)$signaturen['email'], $lidIndex);
    ledenImportIndexBucketVerwijder($index['nummer_naam'], (string)$signaturen['nummer_naam'], $lidIndex);
    ledenImportIndexBucketVerwijder($index['naam_geboorte'], (string)$signaturen['naam_geboorte'], $lidIndex);
    ledenImportIndexBucketVerwijder($index['naam'], (string)$signaturen['naam'], $lidIndex);

    $nummer = (int)$signaturen['nummer'];
    if ($nummer > 0 && isset($index['nummers'][$nummer])) {
        $index['nummers'][$nummer]--;
        if ($index['nummers'][$nummer] <= 0) unset($index['nummers'][$nummer]);
    }
}

function ledenImportIndexBouw(array $data): array
{
    $index = [
        'email' => [],
        'nummer_naam' => [],
        'naam_geboorte' => [],
        'naam' => [],
        'nummers' => [],
        'volgend_nummer' => max(1, (int)($data['volgnummer'] ?? 0) + 1),
    ];

    foreach ((array)($data['leden'] ?? []) as $lidIndex => $lid) {
        if (!is_array($lid)) continue;
        ledenImportIndexVoegSignaturenToe($index, ledenImportIndexSignaturen($lid), (int)$lidIndex);
    }

    return $index;
}

function ledenImportIndexZoekMet(array $index, array $data, array $kandidaat): array
{
    $geen = ['index' => null, 'reden' => ''];
    $sig = ledenImportIndexSignaturen($kandidaat);
    $email = (string)$sig['email'];
    $naam = (string)$sig['naam'];
    $geboortedatum = trim((string)($kandidaat['geboortedatum'] ?? ''));
    $nummer = (int)$sig['nummer'];

    if ($email !== '' && !empty($index['email'][$email])) {
        return ['index' => $index['email'][$email][0], 'reden' => 'mailadres'];
    }

    if ($naam === '') return $geen;

    $nummerNaam = (string)$sig['nummer_naam'];
    if ($nummer > 0 && $nummerNaam !== '' && !empty($index['nummer_naam'][$nummerNaam])) {
        return ['index' => $index['nummer_naam'][$nummerNaam][0], 'reden' => 'lidnummer en naam'];
    }

    $naamGeboorte = (string)$sig['naam_geboorte'];
    if ($geboortedatum !== '' && $naamGeboorte !== '' && !empty($index['naam_geboorte'][$naamGeboorte])) {
        return ['index' => $index['naam_geboorte'][$naamGeboorte][0], 'reden' => 'naam en geboortedatum'];
    }

    $treffers = $index['naam'][$naam] ?? [];
    if (count($treffers) !== 1) return $geen;

    $lidIndex = $treffers[0];
    $lid = $data['leden'][$lidIndex] ?? null;
    if (!is_array($lid)) return $geen;

    $lidEmail = strtolower(trim((string)($lid['email'] ?? '')));
    $lidGeboorte = trim((string)($lid['geboortedatum'] ?? ''));
    $lidNummer = (int)($lid['nummer'] ?? 0);
    if ($email !== '' && $lidEmail !== '' && $email !== $lidEmail) return $geen;
    if ($geboortedatum !== '' && $lidGeboorte !== '' && $geboortedatum !== $lidGeboorte) return $geen;
    if ($nummer > 0 && $lidNummer > 0 && $nummer !== $lidNummer) return $geen;

    return ['index' => $lidIndex, 'reden' => 'naam'];
}

function ledenImportIndexWerkBij(array &$index, ?array $bestaand, array $nieuw, int $lidIndex): void
{
    if ($bestaand !== null) {
        ledenImportIndexVerwijderSignaturen($index, ledenImportIndexSignaturen($bestaand), $lidIndex);
    }
    ledenImportIndexVoegSignaturenToe($index, ledenImportIndexSignaturen($nieuw), $lidIndex);
}

function ledenImportIndexNummerInGebruik(array $index, int $nummer): bool
{
    return $nummer > 0 && (int)($index['nummers'][$nummer] ?? 0) > 0;
}

function ledenImportIndexVolgendNummer(array &$index): int
{
    $nummer = max(1, (int)($index['volgend_nummer'] ?? 1));
    while (ledenImportIndexNummerInGebruik($index, $nummer)) $nummer++;
    $index['volgend_nummer'] = $nummer + 1;
    return $nummer;
}
