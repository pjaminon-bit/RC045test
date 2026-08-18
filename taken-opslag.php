<?php
// ============================================================
// RC045 takenlijst (bestuur): opslag en hulpfuncties
// ------------------------------------------------------------
// Zelfde opzet als vergaderingen-opslag.php: alleen functies, schrijft
// zelf niets naar het scherm. Wordt gebruikt door beheer.php, tabblad
// Takenlijst.
//
// PRIVACY. Net als de vergaderingen zijn dit interne bestuurszaken. Het
// bestand staat daarom BEWUST NIET in data/, want die map is publiek
// opvraagbaar. Het heet taken-data.php en begint met een regel PHP die de
// uitvoer meteen afbreekt: wordt het ooit rechtstreeks opgevraagd, dan
// voert de server het uit als PHP en krijgt de bezoeker een lege pagina in
// plaats van de takenlijst. Zet het bestand er daarnaast bij in
// .htaccess (Require all denied). Let op dat de deploy dotfiles overslaat,
// dus die .htaccess gaat met de hand via FTP.
// ============================================================

require_once __DIR__ . '/leden-opslag.php';
require_once __DIR__ . '/vergaderingen-opslag.php';

define('TAKEN_VOORLOOP', "<?php exit; ?>\n");

function takenBestandPad() {
  return __DIR__ . '/taken-data.php';
}

// ===== Statussen =====

function takenStatussen() {
  return [
    'open'           => 'Open',
    'in_behandeling' => 'In behandeling',
    'afgerond'       => 'Afgerond',
  ];
}

// ===== Lezen en schrijven =====

function takenLeegBestand() {
  return ['updated' => date('c'), 'volgnummer' => 0, 'taken' => []];
}

function takenLees() {
  $pad = takenBestandPad();
  if (!is_file($pad)) return takenLeegBestand();
  $ruw = file_get_contents($pad);
  if ($ruw === false) return takenLeegBestand();
  $start = strpos($ruw, '{');
  if ($start === false) return takenLeegBestand();
  $json = json_decode(substr($ruw, $start), true);
  if (!is_array($json) || !isset($json['taken']) || !is_array($json['taken'])) {
    return takenLeegBestand();
  }
  $json['volgnummer'] = isset($json['volgnummer']) ? (int) $json['volgnummer'] : 0;
  return $json;
}

// Tijdgestempelde kopie in dezelfde map en met dezelfde bewaartermijn als
// de andere back-ups, zodat een per ongeluk gewiste taak terug te halen is.
function takenMaakBackup($bewaardagen = 90, $maxAantal = 200) {
  $pad = takenBestandPad();
  if (!is_file($pad)) return;
  $map = ledenBackupMap();
  if (!is_dir($map) && !@mkdir($map, 0755, true)) return;
  @copy($pad, $map . '/' . date('Ymd-His') . '_taken-data.php');

  $bestanden = @glob($map . '/*_taken-data.php');
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

function takenSchrijf($data, $maakBackup = true) {
  if ($maakBackup) takenMaakBackup();
  $data['updated'] = date('c');
  $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  if ($json === false) return false;
  return file_put_contents(takenBestandPad(), TAKEN_VOORLOOP . $json, LOCK_EX) !== false;
}

// ===== Kleine hulpjes =====

function taakNieuwId() {
  return 'taak_' . bin2hex(random_bytes(6));
}

function taakVolgendNummer($data) {
  $hoogste = (int) ($data['volgnummer'] ?? 0);
  foreach ($data['taken'] as $t) {
    $n = (int) ($t['nummer'] ?? 0);
    if ($n > $hoogste) $hoogste = $n;
  }
  return $hoogste + 1;
}

// Titel voor in de lijst. Zonder omschrijving is er weinig zinnigs te
// tonen, dus dan staat er gewoon dat die ontbreekt.
function taakWeergavenaam($t) {
  $omschrijving = trim((string) ($t['omschrijving'] ?? ''));
  return $omschrijving === '' ? 'Taak zonder omschrijving' : $omschrijving;
}

// Open en in behandeling boven afgerond, daarbinnen nieuwste eerst. Zo
// staan de taken die nog iets van je vragen bovenaan.
function takenGesorteerd($data) {
  $lijst = $data['taken'];
  usort($lijst, function ($a, $b) {
    $klaarA = ($a['status'] ?? '') === 'afgerond' ? 1 : 0;
    $klaarB = ($b['status'] ?? '') === 'afgerond' ? 1 : 0;
    if ($klaarA !== $klaarB) return $klaarA <=> $klaarB;
    return ((int) ($b['nummer'] ?? 0)) <=> ((int) ($a['nummer'] ?? 0));
  });
  return $lijst;
}

// Leesbare tekst voor de koppeling met een vergadering, bijvoorbeeld
// "Besproken in vergadering 12" of "Besproken in ALV 2 (18-03-2026)".
// $vergaderingenBijId is id => vergadering, éénmalig opgebouwd door de
// aanroeper zodat dit niet steeds het hele bestand hoeft in te lezen.
function taakVergaderingTekst($taak, $vergaderingenBijId) {
  $id = trim((string) ($taak['vergadering_id'] ?? ''));
  if ($id === '' || !isset($vergaderingenBijId[$id])) return '';
  $v = $vergaderingenBijId[$id];
  $soort = ($v['soort'] ?? 'bestuur') === '' ? 'bestuur' : ($v['soort'] ?? 'bestuur');
  $nummer = (int) ($v['nummer'] ?? 0);
  if ($soort === 'leden') {
    $label = ($v['ledenvergadering_type'] ?? '') === 'alv' ? 'ALV' : 'ledenvergadering';
    $tekst = 'Besproken in ' . $label . ($nummer > 0 ? ' ' . $nummer : '');
  } else {
    $tekst = 'Besproken in vergadering' . ($nummer > 0 ? ' ' . $nummer : '');
  }
  $datum = trim((string) ($v['datum'] ?? ''));
  // Eigen kleine ISO -> dd-mm-jjjj omzetting in plaats van een aanroep naar
  // datumWeergave() uit beheer.php: dit bestand hoort net als
  // vergaderingen-opslag.php op zichzelf te werken, alleen leunend op
  // leden-opslag.php en vergaderingen-opslag.php.
  if ($datum !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $datum, $m)) {
    $tekst .= ' (' . $m[3] . '-' . $m[2] . '-' . $m[1] . ')';
  }
  return $tekst;
}

// ===== Invoer opschonen =====

function taakVeldGrenzen() {
  return ['omschrijving' => 200];
}

function taakNormaliseer($invoer, $bestaand = null) {
  $t = is_array($bestaand) ? $bestaand : [];

  foreach (taakVeldGrenzen() as $veld => $max) {
    if (array_key_exists($veld, $invoer)) {
      $t[$veld] = ledenKort($invoer[$veld], $max);
    } elseif (!isset($t[$veld])) {
      $t[$veld] = '';
    }
  }

  if (array_key_exists('toelichting', $invoer)) {
    $tekst = trim((string) $invoer['toelichting']);
    $tekst = preg_replace('/\R/u', "\n", $tekst);
    $t['toelichting'] = function_exists('mb_substr') ? mb_substr($tekst, 0, 4000, 'UTF-8') : substr($tekst, 0, 4000);
  } elseif (!isset($t['toelichting'])) {
    $t['toelichting'] = '';
  }

  $statussen = takenStatussen();
  if (array_key_exists('status', $invoer) && isset($statussen[$invoer['status']])) {
    $t['status'] = $invoer['status'];
  } elseif (!isset($t['status']) || !isset($statussen[$t['status']])) {
    $t['status'] = 'open';
  }

  // Koppeling met een vergadering: soort ('bestuur' of 'leden', bepaalt in
  // welk register vergadering_id wordt opgezocht) plus het id zelf. Zonder
  // soort is er ook geen geldige koppeling, dan valt vergadering_id altijd
  // leeg terug.
  if (array_key_exists('vergadering_soort', $invoer)) {
    $soort = trim((string) $invoer['vergadering_soort']);
    $t['vergadering_soort'] = in_array($soort, ['bestuur', 'leden'], true) ? $soort : '';
  } elseif (!isset($t['vergadering_soort'])) {
    $t['vergadering_soort'] = '';
  }

  if (array_key_exists('vergadering_id', $invoer)) {
    $t['vergadering_id'] = ledenKort($invoer['vergadering_id'], 40);
  } elseif (!isset($t['vergadering_id'])) {
    $t['vergadering_id'] = '';
  }
  if ($t['vergadering_soort'] === '') $t['vergadering_id'] = '';

  // Koppeling met een commissie: alleen de sleutel, of de commissie ooit
  // echt bestaat controleert de aanroeper (die de commissielijst al bij de
  // hand heeft).
  if (array_key_exists('commissie_id', $invoer)) {
    $t['commissie_id'] = ledenKort($invoer['commissie_id'], 40);
  } elseif (!isset($t['commissie_id'])) {
    $t['commissie_id'] = '';
  }

  // Toegewezen aan: het lid dat de taak oppakt. Alleen de sleutel, of het
  // lid nog echt bestaat controleert de aanroeper (net als bij commissie_id).
  if (array_key_exists('toegewezen_aan', $invoer)) {
    $t['toegewezen_aan'] = ledenKort($invoer['toegewezen_aan'], 40);
  } elseif (!isset($t['toegewezen_aan'])) {
    $t['toegewezen_aan'] = '';
  }

  if (!isset($t['id']) || $t['id'] === '') $t['id'] = taakNieuwId();
  if (!isset($t['nummer'])) $t['nummer'] = 0;
  if (!isset($t['aangemaakt'])) $t['aangemaakt'] = date('c');
  if (!isset($t['aangemaakt_door'])) $t['aangemaakt_door'] = '';
  $t['gewijzigd'] = date('c');

  return $t;
}
