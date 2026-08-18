<?php
// ============================================================
// RC045 SEO-head: titel en meta-tags per taal, server-side
// ============================================================
// De site is drietalig via ?lang=en en ?lang=de, maar het wisselen van taal
// gebeurt in de browser met JavaScript. Voor bezoekers werkt dat prima, maar
// niet voor de partijen die de pagina alleen als kale HTML opvragen:
//
//   - Facebook, WhatsApp en LinkedIn draaien geen JavaScript. Zij lazen dus
//     altijd de Nederlandse og:title, og:description en og:locale, en een
//     og:url die naar de Nederlandse homepage wees. Alle drie de taalversies
//     vielen bij die platforms samen tot een Nederlandse preview.
//   - Google voert JavaScript wel uit, maar in een tweede ronde zonder
//     garantie, en raadt af om rel=canonical via JavaScript te zetten. De
//     <title> werd bovendien helemaal nooit aangepast, ook niet in de browser.
//
// Daarom staan deze tags nu in PHP: ze komen meteen goed uit de server, in de
// taal die in de URL staat. De rest van de pagina blijft gewoon door
// JavaScript vertaald worden. Dat mag, want Google rendert dat wel en de
// social scrapers kijken alleen naar de head.
//
// Gebruik, als allereerste regel van een pagina (vóór <!DOCTYPE>):
//   require_once __DIR__ . '/seo-head.php';
// en in de head:
//   rc045SeoHead('aanmelden');
//
// Let op: de bestanden heten .php, maar de URL's blijven .html dankzij een
// interne rewrite in .htaccess. De paden hieronder houden dus bewust .html
// aan; dat is wat er in Google staat en wat mensen delen.
// ============================================================

$RC045_SITE = 'https://rc045.nl';

// De taal die de tags bepalen. Alleen deze drie, alles anders wordt
// Nederlands: een onbekende ?lang= mag geen halve pagina opleveren.
$RC045_TALEN = ['nl' => 'nl_NL', 'en' => 'en_GB', 'de' => 'de_DE'];

function rc045Taal() {
  global $RC045_TALEN;
  // is_string() erbij: bij ?lang[]=x is $_GET['lang'] een array, en een cast
  // daarvan geeft een PHP-waarschuwing midden in de head.
  $taal = (isset($_GET['lang']) && is_string($_GET['lang'])) ? $_GET['lang'] : 'nl';
  return isset($RC045_TALEN[$taal]) ? $taal : 'nl';
}

// Per pagina het pad (zoals het in Google staat) en per taal een titel en een
// omschrijving. De omschrijving wordt gebruikt voor de meta description en
// voor het deelbericht op social media; die stonden eerder los van elkaar,
// waarbij het deelbericht op elke pagina dezelfde algemene clubtekst was.
$RC045_PAGINAS = [

  'index' => [
    'pad' => '/',
    'nl' => [
      'titel' => 'RC045 – Bashers of the South',
      'omschrijving' => 'Gezellige RC-vereniging in Zuid-Limburg voor elektrisch aangedreven, radiografisch bestuurbare auto\'s. Eigen baan in Eygelshoven, voor jong en oud.',
    ],
    'en' => [
      'titel' => 'RC045 – Bashers of the South | RC car club in South Limburg',
      'omschrijving' => 'Friendly RC car club in South Limburg for electric radio controlled cars. Our own track in Eygelshoven, for beginners and experienced drivers.',
    ],
    'de' => [
      'titel' => 'RC045 – Bashers of the South | RC-Car-Verein in Süd-Limburg',
      'omschrijving' => 'Geselliger RC-Car-Verein in Süd-Limburg für ferngesteuerte Elektroautos. Eigene Bahn in Eygelshoven nahe Aachen, für Jung und Alt.',
    ],
  ],

  'aanmelden' => [
    'pad' => '/aanmelden.html',
    'nl' => [
      'titel' => 'Aanmelden – RC045 Bashers of the South',
      'omschrijving' => 'Lid worden van RC045 in Eygelshoven. Bekijk de contributie, de voorwaarden en meld je online aan bij onze RC-vereniging in Zuid-Limburg.',
    ],
    'en' => [
      'titel' => 'Join us – RC045 Bashers of the South',
      'omschrijving' => 'Become a member of RC045 in Eygelshoven. Check the membership fees and conditions and sign up online with our RC car club in South Limburg.',
    ],
    'de' => [
      'titel' => 'Mitglied werden – RC045 Bashers of the South',
      'omschrijving' => 'Mitglied werden bei RC045 in Eygelshoven. Beitrag und Bedingungen ansehen und sich online bei unserem RC-Car-Verein in Süd-Limburg anmelden.',
    ],
  ],

  'ontstaan' => [
    'pad' => '/ontstaan.html',
    'nl' => [
      'titel' => 'Het ontstaan – RC045 Bashers of the South',
      'omschrijving' => 'Hoe RC045 Bashers of the South is ontstaan: van een groepje hobbyisten tot een eigen baan in Eygelshoven, Zuid-Limburg.',
    ],
    'en' => [
      'titel' => 'Our story – RC045 Bashers of the South',
      'omschrijving' => 'How RC045 Bashers of the South came about: from a small group of hobbyists to a track of our own in Eygelshoven, South Limburg.',
    ],
    'de' => [
      'titel' => 'Die Entstehung – RC045 Bashers of the South',
      'omschrijving' => 'Wie RC045 Bashers of the South entstanden ist: von einer kleinen Gruppe Hobbyisten bis zur eigenen Bahn in Eygelshoven, Süd-Limburg.',
    ],
  ],

  'baanreglement' => [
    'pad' => '/baanreglement.html',
    'nl' => [
      'titel' => 'Baanreglement – RC045 Bashers of the South',
      'omschrijving' => 'Het baanreglement van RC045 in Eygelshoven: afspraken over veiligheid, rijgedrag, geluid en gebruik van de baan.',
    ],
    'en' => [
      'titel' => 'Track rules – RC045 Bashers of the South',
      'omschrijving' => 'The track rules of RC045 in Eygelshoven: agreements on safety, driving conduct, noise and use of the track.',
    ],
    'de' => [
      'titel' => 'Bahnordnung – RC045 Bashers of the South',
      'omschrijving' => 'Die Bahnordnung von RC045 in Eygelshoven: Vereinbarungen zu Sicherheit, Fahrverhalten, Lärm und Nutzung der Bahn.',
    ],
  ],

  'media' => [
    'pad' => '/media.html',
    'nl' => [
      'titel' => 'Media – RC045 Bashers of the South',
      'omschrijving' => 'RC045 in de media: interviews en artikelen van Omroep Landgraaf, ZO-NWS en L1 over onze RC-autoclub in Zuid-Limburg.',
    ],
    'en' => [
      'titel' => 'In the media – RC045 Bashers of the South',
      'omschrijving' => 'RC045 in the media: interviews and articles by Omroep Landgraaf, ZO-NWS and L1 about our RC car club in South Limburg.',
    ],
    'de' => [
      'titel' => 'In den Medien – RC045 Bashers of the South',
      'omschrijving' => 'RC045 in den Medien: Interviews und Artikel von Omroep Landgraaf, ZO-NWS und L1 über unseren RC-Car-Verein in Süd-Limburg.',
    ],
  ],

  'fotoboek' => [
    'pad' => '/fotoboek.html',
    'nl' => [
      'titel' => 'Fotoboek – RC045 Bashers of the South',
      'omschrijving' => 'Bekijk foto\'s van RC045 evenementen en onze banen, gerangschikt per album.',
    ],
    'en' => [
      'titel' => 'Photo album – RC045 Bashers of the South',
      'omschrijving' => 'Photos of RC045 events and our tracks, sorted by album.',
    ],
    'de' => [
      'titel' => 'Fotoalbum – RC045 Bashers of the South',
      'omschrijving' => 'Fotos von RC045-Veranstaltungen und unseren Bahnen, nach Album sortiert.',
    ],
  ],

  // Staat op noindex (zie de robots-tag in bedankt.php) en hoort dus niet in
  // Google. De titel en de omschrijving staan hier toch, zodat een Duitse of
  // Engelse bezoeker na het aanmelden geen Nederlandse tabtitel krijgt.
  'bedankt' => [
    'pad' => '/bedankt.html',
    'nl' => [
      'titel' => 'Bedankt! – RC045 Bashers of the South',
      'omschrijving' => 'Je aanmelding bij RC045 Bashers of the South is verstuurd. Het bestuur neemt zo snel mogelijk contact met je op.',
    ],
    'en' => [
      'titel' => 'Thank you! – RC045 Bashers of the South',
      'omschrijving' => 'Your application to RC045 Bashers of the South has been sent. The board will get in touch with you as soon as possible.',
    ],
    'de' => [
      'titel' => 'Vielen Dank! – RC045 Bashers of the South',
      'omschrijving' => 'Deine Anmeldung bei RC045 Bashers of the South ist verschickt. Der Vorstand meldet sich so schnell wie möglich bei dir.',
    ],
  ],

];

// De volledige URL van een pagina in een taal. Nederlands is de kale URL
// zonder parameter, zodat de bestaande, geindexeerde adressen niet wijzigen.
function rc045Url($pagina, $taal) {
  global $RC045_SITE, $RC045_PAGINAS;
  $pad = $RC045_PAGINAS[$pagina]['pad'];
  return $RC045_SITE . $pad . ($taal === 'nl' ? '' : (strpos($pad, '?') === false ? '?' : '&') . 'lang=' . $taal);
}

// Schrijft titel, meta description, de og- en twitter-tags, de canonical en de
// hreflang-verwijzingen uit voor de gevraagde pagina in de huidige taal.
// De id's op de description en de canonical blijven staan: setLang() in de
// pagina's grijpt daarnaar bij een taalwissel zonder herladen.
// $indexeerbaar op false laat de canonical en de hreflang-verwijzingen weg.
// Dat is voor pagina's met een noindex-tag: hreflang beschrijft een groep
// pagina's die als taalvarianten geindexeerd moeten worden, en die groep valt
// uit elkaar zodra de leden op noindex staan. Die tags suggereren dan iets wat
// er niet is. De og-tags blijven wel staan: die gebruiken de social scrapers,
// en die trekken zich niets van noindex aan.
function rc045SeoHead($pagina, $indexeerbaar = true) {
  global $RC045_PAGINAS, $RC045_TALEN, $RC045_SITE;

  if (!isset($RC045_PAGINAS[$pagina])) return; // onbekende pagina: liever niets dan een waarschuwing in de head
  $taal = rc045Taal();
  $p = $RC045_PAGINAS[$pagina][$taal];
  $titel = htmlspecialchars($p['titel'], ENT_QUOTES, 'UTF-8');
  $omschrijving = htmlspecialchars($p['omschrijving'], ENT_QUOTES, 'UTF-8');
  $url = htmlspecialchars(rc045Url($pagina, $taal), ENT_QUOTES, 'UTF-8');
  $afbeelding = $RC045_SITE . '/rc045-logo.png';

  echo "  <title>$titel</title>\n";
  // De browser kan zonder reload van taal wisselen. Geef het gedeelde
  // taalscript daarom de drie server-side titels mee; dit verandert niets
  // aan SEO, maar houdt de tabtitel wel synchroon met de gekozen taal.
  foreach (array_keys($RC045_TALEN) as $t) {
    $liveTitel = htmlspecialchars($RC045_PAGINAS[$pagina][$t]['titel'], ENT_QUOTES, 'UTF-8');
    echo "  <meta name=\"rc045-title-$t\" content=\"$liveTitel\">\n";
  }
  echo "  <meta name=\"description\" id=\"meta-description\" content=\"$omschrijving\">\n";
  echo "  <meta property=\"og:title\" content=\"$titel\">\n";
  echo "  <meta property=\"og:description\" content=\"$omschrijving\">\n";
  echo "  <meta property=\"og:image\" content=\"$afbeelding\">\n";
  echo "  <meta property=\"og:url\" content=\"$url\">\n";
  echo "  <meta property=\"og:type\" content=\"website\">\n";
  echo "  <meta property=\"og:locale\" content=\"{$RC045_TALEN[$taal]}\">\n";
  foreach ($RC045_TALEN as $t => $locale) {
    if ($t !== $taal) echo "  <meta property=\"og:locale:alternate\" content=\"$locale\">\n";
  }
  echo "  <meta name=\"twitter:card\" content=\"summary_large_image\">\n";
  echo "  <meta name=\"twitter:title\" content=\"$titel\">\n";
  echo "  <meta name=\"twitter:description\" content=\"$omschrijving\">\n";
  echo "  <meta name=\"twitter:image\" content=\"$afbeelding\">\n";
  if (!$indexeerbaar) return;

  echo "  <link rel=\"canonical\" href=\"$url\" id=\"canonical-link\">\n";
  foreach (array_keys($RC045_TALEN) as $t) {
    $h = htmlspecialchars(rc045Url($pagina, $t), ENT_QUOTES, 'UTF-8');
    echo "  <link rel=\"alternate\" hreflang=\"$t\" href=\"$h\">\n";
  }
  $standaard = htmlspecialchars(rc045Url($pagina, 'nl'), ENT_QUOTES, 'UTF-8');
  echo "  <link rel=\"alternate\" hreflang=\"x-default\" href=\"$standaard\">\n";
}
