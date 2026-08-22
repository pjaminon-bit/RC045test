<?php
// ============================================================
// Centrale beheer-authenticatie, sessie, audit en legacy-tabrechten
// ============================================================
date_default_timezone_set('Europe/Amsterdam');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data: blob: https:; connect-src 'self'; media-src 'self' blob:; worker-src 'self' blob:; frame-src 'none'; upgrade-insecure-requests");

require_once __DIR__ . '/leden-opslag.php';
require_once __DIR__ . '/app/auth-storage.php';

$authSiteConfigGeladen = require __DIR__ . '/site-config.php';
$authSiteConfig = is_array($authSiteConfigGeladen) ? $authSiteConfigGeladen : [];
$authPaden = authStoragePaden($authSiteConfig, __DIR__);
$configPad = $authPaden['config'];
$usersBestand = $authPaden['users'];
$logBestand = $authPaden['audit'];
$loginPogingenBestand = $authPaden['login_attempts'];
$loginPogingenSlotBestand = $authPaden['login_lock'];
$authBackupMap = $authPaden['backups'];

// ===== Sessie =====
$sessieduur = 60 * 60 * 24 * 7;
ini_set('session.gc_maxlifetime', (string)$sessieduur);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params([
  'lifetime' => $sessieduur,
  'path' => '/',
  'secure' => true,
  'httponly' => true,
  'samesite' => 'Lax',
]);
if (!session_start()) throw new RuntimeException('Beheersessie kon niet worden gestart.');

if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf']) || preg_match('/^[0-9a-f]{64}$/D', $_SESSION['csrf']) !== 1) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf'];

function csrfOk(): bool {
  return isset($_POST['csrf'], $_SESSION['csrf'])
    && is_string($_POST['csrf'])
    && is_string($_SESSION['csrf'])
    && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

function authHuidigePagina(): string {
  $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
  return $script !== '' ? $script : 'beheer.php';
}

// ===== Backups, gebruikers en audit =====
$dataBackupMap = __DIR__ . '/data-backups';
$dataBackupBewaardagen = 90;
$dataBackupMaxPerBestand = 200;

function maakDataBackup($pad, $backupMap, $bewaardagen, $maxPerBestand): void {
  if (!is_file($pad) || is_link($pad)) return;
  if (!is_dir($backupMap) && !@mkdir($backupMap, 0750, true) && !is_dir($backupMap)) return;
  if (is_link($backupMap)) return;
  @chmod($backupMap, 0750);
  $basisnaam = basename($pad);
  $nu = microtime(true);
  $seconde = (int)floor($nu);
  $micro = max(0, min(999999, (int)floor(($nu - $seconde) * 1000000)));
  $doelpad = $backupMap . '/' . date('Y-m-d_His', $seconde) . '_' . sprintf('%06d', $micro) . '_' . $basisnaam;
  if (@copy($pad, $doelpad)) @chmod($doelpad, 0640);

  $bestanden = @glob($backupMap . '/*_' . $basisnaam);
  if (!is_array($bestanden) || !$bestanden) return;
  sort($bestanden, SORT_STRING);
  $grens = time() - (int)$bewaardagen * 86400;
  $over = [];
  foreach ($bestanden as $bestand) {
    if (is_link($bestand)) { @unlink($bestand); continue; }
    $tijd = @filemtime($bestand);
    if ($tijd !== false && $tijd >= $grens) $over[] = $bestand;
    else @unlink($bestand);
  }
  $teveel = count($over) - (int)$maxPerBestand;
  for ($i = 0; $i < $teveel; $i++) @unlink($over[$i]);
}

function laadGebruikers($pad): array {
  if (!is_file($pad) || is_link($pad)) return [];
  $ruw = @file_get_contents($pad);
  if (!is_string($ruw)) return [];
  $json = json_decode($ruw, true);
  return is_array($json) ? $json : [];
}

function schrijfGebruikers($pad, $gebruikers): bool {
  global $authBackupMap, $dataBackupBewaardagen, $dataBackupMaxPerBestand;
  if (!is_array($gebruikers) || !authStorageMaakSchrijfmap($pad) || is_link($pad)) return false;
  maakDataBackup($pad, $authBackupMap, $dataBackupBewaardagen, $dataBackupMaxPerBestand);
  $json = json_encode($gebruikers, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  if (!is_string($json)) return false;
  try { $suffix = bin2hex(random_bytes(5)); }
  catch (Throwable $e) { $suffix = str_replace('.', '', (string)microtime(true)); }
  $tmp = $pad . '.tmp.' . $suffix;
  if (@file_put_contents($tmp, $json, LOCK_EX) === false) { @unlink($tmp); return false; }
  @chmod($tmp, 0640);
  if (!@rename($tmp, $pad)) { @unlink($tmp); return false; }
  @chmod($pad, 0640);
  return true;
}

function schrijfLog($pad, $gebruiker, $actie, $details = ''): bool {
  if (!authStorageMaakSchrijfmap($pad) || is_link($pad)) return false;
  $handvat = @fopen($pad, 'c+');
  if (!is_resource($handvat)) return false;
  @chmod($pad, 0640);
  if (!flock($handvat, LOCK_EX)) { fclose($handvat); return false; }
  try {
    rewind($handvat);
    $ruw = stream_get_contents($handvat);
    $log = is_string($ruw) && $ruw !== '' ? json_decode($ruw, true) : [];
    if (!is_array($log)) $log = [];
    $log[] = ['tijd'=>date('c'),'gebruiker'=>(string)$gebruiker,'actie'=>(string)$actie,'details'=>(string)$details];
    $grens = strtotime('-90 days');
    $log = array_values(array_filter($log, static function($regel) use ($grens) {
      if (!is_array($regel)) return false;
      $tijd = strtotime((string)($regel['tijd'] ?? ''));
      return $tijd === false || $tijd >= $grens;
    }));
    if (count($log) > 5000) $log = array_slice($log, -5000);
    $json = json_encode($log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (!is_string($json)) return false;
    rewind($handvat);
    if (!ftruncate($handvat, 0)) return false;
    $geschreven = fwrite($handvat, $json);
    return $geschreven !== false && $geschreven === strlen($json) && fflush($handvat);
  } finally {
    flock($handvat, LOCK_UN);
    fclose($handvat);
  }
}

// ===== Login rate limiting =====
$loginLockoutVenster = 15 * 60;
$loginLockoutDrempel = 5;
$loginLockoutIpDrempel = 20;

function laadLoginPogingen($pad): array {
  if (!file_exists($pad)) return [];
  if (!is_file($pad) || is_link($pad)) return [];
  $ruw = @file_get_contents($pad);
  if (!is_string($ruw)) return [];
  $json = json_decode($ruw, true);
  return is_array($json) ? $json : [];
}

function schrijfLoginPogingen($pad, $pogingen): bool {
  if (!is_array($pogingen) || !authStorageMaakSchrijfmap($pad) || is_link($pad)) return false;
  $json = json_encode($pogingen, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  if (!is_string($json)) return false;
  $ok = @file_put_contents($pad, $json, LOCK_EX) !== false;
  if ($ok) @chmod($pad, 0640);
  return $ok;
}

function loginPogingenSlotOpen() {
  global $loginPogingenSlotBestand;
  if (!authStorageMaakSchrijfmap($loginPogingenSlotBestand) || is_link($loginPogingenSlotBestand)) return false;
  $slot = @fopen($loginPogingenSlotBestand, 'c');
  if (!is_resource($slot)) return false;
  @chmod($loginPogingenSlotBestand, 0640);
  if (!flock($slot, LOCK_EX)) { fclose($slot); return false; }
  return $slot;
}

function loginPogingenSlotDicht($slot): void {
  if (!is_resource($slot)) return;
  flock($slot, LOCK_UN);
  fclose($slot);
}

function loginPogingenOpschonen(&$pogingen, $sleutel, $venster, $nu): array {
  $recent = array_values(array_filter((array)($pogingen[$sleutel] ?? []), static fn($t) => is_numeric($t) && (int)$t > $nu - $venster && (int)$t <= $nu + 60));
  if ($recent) $pogingen[$sleutel] = $recent;
  else unset($pogingen[$sleutel]);
  return $recent;
}

function loginLockoutMinuten($pad, array $limieten, $venster): ?int {
  $slot = loginPogingenSlotOpen();
  if (!$slot) return null;
  try {
    $pogingen = laadLoginPogingen($pad);
    $nu = time();
    $minuten = 0;
    foreach ($limieten as $sleutel => $drempel) {
      $recent = loginPogingenOpschonen($pogingen, (string)$sleutel, (int)$venster, $nu);
      if (count($recent) >= (int)$drempel) $minuten = max($minuten, (int)ceil((min($recent) + $venster - $nu) / 60));
    }
    if (!schrijfLoginPogingen($pad, $pogingen)) return null;
    return $minuten;
  } finally { loginPogingenSlotDicht($slot); }
}

function loginPogingRegistreren($pad, array $sleutels, $venster): bool {
  $slot = loginPogingenSlotOpen();
  if (!$slot) return false;
  try {
    $pogingen = laadLoginPogingen($pad);
    $nu = time();
    foreach (array_unique($sleutels) as $sleutel) {
      $recent = loginPogingenOpschonen($pogingen, (string)$sleutel, (int)$venster, $nu);
      $recent[] = $nu;
      $pogingen[(string)$sleutel] = $recent;
    }
    return schrijfLoginPogingen($pad, $pogingen);
  } finally { loginPogingenSlotDicht($slot); }
}

function loginPogingenWissen($pad, $sleutel): bool {
  $slot = loginPogingenSlotOpen();
  if (!$slot) return false;
  try {
    $pogingen = laadLoginPogingen($pad);
    unset($pogingen[(string)$sleutel]);
    return schrijfLoginPogingen($pad, $pogingen);
  } finally { loginPogingenSlotDicht($slot); }
}

// ===== Mastercredential =====
// Externe/multi-tenant installaties accepteren uitsluitend password_hash().
// Standalone RC045 behoudt tijdelijk alleen voor migratie een expliciet
// gemarkeerd plaintext-pad; de securitylaag rapporteert dit via de bestaande
// $beheerGebruiktLegacyWachtwoord-vlag zodat het kan worden verwijderd zodra
// de huidige serverconfig is omgezet.
$configOk = is_file($configPad) && !is_link($configPad);
$beheerGebruiktLegacyWachtwoord = false;
$beheerHashOk = false;
$beheerLegacyOk = false;
if ($configOk) {
  require $configPad;
  $beheerHashOk = isset($BEHEER_WACHTWOORD_HASH)
    && is_string($BEHEER_WACHTWOORD_HASH)
    && $BEHEER_WACHTWOORD_HASH !== ''
    && ((password_get_info($BEHEER_WACHTWOORD_HASH)['algoName'] ?? 'unknown') !== 'unknown');
  $beheerLegacyOk = empty($authPaden['tenant_private'])
    && isset($BEHEER_WACHTWOORD)
    && is_string($BEHEER_WACHTWOORD)
    && $BEHEER_WACHTWOORD !== ''
    && $BEHEER_WACHTWOORD !== 'VeranderDitWachtwoord';
  $configOk = $beheerHashOk || $beheerLegacyOk;
  $beheerGebruiktLegacyWachtwoord = !$beheerHashOk && $beheerLegacyOk;
  if ($beheerGebruiktLegacyWachtwoord) error_log('[security] standalone mastercredential gebruikt nog legacy plaintext; migreer naar BEHEER_WACHTWOORD_HASH');
}

function authMasterWachtwoordKlopt($invoer): bool {
  global $BEHEER_WACHTWOORD_HASH, $BEHEER_WACHTWOORD, $authPaden;
  if (isset($BEHEER_WACHTWOORD_HASH)
      && is_string($BEHEER_WACHTWOORD_HASH)
      && $BEHEER_WACHTWOORD_HASH !== ''
      && ((password_get_info($BEHEER_WACHTWOORD_HASH)['algoName'] ?? 'unknown') !== 'unknown')) {
    return password_verify((string)$invoer, $BEHEER_WACHTWOORD_HASH);
  }
  // Alleen bestaande standalone-installatie; externe tenants falen hierboven.
  return empty($authPaden['tenant_private'])
    && isset($BEHEER_WACHTWOORD)
    && is_string($BEHEER_WACHTWOORD)
    && $BEHEER_WACHTWOORD !== ''
    && hash_equals($BEHEER_WACHTWOORD, (string)$invoer);
}

// ===== Uitloggen =====
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['formulier'] ?? '') === 'uitloggen' && csrfOk()) {
  $_SESSION = [];
  $cookieParams = session_get_cookie_params();
  setcookie(session_name(), '', [
    'expires'=>time()-42000,
    'path'=>$cookieParams['path'],
    'domain'=>$cookieParams['domain'],
    'secure'=>$cookieParams['secure'],
    'httponly'=>$cookieParams['httponly'],
    'samesite'=>$cookieParams['samesite'],
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
    if (!is_array($flash)) continue;
    $melding[$sleutel] = $flash['tekst'] ?? '';
    $meldingType[$sleutel] = $flash['type'] ?? 'ok';
  }
  unset($_SESSION['flash']);
}

// ===== Inloggen =====
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['formulier'] ?? '') === 'inloggen' && $configOk && !csrfOk()) {
  $inlogFout = 'Sessie verlopen. Ververs de pagina en probeer het opnieuw.';
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['formulier'] ?? '') === 'inloggen' && $configOk) {
  $gebruikersnaamInvoer = trim((string)($_POST['gebruikersnaam'] ?? ''));
  $wachtwoordInvoer = (string)($_POST['wachtwoord'] ?? '');
  $lockoutNaam = $gebruikersnaamInvoer === '' ? 'beheerder' : $gebruikersnaamInvoer;
  $lockoutGebruikerSleutel = 'user:' . strtolower($lockoutNaam);
  $lockoutIpSleutel = 'ip:' . hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'onbekend'));
  $minutenTeWachten = loginLockoutMinuten($loginPogingenBestand, [
    $lockoutGebruikerSleutel => $loginLockoutDrempel,
    $lockoutIpSleutel => $loginLockoutIpDrempel,
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
      if (is_array($g) && isset($g['gebruikersnaam']) && strcasecmp((string)$g['gebruikersnaam'], $gebruikersnaamInvoer) === 0) { $gevondenGebruiker = $g; break; }
    }
    $accountActief = is_array($gevondenGebruiker) && (($gevondenGebruiker['actief'] ?? true) !== false);
    if ($accountActief && isset($gevondenGebruiker['hash']) && is_string($gevondenGebruiker['hash']) && password_verify($wachtwoordInvoer, $gevondenGebruiker['hash'])) {
      session_regenerate_id(true);
      $_SESSION['gebruiker'] = (string)$gevondenGebruiker['gebruikersnaam'];
      $_SESSION['is_master'] = false;
      $_SESSION['user_session_version'] = max(1, (int)($gevondenGebruiker['sessie_versie'] ?? 1));
      loginPogingenWissen($loginPogingenBestand, $lockoutGebruikerSleutel);
      schrijfLog($logBestand, (string)$gevondenGebruiker['gebruikersnaam'], 'login', '');
      header('Location: ' . authHuidigePagina());
      exit;
    }
    $geregistreerd = loginPogingRegistreren($loginPogingenBestand, [$lockoutGebruikerSleutel, $lockoutIpSleutel], $loginLockoutVenster);
    sleep(2);
    $inlogFout = $geregistreerd ? 'Gebruikersnaam of wachtwoord onjuist.' : 'Inloggen is tijdelijk niet beschikbaar. Probeer het over een minuut opnieuw.';
  }
}

$ingelogd = $configOk && isset($_SESSION['gebruiker']) && is_string($_SESSION['gebruiker']);
$huidigeGebruiker = $ingelogd ? (string)$_SESSION['gebruiker'] : '';
$isMaster = $ingelogd && !empty($_SESSION['is_master']);
require __DIR__ . '/app/auth-session-check.php';

// ===== Gebruikersrecord en legacy-tabrechten =====
function authGebruikerRecord() {
  static $record = false;
  global $ingelogd, $isMaster, $huidigeGebruiker, $usersBestand;
  if ($record !== false) return $record;
  $record = null;
  if ($ingelogd && !$isMaster) {
    foreach (laadGebruikers($usersBestand) as $g) {
      if (is_array($g) && isset($g['gebruikersnaam']) && strcasecmp((string)$g['gebruikersnaam'], (string)$huidigeGebruiker) === 0) { $record = $g; break; }
    }
  }
  return $record;
}

function authHeeftExplicietRecht($recht): bool {
  global $ingelogd, $isMaster;
  if (!$ingelogd) return false;
  if ($isMaster) return true;
  $record = authGebruikerRecord();
  if (!is_array($record)) return false;
  if (isset($record['tabs']) && is_array($record['tabs']) && in_array((string)$recht, $record['tabs'], true)) return true;
  if (isset($record['capabilities']) && is_array($record['capabilities'])) {
    require_once __DIR__ . '/app/auth-capabilities.php';
    $vereist = authCapabilitiesVanTabs([(string)$recht]);
    $expliciet = authCapabilitiesNormaliseer($record['capabilities']);
    foreach ($vereist as $cap) if (in_array($cap, $expliciet, true)) return true;
  }
  return false;
}

function authMagLedenAutorisatieWijzigen(): bool {
  return authHeeftExplicietRecht('gebruikers');
}

/**
 * Legacy UI-tabprojectie. Ontbrekende/malforme autorisatiemetadata geeft nooit
 * meer brede toegang. Expliciete capabilities worden naar hun legacy-tabs
 * geprojecteerd; expliciete tabs blijven tijdens de migratie ondersteund.
 * Rolgebonden bestuurstabs blijven uitsluitend door de ledenrol bepaald.
 */
function authRechten(array $alleTabs, array $tabsViaRol = []): array {
  global $ingelogd, $isMaster, $huidigeGebruiker;
  $gebruikerRecord = authGebruikerRecord();
  $toegestaneTabs = [];

  if ($isMaster) {
    $toegestaneTabs = array_keys($alleTabs);
  } elseif (is_array($gebruikerRecord)) {
    $explicieteTabs = [];
    if (array_key_exists('tabs', $gebruikerRecord) && is_array($gebruikerRecord['tabs'])) {
      $explicieteTabs = $gebruikerRecord['tabs'];
    } elseif (array_key_exists('capabilities', $gebruikerRecord) && is_array($gebruikerRecord['capabilities'])) {
      require_once __DIR__ . '/app/auth-capabilities.php';
      $explicieteTabs = authLegacyTabsVoorCapabilities($gebruikerRecord['capabilities']);
    }
    $toegestaneTabs = array_values(array_intersect(array_keys($alleTabs), $explicieteTabs));
  }

  $eigenRol = ($ingelogd && !$isMaster)
    ? ledenRolVanGebruiker($huidigeGebruiker)
    : ['lid'=>null,'bestuurslid'=>false,'functie'=>'','commissies'=>[]];
  if (!is_array($eigenRol)) $eigenRol = ['lid'=>null,'bestuurslid'=>false,'functie'=>'','commissies'=>[]];
  $isBestuurslid = $isMaster || !empty($eigenRol['bestuurslid']);

  foreach ($tabsViaRol as $rolTab) {
    if (!is_string($rolTab) || !isset($alleTabs[$rolTab])) continue;
    $heeftNu = in_array($rolTab, $toegestaneTabs, true);
    if ($isBestuurslid && !$heeftNu) $toegestaneTabs[] = $rolTab;
    elseif (!$isBestuurslid && $heeftNu) $toegestaneTabs = array_values(array_diff($toegestaneTabs, [$rolTab]));
  }

  return [
    'toegestaneTabs'=>$toegestaneTabs,
    'isBestuurslid'=>$isBestuurslid,
    'eigenRol'=>$eigenRol,
    'gebruikerRecord'=>$gebruikerRecord,
  ];
}

function authInlogFormulier($titel): void {
  global $csrfToken, $inlogFout;
  ?>
    <div class="kaart kaart-smal">
      <h1>Inloggen</h1>
      <p class="sub"><?php echo htmlspecialchars((string)$titel, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($inlogFout !== ''): ?>
        <div class="melding fout"><?php echo htmlspecialchars((string)$inlogFout, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>
      <form method="post" action="<?php echo htmlspecialchars(authHuidigePagina(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="formulier" value="inloggen">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)$csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
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
