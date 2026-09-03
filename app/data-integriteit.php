<?php
// ============================================================
// Referentiële data-integriteit voor taken, vergaderingen en evenementen.
//
// Deletebeleid:
// - taak: hard delete + groep->taak relaties verwijderen;
// - vergadering: hard delete + taak.vergadering_* ontkoppelen +
//   groep->vergadering relaties verwijderen;
// - evenement: hard delete + groep->evenement relaties verwijderen.
//
// Alle multi-store mutaties lopen via privateStoreBatchTransactie(), zodat
// zowel PDO als JSON dezelfde all-or-nothing-semantiek hebben.
// ============================================================
require_once __DIR__ . '/storage/domein-repositories.php';
require_once __DIR__ . '/storage/private-store-batch-transaction.php';

function dataIntegriteitId($waarde): string
{
    if (!is_scalar($waarde) && $waarde !== null) return '';
    $id = trim((string)$waarde);
    if ($id === '') return '';
    return function_exists('mb_substr') ? mb_substr($id, 0, 80, 'UTF-8') : substr($id, 0, 80);
}

function dataIntegriteitIdSet(array $data, string $sleutel): array
{
    $set = [];
    foreach ((array)($data[$sleutel] ?? []) as $record) {
        if (!is_array($record)) continue;
        $id = dataIntegriteitId($record['id'] ?? '');
        if ($id !== '') $set[$id] = true;
    }
    return $set;
}

function dataIntegriteitVindIndex(array $data, string $sleutel, string $id): ?int
{
    foreach ((array)($data[$sleutel] ?? []) as $i => $record) {
        if (is_array($record) && dataIntegriteitId($record['id'] ?? '') === $id) return (int)$i;
    }
    return null;
}

function dataIntegriteitRepoSchrijf(string $store, array $data, array $writers = []): void
{
    $writer = $writers[$store] ?? null;
    if ($writer !== null && !is_callable($writer)) {
        throw new InvalidArgumentException('Ongeldige testwriter voor ' . $store . '.');
    }
    if ($writer === null) {
        $writer = match ($store) {
            'taken' => static fn(array $doc): bool => repoTakenSchrijf($doc),
            'vergaderingen' => static fn(array $doc): bool => repoVergaderingenSchrijf($doc),
            'evenementen' => static fn(array $doc): bool => repoEvenementenSchrijf($doc),
            'groepen' => static fn(array $doc): bool => repoGroepenSchrijf($doc),
            default => throw new InvalidArgumentException('Onbekende integriteitsstore: ' . $store),
        };
    }
    if ($writer($data) !== true) {
        throw new RuntimeException('Schrijven van integriteitsstore ' . $store . ' is mislukt.');
    }
}

/**
 * Verwijder uitsluitend exacte object-ID's uit het gevraagde relatietype.
 * Normaliseer het document hier bewust niet volledig: repair mag geen
 * historische of afwijkende data als neveneffect wijzigen.
 */
function dataIntegriteitGroepRelatiesVerwijder(array &$groepen, string $type, string $objectId): int
{
    if (!in_array($type, ['taken', 'vergaderingen', 'evenementen'], true) || $objectId === '') return 0;
    if (!isset($groepen['relaties']) || !is_array($groepen['relaties'])) return 0;

    $verwijderd = 0;
    foreach ($groepen['relaties'] as $groepId => $set) {
        if (!is_array($set) || !isset($set[$type]) || !is_array($set[$type])) continue;
        $nieuw = [];
        foreach ($set[$type] as $waarde) {
            $id = dataIntegriteitId($waarde);
            if ($id !== '' && hash_equals($objectId, $id)) {
                $verwijderd++;
                continue;
            }
            $nieuw[] = $waarde;
        }
        if (count($nieuw) !== count($set[$type])) {
            $groepen['relaties'][$groepId][$type] = array_values($nieuw);
        }
    }
    if ($verwijderd > 0) $groepen['updated'] = date('c');
    return $verwijderd;
}

function dataIntegriteitTakenOntkoppelVergadering(array &$taken, string $vergaderingId): int
{
    if ($vergaderingId === '') return 0;
    $aantal = 0;
    foreach ((array)($taken['taken'] ?? []) as $i => $taak) {
        if (!is_array($taak)) continue;
        $gekoppeld = dataIntegriteitId($taak['vergadering_id'] ?? '');
        if ($gekoppeld === '' || !hash_equals($vergaderingId, $gekoppeld)) continue;
        $taken['taken'][$i]['vergadering_id'] = '';
        $taken['taken'][$i]['vergadering_soort'] = '';
        $taken['taken'][$i]['gewijzigd'] = date('c');
        $aantal++;
    }
    return $aantal;
}

function dataIntegriteitVerwijderTaak(string $id, array $writers = []): array
{
    $id = dataIntegriteitId($id);
    if ($id === '') throw new InvalidArgumentException('Taak-ID ontbreekt.');

    return privateStoreBatchTransactie(
        ['taken', 'groepen'],
        [takenBestandPad(), groepenBestandPad()],
        static function () use ($id, $writers): array {
            $taken = repoTakenLees();
            $idx = dataIntegriteitVindIndex($taken, 'taken', $id);
            if ($idx === null) return ['gevonden' => false, 'object' => null, 'groep_relaties_verwijderd' => 0];

            $object = $taken['taken'][$idx];
            $groepen = repoGroepenLees();
            array_splice($taken['taken'], $idx, 1);
            $groepAantal = dataIntegriteitGroepRelatiesVerwijder($groepen, 'taken', $id);

            dataIntegriteitRepoSchrijf('taken', $taken, $writers);
            if ($groepAantal > 0) dataIntegriteitRepoSchrijf('groepen', $groepen, $writers);

            return ['gevonden' => true, 'object' => $object, 'groep_relaties_verwijderd' => $groepAantal];
        }
    );
}

function dataIntegriteitVerwijderVergadering(string $id, array $writers = []): array
{
    $id = dataIntegriteitId($id);
    if ($id === '') throw new InvalidArgumentException('Vergadering-ID ontbreekt.');

    return privateStoreBatchTransactie(
        ['vergaderingen', 'taken', 'groepen'],
        [vergaderingenBestandPad(), takenBestandPad(), groepenBestandPad()],
        static function () use ($id, $writers): array {
            $vergaderingen = repoVergaderingenLees();
            $idx = dataIntegriteitVindIndex($vergaderingen, 'vergaderingen', $id);
            if ($idx === null) return ['gevonden' => false, 'object' => null, 'taken_ontkoppeld' => 0, 'groep_relaties_verwijderd' => 0];

            $object = $vergaderingen['vergaderingen'][$idx];
            $taken = repoTakenLees();
            $groepen = repoGroepenLees();
            array_splice($vergaderingen['vergaderingen'], $idx, 1);
            $taakAantal = dataIntegriteitTakenOntkoppelVergadering($taken, $id);
            $groepAantal = dataIntegriteitGroepRelatiesVerwijder($groepen, 'vergaderingen', $id);

            // Primaire delete eerst; elke latere writefout moet deze write
            // aantoonbaar via de centrale batchtransactie terugrollen.
            dataIntegriteitRepoSchrijf('vergaderingen', $vergaderingen, $writers);
            if ($taakAantal > 0) dataIntegriteitRepoSchrijf('taken', $taken, $writers);
            if ($groepAantal > 0) dataIntegriteitRepoSchrijf('groepen', $groepen, $writers);

            return [
                'gevonden' => true,
                'object' => $object,
                'taken_ontkoppeld' => $taakAantal,
                'groep_relaties_verwijderd' => $groepAantal,
            ];
        }
    );
}

function dataIntegriteitVerwijderEvenement(string $id, array $writers = []): array
{
    $id = dataIntegriteitId($id);
    if ($id === '') throw new InvalidArgumentException('Evenement-ID ontbreekt.');

    return privateStoreBatchTransactie(
        ['evenementen', 'groepen'],
        [evenementBestandPad(), groepenBestandPad()],
        static function () use ($id, $writers): array {
            $evenementen = repoEvenementenLees();
            $idx = dataIntegriteitVindIndex($evenementen, 'evenementen', $id);
            if ($idx === null) return ['gevonden' => false, 'object' => null, 'groep_relaties_verwijderd' => 0];

            $object = $evenementen['evenementen'][$idx];
            $groepen = repoGroepenLees();
            array_splice($evenementen['evenementen'], $idx, 1);
            $groepAantal = dataIntegriteitGroepRelatiesVerwijder($groepen, 'evenementen', $id);

            dataIntegriteitRepoSchrijf('evenementen', $evenementen, $writers);
            if ($groepAantal > 0) dataIntegriteitRepoSchrijf('groepen', $groepen, $writers);

            return ['gevonden' => true, 'object' => $object, 'groep_relaties_verwijderd' => $groepAantal];
        }
    );
}

function dataIntegriteitDetecteerSnapshot(array $taken, array $vergaderingen, array $evenementen, array $groepen): array
{
    $geldigeTaken = dataIntegriteitIdSet($taken, 'taken');
    $geldigeVergaderingen = dataIntegriteitIdSet($vergaderingen, 'vergaderingen');
    $geldigeEvenementen = dataIntegriteitIdSet($evenementen, 'evenementen');
    $rapport = [
        'taak_vergaderingen' => [],
        'groepen' => ['taken' => [], 'vergaderingen' => [], 'evenementen' => []],
    ];

    foreach ((array)($taken['taken'] ?? []) as $taak) {
        if (!is_array($taak)) continue;
        $taakId = dataIntegriteitId($taak['id'] ?? '');
        $vergaderingId = dataIntegriteitId($taak['vergadering_id'] ?? '');
        if ($taakId !== '' && $vergaderingId !== '' && !isset($geldigeVergaderingen[$vergaderingId])) {
            $rapport['taak_vergaderingen'][$taakId . "\0" . $vergaderingId] = [
                'taak_id' => $taakId,
                'vergadering_id' => $vergaderingId,
            ];
        }
    }

    $sets = [
        'taken' => $geldigeTaken,
        'vergaderingen' => $geldigeVergaderingen,
        'evenementen' => $geldigeEvenementen,
    ];
    foreach ((array)($groepen['relaties'] ?? []) as $groepIdRuw => $relaties) {
        if (!is_array($relaties)) continue;
        $groepId = dataIntegriteitId($groepIdRuw);
        if ($groepId === '') continue;
        foreach ($sets as $type => $geldigeIds) {
            if (!isset($relaties[$type]) || !is_array($relaties[$type])) continue;
            foreach ($relaties[$type] as $waarde) {
                $objectId = dataIntegriteitId($waarde);
                if ($objectId === '' || isset($geldigeIds[$objectId])) continue;
                $rapport['groepen'][$type][$groepId . "\0" . $objectId] = [
                    'groep_id' => $groepId,
                    'object_id' => $objectId,
                ];
            }
        }
    }

    $rapport['taak_vergaderingen'] = array_values($rapport['taak_vergaderingen']);
    foreach (array_keys($rapport['groepen']) as $type) {
        $rapport['groepen'][$type] = array_values($rapport['groepen'][$type]);
    }
    $rapport['aantallen'] = [
        'taak_vergaderingen' => count($rapport['taak_vergaderingen']),
        'groep_taken' => count($rapport['groepen']['taken']),
        'groep_vergaderingen' => count($rapport['groepen']['vergaderingen']),
        'groep_evenementen' => count($rapport['groepen']['evenementen']),
    ];
    $rapport['totaal'] = array_sum($rapport['aantallen']);
    return $rapport;
}

function dataIntegriteitDetecteer(): array
{
    return dataIntegriteitDetecteerSnapshot(
        repoTakenLees(),
        repoVergaderingenLees(),
        repoEvenementenLees(),
        repoGroepenLees()
    );
}

/**
 * Conservatieve repair: alleen IDs die in de huidige snapshot ondubbelzinnig
 * naar een ontbrekend primair object wijzen worden verwijderd/ontkoppeld.
 * Geldige relaties, commissieprovenance en andere velden blijven ongemoeid.
 */
function dataIntegriteitHerstelDangling(array $writers = []): array
{
    return privateStoreBatchTransactie(
        ['taken', 'vergaderingen', 'evenementen', 'groepen'],
        [takenBestandPad(), vergaderingenBestandPad(), evenementBestandPad(), groepenBestandPad()],
        static function () use ($writers): array {
            $taken = repoTakenLees();
            $vergaderingen = repoVergaderingenLees();
            $evenementen = repoEvenementenLees();
            $groepen = repoGroepenLees();
            $voor = dataIntegriteitDetecteerSnapshot($taken, $vergaderingen, $evenementen, $groepen);

            $takenGewijzigd = 0;
            $geldigeVergaderingen = dataIntegriteitIdSet($vergaderingen, 'vergaderingen');
            foreach ((array)($taken['taken'] ?? []) as $i => $taak) {
                if (!is_array($taak)) continue;
                $taakId = dataIntegriteitId($taak['id'] ?? '');
                $vergaderingId = dataIntegriteitId($taak['vergadering_id'] ?? '');
                // Zonder eigen taak-ID is het record malformed en geen veilige
                // automatische repairkandidaat binnen finding #152.
                if ($taakId === '' || $vergaderingId === '' || isset($geldigeVergaderingen[$vergaderingId])) continue;
                $taken['taken'][$i]['vergadering_id'] = '';
                $taken['taken'][$i]['vergadering_soort'] = '';
                $taken['taken'][$i]['gewijzigd'] = date('c');
                $takenGewijzigd++;
            }

            $groepGewijzigd = 0;
            $sets = [
                'taken' => dataIntegriteitIdSet($taken, 'taken'),
                'vergaderingen' => $geldigeVergaderingen,
                'evenementen' => dataIntegriteitIdSet($evenementen, 'evenementen'),
            ];
            if (isset($groepen['relaties']) && is_array($groepen['relaties'])) {
                foreach ($groepen['relaties'] as $groepId => $relaties) {
                    if (!is_array($relaties)) continue;
                    foreach ($sets as $type => $geldigeIds) {
                        if (!isset($relaties[$type]) || !is_array($relaties[$type])) continue;
                        $nieuw = [];
                        foreach ($relaties[$type] as $waarde) {
                            $objectId = dataIntegriteitId($waarde);
                            if ($objectId !== '' && !isset($geldigeIds[$objectId])) {
                                $groepGewijzigd++;
                                continue;
                            }
                            $nieuw[] = $waarde;
                        }
                        if (count($nieuw) !== count($relaties[$type])) {
                            $groepen['relaties'][$groepId][$type] = array_values($nieuw);
                        }
                    }
                }
            }
            if ($groepGewijzigd > 0) $groepen['updated'] = date('c');

            if ($takenGewijzigd > 0) dataIntegriteitRepoSchrijf('taken', $taken, $writers);
            if ($groepGewijzigd > 0) dataIntegriteitRepoSchrijf('groepen', $groepen, $writers);

            $na = dataIntegriteitDetecteerSnapshot($taken, $vergaderingen, $evenementen, $groepen);
            return [
                'voor' => $voor,
                'hersteld' => [
                    'taak_vergaderingen' => $takenGewijzigd,
                    'groep_relaties' => $groepGewijzigd,
                ],
                'na' => $na,
            ];
        }
    );
}
