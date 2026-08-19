<?php
// ============================================================
// Sessiebeveiliging voor gewone beheeraccounts
// ============================================================
// Wordt vanuit auth.php geladen nadat $ingelogd, $isMaster en
// $huidigeGebruiker zijn bepaald.
//
// Doel:
// - een verwijderd account verliest bij het eerstvolgende verzoek toegang;
// - na een rechten- of wachtwoordwijziging kan de bestaande sessie worden
//   ingetrokken door sessie_versie in beheer-users.json te verhogen;
// - bestaande accounts en bestaande sessies van vóór deze uitbreiding
//   migreren zonder gedwongen uitlog naar versie 1.
// ============================================================

if (!$ingelogd || $isMaster) {
    return;
}

$sessionAccount = null;
foreach (laadGebruikers($usersBestand) as $sessionGebruiker) {
    if (isset($sessionGebruiker['gebruikersnaam'])
        && strcasecmp((string)$sessionGebruiker['gebruikersnaam'], (string)$huidigeGebruiker) === 0) {
        $sessionAccount = $sessionGebruiker;
        break;
    }
}

// Geen record meer = account verwijderd. Laat de legacy-rechtenfallback
// nooit op een verweesde sessie los.
if (!is_array($sessionAccount)) {
    unset($_SESSION['gebruiker'], $_SESSION['is_master'], $_SESSION['user_session_version']);
    $ingelogd = false;
    $huidigeGebruiker = '';
    $isMaster = false;
    return;
}

$actueleVersie = max(1, (int)($sessionAccount['sessie_versie'] ?? 1));

// Een bestaande sessie van vóór invoering van sessieversies krijgt éénmalig
// de actuele versie. Nieuwe wijzigingen verhogen daarna de versie in het
// gebruikersrecord, waardoor deze vergelijking de oude sessie intrekt.
if (!isset($_SESSION['user_session_version'])) {
    $_SESSION['user_session_version'] = $actueleVersie;
    return;
}

if ((int)$_SESSION['user_session_version'] !== $actueleVersie) {
    unset($_SESSION['gebruiker'], $_SESSION['is_master'], $_SESSION['user_session_version']);
    $ingelogd = false;
    $huidigeGebruiker = '';
    $isMaster = false;
}
