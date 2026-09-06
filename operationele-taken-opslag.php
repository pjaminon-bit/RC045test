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
// STANDALONE COMPATIBILITEIT / PRIVACY. operationele-taken-data.php is het
// legacy PHP+JSON-formaat voor losse installaties. De PHP-voorloop blokkeert
// directe uitvoer; op Apache kan de repository-.htaccess aanvullend deny-en.
// Dit is geen VPS-opslag- of deploycontract: nieuwe multi-tenant VPS-tenants
// gebruiken de tenant-private storagegrens uit docs/PROVISIONING.md en
// docs/VPS-DEPLOYMENT.md. .htaccess wordt door de releaseflow meegenomen;
// er is geen handmatige FTP-stap voor VPS-deployments.
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

// Alleen dagelijks en wekelijks zijn vaste datumstappen. Kalenderfrequenties
// blijven bewust gekoppeld aan kalendermaanden in plaats van dagenaantallen.
function otaakVasteDagFrequenties() {
  return [
    'dagelijks' => 1,
    'wekelijks' => 7,
  ];
}

function otaakKalenderFrequentieMaanden() {
  return [
    'maandelijks'   => 1,
    'per_kwartaal'  => 3,
    'halfjaarlijks' => 6,
    'jaarlijks'     => 12,
  ];
}

function otaakIsoDatumDelen($iso) {
  $iso = trim((string) $iso);
  if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $iso, $m)) return null;
  $jaar = (int) $m[1];
  $maand = (int) $m[2];
  $dag = (int) $m[3];
  if (!checkdate($maand, $dag, $jaar)) return null;
  return [$jaar, $maand, $dag];
}

// Volgende uitvoerdatum (ISO) op basis van een kalenderdatum. Dagelijks en
// wekelijks gebruiken vaste datumstappen; maand/kwartaal/halfjaar/jaar voegen
// echte kalendermaanden toe. Het anker bewaart de oorspronkelijke dag van de
// maand: 31 januari -> laatste dag februari -> 31 maart, dus geen permanente
// drift door een korte doelmaand. Datumrekenen gebeurt in UTC op date-only
// waarden zodat server-timezone en DST geen invloed hebben.
function otaakVolgendeUitvoering($frequentie, $vanafIso, $kalenderAnkerDag = null) {
  $frequentie = (string) $frequentie;
  $vanafIso = trim((string) $vanafIso);
  $frequenties = otaakFrequenties();
  if (!array_key_exists($frequentie, $frequenties)) {
    throw new InvalidArgumentException('Ongeldige frequentie voor operationele taak.');
  }
  if ($frequentie === 'naar_behoefte') return '';

  $delen = otaakIsoDatumDelen($vanafIso);
  if ($delen === null) throw new InvalidArgumentException('Ongeldige uitvoerdatum voor operationele taak.');

  $vasteDagen = otaakVasteDagFrequenties();
  if (isset($vasteDagen[$frequentie])) {
    $datum = DateTimeImmutable::createFromFormat('!Y-m-d', $vanafIso, new DateTimeZone('UTC'));
    if ($datum === false) throw new InvalidArgumentException('Ongeldige uitvoerdatum voor operationele taak.');
    return $datum->modify('+' . $vasteDagen[$frequentie] . ' days')->format('Y-m-d');
  }

  $kalenderMaanden = otaakKalenderFrequentieMaanden();
  if (!isset($kalenderMaanden[$frequentie])) {
    throw new InvalidArgumentException('Ongeldige kalenderfrequentie voor operationele taak.');
  }

  $ankerDag = $kalenderAnkerDag === null ? $delen[2] : (int) $kalenderAnkerDag;
  if ($ankerDag < 1 || $ankerDag > 31) {
    throw new InvalidArgumentException('Ongeldig kalenderanker voor operationele taak.');
  }

  [$jaar, $maand] = $delen;
  $maandIndex = ($jaar * 12) + ($maand - 1) + $kalenderMaanden[$frequentie];
  $doelJaar = intdiv($maandIndex, 12);
  $doelMaand = ($maandIndex % 12) + 1;
  $doelDag = $ankerDag;
  while ($doelDag > 1 && !checkdate($doelMaand, $doelDag, $doelJaar)) $doelDag--;
  if (!checkdate($doelMaand, $doelDag, $doelJaar)) {
    throw new InvalidArgumentException('Kalenderdatum kon niet veilig worden berekend.');
  }
  return sprintf('%04d-%02d-%02d', $doelJaar, $doelMaand, $doelDag);
}

// ===== Lezen en schrijven =====

function otakenLeegBestand() {
  return ['updated' => date('c'), 'volgnummer' => 0, 'taken' => []];
}

function otakenLees() {
  $json = legacyPrivateJsonLees(otaakBestandPad(), 'operationele_taken', ['taken']);
  if ($json === null) return otakenLeegBestand();
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

function otaakKalenderAnkerDagVoorUitvoering($t, $uitgevoerdOp) {
  if (array_key_exists('kalender_anker_dag', $t)) {
    $anker = $t['kalender_anker_dag'];
    if (!is_int($anker) && !(is_string($anker) && preg_match('/^\d{1,2}$/D', $anker))) {
      throw new InvalidArgumentException('Ongeldig opgeslagen kalenderanker voor operationele taak.');
    }
    $anker = (int) $anker;
    if ($anker < 1 || $anker > 31) {
      throw new InvalidArgumentException('Ongeldig opgeslagen kalenderanker voor operationele taak.');
    }
    return $anker;
  }

  // Bestaande records hebben nog geen ankermetadata. Gebruik conservatief de
  // vorige echte uitvoering als anker wanneer die geldig is; anders de eerste
  // uitvoering na de upgrade. Historie en bestaande volgende datum blijven
  // onaangeraakt totdat de taak opnieuw wordt uitgevoerd.
  $vorige = otaakIsoDatumDelen($t['laatst_uitgevoerd'] ?? '');
  if ($vorige !== null) return $vorige[2];
  $huidige = otaakIsoDatumDelen($uitgevoerdOp);
  if ($huidige === null) throw new InvalidArgumentException('Ongeldige uitvoerdatum voor operationele taak.');
  return $huidige[2];
}

// Een taak afmelden: logt de uitvoering en berekent (indien van toepassing)
// meteen de volgende datum. Geschiedenis blijft beperkt tot de laatste 20
// regels, nieuwste eerst, anders groeit het bestand ongelimiteerd door.
// De optionele datumparameter is uitsluitend voor deterministische tests en
// interne callers; de beheeractie laat hem weg en gebruikt dus vandaag.
function otaakMarkeerUitgevoerd($t, $door, $uitgevoerdOp = null) {
  $frequentie = (string) ($t['frequentie'] ?? '');
  if (!array_key_exists($frequentie, otaakFrequenties())) {
    throw new InvalidArgumentException('Ongeldige frequentie voor operationele taak.');
  }

  $vandaag = $uitgevoerdOp === null ? date('Y-m-d') : trim((string) $uitgevoerdOp);
  if (otaakIsoDatumDelen($vandaag) === null) {
    throw new InvalidArgumentException('Ongeldige uitvoerdatum voor operationele taak.');
  }

  $ankerDag = null;
  if (array_key_exists($frequentie, otaakKalenderFrequentieMaanden())) {
    $ankerDag = otaakKalenderAnkerDagVoorUitvoering($t, $vandaag);
    $t['kalender_anker_dag'] = $ankerDag;
  }

  if (!isset($t['geschiedenis']) || !is_array($t['geschiedenis'])) $t['geschiedenis'] = [];
  array_unshift($t['geschiedenis'], ['datum' => $vandaag, 'door' => $door]);
  $t['geschiedenis'] = array_slice($t['geschiedenis'], 0, 20);
  $t['laatst_uitgevoerd'] = $vandaag;
  $t['laatst_uitgevoerd_door'] = $door;
  $t['volgende_uitvoering'] = otaakVolgendeUitvoering($frequentie, $vandaag, $ankerDag);
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
  if (array_key_exists('frequentie', $invoer)) {
    $nieuw = (string) $invoer['frequentie'];
    if (!array_key_exists($nieuw, $frequenties)) {
      throw new InvalidArgumentException('Ongeldige frequentie voor operationele taak.');
    }
    $oud = isset($t['frequentie']) ? (string) $t['frequentie'] : null;
    $t['frequentie'] = $nieuw;
    if ($oud !== null && $oud !== $nieuw) unset($t['kalender_anker_dag']);
  } elseif (!isset($t['frequentie'])) {
    $t['frequentie'] = 'maandelijks';
  } elseif (!array_key_exists((string) $t['frequentie'], $frequenties)) {
    throw new InvalidArgumentException('Bestaande operationele taak heeft een ongeldige frequentie.');
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
