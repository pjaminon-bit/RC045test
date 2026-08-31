<?php
require_once dirname(__DIR__) . '/app/auth-restore.php';

function arsvAssert(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, "FOUT: {$message}\n");
        exit(1);
    }
}

$huidig = [
    ['gebruikersnaam' => 'Alice', 'sessie_versie' => 41],
    ['gebruikersnaam' => 'bob', 'sessie_versie' => 9],
];
$backup = [
    ['gebruikersnaam' => 'alice', 'sessie_versie' => 7, 'hash' => 'x'],
    ['gebruikersnaam' => 'BOB', 'sessie_versie' => 9, 'hash' => 'y'],
    ['gebruikersnaam' => 'nieuw', 'hash' => 'z'],
];

$hersteld = authRestoreRoteerSessieversies($backup, $huidig, 1700000000);
arsvAssert(count($hersteld) === 3, 'Restore mag geen accounts verliezen.');
arsvAssert((int)$hersteld[0]['sessie_versie'] !== 7, 'Snapshotversie van Alice mag niet terugkeren.');
arsvAssert((int)$hersteld[0]['sessie_versie'] !== 41, 'Actuele versie van Alice mag niet behouden blijven.');
arsvAssert((int)$hersteld[1]['sessie_versie'] !== 9, 'Bob moet ook bij case-insensitive match een nieuwe sessieversie krijgen.');
arsvAssert((int)$hersteld[2]['sessie_versie'] > 0, 'Een hersteld account zonder sessieversie moet een geldige nieuwe versie krijgen.');
arsvAssert(($hersteld[0]['hash'] ?? '') === 'x' && ($hersteld[2]['hash'] ?? '') === 'z', 'Andere accountvelden mogen niet veranderen.');

$backupPagina = file_get_contents(dirname(__DIR__) . '/beheer/backups.php');
arsvAssert(is_string($backupPagina), 'beheer/backups.php kon niet worden gelezen.');
arsvAssert(str_contains($backupPagina, "require_once dirname(__DIR__) . '/app/auth-restore.php';"), 'Back-upbeheer moet de restore-hardening laden.');
arsvAssert(substr_count($backupPagina, 'authRestoreRoteerSessieversies(') >= 2, 'Zowel tenant- als standalone gebruikersrestore moeten sessieversies roteren.');

echo "audit-user-restore-session-version: OK\n";
