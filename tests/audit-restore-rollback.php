<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function arrCheck(bool $cond, string $label): void {
    global $ok, $fout;
    if ($cond) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

$beheer = (string) file_get_contents($root . '/beheer/backups.php');
$store = (string) file_get_contents($root . '/app/storage/tenant-backup-store.php');

$usersStart = strpos($beheer, "if (\$type === 'users') {");
$usersRollback = strpos($beheer, '$rollback = tenantBackupMaakArray($backupKey, $huidig);', $usersStart === false ? 0 : $usersStart);
$usersGuard = strpos($beheer, 'if ($rollback === null) {', $usersRollback === false ? 0 : $usersRollback);
$usersWrite = strpos($beheer, 'if (!schrijfGebruikers($usersBestand, $data)) {', $usersGuard === false ? 0 : $usersGuard);
arrCheck(
    $usersStart !== false && $usersRollback !== false && $usersGuard !== false && $usersWrite !== false
    && $usersStart < $usersRollback && $usersRollback < $usersGuard && $usersGuard < $usersWrite,
    'gebruikersrestore vereist aantoonbaar een duurzame rollback-snapshot vóór de write'
);
arrCheck(
    str_contains($beheer, 'Gebruikersherstel is afgebroken omdat de huidige accounts niet als rollback-snapshot konden worden bewaard.'),
    'gebruikersrestore faalt expliciet gesloten als rollback-snapshot niet kan worden gemaakt'
);

$assetStart = strpos($store, 'function tenantBackupHerstelAssetSnapshot');
$assetStage = strpos($store, 'tenantBackupKopieerMap($payload, $stage)', $assetStart === false ? 0 : $assetStart);
$assetSnapshot = strpos($store, '$rollbackSnapshot = tenantBackupMaakAssetSnapshot($scope);', $assetStage === false ? 0 : $assetStage);
$assetGuard = strpos($store, 'if ($rollbackSnapshot === null) {', $assetSnapshot === false ? 0 : $assetSnapshot);
$assetPark = strpos($store, '@rename($doel, $oud)', $assetGuard === false ? 0 : $assetGuard);
arrCheck(
    $assetStart !== false && $assetStage !== false && $assetSnapshot !== false && $assetGuard !== false && $assetPark !== false
    && $assetStart < $assetStage && $assetStage < $assetSnapshot && $assetSnapshot < $assetGuard && $assetGuard < $assetPark,
    'assetrestore bouwt staging en verplichte rollback-snapshot vóór de actieve assetmap wordt gemuteerd'
);
arrCheck(
    !str_contains($store, 'best-effort pre-restore snapshot')
    && str_contains($store, 'Assetherstel is afgebroken omdat de huidige assetmap niet als rollback-snapshot kon worden bewaard.'),
    'assetrestore heeft geen best-effort rollbackpad meer'
);

$swap = strpos($store, 'if (!@rename($stage, $doel)) {', $assetPark === false ? 0 : $assetPark);
$rollbackCheck = strpos($store, '$rollbackOk = !$hadDoel || @rename($oud, $doel);', $swap === false ? 0 : $swap);
$critical = strpos($store, 'CRITICAL assetrestore rollback mislukt', $rollbackCheck === false ? 0 : $rollbackCheck);
arrCheck(
    $swap !== false && $rollbackCheck !== false && $critical !== false
    && $swap < $rollbackCheck && $rollbackCheck < $critical,
    'mislukte asset-swap controleert ook het resultaat van de automatische filesystemrollback'
);
arrCheck(
    str_contains($store, 'de duurzame rollback-snapshot is bewaard'),
    'kritieke assetrollbackfout meldt dat een duurzame herstelroute behouden blijft'
);

echo "Audit restore rollback: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
