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
// - bestaande accounts zonder sessie_versie gelden als versie 1;
// - bestaande sessies van vóór deze uitbreiding gelden óók als versie 1.
//   Daardoor kan zo'n oude sessie nooit een inmiddels verhoogde versie
//   "overnemen" nadat rechten of wachtwoord al zijn gewijzigd.
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

// Sessies die al bestonden vóór invoering van dit veld zijn versie 1.
// Belangrijk: neem hier NIET de actuele accountversie over. Als een beheerder
// het account na deployment al heeft gewijzigd en het record daardoor op
// versie 2+ staat, moet de oude sessie juist ongeldig zijn.
if (!isset($_SESSION['user_session_version'])) {
    $_SESSION['user_session_version'] = 1;
}

if ((int)$_SESSION['user_session_version'] !== $actueleVersie) {
    unset($_SESSION['gebruiker'], $_SESSION['is_master'], $_SESSION['user_session_version']);
    $ingelogd = false;
    $huidigeGebruiker = '';
    $isMaster = false;
}
