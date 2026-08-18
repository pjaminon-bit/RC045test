<?php
// ============================================================
// SEO-head: titel en meta-tags per taal, server-side
// ============================================================
// Algemene sitegegevens (domein, talen en social image) komen uit de centrale
// verenigingsconfiguratie. De bestaande RC045-functienamen blijven in fase 1
// bewust bestaan als compatibiliteitslaag, zodat de publieke pagina's niet in
// één grote wijziging hoeven te worden aangepast.
// ============================================================

require_once __DIR__ . '/site.php';

// Tijdelijke compatibiliteitsvariabelen. Bestaande code kan hierdoor blijven
// werken terwijl de bron al generiek is gemaakt.
$RC045_SITE = siteUrl();
$RC045_TALEN = siteTalen();

function rc045Taal() {
  global $RC045_TALEN;
  $standaard = siteStandaardTaal();
  $taal = (isset($_GET['lang']) && is_string($_GET['lang'])) ? $_GET['lang'] : $standaard;
  return isset($RC045_TALEN[$taal]) ? $taal : $standaard;
}

// De inhoud van deze pagina-SEO is in fase 1 nog RC045-specifiek. In een
// volgende stap verplaatsen we ook deze content naar configureerbare pagina-
// metadata; eerst scheiden we veilig de technische site-identiteit.
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

function rc045Url($pagina, $taal) {
  global $RC045_SITE, $RC045_PAGINAS;
  $pad = $RC045_PAGINAS[$pagina]['pad'];
  $standaard = siteStandaardTaal();
  return $RC045_SITE . $pad . ($taal === $standaard ? '' : (strpos($pad, '?') === false ? '?' : '&') . 'lang=' . $taal);
}

function rc045SeoHead($pagina, $indexeerbaar = true) {
  global $RC045_PAGINAS, $RC045_TALEN, $RC045_SITE;

  if (!isset($RC045_PAGINAS[$pagina])) return;
  $taal = rc045Taal();
  if (!isset($RC045_PAGINAS[$pagina][$taal])) return;

  $p = $RC045_PAGINAS[$pagina][$taal];
  $titel = htmlspecialchars($p['titel'], ENT_QUOTES, 'UTF-8');
  $omschrijving = htmlspecialchars($p['omschrijving'], ENT_QUOTES, 'UTF-8');
  $url = htmlspecialchars(rc045Url($pagina, $taal), ENT_QUOTES, 'UTF-8');
  $afbeelding = htmlspecialchars(siteAssetUrl('branding.social_image'), ENT_QUOTES, 'UTF-8');

  echo "  <title>$titel</title>\n";
  foreach (array_keys($RC045_TALEN) as $t) {
    if (!isset($RC045_PAGINAS[$pagina][$t])) continue;
    $liveTitel = htmlspecialchars($RC045_PAGINAS[$pagina][$t]['titel'], ENT_QUOTES, 'UTF-8');
    // Naam blijft tijdelijk rc045-title-* vanwege bestaande JavaScript-koppeling.
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
    if (!isset($RC045_PAGINAS[$pagina][$t])) continue;
    $h = htmlspecialchars(rc045Url($pagina, $t), ENT_QUOTES, 'UTF-8');
    echo "  <link rel=\"alternate\" hreflang=\"$t\" href=\"$h\">\n";
  }
  $standaard = htmlspecialchars(rc045Url($pagina, siteStandaardTaal()), ENT_QUOTES, 'UTF-8');
  echo "  <link rel=\"alternate\" hreflang=\"x-default\" href=\"$standaard\">\n";
}
