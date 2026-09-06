<?php
// ============================================================
// Beheer > Commissies/Werkgroepen > Relaties
// Koppelt groepen many-to-many aan taken, vergaderingen en evenementen.
// ============================================================
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/app/auth-capabilities.php';
require_once dirname(__DIR__) . '/app/data-slot.php';
require_once dirname(__DIR__) . '/app/leden/groepen.php';
if(!$ingelogd){header('Location: ./');exit;}
$magCommissies=authHeeftCapability('committees.manage');
$magWerkgroepen=authHeeftCapability('workgroups.manage');
$magTaken=authHeeftCapability('tasks.manage');
$magVergaderingen=authHeeftCapability('meetings.manage');
$magEvenementen=authHeeftCapability('events.manage');
if(!$magCommissies&&!$magWerkgroepen){http_response_code(403);echo'Geen toegang tot groepsrelaties.';exit;}
if(!$magTaken&&!$magVergaderingen&&!$magEvenementen){http_response_code(403);echo'Geen toegang tot koppelbare onderdelen.';exit;}
function grEsc($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function grMagGroep(array $g,bool $c,bool $w):bool{return(($g['type']??'')==='commissie'&&$c)||(($g['type']??'')==='werkgroep'&&$w);}
function grRedirect(string $groep=''):void{header('Location: groep-relaties.php'.($groep!==''?'?groep='.rawurlencode($groep):''));exit;}
function grGeldigeIds(array $bron,string $sleutel):array{$uit=[];foreach((array)($bron[$sleutel]??[]) as $x)if(is_array($x)&&trim((string)($x['id']??''))!=='')$uit[(string)$x['id']]=true;return$uit;}
$flash=$_SESSION['groep_relaties_flash']??null;unset($_SESSION['groep_relaties_flash']);
$doc=groepenLeesDocument();$groepen=array_values(array_filter((array)($doc['groepen']??[]),fn($g)=>is_array($g)&&($g['status']??'actief')!=='gearchiveerd'&&grMagGroep($g,$magCommissies,$magWerkgroepen)));usort($groepen,static fn($a,$b)=>strcmp((string)$a['type'],(string)$b['type'])?:strnatcasecmp((string)$a['naam'],(string)$b['naam']));
$groepId=groepenId($_GET['groep']??$_POST['groep']??'');$groep=$groepId===''?null:groepenVind($doc,$groepId);if($groep&&!grMagGroep($groep,$magCommissies,$magWerkgroepen))$groep=null;
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
 if(!csrfOk()){$_SESSION['groep_relaties_flash']=['type'=>'fout','tekst'=>'Sessie verlopen. Ververs de pagina.'];grRedirect($groepId);}
 if(!$groep){$_SESSION['groep_relaties_flash']=['type'=>'fout','tekst'=>'Groep niet gevonden of geen beheerrecht.'];grRedirect();}
 $slot=dataSlotOpen();try{$doc=groepenLeesDocument();$bestaand=groepenRelatiesVoorGroep($doc,$groepId);$nieuw=$bestaand;
  if($magTaken){$geldig=grGeldigeIds(repoTakenLees(),'taken');$nieuw['taken']=array_values(array_filter(groepenNormaliseerRelatieIds($_POST['taken']??[]),static fn($id)=>isset($geldig[$id])));}
  if($magVergaderingen){$geldig=grGeldigeIds(repoVergaderingenLees(),'vergaderingen');$nieuw['vergaderingen']=array_values(array_filter(groepenNormaliseerRelatieIds($_POST['vergaderingen']??[]),static fn($id)=>isset($geldig[$id])));}
  if($magEvenementen){$geldig=grGeldigeIds(repoEvenementenLees(),'evenementen');$nieuw['evenementen']=array_values(array_filter(groepenNormaliseerRelatieIds($_POST['evenementen']??[]),static fn($id)=>isset($geldig[$id])));}
  if(!groepenRelatiesWerkBij($doc,$groepId,$nieuw))$_SESSION['groep_relaties_flash']=['type'=>'fout','tekst'=>'Groep niet gevonden.'];
  elseif(groepenSchrijfDocument($doc)){schrijfLog($logBestand,$huidigeGebruiker,'groep_relaties_bijgewerkt',$groepId);$_SESSION['groep_relaties_flash']=['type'=>'ok','tekst'=>'Groepsrelaties opgeslagen.'];}
  else $_SESSION['groep_relaties_flash']=['type'=>'fout','tekst'=>'Opslaan mislukt.'];
 }finally{dataSlotDicht($slot);}grRedirect($groepId);
}
$rel=$groep?groepenRelatiesVoorGroep($doc,$groepId):['taken'=>[],'vergaderingen'=>[],'evenementen'=>[]];$taken=$magTaken?takenGesorteerd(repoTakenLees()):[];$verg=$magVergaderingen?vergaderingenGesorteerd(repoVergaderingenLees()):[];$events=$magEvenementen?evenementenGesorteerd(repoEvenementenLees()):[];
?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Groepsrelaties</title><link rel="stylesheet" href="csp205-groep-relaties-c82a61085dfe.css"><link rel="stylesheet" href="ui-2026.css"></head><body><div class="top"><a href="./">← Beheer</a></div><main class="wrap"><h1>Relaties van commissies en werkgroepen</h1><p class="meta">Koppel een groep aan de taken, vergaderingen en evenementen waar die groep verantwoordelijk voor is of aan werkt.</p><?php if($flash):?><div class="flash <?=grEsc($flash['type']??'')?>"><?=grEsc($flash['tekst']??'')?></div><?php endif;?><form method="get" class="card"><label><strong>Groep</strong><br><select name="groep" onchange="this.form.submit()"><option value="">Kies een groep</option><?php foreach($groepen as $g):?><option value="<?=grEsc($g['id'])?>" <?=$groepId===$g['id']?'selected':''?>><?=grEsc(groepenTypes()[$g['type']]??$g['type'])?> · <?=grEsc($g['naam'])?></option><?php endforeach;?></select></label></form><?php if($groep):?><form method="post" class="card"><input type="hidden" name="csrf" value="<?=grEsc($csrfToken)?>"><input type="hidden" name="groep" value="<?=grEsc($groepId)?>"><h2><?=grEsc($groep['naam'])?></h2><div class="grid"><?php if($magTaken):?><section><h3>Taken</h3><div class="set"><?php if(!$taken):?><p class="meta">Geen taken.</p><?php endif;?><?php foreach($taken as $x):?><label><input type="checkbox" name="taken[]" value="<?=grEsc($x['id'])?>" <?=in_array($x['id'],$rel['taken'],true)?'checked':''?>> <?=grEsc(taakWeergavenaam($x))?></label><?php endforeach;?></div></section><?php endif;?><?php if($magVergaderingen):?><section><h3>Vergaderingen</h3><div class="set"><?php if(!$verg):?><p class="meta">Geen vergaderingen.</p><?php endif;?><?php foreach($verg as $x):?><label><input type="checkbox" name="vergaderingen[]" value="<?=grEsc($x['id'])?>" <?=in_array($x['id'],$rel['vergaderingen'],true)?'checked':''?>> <?=grEsc(vergaderingWeergavenaam($x))?></label><?php endforeach;?></div></section><?php endif;?><?php if($magEvenementen):?><section><h3>Evenementen</h3><div class="set"><?php if(!$events):?><p class="meta">Geen evenementen.</p><?php endif;?><?php foreach($events as $x):?><label><input type="checkbox" name="evenementen[]" value="<?=grEsc($x['id'])?>" <?=in_array($x['id'],$rel['evenementen'],true)?'checked':''?>> <?=grEsc(evenementWeergavenaam($x))?></label><?php endforeach;?></div></section><?php endif;?></div><p><button class="btn primary" type="submit">Relaties opslaan</button></p></form><?php endif;?></main></body></html>
