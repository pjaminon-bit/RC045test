<?php
// ============================================================
// SEO-head: titel en meta-tags per taal, server-side
// ============================================================
// Technische site-identiteit komt uit site-config.php via site.php.
// Verenigingsspecifieke pagina-SEO staat apart in site-seo.php.
// Configureerbare contentpagina's kunnen hun SEO-sleutel via de centrale
// pagina-registry aanleveren. Bestaande RC045-functienamen blijven in fase 1
// als compatibiliteitslaag bestaan.
// ============================================================

require_once __DIR__ . '/site.php';
require_once __DIR__ . '/content-pagina.php';

$RC045_SITE = siteUrl();
$RC045_TALEN = siteTalen();
$RC045_PAGINAS = require __DIR__ . '/site-seo.php';

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

  // Voor geregistreerde contentpagina's komt de SEO-identiteit voortaan uit
  // pagina-definities.php. Voor overige legacy-pagina's blijft de aangeleverde
  // sleutel ongewijzigd werken.
  if (contentPaginaBestaat((string) $pagina)) {
    $pagina = contentPaginaSeoSleutel((string) $pagina);
  }

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
