<?php
// ============================================================
// RC045 auth: inloggen, sessie, logboek en rechten
// ============================================================
// Gedeelde inlogafhandeling voor de afgeschermde pagina's van de site.
//
// In standalone/legacy modus blijven de bestaande server-only rootbestanden
// tijdelijk ondersteund. Zodra een tenant een expliciete private_root heeft,
// komen masterconfig, gebruikers, auditlog, loginpogingen, locks en authbackups
// uitsluitend uit de tenant-eigen private opslag. Er is dan geen terugval naar
// authdata uit de gedeelde applicatiecode.
// ============================================================

date_default_timezone_set('Europe/Amsterdam');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: frame-ancestors 'none'");

// De ledenadministratie bepaalt mede de rechten (zie authRechten hieronder):
// wie daar een bestuursfunctie heeft, krijgt de bestuursonderdelen erbij.
require_once __DIR__ . '/leden-opslag.php';
require_once __DIR__ . '/app/auth-storage.php';

// Los vóór session_start() de actieve tenant en diens authpaden op. Een externe
// tenant met private_root mag nooit beheeraccounts of credentials uit RC045
// overnemen wanneer zijn eigen authopslag nog leeg is.
$authSiteConfigGeladen = require __DIR__ . '/site-config.php';
$authSiteConfig = is_array($authSiteConfigGeladen) ? $authSiteConfigGeladen : [];
$authPaden = authStoragePaden($authSiteConfig, __DIR__);
$configPad = $authPaden['config'];
$usersBestand = $authPaden['users'];
$logBestand = $authPaden['audit'];
$loginPogingenBestand = $authPaden['login_attempts'];
$loginPogingenSlotBestand = $authPaden['login_lock'];
$dataBackupMap = $authPaden['backups'];
$dataBackupBewaardagen = 90;
$dataBackupMaxPerBestand = 200;

// ===== Sessie: een week ingelogd blijven, niet halverwege een lang formulier uitloggen =====
$sessieduur = 60 * 60 * 24 * 7;
ini_set('session.gc_maxlifetime', (string) $sessieduur);
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
  'lifetime' => $sessieduur,
  'path' => '/',
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

function csrfOk() {
  return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

function authHuidigePagina() {
  $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
  return $script !== '' ? $script : 'beheer.php';
}

// Lockout bij te veel mislukte inlogpogingen.
$loginLockoutVenster   = 15 * 60;
$loginLockoutDrempel   = 5;
$loginLockoutIpDrempel = 20;

// Zet een tijdgestempelde kopie van $pad in $backupMap en ruimt daarna de
// oude kopieën van datzelfde bestand op.
function maakDataBackup($pad, $backupMap, $bewaardagen, $maxPerBestand) {
  if (!file_exists($pad)) return;

  if (!is_dir($backupMap) && !@mkdir($backupMap, 0750, true) && !is_dir($backupMap)) {
    return;
  }
  @chmod($backupMap, 0750);
  $basisnaam = basename($pad);
  $micro = (int) round((microtime(true) - floor(microtime(true))) * 1000000);
  $doelpad = $backupMap . '/' . date('Y-m-d_His') . '_' . sprintf('%06d', $micro) . '_' . $basisnaam;
  if (@copy($pad, $doelpad)) @chmod($doelpad, 0640);

  $bestanden = @glob($backupMap . '/*_' . $basisnaam);
  if ($bestanden === false || count($bestanden) === 0) return;
  sort($bestanden);

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
  $ruw = @file_get_contents($pad);
  if ($ruw === false) return [];
  $json = json_decode($ruw, true);
  return is_array($json) ? $json : [];
}

function schrijfGebruikers($pad, $gebruikers) {
  global $dataBackupMap, $dataBackupBewaardagen, $dataBackupMaxPerBestand;
  if (!authStorageMaakSchrijfmap($pad)) return false;
  maakDataBackup($pad, $dataBackupMap, $dataBackupBewaardagen, $dataBackupMaxPerBestand);
  $json = json_encode($gebruikers, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  if ($json === false) return false;
  try {
    $suffix = bin2hex(random_bytes(4));
  } catch (Throwable $e) {
    $suffix = str_replace('.', '', (string) microtime(true));
  }
  $tmp = $pad . '.tmp.' . $suffix;
  if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
  @chmod($tmp, 0640);
  if (!@rename($tmp, $pad)) {
    @unlink($tmp);
    return false;
  }
  @chmod($pad, 0640);
  return true;
}

// Auditlog is een read-modify-write-bestand. Houd één flock vast vanaf het
// lezen tot en met truncate/write/flush.
function schrijfLog($pad, $gebruiker, $actie, $details = '') {
  if (!authStorageMaakSchrijfmap($pad)) return false;
  $handvat = @fopen($pad, 'c+');
  if ($handvat === false) return false;
  @chmod($pad, 0640);
  if (!flock($handvat, LOCK_EX)) {
    fclose($handvat);
    return false;
  }

  try {
    rewind($handvat);
    $ruw = stream_get_contents($handvat);
    $log = $ruw !== false && $ruw !== '' ? json_decode($ruw, true) : [];
    if (!is_array($log)) $log = [];

    $log[] = ['tijd' => date('c'), 'gebruiker' => $gebruiker, 'actie' => $actie, 'details' => $details];

    $bewaarGrens = strtotime('-90 days');
    $log = array_values(array_filter($log, function($regel) use ($bewaarGrens) {
      $tijd = strtotime($regel['tijd'] ?? '');
      return $tijd === false || $tijd >= $bewaarGrens;
    }));
    if (count($log) > 5000) {
      $log = array_slice($log, -5000);
    }

    $json = json_encode($log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    rewind($handvat);
    if (!ftruncate($handvat, 0)) return false;
    $geschreven = fwrite($handvat, $json);
    if ($geschreven === false || $geschreven < strlen($json)) return false;
    return fflush($handvat);
  } finally {
    flock($handvat, LOCK_UN);
    fclose($handvat);
  }
}

// ===== Lockout bij mislukte inlogpogingen =====
function laadLoginPogingen($pad) {
  if (!file_exists($pad)) return [];
  $ruw = @file_get_contents($pad);
  if ($ruw === false) return [];
  $json = json_decode($ruw, true);
  return is_array($json) ? $json : [];
}

function schrijfLoginPogingen($pad, $pogingen) {
  if (!authStorageMaakSchrijfmap($pad)) return false;
  $json = json_encode($pogingen, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  if ($json === false) return false;
  $ok = file_put_contents($pad, $json, LOCK_EX) !== false;
  if ($ok) @chmod($pad, 0640);
  return $ok;
}

function loginPogingenSlotOpen() {
  global $loginPogingenSlotBestand;
  if (!authStorageMaakSchrijfmap($loginPogingenSlotBestand)) return false;
  $slot = @fopen($loginPogingenSlotBestand, 'c');
  if ($slot === false) return false;
  @chmod($loginPogingenSlotBestand, 0640);
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

  return isset($BEHEER_WACHTWOORD)
    && is_string($BEHEER_WACHTWOORD)
    && $BEHEER_WACHTWOORD !== ''
    && hash_equals($BEHEER_WACHTWOORD, (string) $invoer);
}

// ===== Uitloggen =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formulier'] ?? '') === 'uitloggen' && csrfOk()) {
  $_SESSION = [];

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

if (!empty($_SESSION['flash']) && is_array($_SESSION['flash'])) {
  foreach ($_SESSION['flash'] as $sleutel => $flash) {
    $melding[$sleutel] = $flash['tekst'] ?? '';
    $meldingType[$sleutel] = $flash['type'] ?? 'ok';
  }
  unset($_SESSION['flash']);
}

// ===== Inloggen =====
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
    $inlogFout = 'Te veel mislukte inlogpogingen. Probeer het over ' . $minutenTeWachten . ' minuut' . ($minutenTeWachten === 1 ? '' : 'en') . ' opnieuw.';
  } elseif ($gebruikersnaamInvoer === '' && authMasterWachtwoordKlopt($wachtwoordInvoer)) {
    session_regenerate_id(true);
    $_SESSION['gebruiker'] = 'beheerder';
    $_SESSION['is_master'] = true;
    unset($_SESSION['user_session_version']);
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
      $_SESSION['user_session_version'] = max(1, (int)($gevondenGebruiker['sessie_versie'] ?? 1));
      loginPogingenWissen($loginPogingenBestand, $lockoutGebruikerSleutel);
      schrijfLog($logBestand, $gevondenGebruiker['gebruikersnaam'], 'login', '');
      header('Location: ' . authHuidigePagina());
      exit;
    }

    loginPogingRegistreren($loginPogingenBestand, [$lockoutGebruikerSleutel, $lockoutIpSleutel], $loginLockoutVenster);
    sleep(2);
    $inlogFout = 'Gebruikersnaam of wachtwoord onjuist.';
  }
}

$ingelogd = $configOk && isset($_SESSION['gebruiker']);
$huidigeGebruiker = $_SESSION['gebruiker'] ?? '';
$isMaster = $ingelogd && !empty($_SESSION['is_master']);
require __DIR__ . '/app/auth-session-check.php';

// ===== Het gebruikersrecord =====
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

function authHeeftExplicietRecht($recht) {
  global $ingelogd, $isMaster;

  if (!$ingelogd) return false;
  if ($isMaster) return true;

  $record = authGebruikerRecord();
  if (!is_array($record) || !isset($record['tabs']) || !is_array($record['tabs'])) return false;
  return in_array((string) $recht, $record['tabs'], true);
}

function authMagLedenAutorisatieWijzigen() {
  return authHeeftExplicietRecht('gebruikers');
}

// ===== Rechten =====
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
