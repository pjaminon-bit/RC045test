<?php
// ============================================================
// Sessieguard voor gewone beheeraccounts
// ============================================================
// Wordt vanuit auth.php geladen nadat $ingelogd/$isMaster zijn bepaald.
// Een account kan daardoor centraal worden geblokkeerd en bestaande sessies
// kunnen worden ingetrokken door sessie_versie in beheer-users.json te verhogen.
// Oude accounts/sessies zonder sessie_versie migreren stil naar versie 1.
// ============================================================

if (!isset($ingelogd, $isMaster, $huidigeGebruiker, $usersBestand) || !$ingelogd || $isMaster) {
    return;
}

$guardRecord = null;
foreach (laadGebruikers($usersBestand) as $guardGebruiker) {
    if (isset($guardGebruiker['gebruikersnaam']) && strcasecmp((string)$guardGebruiker['gebruikersnaam'], (string)$huidigeGebruiker) === 0) {
        $guardRecord = $guardGebruiker;
        break;
    }
}

$guardActief = is_array($guardRecord) && (($guardRecord['actief'] ?? true) !== false);
$guardVersie = is_array($guardRecord) ? max(1, (int)($guardRecord['sessie_versie'] ?? 1)) : 0;
$sessionVersie = isset($_SESSION['user_session_version']) ? (int)$_SESSION['user_session_version'] : null;

// Compatibiliteit bij eerste request na invoering van deze guard: bestaande
// geldige sessies krijgen éénmalig de huidige versie. Vanaf dat moment worden
// wijzigingen aan het account direct afgedwongen.
if ($guardActief && $sessionVersie === null) {
    $_SESSION['user_session_version'] = $guardVersie;
    $sessionVersie = $guardVersie;
}

if (!$guardActief || $guardVersie < 1 || $sessionVersie !== $guardVersie) {
    unset($_SESSION['gebruiker'], $_SESSION['is_master'], $_SESSION['user_session_version']);
    $ingelogd = false;
    $huidigeGebruiker = '';
    $isMaster = false;
}
