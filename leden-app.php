<?php
// ============================================================
// RC045 ledenpagina
// ------------------------------------------------------------
// Afgeschermde pagina voor leden en bestuur. Twee lagen in één scherm:
//
//   Mijn RC045 - voor elk ingelogd lid. De actiepunten die bij de leden
//   open staan, de ledenvergaderingen en ALV's (agenda altijd, notulen pas
//   als ze definitief zijn) en de evenementen waarvoor je je zelf kunt in-
//   en uitschrijven.
//
//   De bestuurstabbladen - ledenadministratie, commissies, bestuurs- en
//   ledenvergaderingen, takenlijst, operationele taken en evenementen.
//   Stonden tot de splitsing in beheer.php; beheer.php gaat sindsdien
//   alleen nog over de website zelf.
//
// Inloggen loopt via auth.php, precies hetzelfde als beheer.php: dezelfde
// gebruikers, dezelfde sessie, hetzelfde logboek en dezelfde lockout. Welke
// tabbladen iemand ziet komt uit authRechten(): de vinkjes bij Gebruikers,
// en voor de drie bestuurstabbladen de bestuursfunctie in de
// ledenadministratie. Het tabblad Mijn RC045 krijgt iedereen die inlogt.
//
// Een account is aan een lid gekoppeld via het veld beheer_account in de
// ledenadministratie. Zonder die koppeling weet deze pagina niet wie je
// bent en valt er niets persoonlijks te tonen.
//
// Opmaak en scripts staan in paneel.css en paneel.js, gedeeld met
// beheer.php.
// ============================================================

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/vergaderingen-opslag.php';
require_once __DIR__ . '/taken-opslag.php';
require_once __DIR__ . '/operationele-taken-opslag.php';
require_once __DIR__ . '/evenementen-opslag.php';
require_once __DIR__ . '/paneel-hulp.php';

$dataMap = __DIR__ . '/data';
$rekentabelBestand = $dataMap . '/rekentabel.json';

// ===== Wie is dit? =====
// ledenRolVanGebruiker() zoekt het lid op waaraan deze inlognaam gekoppeld
// is. Geen koppeling betekent: wel ingelogd, maar geen lid, dus geen knop om
// je in te schrijven.
$eigenRolLid = $ingelogd ? ledenRolVanGebruiker($huidigeGebruiker) : ['lid' => null, 'bestuurslid' => false, 'functie' => '', 'commissies' => []];
$eigenLid = $eigenRolLid['lid'];
$eigenLidId = $eigenLid['id'] ?? '';

// ===== Tabbladen van deze pagina =====
$ledenTabsAlle = [
  'mijn'                => 'Mijn RC045',
  'leden'               => 'Leden',
  'commissies'          => 'Commissies',
  'bestuursvergadering' => 'Bestuursvergadering',
  'ledenvergadering'    => 'Ledenvergadering',
  'takenlijst'          => 'Takenlijst',
  'operationele_taken'  => 'Operationele taken',
  'evenementen'         => 'Evenementen',
];

// Deze drie lopen niet via de vinkjes bij Gebruikers maar via de
// bestuursfunctie in de ledenadministratie. Zelfde regel als die tot de
// splitsing in beheer.php stond.
$ledenTabsViaRol = ['bestuursvergadering', 'ledenvergadering', 'takenlijst'];

$rechten = authRechten($ledenTabsAlle, $ledenTabsViaRol);
$toegestaneTabs = $rechten['toegestaneTabs'];
$isBestuurslid  = $rechten['isBestuurslid'];
$magLedenAutorisatieWijzigen = authMagLedenAutorisatieWijzigen();

// Mijn RC045 hoort bij iedereen die kan inloggen, ook bij een ledenaccount
// dat verder nul tabbladen heeft. Daarom staat het niet aan de vinkjes.
if (!in_array('mijn', $toegestaneTabs, true)) $toegestaneTabs[] = 'mijn';

// Welk formulier hoort bij welk tabblad, om opslaan ook aan de serverkant te
// blokkeren voor een tabblad waar iemand geen toegang toe heeft. Dit is de
// echte beveiliging; het menu verbergt dingen alleen aan de oppervlakte.
$formulierTab = [
  'leden_opslaan' => 'leden', 'leden_verwijderen' => 'leden', 'leden_status' => 'leden',
  'leden_bulk_status' => 'leden',
  'leden_export' => 'leden', 'leden_import_lezen' => 'leden', 'leden_import_bevestigen' => 'leden',
  'leden_import_annuleren' => 'leden',
  'commissies_opslaan' => 'commissies',
  'vergadering_opslaan' => 'bestuursvergadering',
  'vergadering_verwijderen' => 'bestuursvergadering',
  'ledenvergadering_opslaan' => 'ledenvergadering',
  'ledenvergadering_verwijderen' => 'ledenvergadering',
  'taak_opslaan' => 'takenlijst',
  'taak_verwijderen' => 'takenlijst',
  'otaak_opslaan' => 'operationele_taken',
  'otaak_verwijderen' => 'operationele_taken',
  'otaak_uitgevoerd' => 'operationele_taken',
  'evenement_opslaan' => 'evenementen',
  'evenement_verwijderen' => 'evenementen',
];

/* CSV-export veilig voor spreadsheetprogramma's. Waarden die na eventuele
voorloop-witruimte met =, +, - of @ beginnen, kunnen door Excel/LibreOffice
als formule worden uitgevoerd. Een apostrof dwingt zo'n cel tot tekst. */
function ledenCsvVeiligeCel($waarde) {
  $waarde = (string) $waarde;
  return ($waarde !== '' && preg_match('/^[\x00-\x20]*[=+\-@]/u', $waarde))
    ? "'" . $waarde
    : $waarde;
}

function ledenCsvSchrijfRij($handle, array $rij) {
  return fputcsv($handle, array_map('ledenCsvVeiligeCel', $rij), ';');
}

// ===== Zelf in- of uitschrijven voor een evenement =====
// Staat bewust vóór het grote opslaan-blok hieronder: dit hoort bij het
// tabblad Mijn RC045 en heeft dus niets te maken met de rechten op het
// bestuurstabblad Evenementen. De controle of dit lid dit evenement mag zien
// en of de inschrijving open staat, zit in evenementDeelnameWijzigen().
if ($ingelogd && $_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($_POST['formulier'] ?? '', ['evenement_inschrijven', 'evenement_uitschrijven'], true)) {
  $formulierMijn = $_POST['formulier'];
  if (!csrfOk()) {
    $_SESSION['flash']['mijn'] = ['tekst' => 'Sessie verlopen. Ververs de pagina en probeer het opnieuw.', 'type' => 'fout'];
  } elseif ($eigenLidId === '') {
    $_SESSION['flash']['mijn'] = ['tekst' => 'Je account is nog niet aan een lid gekoppeld. Vraag het bestuur om dat te doen.', 'type' => 'fout'];
  } else {
    $aanmelden = $formulierMijn === 'evenement_inschrijven';
    $foutTekst = '';
    if (evenementDeelnameWijzigen($_POST['evenement_id'] ?? '', $eigenLidId, $aanmelden, $foutTekst)) {
      $_SESSION['flash']['mijn'] = [
        'tekst' => $aanmelden ? 'Je bent ingeschreven.' : 'Je inschrijving is ingetrokken.',
        'type'  => 'ok',
      ];
      schrijfLog($logBestand, $huidigeGebruiker, $aanmelden ? 'evenement_inschrijven' : 'evenement_uitschrijven', (string) ($_POST['evenement_id'] ?? ''));
    } else {
      $_SESSION['flash']['mijn'] = ['tekst' => $foutTekst !== '' ? $foutTekst : 'Er ging iets mis.', 'type' => 'fout'];
    }
  }
  header('Location: leden.php#mijn');
  exit;
}

// ===== Opslaan (ledenadministratie, commissies, vergaderingen, taken, evenementen) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ingelogd) {
  $formulier = $_POST['formulier'] ?? '';
  if (isset($formulierTab[$formulier]) && !in_array($formulierTab[$formulier], $toegestaneTabs, true)) {
    // Geen toegang tot dit tabblad: net doen alsof er geen bekend formulier
    // is binnengekomen, dan gebeurt er hieronder simpelweg niets.
    schrijfLog($logBestand, $huidigeGebruiker, 'toegang_geweigerd', $formulier);
    $formulier = '';
  }

  // Eén slot over het hele opslaan-blok, van inlezen tot wegschrijven. Zie
  // data-slot.php: hetzelfde slot dat beheer.php, het aanmeldformulier en
  // het inschrijven op een evenement gebruiken, zodat die vier elkaar nooit
  // kunnen overschrijven.
  $lockHandle = dataSlotOpen();

  // Herkent een POST die door PHP zelf al is afgewezen omdat het geheel
  // groter was dan post_max_size: $_POST en $_FILES komen dan allebei leeg
  // binnen. Speelt hier vooral bij het importeren van een ledenbestand.
  $mogelijkTeGroot = empty($_POST) && empty($_FILES) && !empty($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 0;

  if ($mogelijkTeGroot) {
    $melding['leden'] = 'Versturen mislukt: het bestand is waarschijnlijk te groot voor de server.';
    $meldingType['leden'] = 'fout';
  } elseif (!csrfOk()) {
    $melding['csrf'] = 'Sessie verlopen. Ververs de pagina en probeer het opnieuw.';
    $meldingType['csrf'] = 'fout';
  } elseif ($formulier === '') {
    // Niets te doen.
  } elseif ($formulier === 'leden_opslaan') {
    // Eén lid opslaan: bestaand lid bijwerken, of een nieuw lid toevoegen.
    // Na afloop een redirect (Post-Redirect-Get), zodat vernieuwen van de
    // pagina niet nog een keer opslaat.
    $ledenData = ledenLees();
    $id = trim($_POST['lid_id'] ?? '');
    $index = null;
    foreach ($ledenData['leden'] as $i => $l) {
      if (($l['id'] ?? '') === $id) { $index = $i; break; }
    }

    $voornaam = trim($_POST['voornaam'] ?? '');
    $achternaam = trim($_POST['achternaam'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($voornaam === '' && $achternaam === '') {
      $melding['leden'] = 'Vul minstens een voor- of achternaam in.';
      $meldingType['leden'] = 'fout';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $melding['leden'] = 'Dat mailadres ziet er niet geldig uit.';
      $meldingType['leden'] = 'fout';
    } else {
      $invoer = [];
      foreach (['voornaam','tussenvoegsel','achternaam','geboortedatum','straat','huisnummer',
                'postcode','gemeente','land','telefoon','email','status','inschrijfdatum',
                'opmerking','taken','transponder','auto','nummer'] as $veld) {
        if (isset($_POST[$veld])) $invoer[$veld] = $_POST[$veld];
      }
      $invoer['whatsapp'] = isset($_POST['whatsapp']);
      // Zonder aangevinkte commissies stuurt de browser het veld helemaal
      // niet mee. Altijd een array meegeven, anders zou het weghalen van
      // het laatste vinkje niets doen en zou de oude waarde blijven staan.
      $invoer['commissies'] = (isset($_POST['commissies']) && is_array($_POST['commissies'])) ? $_POST['commissies'] : [];
      // Bestuursfunctie en accountkoppeling zijn autorisatiegegevens. Alleen
      // accounts met het expliciete recht Gebruikers mogen ze überhaupt
      // vanuit dit formulier aan de normalisatielaag aanbieden. Die laag
      // controleert dit daarnaast nogmaals centraal.
      if ($magLedenAutorisatieWijzigen) {
        $invoer['bestuursfunctie'] = $_POST['bestuursfunctie'] ?? '';
        $invoer['beheer_account'] = trim((string) ($_POST['beheer_account'] ?? ''));
      }

      $bestaand = $index === null ? null : $ledenData['leden'][$index];
      $lid = ledenNormaliseer($invoer, $bestaand);
      // Alleen commissies die echt bestaan bewaren, zodat er geen sleutels
      // van opgeheven commissies in het bestand blijven hangen.
      $lid['commissies'] = array_values(array_intersect($lid['commissies'], array_keys(ledenCommissies($ledenData))));

      if ($index === null) {
        if ((int) $lid['nummer'] === 0) $lid['nummer'] = ledenVolgendNummer($ledenData);
        if ($lid['inschrijfdatum'] === '') $lid['inschrijfdatum'] = date('Y-m-d');
      }

      // Lidnummer moet uniek zijn. Het veld is voor nieuwe leden meestal
      // een voorstel (hoogste + 1) en voor bestaande leden zelden gewijzigd,
      // maar blijft met de hand aan te passen (bijvoorbeeld om een dubbel
      // nummer uit het Excel-bestand recht te zetten), dus die controle
      // hoort hier, niet als een simpele disabled-veld-truc die ook een
      // echte oplossing in de weg zit.
      $nummerBotsing = null;
      foreach ($ledenData['leden'] as $i => $ander) {
        if ($i === $index) continue;
        if ((int) $lid['nummer'] > 0 && (int) ($ander['nummer'] ?? 0) === (int) $lid['nummer']) {
          $nummerBotsing = $ander;
          break;
        }
      }

      if ($nummerBotsing !== null) {
        $melding['leden'] = 'Lidnummer ' . $lid['nummer'] . ' is al in gebruik bij ' . ledenVolledigeNaam($nummerBotsing) . '. Kies een ander nummer, bijvoorbeeld met de knop "Gebruik ..." naast het veld.';
        $meldingType['leden'] = 'fout';
      } else {
        // Contributieregels: één blok per jaar, plus een leeg blok om een
        // nieuw jaar toe te voegen. Een blok met een leeg jaartal wordt
        // overgeslagen, een bestaand jaar met het vinkje "verwijderen" gaat eruit.
        $regels = isset($_POST['contributie']) && is_array($_POST['contributie']) ? $_POST['contributie'] : [];
        foreach ($regels as $regel) {
          $jaar = (int) ($regel['jaar'] ?? 0);
          if ($jaar < 2000 || $jaar > 2099) continue;
          if (!empty($regel['verwijderen'])) {
            unset($lid['contributie'][(string) $jaar]);
            continue;
          }
          $lid = ledenZetContributie($lid, $jaar, $regel);
        }

        if ($index === null) {
          $ledenData['leden'][] = $lid;
          $ledenData['volgnummer'] = max((int) $ledenData['volgnummer'], (int) $lid['nummer']);
          $actie = 'toegevoegd';
        } else {
          $ledenData['leden'][$index] = $lid;
          $actie = 'bijgewerkt';
        }

        if (ledenSchrijf($ledenData)) {
          schrijfLog($logBestand, $huidigeGebruiker, 'leden', $actie . ': ' . ledenVolledigeNaam($lid) . ' (nr ' . $lid['nummer'] . ')');
          $_SESSION['flash']['leden'] = ['tekst' => 'Lid ' . $actie . ': ' . ledenVolledigeNaam($lid) . '.', 'type' => 'ok'];
          dataSlotDicht($lockHandle);
          header('Location: leden.php#leden');
          exit;
        }
        $melding['leden'] = 'Opslaan mislukt. Controleer de schrijfrechten in de hoofdmap van de server.';
        $meldingType['leden'] = 'fout';
      }
    }

  } elseif ($formulier === 'leden_verwijderen') {
    // Let op: niet op een lege naam afgaan om te bepalen of het lid
    // gevonden is. Bij een leeg lid (nog geen voornaam/achternaam
    // ingevuld) is ledenVolledigeNaam() zelf ook gewoon een lege string,
    // dus dat botste eerder met "lid niet gevonden" en er werd niets
    // opgeslagen: precies de leden die je met deze knop wilt opruimen.
    $ledenData = ledenLees();
    $id = trim($_POST['lid_id'] ?? '');
    $gevonden = false;
    $naam = '';
    $nummer = '';
    $over = [];
    foreach ($ledenData['leden'] as $l) {
      if (($l['id'] ?? '') === $id) { $gevonden = true; $naam = ledenVolledigeNaam($l); $nummer = (string) ($l['nummer'] ?? ''); continue; }
      $over[] = $l;
    }
    if (!$gevonden) {
      $melding['leden'] = 'Dat lid bestaat niet (meer).';
      $meldingType['leden'] = 'fout';
    } else {
      $ledenData['leden'] = $over;
      if (ledenSchrijf($ledenData)) {
        $naamVoorLog = $naam !== '' ? $naam : 'lid zonder naam (nr ' . $nummer . ')';
        schrijfLog($logBestand, $huidigeGebruiker, 'leden', 'verwijderd: ' . $naamVoorLog);
        $_SESSION['flash']['leden'] = ['tekst' => 'Lid verwijderd: ' . $naamVoorLog . '. Terugzetten kan via een back-up.', 'type' => 'ok'];
        dataSlotDicht($lockHandle);
        header('Location: leden.php#leden');
        exit;
      }
      $melding['leden'] = 'Verwijderen mislukt. Controleer de schrijfrechten in de hoofdmap van de server.';
      $meldingType['leden'] = 'fout';
    }

  } elseif ($formulier === 'leden_status') {
    // Snelle statuswijziging vanuit het overzicht, zonder het hele
    // bewerkformulier te openen.
    $ledenData = ledenLees();
    $id = trim($_POST['lid_id'] ?? '');
    $nieuw = $_POST['status'] ?? '';
    $statussen = ledenStatussen();
    if (!isset($statussen[$nieuw])) {
      $melding['leden'] = 'Onbekende status.';
      $meldingType['leden'] = 'fout';
    } else {
      foreach ($ledenData['leden'] as $i => $l) {
        if (($l['id'] ?? '') !== $id) continue;
        $ledenData['leden'][$i]['status'] = $nieuw;
        $ledenData['leden'][$i]['gewijzigd'] = date('c');
        if (ledenSchrijf($ledenData)) {
          schrijfLog($logBestand, $huidigeGebruiker, 'leden', 'status ' . ledenVolledigeNaam($l) . ' -> ' . $statussen[$nieuw]);
          $_SESSION['flash']['leden'] = ['tekst' => ledenVolledigeNaam($l) . ' staat nu op "' . $statussen[$nieuw] . '".', 'type' => 'ok'];
          dataSlotDicht($lockHandle);
          header('Location: leden.php#leden');
          exit;
        }
        break;
      }
      $melding['leden'] = 'Wijzigen mislukt.';
      $meldingType['leden'] = 'fout';
    }

  } elseif ($formulier === 'leden_bulk_status') {
    // Status van meerdere leden tegelijk aanpassen, aangevinkt in het
    // overzicht. Zelfde opzet als leden_status hierboven, maar dan voor
    // een lijst met id's in een keer, en een keer schrijven aan het eind
    // in plaats van per lid.
    $ledenData = ledenLees();
    $ids = array_filter(array_map('trim', explode(',', (string) ($_POST['lid_ids'] ?? ''))), function ($v) { return $v !== ''; });
    $nieuw = $_POST['status'] ?? '';
    $statussen = ledenStatussen();
    if (!isset($statussen[$nieuw])) {
      $melding['leden'] = 'Onbekende status.';
      $meldingType['leden'] = 'fout';
    } elseif (empty($ids)) {
      $melding['leden'] = 'Geen leden geselecteerd.';
      $meldingType['leden'] = 'fout';
    } else {
      $idsSet = array_flip($ids);
      $namen = [];
      foreach ($ledenData['leden'] as $i => $l) {
        if (!isset($idsSet[$l['id'] ?? ''])) continue;
        $ledenData['leden'][$i]['status'] = $nieuw;
        $ledenData['leden'][$i]['gewijzigd'] = date('c');
        $namen[] = ledenVolledigeNaam($l);
      }
      if (count($namen) > 0 && ledenSchrijf($ledenData)) {
        schrijfLog($logBestand, $huidigeGebruiker, 'leden', count($namen) . ' leden -> ' . $statussen[$nieuw] . ': ' . implode(', ', $namen));
        $_SESSION['flash']['leden'] = ['tekst' => count($namen) . ' leden staan nu op "' . $statussen[$nieuw] . '".', 'type' => 'ok'];
        dataSlotDicht($lockHandle);
        header('Location: leden.php#leden');
        exit;
      }
      $melding['leden'] = count($namen) === 0 ? 'Geen van de geselecteerde leden gevonden.' : 'Wijzigen mislukt.';
      $meldingType['leden'] = 'fout';
    }

  } elseif ($formulier === 'commissies_opslaan') {
    // De commissielijst bepaalt de club zelf. Hernoemen laat de sleutel
    // staan, zodat de leden die eraan hangen gekoppeld blijven. Verwijderen
    // haalt de commissie ook meteen bij alle leden weg: een sleutel die
    // nergens meer bij hoort levert later alleen maar verwarring op.
    //
    // Sinds het tabblad Commissies staat er per commissie ook een
    // verantwoordelijk bestuurslid en een commissiehoofd bij. Dat zijn
    // gewoon lid-id's; of ze nog bestaan wordt hieronder gecontroleerd,
    // een gekozen lid dat inmiddels weg is valt terug naar "geen".
    $ledenData = ledenLees();
    $bestaandeCommissies = ledenCommissiesVolledig($ledenData);
    $geldigeLidIds = array_column($ledenData['leden'], 'id');
    $bestuurslidIds = [];
    foreach ($ledenData['leden'] as $l) {
      if (ledenIsBestuurslid($l)) $bestuurslidIds[] = $l['id'];
    }
    $nieuweLijst = [];
    $verwijderd = [];

    foreach ($bestaandeCommissies as $sleutel => $oud) {
      $regel = $_POST['commissie'][$sleutel] ?? null;
      if (!is_array($regel)) { $nieuweLijst[$sleutel] = $oud; continue; }
      if (!empty($regel['verwijderen'])) { $verwijderd[] = $oud['naam']; continue; }
      $naam = ledenKort($regel['naam'] ?? '', 60);
      $bestuurslidId = ledenKort($regel['bestuurslid_id'] ?? '', 40);
      if (!in_array($bestuurslidId, $bestuurslidIds, true)) $bestuurslidId = '';
      $hoofdId = ledenKort($regel['hoofd_lid_id'] ?? '', 40);
      if (!in_array($hoofdId, $geldigeLidIds, true)) $hoofdId = '';
      $nieuweLijst[$sleutel] = [
        'naam' => $naam === '' ? $oud['naam'] : $naam,
        'bestuurslid_id' => $bestuurslidId,
        'hoofd_lid_id' => $hoofdId,
      ];
    }

    $toegevoegd = [];
    foreach ((array) ($_POST['commissie_nieuw'] ?? []) as $naamInvoer) {
      $naam = ledenKort($naamInvoer, 60);
      if ($naam === '') continue;
      $sleutel = ledenCommissieSleutel($naam);
      if ($sleutel === '' || isset($nieuweLijst[$sleutel])) continue;
      // Ook op naam controleren en niet alleen op sleutel: na een hernoeming
      // hoort de oude sleutel bij de nieuwe naam, en dan zou dezelfde naam
      // er onder een tweede sleutel alsnog naast komen te staan.
      $bestaatAl = false;
      foreach ($nieuweLijst as $bestaandeRegel) {
        if (strcasecmp($bestaandeRegel['naam'], $naam) === 0) { $bestaatAl = true; break; }
      }
      if ($bestaatAl) continue;
      $nieuweLijst[$sleutel] = ['naam' => $naam, 'bestuurslid_id' => '', 'hoofd_lid_id' => ''];
      $toegevoegd[] = $naam;
    }

    $ledenData['commissies'] = $nieuweLijst;
    $geldig = array_keys($nieuweLijst);
    foreach ($ledenData['leden'] as $i => $l) {
      $huidig = (isset($l['commissies']) && is_array($l['commissies'])) ? $l['commissies'] : [];
      $over = array_values(array_intersect($huidig, $geldig));
      if ($over !== $huidig) {
        $ledenData['leden'][$i]['commissies'] = $over;
        $ledenData['leden'][$i]['gewijzigd'] = date('c');
      }
    }

    if (ledenSchrijf($ledenData)) {
      $samenvatting = [];
      if (count($toegevoegd) > 0) $samenvatting[] = 'toegevoegd: ' . implode(', ', $toegevoegd);
      if (count($verwijderd) > 0) $samenvatting[] = 'verwijderd: ' . implode(', ', $verwijderd);
      schrijfLog($logBestand, $huidigeGebruiker, 'commissies', 'commissies bijgewerkt' . (count($samenvatting) > 0 ? ' (' . implode('; ', $samenvatting) . ')' : ''));
      $_SESSION['flash']['commissies'] = ['tekst' => 'Commissies opgeslagen.', 'type' => 'ok'];
      dataSlotDicht($lockHandle);
      header('Location: leden.php#commissies');
      exit;
    }
    $melding['commissies'] = 'Opslaan van de commissies mislukt.';
    $meldingType['commissies'] = 'fout';

  } elseif ($formulier === 'vergadering_opslaan') {
    // Een vergadering aanmaken of bijwerken. Wie hier komt is bestuurslid
    // (of beheerder): dat is hierboven al afgevangen via $formulierTab en
    // $toegestaneTabs. Binnen het bestuur is er geen verdere rangorde, een
    // penningmeester mag net zo goed een vergadering inplannen als de
    // voorzitter.
    $vergaderingenData = vergaderingenLees();
    $id = trim($_POST['vergadering_id'] ?? '');
    $index = null;
    foreach ($vergaderingenData['vergaderingen'] as $i => $v) {
      if (($v['id'] ?? '') === $id) { $index = $i; break; }
    }

    $invoer = [];
    foreach (['titel', 'datum', 'tijd', 'locatie', 'status', 'notulen'] as $veld) {
      if (isset($_POST[$veld])) $invoer[$veld] = $_POST[$veld];
    }
    $invoer['agenda'] = (isset($_POST['agenda']) && is_array($_POST['agenda'])) ? $_POST['agenda'] : [];
    // Aanwezigheid komt als lid-id => keuze binnen. Ontbreekt het hele
    // blok, dan is er niets aangevinkt en hoort het ook leeg te worden.
    $invoer['aanwezigheid'] = (isset($_POST['aanwezigheid']) && is_array($_POST['aanwezigheid'])) ? $_POST['aanwezigheid'] : [];

    if (trim((string) ($invoer['datum'] ?? '')) === '') {
      $melding['bestuursvergadering'] = 'Vul een datum in, anders is de vergadering nergens terug te vinden.';
      $meldingType['bestuursvergadering'] = 'fout';
    } elseif (ledenParseDatum($invoer['datum']) === '') {
      $melding['bestuursvergadering'] = 'Die datum begrijp ik niet. Gebruik dd-mm-jjjj.';
      $meldingType['bestuursvergadering'] = 'fout';
    } else {
      $bestaand = $index === null ? null : $vergaderingenData['vergaderingen'][$index];
      $vergadering = vergaderingNormaliseer($invoer, $bestaand);
      $vergadering['gewijzigd_door'] = $huidigeGebruiker;

      if ($index === null) {
        $vergadering['nummer'] = vergaderingVolgendNummer($vergaderingenData);
        $vergadering['aangemaakt_door'] = $huidigeGebruiker;
        $vergaderingenData['vergaderingen'][] = $vergadering;
        $vergaderingenData['volgnummer'] = max((int) $vergaderingenData['volgnummer'], (int) $vergadering['nummer']);
        $actie = 'aangemaakt';
      } else {
        $vergaderingenData['vergaderingen'][$index] = $vergadering;
        $actie = 'bijgewerkt';
      }

      if (vergaderingenSchrijf($vergaderingenData)) {
        schrijfLog($logBestand, $huidigeGebruiker, 'bestuursvergadering', $actie . ': ' . vergaderingWeergavenaam($vergadering));
        $_SESSION['flash']['bestuursvergadering'] = ['tekst' => 'Vergadering ' . $actie . ': ' . vergaderingWeergavenaam($vergadering) . '.', 'type' => 'ok'];
        dataSlotDicht($lockHandle);
        header('Location: leden.php#bestuursvergadering');
        exit;
      }
      $melding['bestuursvergadering'] = 'Opslaan mislukt. Controleer de schrijfrechten in de hoofdmap van de server.';
      $meldingType['bestuursvergadering'] = 'fout';
    }

  } elseif ($formulier === 'vergadering_verwijderen') {
    $vergaderingenData = vergaderingenLees();
    $id = trim($_POST['vergadering_id'] ?? '');
    $naam = '';
    $gevonden = false;
    foreach ($vergaderingenData['vergaderingen'] as $i => $v) {
      if (($v['id'] ?? '') !== $id) continue;
      $naam = vergaderingWeergavenaam($v);
      unset($vergaderingenData['vergaderingen'][$i]);
      $vergaderingenData['vergaderingen'] = array_values($vergaderingenData['vergaderingen']);
      $gevonden = true;
      break;
    }
    if (!$gevonden) {
      $melding['bestuursvergadering'] = 'Die vergadering staat er niet (meer) in.';
      $meldingType['bestuursvergadering'] = 'fout';
    } elseif (vergaderingenSchrijf($vergaderingenData)) {
      schrijfLog($logBestand, $huidigeGebruiker, 'bestuursvergadering', 'verwijderd: ' . $naam);
      $_SESSION['flash']['bestuursvergadering'] = ['tekst' => 'Vergadering verwijderd. De vorige versie staat in de back-ups.', 'type' => 'ok'];
      dataSlotDicht($lockHandle);
      header('Location: leden.php#bestuursvergadering');
      exit;
    } else {
      $melding['bestuursvergadering'] = 'Verwijderen mislukt.';
      $meldingType['bestuursvergadering'] = 'fout';
    }

  } elseif ($formulier === 'ledenvergadering_opslaan') {
    // Zelfde formulier en opslag als bij Bestuursvergadering, maar met
    // soort 'leden' vast erin en zonder presentielijst per bestuurslid: bij
    // een ledenvergadering (of ALV) is de hele club uitgenodigd, dus een
    // vinkje per lid zou het formulier onwerkbaar groot maken.
    $vergaderingenData = vergaderingenLees();
    $id = trim($_POST['vergadering_id'] ?? '');
    $index = null;
    // Alleen matchen binnen soort 'leden': anders zou een (verlopen of
    // geknoeid) id van een bestuursvergadering hier per ongeluk overschreven
    // of van soort veranderd kunnen worden.
    foreach ($vergaderingenData['vergaderingen'] as $i => $v) {
      $vSoort = ($v['soort'] ?? 'bestuur') === '' ? 'bestuur' : ($v['soort'] ?? 'bestuur');
      if (($v['id'] ?? '') === $id && $vSoort === 'leden') { $index = $i; break; }
    }

    $invoer = ['soort' => 'leden'];
    foreach (['titel', 'datum', 'tijd', 'locatie', 'status', 'notulen', 'ledenvergadering_type', 'agenda_status', 'notulen_status'] as $veld) {
      if (isset($_POST[$veld])) $invoer[$veld] = $_POST[$veld];
    }
    $invoer['agenda'] = (isset($_POST['agenda']) && is_array($_POST['agenda'])) ? $_POST['agenda'] : [];
    // Aanwezigheid komt als lid-id => keuze binnen, zelfde als bij de
    // bestuursvergadering. Ontbreekt het hele blok, dan is er niets
    // aangevinkt en hoort het ook leeg te worden.
    $invoer['aanwezigheid'] = (isset($_POST['aanwezigheid']) && is_array($_POST['aanwezigheid'])) ? $_POST['aanwezigheid'] : [];

    if (trim((string) ($invoer['datum'] ?? '')) === '') {
      $melding['ledenvergadering'] = 'Vul een datum in, anders is de vergadering nergens terug te vinden.';
      $meldingType['ledenvergadering'] = 'fout';
    } elseif (ledenParseDatum($invoer['datum']) === '') {
      $melding['ledenvergadering'] = 'Die datum begrijp ik niet. Gebruik dd-mm-jjjj.';
      $meldingType['ledenvergadering'] = 'fout';
    } else {
      $bestaand = $index === null ? null : $vergaderingenData['vergaderingen'][$index];
      $vergadering = vergaderingNormaliseer($invoer, $bestaand);
      $vergadering['soort'] = 'leden';
      $vergadering['gewijzigd_door'] = $huidigeGebruiker;

      if ($index === null) {
        $vergadering['nummer'] = vergaderingVolgendNummer($vergaderingenData, 'leden');
        $vergadering['aangemaakt_door'] = $huidigeGebruiker;
        $vergaderingenData['vergaderingen'][] = $vergadering;
        $actie = 'aangemaakt';
      } else {
        $vergaderingenData['vergaderingen'][$index] = $vergadering;
        $actie = 'bijgewerkt';
      }

      if (vergaderingenSchrijf($vergaderingenData)) {
        schrijfLog($logBestand, $huidigeGebruiker, 'ledenvergadering', $actie . ': ' . vergaderingWeergavenaam($vergadering));
        $_SESSION['flash']['ledenvergadering'] = ['tekst' => vergaderingWeergavenaam($vergadering) . ' ' . $actie . '.', 'type' => 'ok'];
        dataSlotDicht($lockHandle);
        header('Location: leden.php#ledenvergadering');
        exit;
      }
      $melding['ledenvergadering'] = 'Opslaan mislukt. Controleer de schrijfrechten in de hoofdmap van de server.';
      $meldingType['ledenvergadering'] = 'fout';
    }

  } elseif ($formulier === 'ledenvergadering_verwijderen') {
    $vergaderingenData = vergaderingenLees();
    $id = trim($_POST['vergadering_id'] ?? '');
    $naam = '';
    $gevonden = false;
    foreach ($vergaderingenData['vergaderingen'] as $i => $v) {
      $vSoort = ($v['soort'] ?? 'bestuur') === '' ? 'bestuur' : ($v['soort'] ?? 'bestuur');
      if (($v['id'] ?? '') !== $id || $vSoort !== 'leden') continue;
      $naam = vergaderingWeergavenaam($v);
      unset($vergaderingenData['vergaderingen'][$i]);
      $vergaderingenData['vergaderingen'] = array_values($vergaderingenData['vergaderingen']);
      $gevonden = true;
      break;
    }
    if (!$gevonden) {
      $melding['ledenvergadering'] = 'Die vergadering staat er niet (meer) in.';
      $meldingType['ledenvergadering'] = 'fout';
    } elseif (vergaderingenSchrijf($vergaderingenData)) {
      schrijfLog($logBestand, $huidigeGebruiker, 'ledenvergadering', 'verwijderd: ' . $naam);
      $_SESSION['flash']['ledenvergadering'] = ['tekst' => 'Vergadering verwijderd. De vorige versie staat in de back-ups.', 'type' => 'ok'];
      dataSlotDicht($lockHandle);
      header('Location: leden.php#ledenvergadering');
      exit;
    } else {
      $melding['ledenvergadering'] = 'Verwijderen mislukt.';
      $meldingType['ledenvergadering'] = 'fout';
    }

  } elseif ($formulier === 'taak_opslaan') {
    // Een taak aanmaken of bijwerken, desgewenst gekoppeld aan een
    // vergadering (bestuur of leden/ALV) en/of een commissie.
    $takenData = takenLees();
    $id = trim($_POST['taak_id'] ?? '');
    $index = null;
    foreach ($takenData['taken'] as $i => $t) {
      if (($t['id'] ?? '') === $id) { $index = $i; break; }
    }

    $invoer = [];
    foreach (['omschrijving', 'toelichting', 'status', 'commissie_id', 'toegewezen_aan'] as $veld) {
      if (isset($_POST[$veld])) $invoer[$veld] = $_POST[$veld];
    }

    // De koppeling met een vergadering komt als één keuzelijst binnen
    // ("bestuur:<id>" of "leden:<id>"), want de gebruiker kiest uit één
    // lijst met beide registers erin. Hier weer uit elkaar trekken.
    $vergSelectie = trim((string) ($_POST['taak_vergadering_selectie'] ?? ''));
    if ($vergSelectie !== '' && strpos($vergSelectie, ':') !== false) {
      list($invoer['vergadering_soort'], $invoer['vergadering_id']) = explode(':', $vergSelectie, 2);
    } else {
      $invoer['vergadering_soort'] = '';
      $invoer['vergadering_id'] = '';
    }

    // Een gekoppelde vergadering moet echt bestaan in het opgegeven
    // register, anders koppelt de taak straks aan niets terug.
    if (($invoer['vergadering_soort'] ?? '') !== '') {
      $vergaderingenData = vergaderingenLees();
      $bestaatNog = false;
      foreach ($vergaderingenData['vergaderingen'] as $v) {
        if (($v['id'] ?? '') === ($invoer['vergadering_id'] ?? '') && (($v['soort'] ?? 'bestuur') === $invoer['vergadering_soort'])) {
          $bestaatNog = true;
          break;
        }
      }
      if (!$bestaatNog) { $invoer['vergadering_soort'] = ''; $invoer['vergadering_id'] = ''; }
    }

    // Zelfde controle voor de commissie.
    if (($invoer['commissie_id'] ?? '') !== '') {
      $ledenDataVoorControle = ledenLees();
      $commissiesGeldig = ledenCommissies($ledenDataVoorControle);
      if (!isset($commissiesGeldig[$invoer['commissie_id']])) $invoer['commissie_id'] = '';
    }

    // En voor het toegewezen lid: moet echt (nog) bestaan in de ledenlijst.
    if (($invoer['toegewezen_aan'] ?? '') !== '') {
      $ledenDataVoorControle = isset($ledenDataVoorControle) ? $ledenDataVoorControle : ledenLees();
      $toegewezenBestaatNog = false;
      foreach ($ledenDataVoorControle['leden'] as $lc) {
        if (($lc['id'] ?? '') === $invoer['toegewezen_aan']) { $toegewezenBestaatNog = true; break; }
      }
      if (!$toegewezenBestaatNog) $invoer['toegewezen_aan'] = '';
    }

    if (trim((string) ($invoer['omschrijving'] ?? '')) === '') {
      $melding['takenlijst'] = 'Vul een omschrijving in, anders is de taak nergens op te herkennen.';
      $meldingType['takenlijst'] = 'fout';
    } else {
      $bestaand = $index === null ? null : $takenData['taken'][$index];
      $taak = taakNormaliseer($invoer, $bestaand);

      if ($index === null) {
        $taak['nummer'] = taakVolgendNummer($takenData);
        $taak['aangemaakt_door'] = $huidigeGebruiker;
        $takenData['taken'][] = $taak;
        $takenData['volgnummer'] = max((int) $takenData['volgnummer'], (int) $taak['nummer']);
        $actie = 'aangemaakt';
      } else {
        $takenData['taken'][$index] = $taak;
        $actie = 'bijgewerkt';
      }

      if (takenSchrijf($takenData)) {
        schrijfLog($logBestand, $huidigeGebruiker, 'takenlijst', $actie . ': ' . taakWeergavenaam($taak));
        $_SESSION['flash']['takenlijst'] = ['tekst' => 'Taak ' . $actie . ': ' . taakWeergavenaam($taak) . '.', 'type' => 'ok'];
        dataSlotDicht($lockHandle);
        header('Location: leden.php#takenlijst');
        exit;
      }
      $melding['takenlijst'] = 'Opslaan mislukt. Controleer de schrijfrechten in de hoofdmap van de server.';
      $meldingType['takenlijst'] = 'fout';
    }

  } elseif ($formulier === 'taak_verwijderen') {
    $takenData = takenLees();
    $id = trim($_POST['taak_id'] ?? '');
    $naam = '';
    $gevonden = false;
    foreach ($takenData['taken'] as $i => $t) {
      if (($t['id'] ?? '') !== $id) continue;
      $naam = taakWeergavenaam($t);
      unset($takenData['taken'][$i]);
      $takenData['taken'] = array_values($takenData['taken']);
      $gevonden = true;
      break;
    }
    if (!$gevonden) {
      $melding['takenlijst'] = 'Die taak staat er niet (meer) in.';
      $meldingType['takenlijst'] = 'fout';
    } elseif (takenSchrijf($takenData)) {
      schrijfLog($logBestand, $huidigeGebruiker, 'takenlijst', 'verwijderd: ' . $naam);
      $_SESSION['flash']['takenlijst'] = ['tekst' => 'Taak verwijderd. De vorige versie staat in de back-ups.', 'type' => 'ok'];
      dataSlotDicht($lockHandle);
      header('Location: leden.php#takenlijst');
      exit;
    } else {
      $melding['takenlijst'] = 'Verwijderen mislukt.';
      $meldingType['takenlijst'] = 'fout';
    }

  } elseif ($formulier === 'otaak_opslaan') {
    // Een operationele taak aanmaken of bijwerken. Uitvoeringsgegevens
    // (laatst_uitgevoerd, geschiedenis, ...) lopen niet via dit formulier
    // maar via otaak_uitgevoerd hieronder.
    $otakenData = otakenLees();
    $id = trim($_POST['otaak_id'] ?? '');
    $index = null;
    foreach ($otakenData['taken'] as $i => $t) {
      if (($t['id'] ?? '') === $id) { $index = $i; break; }
    }
    $bestaandOtaak = $index === null ? null : $otakenData['taken'][$index];

    $invoer = [];
    foreach (['omschrijving', 'toelichting', 'frequentie', 'toegewezen_aan'] as $veld) {
      if (isset($_POST[$veld])) $invoer[$veld] = $_POST[$veld];
    }
    $invoer['actief'] = isset($_POST['actief']) ? '1' : '';

    // De zichtbaarheid (leden/bestuur) is alleen door een bestuurslid te
    // wijzigen. Een gewoon lid met toegang tot dit tabblad kan wel taken
    // aanmaken en bewerken, maar altijd met zichtbaarheid "leden": anders
    // zou die zelf een taak kunnen maken die hij daarna niet meer terugziet,
    // of erger, een bestaande bestuurs-only taak openbaar kunnen zetten.
    if ($isBestuurslid && isset($_POST['zichtbaarheid'])) {
      $invoer['zichtbaarheid'] = $_POST['zichtbaarheid'];
    } elseif (!$isBestuurslid) {
      $invoer['zichtbaarheid'] = 'leden';
    }

    // Het toegewezen lid moet echt (nog) bestaan.
    if (($invoer['toegewezen_aan'] ?? '') !== '') {
      $ledenDataVoorControle = ledenLees();
      $toegewezenBestaatNog = false;
      foreach ($ledenDataVoorControle['leden'] as $lc) {
        if (($lc['id'] ?? '') === $invoer['toegewezen_aan']) { $toegewezenBestaatNog = true; break; }
      }
      if (!$toegewezenBestaatNog) $invoer['toegewezen_aan'] = '';
    }

    // Een niet-bestuurslid mag een bestaande bestuurs-only taak niet
    // bewerken, ook niet als het formulier met een geknutseld verzoek
    // binnenkomt (bv. een geraden otaak_id). Gewoon weigeren, in plaats
    // van dit stilzwijgend als een nieuwe taak op te slaan.
    $magOpslaan = true;
    if (!$isBestuurslid && $bestaandOtaak !== null && ($bestaandOtaak['zichtbaarheid'] ?? 'leden') === 'bestuur') {
      $magOpslaan = false;
      $melding['operationele_taken'] = 'Die taak staat er niet (meer) in.';
      $meldingType['operationele_taken'] = 'fout';
    }

    if (!$magOpslaan) {
      // Niets doen: de foutmelding hierboven staat al klaar.
    } elseif (trim((string) ($invoer['omschrijving'] ?? '')) === '') {
      $melding['operationele_taken'] = 'Vul een omschrijving in, anders is de taak nergens op te herkennen.';
      $meldingType['operationele_taken'] = 'fout';
    } else {
      $otaak = otaakNormaliseer($invoer, $bestaandOtaak);

      if ($index === null) {
        $otaak['nummer'] = otaakVolgendNummer($otakenData);
        $otaak['aangemaakt_door'] = $huidigeGebruiker;
        $otakenData['taken'][] = $otaak;
        $otakenData['volgnummer'] = max((int) $otakenData['volgnummer'], (int) $otaak['nummer']);
        $actie = 'aangemaakt';
      } else {
        $otakenData['taken'][$index] = $otaak;
        $actie = 'bijgewerkt';
      }

      if (otakenSchrijf($otakenData)) {
        schrijfLog($logBestand, $huidigeGebruiker, 'operationele_taken', $actie . ': ' . otaakWeergavenaam($otaak));
        $_SESSION['flash']['operationele_taken'] = ['tekst' => 'Taak ' . $actie . ': ' . otaakWeergavenaam($otaak) . '.', 'type' => 'ok'];
        dataSlotDicht($lockHandle);
        header('Location: leden.php#operationele_taken');
        exit;
      }
      $melding['operationele_taken'] = 'Opslaan mislukt. Controleer de schrijfrechten in de hoofdmap van de server.';
      $meldingType['operationele_taken'] = 'fout';
    }

  } elseif ($formulier === 'otaak_verwijderen') {
    $otakenData = otakenLees();
    $id = trim($_POST['otaak_id'] ?? '');
    $naam = '';
    $gevonden = false;
    foreach ($otakenData['taken'] as $i => $t) {
      if (($t['id'] ?? '') !== $id) continue;
      // Een niet-bestuurslid mag een bestuurs-only taak niet verwijderen.
      if (!$isBestuurslid && ($t['zichtbaarheid'] ?? 'leden') === 'bestuur') break;
      $naam = otaakWeergavenaam($t);
      unset($otakenData['taken'][$i]);
      $otakenData['taken'] = array_values($otakenData['taken']);
      $gevonden = true;
      break;
    }
    if (!$gevonden) {
      $melding['operationele_taken'] = 'Die taak staat er niet (meer) in.';
      $meldingType['operationele_taken'] = 'fout';
    } elseif (otakenSchrijf($otakenData)) {
      schrijfLog($logBestand, $huidigeGebruiker, 'operationele_taken', 'verwijderd: ' . $naam);
      $_SESSION['flash']['operationele_taken'] = ['tekst' => 'Taak verwijderd. De vorige versie staat in de back-ups.', 'type' => 'ok'];
      dataSlotDicht($lockHandle);
      header('Location: leden.php#operationele_taken');
      exit;
    } else {
      $melding['operationele_taken'] = 'Verwijderen mislukt.';
      $meldingType['operationele_taken'] = 'fout';
    }

  } elseif ($formulier === 'otaak_uitgevoerd') {
    // Een taak afmelden: logt de uitvoering en berekent de volgende datum.
    $otakenData = otakenLees();
    $id = trim($_POST['otaak_id'] ?? '');
    $index = null;
    foreach ($otakenData['taken'] as $i => $t) {
      if (($t['id'] ?? '') === $id) { $index = $i; break; }
    }
    if ($index === null || (!$isBestuurslid && ($otakenData['taken'][$index]['zichtbaarheid'] ?? 'leden') === 'bestuur')) {
      $melding['operationele_taken'] = 'Die taak staat er niet (meer) in.';
      $meldingType['operationele_taken'] = 'fout';
    } else {
      $otakenData['taken'][$index] = otaakMarkeerUitgevoerd($otakenData['taken'][$index], $huidigeGebruiker);
      $naam = otaakWeergavenaam($otakenData['taken'][$index]);
      if (otakenSchrijf($otakenData)) {
        schrijfLog($logBestand, $huidigeGebruiker, 'operationele_taken', 'uitgevoerd gemeld: ' . $naam);
        $_SESSION['flash']['operationele_taken'] = ['tekst' => 'Taak afgemeld: ' . $naam . '.', 'type' => 'ok'];
        dataSlotDicht($lockHandle);
        header('Location: leden.php#operationele_taken');
        exit;
      }
      $melding['operationele_taken'] = 'Afmelden mislukt. Controleer de schrijfrechten in de hoofdmap van de server.';
      $meldingType['operationele_taken'] = 'fout';
    }

  } elseif ($formulier === 'evenement_opslaan') {
    // Een evenement aanmaken of bijwerken, inclusief de deelnemerslijst.
    // Voor nu beheert het bestuur die lijst hier zelf, net als de
    // presentielijst bij een ledenvergadering (zie het opslagbestand voor
    // de gedachte achter deze opzet, met het oog op een later ledenportaal).
    $evenementenData = evenementenLees();
    $id = trim($_POST['evenement_id'] ?? '');
    $index = null;
    foreach ($evenementenData['evenementen'] as $i => $ev) {
      if (($ev['id'] ?? '') === $id) { $index = $i; break; }
    }
    $bestaandEvenement = $index === null ? null : $evenementenData['evenementen'][$index];

    $invoer = [];
    foreach (['titel', 'omschrijving', 'tijd', 'eindtijd', 'locatie', 'capaciteit', 'betaalverzoek'] as $veld) {
      if (isset($_POST[$veld])) $invoer[$veld] = $_POST[$veld];
    }
    $invoer['datum'] = ledenParseDatum($_POST['datum'] ?? '');
    $invoer['inschrijving_begin'] = ledenParseDatum($_POST['inschrijving_begin'] ?? '');
    $invoer['inschrijving_eind'] = ledenParseDatum($_POST['inschrijving_eind'] ?? '');
    $invoer['tijd'] = evenementParseTijd($_POST['tijd'] ?? '');
    $invoer['eindtijd'] = evenementParseTijd($_POST['eindtijd'] ?? '');
    // Deelnemers komen als lid-id => "1" binnen (alleen aangevinkte
    // checkboxen sturen mee), dus de sleutels zijn de lid-id's zelf.
    $invoer['deelnemers'] = (isset($_POST['deelnemers']) && is_array($_POST['deelnemers']))
      ? array_keys($_POST['deelnemers']) : [];

    // De zichtbaarheid (leden/bestuur) is alleen door een bestuurslid te
    // wijzigen, zelfde reden als bij Operationele taken: anders zou een
    // gewoon lid een bestuurs-only evenement openbaar kunnen zetten, of een
    // eigen evenement maken dat hij daarna niet meer terugziet.
    if ($isBestuurslid && isset($_POST['zichtbaarheid'])) {
      $invoer['zichtbaarheid'] = $_POST['zichtbaarheid'];
    } elseif (!$isBestuurslid) {
      $invoer['zichtbaarheid'] = 'leden';
    }

    // Een niet-bestuurslid mag een evenement dat voor hem niet zichtbaar is
    // (bestuur-only, of nog voor de begindatum inschrijving) niet bewerken,
    // ook niet via een geraden evenement_id. Zelfde functie als bij het
    // filteren van de lijst hierboven, dus altijd dezelfde uitkomst.
    $magOpslaan = true;
    if (!$isBestuurslid && $bestaandEvenement !== null && !evenementZichtbaarVoorLeden($bestaandEvenement)) {
      $magOpslaan = false;
      $melding['evenementen'] = 'Dat evenement staat er niet (meer) in.';
      $meldingType['evenementen'] = 'fout';
    }

    if (!$magOpslaan) {
      // Niets doen: de foutmelding hierboven staat al klaar.
    } elseif (trim((string) ($invoer['titel'] ?? '')) === '') {
      $melding['evenementen'] = 'Vul een titel in, anders is het evenement nergens op te herkennen.';
      $meldingType['evenementen'] = 'fout';
    } elseif ($invoer['inschrijving_begin'] !== '' && $invoer['inschrijving_eind'] !== '' && $invoer['inschrijving_eind'] < $invoer['inschrijving_begin']) {
      $melding['evenementen'] = 'De einddatum inschrijving ligt voor de begindatum. Controleer de datums.';
      $meldingType['evenementen'] = 'fout';
    } elseif ($invoer['tijd'] !== '' && $invoer['eindtijd'] !== '' && $invoer['eindtijd'] < $invoer['tijd']) {
      $melding['evenementen'] = 'De eindtijd ligt voor de aanvangstijd. Controleer de tijden.';
      $meldingType['evenementen'] = 'fout';
    } else {
      // Deelnemers moeten echt (nog) bestaan, anders slipt een verwijderd
      // lid ongemerkt in de lijst.
      $ledenDataVoorControle = ledenLees();
      $bestaandeLidIds = [];
      foreach ($ledenDataVoorControle['leden'] as $lc) { $bestaandeLidIds[$lc['id']] = true; }
      $invoer['deelnemers'] = array_values(array_filter($invoer['deelnemers'], function ($lidId) use ($bestaandeLidIds) {
        return isset($bestaandeLidIds[$lidId]);
      }));

      $evenement = evenementNormaliseer($invoer, $bestaandEvenement);

      if ($index === null) {
        $evenement['nummer'] = evenementVolgendNummer($evenementenData);
        $evenement['aangemaakt_door'] = $huidigeGebruiker;
        $evenementenData['evenementen'][] = $evenement;
        $evenementenData['volgnummer'] = max((int) $evenementenData['volgnummer'], (int) $evenement['nummer']);
        $actie = 'aangemaakt';
      } else {
        $evenementenData['evenementen'][$index] = $evenement;
        $actie = 'bijgewerkt';
      }

      if (evenementenSchrijf($evenementenData)) {
        schrijfLog($logBestand, $huidigeGebruiker, 'evenementen', $actie . ': ' . evenementWeergavenaam($evenement));
        $_SESSION['flash']['evenementen'] = ['tekst' => 'Evenement ' . $actie . ': ' . evenementWeergavenaam($evenement) . '.', 'type' => 'ok'];
        dataSlotDicht($lockHandle);
        header('Location: leden.php#evenementen');
        exit;
      }
      $melding['evenementen'] = 'Opslaan mislukt. Controleer de schrijfrechten in de hoofdmap van de server.';
      $meldingType['evenementen'] = 'fout';
    }

  } elseif ($formulier === 'evenement_verwijderen') {
    $evenementenData = evenementenLees();
    $id = trim($_POST['evenement_id'] ?? '');
    $naam = '';
    $gevonden = false;
    foreach ($evenementenData['evenementen'] as $i => $ev) {
      if (($ev['id'] ?? '') !== $id) continue;
      // Een niet-bestuurslid mag een evenement dat voor hem niet zichtbaar
      // is (bestuur-only, of nog voor de begindatum inschrijving) niet
      // verwijderen.
      if (!$isBestuurslid && !evenementZichtbaarVoorLeden($ev)) break;
      $naam = evenementWeergavenaam($ev);
      unset($evenementenData['evenementen'][$i]);
      $evenementenData['evenementen'] = array_values($evenementenData['evenementen']);
      $gevonden = true;
      break;
    }
    if (!$gevonden) {
      $melding['evenementen'] = 'Dat evenement staat er niet (meer) in.';
      $meldingType['evenementen'] = 'fout';
    } elseif (evenementenSchrijf($evenementenData)) {
      schrijfLog($logBestand, $huidigeGebruiker, 'evenementen', 'verwijderd: ' . $naam);
      $_SESSION['flash']['evenementen'] = ['tekst' => 'Evenement verwijderd. De vorige versie staat in de back-ups.', 'type' => 'ok'];
      dataSlotDicht($lockHandle);
      header('Location: leden.php#evenementen');
      exit;
    } else {
      $melding['evenementen'] = 'Verwijderen mislukt.';
      $meldingType['evenementen'] = 'fout';
    }

  } elseif ($formulier === 'leden_export') {
    // Het hele ledenbestand als CSV, met puntkomma's zodat Excel in het
    // Nederlands het zonder importwizard opent, en een BOM zodat accenten
    // goed doorkomen.
    $ledenData = ledenLees();
    $jaren = [];
    foreach ($ledenData['leden'] as $l) {
      foreach (array_keys($l['contributie'] ?? []) as $j) $jaren[$j] = true;
    }
    $jaren = array_keys($jaren);
    sort($jaren);
    if (count($jaren) === 0) $jaren = [(string) date('Y')];

    $statussen = ledenStatussen();
    $cStatussen = ledenContributieStatussen();
    // De rekentabel wordt pas verderop in dit bestand ingelezen, na de
    // afhandeling van formulieren. Voor de kolom "Jeugdlid" hebben we de
    // leeftijdsgrens hier al nodig, dus die halen we los op.
    $exportRekentabel = $rekentabelStandaard;
    if (file_exists($rekentabelBestand)) {
      $exportJson = json_decode(file_get_contents($rekentabelBestand), true);
      if (is_array($exportJson)) $exportRekentabel = array_merge($rekentabelStandaard, $exportJson);
    }
    $exportJeugdTot = (int) $exportRekentabel['jeugd_leeftijd_tot'];
    $exportFuncties = ledenBestuursfuncties();
    $exportCommissies = ledenCommissies($ledenData);
    $kop = ['nummer','Voornaam','Tussenvoegsel','Achternaam','Geboortedatum','leeftijd','straat','huisnummer','postcode','woonplaats','land','Telefoon / Whatsapp','mailadres','Status','Jeugdlid','Inschrijfdatum','Opmerking','Taken','Toegevoegd Whatsapp','Transponder','Auto','Bestuursfunctie','Commissies'];
    foreach ($jaren as $j) {
      $kop[] = 'Contributie ' . $j . ' status';
      $kop[] = 'Contributiebedrag ' . $j;
      $kop[] = 'Inschrijfgeld ' . $j;
      $kop[] = 'Betaald op ' . $j;
    }

    $uit = fopen('php://temp', 'r+');
    ledenCsvSchrijfRij($uit, $kop);
    $gesorteerd = $ledenData['leden'];
    usort($gesorteerd, function ($a, $b) { return ledenSorteernaam($a) <=> ledenSorteernaam($b); });
    foreach ($gesorteerd as $l) {
      $leeftijd = ledenLeeftijd($l['geboortedatum'] ?? '');
      $jeugd = ledenIsJeugd($l, $exportJeugdTot, date('Y'));
      $rij = [
        $l['nummer'] ?? '', $l['voornaam'] ?? '', $l['tussenvoegsel'] ?? '', $l['achternaam'] ?? '',
        $l['geboortedatum'] ?? '', $leeftijd === null ? '' : $leeftijd,
        $l['straat'] ?? '', $l['huisnummer'] ?? '', $l['postcode'] ?? '', $l['gemeente'] ?? '', $l['land'] ?? '',
        $l['telefoon'] ?? '', $l['email'] ?? '',
        $statussen[$l['status'] ?? ''] ?? '', $jeugd === null ? '' : ($jeugd ? 'ja' : 'nee'),
        $l['inschrijfdatum'] ?? '', $l['opmerking'] ?? '', $l['taken'] ?? '',
        empty($l['whatsapp']) ? 'nee' : 'ja', $l['transponder'] ?? '', $l['auto'] ?? '',
        $exportFuncties[$l['bestuursfunctie'] ?? ''] ?? '',
        implode(', ', ledenCommissieNamen($l, $exportCommissies)),
      ];
      foreach ($jaren as $j) {
        $c = $l['contributie'][$j] ?? null;
        $rij[] = $c ? ($cStatussen[$c['status']] ?? $c['status']) : '';
        $rij[] = ($c && $c['bedrag'] !== null) ? number_format((float) $c['bedrag'], 2, ',', '') : '';
        $rij[] = ($c && $c['inschrijfgeld'] !== null) ? number_format((float) $c['inschrijfgeld'], 2, ',', '') : '';
        $rij[] = $c ? ($c['betaald_op'] ?? '') : '';
      }
      ledenCsvSchrijfRij($uit, $rij);
    }
    rewind($uit);
    $csv = stream_get_contents($uit);
    fclose($uit);

    schrijfLog($logBestand, $huidigeGebruiker, 'leden', 'export van ' . count($ledenData['leden']) . ' leden');
    dataSlotDicht($lockHandle);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rc045-leden-' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF" . $csv;
    exit;

  } elseif ($formulier === 'leden_import_lezen') {
    // Stap 1 van de import: bestand inlezen, controleren en klaarzetten.
    // Er wordt nog niets opgeslagen; dat gebeurt pas na bevestigen.
    unset($_SESSION['leden_import']);
    if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
      $melding['leden'] = 'Geen bestand ontvangen. Is het groter dan de uploadlimiet van de server?';
      $meldingType['leden'] = 'fout';
    } elseif ($_FILES['csv']['size'] > 2 * 1024 * 1024) {
      $melding['leden'] = 'Het bestand is groter dan 2 MB.';
      $meldingType['leden'] = 'fout';
    } else {
      $inhoud = file_get_contents($_FILES['csv']['tmp_name']);
      $gelezen = ledenCsvLezen($inhoud === false ? '' : $inhoud);
      if (count($gelezen['rijen']) === 0) {
        $melding['leden'] = 'Geen bruikbare regels gevonden. Controleer of de eerste regel de kolomnamen bevat.';
        $meldingType['leden'] = 'fout';
      } else {
        $_SESSION['leden_import'] = $gelezen;
        $melding['leden'] = count($gelezen['rijen']) . ' regels ingelezen. Controleer hieronder en bevestig.';
        $meldingType['leden'] = 'ok';
      }
    }

  } elseif ($formulier === 'leden_import_bevestigen') {
    $gelezen = $_SESSION['leden_import'] ?? null;
    if (!is_array($gelezen) || count($gelezen['rijen'] ?? []) === 0) {
      $melding['leden'] = 'Er staat geen ingelezen bestand klaar. Kies het bestand opnieuw.';
      $meldingType['leden'] = 'fout';
    } else {
      $ledenData = ledenLees();
      $nieuw = 0; $bijgewerkt = 0;
      foreach ($gelezen['rijen'] as $rij) {
        $contributie = $rij['_contributie'] ?? [];
        unset($rij['_contributie']);
        $index = ledenZoekBestaande($ledenData, $rij);
        $bestaand = $index === null ? null : $ledenData['leden'][$index];
        $lid = ledenNormaliseer($rij, $bestaand);
        // Een commissienaam uit een CSV telt alleen als die commissie ook
        // echt bestaat. Nieuwe commissies maak je bewust aan bij Leden, niet
        // per ongeluk via een typefout in een importbestand.
        $lid['commissies'] = array_values(array_intersect($lid['commissies'], array_keys(ledenCommissies($ledenData))));
        $lid['bron'] = $index === null ? 'import' : ($lid['bron'] ?? 'import');
        if ($index === null && (int) $lid['nummer'] === 0) {
          $lid['nummer'] = ledenVolgendNummer($ledenData);
        }
        foreach ($contributie as $jaar => $regel) {
          $lid = ledenZetContributie($lid, $jaar, [
            'status'        => ledenContributieStatusUitTekst($regel['contributiestatus'] ?? ''),
            'bedrag'        => str_replace(',', '.', (string) ($regel['contributiebedrag'] ?? '')),
            'inschrijfgeld' => str_replace(',', '.', (string) ($regel['inschrijfgeld'] ?? '')),
            'betaald_op'    => '',
            'opmerking'     => '',
          ]);
        }
        if ($index === null) {
          $ledenData['leden'][] = $lid;
          $ledenData['volgnummer'] = max((int) $ledenData['volgnummer'], (int) $lid['nummer']);
          $nieuw++;
        } else {
          $ledenData['leden'][$index] = $lid;
          $bijgewerkt++;
        }
      }
      if (ledenSchrijf($ledenData)) {
        unset($_SESSION['leden_import']);
        schrijfLog($logBestand, $huidigeGebruiker, 'leden', "import: $nieuw nieuw, $bijgewerkt bijgewerkt");
        $_SESSION['flash']['leden'] = ['tekst' => "Import klaar: $nieuw nieuwe leden, $bijgewerkt bijgewerkt. De vorige versie staat in de back-ups.", 'type' => 'ok'];
        dataSlotDicht($lockHandle);
        header('Location: leden.php#leden');
        exit;
      }
      $melding['leden'] = 'Import mislukt bij het opslaan. Er is niets gewijzigd.';
      $meldingType['leden'] = 'fout';
    }

  } elseif ($formulier === 'leden_import_annuleren') {
    unset($_SESSION['leden_import']);
    $melding['leden'] = 'Import geannuleerd.';
    $meldingType['leden'] = 'ok';


  }

  dataSlotDicht($lockHandle);
}

// ===== Gegevens klaarzetten =====

// Contributiebedragen. Het bestuur beheert ze in beheer.php (tabblad
// Rekentabel); hier worden ze alleen gelezen, voor de contributie per lid.
$rekentabelData = $rekentabelStandaard;
if (file_exists($rekentabelBestand)) {
  $json = json_decode(file_get_contents($rekentabelBestand), true);
  if (is_array($json)) $rekentabelData = array_merge($rekentabelStandaard, $json);
}
$inschrijfkosten = (float) $rekentabelData['inschrijfkosten'];
$tabelJeugd  = rekentabelProRata((float) $rekentabelData['jeugd_jaarbedrag']);
$tabelSenior = rekentabelProRata((float) $rekentabelData['senior_jaarbedrag']);

// De volledige accountlijst is autorisatie-informatie en wordt alleen
// ingelezen wanneer deze gebruiker de koppeling ook daadwerkelijk mag
// wijzigen. Zo komen andere inlognamen niet onnodig in de HTML terecht.
$gebruikersLijst = $magLedenAutorisatieWijzigen ? laadGebruikers($usersBestand) : [];

// ===== Ledenadministratie =====
// Het ledenbestand staat buiten data/ omdat het persoonsgegevens bevat;
// zie leden-opslag.php. Hier alleen inlezen en klaarzetten voor het
// tabblad Leden: de lijst op achternaam, de tellingen per status, en
// eventueel het ene lid dat via ?lid=... bewerkt wordt.
$ledenData = ledenLees();
$ledenLijst = $ledenData['leden'];

// Naam per lid-id, gebruikt bij de Takenlijst en Operationele taken om
// snel de naam van een toegewezen lid te tonen zonder telkens de hele
// ledenlijst te doorlopen.
$ledenNaamBijId = [];
foreach ($ledenLijst as $ln) { $ledenNaamBijId[$ln['id']] = ledenVolledigeNaam($ln); }
usort($ledenLijst, function ($a, $b) { return ledenSorteernaam($a) <=> ledenSorteernaam($b); });

$ledenStatusLabels = ledenStatussen();
$ledenContributieLabels = ledenContributieStatussen();
$ledenFunctieLabels = ledenBestuursfuncties();
$ledenCommissieLijst = ledenCommissies($ledenData);

// Hoeveel leden zitten er in elke commissie? Alleen om bij het beheer van
// de lijst te kunnen zien wat je weggooit voordat je het weggooit.
$ledenCommissieTellingen = [];
foreach (array_keys($ledenCommissieLijst) as $cs) $ledenCommissieTellingen[$cs] = 0;
$ledenBestuurAantal = 0;
foreach ($ledenLijst as $l) {
  if (ledenIsBestuurslid($l)) $ledenBestuurAantal++;
  foreach ((isset($l['commissies']) && is_array($l['commissies'])) ? $l['commissies'] : [] as $cs) {
    if (isset($ledenCommissieTellingen[$cs])) $ledenCommissieTellingen[$cs]++;
  }
}
$ledenJaar = (int) $rekentabelData['jaar'];
$ledenJeugdTot = (int) $rekentabelData['jeugd_leeftijd_tot'];

$ledenTellingen = [];
foreach (array_keys($ledenStatusLabels) as $s) $ledenTellingen[$s] = 0;
foreach ($ledenLijst as $l) {
  $s = $l['status'] ?? 'nieuw';
  if (isset($ledenTellingen[$s])) $ledenTellingen[$s]++;
}

// Zelfde soort telling, maar dan per contributiestatus van het huidige
// jaar, voor de klikbare badges en het filter in de Leden-tab. 'leeg'
// is geen echte status uit ledenContributieStatussen(), maar een eigen
// bakje voor leden zonder contributieregel voor $ledenJaar.
$ledenContributieTellingen = [];
foreach (array_keys($ledenContributieLabels) as $s) $ledenContributieTellingen[$s] = 0;
$ledenContributieTellingen['leeg'] = 0;
foreach ($ledenLijst as $l) {
  $c = $l['contributie'][(string) $ledenJaar] ?? null;
  if ($c === null) {
    $ledenContributieTellingen['leeg']++;
    continue;
  }
  $s = $c['status'] ?? 'open';
  if (isset($ledenContributieTellingen[$s])) $ledenContributieTellingen[$s]++;
}

// ?lid=nieuw opent een leeg formulier, ?lid=<id> een bestaand lid.
$ledenBewerkId = isset($_GET['lid']) ? trim((string) $_GET['lid']) : '';
$ledenBewerkLid = null;
$ledenBewerkNieuw = false;
if ($ledenBewerkId === 'nieuw') {
  $ledenBewerkNieuw = true;
  $ledenBewerkLid = ledenNormaliseer(['status' => 'nieuw', 'inschrijfdatum' => date('Y-m-d')]);
  $ledenBewerkLid['nummer'] = ledenVolgendNummer($ledenData);
} elseif ($ledenBewerkId !== '') {
  foreach ($ledenLijst as $l) {
    if (($l['id'] ?? '') === $ledenBewerkId) { $ledenBewerkLid = $l; break; }
  }
}

// Contributieblokken voor het bewerkformulier: de bestaande jaren, en
// altijd één leeg blok erbij om een nieuw jaar toe te voegen.
$ledenBewerkContributie = [];
if ($ledenBewerkLid !== null) {
  foreach ($ledenBewerkLid['contributie'] as $jaar => $regel) {
    $regel['jaar'] = $jaar;
    $ledenBewerkContributie[] = $regel;
  }
  $volgendJaar = count($ledenBewerkContributie) === 0 ? (string) $ledenJaar : '';
  $ledenBewerkContributie[] = ['jaar' => $volgendJaar, 'status' => 'open', 'bedrag' => null,
                               'inschrijfgeld' => null, 'betaald_op' => '', 'opmerking' => ''];
}

// Voorstel voor het contributiebedrag, zodat het bestuur niet hoeft te
// rekenen. Alleen een hint; het bedrag blijft handmatig aan te passen
// voor bijvoorbeeld pro rata bij instappen halverwege het jaar.
function ledenBedragVoorstel($lid, $jaar, $rekentabelData) {
  $jeugd = ledenIsJeugd($lid, (int) $rekentabelData['jeugd_leeftijd_tot'], $jaar);
  if ($jeugd === null) return null;
  // Voor het jaar na het contributiejaar gelden de aparte bedragen uit de
  // rekentabel, als die al zijn ingevuld. Zonder dit zou het voorstel voor
  // een nieuw jaar het oude bedrag blijven noemen, terwijl aanmelden.html
  // het nieuwe al aan nieuwe leden laat zien.
  return rekentabelJaarbedrag($rekentabelData, $jeugd, $jaar);
}

$ledenImport = isset($_SESSION['leden_import']) && is_array($_SESSION['leden_import']) ? $_SESSION['leden_import'] : null;

// ===== Bestuursvergaderingen =====
// Alleen inlezen en klaarzetten voor het tabblad. De lijst met bestuurs-
// leden komt uit de ledenadministratie, zodat de aanwezigheidslijst
// vanzelf meegroeit als er iemand bij komt of vertrekt.
$vergaderingenData = vergaderingenLees();
$vergaderingenLijst = vergaderingenGesorteerd($vergaderingenData);
$vergaderingStatusLabels = vergaderingenStatussen();
$vergaderingAanwezigheidLabels = vergaderingenAanwezigheid();

$bestuursLeden = [];
foreach ($ledenLijst as $l) {
  if (ledenIsBestuurslid($l)) $bestuursLeden[] = $l;
}
// Voorzitter, penningmeester, secretaris, dan de rest: dat is de volgorde
// waarin een presentielijst normaal gesproken wordt afgelopen.
$bestuursVolgorde = array_flip(array_keys($ledenFunctieLabels));
usort($bestuursLeden, function ($a, $b) use ($bestuursVolgorde) {
  $va = $bestuursVolgorde[$a['bestuursfunctie'] ?? ''] ?? 99;
  $vb = $bestuursVolgorde[$b['bestuursfunctie'] ?? ''] ?? 99;
  if ($va !== $vb) return $va <=> $vb;
  return ledenSorteernaam($a) <=> ledenSorteernaam($b);
});

// ?vergadering=nieuw opent een leeg formulier, ?vergadering=<id> een
// bestaande. Zelfde patroon als ?lid=... bij Leden.
$vergaderingBewerkId = isset($_GET['vergadering']) ? trim((string) $_GET['vergadering']) : '';
$vergaderingBewerk = null;
$vergaderingNieuw = false;
if ($vergaderingBewerkId === 'nieuw') {
  $vergaderingNieuw = true;
  $vergaderingBewerk = vergaderingNormaliseer(['status' => 'gepland', 'tijd' => '20:00']);
  $vergaderingBewerk['nummer'] = vergaderingVolgendNummer($vergaderingenData);
} elseif ($vergaderingBewerkId !== '') {
  foreach ($vergaderingenLijst as $v) {
    if (($v['id'] ?? '') === $vergaderingBewerkId) { $vergaderingBewerk = $v; break; }
  }
}

// Agendablokken voor het formulier: de bestaande punten, en altijd één
// leeg blok erbij om een punt toe te voegen.
$vergaderingAgendaBlokken = [];
if ($vergaderingBewerk !== null) {
  foreach ($vergaderingBewerk['agenda'] as $punt) $vergaderingAgendaBlokken[] = $punt;
  $vergaderingAgendaBlokken[] = ['onderwerp' => '', 'indiener' => '', 'toelichting' => '', 'besluit' => ''];
}

// ===== Commissies (volledig, met bestuurslid en commissiehoofd) =====
$ledenCommissieVolledigLijst = ledenCommissiesVolledig($ledenData);

// Actieve leden voor de presentielijst bij een ledenvergadering: in
// tegenstelling tot de bestuursvergadering (alleen bestuursleden) is bij
// een ledenvergadering in principe elk actief lid uitgenodigd. Op naam
// gesorteerd, geen functievolgorde zoals bij het bestuur.
$ledenActiefVoorAanwezigheid = [];
foreach ($ledenLijst as $l) {
  if (($l['status'] ?? '') === 'actief') $ledenActiefVoorAanwezigheid[] = $l;
}
usort($ledenActiefVoorAanwezigheid, function ($a, $b) {
  return ledenSorteernaam($a) <=> ledenSorteernaam($b);
});

// ===== Ledenvergaderingen (incl. ALV) =====
// Zelfde bestand en functies als bij Bestuursvergadering, alleen gefilterd
// op soort 'leden'. Presentielijst loopt hier over alle actieve leden
// ($ledenActiefVoorAanwezigheid hierboven), niet alleen het bestuur.
$ledenvergaderingenLijst = vergaderingenVanSoort($vergaderingenData, 'leden');
$vergaderingLedenTypeLabels = vergaderingenLedenTypes();

$ledenvergaderingBewerkId = isset($_GET['ledenvergadering']) ? trim((string) $_GET['ledenvergadering']) : '';
$ledenvergaderingBewerk = null;
$ledenvergaderingNieuw = false;
if ($ledenvergaderingBewerkId === 'nieuw') {
  $ledenvergaderingNieuw = true;
  $ledenvergaderingBewerk = vergaderingNormaliseer(['status' => 'gepland', 'tijd' => '20:00', 'soort' => 'leden', 'ledenvergadering_type' => 'regulier']);
  $ledenvergaderingBewerk['soort'] = 'leden';
  $ledenvergaderingBewerk['nummer'] = vergaderingVolgendNummer($vergaderingenData, 'leden');
} elseif ($ledenvergaderingBewerkId !== '') {
  foreach ($ledenvergaderingenLijst as $v) {
    if (($v['id'] ?? '') === $ledenvergaderingBewerkId) { $ledenvergaderingBewerk = $v; break; }
  }
}

$ledenvergaderingAgendaBlokken = [];
if ($ledenvergaderingBewerk !== null) {
  foreach ($ledenvergaderingBewerk['agenda'] as $punt) $ledenvergaderingAgendaBlokken[] = $punt;
  $ledenvergaderingAgendaBlokken[] = ['onderwerp' => '', 'indiener' => '', 'toelichting' => '', 'besluit' => ''];
}

// ===== Takenlijst =====
$takenData = takenLees();
$takenLijst = takenGesorteerd($takenData);
$taakStatusLabels = takenStatussen();

// Voor de koppeling met een vergadering: alle vergaderingen (beide
// soorten) op id, en gegroepeerd per soort voor de keuzelijst in het
// formulier.
$vergaderingenBijId = [];
foreach ($vergaderingenData['vergaderingen'] as $v) {
  $vergaderingenBijId[$v['id']] = $v;
}
$vergaderingenVoorTaakKeuze = [
  'bestuur' => vergaderingenVanSoort($vergaderingenData, 'bestuur'),
  'leden'   => vergaderingenVanSoort($vergaderingenData, 'leden'),
];

$taakBewerkId = isset($_GET['taak']) ? trim((string) $_GET['taak']) : '';
$taakBewerk = null;
$taakNieuw = false;
if ($taakBewerkId === 'nieuw') {
  $taakNieuw = true;
  $taakBewerk = taakNormaliseer(['status' => 'open']);
  $taakBewerk['nummer'] = taakVolgendNummer($takenData);
} elseif ($taakBewerkId !== '') {
  foreach ($takenLijst as $t) {
    if (($t['id'] ?? '') === $taakBewerkId) { $taakBewerk = $t; break; }
  }
}

// ===== Operationele taken =====
// Terugkerende klussen los van de bestuurstaken hierboven. Een taak met
// zichtbaarheid "bestuur" mag een lid zonder bestuursfunctie nergens zien,
// dus die worden hier al uit de lijst gefilterd (niet pas in de weergave).
// $isBestuurslid komt uit het rechtenblok bovenin dit bestand.
$otakenData = otakenLees();
$otakenAlle = otakenGesorteerd($otakenData);
$otaakFrequentieLabels = otaakFrequenties();
$otaakZichtbaarheidLabels = otaakZichtbaarheden();
$otaakStatusLabels = otaakStatusLabels();
$otakenLijst = $isBestuurslid ? $otakenAlle : array_values(array_filter($otakenAlle, function ($t) {
  return ($t['zichtbaarheid'] ?? 'leden') === 'leden';
}));

$otaakBewerkId = isset($_GET['otaak']) ? trim((string) $_GET['otaak']) : '';
$otaakBewerk = null;
$otaakNieuw = false;
if ($otaakBewerkId === 'nieuw') {
  $otaakNieuw = true;
  $otaakBewerk = otaakNormaliseer(['zichtbaarheid' => 'leden', 'actief' => '1']);
  $otaakBewerk['nummer'] = otaakVolgendNummer($otakenData);
} elseif ($otaakBewerkId !== '') {
  // Gezocht in de al-gefilterde lijst: zo kan een lid zonder bestuursfunctie
  // een bestuurs-only taak ook niet openen door zelf het taak-id in de URL
  // te raden of te onthouden.
  foreach ($otakenLijst as $t) {
    if (($t['id'] ?? '') === $otaakBewerkId) { $otaakBewerk = $t; break; }
  }
}

// ===== Evenementen =====
// Zelfde opzet als Operationele taken hierboven: een evenement dat voor
// een lid zonder bestuursfunctie niet zichtbaar is (bestuur-only, of nog
// voor de begindatum inschrijving) wordt hier al uit de lijst gefilterd,
// niet pas in de weergave, zodat zo iemand het ook niet via een geraden id
// kan openen. Zie evenementZichtbaarVoorLeden() in evenementen-opslag.php.
$evenementenData = evenementenLees();
$evenementenAlle = evenementenGesorteerd($evenementenData);
$evenementZichtbaarheidLabels = evenementZichtbaarheden();
$evenementStatusLabels = evenementStatusLabels();
$evenementenLijst = $isBestuurslid ? $evenementenAlle : array_values(array_filter($evenementenAlle, 'evenementZichtbaarVoorLeden'));

$evenementBewerkId = isset($_GET['evenement']) ? trim((string) $_GET['evenement']) : '';
$evenementBewerk = null;
$evenementNieuw = false;
if ($evenementBewerkId === 'nieuw') {
  $evenementNieuw = true;
  $evenementBewerk = evenementNormaliseer(['zichtbaarheid' => 'leden']);
  $evenementBewerk['nummer'] = evenementVolgendNummer($evenementenData);
} elseif ($evenementBewerkId !== '') {
  foreach ($evenementenLijst as $ev) {
    if (($ev['id'] ?? '') === $evenementBewerkId) { $evenementBewerk = $ev; break; }
  }
}

// Dubbele lidnummers. Kan gebeuren als er handmatig een lid is toegevoegd
// (dat krijgt het eerstvolgende vrije nummer) en er daarna een import komt
// waarin dat nummer al aan iemand anders vastzit. Geen fout die iets kapot
// maakt, maar wel iets om te weten, dus komt er een melding bovenaan de lijst.
$ledenNummerTelling = [];
foreach ($ledenLijst as $l) {
  $n = (int) ($l['nummer'] ?? 0);
  if ($n <= 0) continue;
  $ledenNummerTelling[$n] = ($ledenNummerTelling[$n] ?? 0) + 1;
}
$ledenDubbeleNummers = array_keys(array_filter($ledenNummerTelling, function ($aantal) { return $aantal > 1; }));
sort($ledenDubbeleNummers);

// Erbij welke leden het zijn, anders moet je zelf op zoek naar wie het
// dubbele nummer heeft.
$ledenDubbeleNummerRegels = [];
foreach ($ledenDubbeleNummers as $dubbelNr) {
  $namen = [];
  foreach ($ledenLijst as $l) {
    if ((int) ($l['nummer'] ?? 0) === $dubbelNr) $namen[] = ledenVolledigeNaam($l);
  }
  $ledenDubbeleNummerRegels[] = $dubbelNr . ' (' . implode(' & ', $namen) . ')';
}

// ===== Tabblad Mijn RC045 =====
// Alles hieronder is de kant van het gewone lid: lezen, en zich in- of
// uitschrijven voor een evenement. Het bestuurstabblad Evenementen verderop
// gaat over het aanmaken en beheren ervan.

$mijnLedenBijId = [];
foreach ($ledenData['leden'] as $mijnL) {
  if (isset($mijnL['id'])) $mijnLedenBijId[$mijnL['id']] = $mijnL;
}

// Naam bij een lid-id, leeg als het lid niet (meer) bestaat.
function mijnNaam($lidId, $ledenBijId) {
  $lidId = trim((string) $lidId);
  if ($lidId === '' || !isset($ledenBijId[$lidId])) return '';
  return ledenVolledigeNaam($ledenBijId[$lidId]);
}

// Openstaande punten uit de takenlijst die bij de leden horen. Afgerond is
// bestuursarchief, dat hoort niet in dit overzicht.
$mijnActiepunten = [];
foreach (takenGesorteerd($takenData) as $mijnT) {
  if (($mijnT['vergadering_soort'] ?? '') !== 'leden') continue;
  if (($mijnT['status'] ?? 'open') === 'afgerond') continue;
  $mijnActiepunten[] = $mijnT;
}

// Operationele taken met zichtbaarheid "leden", niet gepauzeerd.
$mijnOperationeel = [];
foreach (otakenGesorteerd($otakenData) as $mijnT) {
  if (($mijnT['zichtbaarheid'] ?? 'leden') !== 'leden') continue;
  if (empty($mijnT['actief'])) continue;
  $mijnOperationeel[] = $mijnT;
}

// Ledenvergaderingen en ALV's, nieuwste bovenaan.
$mijnVergaderingen = vergaderingenVanSoort($vergaderingenData, 'leden');

// Evenementen die voor leden zichtbaar zijn.
$mijnEvenementen = [];
foreach (evenementenGesorteerd($evenementenData) as $mijnE) {
  if (!evenementZichtbaarVoorLeden($mijnE)) continue;
  $mijnEvenementen[] = $mijnE;
}

$documentStatusLabels = vergaderingDocumentStatussen();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Leden | RC045</title>
  <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
  <link rel="stylesheet" href="paneel.css?v=<?php echo @filemtime(__DIR__ . '/paneel.css'); ?>">
  <script src="paneel-thema.js?v=<?php echo @filemtime(__DIR__ . '/paneel-thema.js'); ?>"></script>
</head>
<body>
  <button type="button" id="thema-switch" class="thema-switch"></button>
  <div class="wrap">

  <?php if (!$configOk): ?>

    <div class="kaart kaart-smal">
      <h1>Leden</h1>
      <div class="melding fout">
        Configuratie ontbreekt. Upload eenmalig het bestand <strong>beheer-config.php</strong> via FTP naar dezelfde map als deze pagina.
      </div>
    </div>

  <?php elseif (!$ingelogd): ?>

    <?php authInlogFormulier('RC045 leden'); ?>

  <?php else: ?>

    <div class="ingelogd-balk">
      <span>
        Ingelogd als <strong><?php echo htmlspecialchars($eigenLid ? ledenVolledigeNaam($eigenLid) : $huidigeGebruiker); ?></strong><?php if (($eigenRolLid['functie'] ?? '') !== ''): ?>, <?php echo htmlspecialchars($eigenRolLid['functie']); ?><?php endif; ?>
      </span>
      <form method="post" action="leden.php" style="display:inline;">
        <input type="hidden" name="formulier" value="uitloggen">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <button type="submit" class="link-knop">Uitloggen</button>
      </form>
    </div>

    <?php if (isset($melding['csrf'])): ?>
      <div class="melding <?php echo $meldingType['csrf']; ?>"><?php echo htmlspecialchars($melding['csrf']); ?></div>
    <?php endif; ?>

    <div class="beheer-layout">
    <button type="button" class="beheer-menu-knop" id="beheer-menu-knop" aria-expanded="false" aria-controls="beheer-menu">
      <span id="beheer-menu-huidig">Menu</span>
      <span class="streepjes" aria-hidden="true">☰</span>
    </button>
    <nav class="menu" id="beheer-menu">
      <?php
        // Zelfde menu-opzet als beheer.php: alleen groepering van de knoppen,
        // geen invloed op welke tabbladen iemand mag zien. Een groepslabel
        // verschijnt alleen als er in die groep ook echt iets zichtbaar is.
        $menuGroepen = [
          ['label' => 'Mijn RC045', 'tabs' => ['mijn']],
          ['label' => 'Ledenadministratie', 'tabs' => ['leden', 'commissies']],
          ['label' => 'Bestuur', 'tabs' => ['bestuursvergadering', 'ledenvergadering', 'takenlijst', 'operationele_taken', 'evenementen']],
        ];
      ?>
      <?php foreach ($menuGroepen as $groepIndex => $groep): ?>
        <?php
          $zichtbaar = [];
          foreach ($groep['tabs'] as $tabSleutel) {
            if (in_array($tabSleutel, $toegestaneTabs, true)) $zichtbaar[] = $tabSleutel;
          }
        ?>
        <?php if (!empty($zichtbaar)): ?>
      <div class="menu-groep">
        <button type="button" class="menu-groep-label" data-groep="<?php echo $groepIndex; ?>" aria-expanded="false" aria-controls="menu-groep-items-<?php echo $groepIndex; ?>">
          <span><?php echo htmlspecialchars($groep['label']); ?></span>
          <span class="menu-groep-pijl" aria-hidden="true">&#9656;</span>
        </button>
        <div class="menu-groep-items" id="menu-groep-items-<?php echo $groepIndex; ?>" data-groep="<?php echo $groepIndex; ?>" hidden>
          <?php foreach ($zichtbaar as $tabSleutel): ?>
      <button type="button" class="menu-item" data-tab="<?php echo $tabSleutel; ?>"><?php echo htmlspecialchars($ledenTabsAlle[$tabSleutel]); ?></button>
          <?php endforeach; ?>
        </div>
      </div>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if (in_array('mijn', $toegestaneTabs, true) && count($toegestaneTabs) > 1): ?>
        <div class="menu-groep">
          <a class="menu-item" href="beheer.php">Naar het beheer &rarr;</a>
        </div>
      <?php endif; ?>
    </nav>

    <div class="beheer-inhoud">

    <?php if (in_array('mijn', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-mijn">
    <!-- ===== MIJN RC045 ===== -->

    <?php if (!$eigenLid): ?>
      <div class="melding fout">
        Dit account is nog niet aan een lid gekoppeld, dus je kunt je nergens voor inschrijven. Het bestuur doet dat bij Leden.
      </div>
    <?php endif; ?>

    <div class="kaart">
      <h1>Actiepunten</h1>
      <p class="sub">Wat er open staat bij de leden.</p>

      <?php if (count($mijnActiepunten) === 0 && count($mijnOperationeel) === 0): ?>
        <p class="leeg">Er staat op dit moment niets open.</p>
      <?php endif; ?>

      <?php foreach ($mijnActiepunten as $t): ?>
        <?php
          $toegewezen = mijnNaam($t['toegewezen_aan'] ?? '', $mijnLedenBijId);
          $vanJou = $eigenLidId !== '' && ($t['toegewezen_aan'] ?? '') === $eigenLidId;
        ?>
        <div class="regel">
          <div class="regel-kop">
            <span class="regel-titel"><?php echo htmlspecialchars($t['omschrijving'] ?? ''); ?></span>
            <span class="label <?php echo ($t['status'] ?? 'open') === 'open' ? 'goud' : 'grijs'; ?>"><?php echo htmlspecialchars($taakStatusLabels[$t['status'] ?? 'open'] ?? 'Open'); ?></span>
            <?php if ($vanJou): ?><span class="label jij">Voor jou</span><?php endif; ?>
          </div>
          <?php if ($toegewezen !== '' && !$vanJou): ?>
            <div class="regel-meta">Toegewezen aan <?php echo htmlspecialchars($toegewezen); ?></div>
          <?php endif; ?>
          <?php if (trim((string) ($t['toelichting'] ?? '')) !== ''): ?>
            <div class="regel-tekst"><?php echo htmlspecialchars($t['toelichting']); ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php foreach ($mijnOperationeel as $t): ?>
        <?php
          $toegewezen = mijnNaam($t['toegewezen_aan'] ?? '', $mijnLedenBijId);
          $vanJou = $eigenLidId !== '' && ($t['toegewezen_aan'] ?? '') === $eigenLidId;
          $otStatus = otaakStatus($t);
        ?>
        <div class="regel">
          <div class="regel-kop">
            <span class="regel-titel"><?php echo htmlspecialchars($t['omschrijving'] ?? ''); ?></span>
            <span class="label <?php echo $otStatus === 'te_doen' ? 'goud' : 'grijs'; ?>"><?php echo htmlspecialchars($otaakStatusLabels[$otStatus] ?? ''); ?></span>
            <span class="label grijs"><?php echo htmlspecialchars($otaakFrequentieLabels[$t['frequentie'] ?? 'maandelijks'] ?? ''); ?></span>
            <?php if ($vanJou): ?><span class="label jij">Voor jou</span><?php endif; ?>
          </div>
          <div class="regel-meta">
            <?php if ($toegewezen !== '' && !$vanJou): ?>Toegewezen aan <?php echo htmlspecialchars($toegewezen); ?>. <?php endif; ?>
            <?php if (($t['laatst_uitgevoerd'] ?? '') !== ''): ?>Laatst gedaan op <?php echo htmlspecialchars(datumWeergave($t['laatst_uitgevoerd'])); ?>.<?php else: ?>Nog niet eerder gedaan.<?php endif; ?>
          </div>
          <?php if (trim((string) ($t['toelichting'] ?? '')) !== ''): ?>
            <div class="regel-tekst"><?php echo htmlspecialchars($t['toelichting']); ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="kaart">
      <h1>Ledenvergaderingen en ALV's</h1>
      <p class="sub">De agenda staat er altijd bij. Het verslag verschijnt zodra de notulen zijn vastgesteld.</p>

      <?php if (count($mijnVergaderingen) === 0): ?>
        <p class="leeg">Er staan nog geen ledenvergaderingen gepland.</p>
      <?php endif; ?>

      <?php foreach ($mijnVergaderingen as $v): ?>
        <div class="regel">
          <div class="regel-kop">
            <span class="regel-titel">
              <?php
                $vTitel = trim((string) ($v['titel'] ?? ''));
                echo htmlspecialchars($vTitel !== '' ? $vTitel : ($vergaderingLedenTypeLabels[$v['ledenvergadering_type'] ?? 'regulier'] ?? 'Ledenvergadering'));
              ?>
            </span>
            <span class="label"><?php echo htmlspecialchars($vergaderingLedenTypeLabels[$v['ledenvergadering_type'] ?? 'regulier'] ?? ''); ?></span>
            <span class="label <?php echo ($v['status'] ?? '') === 'geannuleerd' ? 'rood' : 'grijs'; ?>"><?php echo htmlspecialchars($vergaderingStatusLabels[$v['status'] ?? 'gepland'] ?? ''); ?></span>
          </div>
          <div class="regel-meta">
            <?php echo htmlspecialchars(datumWeergave($v['datum'] ?? '')); ?>
            <?php if (($v['tijd'] ?? '') !== ''): ?>om <?php echo htmlspecialchars($v['tijd']); ?><?php endif; ?>
            <?php if (($v['locatie'] ?? '') !== ''): ?>, <?php echo htmlspecialchars($v['locatie']); ?><?php endif; ?>
          </div>

          <?php if (vergaderingAgendaZichtbaarVoorLeden($v)): ?>
            <details>
              <summary>Agenda (<?php echo htmlspecialchars($documentStatusLabels[$v['agenda_status'] ?? 'concept'] ?? 'Concept'); ?>)</summary>
              <div>
                <ol class="agenda">
                  <?php foreach ($v['agenda'] as $punt): ?>
                    <li>
                      <?php echo htmlspecialchars($punt['onderwerp'] ?? ''); ?>
                      <?php if (trim((string) ($punt['indiener'] ?? '')) !== ''): ?>
                        <span class="regel-meta">(<?php echo htmlspecialchars($punt['indiener']); ?>)</span>
                      <?php endif; ?>
                      <?php if (trim((string) ($punt['toelichting'] ?? '')) !== ''): ?>
                        <div class="toel"><?php echo htmlspecialchars($punt['toelichting']); ?></div>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ol>
              </div>
            </details>
          <?php endif; ?>

          <?php if (vergaderingNotulenZichtbaarVoorLeden($v)): ?>
            <details>
              <summary>Notulen</summary>
              <div><?php echo htmlspecialchars($v['notulen']); ?></div>
            </details>
          <?php elseif (($v['status'] ?? '') === 'afgerond'): ?>
            <p class="hint">De notulen zijn nog niet vastgesteld.</p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="kaart">
      <h1>Evenementen</h1>
      <p class="sub">Waar je je voor kunt inschrijven.</p>

      <?php if (isset($melding['mijn'])): ?>
        <div class="melding <?php echo $meldingType['mijn'] === 'fout' ? 'fout' : 'ok'; ?>"><?php echo htmlspecialchars($melding['mijn']); ?></div>
      <?php endif; ?>

      <?php if (count($mijnEvenementen) === 0): ?>
        <p class="leeg">Er staan op dit moment geen evenementen open.</p>
      <?php endif; ?>

      <?php foreach ($mijnEvenementen as $e): ?>
        <?php
          $ingeschreven = $eigenLidId !== '' && evenementHeeftDeelnemer($e, $eigenLidId);
          $inschrijvingOpen = evenementInschrijvingOpen($e);
          $isVol = evenementIsVol($e);
          $aantalDeelnemers = evenementAantalDeelnemers($e);
          $capaciteit = (int) ($e['capaciteit'] ?? 0);
        ?>
        <div class="regel">
          <div class="regel-kop">
            <span class="regel-titel"><?php echo htmlspecialchars($e['titel'] ?? ''); ?></span>
            <span class="label <?php echo evenementStatus($e) === 'aankomend' ? '' : 'grijs'; ?>"><?php echo htmlspecialchars($evenementStatusLabels[evenementStatus($e)] ?? ''); ?></span>
            <?php if ($ingeschreven): ?><span class="label jij">Ingeschreven</span><?php endif; ?>
            <?php if ($isVol && !$ingeschreven): ?><span class="label rood">Vol</span><?php endif; ?>
          </div>
          <div class="regel-meta">
            <?php $eDatum = datumWeergave($e['datum'] ?? ''); echo $eDatum !== '' ? htmlspecialchars($eDatum) : 'Datum volgt'; ?>
            <?php if (($e['tijd'] ?? '') !== ''): ?>
              om <?php echo htmlspecialchars($e['tijd']); ?><?php if (($e['eindtijd'] ?? '') !== ''): ?> tot <?php echo htmlspecialchars($e['eindtijd']); ?><?php endif; ?>
            <?php endif; ?>
            <?php if (($e['locatie'] ?? '') !== ''): ?>, <?php echo htmlspecialchars($e['locatie']); ?><?php endif; ?>
            <?php if ($capaciteit > 0): ?>. <?php echo $aantalDeelnemers; ?> van <?php echo $capaciteit; ?> plekken bezet<?php else: ?>. <?php echo $aantalDeelnemers; ?> ingeschreven<?php endif; ?>
            <?php if (($e['inschrijving_eind'] ?? '') !== ''): ?>. Inschrijven kan tot <?php echo htmlspecialchars(datumWeergave($e['inschrijving_eind'])); ?><?php endif; ?>
          </div>

          <?php if (trim((string) ($e['omschrijving'] ?? '')) !== ''): ?>
            <div class="regel-tekst"><?php echo htmlspecialchars($e['omschrijving']); ?></div>
          <?php endif; ?>

          <?php if ($ingeschreven && trim((string) ($e['betaalverzoek'] ?? '')) !== ''): ?>
            <div class="regel-tekst"><strong>Betalen:</strong> <?php echo htmlspecialchars($e['betaalverzoek']); ?></div>
          <?php endif; ?>

          <?php if ($eigenLidId !== '' && $inschrijvingOpen): ?>
            <div class="acties">
              <?php if ($ingeschreven): ?>
                <form method="post" action="leden.php">
                  <input type="hidden" name="formulier" value="evenement_uitschrijven">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                  <input type="hidden" name="evenement_id" value="<?php echo htmlspecialchars($e['id'] ?? ''); ?>">
                  <button type="submit" class="knop-klein">Inschrijving intrekken</button>
                </form>
              <?php elseif ($isVol): ?>
                <button type="button" class="knop-klein" disabled>Vol</button>
              <?php else: ?>
                <form method="post" action="leden.php">
                  <input type="hidden" name="formulier" value="evenement_inschrijven">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                  <input type="hidden" name="evenement_id" value="<?php echo htmlspecialchars($e['id'] ?? ''); ?>">
                  <button type="submit">Inschrijven</button>
                </form>
              <?php endif; ?>
            </div>
          <?php elseif ($eigenLidId !== '' && !$inschrijvingOpen): ?>
            <p class="hint">De inschrijving is gesloten.</p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    </div>
    <?php endif; ?>

    <?php if (in_array('leden', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-leden">
    <!-- ===== LEDENADMINISTRATIE ===== -->

    <?php if ($ledenBewerkLid !== null): ?>
    <div class="kaart" id="leden-bewerken">
      <div class="kaart-header">
        <div>
          <h1><?php echo $ledenBewerkNieuw ? 'Nieuw lid' : 'Lid bewerken'; ?></h1>
          <p class="sub"><?php echo $ledenBewerkNieuw ? 'Vul in wat je hebt. Alleen een naam is verplicht, de rest kan later.' : htmlspecialchars(ledenVolledigeNaam($ledenBewerkLid)); ?></p>
        </div>
        <a class="knop-klein" href="leden.php#leden">Sluiten</a>
      </div>

      <?php if (isset($melding['leden'])): ?>
        <div class="melding <?php echo $meldingType['leden']; ?>"><?php echo htmlspecialchars($melding['leden']); ?></div>
      <?php endif; ?>

      <form method="post" action="leden.php#leden" autocomplete="off">
        <input type="hidden" name="formulier" value="leden_opslaan">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="lid_id" value="<?php echo $ledenBewerkNieuw ? '' : htmlspecialchars($ledenBewerkLid['id']); ?>">

        <div class="sectie-kop">Persoonsgegevens</div>
        <div class="rij-3">
          <div class="veld">
            <label for="lid-nummer">Lidnummer</label>
            <?php
              $ledenVrijNummer = ledenVolgendNummer($ledenData);
              $ledenBewerkNummerBotst = (int) $ledenBewerkLid['nummer'] > 0
                && ($ledenNummerTelling[(int) $ledenBewerkLid['nummer']] ?? 0) > 1;
            ?>
            <?php if ($ledenBewerkNummerBotst): ?>
              <div style="display:flex; gap:8px;">
                <input type="number" id="lid-nummer" name="nummer" min="1" step="1" value="<?php echo htmlspecialchars((string) $ledenBewerkLid['nummer']); ?>" style="flex:1;">
                <button type="button" class="knop-klein" id="lid-nummer-vrij" data-vrij="<?php echo (int) $ledenVrijNummer; ?>" style="width:auto; flex:0 0 auto;">Gebruik <?php echo (int) $ledenVrijNummer; ?></button>
              </div>
              <p class="hint">Dit nummer komt ook bij een ander lid voor. Kies een ander nummer, de knop vult het eerstvolgende vrije nummer (<?php echo (int) $ledenVrijNummer; ?>) meteen in.</p>
            <?php else: ?>
              <input type="number" id="lid-nummer" name="nummer" min="1" step="1" value="<?php echo htmlspecialchars((string) $ledenBewerkLid['nummer']); ?>">
              <p class="hint">Moet uniek zijn: opslaan mislukt als dit nummer al bij een ander lid hoort.</p>
            <?php endif; ?>
          </div>
          <div class="veld">
            <label for="lid-status">Status</label>
            <select id="lid-status" name="status">
              <?php foreach ($ledenStatusLabels as $sleutel => $label): ?>
                <option value="<?php echo htmlspecialchars($sleutel); ?>" <?php echo $ledenBewerkLid['status'] === $sleutel ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="veld">
            <label for="lid-inschrijfdatum">Inschrijfdatum</label>
            <div class="datum-invoer-rij">
              <input type="text" inputmode="numeric" id="lid-inschrijfdatum" name="inschrijfdatum" maxlength="10" placeholder="dd-mm-jjjj" value="<?php echo htmlspecialchars(datumWeergave($ledenBewerkLid['inschrijfdatum'])); ?>">
              <button type="button" class="datum-picker-wrap" title="Datum kiezen uit kalender" aria-label="Datum kiezen"><span class="datum-picker-icoon" aria-hidden="true">📅</span><input type="date" class="datum-picker" data-doel="lid-inschrijfdatum" tabindex="-1" aria-hidden="true"></button>
            </div>
          </div>
        </div>

        <div class="rij-3">
          <div class="veld">
            <label for="lid-voornaam">Voornaam</label>
            <input type="text" id="lid-voornaam" name="voornaam" maxlength="60" value="<?php echo htmlspecialchars($ledenBewerkLid['voornaam']); ?>">
          </div>
          <div class="veld">
            <label for="lid-tussenvoegsel">Tussenvoegsel</label>
            <input type="text" id="lid-tussenvoegsel" name="tussenvoegsel" maxlength="30" value="<?php echo htmlspecialchars($ledenBewerkLid['tussenvoegsel']); ?>">
          </div>
          <div class="veld">
            <label for="lid-achternaam">Achternaam</label>
            <input type="text" id="lid-achternaam" name="achternaam" maxlength="80" value="<?php echo htmlspecialchars($ledenBewerkLid['achternaam']); ?>">
          </div>
        </div>

        <div class="rij-3">
          <div class="veld">
            <label for="lid-geboortedatum">Geboortedatum</label>
            <div class="datum-invoer-rij">
              <input type="text" inputmode="numeric" id="lid-geboortedatum" name="geboortedatum" maxlength="10" placeholder="dd-mm-jjjj" value="<?php echo htmlspecialchars(datumWeergave($ledenBewerkLid['geboortedatum'])); ?>">
              <button type="button" class="datum-picker-wrap" title="Datum kiezen uit kalender" aria-label="Datum kiezen"><span class="datum-picker-icoon" aria-hidden="true">📅</span><input type="date" class="datum-picker" data-doel="lid-geboortedatum" tabindex="-1" aria-hidden="true"></button>
            </div>
            <?php
              $bewerkLeeftijd = ledenLeeftijd($ledenBewerkLid['geboortedatum']);
              $bewerkJeugd = ledenIsJeugd($ledenBewerkLid, $ledenJeugdTot, $ledenJaar);
            ?>
            <p class="hint">
              <?php if ($bewerkLeeftijd === null): ?>
                Leeftijd en jeugd of senior worden hieruit berekend.
              <?php else: ?>
                Nu <?php echo $bewerkLeeftijd; ?> jaar, op 1 januari <?php echo $ledenJaar; ?> <?php echo $bewerkJeugd ? 'jeugdlid' : 'senior'; ?>.
              <?php endif; ?>
            </p>
          </div>
          <div class="veld">
            <label for="lid-telefoon">Telefoon / WhatsApp</label>
            <input type="text" id="lid-telefoon" name="telefoon" maxlength="40" value="<?php echo htmlspecialchars($ledenBewerkLid['telefoon']); ?>">
          </div>
          <div class="veld">
            <label for="lid-email">Mailadres</label>
            <input type="email" id="lid-email" name="email" maxlength="120" value="<?php echo htmlspecialchars($ledenBewerkLid['email']); ?>">
          </div>
        </div>

        <div class="sectie-kop">Adres</div>
        <div class="rij-3">
          <div class="veld">
            <label for="lid-straat">Straat</label>
            <input type="text" id="lid-straat" name="straat" maxlength="100" value="<?php echo htmlspecialchars($ledenBewerkLid['straat']); ?>">
          </div>
          <div class="veld">
            <label for="lid-huisnummer">Huisnummer</label>
            <input type="text" id="lid-huisnummer" name="huisnummer" maxlength="20" value="<?php echo htmlspecialchars($ledenBewerkLid['huisnummer']); ?>">
          </div>
          <div class="veld">
            <label for="lid-postcode">Postcode</label>
            <input type="text" id="lid-postcode" name="postcode" maxlength="20" value="<?php echo htmlspecialchars($ledenBewerkLid['postcode']); ?>">
          </div>
        </div>

        <div class="rij-2">
          <div class="veld">
            <label for="lid-gemeente">Woonplaats</label>
            <input type="text" id="lid-gemeente" name="gemeente" maxlength="80" value="<?php echo htmlspecialchars($ledenBewerkLid['gemeente']); ?>">
          </div>
          <div class="veld">
            <label for="lid-land">Land</label>
            <?php
              $ledenLandenLijst = ledenLanden();
              $ledenLandHuidig = trim((string) $ledenBewerkLid['land']);
              $ledenLandBekend = $ledenLandHuidig === '' || in_array($ledenLandHuidig, $ledenLandenLijst['boven'], true) || in_array($ledenLandHuidig, $ledenLandenLijst['overig'], true);
            ?>
            <select id="lid-land" name="land">
              <option value=""<?php echo $ledenLandHuidig === '' ? ' selected' : ''; ?>>Kies een land</option>
              <?php if (!$ledenLandBekend): ?>
                <option value="<?php echo htmlspecialchars($ledenLandHuidig); ?>" selected><?php echo htmlspecialchars($ledenLandHuidig); ?></option>
              <?php endif; ?>
              <optgroup label="Meest gebruikt">
                <?php foreach ($ledenLandenLijst['boven'] as $ledenLandOptie): ?>
                  <option value="<?php echo htmlspecialchars($ledenLandOptie); ?>"<?php echo $ledenLandHuidig === $ledenLandOptie ? ' selected' : ''; ?>><?php echo htmlspecialchars($ledenLandOptie); ?></option>
                <?php endforeach; ?>
              </optgroup>
              <optgroup label="Overige landen">
                <?php foreach ($ledenLandenLijst['overig'] as $ledenLandOptie): ?>
                  <option value="<?php echo htmlspecialchars($ledenLandOptie); ?>"<?php echo $ledenLandHuidig === $ledenLandOptie ? ' selected' : ''; ?>><?php echo htmlspecialchars($ledenLandOptie); ?></option>
                <?php endforeach; ?>
              </optgroup>
            </select>
          </div>
        </div>

        <div class="sectie-kop">Vereniging</div>
        <div class="rij-3">
          <div class="veld">
            <label for="lid-transponder">Transponder</label>
            <input type="text" id="lid-transponder" name="transponder" maxlength="60" value="<?php echo htmlspecialchars($ledenBewerkLid['transponder']); ?>">
          </div>
          <div class="veld">
            <label for="lid-auto">Auto</label>
            <input type="text" id="lid-auto" name="auto" maxlength="120" value="<?php echo htmlspecialchars($ledenBewerkLid['auto']); ?>">
          </div>
          <div class="veld">
            <label for="lid-taken">Taken</label>
            <input type="text" id="lid-taken" name="taken" maxlength="300" value="<?php echo htmlspecialchars($ledenBewerkLid['taken']); ?>">
          </div>
        </div>

        <div class="rij-2">
          <div class="veld">
            <label<?php echo $magLedenAutorisatieWijzigen ? ' for="lid-bestuursfunctie"' : ''; ?>>Rol in het bestuur</label>
            <?php if ($magLedenAutorisatieWijzigen): ?>
              <select id="lid-bestuursfunctie" name="bestuursfunctie">
                <option value="">Geen bestuursfunctie</option>
                <?php foreach ($ledenFunctieLabels as $sleutel => $label): ?>
                  <option value="<?php echo htmlspecialchars($sleutel); ?>"<?php echo ($ledenBewerkLid['bestuursfunctie'] ?? '') === $sleutel ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
              </select>
              <p class="hint">Voorzitter, penningmeester en secretaris zijn ook bestuurslid. Deze rol bepaalt mede de toegang tot de bestuursdelen.</p>
            <?php else: ?>
              <?php $functieNu = $ledenFunctieLabels[$ledenBewerkLid['bestuursfunctie'] ?? ''] ?? 'Geen bestuursfunctie'; ?>
              <p style="margin:8px 0 0;"><strong><?php echo htmlspecialchars($functieNu); ?></strong></p>
              <p class="hint">Alleen de hoofdbeheerder of iemand met het recht Gebruikers kan de bestuursrol wijzigen.</p>
            <?php endif; ?>
          </div>
          <div class="veld">
            <label>Commissies</label>
            <?php if (count($ledenCommissieLijst) === 0): ?>
              <p class="hint">Er zijn nog geen commissies. Je maakt ze aan bij het tabblad Commissies, daarna kun je ze hier aanvinken.</p>
            <?php else: ?>
              <div class="leden-vinkgroep">
                <?php foreach ($ledenCommissieLijst as $cSleutel => $cNaam): ?>
                  <label class="leden-vink">
                    <input type="checkbox" name="commissies[]" value="<?php echo htmlspecialchars($cSleutel); ?>"<?php echo in_array($cSleutel, (array) ($ledenBewerkLid['commissies'] ?? []), true) ? ' checked' : ''; ?>>
                    <?php echo htmlspecialchars($cNaam); ?>
                  </label>
                <?php endforeach; ?>
              </div>
              <p class="hint">Een bestuurslid mag ook in een commissie zitten, en een commissielid hoeft geen bestuurslid te zijn.</p>
            <?php endif; ?>
          </div>
        </div>

        <div class="veld">
            <label<?php echo $magLedenAutorisatieWijzigen ? ' for="lid-beheer-account"' : ''; ?>>Gekoppeld inlogaccount</label>
            <?php $lidAccount = trim((string) ($ledenBewerkLid['beheer_account'] ?? '')); ?>
            <?php if ($magLedenAutorisatieWijzigen): ?>
              <?php
                $accountNamen = ['beheerder'];
                foreach ($gebruikersLijst as $g) {
                  if (isset($g['gebruikersnaam']) && $g['gebruikersnaam'] !== '') $accountNamen[] = $g['gebruikersnaam'];
                }
                $accountBekend = $lidAccount === '' || in_array($lidAccount, $accountNamen, true);
              ?>
              <select id="lid-beheer-account" name="beheer_account">
                <option value=""<?php echo $lidAccount === '' ? ' selected' : ''; ?>>Geen koppeling</option>
                <?php if (!$accountBekend): ?>
                  <option value="<?php echo htmlspecialchars($lidAccount); ?>" selected><?php echo htmlspecialchars($lidAccount); ?> (account bestaat niet meer)</option>
                <?php endif; ?>
                <?php foreach ($accountNamen as $accountNaam): ?>
                  <option value="<?php echo htmlspecialchars($accountNaam); ?>"<?php echo $lidAccount === $accountNaam ? ' selected' : ''; ?>><?php echo htmlspecialchars($accountNaam); ?></option>
                <?php endforeach; ?>
              </select>
              <p class="hint">Hiermee weet de ledenpagina welk lid er achter een inlognaam zit. Maak het account eerst aan bij Gebruikers in het beheer.</p>
            <?php else: ?>
              <p style="margin:8px 0 0;"><strong><?php echo htmlspecialchars($lidAccount !== '' ? $lidAccount : 'Geen koppeling'); ?></strong></p>
              <p class="hint">Alleen de hoofdbeheerder of iemand met het recht Gebruikers kan een inlogaccount koppelen of ontkoppelen.</p>
            <?php endif; ?>
          </div>

        <div class="veld">
          <label>In de WhatsAppgroep</label>
          <label class="leden-vink"><input type="checkbox" name="whatsapp" value="1" <?php echo !empty($ledenBewerkLid['whatsapp']) ? 'checked' : ''; ?>> Toegevoegd</label>
        </div>

        <div class="veld">
          <label for="lid-opmerking">Opmerking</label>
          <textarea id="lid-opmerking" name="opmerking" maxlength="1000" style="min-height:70px;"><?php echo htmlspecialchars($ledenBewerkLid['opmerking']); ?></textarea>
        </div>

        <h2 class="leden-kop">Contributie per jaar</h2>
        <p class="hint">Een lege regel met een jaartal erin voegt een nieuw jaar toe. Het voorstel komt uit de rekentabel; pas het bedrag aan als iemand halverwege het jaar instapt.</p>

        <?php foreach ($ledenBewerkContributie as $ci => $regel): ?>
          <div class="leden-contributie">
            <div class="leden-contributie-jaar-kop"><?php echo $regel['jaar'] !== '' ? 'Jaar ' . htmlspecialchars((string) $regel['jaar']) : 'Nieuw jaar toevoegen'; ?></div>
            <div class="veld">
              <label for="lid-c-jaar-<?php echo $ci; ?>">Jaar</label>
              <input type="number" id="lid-c-jaar-<?php echo $ci; ?>" name="contributie[<?php echo $ci; ?>][jaar]" min="2000" max="2099" step="1" value="<?php echo htmlspecialchars((string) $regel['jaar']); ?>">
            </div>
            <div class="veld">
              <label for="lid-c-status-<?php echo $ci; ?>">Status</label>
              <select id="lid-c-status-<?php echo $ci; ?>" name="contributie[<?php echo $ci; ?>][status]">
                <?php foreach ($ledenContributieLabels as $sleutel => $label): ?>
                  <option value="<?php echo htmlspecialchars($sleutel); ?>" <?php echo ($regel['status'] ?? 'open') === $sleutel ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="veld">
              <label for="lid-c-bedrag-<?php echo $ci; ?>">Bedrag</label>
              <?php
                $voorstel = $regel['jaar'] === '' ? null : ledenBedragVoorstel($ledenBewerkLid, (int) $regel['jaar'], $rekentabelData);
                // De hele-jaarbedragen in de snelkeuze horen bij het jaar van
                // deze regel, niet altijd bij het contributiejaar: anders
                // biedt een regel voor volgend jaar het bedrag van dit jaar aan.
                $rijJaar = $regel['jaar'] === '' ? null : (int) $regel['jaar'];
                $rijSenior = rekentabelJaarbedrag($rekentabelData, false, $rijJaar);
                $rijJeugd  = rekentabelJaarbedrag($rekentabelData, true,  $rijJaar);
              ?>
              <select class="leden-bedrag-snelkeuze" data-doel="lid-c-bedrag-<?php echo $ci; ?>" aria-label="Snelkeuze bedrag" style="margin-bottom:6px;">
                <option value="">Snelkeuze&hellip;</option>
                <option value="<?php echo (int) round($rijSenior); ?>"><?php echo euro($rijSenior); ?> (senior, heel jaar)</option>
                <option value="<?php echo (int) round($rijJeugd); ?>"><?php echo euro($rijJeugd); ?> (jeugd, heel jaar)</option>
                <optgroup label="Pro-rata, vanaf instapmaand">
                  <?php for ($m = 1; $m <= 11; $m++): ?>
                    <option value="<?php echo (int) $tabelSenior[$m]; ?>"><?php echo euro($tabelSenior[$m]); ?> (senior, vanaf <?php echo $maandNamen[$m]; ?>)</option>
                    <option value="<?php echo (int) $tabelJeugd[$m]; ?>"><?php echo euro($tabelJeugd[$m]); ?> (jeugd, vanaf <?php echo $maandNamen[$m]; ?>)</option>
                  <?php endfor; ?>
                </optgroup>
              </select>
              <input type="number" id="lid-c-bedrag-<?php echo $ci; ?>" name="contributie[<?php echo $ci; ?>][bedrag]" min="0" step="0.01" value="<?php echo $regel['bedrag'] === null ? '' : htmlspecialchars((string) $regel['bedrag']); ?>" placeholder="<?php echo $voorstel === null ? '' : htmlspecialchars(number_format($voorstel, 2, '.', '')); ?>">
              <p class="hint">Snelkeuze vult het bedrag hieronder in; bij uitzondering typ je er zelf een bedrag in.</p>
            </div>
            <div class="veld">
              <label for="lid-c-inschrijf-<?php echo $ci; ?>">Inschrijfgeld</label>
              <input type="number" id="lid-c-inschrijf-<?php echo $ci; ?>" name="contributie[<?php echo $ci; ?>][inschrijfgeld]" min="0" step="0.01" value="<?php echo $regel['inschrijfgeld'] === null ? '' : htmlspecialchars((string) $regel['inschrijfgeld']); ?>" placeholder="<?php echo htmlspecialchars(number_format((float) $rekentabelData['inschrijfkosten'], 2, '.', '')); ?>">
            </div>
            <div class="veld">
              <label for="lid-c-betaald-<?php echo $ci; ?>">Betaald op</label>
              <div class="datum-invoer-rij">
                <input type="text" inputmode="numeric" id="lid-c-betaald-<?php echo $ci; ?>" name="contributie[<?php echo $ci; ?>][betaald_op]" maxlength="10" placeholder="dd-mm-jjjj" value="<?php echo htmlspecialchars(datumWeergave((string) ($regel['betaald_op'] ?? ''))); ?>">
                <button type="button" class="datum-picker-wrap" title="Datum kiezen uit kalender" aria-label="Datum kiezen"><span class="datum-picker-icoon" aria-hidden="true">📅</span><input type="date" class="datum-picker" data-doel="lid-c-betaald-<?php echo $ci; ?>" tabindex="-1" aria-hidden="true"></button>
              </div>
            </div>
            <div class="veld veld-breed">
              <label for="lid-c-opm-<?php echo $ci; ?>">Opmerking</label>
              <input type="text" id="lid-c-opm-<?php echo $ci; ?>" name="contributie[<?php echo $ci; ?>][opmerking]" maxlength="300" value="<?php echo htmlspecialchars((string) ($regel['opmerking'] ?? '')); ?>">
            </div>
            <?php if ($regel['jaar'] !== ''): ?>
              <label class="leden-vink leden-vink-weg"><input type="checkbox" name="contributie[<?php echo $ci; ?>][verwijderen]" value="1"> Dit jaar verwijderen</label>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <button type="submit">Lid opslaan</button>
      </form>

      <?php if (!$ledenBewerkNieuw): ?>
        <form method="post" action="leden.php#leden" onsubmit="return confirm('Dit lid definitief verwijderen? De vorige versie blijft in de back-ups staan.');" style="margin-top:14px;">
          <input type="hidden" name="formulier" value="leden_verwijderen">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
          <input type="hidden" name="lid_id" value="<?php echo htmlspecialchars($ledenBewerkLid['id']); ?>">
          <button type="submit" class="knop-klein">Lid verwijderen</button>
        </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Tabel en import/export alleen tonen als er geen lid openstaat om te
         bewerken: anders zie je onder het formulier ook nog de hele
         ledenlijst, wat niets toevoegt terwijl je met een lid bezig bent. -->
    <?php if ($ledenBewerkLid === null): ?>
    <div class="kaart">
      <div class="kaart-header">
        <div>
          <h1>Leden</h1>
          <p class="sub"><?php echo count($ledenLijst); ?> leden in het bestand. Leeftijd en jeugd of senior worden berekend uit de geboortedatum, dus die kloppen vanzelf altijd.</p>
        </div>
        <a class="knop-toevoegen" href="leden.php?lid=nieuw#leden">Nieuw lid</a>
      </div>

      <?php if (isset($melding['leden']) && $ledenBewerkLid === null): ?>
        <div class="melding <?php echo $meldingType['leden']; ?>"><?php echo htmlspecialchars($melding['leden']); ?></div>
      <?php endif; ?>

      <?php if (count($ledenDubbeleNummers) > 0): ?>
        <div class="melding fout">Let op, dubbel lidnummer: <?php echo htmlspecialchars(implode(', ', $ledenDubbeleNummerRegels)); ?>. Open een van de twee leden en pas het nummer aan.</div>
      <?php endif; ?>

      <div class="leden-telling">
        <?php foreach ($ledenStatusLabels as $sleutel => $label): ?>
          <button type="button" class="leden-badge leden-badge-klikbaar leden-badge-status lb-<?php echo htmlspecialchars($sleutel); ?>" data-status="<?php echo htmlspecialchars($sleutel); ?>" aria-pressed="false" title="Klik om alleen '<?php echo htmlspecialchars($label); ?>' te tonen"><?php echo htmlspecialchars($label); ?>: <?php echo $ledenTellingen[$sleutel]; ?></button>
        <?php endforeach; ?>
      </div>

      <div class="leden-telling">
        <?php foreach ($ledenContributieLabels as $sleutel => $label): ?>
          <button type="button" class="leden-badge leden-badge-klikbaar leden-badge-contributie cb-<?php echo htmlspecialchars($sleutel); ?>" data-contributie="<?php echo htmlspecialchars($sleutel); ?>" aria-pressed="false" title="Klik om alleen '<?php echo htmlspecialchars($label); ?> <?php echo $ledenJaar; ?>' te tonen"><?php echo htmlspecialchars($label); ?> <?php echo $ledenJaar; ?>: <?php echo $ledenContributieTellingen[$sleutel]; ?></button>
        <?php endforeach; ?>
        <button type="button" class="leden-badge leden-badge-klikbaar leden-badge-contributie" data-contributie="leeg" aria-pressed="false" title="Klik om alleen leden zonder contributieregel voor <?php echo $ledenJaar; ?> te tonen">Niet ingevuld <?php echo $ledenJaar; ?>: <?php echo $ledenContributieTellingen['leeg']; ?></button>
      </div>

      <?php if (count($ledenLijst) === 0): ?>
        <p class="hint">Nog geen leden. Voeg er een toe met de knop hierboven, of lees het Excel-bestand in via de import onderaan deze pagina.</p>
      <?php else: ?>
        <div class="leden-filters">
          <input type="search" id="leden-zoek" placeholder="Zoek op naam, mailadres, telefoon, lidnummer, woonplaats, jeugdlid of senior" aria-label="Zoeken in leden">
          <select id="leden-filter-status" aria-label="Filteren op status">
            <option value="">Alle statussen</option>
            <?php foreach ($ledenStatusLabels as $sleutel => $label): ?>
              <option value="<?php echo htmlspecialchars($sleutel); ?>"><?php echo htmlspecialchars($label); ?></option>
            <?php endforeach; ?>
          </select>
          <select id="leden-filter-contributie" aria-label="Filteren op contributie <?php echo $ledenJaar; ?>">
            <option value="">Alle contributiestatussen</option>
            <?php foreach ($ledenContributieLabels as $sleutel => $label): ?>
              <option value="<?php echo htmlspecialchars($sleutel); ?>"><?php echo htmlspecialchars($label); ?> <?php echo $ledenJaar; ?></option>
            <?php endforeach; ?>
            <option value="leeg">Niet ingevuld <?php echo $ledenJaar; ?></option>
          </select>
          <select id="leden-filter-rol" aria-label="Filteren op rol">
            <option value="">Alle rollen</option>
            <optgroup label="Bestuur">
              <option value="bestuur">Alle bestuursleden</option>
              <?php foreach ($ledenFunctieLabels as $fSleutel => $fLabel): ?>
                <option value="functie:<?php echo htmlspecialchars($fSleutel); ?>"><?php echo htmlspecialchars($fLabel); ?></option>
              <?php endforeach; ?>
            </optgroup>
            <?php if (count($ledenCommissieLijst) > 0): ?>
              <optgroup label="Commissies">
                <?php foreach ($ledenCommissieLijst as $cSleutel => $cNaam): ?>
                  <option value="commissie:<?php echo htmlspecialchars($cSleutel); ?>"><?php echo htmlspecialchars($cNaam); ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endif; ?>
            <option value="geen">Zonder rol</option>
          </select>
          <div class="leden-sorteer-mobiel">
            <select id="leden-sorteer" aria-label="Sorteren op">
              <option value="">Standaardvolgorde</option>
              <option value="nr">Sorteer op nummer</option>
              <option value="naam">Sorteer op naam</option>
              <option value="leeftijd">Sorteer op leeftijd</option>
              <option value="status">Sorteer op status</option>
              <option value="contributie">Sorteer op contributie</option>
              <option value="contact">Sorteer op contact</option>
            </select>
            <button type="button" id="leden-sorteer-richting" title="Volgorde omdraaien" aria-label="Volgorde omdraaien">&uarr;</button>
          </div>
        </div>

        <div class="leden-select-rij">
          <label><input type="checkbox" id="leden-select-alles"> Alles selecteren (wat nu zichtbaar is)</label>
        </div>

        <form method="post" action="leden.php#leden" class="leden-bulk-balk" id="leden-bulk-balk" onsubmit="return confirm('Status van de geselecteerde leden aanpassen?');">
          <input type="hidden" name="formulier" value="leden_bulk_status">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
          <input type="hidden" name="lid_ids" id="leden-bulk-ids">
          <span id="leden-bulk-telling">0 leden geselecteerd</span>
          <select name="status" id="leden-bulk-status" aria-label="Nieuwe status" required>
            <option value="" disabled selected>Zet status op...</option>
            <?php foreach ($ledenStatusLabels as $sleutel => $label): ?>
              <option value="<?php echo htmlspecialchars($sleutel); ?>"><?php echo htmlspecialchars($label); ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="knop-klein">Status toepassen</button>
        </form>

        <div class="leden-tabel-wrap">
          <table class="leden-tabel" id="leden-tabel">
            <thead>
              <tr>
                <th class="lc-select-th"></th>
                <th data-kolom="nr" role="button" tabindex="0">Nr</th>
                <th data-kolom="naam" role="button" tabindex="0">Naam</th>
                <th data-kolom="leeftijd" role="button" tabindex="0">Leeftijd</th>
                <th data-kolom="status" role="button" tabindex="0">Status</th>
                <th data-kolom="contributie" role="button" tabindex="0">Contributie <?php echo $ledenJaar; ?></th>
                <th data-kolom="contact" role="button" tabindex="0">Contact</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php $ledenStatusVolgorde = array_flip(array_keys($ledenStatusLabels)); ?>
              <?php foreach ($ledenLijst as $l): ?>
                <?php
                  $leeftijd = ledenLeeftijd($l['geboortedatum'] ?? '');
                  $jeugd = ledenIsJeugd($l, $ledenJeugdTot, $ledenJaar);
                  $c = $l['contributie'][(string) $ledenJaar] ?? null;
                  // Jeugd/senior staat alleen berekend in de Leeftijd-kolom, niet als los
                  // veld in de data, dus die moet je er hier los bij zoeken zodat "jeugdlid"
                  // of "senior" typen ook echt iets oplevert.
                  $zoekJeugd = $jeugd === true ? 'jeugdlid jeugd' : ($jeugd === false ? 'senior seniorlid' : '');
                  $rolTekst = ledenRolTekst($l, $ledenCommissieLijst);
                  // "bestuur" en "bestuurslid" moeten ook een voorzitter of
                  // penningmeester opleveren, want die zijn dat ook.
                  $zoekRol = $rolTekst . (ledenIsBestuurslid($l) ? ' bestuur bestuurslid' : '');
                  $zoek = strtolower(ledenVolledigeNaam($l) . ' ' . ($l['email'] ?? '') . ' ' . ($l['telefoon'] ?? '') . ' ' . ($l['nummer'] ?? '') . ' ' . ($l['gemeente'] ?? '') . ' ' . $zoekJeugd . ' ' . $zoekRol);
                  $sorteerStatus = $ledenStatusVolgorde[$l['status'] ?? ''] ?? 999;
                  $sorteerContributie = ($c !== null && $c['bedrag'] !== null) ? (float) $c['bedrag'] : -1;
                  $sorteerContact = strtolower(trim((($l['email'] ?? '') !== '') ? $l['email'] : ($l['telefoon'] ?? '')));
                  // Losse sleutels voor het rolfilter. "bestuur" zit er bij
                  // elke functie bij, zodat "alle bestuursleden" ook de
                  // voorzitter en de penningmeester oplevert.
                  $rolSleutels = [];
                  if (ledenIsBestuurslid($l)) {
                    $rolSleutels[] = 'bestuur';
                    $rolSleutels[] = 'functie:' . $l['bestuursfunctie'];
                  }
                  foreach ((isset($l['commissies']) && is_array($l['commissies'])) ? $l['commissies'] : [] as $cs) {
                    if (isset($ledenCommissieLijst[$cs])) $rolSleutels[] = 'commissie:' . $cs;
                  }
                  if (count($rolSleutels) === 0) $rolSleutels[] = 'geen';
                ?>
                <tr data-status="<?php echo htmlspecialchars($l['status'] ?? ''); ?>" data-contributie="<?php echo htmlspecialchars($c === null ? 'leeg' : ($c['status'] ?? 'open')); ?>" data-zoek="<?php echo htmlspecialchars($zoek); ?>"
                    data-rol="<?php echo htmlspecialchars(' ' . implode(' ', $rolSleutels) . ' '); ?>"
                    data-href="leden.php?lid=<?php echo urlencode($l['id']); ?>#leden"
                    data-sort-nr="<?php echo (int) ($l['nummer'] ?? 0); ?>"
                    data-sort-naam="<?php echo htmlspecialchars(ledenSorteernaam($l)); ?>"
                    data-sort-leeftijd="<?php echo $leeftijd === null ? -1 : (int) $leeftijd; ?>"
                    data-sort-status="<?php echo (int) $sorteerStatus; ?>"
                    data-sort-contributie="<?php echo htmlspecialchars((string) $sorteerContributie); ?>"
                    data-sort-contact="<?php echo htmlspecialchars($sorteerContact); ?>">
                  <td class="lc-select"><input type="checkbox" class="leden-select-vink" value="<?php echo htmlspecialchars($l['id']); ?>" aria-label="Selecteer <?php echo htmlspecialchars(ledenVolledigeNaam($l)); ?>"></td>
                  <td data-label="Nr"><span class="lc"><?php echo htmlspecialchars((string) ($l['nummer'] ?? '')); ?></span></td>
                  <td class="lc-kop">
                    <span class="lc"><strong><?php echo htmlspecialchars(ledenVolledigeNaam($l)); ?></strong>
                    <?php if (($l['bron'] ?? '') === 'aanmeldformulier'): ?><span class="leden-bron">via formulier</span><?php endif; ?>
                    <?php if ($rolTekst !== ''): ?><span class="leden-rol"><?php echo htmlspecialchars($rolTekst); ?></span><?php endif; ?></span>
                  </td>
                  <td data-label="Leeftijd"><span class="lc"><?php echo $leeftijd === null ? '&mdash;' : ($leeftijd . ($jeugd ? ' (jeugd)' : '')); ?></span></td>
                  <td data-label="Status"><span class="lc"><span class="leden-badge lb-<?php echo htmlspecialchars($l['status'] ?? ''); ?>"><?php echo htmlspecialchars($ledenStatusLabels[$l['status'] ?? ''] ?? '?'); ?></span></span></td>
                  <td data-label="Contributie">
                    <span class="lc">
                    <?php if ($c === null): ?>
                      <span class="leden-leeg">niet ingevuld</span>
                    <?php else: ?>
                      <span class="leden-badge cb-<?php echo htmlspecialchars($c['status']); ?>"><?php echo htmlspecialchars($ledenContributieLabels[$c['status']] ?? $c['status']); ?></span>
                      <?php if ($c['bedrag'] !== null): ?>
                        <span class="leden-bedrag">&euro;<?php echo htmlspecialchars(number_format((float) $c['bedrag'], 2, ',', '.')); ?></span>
                      <?php endif; ?>
                    <?php endif; ?>
                    </span>
                  </td>
                  <td class="leden-contact" data-label="Contact">
                    <span class="lc">
                    <?php if (($l['email'] ?? '') !== ''): ?><a href="mailto:<?php echo htmlspecialchars($l['email']); ?>"><?php echo htmlspecialchars($l['email']); ?></a><br><?php endif; ?>
                    <?php echo htmlspecialchars($l['telefoon'] ?? ''); ?>
                    </span>
                  </td>
                  <td class="lc-actie"><a class="knop-klein" href="leden.php?lid=<?php echo urlencode($l['id']); ?>#leden">Bewerken</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <p class="hint" id="leden-geen-resultaat" hidden>Geen leden gevonden met deze zoekopdracht.</p>
        </div>
      <?php endif; ?>
    </div>

    <div class="kaart">
      <h1>Importeren en exporteren</h1>
      <p class="sub">Voor het overzetten van het Excel-bestand, en om af en toe een kopie voor jezelf te maken.</p>

      <?php if ($ledenImport !== null): ?>
        <h2 class="leden-kop">Controleren voor het opslaan</h2>
        <p class="hint">Er is nog niets gewijzigd. Regels die overeenkomen met een bestaand lid (zelfde mailadres, of zelfde naam en geboortedatum) worden bijgewerkt in plaats van dubbel toegevoegd.</p>
        <?php if (!$magLedenAutorisatieWijzigen): ?>
          <p class="hint">Bestuursfuncties uit het CSV-bestand worden met dit account niet gewijzigd; daarvoor is het expliciete recht Gebruikers nodig.</p>
        <?php endif; ?>

        <?php
          $importNieuw = 0; $importBij = 0;
          $importControle = ledenLees();
          // Per regel één keer bepalen of het een nieuw lid wordt of een
          // bestaand lid bijwerkt, en waarop dat herkend is. De uitkomst
          // wordt hieronder in de tabel hergebruikt.
          //
          // Een regel die als nieuw geldt wordt meteen aan de werklijst
          // toegevoegd, precies zoals het opslaan straks ook doet. Zonder
          // dat telde een bestand met twee regels voor dezelfde nieuwe
          // persoon hier als "2 nieuw" terwijl er daadwerkelijk maar één
          // lid bijkwam.
          $importTreffers = [];
          foreach ($ledenImport['rijen'] as $ri => $rij) {
            $treffer = ledenZoekBestaandeMet($importControle, $rij);
            $importTreffers[$ri] = $treffer;
            if ($treffer['index'] === null) {
              $importNieuw++;
              $importControle['leden'][] = $rij;
            } else {
              $importBij++;
            }
          }
          $importOpNaam = 0;
          foreach ($importTreffers as $treffer) {
            if ($treffer['reden'] === 'naam') $importOpNaam++;
          }
          $onbekendeKolommen = [];
          $berekendeKolommen = [];
          foreach ($ledenImport['kolommen'] as $k) {
            if ($k['veld'] === null) $onbekendeKolommen[] = $k['kop'];
            elseif ($k['veld'] === '_berekend') $berekendeKolommen[] = $k['kop'];
          }
        ?>
        <div class="leden-telling">
          <span class="leden-badge lb-actief"><?php echo $importNieuw; ?> nieuw</span>
          <span class="leden-badge lb-verificatie"><?php echo $importBij; ?> bijgewerkt</span>
        </div>
        <?php if (count($berekendeKolommen) > 0): ?>
          <p class="hint">Niet overgenomen omdat de beheerpagina ze zelf uitrekent: <?php echo htmlspecialchars(implode(', ', $berekendeKolommen)); ?>.</p>
        <?php endif; ?>
        <?php if (count($onbekendeKolommen) > 0): ?>
          <p class="hint">Deze kolommen zijn niet herkend en worden overgeslagen: <?php echo htmlspecialchars(implode(', ', $onbekendeKolommen)); ?>.</p>
        <?php endif; ?>

        <?php if ($importOpNaam > 0): ?>
          <p class="hint">Let op: <?php echo $importOpNaam; ?> <?php echo $importOpNaam === 1 ? 'regel is' : 'regels zijn'; ?> alleen op de naam herkend, omdat er geen mailadres, lidnummer of geboortedatum was om op te vergelijken. Loop die regels na in de kolom "Wordt" voordat je opslaat.</p>
        <?php endif; ?>

        <div class="leden-tabel-wrap">
          <table class="leden-tabel">
            <thead><tr><th>Naam</th><th>Geboortedatum</th><th>Mailadres</th><th>Woonplaats</th><th>Wordt</th></tr></thead>
            <tbody>
              <?php foreach (array_slice($ledenImport['rijen'], 0, 25, true) as $ri => $rij): ?>
                <?php $treffer = $importTreffers[$ri]; ?>
                <tr>
                  <td class="lc-kop"><span class="lc"><strong><?php echo htmlspecialchars(ledenVolledigeNaam($rij)); ?></strong></span></td>
                  <td data-label="Geboren"><span class="lc"><?php echo htmlspecialchars(datumWeergave(ledenParseDatum($rij['geboortedatum'] ?? ''))); ?></span></td>
                  <td data-label="Mail"><span class="lc"><?php echo htmlspecialchars($rij['email'] ?? ''); ?></span></td>
                  <td data-label="Woonplaats"><span class="lc"><?php echo htmlspecialchars($rij['gemeente'] ?? ''); ?></span></td>
                  <td data-label="Wordt">
                    <span class="lc">
                      <?php if ($treffer['index'] === null): ?>
                        toegevoegd
                      <?php else: ?>
                        bijgewerkt
                        <span class="leden-bron<?php echo $treffer['reden'] === 'naam' ? ' leden-let-op' : ''; ?>">herkend op <?php echo htmlspecialchars($treffer['reden']); ?></span>
                      <?php endif; ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if (count($ledenImport['rijen']) > 25): ?>
          <p class="hint">Eerste 25 van <?php echo count($ledenImport['rijen']); ?> regels getoond.</p>
        <?php endif; ?>

        <div class="leden-import-knoppen">
          <form method="post" action="leden.php#leden">
            <input type="hidden" name="formulier" value="leden_import_bevestigen">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <button type="submit">Import definitief opslaan</button>
          </form>
          <form method="post" action="leden.php#leden">
            <input type="hidden" name="formulier" value="leden_import_annuleren">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <button type="submit" class="knop-klein">Annuleren</button>
          </form>
        </div>
      <?php else: ?>
        <form method="post" action="leden.php#leden" enctype="multipart/form-data">
          <input type="hidden" name="formulier" value="leden_import_lezen">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
          <div class="veld">
            <label for="leden-csv">CSV-bestand</label>
            <input type="file" id="leden-csv" name="csv" accept=".csv,text/csv">
            <p class="hint">Sla het Excel-bestand op als CSV. Puntkomma of komma als scheidingsteken maakt niet uit, en een bestand dat Excel in de Windows-codering heeft weggeschreven wordt automatisch omgezet. De eerste regel moet de kolomnamen bevatten. Na het inlezen krijg je eerst een overzicht te zien; er wordt pas opgeslagen als je dat bevestigt.</p>
          </div>
          <button type="submit">Bestand inlezen</button>
        </form>
      <?php endif; ?>

      <h2 class="leden-kop">Exporteren</h2>
      <p class="hint">Een CSV met alle leden en alle contributiejaren, in dezelfde kolommen als het Excel-bestand. Let op waar je die kopie laat: er staan persoonsgegevens in.</p>
      <form method="post" action="leden.php#leden">
        <input type="hidden" name="formulier" value="leden_export">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <button type="submit" class="knop-klein">Download CSV</button>
      </form>
    </div>
    <?php endif; ?>
    </div>

    <?php endif; ?>

    <?php if (in_array('commissies', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-commissies">
    <!-- ===== COMMISSIES ===== -->
    <div class="kaart">
      <div class="kaart-header">
        <div>
          <h1>Commissies</h1>
          <p class="sub">Per commissie een naam, het verantwoordelijke bestuurslid en het commissiehoofd. Een lid vinkt zichzelf bij het tabblad Leden aan onder Vereniging; hernoemen mag altijd, de koppeling met de leden blijft dan gewoon staan.</p>
        </div>
      </div>

      <?php if (isset($melding['commissies'])): ?>
        <div class="melding <?php echo $meldingType['commissies']; ?>"><?php echo htmlspecialchars($melding['commissies']); ?></div>
      <?php endif; ?>

      <form method="post" action="leden.php#commissies" onsubmit="return this.querySelectorAll('input[type=checkbox]:checked').length === 0 || confirm('Aangevinkte commissies worden verwijderd en ook bij alle leden weggehaald. Doorgaan?');">
        <input type="hidden" name="formulier" value="commissies_opslaan">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">

        <?php if (count($ledenCommissieVolledigLijst) === 0): ?>
          <p class="hint">Nog geen commissies. Vul hieronder een naam in, bijvoorbeeld Kantine, Baanonderhoud of Activiteiten.</p>
        <?php else: ?>
          <?php foreach ($ledenCommissieVolledigLijst as $cSleutel => $cRegel): ?>
            <div class="leden-commissie-regel">
              <div class="veld">
                <label for="commissie-<?php echo htmlspecialchars($cSleutel); ?>-naam">Naam</label>
                <input type="text" id="commissie-<?php echo htmlspecialchars($cSleutel); ?>-naam" name="commissie[<?php echo htmlspecialchars($cSleutel); ?>][naam]" maxlength="60" value="<?php echo htmlspecialchars($cRegel['naam']); ?>">
              </div>
              <div class="veld">
                <label for="commissie-<?php echo htmlspecialchars($cSleutel); ?>-bestuurslid">Verantwoordelijk bestuurslid</label>
                <select id="commissie-<?php echo htmlspecialchars($cSleutel); ?>-bestuurslid" name="commissie[<?php echo htmlspecialchars($cSleutel); ?>][bestuurslid_id]">
                  <option value="">Geen</option>
                  <?php foreach ($bestuursLeden as $bl): ?>
                    <option value="<?php echo htmlspecialchars($bl['id']); ?>"<?php echo $cRegel['bestuurslid_id'] === $bl['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars(ledenVolledigeNaam($bl)); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="veld">
                <label for="commissie-<?php echo htmlspecialchars($cSleutel); ?>-hoofd">Commissiehoofd</label>
                <select id="commissie-<?php echo htmlspecialchars($cSleutel); ?>-hoofd" name="commissie[<?php echo htmlspecialchars($cSleutel); ?>][hoofd_lid_id]">
                  <option value="">Geen</option>
                  <?php foreach ($ledenLijst as $l): ?>
                    <option value="<?php echo htmlspecialchars($l['id']); ?>"<?php echo $cRegel['hoofd_lid_id'] === $l['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars(ledenVolledigeNaam($l)); ?></option>
                  <?php endforeach; ?>
                </select>
                <p class="hint">Hoeft geen bestuurslid te zijn.</p>
              </div>
              <span class="leden-commissie-aantal"><?php echo (int) ($ledenCommissieTellingen[$cSleutel] ?? 0); ?> <?php echo ((int) ($ledenCommissieTellingen[$cSleutel] ?? 0)) === 1 ? 'lid' : 'leden'; ?></span>
              <label class="leden-vink leden-vink-weg"><input type="checkbox" name="commissie[<?php echo htmlspecialchars($cSleutel); ?>][verwijderen]" value="1"> Verwijderen</label>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <div class="rij-2" style="margin-top:16px;">
          <div class="veld">
            <label for="commissie-nieuw-1">Nieuwe commissie</label>
            <input type="text" id="commissie-nieuw-1" name="commissie_nieuw[]" maxlength="60" placeholder="Bijvoorbeeld Kantine">
          </div>
          <div class="veld">
            <label for="commissie-nieuw-2">Nog een nieuwe commissie</label>
            <input type="text" id="commissie-nieuw-2" name="commissie_nieuw[]" maxlength="60" placeholder="Bijvoorbeeld Baanonderhoud">
          </div>
        </div>
        <p class="hint">Het bestuurslid en het commissiehoofd stel je in nadat de commissie is aangemaakt: sla eerst de naam op, dan verschijnen de keuzelijsten bij die regel.</p>

        <button type="submit">Commissies opslaan</button>
      </form>
    </div>
    </div>
    <?php endif; ?>

    <?php if (in_array('bestuursvergadering', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-bestuursvergadering">
    <!-- ===== BESTUURSVERGADERING ===== -->
    <?php if ($vergaderingBewerk !== null): ?>
      <div class="kaart">
        <div class="kaart-header">
          <div>
            <h1><?php echo $vergaderingNieuw ? 'Nieuwe vergadering' : htmlspecialchars(vergaderingWeergavenaam($vergaderingBewerk)); ?></h1>
            <p class="sub">
              <?php if ($vergaderingNieuw): ?>
                Vul in elk geval een datum in. De rest kan later, bijvoorbeeld de notulen na afloop.
              <?php else: ?>
                Vergadering <?php echo (int) $vergaderingBewerk['nummer']; ?><?php if (($vergaderingBewerk['aangemaakt_door'] ?? '') !== ''): ?>, aangemaakt door <?php echo htmlspecialchars($vergaderingBewerk['aangemaakt_door']); ?><?php endif; ?>.
              <?php endif; ?>
            </p>
          </div>
          <a class="knop-klein" href="leden.php#bestuursvergadering">Terug naar het overzicht</a>
        </div>

        <?php if (isset($melding['bestuursvergadering'])): ?>
          <div class="melding <?php echo $meldingType['bestuursvergadering']; ?>"><?php echo htmlspecialchars($melding['bestuursvergadering']); ?></div>
        <?php endif; ?>

        <form method="post" action="leden.php#bestuursvergadering">
          <input type="hidden" name="formulier" value="vergadering_opslaan">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
          <input type="hidden" name="vergadering_id" value="<?php echo $vergaderingNieuw ? '' : htmlspecialchars($vergaderingBewerk['id']); ?>">

          <div class="sectie-kop">Wanneer en waar</div>
          <div class="rij-3">
            <div class="veld">
              <label for="verg-datum">Datum</label>
              <div class="datum-invoer-rij">
                <input type="text" inputmode="numeric" id="verg-datum" name="datum" maxlength="10" placeholder="dd-mm-jjjj" value="<?php echo htmlspecialchars(datumWeergave($vergaderingBewerk['datum'])); ?>">
                <button type="button" class="datum-picker-wrap" title="Datum kiezen uit kalender" aria-label="Datum kiezen"><span class="datum-picker-icoon" aria-hidden="true">📅</span><input type="date" class="datum-picker" data-doel="verg-datum" tabindex="-1" aria-hidden="true"></button>
              </div>
            </div>
            <div class="veld">
              <label for="verg-tijd">Aanvang</label>
              <input type="text" id="verg-tijd" name="tijd" maxlength="5" placeholder="20:00" value="<?php echo htmlspecialchars($vergaderingBewerk['tijd']); ?>">
            </div>
            <div class="veld">
              <label for="verg-status">Status</label>
              <select id="verg-status" name="status">
                <?php foreach ($vergaderingStatusLabels as $sleutel => $label): ?>
                  <option value="<?php echo htmlspecialchars($sleutel); ?>"<?php echo $vergaderingBewerk['status'] === $sleutel ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="rij-2">
            <div class="veld">
              <label for="verg-titel">Titel</label>
              <input type="text" id="verg-titel" name="titel" maxlength="120" placeholder="Bijvoorbeeld Jaarvergadering" value="<?php echo htmlspecialchars($vergaderingBewerk['titel']); ?>">
              <p class="hint">Mag leeg blijven, dan staat de datum in het overzicht.</p>
            </div>
            <div class="veld">
              <label for="verg-locatie">Locatie</label>
              <input type="text" id="verg-locatie" name="locatie" maxlength="120" placeholder="Kantine" value="<?php echo htmlspecialchars($vergaderingBewerk['locatie']); ?>">
            </div>
          </div>

          <div class="sectie-kop">Aanwezigheid</div>
          <?php if (count($bestuursLeden) === 0): ?>
            <p class="hint">Er staat nog niemand met een bestuursfunctie in de ledenadministratie. Zet die rol bij het tabblad Leden onder Vereniging, dan verschijnt hier vanzelf een presentielijst.</p>
          <?php else: ?>
            <p class="hint">De lijst volgt de bestuursfuncties uit de ledenadministratie. Niets aanvinken betekent gewoon "nog niet ingevuld".</p>
            <?php foreach ($bestuursLeden as $bl): ?>
              <?php $keuzeNu = $vergaderingBewerk['aanwezigheid'][$bl['id']] ?? ''; ?>
              <div class="verg-aanwezig-regel">
                <span class="verg-aanwezig-naam">
                  <strong><?php echo htmlspecialchars(ledenVolledigeNaam($bl)); ?></strong>
                  <span class="leden-rol"><?php echo htmlspecialchars($ledenFunctieLabels[$bl['bestuursfunctie']] ?? ''); ?></span>
                </span>
                <span class="verg-aanwezig-keuze">
                  <label class="leden-vink"><input type="radio" name="aanwezigheid[<?php echo htmlspecialchars($bl['id']); ?>]" value=""<?php echo $keuzeNu === '' ? ' checked' : ''; ?>> Onbekend</label>
                  <?php foreach ($vergaderingAanwezigheidLabels as $aSleutel => $aLabel): ?>
                    <label class="leden-vink"><input type="radio" name="aanwezigheid[<?php echo htmlspecialchars($bl['id']); ?>]" value="<?php echo htmlspecialchars($aSleutel); ?>"<?php echo $keuzeNu === $aSleutel ? ' checked' : ''; ?>> <?php echo htmlspecialchars($aLabel); ?></label>
                  <?php endforeach; ?>
                </span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

          <div class="sectie-kop">Agendapunten</div>
          <p class="hint">Het lege blok onderaan voegt een punt toe. Een punt zonder onderwerp wordt niet opgeslagen.</p>
          <?php foreach ($vergaderingAgendaBlokken as $ai => $punt): ?>
            <div class="verg-agendapunt">
              <div class="verg-agendapunt-kop"><?php echo trim((string) $punt['onderwerp']) === '' ? 'Nieuw agendapunt' : 'Punt ' . ($ai + 1); ?></div>
              <div class="rij-2">
                <div class="veld">
                  <label for="verg-a-onderwerp-<?php echo $ai; ?>">Onderwerp</label>
                  <input type="text" id="verg-a-onderwerp-<?php echo $ai; ?>" name="agenda[<?php echo $ai; ?>][onderwerp]" maxlength="160" value="<?php echo htmlspecialchars((string) $punt['onderwerp']); ?>">
                </div>
                <div class="veld">
                  <label for="verg-a-indiener-<?php echo $ai; ?>">Ingebracht door</label>
                  <input type="text" id="verg-a-indiener-<?php echo $ai; ?>" name="agenda[<?php echo $ai; ?>][indiener]" maxlength="80" value="<?php echo htmlspecialchars((string) $punt['indiener']); ?>">
                </div>
              </div>
              <div class="veld">
                <label for="verg-a-toelichting-<?php echo $ai; ?>">Toelichting</label>
                <textarea id="verg-a-toelichting-<?php echo $ai; ?>" name="agenda[<?php echo $ai; ?>][toelichting]" maxlength="4000" style="min-height:60px;"><?php echo htmlspecialchars((string) $punt['toelichting']); ?></textarea>
              </div>
              <div class="veld">
                <label for="verg-a-besluit-<?php echo $ai; ?>">Besluit</label>
                <textarea id="verg-a-besluit-<?php echo $ai; ?>" name="agenda[<?php echo $ai; ?>][besluit]" maxlength="4000" style="min-height:60px;"><?php echo htmlspecialchars((string) $punt['besluit']); ?></textarea>
                <p class="hint">Vul je na afloop in. Zo staat bij elk punt wat er uiteindelijk is afgesproken.</p>
              </div>
              <?php if (trim((string) $punt['onderwerp']) !== ''): ?>
                <label class="leden-vink leden-vink-weg"><input type="checkbox" name="agenda[<?php echo $ai; ?>][verwijderen]" value="1"> Dit punt verwijderen</label>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>

          <div class="sectie-kop">Notulen</div>
          <div class="veld">
            <label for="verg-notulen">Verslag</label>
            <textarea id="verg-notulen" name="notulen" maxlength="20000" style="min-height:200px;"><?php echo htmlspecialchars($vergaderingBewerk['notulen']); ?></textarea>
            <p class="hint">Vrije tekst. Wat per agendapunt is besloten hoort bij dat punt zelf, hier komt de rest van het verslag.</p>
          </div>

          <button type="submit">Vergadering opslaan</button>
        </form>

        <?php if (!$vergaderingNieuw): ?>
          <form method="post" action="leden.php#bestuursvergadering" onsubmit="return confirm('Deze vergadering definitief verwijderen? De vorige versie blijft in de back-ups staan.');" style="margin-top:14px;">
            <input type="hidden" name="formulier" value="vergadering_verwijderen">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="vergadering_id" value="<?php echo htmlspecialchars($vergaderingBewerk['id']); ?>">
            <button type="submit" class="knop-klein">Vergadering verwijderen</button>
          </form>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="kaart">
        <div class="kaart-header">
          <div>
            <h1>Bestuursvergaderingen</h1>
            <p class="sub">Ieder bestuurslid kan hier een vergadering aanmaken en bijwerken, ongeacht de functie. Alleen leden met een bestuursfunctie in de ledenadministratie zien dit tabblad.</p>
          </div>
          <a class="knop-toevoegen" href="leden.php?vergadering=nieuw#bestuursvergadering">Nieuwe vergadering</a>
        </div>

        <?php if (isset($melding['bestuursvergadering'])): ?>
          <div class="melding <?php echo $meldingType['bestuursvergadering']; ?>"><?php echo htmlspecialchars($melding['bestuursvergadering']); ?></div>
        <?php endif; ?>

        <?php if (count($vergaderingenLijst) === 0): ?>
          <p class="hint">Nog geen vergaderingen. Maak er een aan met de knop hierboven.</p>
        <?php else: ?>
          <div class="leden-tabel-wrap">
            <table class="leden-tabel" id="vergaderingen-tabel">
              <thead>
                <tr>
                  <th>Datum</th>
                  <th>Vergadering</th>
                  <th>Status</th>
                  <th>Agenda</th>
                  <th>Aanwezig</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($vergaderingenLijst as $v): ?>
                  <?php $telling = vergaderingAanwezigheidTelling($v); ?>
                  <tr data-href="leden.php?vergadering=<?php echo urlencode($v['id']); ?>#bestuursvergadering">
                    <td data-label="Datum"><span class="lc"><?php echo htmlspecialchars(datumWeergave($v['datum'])); ?><?php if (($v['tijd'] ?? '') !== ''): ?> <?php echo htmlspecialchars($v['tijd']); ?><?php endif; ?></span></td>
                    <td class="lc-kop">
                      <span class="lc"><strong><?php echo htmlspecialchars(vergaderingWeergavenaam($v)); ?></strong>
                      <?php if (($v['locatie'] ?? '') !== ''): ?><span class="leden-bron"><?php echo htmlspecialchars($v['locatie']); ?></span><?php endif; ?></span>
                    </td>
                    <td data-label="Status"><span class="lc"><span class="leden-badge vb-<?php echo htmlspecialchars($v['status']); ?>"><?php echo htmlspecialchars($vergaderingStatusLabels[$v['status']] ?? $v['status']); ?></span></span></td>
                    <td data-label="Agenda"><span class="lc"><?php echo count($v['agenda']); ?> <?php echo count($v['agenda']) === 1 ? 'punt' : 'punten'; ?></span></td>
                    <td data-label="Aanwezig"><span class="lc"><?php echo $telling['aanwezig'] > 0 || $telling['afgemeld'] > 0 || $telling['afwezig'] > 0 ? $telling['aanwezig'] . ' van ' . count($bestuursLeden) : '<span class="leden-leeg">niet ingevuld</span>'; ?></span></td>
                    <td class="lc-actie"><a class="knop-klein" href="leden.php?vergadering=<?php echo urlencode($v['id']); ?>#bestuursvergadering">Openen</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (in_array('ledenvergadering', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-ledenvergadering">
    <!-- ===== LEDENVERGADERINGEN (incl. ALV) ===== -->
    <?php if ($ledenvergaderingBewerk !== null): ?>
      <div class="kaart">
        <div class="kaart-header">
          <div>
            <h1><?php echo $ledenvergaderingNieuw ? 'Nieuwe ledenvergadering' : htmlspecialchars(vergaderingWeergavenaam($ledenvergaderingBewerk)); ?></h1>
            <p class="sub">
              <?php if ($ledenvergaderingNieuw): ?>
                Vul in elk geval een datum in. De rest kan later, bijvoorbeeld de notulen na afloop.
              <?php else: ?>
                <?php echo htmlspecialchars($vergaderingLedenTypeLabels[$ledenvergaderingBewerk['ledenvergadering_type']] ?? 'Ledenvergadering'); ?> <?php echo (int) $ledenvergaderingBewerk['nummer']; ?><?php if (($ledenvergaderingBewerk['aangemaakt_door'] ?? '') !== ''): ?>, aangemaakt door <?php echo htmlspecialchars($ledenvergaderingBewerk['aangemaakt_door']); ?><?php endif; ?>.
              <?php endif; ?>
            </p>
          </div>
          <a class="knop-klein" href="leden.php#ledenvergadering">Terug naar het overzicht</a>
        </div>

        <?php if (isset($melding['ledenvergadering'])): ?>
          <div class="melding <?php echo $meldingType['ledenvergadering']; ?>"><?php echo htmlspecialchars($melding['ledenvergadering']); ?></div>
        <?php endif; ?>

        <form method="post" action="leden.php#ledenvergadering">
          <input type="hidden" name="formulier" value="ledenvergadering_opslaan">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
          <input type="hidden" name="vergadering_id" value="<?php echo $ledenvergaderingNieuw ? '' : htmlspecialchars($ledenvergaderingBewerk['id']); ?>">

          <div class="sectie-kop">Soort, wanneer en waar</div>
          <div class="rij-3">
            <div class="veld">
              <label for="lverg-type">Soort</label>
              <select id="lverg-type" name="ledenvergadering_type">
                <?php foreach ($vergaderingLedenTypeLabels as $sleutel => $label): ?>
                  <option value="<?php echo htmlspecialchars($sleutel); ?>"<?php echo ($ledenvergaderingBewerk['ledenvergadering_type'] ?? 'regulier') === $sleutel ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
              </select>
              <p class="hint">Een ALV is qua opzet gewoon een ledenvergadering, alleen zo terug te vinden als jaarvergadering.</p>
            </div>
            <div class="veld">
              <label for="lverg-datum">Datum</label>
              <div class="datum-invoer-rij">
                <input type="text" inputmode="numeric" id="lverg-datum" name="datum" maxlength="10" placeholder="dd-mm-jjjj" value="<?php echo htmlspecialchars(datumWeergave($ledenvergaderingBewerk['datum'])); ?>">
                <button type="button" class="datum-picker-wrap" title="Datum kiezen uit kalender" aria-label="Datum kiezen"><span class="datum-picker-icoon" aria-hidden="true">📅</span><input type="date" class="datum-picker" data-doel="lverg-datum" tabindex="-1" aria-hidden="true"></button>
              </div>
            </div>
            <div class="veld">
              <label for="lverg-tijd">Aanvang</label>
              <input type="text" id="lverg-tijd" name="tijd" maxlength="5" placeholder="20:00" value="<?php echo htmlspecialchars($ledenvergaderingBewerk['tijd']); ?>">
            </div>
          </div>

          <div class="rij-3">
            <div class="veld">
              <label for="lverg-status">Status</label>
              <select id="lverg-status" name="status">
                <?php foreach ($vergaderingStatusLabels as $sleutel => $label): ?>
                  <option value="<?php echo htmlspecialchars($sleutel); ?>"<?php echo $ledenvergaderingBewerk['status'] === $sleutel ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="veld">
              <label for="lverg-titel">Titel</label>
              <input type="text" id="lverg-titel" name="titel" maxlength="120" placeholder="Bijvoorbeeld ALV 2026" value="<?php echo htmlspecialchars($ledenvergaderingBewerk['titel']); ?>">
              <p class="hint">Mag leeg blijven, dan staat de datum in het overzicht.</p>
            </div>
            <div class="veld">
              <label for="lverg-locatie">Locatie</label>
              <input type="text" id="lverg-locatie" name="locatie" maxlength="120" placeholder="Kantine" value="<?php echo htmlspecialchars($ledenvergaderingBewerk['locatie']); ?>">
            </div>
          </div>

          <div class="sectie-kop">Aanwezigheid</div>
          <?php if (count($ledenActiefVoorAanwezigheid) === 0): ?>
            <p class="hint">Er staan nog geen actieve leden in de ledenadministratie, dus verschijnt hier nog geen presentielijst.</p>
          <?php else: ?>
            <p class="hint">De lijst volgt de actieve leden uit de ledenadministratie. Niets aanvinken betekent gewoon "nog niet ingevuld".</p>
            <?php foreach ($ledenActiefVoorAanwezigheid as $al): ?>
              <?php $keuzeNu = $ledenvergaderingBewerk['aanwezigheid'][$al['id']] ?? ''; ?>
              <div class="verg-aanwezig-regel">
                <span class="verg-aanwezig-naam">
                  <strong><?php echo htmlspecialchars(ledenVolledigeNaam($al)); ?></strong>
                </span>
                <span class="verg-aanwezig-keuze">
                  <label class="leden-vink"><input type="radio" name="aanwezigheid[<?php echo htmlspecialchars($al['id']); ?>]" value=""<?php echo $keuzeNu === '' ? ' checked' : ''; ?>> Onbekend</label>
                  <?php foreach ($vergaderingAanwezigheidLabels as $aSleutel => $aLabel): ?>
                    <label class="leden-vink"><input type="radio" name="aanwezigheid[<?php echo htmlspecialchars($al['id']); ?>]" value="<?php echo htmlspecialchars($aSleutel); ?>"<?php echo $keuzeNu === $aSleutel ? ' checked' : ''; ?>> <?php echo htmlspecialchars($aLabel); ?></label>
                  <?php endforeach; ?>
                </span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

          <div class="sectie-kop">Agendapunten</div>
          <p class="hint">Het lege blok onderaan voegt een punt toe. Een punt zonder onderwerp wordt niet opgeslagen.</p>
          <div class="veld">
            <label for="lverg-agenda-status">Status van de agenda</label>
            <select id="lverg-agenda-status" name="agenda_status">
              <?php foreach (vergaderingDocumentStatussen() as $sleutel => $label): ?>
                <option value="<?php echo htmlspecialchars($sleutel); ?>"<?php echo ($ledenvergaderingBewerk['agenda_status'] ?? 'concept') === $sleutel ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
              <?php endforeach; ?>
            </select>
            <p class="hint">Leden zien de agenda op de ledenpagina hoe dan ook, met dit label erbij. Zet hem op definitief zodra er niets meer verandert.</p>
          </div>
          <?php foreach ($ledenvergaderingAgendaBlokken as $ai => $punt): ?>
            <div class="verg-agendapunt">
              <div class="verg-agendapunt-kop"><?php echo trim((string) $punt['onderwerp']) === '' ? 'Nieuw agendapunt' : 'Punt ' . ($ai + 1); ?></div>
              <div class="rij-2">
                <div class="veld">
                  <label for="lverg-a-onderwerp-<?php echo $ai; ?>">Onderwerp</label>
                  <input type="text" id="lverg-a-onderwerp-<?php echo $ai; ?>" name="agenda[<?php echo $ai; ?>][onderwerp]" maxlength="160" value="<?php echo htmlspecialchars((string) $punt['onderwerp']); ?>">
                </div>
                <div class="veld">
                  <label for="lverg-a-indiener-<?php echo $ai; ?>">Ingebracht door</label>
                  <input type="text" id="lverg-a-indiener-<?php echo $ai; ?>" name="agenda[<?php echo $ai; ?>][indiener]" maxlength="80" value="<?php echo htmlspecialchars((string) $punt['indiener']); ?>">
                </div>
              </div>
              <div class="veld">
                <label for="lverg-a-toelichting-<?php echo $ai; ?>">Toelichting</label>
                <textarea id="lverg-a-toelichting-<?php echo $ai; ?>" name="agenda[<?php echo $ai; ?>][toelichting]" maxlength="4000" style="min-height:60px;"><?php echo htmlspecialchars((string) $punt['toelichting']); ?></textarea>
              </div>
              <div class="veld">
                <label for="lverg-a-besluit-<?php echo $ai; ?>">Besluit</label>
                <textarea id="lverg-a-besluit-<?php echo $ai; ?>" name="agenda[<?php echo $ai; ?>][besluit]" maxlength="4000" style="min-height:60px;"><?php echo htmlspecialchars((string) $punt['besluit']); ?></textarea>
                <p class="hint">Vul je na afloop in. Zo staat bij elk punt wat er uiteindelijk is afgesproken.</p>
              </div>
              <?php if (trim((string) $punt['onderwerp']) !== ''): ?>
                <label class="leden-vink leden-vink-weg"><input type="checkbox" name="agenda[<?php echo $ai; ?>][verwijderen]" value="1"> Dit punt verwijderen</label>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>

          <div class="sectie-kop">Notulen</div>
          <div class="veld">
            <label for="lverg-notulen">Verslag</label>
            <textarea id="lverg-notulen" name="notulen" maxlength="20000" style="min-height:200px;"><?php echo htmlspecialchars($ledenvergaderingBewerk['notulen']); ?></textarea>
            <p class="hint">Vrije tekst. Wat per agendapunt is besloten hoort bij dat punt zelf, hier komt de rest van het verslag.</p>
          </div>
          <div class="veld">
            <label for="lverg-notulen-status">Status van de notulen</label>
            <select id="lverg-notulen-status" name="notulen_status">
              <?php foreach (vergaderingDocumentStatussen() as $sleutel => $label): ?>
                <option value="<?php echo htmlspecialchars($sleutel); ?>"<?php echo ($ledenvergaderingBewerk['notulen_status'] ?? 'concept') === $sleutel ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
              <?php endforeach; ?>
            </select>
            <p class="hint">Zolang dit op concept staat zien leden het verslag niet. Pas op definitief zetten als de notulen zijn vastgesteld.</p>
          </div>

          <button type="submit">Vergadering opslaan</button>
        </form>

        <?php if (!$ledenvergaderingNieuw): ?>
          <form method="post" action="leden.php#ledenvergadering" onsubmit="return confirm('Deze vergadering definitief verwijderen? De vorige versie blijft in de back-ups staan.');" style="margin-top:14px;">
            <input type="hidden" name="formulier" value="ledenvergadering_verwijderen">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="vergadering_id" value="<?php echo htmlspecialchars($ledenvergaderingBewerk['id']); ?>">
            <button type="submit" class="knop-klein">Vergadering verwijderen</button>
          </form>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="kaart">
        <div class="kaart-header">
          <div>
            <h1>Ledenvergaderingen</h1>
            <p class="sub">Ledenvergaderingen en ALV's (jaarvergaderingen), los van de bestuursvergaderingen. Alleen leden met een bestuursfunctie in de ledenadministratie zien dit tabblad.</p>
          </div>
          <a class="knop-toevoegen" href="leden.php?ledenvergadering=nieuw#ledenvergadering">Nieuwe ledenvergadering</a>
        </div>

        <?php if (isset($melding['ledenvergadering'])): ?>
          <div class="melding <?php echo $meldingType['ledenvergadering']; ?>"><?php echo htmlspecialchars($melding['ledenvergadering']); ?></div>
        <?php endif; ?>

        <?php if (count($ledenvergaderingenLijst) === 0): ?>
          <p class="hint">Nog geen ledenvergaderingen of ALV's. Maak er een aan met de knop hierboven.</p>
        <?php else: ?>
          <div class="leden-tabel-wrap">
            <table class="leden-tabel" id="ledenvergaderingen-tabel">
              <thead>
                <tr>
                  <th>Datum</th>
                  <th>Vergadering</th>
                  <th>Soort</th>
                  <th>Status</th>
                  <th>Agenda</th>
                  <th>Aanwezig</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($ledenvergaderingenLijst as $v): ?>
                  <?php $lvTelling = vergaderingAanwezigheidTelling($v); ?>
                  <tr data-href="leden.php?ledenvergadering=<?php echo urlencode($v['id']); ?>#ledenvergadering">
                    <td data-label="Datum"><span class="lc"><?php echo htmlspecialchars(datumWeergave($v['datum'])); ?><?php if (($v['tijd'] ?? '') !== ''): ?> <?php echo htmlspecialchars($v['tijd']); ?><?php endif; ?></span></td>
                    <td class="lc-kop">
                      <span class="lc"><strong><?php echo htmlspecialchars(vergaderingWeergavenaam($v)); ?></strong>
                      <?php if (($v['locatie'] ?? '') !== ''): ?><span class="leden-bron"><?php echo htmlspecialchars($v['locatie']); ?></span><?php endif; ?></span>
                    </td>
                    <td data-label="Soort"><span class="lc"><?php echo htmlspecialchars($vergaderingLedenTypeLabels[$v['ledenvergadering_type'] ?? 'regulier'] ?? 'Ledenvergadering'); ?></span></td>
                    <td data-label="Status"><span class="lc"><span class="leden-badge vb-<?php echo htmlspecialchars($v['status']); ?>"><?php echo htmlspecialchars($vergaderingStatusLabels[$v['status']] ?? $v['status']); ?></span></span></td>
                    <td data-label="Agenda"><span class="lc"><?php echo count($v['agenda']); ?> <?php echo count($v['agenda']) === 1 ? 'punt' : 'punten'; ?></span></td>
                    <td data-label="Aanwezig"><span class="lc"><?php echo $lvTelling['aanwezig'] > 0 || $lvTelling['afgemeld'] > 0 || $lvTelling['afwezig'] > 0 ? $lvTelling['aanwezig'] . ' van ' . count($ledenActiefVoorAanwezigheid) : '<span class="leden-leeg">niet ingevuld</span>'; ?></span></td>
                    <td class="lc-actie"><a class="knop-klein" href="leden.php?ledenvergadering=<?php echo urlencode($v['id']); ?>#ledenvergadering">Openen</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (in_array('takenlijst', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-takenlijst">
    <!-- ===== TAKENLIJST (BESTUUR) ===== -->
    <?php if ($taakBewerk !== null): ?>
      <div class="kaart">
        <div class="kaart-header">
          <div>
            <h1><?php echo $taakNieuw ? 'Nieuwe taak' : htmlspecialchars(taakWeergavenaam($taakBewerk)); ?></h1>
            <p class="sub">
              <?php if ($taakNieuw): ?>
                Vul in elk geval een omschrijving in. Koppelen aan een vergadering of commissie kan, maar hoeft niet.
              <?php else: ?>
                Taak <?php echo (int) $taakBewerk['nummer']; ?><?php if (($taakBewerk['aangemaakt_door'] ?? '') !== ''): ?>, aangemaakt door <?php echo htmlspecialchars($taakBewerk['aangemaakt_door']); ?><?php endif; ?>.
              <?php endif; ?>
            </p>
          </div>
          <a class="knop-klein" href="leden.php#takenlijst">Terug naar het overzicht</a>
        </div>

        <?php if (isset($melding['takenlijst'])): ?>
          <div class="melding <?php echo $meldingType['takenlijst']; ?>"><?php echo htmlspecialchars($melding['takenlijst']); ?></div>
        <?php endif; ?>

        <form method="post" action="leden.php#takenlijst">
          <input type="hidden" name="formulier" value="taak_opslaan">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
          <input type="hidden" name="taak_id" value="<?php echo $taakNieuw ? '' : htmlspecialchars($taakBewerk['id']); ?>">

          <div class="veld">
            <label for="taak-omschrijving">Omschrijving</label>
            <input type="text" id="taak-omschrijving" name="omschrijving" maxlength="200" value="<?php echo htmlspecialchars($taakBewerk['omschrijving']); ?>">
          </div>

          <div class="rij-3">
            <div class="veld">
              <label for="taak-status">Status</label>
              <select id="taak-status" name="status">
                <?php foreach ($taakStatusLabels as $sleutel => $label): ?>
                  <option value="<?php echo htmlspecialchars($sleutel); ?>"<?php echo $taakBewerk['status'] === $sleutel ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="veld">
              <label for="taak-commissie">Commissie</label>
              <select id="taak-commissie" name="commissie_id">
                <option value="">Geen</option>
                <?php foreach ($ledenCommissieLijst as $cSleutel => $cNaam): ?>
                  <option value="<?php echo htmlspecialchars($cSleutel); ?>"<?php echo $taakBewerk['commissie_id'] === $cSleutel ? ' selected' : ''; ?>><?php echo htmlspecialchars($cNaam); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="veld">
              <label for="taak-toegewezen">Toegewezen aan</label>
              <select id="taak-toegewezen" name="toegewezen_aan">
                <option value="">Niemand</option>
                <?php foreach ($ledenActiefVoorAanwezigheid as $tl): ?>
                  <option value="<?php echo htmlspecialchars($tl['id']); ?>"<?php echo $taakBewerk['toegewezen_aan'] === $tl['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars(ledenVolledigeNaam($tl)); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="veld">
            <label for="taak-vergadering">Besproken in</label>
            <?php
              $taakVergSelectie = $taakBewerk['vergadering_soort'] !== '' ? $taakBewerk['vergadering_soort'] . ':' . $taakBewerk['vergadering_id'] : '';
            ?>
            <select id="taak-vergadering" name="taak_vergadering_selectie">
              <option value="">Geen koppeling</option>
              <?php if (count($vergaderingenVoorTaakKeuze['bestuur']) > 0): ?>
                <optgroup label="Bestuursvergaderingen">
                  <?php foreach ($vergaderingenVoorTaakKeuze['bestuur'] as $v): ?>
                    <option value="bestuur:<?php echo htmlspecialchars($v['id']); ?>"<?php echo $taakVergSelectie === 'bestuur:' . $v['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars(vergaderingWeergavenaam($v)); ?></option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endif; ?>
              <?php if (count($vergaderingenVoorTaakKeuze['leden']) > 0): ?>
                <optgroup label="Ledenvergaderingen en ALV's">
                  <?php foreach ($vergaderingenVoorTaakKeuze['leden'] as $v): ?>
                    <option value="leden:<?php echo htmlspecialchars($v['id']); ?>"<?php echo $taakVergSelectie === 'leden:' . $v['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars(vergaderingWeergavenaam($v)); ?></option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endif; ?>
            </select>
            <p class="hint">Bijvoorbeeld "besproken in vergadering 12" of "besproken in ledenvergadering 3".</p>
          </div>

          <div class="veld">
            <label for="taak-toelichting">Toelichting</label>
            <textarea id="taak-toelichting" name="toelichting" maxlength="4000" style="min-height:100px;"><?php echo htmlspecialchars($taakBewerk['toelichting']); ?></textarea>
          </div>

          <button type="submit">Taak opslaan</button>
        </form>

        <?php if (!$taakNieuw): ?>
          <form method="post" action="leden.php#takenlijst" onsubmit="return confirm('Deze taak definitief verwijderen? De vorige versie blijft in de back-ups staan.');" style="margin-top:14px;">
            <input type="hidden" name="formulier" value="taak_verwijderen">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="taak_id" value="<?php echo htmlspecialchars($taakBewerk['id']); ?>">
            <button type="submit" class="knop-klein">Taak verwijderen</button>
          </form>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="kaart">
        <div class="kaart-header">
          <div>
            <h1>Takenlijst</h1>
            <p class="sub">Openstaande en afgeronde bestuurstaken, desgewenst gekoppeld aan een vergadering of commissie. Alleen leden met een bestuursfunctie in de ledenadministratie zien dit tabblad.</p>
          </div>
          <a class="knop-toevoegen" href="leden.php?taak=nieuw#takenlijst">Nieuwe taak</a>
        </div>

        <?php if (isset($melding['takenlijst'])): ?>
          <div class="melding <?php echo $meldingType['takenlijst']; ?>"><?php echo htmlspecialchars($melding['takenlijst']); ?></div>
        <?php endif; ?>

        <?php if (count($takenLijst) === 0): ?>
          <p class="hint">Nog geen taken. Maak er een aan met de knop hierboven.</p>
        <?php else: ?>
          <div class="leden-tabel-wrap">
            <table class="leden-tabel" id="takenlijst-tabel">
              <thead>
                <tr>
                  <th>Taak</th>
                  <th>Status</th>
                  <th>Besproken in</th>
                  <th>Commissie</th>
                  <th>Toegewezen aan</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($takenLijst as $t): ?>
                  <tr data-href="leden.php?taak=<?php echo urlencode($t['id']); ?>#takenlijst">
                    <td class="lc-kop"><span class="lc"><strong><?php echo htmlspecialchars(taakWeergavenaam($t)); ?></strong></span></td>
                    <td data-label="Status"><span class="lc"><span class="leden-badge tk-<?php echo htmlspecialchars($t['status']); ?>"><?php echo htmlspecialchars($taakStatusLabels[$t['status']] ?? $t['status']); ?></span></span></td>
                    <td data-label="Besproken in"><span class="lc"><?php $vergTekst = taakVergaderingTekst($t, $vergaderingenBijId); echo $vergTekst !== '' ? htmlspecialchars($vergTekst) : '<span class="leden-leeg">geen koppeling</span>'; ?></span></td>
                    <td data-label="Commissie"><span class="lc"><?php echo ($t['commissie_id'] !== '' && isset($ledenCommissieLijst[$t['commissie_id']])) ? htmlspecialchars($ledenCommissieLijst[$t['commissie_id']]) : '<span class="leden-leeg">geen</span>'; ?></span></td>
                    <td data-label="Toegewezen aan"><span class="lc"><?php echo ($t['toegewezen_aan'] !== '' && isset($ledenNaamBijId[$t['toegewezen_aan']])) ? htmlspecialchars($ledenNaamBijId[$t['toegewezen_aan']]) : '<span class="leden-leeg">niemand</span>'; ?></span></td>
                    <td class="lc-actie"><a class="knop-klein" href="leden.php?taak=<?php echo urlencode($t['id']); ?>#takenlijst">Openen</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (in_array('operationele_taken', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-operationele_taken">
    <!-- ===== OPERATIONELE TAKEN ===== -->
    <?php if ($otaakBewerk !== null): ?>
      <div class="kaart">
        <div class="kaart-header">
          <div>
            <h1><?php echo $otaakNieuw ? 'Nieuwe operationele taak' : htmlspecialchars(otaakWeergavenaam($otaakBewerk)); ?></h1>
            <p class="sub">
              <?php if ($otaakNieuw): ?>
                Een terugkerende klus die de club sowieso moet doen, met een frequentie en eventueel een lid dat ervoor verantwoordelijk is.
              <?php else: ?>
                Taak <?php echo (int) $otaakBewerk['nummer']; ?><?php if (($otaakBewerk['aangemaakt_door'] ?? '') !== ''): ?>, aangemaakt door <?php echo htmlspecialchars($otaakBewerk['aangemaakt_door']); ?><?php endif; ?>.
              <?php endif; ?>
            </p>
          </div>
          <a class="knop-klein" href="leden.php#operationele_taken">Terug naar het overzicht</a>
        </div>

        <?php if (isset($melding['operationele_taken'])): ?>
          <div class="melding <?php echo $meldingType['operationele_taken']; ?>"><?php echo htmlspecialchars($melding['operationele_taken']); ?></div>
        <?php endif; ?>

        <?php if (!$otaakNieuw): ?>
          <div class="veld">
            <label>Laatst uitgevoerd</label>
            <p class="hint" style="margin-top:0;">
              <?php if ($otaakBewerk['laatst_uitgevoerd'] !== ''): ?>
                <?php echo htmlspecialchars(datumWeergave($otaakBewerk['laatst_uitgevoerd'])); ?><?php if ($otaakBewerk['laatst_uitgevoerd_door'] !== ''): ?> door <?php echo htmlspecialchars($otaakBewerk['laatst_uitgevoerd_door']); ?><?php endif; ?>
                <?php if ($otaakBewerk['volgende_uitvoering'] !== ''): ?>, volgende keer rond <?php echo htmlspecialchars(datumWeergave($otaakBewerk['volgende_uitvoering'])); ?><?php endif; ?>
              <?php else: ?>
                Nog nooit gemeld als uitgevoerd.
              <?php endif; ?>
            </p>
          </div>

          <form method="post" action="leden.php#operationele_taken" style="margin-bottom:18px;">
            <input type="hidden" name="formulier" value="otaak_uitgevoerd">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="otaak_id" value="<?php echo htmlspecialchars($otaakBewerk['id']); ?>">
            <button type="submit" class="knop-klein">Uitvoering melden</button>
          </form>

          <?php if (count($otaakBewerk['geschiedenis']) > 0): ?>
            <div class="veld">
              <label>Eerdere keren</label>
              <p class="hint" style="margin-top:0;">
                <?php
                  $geschRegels = [];
                  foreach (array_slice($otaakBewerk['geschiedenis'], 0, 10) as $g) {
                    $geschRegels[] = datumWeergave($g['datum'] ?? '') . (($g['door'] ?? '') !== '' ? ' (' . $g['door'] . ')' : '');
                  }
                  echo htmlspecialchars(implode(', ', $geschRegels));
                ?>
              </p>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <form method="post" action="leden.php#operationele_taken">
          <input type="hidden" name="formulier" value="otaak_opslaan">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
          <input type="hidden" name="otaak_id" value="<?php echo $otaakNieuw ? '' : htmlspecialchars($otaakBewerk['id']); ?>">

          <div class="veld">
            <label for="otaak-omschrijving">Omschrijving</label>
            <input type="text" id="otaak-omschrijving" name="omschrijving" maxlength="200" value="<?php echo htmlspecialchars($otaakBewerk['omschrijving']); ?>">
          </div>

          <div class="rij-3">
            <div class="veld">
              <label for="otaak-frequentie">Uitvoeringsfrequentie</label>
              <select id="otaak-frequentie" name="frequentie">
                <?php foreach ($otaakFrequentieLabels as $sleutel => $label): ?>
                  <option value="<?php echo htmlspecialchars($sleutel); ?>"<?php echo $otaakBewerk['frequentie'] === $sleutel ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="veld">
              <label for="otaak-toegewezen">Toegewezen aan</label>
              <select id="otaak-toegewezen" name="toegewezen_aan">
                <option value="">Niemand</option>
                <?php foreach ($ledenActiefVoorAanwezigheid as $tl): ?>
                  <option value="<?php echo htmlspecialchars($tl['id']); ?>"<?php echo $otaakBewerk['toegewezen_aan'] === $tl['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars(ledenVolledigeNaam($tl)); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="veld">
              <?php if ($isBestuurslid): ?>
                <label for="otaak-zichtbaarheid">Zichtbaar voor</label>
                <select id="otaak-zichtbaarheid" name="zichtbaarheid">
                  <?php foreach ($otaakZichtbaarheidLabels as $sleutel => $label): ?>
                    <option value="<?php echo htmlspecialchars($sleutel); ?>"<?php echo $otaakBewerk['zichtbaarheid'] === $sleutel ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                  <?php endforeach; ?>
                </select>
              <?php else: ?>
                <label>Zichtbaar voor</label>
                <p class="hint" style="margin-top:8px;">Leden (alleen bestuursleden kunnen een taak op "Bestuursleden" zetten).</p>
              <?php endif; ?>
            </div>
          </div>

          <div class="veld">
            <label class="leden-vink">
              <input type="checkbox" name="actief" value="1"<?php echo !empty($otaakBewerk['actief']) ? ' checked' : ''; ?>>
              Actief
            </label>
            <p class="hint" style="margin-top:2px;">Staat de taak tijdelijk stil, bijvoorbeeld buiten het seizoen? Vink dan uit. De taak blijft bewaard en kun je later weer aanzetten.</p>
          </div>

          <div class="veld">
            <label for="otaak-toelichting">Toelichting</label>
            <textarea id="otaak-toelichting" name="toelichting" maxlength="4000" style="min-height:100px;"><?php echo htmlspecialchars($otaakBewerk['toelichting']); ?></textarea>
          </div>

          <button type="submit">Taak opslaan</button>
        </form>

        <?php if (!$otaakNieuw): ?>
          <form method="post" action="leden.php#operationele_taken" onsubmit="return confirm('Deze taak definitief verwijderen? De vorige versie blijft in de back-ups staan.');" style="margin-top:14px;">
            <input type="hidden" name="formulier" value="otaak_verwijderen">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="otaak_id" value="<?php echo htmlspecialchars($otaakBewerk['id']); ?>">
            <button type="submit" class="knop-klein">Taak verwijderen</button>
          </form>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="kaart">
        <div class="kaart-header">
          <div>
            <h1>Operationele taken</h1>
            <p class="sub">Terugkerende klussen die de club sowieso moet doen, met een uitvoeringsfrequentie en desgewenst een verantwoordelijk lid. Een taak met zichtbaarheid "Bestuursleden" is alleen zichtbaar voor leden met een bestuursfunctie.</p>
          </div>
          <a class="knop-toevoegen" href="leden.php?otaak=nieuw#operationele_taken">Nieuwe operationele taak</a>
        </div>

        <?php if (isset($melding['operationele_taken'])): ?>
          <div class="melding <?php echo $meldingType['operationele_taken']; ?>"><?php echo htmlspecialchars($melding['operationele_taken']); ?></div>
        <?php endif; ?>

        <?php if (count($otakenLijst) === 0): ?>
          <p class="hint">Nog geen operationele taken. Maak er een aan met de knop hierboven.</p>
        <?php else: ?>
          <div class="leden-tabel-wrap">
            <table class="leden-tabel" id="operationele-taken-tabel">
              <thead>
                <tr>
                  <th>Taak</th>
                  <th>Frequentie</th>
                  <th>Toegewezen aan</th>
                  <th>Status</th>
                  <?php if ($isBestuurslid): ?><th>Zichtbaar voor</th><?php endif; ?>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($otakenLijst as $t): ?>
                  <?php $otStatus = otaakStatus($t); ?>
                  <tr data-href="leden.php?otaak=<?php echo urlencode($t['id']); ?>#operationele_taken">
                    <td class="lc-kop"><span class="lc"><strong><?php echo htmlspecialchars(otaakWeergavenaam($t)); ?></strong></span></td>
                    <td data-label="Frequentie"><span class="lc"><?php echo htmlspecialchars($otaakFrequentieLabels[$t['frequentie']] ?? $t['frequentie']); ?></span></td>
                    <td data-label="Toegewezen aan"><span class="lc"><?php echo ($t['toegewezen_aan'] !== '' && isset($ledenNaamBijId[$t['toegewezen_aan']])) ? htmlspecialchars($ledenNaamBijId[$t['toegewezen_aan']]) : '<span class="leden-leeg">niemand</span>'; ?></span></td>
                    <td data-label="Status"><span class="lc"><span class="leden-badge ot-<?php echo htmlspecialchars($otStatus); ?>"><?php echo htmlspecialchars($otaakStatusLabels[$otStatus] ?? $otStatus); ?><?php if ($otStatus === 'gepland' && $t['volgende_uitvoering'] !== ''): ?> (<?php echo htmlspecialchars(datumWeergave($t['volgende_uitvoering'])); ?>)<?php endif; ?></span></span></td>
                    <?php if ($isBestuurslid): ?>
                      <td data-label="Zichtbaar voor"><span class="lc"><?php echo htmlspecialchars($otaakZichtbaarheidLabels[$t['zichtbaarheid']] ?? $t['zichtbaarheid']); ?></span></td>
                    <?php endif; ?>
                    <td class="lc-actie"><a class="knop-klein" href="leden.php?otaak=<?php echo urlencode($t['id']); ?>#operationele_taken">Openen</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (in_array('evenementen', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-evenementen">
    <!-- ===== EVENEMENTEN ===== -->
    <?php if ($evenementBewerk !== null): ?>
      <div class="kaart">
        <div class="kaart-header">
          <div>
            <h1><?php echo $evenementNieuw ? 'Nieuw evenement' : htmlspecialchars(evenementWeergavenaam($evenementBewerk)); ?></h1>
            <p class="sub">
              <?php if ($evenementNieuw): ?>
                Een activiteit waar leden zich voor kunnen aanmelden, zoals een clubdag of een wedstrijd.
              <?php else: ?>
                Evenement <?php echo (int) $evenementBewerk['nummer']; ?><?php if (($evenementBewerk['aangemaakt_door'] ?? '') !== ''): ?>, aangemaakt door <?php echo htmlspecialchars($evenementBewerk['aangemaakt_door']); ?><?php endif; ?>.
              <?php endif; ?>
            </p>
            <?php if (!$evenementNieuw && ($evenementBewerk['zichtbaarheid'] ?? 'leden') === 'leden' && !evenementZichtbaarVoorLeden($evenementBewerk)): ?>
              <p class="hint" style="margin-top:6px;">Nog niet zichtbaar voor leden: dat gebeurt vanaf <?php echo htmlspecialchars(datumWeergave($evenementBewerk['inschrijving_begin'])); ?>.</p>
            <?php endif; ?>
          </div>
          <a class="knop-klein" href="leden.php#evenementen">Terug naar het overzicht</a>
        </div>

        <?php if (isset($melding['evenementen'])): ?>
          <div class="melding <?php echo $meldingType['evenementen']; ?>"><?php echo htmlspecialchars($melding['evenementen']); ?></div>
        <?php endif; ?>

        <form method="post" action="leden.php#evenementen">
          <input type="hidden" name="formulier" value="evenement_opslaan">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
          <input type="hidden" name="evenement_id" value="<?php echo $evenementNieuw ? '' : htmlspecialchars($evenementBewerk['id']); ?>">

          <div class="veld">
            <label for="ev-titel">Titel</label>
            <input type="text" id="ev-titel" name="titel" maxlength="160" value="<?php echo htmlspecialchars($evenementBewerk['titel']); ?>">
          </div>

          <div class="rij-3">
            <div class="veld">
              <label for="ev-datum">Datum</label>
              <div class="datum-invoer-rij">
                <input type="text" inputmode="numeric" id="ev-datum" name="datum" maxlength="10" placeholder="dd-mm-jjjj" value="<?php echo htmlspecialchars(datumWeergave($evenementBewerk['datum'])); ?>">
                <button type="button" class="datum-picker-wrap" title="Datum kiezen uit kalender" aria-label="Datum kiezen"><span class="datum-picker-icoon" aria-hidden="true">📅</span><input type="date" class="datum-picker" data-doel="ev-datum" tabindex="-1" aria-hidden="true"></button>
              </div>
            </div>
            <div class="veld">
              <label for="ev-tijd">Aanvang</label>
              <input type="text" id="ev-tijd" name="tijd" maxlength="5" placeholder="10:00" value="<?php echo htmlspecialchars($evenementBewerk['tijd']); ?>">
            </div>
            <div class="veld">
              <label for="ev-eindtijd">Eindtijd</label>
              <input type="text" id="ev-eindtijd" name="eindtijd" maxlength="5" placeholder="17:00" value="<?php echo htmlspecialchars($evenementBewerk['eindtijd']); ?>">
            </div>
          </div>

          <div class="veld">
            <label for="ev-locatie">Locatie</label>
            <input type="text" id="ev-locatie" name="locatie" maxlength="120" placeholder="Baan RC045" value="<?php echo htmlspecialchars($evenementBewerk['locatie']); ?>">
          </div>

          <div class="rij-2">
            <div class="veld">
              <label for="ev-inschrijving-begin">Begindatum inschrijving</label>
              <div class="datum-invoer-rij">
                <input type="text" inputmode="numeric" id="ev-inschrijving-begin" name="inschrijving_begin" maxlength="10" placeholder="dd-mm-jjjj" value="<?php echo htmlspecialchars(datumWeergave($evenementBewerk['inschrijving_begin'])); ?>">
                <button type="button" class="datum-picker-wrap" title="Datum kiezen uit kalender" aria-label="Datum kiezen"><span class="datum-picker-icoon" aria-hidden="true">📅</span><input type="date" class="datum-picker" data-doel="ev-inschrijving-begin" tabindex="-1" aria-hidden="true"></button>
              </div>
              <p class="hint">Leeg laten om het evenement meteen zichtbaar te maken. Anders is het pas vanaf deze datum zichtbaar voor leden: tot dan kun je het al wel rustig voorbereiden.</p>
            </div>
            <div class="veld">
              <label for="ev-inschrijving-eind">Einddatum inschrijving</label>
              <div class="datum-invoer-rij">
                <input type="text" inputmode="numeric" id="ev-inschrijving-eind" name="inschrijving_eind" maxlength="10" placeholder="dd-mm-jjjj" value="<?php echo htmlspecialchars(datumWeergave($evenementBewerk['inschrijving_eind'])); ?>">
                <button type="button" class="datum-picker-wrap" title="Datum kiezen uit kalender" aria-label="Datum kiezen"><span class="datum-picker-icoon" aria-hidden="true">📅</span><input type="date" class="datum-picker" data-doel="ev-inschrijving-eind" tabindex="-1" aria-hidden="true"></button>
              </div>
              <p class="hint">Alleen ter informatie: leden kunnen zich hier gewoon aangemeld blijven zien, dit sluit de aanmelding nog niet automatisch af.</p>
            </div>
          </div>

          <div class="rij-2">
            <div class="veld">
              <label for="ev-capaciteit">Maximaal aantal deelnemers</label>
              <input type="number" id="ev-capaciteit" name="capaciteit" min="0" max="9999" placeholder="onbeperkt" value="<?php echo ((int) $evenementBewerk['capaciteit']) > 0 ? (int) $evenementBewerk['capaciteit'] : ''; ?>">
              <p class="hint">Leeg laten voor een onbeperkt aantal plekken.</p>
            </div>
            <div class="veld">
              <?php if ($isBestuurslid): ?>
                <label for="ev-zichtbaarheid">Zichtbaar voor</label>
                <select id="ev-zichtbaarheid" name="zichtbaarheid">
                  <?php foreach ($evenementZichtbaarheidLabels as $sleutel => $label): ?>
                    <option value="<?php echo htmlspecialchars($sleutel); ?>"<?php echo $evenementBewerk['zichtbaarheid'] === $sleutel ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                  <?php endforeach; ?>
                </select>
              <?php else: ?>
                <label>Zichtbaar voor</label>
                <p class="hint" style="margin-top:8px;">Leden (alleen bestuursleden kunnen een evenement op "Bestuursleden" zetten).</p>
              <?php endif; ?>
            </div>
          </div>

          <div class="veld">
            <label for="ev-betaalverzoek">Betaalverzoek</label>
            <input type="text" id="ev-betaalverzoek" name="betaalverzoek" maxlength="500" placeholder="https://tikkie.me/pay/..." value="<?php echo htmlspecialchars($evenementBewerk['betaalverzoek']); ?>">
            <p class="hint">
              Plak hier de link van een betaalverzoek (bv. Tikkie), zodat leden meteen kunnen betalen bij het aanmelden.
              <?php if ($evenementBewerk['betaalverzoek'] !== '' && preg_match('#^https?://#i', $evenementBewerk['betaalverzoek'])): ?>
                <a href="<?php echo htmlspecialchars($evenementBewerk['betaalverzoek']); ?>" target="_blank" rel="noopener noreferrer">Huidige link openen &#8599;</a>
              <?php endif; ?>
            </p>
          </div>

          <div class="veld">
            <label for="ev-omschrijving">Omschrijving</label>
            <textarea id="ev-omschrijving" name="omschrijving" maxlength="4000" style="min-height:100px;"><?php echo htmlspecialchars($evenementBewerk['omschrijving']); ?></textarea>
          </div>

          <div class="sectie-kop">Aanmeldingen</div>
          <?php if (count($ledenActiefVoorAanwezigheid) === 0): ?>
            <p class="hint">Er staan nog geen actieve leden in de ledenadministratie, dus is er nog niemand om aan te melden.</p>
          <?php else: ?>
            <p class="hint">Vink aan wie meedoet. Zodra er een ledenportaal is, kunnen leden zich hier straks ook zelf voor aan- of afmelden.</p>
            <?php foreach ($ledenActiefVoorAanwezigheid as $al): ?>
              <div class="verg-aanwezig-regel">
                <span class="verg-aanwezig-naam">
                  <strong><?php echo htmlspecialchars(ledenVolledigeNaam($al)); ?></strong>
                </span>
                <span class="verg-aanwezig-keuze">
                  <label class="leden-vink"><input type="checkbox" name="deelnemers[<?php echo htmlspecialchars($al['id']); ?>]" value="1"<?php echo in_array($al['id'], $evenementBewerk['deelnemers'], true) ? ' checked' : ''; ?>> Aangemeld</label>
                </span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

          <button type="submit">Evenement opslaan</button>
        </form>

        <?php if (!$evenementNieuw): ?>
          <form method="post" action="leden.php#evenementen" onsubmit="return confirm('Dit evenement definitief verwijderen? De vorige versie blijft in de back-ups staan.');" style="margin-top:14px;">
            <input type="hidden" name="formulier" value="evenement_verwijderen">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="evenement_id" value="<?php echo htmlspecialchars($evenementBewerk['id']); ?>">
            <button type="submit" class="knop-klein">Evenement verwijderen</button>
          </form>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="kaart">
        <div class="kaart-header">
          <div>
            <h1>Evenementen</h1>
            <p class="sub">Activiteiten waar leden zich voor kunnen aanmelden. Een evenement met zichtbaarheid "Bestuursleden" is alleen zichtbaar voor leden met een bestuursfunctie.</p>
          </div>
          <a class="knop-toevoegen" href="leden.php?evenement=nieuw#evenementen">Nieuw evenement</a>
        </div>

        <?php if (isset($melding['evenementen'])): ?>
          <div class="melding <?php echo $meldingType['evenementen']; ?>"><?php echo htmlspecialchars($melding['evenementen']); ?></div>
        <?php endif; ?>

        <?php if (count($evenementenLijst) === 0): ?>
          <p class="hint">Nog geen evenementen. Maak er een aan met de knop hierboven.</p>
        <?php else: ?>
          <div class="leden-tabel-wrap">
            <table class="leden-tabel" id="evenementen-tabel">
              <thead>
                <tr>
                  <th>Datum</th>
                  <th>Evenement</th>
                  <th>Aanmeldingen</th>
                  <th>Status</th>
                  <?php if ($isBestuurslid): ?><th>Zichtbaar voor</th><?php endif; ?>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($evenementenLijst as $ev): ?>
                  <?php $evStatus = evenementStatus($ev); $evAantal = evenementAantalDeelnemers($ev); $evCapaciteit = (int) $ev['capaciteit']; ?>
                  <tr data-href="leden.php?evenement=<?php echo urlencode($ev['id']); ?>#evenementen">
                    <td data-label="Datum"><span class="lc"><?php echo $ev['datum'] !== '' ? htmlspecialchars(datumWeergave($ev['datum'])) : '<span class="leden-leeg">nog te plannen</span>'; ?><?php if (($ev['tijd'] ?? '') !== ''): ?> <?php echo htmlspecialchars($ev['tijd']); ?><?php if (($ev['eindtijd'] ?? '') !== ''): ?> - <?php echo htmlspecialchars($ev['eindtijd']); ?><?php endif; ?><?php endif; ?></span></td>
                    <td class="lc-kop">
                      <span class="lc"><strong><?php echo htmlspecialchars(evenementWeergavenaam($ev)); ?></strong>
                      <?php if (($ev['locatie'] ?? '') !== ''): ?><span class="leden-bron"><?php echo htmlspecialchars($ev['locatie']); ?></span><?php endif; ?></span>
                    </td>
                    <td data-label="Aanmeldingen"><span class="lc"><?php echo $evAantal; ?><?php echo $evCapaciteit > 0 ? ' van ' . $evCapaciteit : ''; ?><?php if (evenementIsVol($ev)): ?> <span class="leden-badge ev-vol">Vol</span><?php endif; ?></span></td>
                    <td data-label="Status"><span class="lc"><span class="leden-badge ev-<?php echo htmlspecialchars($evStatus); ?>"><?php echo htmlspecialchars($evenementStatusLabels[$evStatus] ?? $evStatus); ?></span></span></td>
                    <?php if ($isBestuurslid): ?>
                      <td data-label="Zichtbaar voor"><span class="lc"><?php echo htmlspecialchars($evenementZichtbaarheidLabels[$ev['zichtbaarheid']] ?? $ev['zichtbaarheid']); ?><?php if (($ev['zichtbaarheid'] ?? 'leden') === 'leden' && !evenementZichtbaarVoorLeden($ev)): ?> <span class="leden-bron">nog niet zichtbaar</span><?php endif; ?></span></td>
                    <?php endif; ?>
                    <td class="lc-actie"><a class="knop-klein" href="leden.php?evenement=<?php echo urlencode($ev['id']); ?>#evenementen">Openen</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    </div>
    <?php endif; ?>

    </div>
    </div>

  <?php endif; ?>

  </div>
  <script src="paneel.js?v=<?php echo @filemtime(__DIR__ . '/paneel.js'); ?>"></script>
</body>
</html>
