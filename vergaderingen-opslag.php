<?php
// ============================================================
// RC045 bestuursvergaderingen: opslag en hulpfuncties
// ------------------------------------------------------------
// Zelfde opzet als leden-opslag.php: alleen functies, schrijft zelf
// niets naar het scherm. Wordt gebruikt door beheer.php, tabblad
// Bestuursvergadering.
//
// STANDALONE COMPATIBILITEIT / PRIVACY. vergaderingen-data.php is het
// legacy PHP+JSON-formaat voor losse installaties. De PHP-voorloop blokkeert
// directe uitvoer; op Apache kan de repository-.htaccess als aanvullende
// denylaag dienen. Dit is geen VPS-opslag- of deploycontract: nieuwe
// multi-tenant VPS-tenants volgen de tenant-private storagegrens uit
// docs/PROVISIONING.md en docs/VPS-DEPLOYMENT.md. .htaccess wordt door de
// releaseflow meegenomen; er is geen handmatige FTP-stap voor VPS-deployments.
// ============================================================

require_once __DIR__ . '/leden-opslag.php';

define('VERGADERINGEN_VOORLOOP', "<?php exit; ?>\n");

function vergaderingenBestandPad() {
  return __DIR__ . '/vergaderingen-data.php';
}

// ===== Statussen =====

function vergaderingenStatussen() {
  return [
    'gepland'      => 'Gepland',
    'afgerond'     => 'Afgerond',
    'geannuleerd'  => 'Geannuleerd',
  ];
}

// Aanwezigheid per bestuurslid. 'onbekend' is geen keuze in het
// formulier maar wat er geldt zolang er niets is aangevinkt, bijvoorbeeld
// bij een vergadering die nog moet plaatsvinden.
function vergaderingenAanwezigheid() {
  return [
    'aanwezig'  => 'Aanwezig',
    'afgemeld'  => 'Afgemeld',
    'afwezig'   => 'Afwezig',
  ];
}

// ===== Soorten =====
// Twee registers in hetzelfde bestand: bestuursvergaderingen (tabblad
// Bestuursvergadering, presentielijst per bestuurslid) en
// ledenvergaderingen (tabblad Ledenvergadering, ook de ALV's). Elke
// vergadering heeft precies één soort. Bestaande vergaderingen van vóór
// deze indeling hebben nog geen 'soort' veld en worden als 'bestuur'
// behandeld, zodat ze gewoon blijven staan waar ze stonden.
function vergaderingenSoorten() {
  return [
    'bestuur' => 'Bestuursvergadering',
    'leden'   => 'Ledenvergadering',
  ];
}

// Status van de agenda en van de notulen, los van de status van de
// vergadering zelf. Alleen van toepassing bij soort 'leden': leden zien de
// agenda altijd, met dit label erbij zodat duidelijk is of er nog aan
// gesleuteld wordt, maar de notulen pas als die op definitief staan.
function vergaderingDocumentStatussen() {
  return [
    'concept'    => 'Concept',
    'definitief' => 'Definitief',
  ];
}

function vergaderingAgendaZichtbaarVoorLeden($v) {
  return (($v['soort'] ?? 'bestuur') === 'leden') && !empty($v['agenda']);
}

function vergaderingNotulenZichtbaarVoorLeden($v) {
  if (($v['soort'] ?? 'bestuur') !== 'leden') return false;
  if (trim((string) ($v['notulen'] ?? '')) === '') return false;
  return ($v['notulen_status'] ?? 'concept') === 'definitief';
}

function vergaderingenLedenTypes() {
  return [
    'regulier' => 'Ledenvergadering',
    'alv'      => 'ALV (jaarvergadering)',
  ];
}

// ===== Lezen en schrijven =====

function vergaderingenLeegBestand() {
  return ['updated' => date('c'), 'volgnummer' => 0, 'vergaderingen' => []];
}

function vergaderingenLees() {
  $json = legacyPrivateJsonLees(vergaderingenBestandPad(), 'vergaderingen', ['vergaderingen']);
  if ($json === null) return vergaderingenLeegBestand();
  $json['volgnummer'] = isset($json['volgnummer']) ? (int) $json['volgnummer'] : 0;
  return $json;
}

function vergaderingenMaakBackup($bewaardagen = 90, $maxAantal = 200) {
  $pad = vergaderingenBestandPad();
  if (!is_file($pad)) return;
  $map = ledenBackupMap();
  if (!is_dir($map) && !@mkdir($map, 0755, true)) return;
  @copy($pad, $map . '/' . date('Ymd-His') . '_vergaderingen-data.php');

  $bestanden = @glob($map . '/*_vergaderingen-data.php');
  if ($bestanden === false || count($bestanden) === 0) return;
  sort($bestanden);
  $grens = time() - $bewaardagen * 24 * 60 * 60;
  $over = [];
  foreach ($bestanden as $b) {
    $tijd = @filemtime($b);
    if ($tijd !== false && $tijd >= $grens) { $over[] = $b; } else { @unlink($b); }
  }
  $teveel = count($over) - $maxAantal;
  for ($i = 0; $i < $teveel; $i++) { @unlink($over[$i]); }
}

function vergaderingenSchrijf($data, $maakBackup = true) {
  if ($maakBackup) vergaderingenMaakBackup();
  $data['updated'] = date('c');
  $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  if ($json === false) return false;
  return file_put_contents(vergaderingenBestandPad(), VERGADERINGEN_VOORLOOP . $json, LOCK_EX) !== false;
}

function vergaderingNieuwId() {
  return 'verg_' . bin2hex(random_bytes(6));
}

function vergaderingVolgendNummer($data, $soort = 'bestuur') {
  $hoogste = 0;
  if ($soort === 'bestuur') $hoogste = (int) ($data['volgnummer'] ?? 0);
  foreach ($data['vergaderingen'] as $v) {
    $vSoort = ($v['soort'] ?? 'bestuur') === '' ? 'bestuur' : ($v['soort'] ?? 'bestuur');
    if ($vSoort !== $soort) continue;
    $n = (int) ($v['nummer'] ?? 0);
    if ($n > $hoogste) $hoogste = $n;
  }
  return $hoogste + 1;
}

function vergaderingenVanSoort($data, $soort, $oplopend = false) {
  $lijst = array_values(array_filter($data['vergaderingen'], function ($v) use ($soort) {
    $vSoort = ($v['soort'] ?? 'bestuur') === '' ? 'bestuur' : ($v['soort'] ?? 'bestuur');
    return $vSoort === $soort;
  }));
  usort($lijst, function ($a, $b) use ($oplopend) {
    $vergelijk = vergaderingSorteersleutel($a) <=> vergaderingSorteersleutel($b);
    return $oplopend ? $vergelijk : -$vergelijk;
  });
  return $lijst;
}

function vergaderingParseTijd($waarde) {
  $waarde = trim((string) $waarde);
  if ($waarde === '') return '';
  if (preg_match('/^(\d{1,2})[:.h ]?(\d{2})$/i', $waarde, $m)) {
    $uur = (int) $m[1];
    $minuut = (int) $m[2];
    if ($uur > 23 || $minuut > 59) return '';
    return sprintf('%02d:%02d', $uur, $minuut);
  }
  if (preg_match('/^(\d{1,2})$/', $waarde, $m)) {
    $uur = (int) $m[1];
    if ($uur > 23) return '';
    return sprintf('%02d:00', $uur);
  }
  return '';
}

function vergaderingSorteersleutel($v) {
  $datum = trim((string) ($v['datum'] ?? ''));
  if ($datum === '') return '0000-00-00 00:00';
  $tijd = trim((string) ($v['tijd'] ?? ''));
  return $datum . ' ' . ($tijd === '' ? '00:00' : $tijd);
}

function vergaderingenGesorteerd($data, $oplopend = false) {
  $lijst = $data['vergaderingen'];
  usort($lijst, function ($a, $b) use ($oplopend) {
    $vergelijk = vergaderingSorteersleutel($a) <=> vergaderingSorteersleutel($b);
    return $oplopend ? $vergelijk : -$vergelijk;
  });
  return $lijst;
}

function vergaderingWeergavenaam($v) {
  $titel = trim((string) ($v['titel'] ?? ''));
  if ($titel !== '') return $titel;
  $soort = ($v['soort'] ?? 'bestuur') === '' ? 'bestuur' : ($v['soort'] ?? 'bestuur');
  if ($soort === 'leden') {
    $prefix = ($v['ledenvergadering_type'] ?? '') === 'alv' ? 'ALV' : 'Ledenvergadering';
  } else {
    $prefix = 'Vergadering';
  }
  $datum = trim((string) ($v['datum'] ?? ''));
  return $datum === '' ? $prefix . ' zonder datum' : $prefix . ' ' . $datum;
}

function vergaderingVeldGrenzen() {
  return ['titel' => 120, 'locatie' => 120];
}

function vergaderingNormaliseer($invoer, $bestaand = null) {
  $v = is_array($bestaand) ? $bestaand : [];
  foreach (vergaderingVeldGrenzen() as $veld => $max) {
    if (array_key_exists($veld, $invoer)) $v[$veld] = ledenKort($invoer[$veld], $max);
    elseif (!isset($v[$veld])) $v[$veld] = '';
  }
  if (array_key_exists('datum', $invoer)) $v['datum'] = ledenParseDatum($invoer['datum']);
  elseif (!isset($v['datum'])) $v['datum'] = '';
  if (array_key_exists('tijd', $invoer)) $v['tijd'] = vergaderingParseTijd($invoer['tijd']);
  elseif (!isset($v['tijd'])) $v['tijd'] = '';

  $statussen = vergaderingenStatussen();
  if (array_key_exists('status', $invoer) && isset($statussen[$invoer['status']])) $v['status'] = $invoer['status'];
  elseif (!isset($v['status']) || !isset($statussen[$v['status']])) $v['status'] = 'gepland';

  $soorten = vergaderingenSoorten();
  if (!isset($v['soort']) || !isset($soorten[$v['soort']])) {
    $v['soort'] = array_key_exists('soort', $invoer) && isset($soorten[$invoer['soort']]) ? $invoer['soort'] : 'bestuur';
  }
  $ledenTypes = vergaderingenLedenTypes();
  if ($v['soort'] === 'leden') {
    if (array_key_exists('ledenvergadering_type', $invoer) && isset($ledenTypes[$invoer['ledenvergadering_type']])) $v['ledenvergadering_type'] = $invoer['ledenvergadering_type'];
    elseif (!isset($v['ledenvergadering_type']) || !isset($ledenTypes[$v['ledenvergadering_type']])) $v['ledenvergadering_type'] = 'regulier';
  } else $v['ledenvergadering_type'] = '';

  $docStatussen = vergaderingDocumentStatussen();
  if ($v['soort'] === 'leden') {
    foreach (['agenda_status', 'notulen_status'] as $veld) {
      if (array_key_exists($veld, $invoer) && isset($docStatussen[$invoer[$veld]])) $v[$veld] = $invoer[$veld];
      elseif (!isset($v[$veld]) || !isset($docStatussen[$v[$veld]])) $v[$veld] = 'concept';
    }
  } else {
    $v['agenda_status'] = '';
    $v['notulen_status'] = '';
  }

  if (array_key_exists('notulen', $invoer)) {
    $tekst = trim((string) $invoer['notulen']);
    $tekst = preg_replace('/\R/u', "\n", $tekst);
    $v['notulen'] = function_exists('mb_substr') ? mb_substr($tekst, 0, 20000, 'UTF-8') : substr($tekst, 0, 20000);
  } elseif (!isset($v['notulen'])) $v['notulen'] = '';

  if (array_key_exists('agenda', $invoer)) $v['agenda'] = vergaderingAgendaOpschonen($invoer['agenda']);
  elseif (!isset($v['agenda']) || !is_array($v['agenda'])) $v['agenda'] = [];
  if (array_key_exists('aanwezigheid', $invoer)) $v['aanwezigheid'] = vergaderingAanwezigheidOpschonen($invoer['aanwezigheid']);
  elseif (!isset($v['aanwezigheid']) || !is_array($v['aanwezigheid'])) $v['aanwezigheid'] = [];

  if (!isset($v['id']) || $v['id'] === '') $v['id'] = vergaderingNieuwId();
  if (!isset($v['nummer'])) $v['nummer'] = 0;
  if (!isset($v['aangemaakt'])) $v['aangemaakt'] = date('c');
  if (!isset($v['aangemaakt_door'])) $v['aangemaakt_door'] = '';
  $v['gewijzigd'] = date('c');
  return $v;
}

function vergaderingAgendaOpschonen($ruw) {
  if (!is_array($ruw)) return [];
  $punten = [];
  foreach ($ruw as $punt) {
    if (!is_array($punt) || !empty($punt['verwijderen'])) continue;
    $onderwerp = ledenKort($punt['onderwerp'] ?? '', 160);
    if ($onderwerp === '') continue;
    $toelichting = preg_replace('/\R/u', "\n", trim((string) ($punt['toelichting'] ?? '')));
    $besluit = preg_replace('/\R/u', "\n", trim((string) ($punt['besluit'] ?? '')));
    $punten[] = [
      'onderwerp' => $onderwerp,
      'indiener' => ledenKort($punt['indiener'] ?? '', 80),
      'toelichting' => function_exists('mb_substr') ? mb_substr($toelichting, 0, 4000, 'UTF-8') : substr($toelichting, 0, 4000),
      'besluit' => function_exists('mb_substr') ? mb_substr($besluit, 0, 4000, 'UTF-8') : substr($besluit, 0, 4000),
    ];
  }
  return $punten;
}

function vergaderingAanwezigheidOpschonen($ruw) {
  if (!is_array($ruw)) return [];
  $geldig = vergaderingenAanwezigheid();
  $uit = [];
  foreach ($ruw as $lidId => $keuze) {
    $lidId = ledenKort($lidId, 40);
    if ($lidId === '' || !is_string($keuze) || !isset($geldig[$keuze])) continue;
    $uit[$lidId] = $keuze;
  }
  return $uit;
}

function vergaderingAanwezigheidTelling($v) {
  $telling = [];
  foreach (array_keys(vergaderingenAanwezigheid()) as $sleutel) $telling[$sleutel] = 0;
  foreach ((isset($v['aanwezigheid']) && is_array($v['aanwezigheid']) ? $v['aanwezigheid'] : []) as $keuze) {
    if (isset($telling[$keuze])) $telling[$keuze]++;
  }
  return $telling;
}
