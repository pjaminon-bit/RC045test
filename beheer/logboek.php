<?php
// ============================================================
// Modulaire beheerpagina: Logboek
// ============================================================
require_once dirname(__DIR__) . '/auth.php';

if (!$ingelogd) { header('Location: ../beheer.php'); exit; }
if (!authHeeftBeheerOnderdeel('logboek')) {
    http_response_code(403);
    echo 'Geen toegang tot Logboek.';
    exit;
}

function logEsc($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function logLees(string $pad): array {
    if (!is_file($pad)) return [];
    $raw = @file_get_contents($pad);
    if ($raw === false) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
}
function logDatumTijd(string $waarde): string {
    $ts = strtotime($waarde);
    return $ts === false ? $waarde : date('d-m-Y H:i:s', $ts);
}
function logQuery(array $extra = []): string {
    $q = $_GET;
    foreach ($extra as $k => $v) {
        if ($v === null || $v === '') unset($q[$k]); else $q[$k] = $v;
    }
    return http_build_query($q);
}
// Spreadsheetprogramma's kunnen een CSV-cel die begint met =, +, -, @,
// tab of CR als formule interpreteren. Logdetails kunnen beheerde tekst
// bevatten; prefix zulke cellen daarom met een apostrof bij export.
function logCsvVeilig($waarde): string {
    $tekst = (string) $waarde;
    if ($tekst !== '' && preg_match('/^[=+\-@\t\r]/', $tekst)) return "'" . $tekst;
    return $tekst;
}

$regels = logLees($logBestand);
$zoek = trim((string)($_GET['q'] ?? ''));
$gebruiker = trim((string)($_GET['gebruiker'] ?? ''));
$actie = trim((string)($_GET['actie'] ?? ''));
$dagen = isset($_GET['dagen']) && ctype_digit((string)$_GET['dagen']) ? max(0, min(365, (int)$_GET['dagen'])) : 90;

$gebruikers = [];
$acties = [];
foreach ($regels as $r) {
    $g = trim((string)($r['gebruiker'] ?? ''));
    $a = trim((string)($r['actie'] ?? ''));
    if ($g !== '') $gebruikers[$g] = true;
    if ($a !== '') $acties[$a] = true;
}
$gebruikers = array_keys($gebruikers); natcasesort($gebruikers); $gebruikers = array_values($gebruikers);
$acties = array_keys($acties); natcasesort($acties); $acties = array_values($acties);

$grens = $dagen > 0 ? strtotime('-' . $dagen . ' days') : null;
$regels = array_values(array_filter($regels, function(array $r) use ($zoek, $gebruiker, $actie, $grens): bool {
    if ($gebruiker !== '' && strcasecmp((string)($r['gebruiker'] ?? ''), $gebruiker) !== 0) return false;
    if ($actie !== '' && strcasecmp((string)($r['actie'] ?? ''), $actie) !== 0) return false;
    if ($grens !== null) {
        $ts = strtotime((string)($r['tijd'] ?? ''));
        if ($ts !== false && $ts < $grens) return false;
    }
    if ($zoek !== '') {
        $haystack = implode(' ', [(string)($r['gebruiker'] ?? ''), (string)($r['actie'] ?? ''), (string)($r['details'] ?? '')]);
        if (function_exists('mb_stripos')) {
            if (mb_stripos($haystack, $zoek, 0, 'UTF-8') === false) return false;
        } elseif (stripos($haystack, $zoek) === false) return false;
    }
    return true;
}));

usort($regels, static function(array $a, array $b): int {
    $ta = strtotime((string)($a['tijd'] ?? '')) ?: 0;
    $tb = strtotime((string)($b['tijd'] ?? '')) ?: 0;
    return $tb <=> $ta;
});

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="beheer-logboek-' . date('Ymd-His') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['Tijd', 'Gebruiker', 'Actie', 'Details'], ';');
    foreach ($regels as $r) {
        fputcsv($out, [
            logCsvVeilig((string)($r['tijd'] ?? '')),
            logCsvVeilig((string)($r['gebruiker'] ?? '')),
            logCsvVeilig((string)($r['actie'] ?? '')),
            logCsvVeilig((string)($r['details'] ?? '')),
        ], ';');
    }
    fclose($out);
    exit;
}

?><!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Logboek</title>
<style>
body{margin:0;background:#f6f2e8;color:#26351d;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.45}.top{background:#fff;border-bottom:1px solid #ddd8c0;padding:14px 22px}.top a{color:#2d6260;text-decoration:none;font-weight:700}.wrap{max-width:1180px;margin:28px auto;padding:0 20px 60px}.card{background:#fff;border:1px solid #ddd8c0;border-radius:14px;padding:18px;margin-bottom:16px}.filters{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:10px;align-items:end}.veld label{display:block;font-size:12px;font-weight:800;color:#68705f;margin-bottom:5px}.veld input,.veld select{width:100%;box-sizing:border-box;border:1px solid #cfcab7;border-radius:8px;padding:9px;font:inherit}.acties{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.btn{display:inline-block;border:0;border-radius:8px;padding:9px 12px;background:#3a7a77;color:#fff;text-decoration:none;font:inherit;font-weight:750;cursor:pointer}.btn.sec{background:#fff;color:#2d6260;border:1px solid #cfcab7}.meta{color:#68705f;font-size:13px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;background:#fff}th,td{text-align:left;vertical-align:top;border-bottom:1px solid #eee9db;padding:10px;font-size:13px}th{color:#68705f;font-size:11px;text-transform:uppercase;letter-spacing:.04em;position:sticky;top:0;background:#fff}td.details{min-width:300px;white-space:pre-wrap;word-break:break-word}.empty{padding:24px;text-align:center;color:#68705f}@media(max-width:800px){.filters{grid-template-columns:1fr 1fr}.wrap{padding:0 12px 40px}}@media(max-width:520px){.filters{grid-template-columns:1fr}}
</style>
<link rel="stylesheet" href="ui-2026.css">
</head>
<body>
<div class="top"><a href="./">← Beheer</a></div>
<main class="wrap">
<h1>Logboek</h1>
<p class="meta">Audittrail van beheerhandelingen. Standaard worden de laatste 90 dagen getoond.</p>
<section class="card">
<form method="get">
<div class="filters">
<div class="veld"><label for="q">Zoeken</label><input id="q" name="q" value="<?=logEsc($zoek)?>" placeholder="gebruiker, actie of details"></div>
<div class="veld"><label for="gebruiker">Gebruiker</label><select id="gebruiker" name="gebruiker"><option value="">Alle</option><?php foreach($gebruikers as $g):?><option value="<?=logEsc($g)?>" <?=$gebruiker===$g?'selected':''?>><?=logEsc($g)?></option><?php endforeach;?></select></div>
<div class="veld"><label for="actie">Actie</label><select id="actie" name="actie"><option value="">Alle</option><?php foreach($acties as $a):?><option value="<?=logEsc($a)?>" <?=$actie===$a?'selected':''?>><?=logEsc($a)?></option><?php endforeach;?></select></div>
<div class="veld"><label for="dagen">Periode</label><select id="dagen" name="dagen"><?php foreach([7=>'7 dagen',30=>'30 dagen',90=>'90 dagen',180=>'180 dagen',365=>'365 dagen',0=>'Alles'] as $d=>$label):?><option value="<?=$d?>" <?=$dagen===$d?'selected':''?>><?=logEsc($label)?></option><?php endforeach;?></select></div>
</div>
<div class="acties"><button class="btn" type="submit">Filteren</button><a class="btn sec" href="logboek.php">Wissen</a><a class="btn sec" href="?<?=logEsc(logQuery(['export'=>'csv']))?>">CSV exporteren</a></div>
</form>
</section>
<section class="card table-wrap">
<?php if(!$regels):?><div class="empty">Geen logregels gevonden voor deze selectie.</div><?php else:?><table><thead><tr><th>Tijd</th><th>Gebruiker</th><th>Actie</th><th>Details</th></tr></thead><tbody><?php foreach($regels as $r):?><tr><td><?=logEsc(logDatumTijd((string)($r['tijd']??'')))?></td><td><?=logEsc($r['gebruiker']??'')?></td><td><?=logEsc($r['actie']??'')?></td><td class="details"><?=logEsc($r['details']??'')?></td></tr><?php endforeach;?></tbody></table><?php endif;?>
</section>
</main>
</body>
</html>
