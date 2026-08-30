<?php
require_once __DIR__ . '/authenticated-e2e-fixture.php';

function e2e511MarkerRecord(array $record, string $tenant): bool
{
    return ($record['e2e_fixture'] ?? '') === e2e510Marker()
        && ($record['e2e_tenant'] ?? '') === $tenant;
}

function e2e511ContributionId(string $tenant, ?int $year = null): string
{
    $ids = e2e510Ids($tenant);
    $year = $year ?? (int)gmdate('Y');
    return 'contrib_e2e_' . substr(hash('sha256', $ids['member'] . '|' . $year), 0, 16);
}

function e2e511CanonicalContributionId(string $tenant, int $year): string
{
    $ids = e2e510Ids($tenant);
    return 'contrib_' . substr(hash('sha256', $ids['member'] . '|' . $year), 0, 24);
}

function e2e511MarkedMemberPresent(array $leden, string $tenant): bool
{
    $ids = e2e510Ids($tenant);
    foreach ((array)($leden['leden'] ?? []) as $record) {
        if (is_array($record) && ($record['id'] ?? '') === $ids['member'] && e2e511MarkerRecord($record, $tenant)) return true;
    }
    return false;
}

function e2e511ContributionFixtureRecord(array $record, string $tenant, bool $allowLegacy = false): bool
{
    $ids = e2e510Ids($tenant);
    if (($record['lid_id'] ?? '') !== $ids['member']) return false;
    $year = (int)($record['jaar'] ?? 0);
    if ($year < 2000 || $year > 2099) return false;
    $id = (string)($record['id'] ?? '');
    $canonical = e2e511CanonicalContributionId($tenant, $year);
    $builder = e2e511ContributionId($tenant, $year);
    if ($id !== '' && $id !== $canonical && $id !== $builder) return false;

    $opmerking = (string)($record['opmerking'] ?? '');
    if (hash_equals('Authenticated VPS E2E fixture ' . e2e510DurableMarker($tenant), $opmerking)) return true;
    if (!$allowLegacy || $year !== (int)gmdate('Y') || $opmerking !== 'Authenticated VPS E2E fixture') return false;

    return ($record['status'] ?? '') === 'deels_betaald'
        && trim((string)($record['lidmaatschap_type'] ?? '')) === ''
        && abs((float)($record['verschuldigd_bedrag'] ?? -1) - 100.0) < 0.001
        && abs((float)($record['inschrijfgeld'] ?? -1) - 0.0) < 0.001
        && abs((float)($record['betaald_bedrag'] ?? -1) - 25.0) < 0.001
        && trim((string)($record['vrijstelling_reden'] ?? '')) === '';
}

function e2e511GroupFixtureRecord(array $record, string $tenant, bool $allowLegacy = false): bool
{
    $ids = e2e510Ids($tenant);
    if (($record['id'] ?? '') !== $ids['group']) return false;
    $omschrijving = (string)($record['omschrijving'] ?? '');
    if (hash_equals('Dedicated synthetische VPS-testfixture ' . e2e510DurableMarker($tenant), $omschrijving)) return true;
    if (!$allowLegacy || $omschrijving !== 'Dedicated synthetische VPS-testfixture') return false;
    if (($record['type'] ?? '') !== 'commissie' || ($record['naam'] ?? '') !== 'E2E Testcommissie') return false;
    if (($record['doel'] ?? '') !== 'Authenticated ledenportaal end-to-end bewijzen' || ($record['status'] ?? '') !== 'actief') return false;
    foreach ((array)($record['leden'] ?? []) as $member) {
        if (!is_array($member) || ($member['lid_id'] ?? '') !== $ids['member']) continue;
        if (in_array('lid', (array)($member['rollen'] ?? []), true) && trim((string)($member['tot'] ?? '')) === '') return true;
    }
    return false;
}

function e2e511AssertReservedSlots(array $leden, array $contributies, array $groepen, array $vergaderingen, array $taken, string $tenant): void
{
    $ids = e2e510Ids($tenant);
    $legacyAllowed = false;
    foreach ((array)($leden['leden'] ?? []) as $record) {
        if (!is_array($record) || ($record['id'] ?? '') !== $ids['member']) continue;
        if (!e2e511MarkerRecord($record, $tenant)) throw new RuntimeException('Gereserveerde E2E-lid-ID botst met niet-fixture data.');
        $legacyAllowed = true;
    }
    $year = (int)gmdate('Y');
    foreach ((array)($contributies['regels'] ?? []) as $record) {
        if (!is_array($record)) continue;
        $isReserved = (($record['id'] ?? '') === e2e511ContributionId($tenant, $year))
            || (($record['id'] ?? '') === e2e511CanonicalContributionId($tenant, $year))
            || (($record['lid_id'] ?? '') === $ids['member'] && (int)($record['jaar'] ?? 0) === $year);
        if ($isReserved && !e2e511ContributionFixtureRecord($record, $tenant, $legacyAllowed)) throw new RuntimeException('Gereserveerde E2E-contributiesleutel botst met niet-fixture data.');
    }
    foreach ((array)($groepen['groepen'] ?? []) as $record) {
        if (!is_array($record) || ($record['id'] ?? '') !== $ids['group']) continue;
        if (!e2e511GroupFixtureRecord($record, $tenant, $legacyAllowed)) throw new RuntimeException('Gereserveerde E2E-groep-ID botst met niet-fixture data.');
    }
    foreach ([
        ['doc' => $vergaderingen, 'key' => 'vergaderingen', 'id' => $ids['meeting'], 'label' => 'vergadering'],
        ['doc' => $taken, 'key' => 'taken', 'id' => $ids['task'], 'label' => 'taak'],
    ] as $slot) {
        foreach ((array)($slot['doc'][$slot['key']] ?? []) as $record) {
            if (!is_array($record) || ($record['id'] ?? '') !== $slot['id']) continue;
            if (!e2e511MarkerRecord($record, $tenant)) throw new RuntimeException('Gereserveerde E2E-' . $slot['label'] . '-ID botst met niet-fixture data.');
        }
    }
}

function e2e511MarkTargetRecords(array $doc, string $key, callable $isTarget, string $tenant): array
{
    $records = [];
    foreach ((array)($doc[$key] ?? []) as $record) {
        if (is_array($record) && $isTarget($record)) {
            $record['e2e_fixture'] = e2e510Marker();
            $record['e2e_tenant'] = $tenant;
        }
        $records[] = $record;
    }
    $doc[$key] = $records;
    return $doc;
}

function e2e511MarkDocuments(array $leden, array $contributies, array $groepen, array $vergaderingen, array $taken, string $tenant): array
{
    $ids = e2e510Ids($tenant);
    $year = (int)gmdate('Y');
    $leden = e2e511MarkTargetRecords($leden, 'leden', fn(array $r): bool => ($r['id'] ?? '') === $ids['member'], $tenant);
    $contributies = e2e511MarkTargetRecords($contributies, 'regels', fn(array $r): bool => ($r['lid_id'] ?? '') === $ids['member'] && (int)($r['jaar'] ?? 0) === $year, $tenant);
    $groepen = e2e511MarkTargetRecords($groepen, 'groepen', fn(array $r): bool => ($r['id'] ?? '') === $ids['group'], $tenant);
    $vergaderingen = e2e511MarkTargetRecords($vergaderingen, 'vergaderingen', fn(array $r): bool => ($r['id'] ?? '') === $ids['meeting'], $tenant);
    $taken = e2e511MarkTargetRecords($taken, 'taken', fn(array $r): bool => ($r['id'] ?? '') === $ids['task'], $tenant);
    return [$leden, $contributies, $groepen, $vergaderingen, $taken];
}

function e2e511CleanupAuth(array $users, string $tenant): array
{
    $ids = e2e510Ids($tenant);
    $out = [];
    foreach ($users as $record) {
        if (!is_array($record)) throw new RuntimeException('Authstore bevat een ongeldig record.');
        $id = (string)($record['id'] ?? '');
        $isReserved = $id === $ids['admin_user'] || $id === $ids['member_user'];
        if ($isReserved && !e2e511MarkerRecord($record, $tenant)) throw new RuntimeException('Cleanup weigert een gereserveerd niet-fixture authrecord te verwijderen.');
        if (e2e511MarkerRecord($record, $tenant)) continue;
        $out[] = $record;
    }
    return array_values($out);
}

function e2e511CleanupDocument(array $doc, string $key, string $tenant): array
{
    $out = [];
    foreach ((array)($doc[$key] ?? []) as $record) {
        if (is_array($record) && e2e511MarkerRecord($record, $tenant)) continue;
        $out[] = $record;
    }
    $doc[$key] = array_values($out);
    return $doc;
}

function e2e511CleanupMatching(array $doc, string $key, callable $isFixture): array
{
    $out = [];
    foreach ((array)($doc[$key] ?? []) as $record) {
        if (is_array($record) && $isFixture($record)) continue;
        $out[] = $record;
    }
    $doc[$key] = array_values($out);
    return $doc;
}

function e2e511CleanupDocuments(array $leden, array $contributies, array $groepen, array $vergaderingen, array $taken, string $tenant): array
{
    $legacyAllowed = e2e511MarkedMemberPresent($leden, $tenant);
    return [
        e2e511CleanupDocument($leden, 'leden', $tenant),
        e2e511CleanupMatching($contributies, 'regels', fn(array $r): bool => e2e511ContributionFixtureRecord($r, $tenant, $legacyAllowed)),
        e2e511CleanupMatching($groepen, 'groepen', fn(array $r): bool => e2e511GroupFixtureRecord($r, $tenant, $legacyAllowed)),
        e2e511CleanupDocument($vergaderingen, 'vergaderingen', $tenant),
        e2e511CleanupDocument($taken, 'taken', $tenant),
    ];
}

function e2e511CountFixture(array $records, string $tenant): int
{
    $count = 0;
    foreach ($records as $record) if (is_array($record) && e2e511MarkerRecord($record, $tenant)) $count++;
    return $count;
}

function e2e511CountMatching(array $records, callable $isFixture): int
{
    $count = 0;
    foreach ($records as $record) if (is_array($record) && $isFixture($record)) $count++;
    return $count;
}

function e2e511CountAll(array $users, array $leden, array $contributies, array $groepen, array $vergaderingen, array $taken, string $tenant): int
{
    $legacyAllowed = e2e511MarkedMemberPresent($leden, $tenant);
    return e2e511CountFixture($users, $tenant)
        + e2e511CountFixture((array)($leden['leden'] ?? []), $tenant)
        + e2e511CountMatching((array)($contributies['regels'] ?? []), fn(array $r): bool => e2e511ContributionFixtureRecord($r, $tenant, $legacyAllowed))
        + e2e511CountMatching((array)($groepen['groepen'] ?? []), fn(array $r): bool => e2e511GroupFixtureRecord($r, $tenant, $legacyAllowed))
        + e2e511CountFixture((array)($vergaderingen['vergaderingen'] ?? []), $tenant)
        + e2e511CountFixture((array)($taken['taken'] ?? []), $tenant);
}
