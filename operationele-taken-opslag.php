<?php
// ============================================================
// RC045 operationele taken: opslag en hulpfuncties
// ------------------------------------------------------------
// Terugkerende klussen die de club sowieso moet doen (bv. gras maaien,
// EHBO-kist controleren), los van de bestuurstaken in taken-opslag.php.
// Kunnen aan een lid worden toegewezen en hebben een uitvoeringsfrequentie.
// Een taak kan zichtbaar zijn voor alle leden, of alleen voor bestuursleden
// (bv. gevoelige klussen). Zelfde opzet als taken-opslag.php: alleen
// functies, schrijft zelf niets naar het scherm. Wordt gebruikt door
// beheer.php, tabblad Operationele taken.
//
// PRIVACY. Het bestand staat BEWUST NIET in data/, want die map is publiek
// opvraagbaar, en een deel van de taken is alleen voor bestuursleden
// bedoeld. Het heet operationele-taken-data.php en begint met een regel
// PHP die de uitvoer meteen afbreekt: wordt het ooit rechtstreeks
// opgevraagd, dan voert de server het uit als PHP en krijgt de bezoeker
// een lege pagina in plaats van de takenlijst. Zet het bestand er
// daarnaast bij in .htaccess (Require all denied). Let op dat de deploy
// dotfiles overslaat, dus die .htaccess gaat met de hand via FTP.
// ============================================================

require_once __DIR__ . '/leden-opslag.php';

define('OTAKEN_VOORLOOP', "<?php exit; ?>\n");

function otaakBestandPad() {
  return __DIR__ . '/operationele-taken-data.php';
}

// ===== Frequenties en zichtbaarheid =====

function otaakFrequenties() {
  return [
    'dagelijks'     => 'Dagelijks',
    'wekelijks'     => 'Wekelijks',
    'maandelijks'   => 'Maandelijks',
    'per_kwartaal'  => 'Per kwartaal',
    'halfjaarlijks' => 'Halfjaarlijks',
    'jaarlijks'     => 'Jaarlijks',
    'naar_behoefte' => 'Naar behoefte',
  ];
}

function otaakZichtbaarheden() {
  return [
    'leden'   => 'Leden',
    'bestuur' => 'Bestuursleden',
  ];
}

// Aantal dagen dat bij elke frequentie hoort, om de volgende uitvoerdatum
// mee te berekenen. 'naar_behoefte' staat er bewust niet in: die heeft
// geen vaste volgende datum.
function otaakFrequentieDagen() {
  return [
    'dagelijks'     => 1,
    'wekelijks'     => 7,
    'maandelijks'   => 30,
    'per_kwartaal'  => 91,
    'halfjaarlijks' => 182,
    'jaarlijks'     => 365,
  ];
}

// Volgende uitvoerdatum (ISO) op basis van de frequentie, vanaf een
// gegeven datum (ISO, meestal vandaag). Lege string als de frequentie
// geen vaste datum kent of ongeldig is.
function otaakVolgendeUitvoering($frequentie, $vanafIso) {
  $dagen = otaakFrequentieDagen();
  if (!isset($dagen[$frequentie])) return '';
  $tijd = strtotime((string) $vanafIso);
  if ($tijd === false) return '';
  return date('Y-m-d', strtotime('+' . $dagen[$frequentie] . ' days', $tijd));
}

// ===== Lezen en schrijven =====

function otakenLeegBestand() {
  return ['updated' => date('c'), 'volgnummer' => 0, 'taken' => []];
}

function otakenLees() {
  $pad = otaakBestandPad();
  if (!is_file($pad)) return otakenLeegBestand();
  $ruw = file_get_contents($pad);
  if ($ruw === false) return otakenLeegBestand();
  $start = strpos($ruw, '{');
  if ($start === false) return otakenLeegBestand();
  $json = json_decode(substr($ruw, $start), true);
  if (!is_array($json) || !isset($json['taken']) || !is_array($json['taken'])) {
    return otakenLeegBestand();
  }
  $json['volgnummer'] = isset($json['volgnummer']) ? (int) $json['volgnummer'] : 0;
  return $json;
}

// Tijdgestempelde kopie in dezelfde map en met dezelfde bewaartermijn als
// de andere back-ups, zodat een per ongeluk gewiste taak terug te halen is.
function otakenMaakBackup($bewaardagen = 90, $maxAantal = 200) {
  $pad = otaakBestandPad();
  if (!is_file($pad)) return;
  $map = ledenBackupMap();
  if (!is_dir($map) && !@mkdir($map, 0755, true)) return;
  @copy($pad, $map . '/' . date('Ymd-His') . '_operationele-taken-data.php');

  $bestanden = @glob($map . '/*_operationele-taken-data.php');
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

function otakenSchrijf($data, $maakBackup = true) {
  if ($maakBackup) otakenMaakBackup();
  $data['updated'] = date('c');
  $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  if ($json === false) return false;
  return file_put_contents(otaakBestandPad(), OTAKEN_VOORLOOP . $json, LOCK_EX) !== false;
}

// ===== Kleine hulpjes =====

function otaakNieuwId() {
  return 'otaak_' . bin2hex(random_bytes(6));
}

function otaakVolgendNummer($data) {
  $hoogste = (int) ($data['volgnummer'] ?? 0);
  foreach ($data['taken'] as $t) {
    $n = (int) ($t['nummer'] ?? 0);
    if ($n > $hoogste) $hoogste = $n;
  }
  return $hoogste + 1;
}

function otaakWeergavenaam($t) {
  $omschrijving = trim((string) ($t['omschrijving'] ?? ''));
  return $omschrijving === '' ? 'Taak zonder omschrijving' : $omschrijving;
}

// Actuele status, afgeleid in plaats van los opgeslagen: zo kan het nooit
// tegenstrijdig raken met de datums. 'gepauzeerd' wint altijd, daarna
// 'te_doen' (nog nooit gedaan, of de volgende datum is vandaag of voorbij)
// en anders 'gepland'.
function otaakStatus($t) {
  if (empty($t['actief'])) return 'gepauzeerd';
  $volgende = trim((string) ($t['volgende_uitvoering'] ?? ''));
  $laatst = trim((string) ($t['laatst_uitgevoerd'] ?? ''));
  if ($laatst === '') return 'te_doen';
  if ($volgende === '') return 'gepland'; // naar_behoefte, al eens gedaan: geen druk, gewoon "gepland" zonder datum
  return $volgende <= date('Y-m-d') ? 'te_doen' : 'gepland';
}

function otaakStatusLabels() {
  return [
    'te_doen'    => 'Te doen',
    'gepland'    => 'Gepland',
    'gepauzeerd' => 'Gepauzeerd',
  ];
}

// Open/te doen boven gepland boven gepauzeerd, daarbinnen nieuwste eerst.
function otakenGesorteerd($data) {
  $volgorde = ['te_doen' => 0, 'gepland' => 1, 'gepauzeerd' => 2];
  $lijst = $data['taken'];
  usort($lijst, function ($a, $b) use ($volgorde) {
    $sa = $volgorde[otaakStatus($a)] ?? 3;
    $sb = $volgorde[otaakStatus($b)] ?? 3;
    if ($sa !== $sb) return $sa <=> $sb;
    return ((int) ($b['nummer'] ?? 0)) <=> ((int) ($a['nummer'] ?? 0));
  });
  return $lijst;
}

// Een taak afmelden: logt de uitvoering en berekent (indien van toepassing)
// meteen de volgende datum. Geschiedenis blijft beperkt tot de laatste 20
// regels, nieuwste eerst, anders groeit het bestand ongelimiteerd door.
function otaakMarkeerUitgevoerd($t, $door) {
  $vandaag = date('Y-m-d');
  if (!isset($t['geschiedenis']) || !is_array($t['geschiedenis'])) $t['geschiedenis'] = [];
  array_unshift($t['geschiedenis'], ['datum' => $vandaag, 'door' => $door]);
  $t['geschiedenis'] = array_slice($t['geschiedenis'], 0, 20);
  $t['laatst_uitgevoerd'] = $vandaag;
  $t['laatst_uitgevoerd_door'] = $door;
  $t['volgende_uitvoering'] = otaakVolgendeUitvoering($t['frequentie'] ?? '', $vandaag);
  $t['gewijzigd'] = date('c');
  return $t;
}

// ===== Invoer opschonen =====

function otaakVeldGrenzen() {
  return ['omschrijving' => 200];
}

function otaakNormaliseer($invoer, $bestaand = null) {
  $t = is_array($bestaand) ? $bestaand : [];

  foreach (otaakVeldGrenzen() as $veld => $max) {
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

  $frequenties = otaakFrequenties();
  if (array_key_exists('frequentie', $invoer) && isset($frequenties[$invoer['frequentie']])) {
    $t['frequentie'] = $invoer['frequentie'];
  } elseif (!isset($t['frequentie']) || !isset($frequenties[$t['frequentie']])) {
    $t['frequentie'] = 'maandelijks';
  }

  $zichtbaarheden = otaakZichtbaarheden();
  if (array_key_exists('zichtbaarheid', $invoer) && isset($zichtbaarheden[$invoer['zichtbaarheid']])) {
    $t['zichtbaarheid'] = $invoer['zichtbaarheid'];
  } elseif (!isset($t['zichtbaarheid']) || !isset($zichtbaarheden[$t['zichtbaarheid']])) {
    $t['zichtbaarheid'] = 'leden';
  }

  // Toegewezen aan: het lid dat de taak oppakt. Alleen de sleutel, of het
  // lid nog echt bestaat controleert de aanroeper.
  if (array_key_exists('toegewezen_aan', $invoer)) {
    $t['toegewezen_aan'] = ledenKort($invoer['toegewezen_aan'], 40);
  } elseif (!isset($t['toegewezen_aan'])) {
    $t['toegewezen_aan'] = '';
  }

  // Actief (checkbox): niet aangevinkt komt niet binnen in $_POST, dus
  // afwezigheid in $invoer betekent hier bewust "uit" en niet "ongewijzigd
  // laten", in tegenstelling tot de andere velden hierboven.
  if (array_key_exists('actief', $invoer)) {
    $t['actief'] = !empty($invoer['actief']);
  } elseif (!isset($t['actief'])) {
    $t['actief'] = true;
  }

  // Uitvoeringsgegevens: alleen aanraken via otaakMarkeerUitgevoerd(),
  // hier alleen zorgen dat de velden bestaan.
  if (!isset($t['laatst_uitgevoerd'])) $t['laatst_uitgevoerd'] = '';
  if (!isset($t['laatst_uitgevoerd_door'])) $t['laatst_uitgevoerd_door'] = '';
  if (!isset($t['volgende_uitvoering'])) $t['volgende_uitvoering'] = '';
  if (!isset($t['geschiedenis']) || !is_array($t['geschiedenis'])) $t['geschiedenis'] = [];

  if (!isset($t['id']) || $t['id'] === '') $t['id'] = otaakNieuwId();
  if (!isset($t['nummer'])) $t['nummer'] = 0;
  if (!isset($t['aangemaakt'])) $t['aangemaakt'] = date('c');
  if (!isset($t['aangemaakt_door'])) $t['aangemaakt_door'] = '';
  $t['gewijzigd'] = date('c');

  return $t;
}
