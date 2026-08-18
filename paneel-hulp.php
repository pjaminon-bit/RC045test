<?php
// ============================================================
// RC045 gedeelde hulpjes voor beheer.php en leden.php
// ------------------------------------------------------------
// Kleine dingen die allebei de afgeschermde pagina's nodig hebben: hoe een
// datum en een bedrag getoond worden, de maandnamen, en de contributie-
// bedragen met de pro-ratatabel.
// ============================================================

// Fase 1C/1D: moduleconfiguratie en generieke contenteditor.
$huidigPaneelScript = strtolower(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
if ($huidigPaneelScript === 'beheer.php') {
  require_once __DIR__ . '/site.php';
  require_once __DIR__ . '/content-pagina.php';

  // Fase 1D afgerond: Ontstaan en Baanreglement worden uitsluitend nog via
  // content-beheer.php beheerd. De historische POST-routes in het grote
  // beheer.php blijven voorlopig fysiek aanwezig als dode code tot fase 2,
  // maar zijn vanaf hier niet meer bereikbaar.
  $legacyContentFormulieren = ['ontstaan', 'baanreglement'];
  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $legacyFormulier = isset($_POST['formulier']) && is_string($_POST['formulier']) ? $_POST['formulier'] : '';
    if (in_array($legacyFormulier, $legacyContentFormulieren, true)) {
      $_POST['formulier'] = '';
      if (function_exists('schrijfLog') && isset($logBestand, $huidigeGebruiker)) {
        schrijfLog($logBestand, (string) $huidigeGebruiker, 'legacy_content_geblokkeerd', $legacyFormulier);
      }
    }
  }

  // Centrale ingang naar alle geregistreerde contentpagina's. De oude tabs
  // Ontstaan en Baanreglement worden tegelijk uit de beheerinterface gehaald,
  // zodat er nog maar één beheerroute zichtbaar en bruikbaar is.
  ob_start(function ($html) {
    if (!is_string($html) || stripos($html, '</body>') === false) return $html;
    if (strpos($html, 'id="generieke-content-snelkoppelingen"') !== false) return $html;

    $links = [];
    foreach (contentPaginaDefinities() as $sleutel => $def) {
      $label = trim((string) ($def['label'] ?? $sleutel));
      $tab = contentPaginaBeheerTab((string) $sleutel);
      $mag = !isset($GLOBALS['toegestaneTabs']) || in_array($tab, (array) $GLOBALS['toegestaneTabs'], true) || !empty($GLOBALS['isMaster']);
      if (!$mag) continue;

      $links[] = '<a href="content-beheer.php?pagina=' . rawurlencode((string) $sleutel) . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    // Legacy-tabs onzichtbaar maken. De server-side POST-blokkade hierboven is
    // de echte afsluiting; deze CSS voorkomt alleen dubbele beheer-ingangen.
    $legacyCss = '<style id="legacy-content-tabs-uit">'
      . '#tab-ontstaan,#tab-baanreglement,'
      . '[href="#ontstaan"],[href="#baanreglement"],'
      . '[href="#tab-ontstaan"],[href="#tab-baanreglement"],'
      . '[data-tab="ontstaan"],[data-tab="baanreglement"],'
      . '[data-tab-target="ontstaan"],[data-tab-target="baanreglement"]'
      . '{display:none!important}</style>';
    if (stripos($html, '</head>') !== false && strpos($html, 'id="legacy-content-tabs-uit"') === false) {
      $html = preg_replace('~</head>~i', $legacyCss . "\n</head>", $html, 1) ?? $html;
    }

    if (!$links) return $html;

    $blok = '<aside id="generieke-content-snelkoppelingen" style="position:fixed;right:18px;bottom:18px;z-index:9999;width:min(320px,calc(100vw - 36px));background:#fff;border:1px solid #d9d3bd;border-radius:12px;box-shadow:0 12px 36px rgba(0,0,0,.14);padding:16px;font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif">'
      . '<div style="font-weight:800;color:#26351d;margin-bottom:6px">Contentpagina\'s</div>'
      . '<div style="font-size:12px;color:#6a7560;line-height:1.45;margin-bottom:10px">Beheer configureerbare pagina\'s via de generieke editor.</div>'
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
