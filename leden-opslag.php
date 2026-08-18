<?php
// ============================================================
// RC045 ledenadministratie: opslag en hulpfuncties
// ------------------------------------------------------------
// Dit bestand bevat alleen functies en schrijft zelf niets naar
// het scherm. Het wordt gebruikt door meerdere plekken:
//   - leden.php                (overzicht, bewerken, import)
//   - auth.php                 (rol van een ingelogde gebruiker bepalen)
//   - aanmelden-ontvangst.php  (nieuwe aanmeldingen van aanmelden.html)
//
// PRIVACY. Het ledenbestand bevat geboortedata, adressen, telefoon-
// nummers en mailadressen. Het staat daarom BEWUST NIET in data/,
// want die map is publiek opvraagbaar omdat de website er JSON uit
// leest. Het bestand heet leden-data.php en begint met een regel
// PHP die de uitvoer meteen afbreekt. Wordt het ooit rechtstreeks
// opgevraagd, dan voert de server het uit als PHP en krijgt de
// bezoeker een lege pagina in plaats van het hele ledenbestand.
// Dat werkt ook als de afscherming in .htaccess ontbreekt, wat kan
// gebeuren omdat de deploy dotfiles overslaat. Zet die regel er
// alsnog bij, twee sloten is beter dan een.
// ============================================================

if (!defined('RC045_LEDEN')) {
  define('RC045_LEDEN', true);
}

// Eerste regel van leden-data.php. Alles daarna is JSON.
define('LEDEN_VOORLOOP', "<?php exit; ?>\n");

require_once __DIR__ . '/data-slot.php';

function ledenBestandPad() {
  return __DIR__ . '/leden-data.php';
}

function ledenBackupMap() {
  return __DIR__ . '/data-backups';
}

// ===== Statussen =====
// De sleutel gaat het bestand in, het label is wat het bestuur ziet.

function ledenStatussen() {
  return [
    'nieuw'             => 'Nieuw',
    'verificatie'       => 'In verificatie',
    'wacht_op_betaling' => 'Wacht op betaling',
    'actief'            => 'Actief lid',
    'opgezegd'          => 'Opgezegd',
    'geweigerd'         => 'Geweigerd',
  ];
}

function ledenContributieStatussen() {
  return [
    'open'            => 'Open',
    'betaald'         => 'Betaald',
    'kwijtgescholden' => 'Kwijtgescholden',
    'vervallen'       => 'Vervallen',
  ];
}

// ===== Rollen binnen de vereniging =====
// Vaste bestuursfuncties. Voorzitter, penningmeester en secretaris zijn
// per definitie ook bestuurslid, dus dit is één keuzelijst en geen los
// vinkje "is bestuurslid" ernaast. Wie hier iets anders dan leeg heeft
// staan, telt als bestuurslid.

function ledenBestuursfuncties() {
  return [
    'voorzitter'     => 'Voorzitter',
    'penningmeester' => 'Penningmeester',
    'secretaris'     => 'Secretaris',
    'bestuurslid'    => 'Bestuurslid',
  ];
}

function ledenIsBestuurslid($lid) {
  $functie = trim((string) ($lid['bestuursfunctie'] ?? ''));
  return $functie !== '' && array_key_exists($functie, ledenBestuursfuncties());
}

// Commissies stelt de club zelf samen. Ze staan in hetzelfde bestand als
// de leden, onder de sleutel 'commissies': een object van sleutel naar
// naam. De sleutel is een vaste, veilige variant van de naam en verandert
// niet meer als de naam later wordt aangepast, zodat de koppeling met de
// leden blijft kloppen bij een hernoeming.

function ledenCommissieSleutel($tekst) {
  $tekst = trim((string) $tekst);
  if ($tekst === '') return '';
  if (function_exists('iconv')) {
    $om = @iconv('UTF-8', 'ASCII//TRANSLIT', $tekst);
    if ($om !== false) $tekst = $om;
  }
  $tekst = strtolower($tekst);
  $tekst = preg_replace('/[^a-z0-9]+/', '_', $tekst);
  $tekst = trim($tekst, '_');
  return substr($tekst, 0, 40);
}

// De commissielijst uit het ledenbestand, opgeschoond. Geeft altijd
// sleutel => naam terug, ook als er nog nooit een commissie is aangemaakt.
function ledenCommissies($data) {
  $lijst = [];
  if (isset($data['commissies']) && is_array($data['commissies'])) {
    foreach ($data['commissies'] as $sleutel => $waarde) {
      $sleutel = ledenCommissieSleutel($sleutel);
      // Oud formaat: sleutel => naam (string). Nieuw formaat: sleutel => [
      // 'naam', 'bestuurslid_id', 'hoofd_lid_id' ]. Hier is alleen de naam
      // nodig, dus dat werkt met allebei.
      $naam = is_array($waarde) ? ($waarde['naam'] ?? '') : $waarde;
      $naam = ledenKort($naam, 60);
      if ($sleutel === '' || $naam === '') continue;
      $lijst[$sleutel] = $naam;
    }
  }
  return $lijst;
}

// Zelfde lijst, maar met de volledige commissiegegevens: naam, het
// verantwoordelijke bestuurslid en het commissiehoofd. Gebruikt door het
// tabblad Commissies. Elders in de code (checkboxen bij een lid, export,
// rolfilter) is alleen de naam nodig, die blijft via ledenCommissies() lopen
// zodat die plekken niet hoeven te weten van dit uitgebreidere formaat.
function ledenCommissiesVolledig($data) {
  $lijst = [];
  if (isset($data['commissies']) && is_array($data['commissies'])) {
    foreach ($data['commissies'] as $sleutel => $waarde) {
      $sleutel = ledenCommissieSleutel($sleutel);
      if ($sleutel === '') continue;
      if (is_array($waarde)) {
        $naam = ledenKort($waarde['naam'] ?? '', 60);
        $bestuurslidId = ledenKort($waarde['bestuurslid_id'] ?? '', 40);
        $hoofdId = ledenKort($waarde['hoofd_lid_id'] ?? '', 40);
      } else {
        $naam = ledenKort($waarde, 60);
        $bestuurslidId = '';
        $hoofdId = '';
      }
      if ($naam === '') continue;
      $lijst[$sleutel] = ['naam' => $naam, 'bestuurslid_id' => $bestuurslidId, 'hoofd_lid_id' => $hoofdId];
    }
  }
  return $lijst;
}

// De commissies van één lid als namen, in de volgorde van de lijst.
// Sleutels van commissies die niet meer bestaan vallen weg.
function ledenCommissieNamen($lid, $commissies) {
  $vanLid = isset($lid['commissies']) && is_array($lid['commissies']) ? $lid['commissies'] : [];
  $namen = [];
  foreach ($commissies as $sleutel => $naam) {
    if (in_array($sleutel, $vanLid, true)) $namen[] = $naam;
  }
  return $namen;
}

// Korte omschrijving van de rol, bijvoorbeeld "Penningmeester, Kantine".
function ledenRolTekst($lid, $commissies = []) {
  $delen = [];
  $functies = ledenBestuursfuncties();
  $functie = (string) ($lid['bestuursfunctie'] ?? '');
  if (isset($functies[$functie])) $delen[] = $functies[$functie];
  foreach (ledenCommissieNamen($lid, $commissies) as $naam) $delen[] = $naam;
  return implode(', ', $delen);
}

// Voor de import: "Penningmeester" of "bestuur" uit een CSV terugvertalen
// naar een sleutel. Onbekende tekst betekent geen bestuursfunctie.
function ledenBestuursfunctieUitTekst($tekst) {
  $t = strtolower(trim((string) $tekst));
  if ($t === '') return '';
  if (strpos($t, 'voorzitter') !== false) return 'voorzitter';
  if (strpos($t, 'penning') !== false) return 'penningmeester';
  if (strpos($t, 'secretaris') !== false) return 'secretaris';
  if (strpos($t, 'bestuur') !== false) return 'bestuurslid';
  return '';
}

// Bestuursfunctie en beheer_account zijn geen gewone ledengegevens: samen
// bepalen ze via authRechten() welke rolgebonden tabbladen een account krijgt.
// De opslaglaag beschermt ze daarom centraal. Als auth.php aanwezig is, mag
// alleen een account met het expliciete hoge-vertrouwensrecht "Gebruikers"
// (of de master) ze wijzigen. Buiten een ingelogde auth-context, bijvoorbeeld
// het openbare aanmeldformulier, is wijzigen altijd verboden.
function ledenMagAutorisatieWijzigen() {
  return function_exists('authMagLedenAutorisatieWijzigen') && authMagLedenAutorisatieWijzigen();
}

// De rol van de ingelogde gebruiker. De koppeling loopt via het veld
// beheer_account op het lid: daar staat de inlognaam waarmee dat lid inlogt.
// Zonder koppeling is er geen rol en dus geen extra toegang. Omdat dit veld
// samen met bestuursfunctie autorisatie bepaalt, worden beide velden in
// ledenNormaliseer() beschermd en alleen wijzigbaar door de master of iemand
// met het expliciete recht Gebruikers.
function ledenRolVanGebruiker($gebruikersnaam) {
  $leeg = ['lid' => null, 'bestuurslid' => false, 'functie' => '', 'commissies' => []];
  $gebruikersnaam = trim((string) $gebruikersnaam);
  if ($gebruikersnaam === '') return $leeg;
  $data = ledenLees();
  foreach ($data['leden'] as $lid) {
    $koppeling = trim((string) ($lid['beheer_account'] ?? ''));
    if ($koppeling === '' || strcasecmp($koppeling, $gebruikersnaam) !== 0) continue;
    return [
      'lid'         => $lid,
      'bestuurslid' => ledenIsBestuurslid($lid),
      'functie'     => (string) ($lid['bestuursfunctie'] ?? ''),
      'commissies'  => isset($lid['commissies']) && is_array($lid['commissies']) ? $lid['commissies'] : [],
    ];
  }
  return $leeg;
}

function ledenLanden() {
  return [
    'boven' => ['Nederland', 'Duitsland', 'België'],
    'overig' => [
    'Afghanistan',
    'Albanië',
    'Algerije',
    'Andorra',
    'Angola',
    'Antigua en Barbuda',
    'Argentinië',
    'Armenië',
    'Australië',
    'Azerbeidzjan',
    'Bahama\'s',
    'Bahrein',
    'Bangladesh',
    'Barbados',
    'Belize',
    'Benin',
    'Bhutan',
    'Bolivia',
    'Bosnië en Herzegovina',
    'Botswana',
    'Brazilië',
    'Brunei',
    'Bulgarije',
    'Burkina Faso',
    'Burundi',
    'Cambodja',
    'Canada',
    'Centraal-Afrikaanse Republiek',
    'Chili',
    'China',
    'Colombia',
    'Comoren',
    'Congo-Brazzaville',
    'Congo-Kinshasa',
    'Costa Rica',
    'Cuba',
    'Cyprus',
    'Denemarken',
    'Djibouti',
    'Dominica',
    'Dominicaanse Republiek',
    'Ecuador',
    'Egypte',
    'El Salvador',
    'Equatoriaal-Guinea',
    'Eritrea',
    'Estland',
    'Eswatini',
    'Ethiopië',
    'Fiji',
    'Filipijnen',
    'Finland',
    'Frankrijk',
    'Gabon',
    'Gambia',
    'Georgië',
    'Ghana',
    'Grenada',
    'Griekenland',
    'Guatemala',
    'Guinee',
    'Guinee-Bissau',
    'Guyana',
    'Haïti',
    'Honduras',
    'Hongarije',
    'Ierland',
    'IJsland',
    'India',
    'Indonesië',
    'Irak',
    'Iran',
    'Israël',
    'Italië',
    'Ivoorkust',
    'Jamaica',
    'Japan',
    'Jemen',
    'Jordanië',
    'Kaapverdië',
    'Kameroen',
    'Kazachstan',
    'Kenia',
    'Kirgizië',
    'Kiribati',
    'Koeweit',
    'Kroatië',
    'Laos',
    'Lesotho',
    'Letland',
    'Libanon',
    'Liberia',
    'Libië',
    'Liechtenstein',
    'Litouwen',
    'Luxemburg',
    'Madagaskar',
    'Malawi',
    'Maldiven',
    'Maleisië',
    'Mali',
    'Malta',
    'Marokko',
    'Marshalleilanden',
    'Mauritanië',
    'Mauritius',
    'Mexico',
    'Micronesië',
    'Moldavië',
    'Monaco',
    'Mongolië',
    'Montenegro',
    'Mozambique',
    'Myanmar',
    'Namibië',
    'Nauru',
    'Nepal',
    'Nicaragua',
    'Nieuw-Zeeland',
    'Niger',
    'Nigeria',
    'Noord-Korea',
    'Noord-Macedonië',
    'Noorwegen',
    'Oeganda',
    'Oekraïne',
    'Oezbekistan',
    'Oman',
    'Oostenrijk',
    'Oost-Timor',
    'Pakistan',
    'Palau',
    'Panama',
    'Papoea-Nieuw-Guinea',
    'Paraguay',
    'Peru',
    'Polen',
    'Portugal',
    'Qatar',
    'Roemenië',
    'Rusland',
    'Rwanda',
    'Saint Kitts en Nevis',
    'Saint Lucia',
    'Saint Vincent en de Grenadines',
    'Salomonseilanden',
    'Samoa',
    'San Marino',
    'Saoedi-Arabië',
    'Sao Tomé en Principe',
    'Senegal',
    'Servië',
    'Seychellen',
    'Sierra Leone',
    'Singapore',
    'Slovenië',
    'Slowakije',
    'Soedan',
    'Somalië',
    'Spanje',
    'Sri Lanka',
    'Suriname',
    'Syrië',
    'Tadzjikistan',
    'Taiwan',
    'Tanzania',
    'Thailand',
    'Togo',
    'Tonga',
    'Trinidad en Tobago',
    'Tsjaad',
    'Tsjechië',
    'Tunesië',
    'Turkije',
    'Turkmenistan',
    'Tuvalu',
    'Uruguay',
    'Vanuatu',
    'Vaticaanstad',
    'Venezuela',
    'Verenigde Arabische Emiraten',
    'Verenigde Staten',
    'Verenigd Koninkrijk',
    'Vietnam',
    'Wit-Rusland',
    'Zambia',
    'Zimbabwe',
    'Zuid-Afrika',
    'Zuid-Korea',
    'Zuid-Soedan',
    'Zweden',
    'Zwitserland',
    ],
  ];
}

// ===== Lezen en schrijven =====

function ledenLeegBestand() {
  return ['updated' => date('c'), 'volgnummer' => 0, 'leden' => []];
}

function ledenLees() {
  $pad = ledenBestandPad();
  if (!is_file($pad)) return ledenLeegBestand();
  $ruw = file_get_contents($pad);
  if ($ruw === false) return ledenLeegBestand();
  // De PHP-voorloopregel eraf halen: alles vanaf de eerste accolade is JSON.
  $start = strpos($ruw, '{');
  if ($start === false) return ledenLeegBestand();
  $json = json_decode(substr($ruw, $start), true);
  if (!is_array($json) || !isset($json['leden']) || !is_array($json['leden'])) {
    return ledenLeegBestand();
  }
  $json['volgnummer'] = isset($json['volgnummer']) ? (int) $json['volgnummer'] : 0;
  return $json;
}

// Tijdgestempelde kopie van het huidige bestand, zodat een verkeerde
// import of verwijdering terug te draaien is. Zelfde map en zelfde
// bewaartermijn als de back-ups van de andere databestanden.
function ledenMaakBackup($bewaardagen = 90, $maxAantal = 200) {
  $pad = ledenBestandPad();
  if (!is_file($pad)) return;
  $map = ledenBackupMap();
  if (!is_dir($map) && !@mkdir($map, 0755, true)) return;
  @copy($pad, $map . '/' . date('Ymd-His') . '_leden-data.php');

  $bestanden = @glob($map . '/*_leden-data.php');
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

function ledenSchrijf($data, $maakBackup = true) {
  if ($maakBackup) ledenMaakBackup();
  $data['updated'] = date('c');
  $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  if ($json === false) return false;
  return file_put_contents(ledenBestandPad(), LEDEN_VOORLOOP . $json, LOCK_EX) !== false;
}

// ===== Kleine hulpjes =====

function ledenKort($tekst, $max) {
  $tekst = trim(preg_replace('/\s+/u', ' ', (string) $tekst));
  if (function_exists('mb_substr')) return mb_substr($tekst, 0, $max, 'UTF-8');
  return substr($tekst, 0, $max);
}

function ledenNieuwId() {
  return 'lid_' . bin2hex(random_bytes(6));
}

function ledenVolgendNummer($data) {
  $hoogste = (int) ($data['volgnummer'] ?? 0);
  foreach ($data['leden'] as $lid) {
    $n = (int) ($lid['nummer'] ?? 0);
    if ($n > $hoogste) $hoogste = $n;
  }
  return $hoogste + 1;
}

function ledenVolledigeNaam($lid) {
  $delen = [];
  foreach (['voornaam', 'tussenvoegsel', 'achternaam'] as $veld) {
    $w = trim((string) ($lid[$veld] ?? ''));
    if ($w !== '') $delen[] = $w;
  }
  return implode(' ', $delen);
}

// Sorteernaam: achternaam voorop, tussenvoegsel achteraan zoals in een
// telefoonboek ("Berg, Jan van den").
function ledenSorteernaam($lid) {
  $achter = trim((string) ($lid['achternaam'] ?? ''));
  $voor = trim((string) ($lid['voornaam'] ?? ''));
  $tussen = trim((string) ($lid['tussenvoegsel'] ?? ''));
  $naam = $achter . ', ' . $voor;
  if ($tussen !== '') $naam .= ' ' . $tussen;
  return function_exists('mb_strtolower') ? mb_strtolower($naam, 'UTF-8') : strtolower($naam);
}

// Leeftijd in hele jaren op een peildatum (standaard vandaag).
function ledenLeeftijd($geboortedatum, $peildatum = null) {
  $geboortedatum = trim((string) $geboortedatum);
  if ($geboortedatum === '') return null;
  $geb = date_create($geboortedatum);
  if ($geb === false) return null;
  $peil = $peildatum === null ? new DateTime('today') : date_create($peildatum);
  if ($peil === false) return null;
  if ($geb > $peil) return null;
  return (int) $geb->diff($peil)->y;
}

// Jeugd of senior, op basis van de leeftijdsgrens uit de rekentabel.
// Peildatum is 1 januari van het contributiejaar: dat is hoe de club
// het jaar in gaat, en het voorkomt dat iemand halverwege het jaar van
// categorie wisselt.
function ledenIsJeugd($lid, $jeugdLeeftijdTot, $jaar) {
  $leeftijd = ledenLeeftijd($lid['geboortedatum'] ?? '', $jaar . '-01-01');
  if ($leeftijd === null) return null;
  return $leeftijd <= (int) $jeugdLeeftijdTot;
}

// ===== Invoer opschonen =====
// Elk veld krijgt een maximale lengte, zodat een verkeerde import of een
// kwaadwillende POST het bestand niet kan laten ontploffen.

function ledenVeldGrenzen() {
  return [
    'voornaam' => 60, 'tussenvoegsel' => 30, 'achternaam' => 80,
    'straat' => 100, 'huisnummer' => 20, 'postcode' => 20, 'gemeente' => 80, 'land' => 40,
    'telefoon' => 40, 'email' => 120,
    'opmerking' => 1000, 'taken' => 300, 'transponder' => 60, 'auto' => 120,
  ];
}

// Maakt van losse invoer een compleet, opgeschoond lid. Onbekende velden
// worden genegeerd. Geeft altijd dezelfde sleutels terug, in dezelfde
// volgorde, zodat het JSON-bestand leesbaar blijft.
//
// Belangrijk: bestuursfunctie en beheer_account zijn autorisatievelden. Ze
// worden hier centraal beschermd, zodat niet alleen het bewerkformulier maar
// ook CSV-import en toekomstige aanroepers dezelfde beveiligingsregel krijgen.
function ledenNormaliseer($invoer, $bestaand = null) {
  $lid = is_array($bestaand) ? $bestaand : [];
  $grenzen = ledenVeldGrenzen();

  foreach ($grenzen as $veld => $max) {
    if (array_key_exists($veld, $invoer)) {
      $waarde = ledenKort($invoer[$veld], $max);
      // In een opmerking mogen regeleinden blijven staan.
      if ($veld === 'opmerking' && isset($invoer['opmerking'])) {
        $waarde = trim((string) $invoer['opmerking']);
        $waarde = preg_replace('/\R/u', "\n", $waarde);
        $waarde = function_exists('mb_substr') ? mb_substr($waarde, 0, $max, 'UTF-8') : substr($waarde, 0, $max);
      }
      $lid[$veld] = $waarde;
    } elseif (!isset($lid[$veld])) {
      $lid[$veld] = '';
    }
  }

  if (array_key_exists('geboortedatum', $invoer)) {
    $lid['geboortedatum'] = ledenParseDatum($invoer['geboortedatum']);
  } elseif (!isset($lid['geboortedatum'])) {
    $lid['geboortedatum'] = '';
  }

  if (array_key_exists('inschrijfdatum', $invoer)) {
    $lid['inschrijfdatum'] = ledenParseDatum($invoer['inschrijfdatum']);
  } elseif (!isset($lid['inschrijfdatum'])) {
    $lid['inschrijfdatum'] = '';
  }

  if (array_key_exists('whatsapp', $invoer)) {
    $lid['whatsapp'] = ledenParseJaNee($invoer['whatsapp']);
  } elseif (!isset($lid['whatsapp'])) {
    $lid['whatsapp'] = false;
  }

  if (array_key_exists('nummer', $invoer)) {
    $n = (int) $invoer['nummer'];
    $lid['nummer'] = $n > 0 ? $n : (int) ($lid['nummer'] ?? 0);
  } elseif (!isset($lid['nummer'])) {
    $lid['nummer'] = 0;
  }

  $statussen = ledenStatussen();
  if (array_key_exists('status', $invoer) && isset($statussen[$invoer['status']])) {
    $lid['status'] = $invoer['status'];
  } elseif (!isset($lid['status']) || !isset($statussen[$lid['status']])) {
    $lid['status'] = 'nieuw';
  }

  // Autorisatievelden. Onbevoegde aanroepers mogen de bestaande waarden niet
  // veranderen; bij een nieuw lid blijven ze leeg. Dit blok zit vóór de rest
  // van de rolverwerking zodat een handgemaakte POST of CSV-kolom nooit via
  // een andere invoerroute om de beveiliging heen kan.
  if (ledenMagAutorisatieWijzigen()) {
    if (array_key_exists('beheer_account', $invoer)) {
      $lid['beheer_account'] = ledenKort($invoer['beheer_account'], 60);
    } elseif (!isset($lid['beheer_account'])) {
      $lid['beheer_account'] = '';
    }

    if (array_key_exists('bestuursfunctie', $invoer)) {
      $functie = trim((string) $invoer['bestuursfunctie']);
      if (!array_key_exists($functie, ledenBestuursfuncties())) {
        $functie = ledenBestuursfunctieUitTekst($functie);
      }
      $lid['bestuursfunctie'] = $functie;
    } elseif (!isset($lid['bestuursfunctie'])) {
      $lid['bestuursfunctie'] = '';
    }
  } else {
    if (is_array($bestaand)) {
      $lid['beheer_account'] = ledenKort($bestaand['beheer_account'] ?? '', 60);
      $bestaandeFunctie = trim((string) ($bestaand['bestuursfunctie'] ?? ''));
      $lid['bestuursfunctie'] = array_key_exists($bestaandeFunctie, ledenBestuursfuncties()) ? $bestaandeFunctie : '';
    } else {
      $lid['beheer_account'] = '';
      $lid['bestuursfunctie'] = '';
    }
  }

  // Commissies: een lijst sleutels. Uit het formulier komt een array met
  // vinkjes, uit een import een tekstveld met komma's ertussen. Of de
  // commissie ook echt bestaat wordt hier niet gecontroleerd, dat doet de
  // aanroeper die de commissielijst bij de hand heeft.
  if (array_key_exists('commissies', $invoer)) {
    $ruw = $invoer['commissies'];
    if (!is_array($ruw)) $ruw = preg_split('/[,;]/', (string) $ruw);
    $gekozen = [];
    foreach ($ruw as $item) {
      $sleutel = ledenCommissieSleutel($item);
      if ($sleutel !== '' && !in_array($sleutel, $gekozen, true)) $gekozen[] = $sleutel;
    }
    $lid['commissies'] = $gekozen;
  } elseif (!isset($lid['commissies']) || !is_array($lid['commissies'])) {
    $lid['commissies'] = [];
  }

  if (!isset($lid['id']) || $lid['id'] === '') $lid['id'] = ledenNieuwId();
  if (!isset($lid['contributie']) || !is_array($lid['contributie'])) $lid['contributie'] = [];
  if (!isset($lid['bron'])) $lid['bron'] = 'handmatig';
  if (!isset($lid['aangemaakt'])) $lid['aangemaakt'] = date('c');
  $lid['gewijzigd'] = date('c');

  return $lid;
}

// Zet een contributieregel voor één jaar. Bedragen worden op twee
// decimalen bewaard; een leeg bedrag betekent "nog niet vastgesteld".
function ledenZetContributie($lid, $jaar, $regel) {
  $jaar = (string) (int) $jaar;
  if ($jaar === '0') return $lid;
  $statussen = ledenContributieStatussen();
  $status = $regel['status'] ?? 'open';
  if (!isset($statussen[$status])) $status = 'open';

  $bedrag = $regel['bedrag'] ?? '';
  $inschrijfgeld = $regel['inschrijfgeld'] ?? '';

  $lid['contributie'][$jaar] = [
    'status'        => $status,
    'bedrag'        => ($bedrag === '' || $bedrag === null) ? null : round((float) $bedrag, 2),
    'inschrijfgeld' => ($inschrijfgeld === '' || $inschrijfgeld === null) ? null : round((float) $inschrijfgeld, 2),
    'betaald_op'    => ledenParseDatum($regel['betaald_op'] ?? ''),
    'opmerking'     => ledenKort($regel['opmerking'] ?? '', 300),
  ];
  ksort($lid['contributie']);
  return $lid;
}

// ===== Datums en ja/nee =====

// Accepteert 2026-08-08, 08-08-2026, 8/8/2026, 8 aug 2026, aaneengeschreven
// 08082026 (ddmmjjjj) en het serienummer dat Excel gebruikt (dagen sinds
// 30-12-1899).
function ledenParseDatum($waarde) {
  $waarde = trim((string) $waarde);
  if ($waarde === '') return '';

  if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $waarde, $m)) {
    if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) return '';
    return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
  }

  if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $waarde, $m)) {
    $dag = (int) $m[1]; $maand = (int) $m[2]; $jaar = (int) $m[3];
    if (checkdate($maand, $dag, $jaar)) {
      return sprintf('%04d-%02d-%02d', $jaar, $maand, $dag);
    }
    return '';
  }

  if (preg_match('/^\d{1,5}$/', $waarde)) {
    $serie = (int) $waarde;
    if ($serie >= 1 && $serie <= 60000) {
      $basis = date_create('1899-12-30');
      $basis->modify('+' . $serie . ' days');
      return $basis->format('Y-m-d');
    }
    return '';
  }

  if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2,4})$/', $waarde, $m)) {
    $dag = (int) $m[1]; $maand = (int) $m[2]; $jaar = (int) $m[3];
    if ($jaar < 100) $jaar += ($jaar > 50 ? 1900 : 2000);
    if (!checkdate($maand, $dag, $jaar)) return '';
    return sprintf('%04d-%02d-%02d', $jaar, $maand, $dag);
  }

  $maanden = ['jan' => 1, 'feb' => 2, 'mrt' => 3, 'maa' => 3, 'apr' => 4, 'mei' => 5, 'jun' => 6,
              'jul' => 7, 'aug' => 8, 'sep' => 9, 'okt' => 10, 'oct' => 10, 'nov' => 11, 'dec' => 12];
  if (preg_match('/^(\d{1,2})\s+([a-z]{3,})\.?\s+(\d{4})$/iu', $waarde, $m)) {
    $sleutel = strtolower(substr($m[2], 0, 3));
    if (isset($maanden[$sleutel]) && checkdate($maanden[$sleutel], (int) $m[1], (int) $m[3])) {
      return sprintf('%04d-%02d-%02d', (int) $m[3], $maanden[$sleutel], (int) $m[1]);
    }
  }

  return '';
}

function ledenGeldigeDatum($ymd) {
  $d = explode('-', $ymd);
  return count($d) === 3 && checkdate((int) $d[1], (int) $d[2], (int) $d[0]);
}

function ledenParseJaNee($waarde) {
  if (is_bool($waarde)) return $waarde;
  $w = strtolower(trim((string) $waarde));
  return in_array($w, ['1', 'ja', 'j', 'x', 'y', 'yes', 'true', 'waar', 'v'], true);
}

// ===== CSV inlezen =====

// De kolomnamen uit het Excel-bestand van de club, en wat er in het
// ledenbestand van wordt. Meerdere schrijfwijzen per veld, omdat de
// export van Excel niet altijd exact hetzelfde heet.
function ledenCsvKolommen() {
  return [
    'nummer'         => ['nummer', 'nr', 'lidnummer', 'nummer lid'],
    'voornaam'       => ['voornaam'],
    'tussenvoegsel'  => ['tussenvoegsel', 'tussen'],
    'achternaam'     => ['achternaam'],
    'geboortedatum'  => ['geboortedatum', 'geboorte datum', 'gebdatum'],
    'straat'         => ['straat', 'adres', 'straatnaam'],
    'huisnummer'     => ['huisnummer', 'huisnr', 'nr huis'],
    'postcode'       => ['postcode'],
    'gemeente'       => ['gemeente', 'woonplaats', 'plaats', 'stad'],
    'land'           => ['land'],
    'telefoon'       => ['telefoon', 'telefoon / whatsapp', 'telefoon/whatsapp', 'mobiel', 'tel'],
    'email'          => ['mailadres', 'email', 'e-mail', 'e-mailadres'],
    'contributiestatus' => ['contributie status', 'contributiestatus', 'status contributie'],
    'contributiebedrag' => ['contributiebedrag', 'contributie bedrag', 'bedrag'],
    'inschrijfgeld'  => ['inschrijfgeld', 'inschrijfkosten'],
    'inschrijfdatum' => ['inschrijfdatum', 'datum inschrijving'],
    'opmerking'      => ['opmerking', 'opmerkingen', 'notitie'],
    'taken'          => ['taken', 'taak'],
    'whatsapp'       => ['toegevoegd whatsapp', 'whatsapp toegevoegd', 'in whatsapp'],
    'transponder'    => ['transponder', 'transpondernummer'],
    'auto'           => ['auto', "auto's", 'wagen'],
    'status'         => ['status', 'lidstatus'],
    'bestuursfunctie' => ['bestuursfunctie', 'bestuur', 'rol', 'functie'],
    'commissies'     => ['commissies', 'commissie'],
    // Deze twee staan wel in het Excel-bestand maar worden niet opgeslagen:
    // leeftijd en jeugd/senior rekent de beheerpagina zelf uit de geboorte-
    // datum en de rekentabel. Ze staan hier alleen zodat de importcontrole
    // ze kan melden als "wordt berekend" in plaats van "niet herkend".
    '_berekend'      => ['leeftijd', 'jeugdlid', 'jeugd', 'senior'],
  ];
}

// Splitst "Dorpstraat 12 a" in straat en huisnummer. Het Excel-bestand
// van de club heeft geen apart huisnummerveld, het aanmeldformulier wel.
function ledenSplitsAdres($straat, $huisnummer) {
  $straat = trim((string) $straat);
  $huisnummer = trim((string) $huisnummer);
  if ($huisnummer !== '' || $straat === '') return [$straat, $huisnummer];
  if (preg_match('/^(.*?)[,\s]+(\d+\s*[a-zA-Z]?(?:\s*[-\/]\s*\d+\s*[a-zA-Z]?)?)$/u', $straat, $m)) {
    $naam = trim($m[1]);
    if ($naam !== '') return [$naam, trim($m[2])];
  }
  return [$straat, $huisnummer];
}

// "Contributie 2026 status" -> veld contributiestatus, jaar 2026.
// Geeft [veldnaam, jaar] terug, of [null, null] als de kolom onbekend is.
function ledenCsvKolomHerkennen($kop) {
  $kop = strtolower(trim((string) $kop));
  $kop = preg_replace('/\s+/u', ' ', $kop);
  $kop = str_replace(['_', '.'], ' ', $kop);
  $jaar = null;
  if (preg_match('/\b(20\d{2})\b/', $kop, $m)) {
    $jaar = (int) $m[1];
    $kop = trim(preg_replace('/\b20\d{2}\b/', '', $kop));
    $kop = preg_replace('/\s+/u', ' ', $kop);
  }
  foreach (ledenCsvKolommen() as $veld => $namen) {
    if (in_array($kop, $namen, true)) return [$veld, $jaar];
  }
  // "contributie" met een jaartal en zonder verder woord: dat is het bedrag.
  if ($kop === 'contributie' && $jaar !== null) return ['contributiebedrag', $jaar];
  return [null, null];
}

// Leest een CSV en geeft ['kolommen' => [...], 'rijen' => [...]] terug.
// Puntkomma en komma worden allebei herkend, net als een bestand dat
// Excel in Windows-codering heeft weggeschreven.
function ledenCsvLezen($inhoud) {
  // Byte order mark eraf.
  if (substr($inhoud, 0, 3) === "\xEF\xBB\xBF") $inhoud = substr($inhoud, 3);

  // Excel op Windows schrijft vaak in CP1252. Als de inhoud geen geldige
  // UTF-8 is, gaan we daarvan uit en zetten we het om.
  if (!ledenIsUtf8($inhoud)) {
    if (function_exists('iconv')) {
      $om = @iconv('Windows-1252', 'UTF-8//TRANSLIT', $inhoud);
      if ($om !== false) $inhoud = $om;
    } elseif (function_exists('mb_convert_encoding')) {
      $inhoud = mb_convert_encoding($inhoud, 'UTF-8', 'Windows-1252');
    }
  }

  $inhoud = preg_replace('/\r\n?/', "\n", $inhoud);
  $eersteRegel = strtok($inhoud, "\n");
  if ($eersteRegel === false) return ['kolommen' => [], 'rijen' => []];
  $scheiding = (substr_count($eersteRegel, ';') >= substr_count($eersteRegel, ',')) ? ';' : ',';

  $handle = fopen('php://temp', 'r+');
  fwrite($handle, $inhoud);
  rewind($handle);

  $kop = fgetcsv($handle, 0, $scheiding);
  if ($kop === false) { fclose($handle); return ['kolommen' => [], 'rijen' => []]; }

  $toewijzing = [];
  $kolommen = [];
  foreach ($kop as $i => $naam) {
    list($veld, $jaar) = ledenCsvKolomHerkennen($naam);
    $toewijzing[$i] = [$veld, $jaar];
    $kolommen[] = ['kop' => ledenKort($naam, 60), 'veld' => $veld, 'jaar' => $jaar];
  }

  $rijen = [];
  while (($rij = fgetcsv($handle, 0, $scheiding)) !== false) {
    if (count($rij) === 1 && trim((string) $rij[0]) === '') continue;
    $waarden = [];
    $contributie = [];
    foreach ($rij as $i => $waarde) {
      if (!isset($toewijzing[$i])) continue;
      list($veld, $jaar) = $toewijzing[$i];
      if ($veld === null || $veld === '_berekend') continue;
      $waarde = trim((string) $waarde);
      if (in_array($veld, ['contributiestatus', 'contributiebedrag', 'inschrijfgeld'], true)) {
        $j = $jaar === null ? (int) date('Y') : $jaar;
        if (!isset($contributie[$j])) $contributie[$j] = [];
        $contributie[$j][$veld] = $waarde;
      } else {
        $waarden[$veld] = $waarde;
      }
    }
    if (ledenVolledigeNaam($waarden) === '' && ($waarden['email'] ?? '') === '') continue;
    list($waarden['straat'], $waarden['huisnummer']) =
      ledenSplitsAdres($waarden['straat'] ?? '', $waarden['huisnummer'] ?? '');
    $waarden['_contributie'] = $contributie;
    $rijen[] = $waarden;
  }
  fclose($handle);

  return ['kolommen' => $kolommen, 'rijen' => $rijen];
}

function ledenIsUtf8($tekst) {
  if (function_exists('mb_check_encoding')) return mb_check_encoding($tekst, 'UTF-8');
  return (bool) preg_match('//u', $tekst);
}

// Zet de tekstuele contributiestatus uit Excel om naar een sleutel.
function ledenContributieStatusUitTekst($tekst) {
  $t = strtolower(trim((string) $tekst));
  if ($t === '') return 'open';
  if (strpos($t, 'betaald') !== false || strpos($t, 'voldaan') !== false || $t === 'ja' || $t === 'x') return 'betaald';
  if (strpos($t, 'kwijt') !== false || strpos($t, 'vrijgesteld') !== false) return 'kwijtgescholden';
  if (strpos($t, 'verval') !== false || strpos($t, 'opgezegd') !== false || strpos($t, 'gestopt') !== false) return 'vervallen';
  return 'open';
}

// Zoekt een bestaand lid bij een importregel. Geeft de index terug plus
// waarop de herkenning is gebaseerd, zodat de importcontrole kan laten
// zien waarom een regel als "bijgewerkt" wordt aangemerkt.
//
// De volgorde loopt van hard naar zacht bewijs:
//   1. mailadres              uniek genoeg om op zichzelf te staan
//   2. lidnummer plus naam    allebei gelijk is sterk; alleen het nummer
//                             niet, want een handmatig toegevoegd lid en
//                             een importregel kunnen per ongeluk hetzelfde
//                             nummer hebben terwijl het twee mensen zijn
//   3. naam plus geboortedatum
//   4. alleen de naam         alleen als die maar één keer voorkomt en er
//                             niets tegenspreekt (zie hieronder)
//
// Die vierde stap zat er eerst niet in. Gevolg: een lid zonder mailadres
// én zonder geboortedatum werd bij een tweede import niet herkend en kwam
// er een tweede keer bij te staan, ook bij een export die je meteen weer
// inleest. Om te voorkomen dat twee verschillende mensen met dezelfde naam
// samengevoegd worden, telt die stap alleen als er precies één lid met die
// naam is en geen van de andere gegevens elkaar tegenspreekt.
function ledenZoekBestaandeMet($data, $kandidaat) {
  $geen = ['index' => null, 'reden' => ''];
  $email  = strtolower(trim((string) ($kandidaat['email'] ?? '')));
  $naam   = strtolower(ledenVolledigeNaam($kandidaat));
  $geb    = trim((string) ($kandidaat['geboortedatum'] ?? ''));
  $nummer = (int) ($kandidaat['nummer'] ?? 0);

  if ($email !== '') {
    foreach ($data['leden'] as $i => $lid) {
      $lidEmail = strtolower(trim((string) ($lid['email'] ?? '')));
      if ($lidEmail !== '' && $lidEmail === $email) return ['index' => $i, 'reden' => 'mailadres'];
    }
  }

  if ($naam === '') return $geen;

  if ($nummer > 0) {
    foreach ($data['leden'] as $i => $lid) {
      if ((int) ($lid['nummer'] ?? 0) !== $nummer) continue;
      if (strtolower(ledenVolledigeNaam($lid)) === $naam) return ['index' => $i, 'reden' => 'lidnummer en naam'];
    }
  }

  if ($geb !== '') {
    foreach ($data['leden'] as $i => $lid) {
      if (strtolower(ledenVolledigeNaam($lid)) === $naam && trim((string) ($lid['geboortedatum'] ?? '')) === $geb) {
        return ['index' => $i, 'reden' => 'naam en geboortedatum'];
      }
    }
  }

  $treffers = [];
  foreach ($data['leden'] as $i => $lid) {
    if (strtolower(ledenVolledigeNaam($lid)) === $naam) $treffers[] = $i;
  }
  // Meer dan één naamgenoot: dan is de naam alleen geen bewijs meer en
  // wordt de regel als nieuw lid behandeld. Beter een dubbele regel die je
  // ziet staan dan stilletjes de verkeerde persoon overschrijven.
  if (count($treffers) !== 1) return $geen;

  $lid = $data['leden'][$treffers[0]];
  $lidEmail  = strtolower(trim((string) ($lid['email'] ?? '')));
  $lidGeb    = trim((string) ($lid['geboortedatum'] ?? ''));
  $lidNummer = (int) ($lid['nummer'] ?? 0);
  if ($email !== '' && $lidEmail !== '' && $email !== $lidEmail) return $geen;
  if ($geb !== '' && $lidGeb !== '' && $geb !== $lidGeb) return $geen;
  if ($nummer > 0 && $lidNummer > 0 && $nummer !== $lidNummer) return $geen;

  return ['index' => $treffers[0], 'reden' => 'naam'];
}

// Alleen de index, voor de plekken die verder niets met de reden doen.
function ledenZoekBestaande($data, $kandidaat) {
  return ledenZoekBestaandeMet($data, $kandidaat)['index'];
}
