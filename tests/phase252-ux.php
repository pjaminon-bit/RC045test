<?php
$root=dirname(__DIR__);$errors=[];$ok=[];
function t252($cond,string $msg):void{global$errors,$ok;if($cond)$ok[]=$msg;else$errors[]=$msg;}
function t252txt(string $p):string{return is_file($p)?(string)file_get_contents($p):'';}
$menu=t252txt($root.'/beheer/index.php');
foreach(['aanmeldingen','leden_import','ledenlabels','groepsrollen','operationele_taken'] as $sleutel)t252(strpos($menu,"'$sleutel'")!==false,"$sleutel is als secundair hoofdmenu-item gemarkeerd");
t252(strpos($menu,"'leden'=>['aanmeldingen','leden_import','ledenlabels']")!==false,'Leden groepeert aanmeldingen/import/labels');
t252(strpos($menu,"'taken'=>['operationele_taken']")!==false,'Taken groepeert operationele taken');
t252(substr_count($menu,"'groepsrollen'")>=3,'Groepsrollen zijn contextactie bij commissies en werkgroepen');
t252(strpos($menu,"['label'=>'Relaties','route'=>'groep-relaties.php']")!==false,'Commissies/werkgroepen hebben relaties-contextactie');
require_once $root.'/app/leden/groepen.php';
$doc=groepenNormaliseerDocument(['rollen'=>[['id'=>'lid','naam'=>'Lid','actief'=>true]],'groepen'=>[['id'=>'wg_x','type'=>'werkgroep','naam'=>'X','status'=>'actief','leden'=>[]]],'relaties'=>['wg_x'=>['taken'=>['t1','t1',''],'vergaderingen'=>['v1'],'evenementen'=>['e1']], 'onbekend'=>['taken'=>['t2']]]]);
t252(($doc['schema']??0)===2,'groepen schema verhoogd voor relaties');
t252(($doc['relaties']['wg_x']['taken']??[])===['t1'],'relatie-ids worden opgeschoond en gededupliceerd');
t252(!isset($doc['relaties']['onbekend']),'relaties naar onbekende groepen vallen weg');
t252(groepenRelatiesWerkBij($doc,'wg_x',['taken'=>['t2'],'vergaderingen'=>[],'evenementen'=>['e2']]),'relaties kunnen per groep worden bijgewerkt');
t252((groepenRelatiesVoorGroep($doc,'wg_x')['taken']??[])===['t2'],'bijgewerkte taakrelatie leesbaar');
$groepen=groepenRelatiesObjectGroepen($doc,'evenementen','e2');t252(count($groepen)===1&&($groepen[0]['id']??'')==='wg_x','reverse lookup object naar groep werkt');
$route=t252txt($root.'/beheer/groep-relaties.php');t252($route!=='','groep-relaties beheerroute bestaat');t252(strpos($route,'csrfOk(')!==false,'groep-relaties controleert CSRF');t252(strpos($route,"authHeeftCapability('tasks.manage')")!==false&&strpos($route,"authHeeftCapability('meetings.manage')")!==false&&strpos($route,"authHeeftCapability('events.manage')")!==false,'relatiecategorieën respecteren objectrechten');t252(strpos($route,'dataSlotOpen()')!==false,'relaties schrijven onder dataslot');t252(strpos($route,'groepenSchrijfDocument')!==false,'relaties gebruiken tenant-private groepenrepository');
echo 'Phase 2.5.2 checks: '.count($ok).' OK, '.count($errors)." fout(en)\n";if($errors){foreach($errors as $e)fwrite(STDERR,"FOUT: $e\n");exit(1);}foreach($ok as $m)echo "OK: $m\n";
