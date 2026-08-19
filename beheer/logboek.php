<?php
// ============================================================
// Modulaire beheerpagina: Logboek
// ============================================================
require_once dirname(__DIR__) . '/auth.php';

if (!$ingelogd) { header('Location: ../beheer.php'); exit; }
$rechten = authRechten(['log' => 'Logboek'], []);
if (!$isMaster && !in_array('log', $rechten['toegestaneTabs'] ?? [], true)) {
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
    header('Content-Disposition: attachment; filename="logboek-' . date('Y-m-d-His') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Tijd', 'Gebruiker', 'Actie', 'Details'], ';');
    foreach ($regels as $r) {
        fputcsv($out, [
            logCsvVeilig(logDatumTijd((string)($r['tijd'] ?? ''))),
            logCsvVeilig((string)($r['gebruiker'] ?? '')),
            logCsvVeilig((string)($r['actie'] ?? '')),
            logCsvVeilig((string)($r['details'] ?? '')),
        ], ';');
    }
    fclose($out);
    exit;
}

$perPagina = 50;
$totaal = count($regels);
$paginas = max(1, (int)ceil($totaal / $perPagina));
$pagina = isset($_GET['pagina']) && ctype_digit((string)$_GET['pagina']) ? max(1, min($paginas, (int)$_GET['pagina'])) : 1;
$zichtbaar = array_slice($regels, ($pagina - 1) * $perPagina, $perPagina);
?><!DOCTYPE html>
<html lang="nl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Logboek</title>
<style>
body{margin:0;background:#f6f2e8;color:#26351d;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.top{position:sticky;top:0;z-index:10;background:#fff;border-bottom:1px solid #ddd8c0;padding:15px 22px}.topin{max-width:1180px;margin:auto;display:flex;justify-content:space-between;gap:16px}.top a{font-weight:700;color:#2d6260;text-decoration:none}.wrap{max-width:1180px;margin:28px auto;padding:0 22px 70px}.kaart{background:#fff;border:1px solid #ddd8c0;border-radius:14px;padding:22px;margin-bottom:18px}.filters{display:grid;grid-template-columns:2fr 1fr 1fr .8fr;gap:12px;align-items:end}.veld label{display:block;font-weight:700;margin-bottom:6px}.veld input,.veld select{box-sizing:border-box;width:100%;border:1px solid #cfcab7;border-radius:8px;padding:10px;font:inherit}.acties{display:flex;gap:9px;flex-wrap:wrap;margin-top:14px}.btn{border:0;border-radius:9px;padding:10px 14px;font:inherit;font-weight:700;text-decoration:none;display:inline-block;cursor:pointer}.primair{background:#3a7a77;color:#fff}.secundair{background:#fff;border:1px solid #cfcab7;color:#26351d}.meta{color:#66705e;font-size:14px}.tabel-wrap{overflow:auto}.logtabel{width:100%;border-collapse:collapse;min-width:760px}.logtabel th,.logtabel td{text-align:left;vertical-align:top;padding:10px 9px;border-bottom:1px solid #ece8dc}.logtabel th{font-size:13px;color:#66705e}.tijd{white-space:nowrap}.actie{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:13px}.details{white-space:pre-wrap;word-break:break-word}.pager{display:flex;gap:8px;align-items:center;justify-content:flex-end;margin-top:16px}.pager a,.pager span{padding:7px 10px;border:1px solid #d8d2bf;border-radius:7px;text-decoration:none;color:#26351d}.pager .actief{background:#3a7a77;color:#fff;border-color:#3a7a77}@media(max-width:850px){.filters{grid-template-columns:1fr 1fr}.wrap{padding-left:12px;padding-right:12px}}@media(max-width:540px){.filters{grid-template-columns:1fr}}
</style></head><body>
<div class="top"><div class="topin"><a href="../beheer.php">← Terug naar beheer</a><span>Alleen-lezen</span></div></div>
<main class="wrap"><h1>Logboek</h1><p class="meta">Activiteitenlogboek van beheer. Het bronbestand bewaart maximaal 90 dagen; deze pagina wijzigt of wist niets.</p>
<section class="kaart"><form method="get"><div class="filters"><div class="veld"><label for="q">Zoeken</label><input id="q" name="q" value="<?=logEsc($zoek)?>" placeholder="Gebruiker, actie of details"></div><div class="veld"><label for="gebruiker">Gebruiker</label><select id="gebruiker" name="gebruiker"><option value="">Alle gebruikers</option><?php foreach($gebruikers as $g):?><option value="<?=logEsc($g)?>" <?=$gebruiker===$g?'selected':''?>><?=logEsc($g)?></option><?php endforeach;?></select></div><div class="veld"><label for="actie">Actie</label><select id="actie" name="actie"><option value="">Alle acties</option><?php foreach($acties as $a):?><option value="<?=logEsc($a)?>" <?=$actie===$a?'selected':''?>><?=logEsc($a)?></option><?php endforeach;?></select></div><div class="veld"><label for="dagen">Periode</label><select id="dagen" name="dagen"><option value="7" <?=$dagen===7?'selected':''?>>7 dagen</option><option value="30" <?=$dagen===30?'selected':''?>>30 dagen</option><option value="90" <?=$dagen===90?'selected':''?>>90 dagen</option><option value="0" <?=$dagen===0?'selected':''?>>Alles in bestand</option></select></div></div><div class="acties"><button class="btn primair" type="submit">Filter toepassen</button><a class="btn secundair" href="logboek.php">Filters wissen</a><a class="btn secundair" href="?<?=logEsc(logQuery(['export'=>'csv','pagina'=>null]))?>">CSV exporteren</a></div></form></section>
<section class="kaart"><p class="meta"><strong><?=$totaal?></strong> regel(s) gevonden<?= $totaal ? ' · pagina '.$pagina.' van '.$paginas : '' ?>.</p><?php if(!$zichtbaar):?><p>Geen logregels gevonden met deze filters.</p><?php else:?><div class="tabel-wrap"><table class="logtabel"><thead><tr><th>Tijd</th><th>Gebruiker</th><th>Actie</th><th>Details</th></tr></thead><tbody><?php foreach($zichtbaar as $r):?><tr><td class="tijd"><?=logEsc(logDatumTijd((string)($r['tijd']??'')))?></td><td><?=logEsc($r['gebruiker']??'')?></td><td class="actie"><?=logEsc($r['actie']??'')?></td><td class="details"><?=logEsc($r['details']??'')?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?>
<?php if($paginas>1):?><nav class="pager" aria-label="Paginering"><?php if($pagina>1):?><a href="?<?=logEsc(logQuery(['pagina'=>$pagina-1]))?>">← Vorige</a><?php endif;?><span class="actief"><?=$pagina?> / <?=$paginas?></span><?php if($pagina<$paginas):?><a href="?<?=logEsc(logQuery(['pagina'=>$pagina+1]))?>">Volgende →</a><?php endif;?></nav><?php endif;?></section></main></body></html>
