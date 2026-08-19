<?php
// ============================================================
// RC045 gedeelde hulpjes voor beheer.php en leden.php
// ------------------------------------------------------------
// Kleine dingen die allebei de afgeschermde pagina's nodig hebben: hoe een
// datum en een bedrag getoond worden, de maandnamen, en de contributie-
// bedragen met de pro-ratatabel.
// ============================================================

$huidigPaneelScript = strtolower(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
if ($huidigPaneelScript === 'beheer.php') {
  require_once __DIR__ . '/site.php';
  require_once __DIR__ . '/beheer/bootstrap.php';
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
