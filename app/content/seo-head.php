<?php
// ============================================================
// SEO-head: titel en meta-tags per taal, server-side
// ============================================================
// Standalone RC045 behoudt de bestaande site-seo.php. Externe tenants krijgen
// neutrale platform-SEO op basis van hun eigen verenigingsnaam, tenzij zij via
// hun server-only configuratie expliciete seo.paginas-overrides aanleveren.
// Bestaande RC045-functienamen blijven voorlopig als compatibiliteitslaag.
// ============================================================

require_once dirname(__DIR__) . '/core/site.php';
require_once __DIR__ . '/content-pagina.php';
require_once __DIR__ . '/content-pagina-runtime.php';

function siteSeoNeutraleDefinities(): array
{
  $naam = siteNaam();
  return [
    'index' => [
      'pad' => '/',
      'nl' => ['titel' => $naam, 'omschrijving' => 'Officiële website van ' . $naam . '.'],
      'en' => ['titel' => $naam, 'omschrijving' => 'Official website of ' . $naam . '.'],
      'de' => ['titel' => $naam, 'omschrijving' => 'Offizielle Website von ' . $naam . '.'],
    ],
    'aanmelden' => [
      'pad' => '/aanmelden.html',
      'nl' => ['titel' => 'Aanmelden – ' . $naam, 'omschrijving' => 'Bekijk hoe je je kunt aanmelden bij ' . $naam . '.'],
      'en' => ['titel' => 'Join – ' . $naam, 'omschrijving' => 'Find out how to join ' . $naam . '.'],
      'de' => ['titel' => 'Mitglied werden – ' . $naam, 'omschrijving' => 'Informationen zur Anmeldung bei ' . $naam . '.'],
    ],
    'ontstaan' => [
      'pad' => '/ontstaan.html',
      'nl' => ['titel' => 'Over ons – ' . $naam, 'omschrijving' => 'Lees meer over de geschiedenis van ' . $naam . '.'],
      'en' => ['titel' => 'About us – ' . $naam, 'omschrijving' => 'Read more about the history of ' . $naam . '.'],
      'de' => ['titel' => 'Über uns – ' . $naam, 'omschrijving' => 'Erfahren Sie mehr über die Geschichte von ' . $naam . '.'],
    ],
    'baanreglement' => [
      'pad' => '/baanreglement.html',
      'nl' => ['titel' => 'Reglement – ' . $naam, 'omschrijving' => 'Bekijk de afspraken en het reglement van ' . $naam . '.'],
      'en' => ['titel' => 'Rules – ' . $naam, 'omschrijving' => 'View the rules and agreements of ' . $naam . '.'],
      'de' => ['titel' => 'Reglement – ' . $naam, 'omschrijving' => 'Regeln und Vereinbarungen von ' . $naam . '.'],
    ],
    'media' => [
      'pad' => '/media.html',
      'nl' => ['titel' => 'Media – ' . $naam, 'omschrijving' => 'Media en publicaties van ' . $naam . '.'],
      'en' => ['titel' => 'Media – ' . $naam, 'omschrijving' => 'Media and publications from ' . $naam . '.'],
      'de' => ['titel' => 'Medien – ' . $naam, 'omschrijving' => 'Medien und Veröffentlichungen von ' . $naam . '.'],
    ],
    'fotoboek' => [
      'pad' => '/fotoboek.html',
      'nl' => ['titel' => 'Fotoboek – ' . $naam, 'omschrijving' => 'Bekijk fotoalbums van ' . $naam . '.'],
      'en' => ['titel' => 'Photo album – ' . $naam, 'omschrijving' => 'View photo albums from ' . $naam . '.'],
      'de' => ['titel' => 'Fotoalbum – ' . $naam, 'omschrijving' => 'Fotoalben von ' . $naam . ' ansehen.'],
    ],
    'bedankt' => [
      'pad' => '/bedankt.html',
      'nl' => ['titel' => 'Bedankt – ' . $naam, 'omschrijving' => 'Je aanmelding bij ' . $naam . ' is ontvangen.'],
      'en' => ['titel' => 'Thank you – ' . $naam, 'omschrijving' => 'Your application to ' . $naam . ' has been received.'],
      'de' => ['titel' => 'Vielen Dank – ' . $naam, 'omschrijving' => 'Ihre Anmeldung bei ' . $naam . ' wurde empfangen.'],
    ],
  ];
}

function siteSeoDefinitiesVoorRuntime(): array
{
  if (tenantRuntimePrivateRoot(siteConfig()) === null) {
    $legacy = require dirname(__DIR__) . '/core/site-seo.php';
    return is_array($legacy) ? $legacy : [];
  }

  $basis = siteSeoNeutraleDefinities();
  $override = siteConfigGet('seo.paginas', []);
  return is_array($override) ? array_replace_recursive($basis, $override) : $basis;
}

$RC045_SITE = siteUrl();
$RC045_TALEN = siteTalen();
$RC045_PAGINAS = siteSeoDefinitiesVoorRuntime();

function rc045Taal() {
  global $RC045_TALEN;
  $standaard = siteStandaardTaal();
  $taal = (isset($_GET['lang']) && is_string($_GET['lang'])) ? $_GET['lang'] : $standaard;
  return isset($RC045_TALEN[$taal]) ? $taal : $standaard;
}

function rc045Url($pagina, $taal) {
  global $RC045_SITE, $RC045_PAGINAS;
  if (!isset($RC045_PAGINAS[$pagina]['pad'])) return $RC045_SITE . '/';
  $pad = $RC045_PAGINAS[$pagina]['pad'];
  $standaard = siteStandaardTaal();
  return $RC045_SITE . $pad . ($taal === $standaard ? '' : (strpos($pad, '?') === false ? '?' : '&') . 'lang=' . $taal);
}

function rc045SeoHead($pagina, $indexeerbaar = true) {
  global $RC045_PAGINAS, $RC045_TALEN;

  if (contentPaginaBestaat((string) $pagina)) {
    $pagina = contentPaginaSeoSleutel((string) $pagina);
  }

  if (!isset($RC045_PAGINAS[$pagina])) return;
  $taal = rc045Taal();
  if (!isset($RC045_PAGINAS[$pagina][$taal])) return;

  $p = $RC045_PAGINAS[$pagina][$taal];
  $titel = htmlspecialchars((string)$p['titel'], ENT_QUOTES, 'UTF-8');
  $omschrijving = htmlspecialchars((string)$p['omschrijving'], ENT_QUOTES, 'UTF-8');
  $url = htmlspecialchars(rc045Url($pagina, $taal), ENT_QUOTES, 'UTF-8');
  $socialAsset = siteAsset('branding.social_image');
  $afbeelding = $socialAsset !== '' ? htmlspecialchars(siteUrl() . '/' . $socialAsset, ENT_QUOTES, 'UTF-8') : '';

  echo "  <title>$titel</title>\n";
  foreach (array_keys($RC045_TALEN) as $t) {
    if (!isset($RC045_PAGINAS[$pagina][$t])) continue;
    $liveTitel = htmlspecialchars((string)$RC045_PAGINAS[$pagina][$t]['titel'], ENT_QUOTES, 'UTF-8');
    echo "  <meta name=\"rc045-title-$t\" content=\"$liveTitel\">\n";
  }
  echo "  <meta name=\"description\" id=\"meta-description\" content=\"$omschrijving\">\n";
  echo "  <meta property=\"og:title\" content=\"$titel\">\n";
  echo "  <meta property=\"og:description\" content=\"$omschrijving\">\n";
  if ($afbeelding !== '') echo "  <meta property=\"og:image\" content=\"$afbeelding\">\n";
  echo "  <meta property=\"og:url\" content=\"$url\">\n";
  echo "  <meta property=\"og:type\" content=\"website\">\n";
  echo "  <meta property=\"og:locale\" content=\"{$RC045_TALEN[$taal]}\">\n";
  foreach ($RC045_TALEN as $t => $locale) {
    if ($t !== $taal) echo "  <meta property=\"og:locale:alternate\" content=\"$locale\">\n";
  }
  echo "  <meta name=\"twitter:card\" content=\"summary_large_image\">\n";
  echo "  <meta name=\"twitter:title\" content=\"$titel\">\n";
  echo "  <meta name=\"twitter:description\" content=\"$omschrijving\">\n";
  if ($afbeelding !== '') echo "  <meta name=\"twitter:image\" content=\"$afbeelding\">\n";
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
