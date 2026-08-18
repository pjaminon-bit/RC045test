<?php
// ============================================================
// RC045 evenementen: opslag en hulpfuncties
// ------------------------------------------------------------
// Activiteiten waarvoor leden zich kunnen inschrijven (bv. een clubdag,
// BBQ, wedstrijd). Voor nu beheert het bestuur de deelnemerslijst zelf in
// dit tabblad, net als de presentielijst bij de ledenvergadering. Zodra er
// een ledenportaal is, kan een lid zich daar straks ook zelf aan- of
// afmelden: dat schrijft dan naar dezelfde 'deelnemers'-lijst hieronder,
// dus deze opzet is daar al klaar voor.
//
// Een evenement kan zichtbaar zijn voor alle leden, of alleen voor
// bestuursleden (bv. een bestuursuitje). Zelfde opzet als
// operationele-taken-opslag.php: alleen functies, schrijft zelf niets naar
// het scherm. Wordt gebruikt door beheer.php, tabblad Evenementen.
//
// PRIVACY. Het bestand staat BEWUST NIET in data/, want die map is publiek
// opvraagbaar, en een deel van de evenementen is alleen voor bestuursleden
// bedoeld (en de deelnemerslijst is sowieso geen publieke informatie). Het
// heet evenementen-data.php en begint met een regel PHP die de uitvoer
// meteen afbreekt: wordt het ooit rechtstreeks opgevraagd, dan voert de
// server het uit als PHP en krijgt de bezoeker een lege pagina in plaats
// van de evenementenlijst. Zet het bestand er daarnaast bij in .htaccess
// (Require all denied). Let op dat de deploy dotfiles overslaat, dus die
// .htaccess gaat met de hand via FTP.
// ============================================================

require_once __DIR__ . '/leden-opslag.php';

define('EVENEMENTEN_VOORLOOP', "<?php exit; ?>\n");

function evenementBestandPad() {
  return __DIR__ . '/evenementen-data.php';
}

// ===== Zichtbaarheid =====

function evenementZichtbaarheden() {
  return [
    'leden'   => 'Leden',
    'bestuur' => 'Bestuursleden',
  ];
}

// ===== Lezen en schrijven =====

function evenementenLeegBestand() {
  return ['updated' => date('c'), 'volgnummer' => 0, 'evenementen' => []];
}

function evenementenLees() {
  $pad = evenementBestandPad();
  if (!is_file($pad)) return evenementenLeegBestand();
  $ruw = file_get_contents($pad);
  if ($ruw === false) return evenementenLeegBestand();
  $start = strpos($ruw, '{');
  if ($start === false) return evenementenLeegBestand();
  $json = json_decode(substr($ruw, $start), true);
  if (!is_array($json) || !isset($json['evenementen']) || !is_array($json['evenementen'])) {
    return evenementenLeegBestand();
  }
  $json['volgnummer'] = isset($json['volgnummer']) ? (int) $json['volgnummer'] : 0;
  return $json;
}

// Tijdgestempelde kopie in dezelfde map en met dezelfde bewaartermijn als
// de andere back-ups, zodat een per ongeluk gewist evenement (of een
// verkeerd aangepaste deelnemerslijst) terug te halen is.
function evenementenMaakBackup($bewaardagen = 90, $maxAantal = 200) {
  $pad = evenementBestandPad();
  if (!is_file($pad)) return;
  $map = ledenBackupMap();
  if (!is_dir($map) && !@mkdir($map, 0755, true)) return;
  @copy($pad, $map . '/' . date('Ymd-His') . '_evenementen-data.php');

  $bestanden = @glob($map . '/*_evenementen-data.php');
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

function evenementenSchrijf($data, $maakBackup = true) {
  if ($maakBackup) evenementenMaakBackup();
  $data['updated'] = date('c');
  $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  if ($json === false) return false;
  return file_put_contents(evenementBestandPad(), EVENEMENTEN_VOORLOOP . $json, LOCK_EX) !== false;
}

// ===== Kleine hulpjes =====

function evenementNieuwId() {
  return 'evenement_' . bin2hex(random_bytes(6));
}

function evenementVolgendNummer($data) {
  $hoogste = (int) ($data['volgnummer'] ?? 0);
  foreach ($data['evenementen'] as $e) {
    $n = (int) ($e['nummer'] ?? 0);
    if ($n > $hoogste) $hoogste = $n;
  }
  return $hoogste + 1;
}

function evenementWeergavenaam($e) {
  $titel = trim((string) ($e['titel'] ?? ''));
  return $titel === '' ? 'Evenement zonder titel' : $titel;
}

// Actuele status, afgeleid in plaats van los opgeslagen: zo kan het nooit
// tegenstrijdig raken met de datum. Geen datum ingevuld telt als
// 'aankomend' (nog te plannen), want er is nog geen reden om het als
// geweest te markeren.
function evenementStatus($e) {
  $datum = trim((string) ($e['datum'] ?? ''));
  if ($datum === '') return 'aankomend';
  return $datum < date('Y-m-d') ? 'geweest' : 'aankomend';
}

function evenementStatusLabels() {
  return [
    'aankomend' => 'Aankomend',
    'geweest'   => 'Geweest',
  ];
}

// Of een evenement zichtbaar is voor een lid zonder bestuursfunctie: niet
// alleen de ingestelde zichtbaarheid, ook een eventuele begindatum
// inschrijving telt mee. Zo kan het bestuur een evenement al aanmaken en
// voorbereiden zonder dat leden het meteen zien, en op de afgesproken
// datum verschijnt het vanzelf. Geen begindatum ingevuld betekent: meteen
// zichtbaar zodra de zichtbaarheid op "leden" staat.
function evenementZichtbaarVoorLeden($e) {
  if (($e['zichtbaarheid'] ?? 'leden') !== 'leden') return false;
  $begin = trim((string) ($e['inschrijving_begin'] ?? ''));
  if ($begin !== '' && $begin > date('Y-m-d')) return false;
  return true;
}

// Aantal aanmeldingen. $e['deelnemers'] is een simpele lijst met lid-id's,
// geen los-op-te-zoeken namen: die komen bij het tonen uit de
// ledenadministratie (zelfde patroon als toegewezen_aan elders).
function evenementAantalDeelnemers($e) {
  return is_array($e['deelnemers'] ?? null) ? count($e['deelnemers']) : 0;
}

// Vol: alleen van toepassing als er een capaciteit is ingevuld. Leeg of 0
// betekent onbeperkt.
function evenementIsVol($e) {
  $capaciteit = (int) ($e['capaciteit'] ?? 0);
  if ($capaciteit <= 0) return false;
  return evenementAantalDeelnemers($e) >= $capaciteit;
}

// Staat de inschrijving op dit moment open? Los van vol of niet, dat is een
// aparte vraag. Geen einddatum ingevuld betekent: open tot het evenement
// geweest is.
function evenementInschrijvingOpen($e) {
  if (!evenementZichtbaarVoorLeden($e)) return false;
  if (evenementStatus($e) !== 'aankomend') return false;
  $eind = trim((string) ($e['inschrijving_eind'] ?? ''));
  if ($eind !== '' && $eind < date('Y-m-d')) return false;
  return true;
}

// Is dit lid ingeschreven?
function evenementHeeftDeelnemer($e, $lidId) {
  $lidId = trim((string) $lidId);
  if ($lidId === '') return false;
  return in_array($lidId, is_array($e['deelnemers'] ?? null) ? $e['deelnemers'] : [], true);
}

// ===== Zelf in- en uitschrijven =====
// Eén lid aan- of afmelden voor één evenement, gebruikt door leden.php.
//
// Waarom dit hier staat en niet in de pagina: lezen, wijzigen en
// terugschrijven moet als geheel afgeschermd zijn. LOCK_EX op
// file_put_contents() beschermt alleen het schrijven zelf, dus als twee
// leden tegelijk op "inschrijven" drukken, leest de tweede het bestand nog
// zonder de eerste erin en schrijft die er daarna overheen. Bij het bestuur
// dat af en toe iets opslaat valt dat nooit op, bij zestig leden die zich
// op dezelfde clubdag inschrijven wel. Vandaar een echte lock om de hele
// cyclus, met het gedeelde slot uit data-slot.php: hetzelfde slot dat
// beheer.php en leden.php om hun opslaan-blok heen leggen, zodat een lid dat
// zich inschrijft en een bestuurslid dat het evenement bewerkt niet
// tegelijk kunnen schrijven.
//
// Geeft true bij succes. Bij false staat in $fout een uitlegbare reden.
function evenementDeelnameWijzigen($evenementId, $lidId, $aanmelden, &$fout = null) {
  $fout = '';
  $evenementId = trim((string) $evenementId);
  $lidId = trim((string) $lidId);
  if ($evenementId === '' || $lidId === '') {
    $fout = 'Onbekend evenement of lid.';
    return false;
  }

  $slot = dataSlotOpen();

  try {
    $data = evenementenLees();
    $index = null;
    foreach ($data['evenementen'] as $i => $e) {
      if (($e['id'] ?? '') === $evenementId) { $index = $i; break; }
    }
    if ($index === null) {
      $fout = 'Dit evenement bestaat niet meer.';
      return false;
    }

    $e = $data['evenementen'][$index];
    if (!evenementZichtbaarVoorLeden($e)) {
      $fout = 'Dit evenement is niet voor jou beschikbaar.';
      return false;
    }
    if (!evenementInschrijvingOpen($e)) {
      $fout = 'De inschrijving voor dit evenement is gesloten.';
      return false;
    }

    $stondIngeschreven = evenementHeeftDeelnemer($e, $lidId);
    if ($aanmelden && $stondIngeschreven) return true;  // al goed, niets te doen
    if (!$aanmelden && !$stondIngeschreven) return true;

    if ($aanmelden) {
      // Pas hier controleren, met het bestand onder de lock: tussen het
      // tonen van de pagina en deze klik kan de laatste plek weg zijn.
      if (evenementIsVol($e)) {
        $fout = 'Dit evenement zit vol.';
        return false;
      }
      $e['deelnemers'][] = $lidId;
    } else {
      $e['deelnemers'] = array_values(array_filter(
        is_array($e['deelnemers'] ?? null) ? $e['deelnemers'] : [],
        function ($id) use ($lidId) { return $id !== $lidId; }
      ));
    }
    $e['gewijzigd'] = date('c');
    $data['evenementen'][$index] = $e;

    if (!evenementenSchrijf($data)) {
      $fout = 'Opslaan mislukt. Probeer het nog eens.';
      return false;
    }
    return true;
  } finally {
    dataSlotDicht($slot);
  }
}

// Aankomend boven geweest, daarbinnen aankomend op dichtstbijzijnde datum
// eerst en geweest op meest recente datum eerst. Evenementen zonder datum
// (nog te plannen) staan bovenaan bij aankomend.
function evenementenGesorteerd($data) {
  $volgorde = ['aankomend' => 0, 'geweest' => 1];
  $lijst = $data['evenementen'];
  usort($lijst, function ($a, $b) use ($volgorde) {
    $sa = $volgorde[evenementStatus($a)] ?? 2;
    $sb = $volgorde[evenementStatus($b)] ?? 2;
    if ($sa !== $sb) return $sa <=> $sb;
    $da = (string) ($a['datum'] ?? '');
    $db = (string) ($b['datum'] ?? '');
    if ($sa === 0) {
      // Aankomend: geen datum eerst, dan oplopend (eerstvolgende bovenaan).
      if ($da === '' && $db === '') return ((int) ($b['nummer'] ?? 0)) <=> ((int) ($a['nummer'] ?? 0));
      if ($da === '') return -1;
      if ($db === '') return 1;
      return $da <=> $db;
    }
    // Geweest: aflopend (meest recente bovenaan).
    return $db <=> $da;
  });
  return $lijst;
}

// Tijd (HH:MM) valideren en normaliseren. Accepteert ook varianten als
// "9", "9.30" of "930", zelfde soepele aanpak als vergaderingParseTijd() in
// vergaderingen-opslag.php. Ongeldige of lege invoer wordt gewoon leeg.
function evenementParseTijd($waarde) {
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

// Deelnemerslijst opschonen: alleen geldige, unieke lid-id's. Of een lid
// nog echt bestaat controleert de aanroeper (net als bij toegewezen_aan),
// hier alleen vorm en duplicaten.
function evenementDeelnemersOpschonen($ruw) {
  if (!is_array($ruw)) return [];
  $uit = [];
  foreach ($ruw as $lidId) {
    $lidId = ledenKort($lidId, 40);
    if ($lidId === '' || in_array($lidId, $uit, true)) continue;
    $uit[] = $lidId;
  }
  return $uit;
}

// ===== Invoer opschonen =====

function evenementVeldGrenzen() {
  return ['titel' => 160, 'locatie' => 120, 'betaalverzoek' => 500];
}

function evenementNormaliseer($invoer, $bestaand = null) {
  $e = is_array($bestaand) ? $bestaand : [];

  foreach (evenementVeldGrenzen() as $veld => $max) {
    if (array_key_exists($veld, $invoer)) {
      $e[$veld] = ledenKort($invoer[$veld], $max);
    } elseif (!isset($e[$veld])) {
      $e[$veld] = '';
    }
  }

  if (array_key_exists('omschrijving', $invoer)) {
    $tekst = trim((string) $invoer['omschrijving']);
    $tekst = preg_replace('/\R/u', "\n", $tekst);
    $e['omschrijving'] = function_exists('mb_substr') ? mb_substr($tekst, 0, 4000, 'UTF-8') : substr($tekst, 0, 4000);
  } elseif (!isset($e['omschrijving'])) {
    $e['omschrijving'] = '';
  }

  // Datum: verwacht al genormaliseerd (Y-m-d) via ledenParseDatum() door de
  // aanroeper, hier alleen bewaren of leeg laten.
  if (array_key_exists('datum', $invoer)) {
    $e['datum'] = ledenGeldigeDatum((string) $invoer['datum']) ? $invoer['datum'] : '';
  } elseif (!isset($e['datum'])) {
    $e['datum'] = '';
  }

  // Aanvang en eindtijd: hier (in tegenstelling tot de datumvelden) zelf
  // geparsed, dat hoeft de aanroeper dus niet apart te doen.
  if (array_key_exists('tijd', $invoer)) {
    $e['tijd'] = evenementParseTijd($invoer['tijd']);
  } elseif (!isset($e['tijd'])) {
    $e['tijd'] = '';
  }

  if (array_key_exists('eindtijd', $invoer)) {
    $e['eindtijd'] = evenementParseTijd($invoer['eindtijd']);
  } elseif (!isset($e['eindtijd'])) {
    $e['eindtijd'] = '';
  }

  // Begin- en einddatum inschrijving: ook al genormaliseerd door de
  // aanroeper. Leeg mag: dan gelden er geen aparte inschrijvingsdata en is
  // de gewone zichtbaarheid leidend (zie evenementZichtbaarVoorLeden()).
  // De volgorde (eind niet voor begin) controleert de aanroeper, want hier
  // is geen ruimte voor een foutmelding aan de gebruiker.
  if (array_key_exists('inschrijving_begin', $invoer)) {
    $e['inschrijving_begin'] = ledenGeldigeDatum((string) $invoer['inschrijving_begin']) ? $invoer['inschrijving_begin'] : '';
  } elseif (!isset($e['inschrijving_begin'])) {
    $e['inschrijving_begin'] = '';
  }

  if (array_key_exists('inschrijving_eind', $invoer)) {
    $e['inschrijving_eind'] = ledenGeldigeDatum((string) $invoer['inschrijving_eind']) ? $invoer['inschrijving_eind'] : '';
  } elseif (!isset($e['inschrijving_eind'])) {
    $e['inschrijving_eind'] = '';
  }

  $capaciteitsGrens = 9999;
  if (array_key_exists('capaciteit', $invoer)) {
    $ruw = trim((string) $invoer['capaciteit']);
    if ($ruw === '' || !ctype_digit($ruw)) {
      $e['capaciteit'] = 0;
    } else {
      $e['capaciteit'] = min((int) $ruw, $capaciteitsGrens);
    }
  } elseif (!isset($e['capaciteit'])) {
    $e['capaciteit'] = 0;
  }

  $zichtbaarheden = evenementZichtbaarheden();
  if (array_key_exists('zichtbaarheid', $invoer) && isset($zichtbaarheden[$invoer['zichtbaarheid']])) {
    $e['zichtbaarheid'] = $invoer['zichtbaarheid'];
  } elseif (!isset($e['zichtbaarheid']) || !isset($zichtbaarheden[$e['zichtbaarheid']])) {
    $e['zichtbaarheid'] = 'leden';
  }

  // Deelnemers (checkbox per lid): ontbreekt het hele blok in de invoer,
  // dan is er niets aangevinkt en hoort de lijst leeg te worden, net als
  // bij de aanwezigheid van een ledenvergadering.
  if (array_key_exists('deelnemers', $invoer)) {
    $e['deelnemers'] = evenementDeelnemersOpschonen($invoer['deelnemers']);
  } elseif (!isset($e['deelnemers']) || !is_array($e['deelnemers'])) {
    $e['deelnemers'] = [];
  }

  if (!isset($e['id']) || $e['id'] === '') $e['id'] = evenementNieuwId();
  if (!isset($e['nummer'])) $e['nummer'] = 0;
  if (!isset($e['aangemaakt'])) $e['aangemaakt'] = date('c');
  if (!isset($e['aangemaakt_door'])) $e['aangemaakt_door'] = '';
  $e['gewijzigd'] = date('c');

  return $e;
}
