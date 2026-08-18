<?php
// ============================================================
// Modulaire beheerpagina: Agenda
// ============================================================

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/site.php';
require_once dirname(__DIR__) . '/data-slot.php';

if (!$ingelogd) { header('Location: ../beheer.php'); exit; }
if (!siteModuleActief('agenda')) { http_response_code(404); echo 'De agendamodule is voor deze vereniging niet ingeschakeld.'; exit; }

$rechten = authRechten(['agenda' => 'Agenda'], []);
if (!$isMaster && !in_array('agenda', $rechten['toegestaneTabs'] ?? [], true)) { http_response_code(403); echo 'Geen toegang tot Agenda.'; exit; }

$agendaBestand = dirname(__DIR__) . '/data/agenda.json';
$agendaTags = ['leden' => 'Ledenevenement', 'opendag' => 'Open dag', 'wedstrijd' => 'Wedstrijd'];

function agendaEsc($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function agendaKort($v, int $max): string {
    $t = trim(is_scalar($v) ? (string) $v : '');
    return function_exists('mb_substr') ? mb_substr($t, 0, $max, 'UTF-8') : substr($t, 0, $max);
}
function agendaDatumIso($v): string {
    $v = trim(is_scalar($v) ? (string) $v : '');
    if ($v === '') return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
    if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $v, $m)) {
        $d=(int)$m[1]; $mo=(int)$m[2]; $y=(int)$m[3];
        return checkdate($mo,$d,$y) ? sprintf('%04d-%02d-%02d',$y,$mo,$d) : '';
    }
    return '';
}
function agendaLees(string $pad): array {
    if (!is_file($pad)) return [];
    $d = json_decode((string) @file_get_contents($pad), true);
    return is_array($d) ? array_values($d) : [];
}
function agendaSchrijf(string $pad, array $data): bool {
    global $dataBackupMap, $dataBackupBewaardagen, $dataBackupMaxPerBestand;
    maakDataBackup($pad, $dataBackupMap, $dataBackupBewaardagen, $dataBackupMaxPerBestand);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return $json !== false && file_put_contents($pad, $json, LOCK_EX) !== false;
}

$events = agendaLees($agendaBestand);
$melding=''; $meldingType='';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrfOk()) { $melding='Sessie verlopen. Ververs de pagina en probeer opnieuw.'; $meldingType='fout'; }
    else {
        $slot = dataSlotOpen();
        try {
            $ruw=[];
            foreach ((array)($_POST['agenda'] ?? []) as $idx=>$rij) {
                if (!is_array($rij)) continue;
                $titelNl=agendaKort($rij['title_nl'] ?? '',80);
                if ($titelNl==='') continue;
                $tag=(string)($rij['tag'] ?? 'leden');
                if (!isset($agendaTags[$tag])) $tag='leden';
                $volgorde=is_numeric($rij['volgorde'] ?? null)?(float)$rij['volgorde']:(float)$idx;
                $ruw[]=[
                    'volgorde'=>$volgorde,
                    'orig'=>(int)$idx,
                    'event'=>[
                        'date'=>agendaDatumIso($rij['date'] ?? ''),
                        'tag'=>$tag,
                        'time'=>agendaKort($rij['time'] ?? '',40),
                        'title'=>['nl'=>$titelNl,'en'=>agendaKort($rij['title_en'] ?? '',80),'de'=>agendaKort($rij['title_de'] ?? '',80)],
                        'desc'=>['nl'=>agendaKort($rij['desc_nl'] ?? '',200),'en'=>agendaKort($rij['desc_en'] ?? '',200),'de'=>agendaKort($rij['desc_de'] ?? '',200)],
                        'past'=>!empty($rij['past']),
                    ],
                ];
            }
            usort($ruw, static fn($a,$b)=>$a['volgorde'] <=> $b['volgorde'] ?: $b['orig'] <=> $a['orig']);
            $nieuw=array_map(static fn($r)=>$r['event'],$ruw);
            if (agendaSchrijf($agendaBestand,$nieuw)) {
                $events=$nieuw; $melding='Opgeslagen. De agenda op de website is bijgewerkt.'; $meldingType='ok';
                schrijfLog($logBestand,$huidigeGebruiker,'agenda',count($events).' kaart(en) opgeslagen via modulaire editor');
            } else { $melding='Opslaan mislukt. Controleer de schrijfrechten van de data-map.'; $meldingType='fout'; }
        } finally { dataSlotDicht($slot); }
    }
}

if (!$events) $events=[['date'=>'','tag'=>'leden','time'=>'','title'=>['nl'=>'','en'=>'','de'=>''],'desc'=>['nl'=>'','en'=>'','de'=>''],'past'=>false]];
?><!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Agenda beheren</title>
<style>
body{margin:0;background:#f6f2e8;color:#26351d;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.top{background:#fff;border-bottom:1px solid #ddd8c0;padding:16px 24px;position:sticky;top:0;z-index:10}.topin{max-width:1100px;margin:auto;display:flex;justify-content:space-between;gap:16px}.top a{color:#2d6260;text-decoration:none;font-weight:700}.wrap{max-width:1100px;margin:30px auto;padding:0 24px 60px}.kaart{background:#fff;border:1px solid #ddd8c0;border-radius:14px;padding:22px;margin-bottom:18px}.grid{display:grid;grid-template-columns:1.2fr .8fr .8fr .55fr;gap:12px}.talen{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.veld{margin-bottom:14px}.veld label{display:block;font-weight:700;margin-bottom:6px}.veld input,.veld textarea,.veld select{box-sizing:border-box;width:100%;border:1px solid #cfcab7;border-radius:8px;padding:10px;font:inherit}.veld textarea{min-height:90px}.rij-kop{display:flex;justify-content:space-between;gap:12px;align-items:center}.btn{border:0;border-radius:9px;padding:10px 15px;font:inherit;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block}.primair{background:#3a7a77;color:#fff}.secundair{background:#fff;color:#26351d;border:1px solid #cfcab7}.gevaar{background:#fff1ef;color:#8b2e27;border:1px solid #e8b8b2}.melding{padding:12px 14px;border-radius:9px;margin-bottom:18px}.melding.ok{background:#e8f5ee;color:#205b38}.melding.fout{background:#fdeceb;color:#8b2e27}.acties{position:sticky;bottom:0;background:rgba(246,242,232,.94);padding:13px 0;display:flex;gap:10px}@media(max-width:800px){.grid,.talen{grid-template-columns:1fr}}
</style></head><body>
<div class="top"><div class="topin"><a href="../beheer.php">← Terug naar beheer</a><a href="../index.html" target="_blank" rel="noopener">Bekijk website ↗</a></div></div>
<main class="wrap"><h1>Agenda</h1><p>Beheer de agendakaarten, volgorde en vertalingen.</p>
<?php if($melding!==''):?><div class="melding <?=agendaEsc($meldingType)?>"><?=agendaEsc($melding)?></div><?php endif;?>
<form method="post" id="agenda-form"><input type="hidden" name="csrf" value="<?=agendaEsc($csrfToken)?>"><div id="agenda-lijst">
<?php foreach($events as $i=>$ev):?><section class="kaart agenda-rij"><div class="rij-kop"><h2>Agendakaart <?=($i+1)?></h2><button type="button" class="btn gevaar verwijder">Verwijderen</button></div>
<div class="grid"><div class="veld"><label>Datum</label><input type="date" data-field="date" name="agenda[<?=$i?>][date]" value="<?=agendaEsc($ev['date']??'')?>"></div><div class="veld"><label>Tijd</label><input data-field="time" name="agenda[<?=$i?>][time]" maxlength="40" value="<?=agendaEsc($ev['time']??'')?>"></div><div class="veld"><label>Type</label><select data-field="tag" name="agenda[<?=$i?>][tag]"><?php foreach($agendaTags as $k=>$lab):?><option value="<?=agendaEsc($k)?>" <?=($ev['tag']??'leden')===$k?'selected':''?>><?=agendaEsc($lab)?></option><?php endforeach;?></select></div><div class="veld"><label>Positie</label><input data-field="volgorde" type="number" min="1" step="1" name="agenda[<?=$i?>][volgorde]" value="<?=($i+1)?>"></div></div>
<div class="veld"><label><input data-field="past" type="checkbox" name="agenda[<?=$i?>][past]" value="1" <?=!empty($ev['past'])?'checked':''?>> Markeer als afgelopen</label></div>
<h3>Titel</h3><div class="talen"><?php foreach(['nl'=>'Nederlands','en'=>'Engels','de'=>'Duits'] as $t=>$lab):?><div class="veld"><label><?=$lab?></label><input data-field="title_<?=$t?>" name="agenda[<?=$i?>][title_<?=$t?>]" maxlength="80" value="<?=agendaEsc($ev['title'][$t]??'')?>"></div><?php endforeach;?></div>
<h3>Omschrijving</h3><div class="talen"><?php foreach(['nl'=>'Nederlands','en'=>'Engels','de'=>'Duits'] as $t=>$lab):?><div class="veld"><label><?=$lab?></label><textarea data-field="desc_<?=$t?>" name="agenda[<?=$i?>][desc_<?=$t?>]" maxlength="200"><?=agendaEsc($ev['desc'][$t]??'')?></textarea></div><?php endforeach;?></div>
</section><?php endforeach;?></div>
<button class="btn secundair" type="button" id="voeg-toe">+ Agendakaart toevoegen</button><div class="acties"><button class="btn primair" type="submit">Agenda opslaan</button><a class="btn secundair" href="../beheer.php">Annuleren</a></div></form></main>
<template id="agenda-template"><section class="kaart agenda-rij"><div class="rij-kop"><h2>Agendakaart</h2><button type="button" class="btn gevaar verwijder">Verwijderen</button></div><div class="grid"><div class="veld"><label>Datum</label><input type="date" data-field="date"></div><div class="veld"><label>Tijd</label><input data-field="time" maxlength="40"></div><div class="veld"><label>Type</label><select data-field="tag"><?php foreach($agendaTags as $k=>$lab):?><option value="<?=agendaEsc($k)?>"><?=agendaEsc($lab)?></option><?php endforeach;?></select></div><div class="veld"><label>Positie</label><input data-field="volgorde" type="number" min="1" step="1"></div></div><div class="veld"><label><input data-field="past" type="checkbox" value="1"> Markeer als afgelopen</label></div><h3>Titel</h3><div class="talen"><?php foreach(['nl'=>'Nederlands','en'=>'Engels','de'=>'Duits'] as $t=>$lab):?><div class="veld"><label><?=$lab?></label><input data-field="title_<?=$t?>" maxlength="80"></div><?php endforeach;?></div><h3>Omschrijving</h3><div class="talen"><?php foreach(['nl'=>'Nederlands','en'=>'Engels','de'=>'Duits'] as $t=>$lab):?><div class="veld"><label><?=$lab?></label><textarea data-field="desc_<?=$t?>" maxlength="200"></textarea></div><?php endforeach;?></div></section></template>
<script>
const lijst=document.getElementById('agenda-lijst');
function hernummer(){[...lijst.querySelectorAll('.agenda-rij')].forEach((rij,i)=>{rij.querySelector('h2').textContent='Agendakaart '+(i+1);rij.querySelectorAll('[data-field]').forEach(el=>{el.name=`agenda[${i}][${el.dataset.field}]`;});const pos=rij.querySelector('[data-field="volgorde"]');if(pos&&!pos.value)pos.value=i+1;});}
document.getElementById('voeg-toe').addEventListener('click',()=>{lijst.appendChild(document.getElementById('agenda-template').content.cloneNode(true));hernummer();});
lijst.addEventListener('click',e=>{if(e.target.classList.contains('verwijder')){e.target.closest('.agenda-rij').remove();hernummer();}});hernummer();
</script></body></html>