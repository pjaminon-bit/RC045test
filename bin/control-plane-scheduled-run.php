<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Alleen via CLI beschikbaar.');}
require_once dirname(__DIR__).'/app/deployment/runtime-contract.php';
require_once dirname(__DIR__).'/app/deployment/control-plane-admin-suite-contract.php';

function cpsStop(string$m,int$c=1):never{fwrite(STDERR,"FOUT: {$m}\n");exit($c);}
function cpsAbs(string$p):bool{return runtime41IsAbsoluutPad($p)&&!runtime41HeeftRelatieveSegmenten($p);}
function cpsConfig(string$path):array{
    if(!cpsAbs($path)||runtime41SymlinkInPad($path)!==null||!is_file($path))throw new RuntimeException('Runtimeconfig is onveilig.');$raw=@file_get_contents($path);$c=is_string($raw)?json_decode($raw,true):null;
    $keys=['host','app_root','tenants_root','runtime_user','pending_dir','processing_dir','results_dir','sessions_dir','snapshot_file','executor_lock','audit_file','lifecycle_apply'];if(!is_array($c)||(int)($c['schema']??0)!==1||($c['phase']??'')!=='5.1-runtime')throw new RuntimeException('Runtimeconfig heeft onbekend schema.');foreach($keys as$k)if(!is_string($c[$k]??null)||$c[$k]==='')throw new RuntimeException('Runtimeconfig mist '.$k.'.');foreach(['app_root','tenants_root','pending_dir','processing_dir','results_dir','sessions_dir','snapshot_file','executor_lock','audit_file','lifecycle_apply']as$k)if(!cpsAbs($c[$k]))throw new RuntimeException('Runtimeconfig bevat onveilig pad.');$c['_config_file']=$path;return$c;
}
function cpsIds(string$user):array{if(!function_exists('posix_getpwnam')||!function_exists('posix_getgrnam'))throw new RuntimeException('POSIX accountcontrole ontbreekt.');$u=@posix_getpwnam($user);$g=@posix_getgrnam($user);if(!is_array($u)||!is_array($g))throw new RuntimeException('Control-plane runtimeidentity ontbreekt.');return[(int)$u['uid'],(int)$g['gid']];}
function cpsWrite(string$path,array$data,int$uid,int$gid):void{
    if(runtime41SymlinkInPad($path)!==null)throw new RuntimeException('Scheduled writepad bevat symlink.');$json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);if(!is_string($json))throw new RuntimeException('Scheduled JSON write faalde.');$tmp=dirname($path).'/.'.basename($path).'.tmp.'.bin2hex(random_bytes(5));if(@file_put_contents($tmp,$json."\n",LOCK_EX)===false)throw new RuntimeException('Scheduled tijdelijke write faalde.');if(!@chown($tmp,$uid)||!@chgrp($tmp,$gid)||!@chmod($tmp,0640)){@unlink($tmp);throw new RuntimeException('Scheduled write metadata faalde.');}if(is_link($path)||!@rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('Scheduled atomische write faalde.');}}
function cpsScheduleLock(string$dir,string$id){
    if(preg_match('/^[0-9a-f]{32}$/D',$id)!==1||!cpsAbs($dir)||runtime41SymlinkInPad($dir)!==null||!is_dir($dir))throw new RuntimeException('Schedule-locklocatie is onveilig.');
    $path=$dir.'/'.$id.'.lock';if(runtime41SymlinkInPad($path)!==null)throw new RuntimeException('Schedule-lock bevat symlink.');$h=@fopen($path,'c');if(!is_resource($h))throw new RuntimeException('Schedule-lock kon niet worden geopend.');
    if(!@chown($path,0)||!@chgrp($path,0)||!@chmod($path,0600)){fclose($h);throw new RuntimeException('Schedule-lock kon niet root-only worden gemaakt.');}
    if(!flock($h,LOCK_EX)){fclose($h);throw new RuntimeException('Schedule-lock kon niet worden verkregen.');}return$h;
}

foreach($_SERVER['argv']??[]as$arg)if(preg_match('/^--(?:password|pass|secret|token|credential|webhook)(?:=|$)/i',(string)$arg)===1)cpsStop('Secrets zijn niet toegestaan.');
$o=getopt('',['config:','schedule:','help']);if(isset($o['help'])){echo"Gebruik: sudo php bin/control-plane-scheduled-run.php --config=/etc/verenigingsplatform/control-plane/runtime.json --schedule=<32hex>\n";exit(0);}if(PHP_OS_FAMILY!=='Linux'||!function_exists('posix_geteuid')||posix_geteuid()!==0)cpsStop('Scheduled runner vereist Linux root.');
try{
    $config=trim((string)($o['config']??''));$id=trim((string)($o['schedule']??''));if(preg_match('/^[0-9a-f]{32}$/D',$id)!==1)throw new RuntimeException('Schedule-id is ongeldig.');$c=cpsConfig($config);$paths=control58StatePaths($c);$lock=cpsScheduleLock($paths['schedules_dir'],$id);
    try{
        $file=$paths['schedules_dir'].'/'.$id.'.json';if(is_link($file)||!is_file($file))throw new RuntimeException('Schedulebestand ontbreekt.');$raw=@file_get_contents($file);$doc=control58ScheduleDocument(is_string($raw)?json_decode($raw,true):null);if($doc===null||!hash_equals($id,$doc['schedule_id']))throw new RuntimeException('Schedulebestand is ongeldig.');if($doc['status']!=='scheduled')throw new RuntimeException('Schedule is niet meer uitvoerbaar.');$execute=strtotime($doc['execute_at_utc']);if($execute===false||time()+5<$execute)throw new RuntimeException('Schedule werd te vroeg gestart.');
        [$uid,$gid]=cpsIds($c['runtime_user']);$requestId=bin2hex(random_bytes(16));$request=['schema'=>1,'phase'=>'5.1-request','request_id'=>$requestId,'tenant_key'=>$doc['tenant_key'],'action'=>$doc['action'],'operator'=>$doc['operator'],'requested_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'confirm'=>[]];$pending=$c['pending_dir'].'/'.$requestId.'.json';if(is_link($pending)||file_exists($pending))throw new RuntimeException('Pending request-id bestaat al.');$json=json_encode($request,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$h=@fopen($pending,'x');if(!is_resource($h))throw new RuntimeException('Scheduled queuewrite kon niet exclusief worden aangemaakt.');try{if(!flock($h,LOCK_EX)||fwrite($h,$json."\n")===false||!fflush($h))throw new RuntimeException('Scheduled queuewrite faalde.');}finally{fclose($h);}if(!@chown($pending,$uid)||!@chgrp($pending,$gid)||!@chmod($pending,0640)){@unlink($pending);throw new RuntimeException('Scheduled queue metadata faalde.');}
        $doc['status']='queued';$doc['request_id']=$requestId;$doc['message']='In uitvoerqueue geplaatst op '.gmdate('Y-m-d\TH:i:s\Z').'.';cpsWrite($file,$doc,0,$gid);echo'SCHEDULE QUEUED id='.$id.' request='.$requestId."\n";
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}catch(Throwable$e){cpsStop($e->getMessage());}
