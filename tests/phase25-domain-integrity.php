<?php
$root=dirname(__DIR__);$errors=[];$ok=[];
function d25($cond,string $message):void{global$errors,$ok;if($cond)$ok[]=$message;else$errors[]=$message;}
function d25txt(string $path):string{return is_file($path)?(string)file_get_contents($path):'';}

require_once $root.'/app/leden/contributies.php';
require_once $root.'/aanmeldingen-opslag.php';

// Eén financiële regel per lid+jaar; laatste mutatie wint.
$doc=contributiesNormaliseerDocument(['regels'=>[
 ['lid_id'=>'lid_test','jaar'=>2026,'status'=>'open','verschuldigd_bedrag'=>100,'inschrijfgeld'=>10,'betaald_bedrag'=>0],
 ['lid_id'=>'lid_test','jaar'=>2026,'status'=>'open','verschuldigd_bedrag'=>90,'inschrijfgeld'=>10,'betaald_bedrag'=>25],
 ['lid_id'=>'lid_test','jaar'=>2027,'status'=>'open','verschuldigd_bedrag'=>110,'inschrijfgeld'=>0,'betaald_bedrag'=>0],
]]);
d25(count($doc['regels'])===2,'contributie-document dedupliceert lid+jaar');
$regel2026=null;foreach($doc['regels'] as $r)if(($r['lid_id']??'')==='lid_test'&&(int)($r['jaar']??0)===2026)$regel2026=$r;
d25(is_array($regel2026)&&(float)$regel2026['verschuldigd_bedrag']===90.0,'laatste duplicate contributieregel wint');
d25(($regel2026['status']??'')==='deels_betaald','gedeeltelijke betaling normaliseert naar deels_betaald');
d25(abs(contributieRestant($regel2026)-75.0)<0.001,'restant wordt correct berekend');

$kwijt=contributieNormaliseer(['lid_id'=>'lid_x','jaar'=>2026,'status'=>'kwijtgescholden','verschuldigd_bedrag'=>100,'inschrijfgeld'=>10,'betaald_bedrag'=>0,'vrijstelling_reden'=>'Bestuursbesluit']);
d25(contributieRestant($kwijt)===0.0,'kwijtschelding heeft geen openstaand restant');

// Privacyretentie: open blijft, oude afgehandelde verdwijnt, recente blijft.
$now=strtotime('2026-08-19T22:35:00+02:00');$old=date('c',$now-100*86400);$recent=date('c',$now-10*86400);
$apps=['aanmeldingen'=>[
 ['id'=>'open','status'=>'nieuw','aangemaakt'=>$old,'beoordeeld_op'=>''],
 ['id'=>'oud','status'=>'afgewezen','aangemaakt'=>$old,'beoordeeld_op'=>$old],
 ['id'=>'recent','status'=>'geaccepteerd','aangemaakt'=>$old,'beoordeeld_op'=>$recent],
]];
$removed=aanmeldingenPasRetentieToe($apps,$now);$ids=array_map(static fn($a)=>(string)($a['id']??''),$apps['aanmeldingen']);
d25($removed===1,'retentie verwijdert alleen oude afgehandelde aanvraag');
d25(in_array('open',$ids,true),'open aanvraag blijft ondanks ouderdom');
d25(!in_array('oud',$ids,true),'oude afgehandelde aanvraag verwijderd');
d25(in_array('recent',$ids,true),'recente afgehandelde aanvraag blijft');

// Strikte domeinscheiding: nieuwe flows schrijven geen geneste finance meer.
foreach(['beheer/leden.php','beheer/leden-import.php','beheer/aanmeldingen.php'] as $rel){
 $txt=d25txt($root.'/'.$rel);d25(strpos($txt,'ledenZetContributie(')===false,"$rel schrijft geen geneste contributieregel");
}
d25(strpos(d25txt($root.'/beheer/aanmeldingen.php'),'contributiesSchrijf')!==false,'aanmeldingacceptatie schrijft aparte contributieregel');

// Oude runtime-architectuur moet fysiek uit de repository verdwenen zijn.
foreach(['app/beheer/bootstrap.php','app/paneel-hulp.php','app/core/paneel-modules.php','leden-app.php'] as $rel)d25(!is_file($root.'/'.$rel),"legacy runtime verwijderd: $rel");
$moduleDir=$root.'/app/beheer/modules';
$moduleFiles=is_dir($moduleDir)?array_values(array_filter(scandir($moduleDir)?:[],static fn($n)=>$n!=='.'&&$n!=='..')):[];
d25(!$moduleFiles,'oude beheer outputfilter-modules zijn verwijderd');

$platform=require $root.'/app/core/platform-definities.php';
$bootstrapRefs=[];foreach((array)($platform['beheer']??[]) as $key=>$def)if(is_array($def)&&array_key_exists('bootstrap',$def))$bootstrapRefs[]=$key;
d25(!$bootstrapRefs,'platformregistry bevat geen outputfilter-bootstrapvelden');

// Accountkoppeling en erase zijn server-side beschermd.
$leden=d25txt($root.'/beheer/leden.php');
d25(strpos($leden,'lmUserVrij')!==false,'ledenbeheer bewaakt unieke actieve user_id-koppeling');
d25(strpos($leden,'lmNummerVrij')!==false,'ledenbeheer bewaakt uniek lidnummer');
d25(strpos($leden,"$magErase=authHeeftCapability('members.erase',true)")!==false,'privacy erase vereist expliciet gevoelig recht');
$service=d25txt($root.'/app/leden/service.php');
d25(strpos($service,'array_keys($waarde)')!==false,'privacy purge verwijdert lid-id ook als associative sleutel');

// PDO-mode file restore mag private PHP+JSON nooit aanbieden als actuele DB-backup.
$backup=d25txt($root.'/beheer/backups.php');
d25(strpos($backup,'privateViaPdo')!==false&&strpos($backup,"$type==='phpjson'")!==false,'PDO-modus blokkeert private bestandsrestore');

echo 'Phase 2.5 domain checks: '.count($ok).' OK, '.count($errors)." fout(en)\n";
if($errors){foreach($errors as $e)fwrite(STDERR,"FOUT: $e\n");exit(1);}foreach($ok as $m)echo "OK: $m\n";
