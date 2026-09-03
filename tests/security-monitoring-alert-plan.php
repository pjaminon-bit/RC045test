<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function cp154(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";return;}$fout++;fwrite(STDERR,"FOUT: {$l}\n");}
require_once $root.'/app/deployment/monitoring-contract.php';
$tmp=sys_get_temp_dir().'/issue154-plan-'.bin2hex(random_bytes(4));@mkdir($tmp.'/tenant',0750,true);@mkdir($tmp.'/private',0750,true);
$c=['tenant_key'=>'issue154','canonical_host'=>'issue154.example','tenant_root'=>$tmp.'/tenant','private_root'=>$tmp.'/private','app_root'=>'/srv/verenigingsplatform/current','runtime_user'=>'vp_issue154','runtime_group'=>'vp_issue154','php_version'=>'8.5','pool'=>'vp_issue154','socket'=>'/run/php/vp_issue154.sock','runtime_plan_path'=>$tmp.'/runtime-plan.json','runtime_plan_sha256'=>str_repeat('a',64),'tls_plan_path'=>$tmp.'/tls-plan.json','tls_plan_sha256'=>str_repeat('b',64),'database_plan_path'=>$tmp.'/database-plan.json','database_plan_sha256'=>str_repeat('c',64),'certificate_fullchain'=>'/etc/letsencrypt/live/issue154/fullchain.pem','certificate_privkey'=>'/etc/letsencrypt/live/issue154/privkey.pem','tenant_https_filename'=>'issue154-ssl.conf','database'=>'vp_issue154','database_user'=>'vp_issue154'];
try{
 $on=monitoring46Plan($c,true);$off=monitoring46Plan($c,false);
 cp154(($on['alerts']['enabled']??null)===true,'nieuw monitoringplan legt enabled alerting expliciet vast');
 cp154(($off['alerts']['enabled']??null)===false,'nieuw monitoringplan kan alerting expliciet disabled vastleggen');
 cp154(($on['alerts']['adapter']??'')==='/etc/verenigingsplatform/monitoring/alert-command','enabled en disabled gebruiken hetzelfde externe adaptercontract');
 cp154(monitoring46SystemdService($off)===monitoring46SystemdService($on),'systemd healthprobe blijft --alert uitvoeren zodat disabled ook observeerbaar wordt afgehandeld');
 cp154(str_contains(monitoring46SystemdService($on),'--probe --write-status --alert'),'periodieke service vraagt expliciet healthstatus én alertdelivery');
}finally{@rmdir($tmp.'/tenant');@rmdir($tmp.'/private');@rmdir($tmp);}
echo"Issue #154 explicit alert plan: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
