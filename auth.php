<?php
// ============================================================
// RC045 auth: inloggen, sessie, logboek en rechten
// ============================================================
// Gedeelde inlogafhandeling voor de afgeschermde pagina's van de site.
// Dit bestand stond tot nu toe verspreid door beheer.php; het is er
// ongewijzigd uitgehaald zodat een tweede pagina (leden.php) straks
// exact dezelfde sessie, dezelfde gebruikers en hetzelfde rechtenmodel
// gebruikt, in plaats van een tweede inlogsysteem ernaast.
//
// Gebruik bovenin een afgeschermde pagina, vóór elke uitvoer:
//
//   require_once __DIR__ . '/auth.php';
//
// Daarna zijn beschikbaar:
//   $configOk         - beheer-config.php aanwezig en ingevuld
//   $ingelogd         - true als er iemand is ingelogd
//   $huidigeGebruiker - inlognaam van die persoon
//   $isMaster         - ingelogd met het beheerderswachtwoord (mag alles)
//   $csrfToken        - verplicht mee te sturen in elk formulier
//   $inlogFout        - foutmelding voor het inlogscherm ('' = geen)
//   $melding / $meldingType - flash-meldingen na een Post-Redirect-Get
//
// Inloggen en uitloggen worden hier al afgehandeld (POST met
// formulier=inloggen / formulier=uitloggen). De redirect gaat terug naar
// de pagina die dit bestand insluit, niet naar een vaste beheer.php, zodat
// hetzelfde formulier op elke afgeschermde pagina werkt.
//
// Bestanden (alle drie server-only: niet in GitHub, afgeschermd in .htaccess):
//   beheer-config.php          - hash van het beheerderswachtwoord, handmatig via FTP
//   beheer-users.json          - gebruikers met wachtwoord-hash
//   beheer-log.json            - activiteitenlogboek
//   beheer-login-pogingen.json - teller voor de lockout
// ============================================================

date_default_timezone_set('Europe/Amsterdam');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
// Voorkomt dat een afgeschermde pagina in een iframe op een andere site
// getoond kan worden (clickjacking): X-Frame-Options voor oudere browsers,
// de CSP-regel is de moderne vervanger. Beide beïnvloeden alleen framing,
// niet de eigen inline <script>/<style> die deze pagina's gebruiken.
header('X-Frame-Options: DENY');
header("Content-Security-Policy: frame-ancestors 'none'");

// De ledenadministratie bepaalt mede de rechten (zie authRechten hieronder):
// wie daar een bestuursfunctie heeft, krijgt de bestuursonderdelen erbij.
require_once __DIR__ . '/leden-opslag.php';

// ===== Sessie: een week ingelogd blijven, niet halverwege een lang formulier uitloggen =====
$sessieduur = 60 * 60 * 24 * 7;
ini_set('session.gc_maxlifetime', (string) $sessieduur);
// Weiger een sessie-ID dat PHP niet zelf heeft uitgegeven. Zonder dit
// accepteert PHP elk ID dat in de cookie staat en maakt daar een lege sessie
// mee aan, waardoor een ID dat na het uitloggen in de browser is blijven
// hangen (of door een ander is opgedrongen) eindeloos blijft leven. Moet
// vóór session_start() staan om effect te hebben.
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
  'lifetime' => $sessieduur,
  'path' => '/',
  // Hard op true, niet afgeleid uit $_SERVER['HTTPS']: Strato handelt de
  // beveiligde verbinding af voordat PHP aan de beurt is, waardoor die
  // variabele ook bij een https-bezoek leeg blijft en de cookie stilzwijgend
  // zonder Secure-vlag verstuurd zou worden. HTTPS wordt in .htaccess
  // afgedwongen, dus er is geen http-pad meer waarover deze cookie zou
  // moeten reizen. Let op: hierdoor werkt inloggen over http niet meer, de
  // browser stuurt de cookie dan simpelweg niet mee.
  'secure' => true,
  'httponly' => true,
  'samesite' => 'Lax',
]);
session_start();

// ===== CSRF-token: één per sessie, verplicht veld in elk formulier =====
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf'];

// Geeft true als het meegestuurde csrf-veld bij een POST klopt met de sessie.
function csrfOk() {
  return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

// De pagina waar een redirect na in- of uitloggen naartoe moet: het script
// dat dit bestand insluit. Stond hier eerder hard als "beheer.php", waardoor
// een tweede afgeschermde pagina na het inloggen in beheer zou belanden.
function authHuidigePagina() {
  $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
  return $script !== '' ? $script : 'beheer.php';
}

$configPad    = __DIR__ . '/beheer-config.php';
$usersBestand = __DIR__ . '/beheer-users.json';
$logBestand   = __DIR__ . '/beheer-log.json';
$loginPogingenBestand = __DIR__ . '/beheer-login-pogingen.json';

// Lockout bij te veel mislukte inlogpogingen. Er gelden twee grenzen binnen
// hetzelfde venster: per gebruikersnaam en per bron-IP. De gebruikersnaam-
// grens remt gericht raden op één account af; de ruimere IP-grens voorkomt
// dat één bron eindeloos verschillende gebruikersnamen probeert. De teller
// wordt onder een apart flock-slot gelezen én gewijzigd, zodat parallelle
// verzoeken geen mislukte pogingen meer kunnen verliezen.
$loginLockoutVenster   = 15 * 60;
$loginLockoutDrempel   = 5;
$loginLockoutIpDrempel = 20;

// Automatische back-up van de databestanden: vlak voordat schrijfJson() of
// schrijfGebruikers() een bestand overschrijft, gaat er eerst een
// tijdgestempelde kopie naar data-backups/. Zo is een verkeerde opslag- of
// bugactie altijd terug te draaien. Bewaartermijn gelijk aan het logboek (90
// dagen), met een hardstop per bestand zodat de map nooit ongelimiteerd kan
// groeien. Deze map staat buiten data/ zodat hij apart in .htaccess
// geblokkeerd kan worden (de bestanden in data/ zelf zijn bewust wel publiek
// opvraagbaar).
$dataBackupMap              = __DIR__ . '/data-backups';
$dataBackupBewaardagen      = 90;
$dataBackupMaxPerBestand    = 200;

// Zet een tijdgestempelde kopie van $pad in $backupMap en ruimt daarna de
// oude kopieën van datzelfde bestand op: alles ouder dan $bewaardagen weg,
// en als er dan nog meer dan $maxPerBestand over zijn, gaan de oudste eruit.
function maakDataBackup($pad, $backupMap, $bewaardagen, $maxPerBestand) {
  if (!file_exists($pad)) return; // nieuw bestand, er is nog niets te bewaren

  if (!is_dir($backupMap)) {
    @mkdir($backupMap, 0755, true);
  }
  $basisnaam = basename($pad);
  $doelpad = $backupMap . '/' . date('Y-m-d_His') . '_' . $basisnaam;
  @copy($pad, $doelpad);

  $bestanden = @glob($backupMap . '/*_' . $basisnaam);
  if ($bestanden === false || count($bestanden) === 0) return;
  sort($bestanden); // tijdstempel voorop => alfabetisch is ook chronologisch

  $grens = time() - $bewaardagen * 24 * 60 * 60;
  $overgebleven = [];
  foreach ($bestanden as $b) {
    $tijd = @filemtime($b);
    if ($tijd !== false && $tijd >= $grens) {
      $overgebleven[] = $b;
    } else {
      @unlink($b);
    }
  }
  $teveel = count($overgebleven) - $maxPerBestand;
  for ($i = 0; $i < $teveel; $i++) {
    @unlink($overgebleven[$i]);
  }
}

// ===== Gebruikers en logboek =====
function laadGebruikers($pad) {
  if (!file_exists($pad)) return [];
  $json = json_decode(file_get_contents($pad), true);
  return is_array($json) ? $json : [];
}

function schrijfGebruikers($pad, $gebruikers) {
  global $dataBackupMap, $dataBackupBewaardagen, $dataBackupMaxPerBestand;
  maakDataBackup($pad, $dataBackupMap, $dataBackupBewaardagen, $dataBackupMaxPerBestand);
  return file_put_contents($pad, json_encode($gebruikers, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false;
}

function schrijfLog($pad, $gebruiker, $actie, $details = '') {
  $log = [];
  if (file_exists($pad)) {
    $json = json_decode(file_get_contents($pad), true);
    if (is_array($json)) $log = $json;
  }
  $log[] = ['tijd' => date('c'), 'gebruiker' => $gebruiker, 'actie' => $actie, 'details' => $details];

  // Bewaren op tijd (een paar maanden), niet op een vast aantal regels: bij
  // een vast aantal duwt een drukke dag (bijv. een grote foto-upload) meteen
  // oudere, nog prima relevante regels eruit. De harde bovengrens van 5000 is
  // alleen een noodrem tegen onbeperkte bestandsgroei, geen streefwaarde.
  $bewaarGrens = strtotime('-90 days');
  $log = array_values(array_filter($log, function($regel) use ($bewaarGrens) {
    $tijd = strtotime($regel['tijd'] ?? '');
    return $tijd === false || $tijd >= $bewaarGrens;
  }));
  if (count($log) > 5000) {
    $log = array_slice($log, -5000);
  }

  file_put_contents($pad, json_encode($log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

// ===== Lockout bij mislukte inlogpogingen =====
// Bestandsformaat: sleutels "user:<naam>" en "ip:<sha256>" met per sleutel
// een lijst unix-tijdstippen. Het IP zelf wordt dus niet op schijf bewaard.
// Alle lees-wijzig-schrijfhandelingen hieronder gebruiken één apart slot;
// LOCK_EX op alleen file_put_contents() is daarvoor niet voldoende.
function laadLoginPogingen($pad) {
  if (!file_exists($pad)) return [];
  $json = json_decode(file_get_contents($pad), true);
  return is_array($json) ? $json : [];
}

function schrijfLoginPogingen($pad, $pogingen) {
  return file_put_contents($pad, json_encode($pogingen, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false;
}

function loginPogingenSlotOpen() {
  global $dataBackupMap;
  if (!is_dir($dataBackupMap) && !@mkdir($dataBackupMap, 0755, true)) return false;
  $slot = @fopen($dataBackupMap . '/.login-pogingen.lock', 'c');
  if ($slot === false) return false;
  if (!flock($slot, LOCK_EX)) {
    fclose($slot);
    return false;
  }
  return $slot;
}

function loginPogingenSlotDicht($slot) {
  if (!$slot) return;
  flock($slot, LOCK_UN);
  fclose($slot);
}

function loginPogingenOpschonen(&$pogingen, $sleutel, $venster, $nu) {
  $recent = array_values(array_filter($pogingen[$sleutel] ?? [], function($t) use ($nu, $venster) {
    return is_numeric($t) && (int) $t > $nu - $venster;
  }));
  if ($recent) $pogingen[$sleutel] = $recent;
  else unset($pogingen[$sleutel]);
  return $recent;
}

// Geeft het hoogste aantal minuten van de actieve limieten, 0 als er geen
// blokkade is, of null als de limiter-opslag niet veilig gelockt kon worden.
// In dat laatste geval faalt inloggen gesloten: liever even niet inloggen dan
// brute-forcebescherming ongemerkt uitschakelen.
function loginLockoutMinuten($pad, array $limieten, $venster) {
  $slot = loginPogingenSlotOpen();
  if (!$slot) return null;
  try {
    $pogingen = laadLoginPogingen($pad);
    $nu = time();
    $minuten = 0;
    foreach ($limieten as $sleutel => $drempel) {
      $recent = loginPogingenOpschonen($pogingen, $sleutel, $venster, $nu);
      if (count($recent) >= (int) $drempel) {
        $minuten = max($minuten, (int) ceil((min($recent) + $venster - $nu) / 60));
      }
    }
    schrijfLoginPogingen($pad, $pogingen);
    return $minuten;
  } finally {
    loginPogingenSlotDicht($slot);
  }
}

// Registreert één mislukte poging tegelijk voor alle meegegeven sleutels
// (hier: gebruikersnaam én IP), onder hetzelfde slot.
function loginPogingRegistreren($pad, array $sleutels, $venster) {
  $slot = loginPogingenSlotOpen();
  if (!$slot) return false;
  try {
    $pogingen = laadLoginPogingen($pad);
    $nu = time();
    foreach (array_unique($sleutels) as $sleutel) {
      $recent = loginPogingenOpschonen($pogingen, $sleutel, $venster, $nu);
      $recent[] = $nu;
      $pogingen[$sleutel] = $recent;
    }
    return schrijfLoginPogingen($pad, $pogingen);
  } finally {
    loginPogingenSlotDicht($slot);
  }
}

// Na een geslaagde login alleen de teller van dit account wissen. De IP-teller
// blijft staan: één succesvolle login mag mislukte pogingen op andere accounts
// vanaf hetzelfde adres niet ineens uitwissen.
function loginPogingenWissen($pad, $sleutel) {
  $slot = loginPogingenSlotOpen();
  if (!$slot) return false;
  try {
    $pogingen = laadLoginPogingen($pad);
    if (isset($pogingen[$sleutel])) unset($pogingen[$sleutel]);
    return schrijfLoginPogingen($pad, $pogingen);
  } finally {
    loginPogingenSlotDicht($slot);
  }
}

$configOk = file_exists($configPad);
$beheerGebruiktLegacyWachtwoord = false;
if ($configOk) {
  require $configPad;

  // Voorkeur: alleen een password_hash() in beheer-config.php bewaren.
  // De oude plaintext-variabele blijft tijdelijk ondersteund zodat een
  // deploy niemand buitensluit voordat het server-only configbestand via
  // FTP is omgezet. Zodra een geldige hash aanwezig is, wordt het oude
  // wachtwoord volledig genegeerd, ook als die variabele nog bestaat.
  $beheerHashOk = isset($BEHEER_WACHTWOORD_HASH)
    && is_string($BEHEER_WACHTWOORD_HASH)
    && $BEHEER_WACHTWOORD_HASH !== ''
    && ((password_get_info($BEHEER_WACHTWOORD_HASH)['algoName'] ?? 'unknown') !== 'unknown');
  $beheerLegacyOk = isset($BEHEER_WACHTWOORD)
    && is_string($BEHEER_WACHTWOORD)
    && $BEHEER_WACHTWOORD !== ''
    && $BEHEER_WACHTWOORD !== 'VeranderDitWachtwoord';

  $configOk = $beheerHashOk || $beheerLegacyOk;
  $beheerGebruiktLegacyWachtwoord = !$beheerHashOk && $beheerLegacyOk;
}

function authMasterWachtwoordKlopt($invoer) {
  global $BEHEER_WACHTWOORD_HASH, $BEHEER_WACHTWOORD;

  if (isset($BEHEER_WACHTWOORD_HASH)
      && is_string($BEHEER_WACHTWOORD_HASH)
      && $BEHEER_WACHTWOORD_HASH !== ''
      && ((password_get_info($BEHEER_WACHTWOORD_HASH)['algoName'] ?? 'unknown') !== 'unknown')) {
    return password_verify((string) $invoer, $BEHEER_WACHTWOORD_HASH);
  }

  // Alleen migratiepad voor bestaande installaties. Verwijder deze
  // variabele uit beheer-config.php zodra de hash is ingesteld.
  return isset($BEHEER_WACHTWOORD)
    && is_string($BEHEER_WACHTWOORD)
    && $BEHEER_WACHTWOORD !== ''
    && hash_equals($BEHEER_WACHTWOORD, (string) $invoer);
}

// ===== Uitloggen =====
// Bewust een POST-formulier met csrf-controle in plaats van een simpele link:
// een gewone GET-link kan door een pagina van een ander (bijv. als afbeelding)
// worden misbruikt om een ingelogde beheerder ongevraagd uit te loggen.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formulier'] ?? '') === 'uitloggen' && csrfOk()) {
  $_SESSION = [];

  // session_destroy() ruimt alleen de sessie op de server op; de browser
  // blijft het sessie-ID daarna gewoon meesturen. Daarom de cookie ook
  // expliciet laten verlopen, met exact dezelfde eigenschappen als waarmee
  // hij is gezet: een browser ziet hem anders niet als dezelfde cookie en
  // laat de oude gewoon staan.
  $cookieParams = session_get_cookie_params();
  setcookie(session_name(), '', [
    'expires'  => time() - 42000,
    'path'     => $cookieParams['path'],
    'domain'   => $cookieParams['domain'],
    'secure'   => $cookieParams['secure'],
    'httponly' => $cookieParams['httponly'],
    'samesite' => $cookieParams['samesite'],
  ]);

  session_destroy();
  header('Location: ' . authHuidigePagina());
  exit;
}

$melding = [];
$meldingType = [];
$inlogFout = '';

// Meldingen die via Post-Redirect-Get zijn doorgegeven: één keer tonen en
// direct weer weggooien.
if (!empty($_SESSION['flash']) && is_array($_SESSION['flash'])) {
  foreach ($_SESSION['flash'] as $sleutel => $flash) {
    $melding[$sleutel] = $flash['tekst'] ?? '';
    $meldingType[$sleutel] = $flash['type'] ?? 'ok';
  }
  unset($_SESSION['flash']);
}

// ===== Inloggen =====
// Gebruikersnaam leeg + het beheerderswachtwoord -> ingelogd als "beheerder",
// met toegang tot gebruikersbeheer en het logboek. Een bekende gebruikersnaam
// + bijbehorend wachtwoord -> gewone toegang tot de inhoud.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formulier'] ?? '') === 'inloggen' && $configOk && !csrfOk()) {
  $inlogFout = 'Sessie verlopen. Ververs de pagina en probeer het opnieuw.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formulier'] ?? '') === 'inloggen' && $configOk) {
  $gebruikersnaamInvoer = trim($_POST['gebruikersnaam'] ?? '');
  $wachtwoordInvoer = $_POST['wachtwoord'] ?? '';
  $lockoutNaam = $gebruikersnaamInvoer === '' ? 'beheerder' : $gebruikersnaamInvoer;
  $lockoutGebruikerSleutel = 'user:' . strtolower($lockoutNaam);
  $bronIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'onbekend');
  $lockoutIpSleutel = 'ip:' . hash('sha256', $bronIp);
  $minutenTeWachten = loginLockoutMinuten($loginPogingenBestand, [
    $lockoutGebruikerSleutel => $loginLockoutDrempel,
    $lockoutIpSleutel        => $loginLockoutIpDrempel,
  ], $loginLockoutVenster);

  if ($minutenTeWachten === null) {
    $inlogFout = 'Inloggen is tijdelijk niet beschikbaar. Probeer het over een minuut opnieuw.';
  } elseif ($minutenTeWachten > 0) {
    // Bij een actieve account- of IP-limiet wordt het wachtwoord niet meer
    // gecontroleerd. De melding is bewust generiek en verraadt niet welke
    // van de twee grenzen geraakt is.
    $inlogFout = 'Te veel mislukte inlogpogingen. Probeer het over ' . $minutenTeWachten . ' minuut' . ($minutenTeWachten === 1 ? '' : 'en') . ' opnieuw.';
  } elseif ($gebruikersnaamInvoer === '' && authMasterWachtwoordKlopt($wachtwoordInvoer)) {
    // Nieuw sessie-ID na succesvol inloggen (session fixation): zonder dit zou
    // een sessie-ID dat van vóór het inloggen dateert (bijv. opgedrongen door
    // een aanvaller) na login gewoon geldig blijven. "true" verwijdert meteen
    // ook het oude sessiebestand op de server.
    session_regenerate_id(true);
    $_SESSION['gebruiker'] = 'beheerder';
    $_SESSION['is_master'] = true;
    loginPogingenWissen($loginPogingenBestand, $lockoutGebruikerSleutel);
    schrijfLog($logBestand, 'beheerder', 'login', '');
    header('Location: ' . authHuidigePagina());
    exit;
  } else {
    $gevondenGebruiker = null;
    foreach (laadGebruikers($usersBestand) as $g) {
      if (isset($g['gebruikersnaam']) && strcasecmp($g['gebruikersnaam'], $gebruikersnaamInvoer) === 0) {
        $gevondenGebruiker = $g;
        break;
      }
    }
    if ($gevondenGebruiker && isset($gevondenGebruiker['hash']) && password_verify($wachtwoordInvoer, $gevondenGebruiker['hash'])) {
      session_regenerate_id(true);
      $_SESSION['gebruiker'] = $gevondenGebruiker['gebruikersnaam'];
      $_SESSION['is_master'] = false;
      loginPogingenWissen($loginPogingenBestand, $lockoutGebruikerSleutel);
      schrijfLog($logBestand, $gevondenGebruiker['gebruikersnaam'], 'login', '');
      header('Location: ' . authHuidigePagina());
      exit;
    }

    loginPogingRegistreren($loginPogingenBestand, [$lockoutGebruikerSleutel, $lockoutIpSleutel], $loginLockoutVenster);
    sleep(2); // blijft daarnaast bestaan als simpele, extra afremming
    $inlogFout = 'Gebruikersnaam of wachtwoord onjuist.';
  }
}

$ingelogd = $configOk && isset($_SESSION['gebruiker']);
$huidigeGebruiker = $_SESSION['gebruiker'] ?? '';
$isMaster = $ingelogd && !empty($_SESSION['is_master']);

// ===== Het gebruikersrecord =====
// Onthouden na de eerste keer: zowel de rechten als de pagina's zelf hebben
// het nodig en het staat in een JSON-bestand. Geeft null voor de master
// (ingelogd met het beheerderswachtwoord) en voor niet-ingelogd.
function authGebruikerRecord() {
  static $record = false;
  global $ingelogd, $isMaster, $huidigeGebruiker, $usersBestand;

  if ($record !== false) return $record;
  $record = null;
  if ($ingelogd && !$isMaster) {
    foreach (laadGebruikers($usersBestand) as $g) {
      if (isset($g['gebruikersnaam']) && strcasecmp($g['gebruikersnaam'], $huidigeGebruiker) === 0) {
        $record = $g;
        break;
      }
    }
  }
  return $record;
}

// Gevoelige beheerrechten moeten expliciet op het account staan. Voor oude
// accounts zonder opgeslagen tabs geldt in authRechten() om compatibiliteits-
// redenen nog een brede terugval, maar die mag nooit gebruikt worden voor
// handelingen waarmee iemand zijn eigen autorisatie kan verhogen.
function authHeeftExplicietRecht($recht) {
  global $ingelogd, $isMaster;

  if (!$ingelogd) return false;
  if ($isMaster) return true;

  $record = authGebruikerRecord();
  if (!is_array($record) || !isset($record['tabs']) || !is_array($record['tabs'])) return false;
  return in_array((string) $recht, $record['tabs'], true);
}

// Bestuursfunctie en de koppeling tussen een lid en een inlogaccount bepalen
// rechtstreeks welke rolgebonden tabbladen iemand krijgt. Daarom mogen die
// velden alleen worden gewijzigd door iemand die óók gebruikers en rechten
// mag beheren. Het recht Gebruikers is al een hoog-vertrouwensrecht: wie dat
// heeft kan accountrechten aanpassen, dus hiermee ontstaat geen nieuwe macht.
function authMagLedenAutorisatieWijzigen() {
  return authHeeftExplicietRecht('gebruikers');
}

// ===== Rechten =====
// Bepaalt welke onderdelen de ingelogde persoon mag zien en opslaan.
//
// $alleTabs   : sleutel => label van alle onderdelen van de pagina
// $tabsViaRol : sleutels die niet via de vinkjes bij Gebruikers lopen maar
//               via de bestuursfunctie in de ledenadministratie
//
// Geeft terug: toegestaneTabs, isBestuurslid, eigenRol, gebruikerRecord.
//
// Master mag alles. Een gewone gebruiker zonder 'tabs'-veld (nog nooit
// ingesteld via Gebruikers) mag ook alles, net als voor die functie bestond,
// zodat bestaande gebruikers niet ineens buiten de deur staan. Pas als er via
// Gebruikers expliciet een selectie is opgeslagen, geldt die beperking.
//
// Voor de rol-tabbladen is de bestuursfunctie leidend, niet de checkboxlijst:
// wie in het tabblad Leden een bestuursfunctie heeft staan (voorzitter,
// penningmeester, secretaris of bestuurslid) en daar aan deze inlognaam is
// gekoppeld, krijgt ze erbij. Wie die functie niet heeft, raakt ze ook weer
// kwijt als ze per ongeluk via Gebruikers zijn aangevinkt.
function authRechten(array $alleTabs, array $tabsViaRol = []) {
  global $ingelogd, $isMaster, $huidigeGebruiker;

  $gebruikerRecord = authGebruikerRecord();

  if ($isMaster) {
    $toegestaneTabs = array_keys($alleTabs);
  } elseif ($gebruikerRecord && isset($gebruikerRecord['tabs']) && is_array($gebruikerRecord['tabs'])) {
    $toegestaneTabs = array_values(array_intersect(array_keys($alleTabs), $gebruikerRecord['tabs']));
  } else {
    $toegestaneTabs = array_keys($alleTabs);
  }

  $eigenRol = ($ingelogd && !$isMaster)
    ? ledenRolVanGebruiker($huidigeGebruiker)
    : ['lid' => null, 'bestuurslid' => false, 'functie' => '', 'commissies' => []];
  $isBestuurslid = $isMaster || $eigenRol['bestuurslid'];

  foreach ($tabsViaRol as $rolTab) {
    if (!isset($alleTabs[$rolTab])) continue;
    $heeftNu = in_array($rolTab, $toegestaneTabs, true);
    if ($isBestuurslid && !$heeftNu) {
      $toegestaneTabs[] = $rolTab;
    } elseif (!$isBestuurslid && $heeftNu) {
      $toegestaneTabs = array_values(array_diff($toegestaneTabs, [$rolTab]));
    }
  }

  return [
    'toegestaneTabs'  => $toegestaneTabs,
    'isBestuurslid'   => $isBestuurslid,
    'eigenRol'        => $eigenRol,
    'gebruikerRecord' => $gebruikerRecord,
  ];
}

// Het inlogscherm. Staat hier zodat elke afgeschermde pagina hetzelfde
// formulier toont; de opmaak komt van de pagina die het insluit.
// $titel is de regel onder "Inloggen" (bijv. "RC045 beheer").
function authInlogFormulier($titel) {
  global $csrfToken, $inlogFout;
  ?>
    <div class="kaart kaart-smal">
      <h1>Inloggen</h1>
      <p class="sub"><?php echo htmlspecialchars($titel); ?></p>

      <?php if ($inlogFout !== ''): ?>
        <div class="melding fout"><?php echo htmlspecialchars($inlogFout); ?></div>
      <?php endif; ?>

      <form method="post" action="<?php echo htmlspecialchars(authHuidigePagina()); ?>">
        <input type="hidden" name="formulier" value="inloggen">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <div class="veld">
          <label for="login-gebruikersnaam">Gebruikersnaam</label>
          <input type="text" id="login-gebruikersnaam" name="gebruikersnaam" autocomplete="username" autocapitalize="off">
        </div>
        <div class="veld">
          <label for="login-wachtwoord">Wachtwoord</label>
          <input type="password" id="login-wachtwoord" name="wachtwoord" autocomplete="current-password" required>
        </div>
        <button type="submit">Inloggen</button>
        <p class="hint">Beheerderswachtwoord om gebruikers te beheren? Laat gebruikersnaam leeg.</p>
      </form>
    </div>
  <?php
}
