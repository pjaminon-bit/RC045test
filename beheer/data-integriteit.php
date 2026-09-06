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
?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Data-integriteit</title><link rel="stylesheet" href="csp205-data-integriteit-bcde8f6ab74a.css"><link rel="stylesheet" href="ui-2026.css"></head><body><div class="top"><a href="groep-relaties.php">← Groepsrelaties</a></div><main class="wrap"><h1>Data-integriteit</h1><p class="meta">Read-only controle op dangling verwijzingen rond taken, vergaderingen en evenementen. Deze pagina wijzigt geen data.</p><section id="data-integriteit-status" class="card <?=$totaal===0?'ok':'fout'?>" data-dangling-total="<?=$totaal?>" data-task-meeting="<?=(int)($a['taak_vergaderingen']??0)?>" data-group-task="<?=(int)($a['groep_taken']??0)?>" data-group-meeting="<?=(int)($a['groep_vergaderingen']??0)?>" data-group-event="<?=(int)($a['groep_evenementen']??0)?>"><h2><?=$totaal===0?'Geen dangling relaties gevonden':'Dangling relaties gevonden'?></h2><div class="grid"><div>Taak → vergadering: <strong><?=diEsc($a['taak_vergaderingen']??0)?></strong></div><div>Groep → taak: <strong><?=diEsc($a['groep_taken']??0)?></strong></div><div>Groep → vergadering: <strong><?=diEsc($a['groep_vergaderingen']??0)?></strong></div><div>Groep → evenement: <strong><?=diEsc($a['groep_evenementen']??0)?></strong></div></div><p><strong>Totaal: <?=diEsc($totaal)?></strong></p></section></main></body></html>
