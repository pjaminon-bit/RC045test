<?php
// ============================================================
// Sessiebeveiliging voor beheeraccounts
// ============================================================
// Wordt vanuit auth.php geladen nadat $ingelogd, $isMaster en
// $huidigeGebruiker zijn bepaald.
//
// Doel:
// - iedere sessie hoort expliciet bij één tenant én één installatie;
// - een sessie van PROD/DEV of tenant A/B wordt fail-closed verworpen;
// - een verwijderd of geblokkeerd account verliest bij het eerstvolgende
//   verzoek toegang;
// - na een rechten- of wachtwoordwijziging kan de bestaande sessie worden
//   ingetrokken door sessie_versie in beheer-users.json te verhogen;
// - beheerpagina's worden server-side tegen hetzelfde centrale
//   capabilitycontract gecontroleerd als de beheer-shell;
// - beheerrequests vereisen minimaal een geldig capabilities- of tabs-profiel;
// - externe tenants behouden tijdens de autorisatiemigratie de bestaande,
//   strengere eis van een expliciet legacy-tabprofiel.
// ============================================================

require_once __DIR__ . '/auth-session-tenant.php';
require_once __DIR__ . '/auth-beheer-guard.php';

$authSessionTenantKey = authSessionTenantSleutel($authSiteConfig ?? []);
$authSessionInstallatieBinding = (string)($authPaden['session_binding'] ?? '');
$authSessionTenantOk = authSessionTenantBewaak(
    $authSessionTenantKey,
    $authSessionInstallatieBinding,
    $csrfToken
);

if (!$authSessionTenantOk) {
    $ingelogd = false;
    $huidigeGebruiker = '';
    $isMaster = false;
    $inlogFout = 'Je sessie hoort niet bij deze installatie. Log opnieuw in.';
    return;
}

if (!$ingelogd) {
    return;
}

$authBeheerContract = authBeheerRouteContract();

if ($isMaster) {
    authBeheerEndpointHandhaaf($authBeheerContract);
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

$heeftExplicietTabprofiel = array_key_exists('tabs', $sessionAccount) && is_array($sessionAccount['tabs']);
$heeftGeldigRechtenprofiel = authBeheerRechtenprofielGeldig($sessionAccount);
$beheerRequest = $authBeheerContract !== null;
$mistVereistProfiel = (!empty($authPaden['tenant_private']) && !$heeftExplicietTabprofiel)
    || ($beheerRequest && !$heeftGeldigRechtenprofiel);
if ($mistVereistProfiel) {
    unset($_SESSION['gebruiker'], $_SESSION['is_master'], $_SESSION['user_session_version']);
    $ingelogd = false;
    $huidigeGebruiker = '';
    $isMaster = false;
    $inlogFout = 'Dit account heeft nog geen expliciet rechtenprofiel voor deze vereniging. Laat de hoofdbeheerder het account migreren en rechten toekennen.';
    return;
}

$actueleVersie = max(1, (int)($sessionAccount['sessie_versie'] ?? 1));
if (!isset($_SESSION['user_session_version'])) {
    $_SESSION['user_session_version'] = 1;
}

if ((int)$_SESSION['user_session_version'] !== $actueleVersie) {
    unset($_SESSION['gebruiker'], $_SESSION['is_master'], $_SESSION['user_session_version']);
    $ingelogd = false;
    $huidigeGebruiker = '';
    $isMaster = false;
    $inlogFout = 'Je sessie is beëindigd. Log opnieuw in.';
    return;
}

authBeheerEndpointHandhaaf($authBeheerContract);
