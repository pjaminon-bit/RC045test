<?php
// ============================================================
// Sessiebeveiliging voor beheeraccounts
// ============================================================
// Wordt vanuit auth.php geladen nadat $ingelogd, $isMaster en
// $huidigeGebruiker zijn bepaald.
//
// Doel:
// - iedere sessie hoort expliciet bij precies één tenant;
// - een sessie van tenant A wordt bij tenant B fail-closed verworpen;
// - een verwijderd of geblokkeerd account verliest bij het eerstvolgende
//   verzoek toegang;
// - na een rechten- of wachtwoordwijziging kan de bestaande sessie worden
//   ingetrokken door sessie_versie in beheer-users.json te verhogen;
// - bestaande accounts zonder sessie_versie gelden als versie 1;
// - bestaande standalone RC045-sessies zonder tenant_key worden eenmalig
//   compatibel aan RC045 gebonden;
// - een externe tenant accepteert nooit een beheeraccount zonder expliciet
//   tabs-array. Zolang legacy beheerpagina's authRechten() gebruiken is dit
//   veld de fail-closed brug tussen capability- en tabautorisatie.
// ============================================================

require_once __DIR__ . '/auth-session-tenant.php';

$authSessionTenantKey = authSessionTenantSleutel($authSiteConfig ?? []);
$authSessionTenantOk = authSessionTenantBewaak(
    $authSessionTenantKey,
    !empty($authPaden['tenant_private']),
    $csrfToken
);

if (!$authSessionTenantOk) {
    // De helper heeft de vreemde sessie met session_abort() losgelaten en een
    // volledig schone sessie voor deze tenant gestart. Geen authstate uit de
    // oorspronkelijke sessie mag daarna nog door auth.php gebruikt worden.
    $ingelogd = false;
    $huidigeGebruiker = '';
    $isMaster = false;
    $inlogFout = 'Je sessie hoort niet bij deze vereniging. Log opnieuw in.';
    return;
}

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

// Geen record meer = account verwijderd. Een expliciet geblokkeerd account
// wordt op exact dezelfde fail-closed manier behandeld. Oude accounts zonder
// 'actief'-veld blijven voor compatibiliteit actief.
$accountActief = is_array($sessionAccount) && (($sessionAccount['actief'] ?? true) !== false);
if (!$accountActief) {
    unset($_SESSION['gebruiker'], $_SESSION['is_master'], $_SESSION['user_session_version']);
    $ingelogd = false;
    $huidigeGebruiker = '';
    $isMaster = false;
    $inlogFout = is_array($sessionAccount)
        ? 'Dit account is tijdelijk niet beschikbaar. Neem contact op met de beheerder.'
        : '';
    return;
}

// Op een externe tenant mag de oude standalone-terugval "geen tabs-array =
// brede toegang" nooit gelden. Ook een capabilities-only record is voorlopig
// onvoldoende: oudere beheerpagina's lezen nog rechtstreeks het tabs-profiel.
// De migratiehelper schrijft voor een veilig gemigreerd account altijd zowel
// capabilities als tabs, waarbij een expliciet leeg profiel gewoon geldig is.
$heeftExplicietTabprofiel = array_key_exists('tabs', $sessionAccount)
    && is_array($sessionAccount['tabs']);
if (!empty($authPaden['tenant_private']) && !$heeftExplicietTabprofiel) {
    unset($_SESSION['gebruiker'], $_SESSION['is_master'], $_SESSION['user_session_version']);
    $ingelogd = false;
    $huidigeGebruiker = '';
    $isMaster = false;
    $inlogFout = 'Dit account heeft nog geen expliciet rechtenprofiel voor deze vereniging. Laat de hoofdbeheerder het account migreren en rechten toekennen.';
    return;
}

$actueleVersie = max(1, (int)($sessionAccount['sessie_versie'] ?? 1));

// Sessies die al bestonden vóór invoering van dit veld zijn versie 1.
// Neem hier nooit de actuele accountversie over: na een beveiligingswijziging
// moet een oude sessie juist ongeldig worden.
if (!isset($_SESSION['user_session_version'])) {
    $_SESSION['user_session_version'] = 1;
}

if ((int)$_SESSION['user_session_version'] !== $actueleVersie) {
    unset($_SESSION['gebruiker'], $_SESSION['is_master'], $_SESSION['user_session_version']);
    $ingelogd = false;
    $huidigeGebruiker = '';
    $isMaster = false;
    $inlogFout = 'Je sessie is beëindigd. Log opnieuw in.';
}
