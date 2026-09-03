<?php
// Read-only diagnose van referentiële integriteit. Geen repair via HTTP:
// operatorherstel blijft een expliciete CLI-actie.
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/app/auth-capabilities.php';
require_once dirname(__DIR__) . '/app/data-slot.php';
require_once dirname(__DIR__) . '/app/data-integriteit.php';
if(!$ingelogd){header('Location: ./');exit;}
$vereist=['committees.manage','workgroups.manage','tasks.manage','meetings.manage','events.manage'];
foreach($vereist as $cap)if(!authHeeftCapability($cap)){http_response_code(403);echo'Geen toegang tot data-integriteitscontrole.';exit;}
function diEsc($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$slot=dataSlotOpen();
try{$rapport=dataIntegriteitDetecteer();}
finally{dataSlotDicht($slot);}
$a=(array)($rapport['aantallen']??[]);$totaal=(int)($rapport['totaal']??0);
?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Data-integriteit</title><style>body{margin:0;background:#f6f2e8;color:#26351d;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.top{padding:14px 22px;background:#fff;border-bottom:1px solid #ddd8c0}.top a{color:#2d6260;font-weight:750;text-decoration:none}.wrap{max-width:850px;margin:30px auto;padding:0 20px 70px}.card{background:#fff;border:1px solid #ddd8c0;border-radius:14px;padding:20px;margin:14px 0}.ok{background:#eaf6ee}.fout{background:#fdeceb;color:#8b2e27}.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}.meta{color:#68705f;font-size:13px}@media(max-width:650px){.grid{grid-template-columns:1fr}}</style><link rel="stylesheet" href="ui-2026.css"></head><body><div class="top"><a href="groep-relaties.php">← Groepsrelaties</a></div><main class="wrap"><h1>Data-integriteit</h1><p class="meta">Read-only controle op dangling verwijzingen rond taken, vergaderingen en evenementen. Deze pagina wijzigt geen data.</p><section id="data-integriteit-status" class="card <?=$totaal===0?'ok':'fout'?>" data-dangling-total="<?=$totaal?>" data-task-meeting="<?=(int)($a['taak_vergaderingen']??0)?>" data-group-task="<?=(int)($a['groep_taken']??0)?>" data-group-meeting="<?=(int)($a['groep_vergaderingen']??0)?>" data-group-event="<?=(int)($a['groep_evenementen']??0)?>"><h2><?=$totaal===0?'Geen dangling relaties gevonden':'Dangling relaties gevonden'?></h2><div class="grid"><div>Taak → vergadering: <strong><?=diEsc($a['taak_vergaderingen']??0)?></strong></div><div>Groep → taak: <strong><?=diEsc($a['groep_taken']??0)?></strong></div><div>Groep → vergadering: <strong><?=diEsc($a['groep_vergaderingen']??0)?></strong></div><div>Groep → evenement: <strong><?=diEsc($a['groep_evenementen']??0)?></strong></div></div><p><strong>Totaal: <?=diEsc($totaal)?></strong></p></section></main></body></html>
