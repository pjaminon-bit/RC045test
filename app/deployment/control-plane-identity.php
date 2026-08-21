<?php
// Fase 5.1.1 — pure NSS identity checks voor de control-plane system account.
// Deze helper muteert niets en is daardoor direct testbaar met gesimuleerde
// passwd/group records. De root-installer gebruikt exact dezelfde checks op
// volledige getent-resultaten vóór en na accountcreatie.

function control511IntField(array $record, int $index, string $label): int
{
    $value = $record[$index] ?? null;
    if (!is_string($value) || !ctype_digit($value)) {
        throw new RuntimeException($label . ' bevat geen geldige numerieke identity.');
    }
    return (int)$value;
}

function control511GroupIdentity(string $user, string $group, array $groupRecords, array $passwdRecords): int
{
    $matches = array_values(array_filter($groupRecords, static fn(array $r): bool => (string)($r[0] ?? '') === $group));
    if (count($matches) !== 1) {
        throw new RuntimeException('Control-plane groep moet exact één NSS-record hebben.');
    }
    $target = $matches[0];
    $gid = control511IntField($target, 2, 'Control-plane groep');
    if (trim((string)($target[3] ?? '')) !== '') {
        throw new RuntimeException('Control-plane groep mag geen expliciete groepsleden bevatten.');
    }
    foreach ($groupRecords as $record) {
        $name = (string)($record[0] ?? '');
        $recordGid = isset($record[2]) && is_string($record[2]) && ctype_digit($record[2]) ? (int)$record[2] : -1;
        if ($recordGid === $gid && $name !== $group) {
            throw new RuntimeException('Control-plane GID wordt ook door een andere groepsnaam gebruikt.');
        }
    }
    foreach ($passwdRecords as $record) {
        $name = (string)($record[0] ?? '');
        $primaryGid = isset($record[3]) && is_string($record[3]) && ctype_digit($record[3]) ? (int)$record[3] : -1;
        if ($primaryGid === $gid && $name !== $user) {
            throw new RuntimeException('Control-plane GID is primary group van een andere account.');
        }
    }
    return $gid;
}

function control511UserIdentity(string $user, string $group, array $passwdRecords, array $groupRecords): array
{
    $gid = control511GroupIdentity($user, $group, $groupRecords, $passwdRecords);
    $matches = array_values(array_filter($passwdRecords, static fn(array $r): bool => (string)($r[0] ?? '') === $user));
    if (count($matches) !== 1) {
        throw new RuntimeException('Control-plane user moet exact één NSS-record hebben.');
    }
    $target = $matches[0];
    if (count($target) < 7) throw new RuntimeException('Control-plane passwd-record is onvolledig.');
    $uid = control511IntField($target, 2, 'Control-plane user');
    $primaryGid = control511IntField($target, 3, 'Control-plane user');
    if ($primaryGid !== $gid) throw new RuntimeException('Control-plane user heeft niet de unieke verwachte primary group.');
    if ((string)$target[5] !== '/nonexistent' || (string)$target[6] !== '/usr/sbin/nologin') {
        throw new RuntimeException('Control-plane user heeft afwijkende home of login shell.');
    }
    foreach ($passwdRecords as $record) {
        $name = (string)($record[0] ?? '');
        $recordUid = isset($record[2]) && is_string($record[2]) && ctype_digit($record[2]) ? (int)$record[2] : -1;
        if ($recordUid === $uid && $name !== $user) {
            throw new RuntimeException('Control-plane UID wordt ook door een andere account gebruikt.');
        }
    }
    return ['uid' => $uid, 'gid' => $gid];
}
