<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function f278(bool $conditie, string $label): void
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

$data = ['leden' => [[
    'id' => 'lid_actief',
    'status' => 'actief',
    'user_id' => 'usr_actief001',
    'beheer_account' => 'actief-account',
    'gearchiveerd_op' => '',
    'gearchiveerd_door' => '',
]]];
$archief = ledenServiceArchiveer($data, 'lid_actief', 'Bestuur');
f278(is_array($archief), 'actief lid kan worden gearchiveerd');
f278(($archief['status'] ?? '') === 'opgezegd', 'archiveren zet tijdelijke domeinstatus op opgezegd');
f278(($archief['status_voor_archief'] ?? '') === 'actief', 'archiveren bewaart oorspronkelijke actieve status');
f278(($archief['user_id'] ?? null) === '' && ($archief['beheer_account'] ?? null) === '', 'archiveren verbreekt accountkoppelingen');

$nogmaals = ledenServiceArchiveer($data, 'lid_actief', 'Andere beheerder');
f278(($nogmaals['status_voor_archief'] ?? '') === 'actief', 'herhaald archiveren overschrijft oorspronkelijke status niet');

$herstel = ledenServiceHerstelArchief($data, 'lid_actief');
f278(is_array($herstel), 'gearchiveerd lid kan worden hersteld');
f278(($herstel['status'] ?? '') === 'actief', 'herstel zet actieve oorspronkelijke status terug');
f278(!array_key_exists('status_voor_archief', $herstel), 'herstel ruimt tijdelijke statusmetadata op');
f278(($herstel['gearchiveerd_op'] ?? null) === '' && ($herstel['gearchiveerd_door'] ?? null) === '', 'herstel ruimt archiefmarkering op');
f278(($herstel['user_id'] ?? null) === '' && ($herstel['beheer_account'] ?? null) === '', 'accountkoppelingen blijven na herstel bewust leeg');

$afwijkend = ['leden' => [[
    'id' => 'lid_afwijkend',
    'status' => 'bijzonder',
    'user_id' => '',
    'beheer_account' => '',
    'gearchiveerd_op' => '',
]]];
ledenServiceArchiveer($afwijkend, 'lid_afwijkend', 'Bestuur');
$afwijkendHerstel = ledenServiceHerstelArchief($afwijkend, 'lid_afwijkend');
f278(($afwijkendHerstel['status'] ?? '') === 'bijzonder', 'herstel bewaart een afwijkende oorspronkelijke status zonder die naar actief te normaliseren');

$legacy = ['leden' => [[
    'id' => 'lid_legacy',
    'status' => 'opgezegd',
    'user_id' => '',
    'beheer_account' => '',
    'gearchiveerd_op' => '2026-01-01T12:00:00+01:00',
    'gearchiveerd_door' => 'Bestuur',
]]];
$legacyHerstel = ledenServiceHerstelArchief($legacy, 'lid_legacy');
f278(($legacyHerstel['status'] ?? '') === 'actief', 'legacy archiefrecord zonder statusmetadata krijgt veilige herstelstatus actief');
f278(!array_key_exists('status_voor_archief', $legacyHerstel), 'legacy herstel introduceert geen blijvende tijdelijke statusmetadata');

echo "Functioneel #278 ledenarchiefstatus: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
