<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function c481(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}
function reject481(callable$fn,string$needle=''):bool{try{$fn();return false;}catch(Throwable$e){return$needle===''||str_contains($e->getMessage(),$needle);}}
function rm481(string$p):void{if(is_link($p)||is_file($p)){@unlink($p);return;}if(!is_dir($p))return;foreach(scandir($p)?:[]as$n){if($n==='.'||$n==='..')continue;rm481($p.'/'.$n);}@rmdir($p);}
require_once $root.'/app/deployment/runtime-contract.php';
require_once $root.'/app/deployment/lifecycle-purge-hardening.php';
$key='alpha-club';$tenantRoot='/srv/verenigingen/'.$key;
$plan=['schema'=>1,'phase'=>'4.8','tenant_key'=>$key,'filesystem'=>[
 'tenant_root'=>$tenantRoot,'private_root'=>$tenantRoot.'/private','bundle_dir'=>$tenantRoot.'/lifecycle','plan_file'=>$tenantRoot.'/lifecycle/lifecycle-plan.json',
 'state_dir'=>'/var/lib/verenigingsplatform/lifecycle','state_file'=>'/var/lib/verenigingsplatform/lifecycle/'.$key.'.json',
 'plan_snapshot_dir'=>'/var/lib/verenigingsplatform/lifecycle/plans','plan_snapshot_file'=>'/var/lib/verenigingsplatform/lifecycle/plans/'.$key.'.json',
 'tombstone_dir'=>'/var/lib/verenigingsplatform/lifecycle/tombstones','tombstone_file'=>'/var/lib/verenigingsplatform/lifecycle/tombstones/'.$key.'.json',
 'export_root'=>'/var/backups/verenigingsplatform/tenants/'.$key,'lock_file'=>'/run/lock/verenigingsplatform-lifecycle-'.$key.'.lock']];
$b=lifecycle481DeleteBinding($plan);c481($b['tenant_root']===$tenantRoot&&$b['tenant_base']==='/srv/verenigingen','exact tenantroot/basis-binding wordt geaccepteerd');
$bad=$plan;$bad['filesystem']['tenant_root']='/srv/verenigingen/slachtoffer';c481(reject481(fn()=>lifecycle481DeleteBinding($bad),'tenant-key'),'tenantroot van andere tenant wordt geweigerd');
$bad=$plan;$bad['filesystem']['tenant_root']='/srv/verenigingen/../'.$key;c481(reject481(fn()=>lifecycle481DeleteBinding($bad),'veilig absoluut pad'),'relatieve segmenten in destructieve tenantroot worden geweigerd');
$bad=$plan;$bad['filesystem']['private_root']='/srv/verenigingen/slachtoffer/private';c481(reject481(fn()=>lifecycle481DeleteBinding($bad),'private_root'),'private-root crossbinding maakt purgeplan ongeldig');
$bad=$plan;$bad['filesystem']['state_file']='/var/lib/verenigingsplatform/lifecycle/slachtoffer.json';c481(reject481(fn()=>lifecycle481DeleteBinding($bad),'state_file'),'recovery statepad is exact tenantgebonden');
$tomb=['schema'=>1,'phase'=>'4.8-tombstone','tenant_key'=>$key,'status'=>'data_delete','tenant_root'=>$tenantRoot];c481(lifecycle481DeleteBinding($plan,$tomb)['tenant_root']===$tenantRoot,'geldige data-delete tombstone bindt exact aan planroot');
$bad=$tomb;$bad['tenant_root']='/srv/verenigingen/slachtoffer';c481(reject481(fn()=>lifecycle481DeleteBinding($plan,$bad),'andere tenantroot'),'tombstone kan deletepad niet naar andere tenant ombuigen');
$bad=$tomb;$bad['phase']='anders';c481(reject481(fn()=>lifecycle481DeleteBinding($plan,$bad),'tenantgebonden'),'tombstone schema/fase wordt fail-closed gevalideerd');
$bad=$tomb;$bad['status']='active';c481(reject481(fn()=>lifecycle481DeleteBinding($plan,$bad),'onbekende status'),'recovery accepteert geen niet-purge tombstone-status');
$tmp=sys_get_temp_dir().'/phase481-'.bin2hex(random_bytes(4));@mkdir($tmp.'/real',0750,true);@mkdir($tmp.'/real/'.$key,0750,true);$link=$tmp.'/linked';$made=function_exists('symlink')&&@symlink($tmp.'/real',$link);if($made)c481(runtime41SymlinkInPad($link.'/'.$key)===$link,'symlink-ancestor in tenantbasis wordt dynamisch gedetecteerd');else c481(true,'symlink-ancestortest overgeslagen');rm481($tmp);
$apply=(string)file_get_contents($root.'/bin/apply-vps-lifecycle.php');
c481(str_contains($apply,"require_once dirname(__DIR__) . '/app/deployment/lifecycle-purge-hardening.php'"),'lifecycle root-apply laadt pure purge-bindinghelper');
c481(str_contains($apply,'function apply48DeleteBoundary')&&str_contains($apply,'runtime41SymlinkInPad($pad)')&&str_contains($apply,"realpath(\$base)"),'destructieve boundary bewijst ancestors en fysieke tenantbasis');
c481(str_contains($apply,'apply48RecoveryMetaFile($snap')&&str_contains($apply,"apply48RecoveryMetaFile(\$tomb"),'zowel recovery snapshot als tombstone krijgen volledige ancestor/metadata-check');
c481(str_contains($apply,"(int)\$s['uid']!==0")&&str_contains($apply,"['mode']&0777")&&str_contains($apply,'!==0640'),'recovery metadata moet root-owned exact 0640 zijn');
$recover=strpos($apply,"if(\$actie==='recover-purge')");$recoverBoundary=strpos($apply,'apply48DeleteBoundary($pr,$tb)',$recover);$recoverRm=strpos($apply,'apply48RmStrict($root)',$recover);c481($recover!==false&&$recoverBoundary!==false&&$recoverRm!==false&&$recoverBoundary<$recoverRm,'recover-purge bewijst deleteboundary vóór recursive delete');
$purge=strpos($apply,"if(\$actie==='purge')");$purgeBoundary=strpos($apply,'apply48DeleteBoundary($p)',$purge);$purgeRm=strpos($apply,'apply48RmStrict($deleteRoot)',$purge);c481($purge!==false&&$purgeBoundary!==false&&$purgeRm!==false&&$purgeBoundary<$purgeRm,'normale purge bewijst dezelfde deleteboundary vóór recursive delete');
$workflow=(string)file_get_contents($root.'/.github/workflows/deploy-dev.yml');$runAll=(string)file_get_contents($root.'/tests/run-all.sh');
c481(str_contains($workflow,'bash tests/run-all.sh')&&str_contains($runAll,"find tests -maxdepth 1 -type f -name '*.php'")&&str_contains($runAll,'php "$test_file"'),'fase 4.8.1 purge-hardeningtest valt automatisch onder de volledige CI-regressiesuite');
echo"Phase 4.8.1 purge recovery hardening: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);