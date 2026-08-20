<?php
$root=dirname(__DIR__);$errors=[];$ok=[];
function d25($cond,string $message):void{global$errors,$ok;if($cond)$ok[]=$message;else$errors[]=$message;}
require_once $root.'/app/leden/contributies.php';
require_once $root.'/aanmeldingen-opslag.php';
require_once $root.'/app/leden/service.php';

$doc=contributiesNormaliseerDocument(['regels'=>[
 ['lid_id'=>'lid_test','jaar'=>2026,'status'=>'open','verschuldigd_bedrag'=>100,'inschrijfgeld'=>10,'betaald_bedrag'=>0],
 ['lid_id'=>'lid_test','jaar'=>2026,'status'=>'open','verschuldigd_bedrag'=>90,'inschrijfgeld'=>10,'betaald_bedrag'=>25],
 ['lid_id'=>'lid_test','jaar'=>2027,'status'=>'open','verschuldigd_bedrag'=>110,'inschrijfgeld'=>0,'betaald_bedrag'=>0],
]]);
d25(count($doc['regels'])===2,'contributie-document dedupliceert lid+jaar');$r2026=null;foreach($doc['regels'] as $r)if(($r['lid_id']??'')==='lid_test'&&(int)($r['jaar']??0)===2026)$r2026=$r;d25(is_array($r2026)&&(float)$r2026['verschuldigd_bedrag']===90.0,'laatste duplicate contributieregel wint');d25(($r2026['status']??'')==='deels_betaald','gedeeltelijke betaling normaliseert status');d25(abs(contributieRestant($r2026)-75.0)<0.001,'restant correct berekend');
$kwijt=contributieNormaliseer(['lid_id'=>'lid_x','jaar'=>2026,'status'=>'kwijtgescholden','verschuldigd_bedrag'=>100,'inschrijfgeld'=>10,'betaald_bedrag'=>0,'vrijstelling_reden'=>'Bestuursbesluit']);d25(contributieRestant($kwijt)===0.0,'kwijtschelding heeft nul restant');

$now=strtotime('2026-08-20T07:00:00+02:00');$old=date('c',$now-100*86400);$recent=date('c',$now-10*86400);$apps=['aanmeldingen'=>[['id'=>'open','status'=>'nieuw','aangemaakt'=>$old,'beoordeeld_op'=>''],['id'=>'oud','status'=>'afgewezen','aangemaakt'=>$old,'beoordeeld_op'=>$old],['id'=>'recent','status'=>'geaccepteerd','aangemaakt'=>$old,'beoordeeld_op'=>$recent]]];$removed=aanmeldingenPasRetentieToe($apps,$now);$ids=array_map(static fn($a)=>(string)($a['id']??''),$apps['aanmeldingen']);d25($removed===1,'retentie verwijdert alleen oude afgehandelde aanvraag');d25(in_array('open',$ids,true),'open aanvraag blijft ondanks ouderdom');d25(!in_array('oud',$ids,true),'oude afgehandelde aanvraag verwijderd');d25(in_array('recent',$ids,true),'recente afgehandelde aanvraag blijft');

$rel=['aanwezigheid'=>['lid_test'=>'aanwezig','ander'=>'afwezig'],'deelnemers'=>['lid_test','ander'],'nested'=>['toegewezen_aan'=>'lid_test']];ledenServicePurgeId($rel,'lid_test');d25(!array_key_exists('lid_test',$rel['aanwezigheid']),'privacy purge verwijdert associative lid-id sleutel');d25($rel['deelnemers']===['ander'],'privacy purge verwijdert lid-id uit lijsten');d25(($rel['nested']['toegewezen_aan']??null)==='','privacy purge wist enkelvoudige lid-id waarde');

$type=['id'=>'test','actief'=>true,'leeftijd_min'=>16,'leeftijd_max'=>65,'jaarbedrag'=>120,'inschrijfgeld'=>10,'pro_rata'=>true,'labels'=>['nl'=>'Testlid','en'=>'Test member']];d25(lidmaatschapTypeToegestaanVoorLeeftijd($type,16),'ondergrens lidmaatschapstype toegestaan');d25(lidmaatschapTypeToegestaanVoorLeeftijd($type,65),'bovengrens lidmaatschapstype toegestaan');d25(!lidmaatschapTypeToegestaanVoorLeeftijd($type,15),'onder leeftijd geweigerd');d25(lidmaatschapLabel($type,'en')==='Test member','meertalige label werkt');d25(abs(lidmaatschapBedragVoorMaand($type,7)-50.0)<0.001,'pro-rata bedrag correct');

$serviceTekst=(string)file_get_contents($root.'/app/leden/aanmeldingen-service.php');d25(strpos($serviceTekst,'betaald>0')!==false||strpos($serviceTekst,'$betaald>0')!==false,'acceptatieretry controleert reeds betaald bedrag');d25(strpos($serviceTekst,"status!=='open'")!==false,'acceptatieretry controleert financieel gewijzigde status');d25(strpos($serviceTekst,'privateStoreTransactie')!==false,'acceptatie gebruikt transactie');

echo 'Phase 2.5 domain checks: '.count($ok).' OK, '.count($errors)." fout(en)\n";if($errors){foreach($errors as $e)fwrite(STDERR,"FOUT: $e\n");exit(1);}foreach($ok as $m)echo "OK: $m\n";
