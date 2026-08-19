<?php
// ============================================================
// RC045 gedeelde hulpjes voor beheer en leden
// ============================================================

function paneelContext(): string
{
  $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
  $pad = strtolower((string) (parse_url($script, PHP_URL_PATH) ?: $script));
  if (preg_match('~(?:^|/)beheer/(?:index\.php)?$~', $pad) === 1 || basename($pad) === 'beheer.php') return 'beheer';
  if (preg_match('~(?:^|/)leden/(?:index\.php)?$~', $pad) === 1 || basename($pad) === 'leden.php') return 'leden';
  return '';
}

$projectRoot = dirname(__DIR__);
$huidigePaneelContext = paneelContext();
if ($huidigePaneelContext === 'beheer') {
  require_once $projectRoot . '/app/core/site.php';
  require_once $projectRoot . '/beheer/bootstrap.php';
} elseif ($huidigePaneelContext === 'leden') {
  require_once $projectRoot . '/app/core/paneel-modules.php';
}

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
    if ($volgend !== '' && $volgend !== null && is_numeric($volgend)) return (float) $volgend;
  }
  return (float) $rekentabelData[$sleutel];
}

function rekentabelProRata($jaarbedrag) {
  $tabel = [];
  for ($m = 1; $m <= 11; $m++) $tabel[$m] = (int) round($jaarbedrag * (12 - $m) / 12);
  $tabel[12] = null;
  return $tabel;
}
