<?php
// ============================================================
// RC045 gedeelde hulpjes voor beheer.php en leden.php
// ------------------------------------------------------------
// Kleine dingen die allebei de afgeschermde pagina's nodig hebben: hoe een
// datum en een bedrag getoond worden, de maandnamen, en de contributie-
// bedragen met de pro-ratatabel. Die laatste worden in beheer.php beheerd
// (tabblad Rekentabel) en in leden.php alleen gelezen, bij het berekenen van
// de contributie van een lid.
// ============================================================

// Fase 1C/1D: moduleconfiguratie en tijdelijke migratie-ingang voor generieke
// contentpagina's.
$huidigPaneelScript = strtolower(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
if ($huidigPaneelScript === 'beheer.php') {
  require_once __DIR__ . '/site.php';
  require_once __DIR__ . '/content-pagina.php';

  // Fase 1D: voeg in het bestaande beheer een veilige, centrale ingang toe
  // naar alle geregistreerde contentpagina's. De oude tabs blijven voorlopig
  // bestaan als fallback; zodra de generieke editor in de praktijk is getest
  // kunnen die pagina-specifieke formulieren uit beheer.php verdwijnen.
  ob_start(function ($html) {
    if (!is_string($html) || stripos($html, '</body>') === false) return $html;
    if (strpos($html, 'id="generieke-content-snelkoppelingen"') !== false) return $html;

    $links = [];
    foreach (contentPaginaDefinities() as $sleutel => $def) {
      $label = trim((string) ($def['label'] ?? $sleutel));
      $tab = contentPaginaBeheerTab((string) $sleutel);

      // Respecteer bestaande rechten wanneer die al in de globale beheerflow
      // zijn berekend. Master wordt door beheer.php apart afgehandeld.
      $mag = !isset($GLOBALS['toegestaneTabs']) || in_array($tab, (array) $GLOBALS['toegestaneTabs'], true) || !empty($GLOBALS['isMaster']);
      if (!$mag) continue;

      $links[] = '<a href="content-beheer.php?pagina=' . rawurlencode((string) $sleutel) . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    if (!$links) return $html;

    $blok = '<aside id="generieke-content-snelkoppelingen" style="position:fixed;right:18px;bottom:18px;z-index:9999;width:min(320px,calc(100vw - 36px));background:#fff;border:1px solid #d9d3bd;border-radius:12px;box-shadow:0 12px 36px rgba(0,0,0,.14);padding:16px;font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif">'
      . '<div style="font-weight:800;color:#26351d;margin-bottom:6px">Contentpagina\'s</div>'
      . '<div style="font-size:12px;color:#6a7560;line-height:1.45;margin-bottom:10px">Nieuwe generieke editor. De oude tabs blijven tijdelijk beschikbaar.</div>'
      . '<div style="display:flex;flex-direction:column;gap:7px">'
      . implode('', array_map(function ($link) {
          return str_replace('<a ', '<a style="display:block;padding:9px 11px;background:#eaf4f3;color:#2d6260;border-radius:8px;text-decoration:none;font-weight:700" ', $link);
        }, $links))
      . '</div></aside>';

    return preg_replace('~</body>~i', $blok . "\n</body>", $html, 1) ?? $html;
  });
} elseif ($huidigPaneelScript === 'leden.php') {
  require_once __DIR__ . '/paneel-modules.php';
}

// jjjj-mm-dd naar dd-mm-jjjj. Ongeldig of leeg blijft leeg.
function datumWeergave($iso) {
  if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $iso, $m)) {
    return $m[3] . '-' . $m[2] . '-' . $m[1];
  }
  return '';
}

function euro($bedrag) {
  $s = number_format($bedrag, 2, ',', '.');
  if (substr($s, -3) === ',00') $s = substr($s, 0, -3);
  return '€' . $s;
}

$maandNamen  = [1 => 'Januari', 2 => 'Februari', 3 => 'Maart', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Augustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'December'];

$rekentabelStandaard = [
  'jaar' => date('Y'),
  'inschrijfkosten' => 10,
  'jeugd_jaarbedrag' => 50,
  'senior_jaarbedrag' => 100,
  'jeugd_leeftijd_tot' => 15,
  'jeugd_jaarbedrag_volgend' => '',
  'senior_jaarbedrag_volgend' => '',
];

function rekentabelJaarbedrag($rekentabelData, $jeugd, $jaar = null) {
  $sleutel = $jeugd ? 'jeugd_jaarbedrag' : 'senior_jaarbedrag';
  if ($jaar !== null && (int) $jaar === (int) $rekentabelData['jaar'] + 1) {
    $volgend = $rekentabelData[$sleutel . '_volgend'] ?? '';
    if ($volgend !== '' && $volgend !== null && is_numeric($volgend)) {
      return (float) $volgend;
    }
  }
  return (float) $rekentabelData[$sleutel];
}

function rekentabelProRata($jaarbedrag) {
  $tabel = [];
  for ($m = 1; $m <= 11; $m++) {
    $tabel[$m] = (int) round($jaarbedrag * (12 - $m) / 12);
  }
  $tabel[12] = null;
  return $tabel;
}
