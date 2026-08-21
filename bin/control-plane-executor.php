<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Alleen via CLI beschikbaar.'); }
require_once dirname(__DIR__) . '/app/deployment/lifecycle-contract.php';

function cpeStop(string $m, int $c=1): never { fwrite(STDERR,"FOUT: {$m}\n"); exit($c); }
function cpeRun(array $cmd): array
{
    $d=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];$p=@proc_open($cmd,$d,$x,null,null,['bypass_shell'=>true]);
    if(!is_resource($p))return[255,'','proces kon niet starten'];fclose($x[0]);$o=stream_get_contents($x[1]);fclose($x[1]);$e=stream_get_contents($x[2]);fclose($x[2]);return[proc_close($p),trim((string)$o),trim((string)$e)];
}
function cpeAbs(string$p):bool{return str_starts_with($p,'/')&&!str_contains($p,"\0")&&!preg_match('#(?:^|/)\.\.?(/|$)#',$p);}
function cpeConfig(string $pad): array
{
    if(!cpeAbs($pad)||is_link($pad)||!is_file($pad))throw new RuntimeException('Control-plane runtimeconfig is onveilig.');
    $raw=@file_get_contents($pad);try{$c=is_string($raw)?json_decode($raw,true,64,JSON_THROW_ON_ERROR):null;}catch(Throwable$e){$c=null;}
    $keys=['host','app_root','tenants_root','runtime_user','pending_dir','processing_dir','results_dir','sessions_dir','snapshot_file','executor_lock','audit_file','lifecycle_apply'];
    if(!is_array($c)||(int)($c['schema']??0)!==1||($c['phase']??'')!=='5.1-runtime')throw new RuntimeException('Control-plane runtimeconfig heeft onbekend schema.');
    foreach($keys as$k)if(!isset($c[$k])||!is_string($c[$k])||$c[$k]==='')throw new RuntimeException('Runtimeconfig mist '.$k.'.');
    foreach(['app_root','tenants_root','pending_dir','processing_dir','results_dir','sessions_dir','snapshot_file','executor_lock','audit_file','lifecycle_apply']as$k)if(!cpeAbs($c[$k]))throw new RuntimeException('Onveilig runtimepad: '.$k);
    return$c;
}
function cpeDir(string$p,int$mode=0750,string|int$owner=0,string|int$group=0):void
{
    if(runtime41SymlinkInPad($p)!==null)throw new RuntimeException('Symlink in control-plane map: '.$p);if(!is_dir($p)&&!@mkdir($p,$mode,true)&&!is_dir($p))throw new RuntimeException('Map kon niet worden gemaakt: '.$p);@chown($p,$owner);@chgrp($p,$group);@chmod($p,$mode);
}
function cpeWrite(string$p,array$d,int$mode=0640,string|int$group='vst-control'):void
{
    if(runtime41SymlinkInPad($p)!==null)throw new RuntimeException('Symlink in control-plane writepad.');$j=json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);if(!is_string($j))throw new RuntimeException('JSON write faalde.');$tmp=dirname($p).'/.'.basename($p).'.tmp.'.bin2hex(random_bytes(5));if(@file_put_contents($tmp,$j."\n",LOCK_EX)===false)throw new RuntimeException('Tijdelijke write faalde.');@chown($tmp,0);@chgrp($tmp,$group);@chmod($tmp,$mode);if(is_link($p)||!@rename($tmp,$p)){@unlink($tmp);throw new RuntimeException('Atomische write faalde.');}@chown($p,0);@chgrp($p,$group);@chmod($p,$mode);
}
function cpeAudit(array$c,array$r,string$result,string$message=''):void
{
    cpeDir(dirname($c['audit_file']),0750,0,'adm');$row=['timestamp_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'request_id'=>$r['request_id']??null,'operator'=>$r['operator']??null,'tenant_key'=>$r['tenant_key']??null,'action'=>$r['action']??null,'result'=>$result];if($message!=='')$row['message']=substr(preg_replace('/\s+/',' ',trim($message))??'',0,300);$j=json_encode($row,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);if(!is_string($j)||@file_put_contents($c['audit_file'],$j."\n",FILE_APPEND|LOCK_EX)===false)throw new RuntimeException('Control-plane auditwrite faalde.');@chown($c['audit_file'],0);@chgrp($c['audit_file'],'adm');@chmod($c['audit_file'],0640);
}
function cpeState(array$p):array
{
    $f=(string)$p['filesystem']['state_file'];if(!file_exists($f))return['status'=>'unmanaged','transition'=>null,'updated_at_utc'=>null,'last_export'=>null,'purge_not_before_utc'=>null,'delete_export'=>null];if(is_link($f)||!is_file($f))throw new RuntimeException('Lifecycle-state is onveilig.');$raw=@file_get_contents($f);$s=is_string($raw)?json_decode($raw,true):null;if(!is_array($s)||(int)($s['schema']??0)!==1||($s['phase']??'')!=='4.8-state'||!hash_equals((string)$p['tenant_key'],(string)($s['tenant_key']??'')))throw new RuntimeException('Lifecycle-state is ongeldig.');$st=(string)($s['status']??'');if(!in_array($st,['active','suspended','pending_delete'],true))throw new RuntimeException('Onbekende lifecycle-status.');return['status'=>$st,'transition'=>$s['transition']??null,'updated_at_utc'=>$s['updated_at_utc']??null,'last_export'=>$s['last_export']??null,'purge_not_before_utc'=>$s['purge_not_before_utc']??null,'delete_export'=>$s['delete_export']??null];
}
function cpeGezond(array$p,string$status):bool
{
    if($status!=='active')return false;$f=(string)$p['monitoring']['health_status'];if(is_link($f)||!is_file($f))return false;$raw=@file_get_contents($f);$h=is_string($raw)?json_decode($raw,true):null;if(!is_array($h)||($h['phase']??'')!=='4.6-health'||!hash_equals((string)$p['tenant_key'],(string)($h['tenant_key']??''))||($h['state']??'')!=='up')return false;$t=strtotime((string)($h['checked_at_utc']??''));return$t!==false&&time()-$t<=300;
}
function cpeVeiligeExport(mixed$x):?array
{
    if(!is_array($x))return null;$sha=(string)($x['sha256']??'');if(preg_match('/^[0-9a-f]{64}$/D',$sha)!==1)return null;return['sha256'=>$sha,'created_at_utc'=>(string)($x['created_at_utc']??'')];
}
function cpeSnapshot(array$c):array
{
    $root=$c['tenants_root'];if(runtime41SymlinkInPad($root)!==null||!is_dir($root))throw new RuntimeException('Tenantroot ontbreekt of is onveilig.');$rows=[];
    foreach(scandir($root)?:[]as$key){if($key==='.'||$key==='..'||!runtime41CanoniekeTenantKey($key))continue;$tenant=$root.'/'.$key;if(is_link($tenant)||!is_dir($tenant))continue;$planPad=$tenant.'/lifecycle/lifecycle-plan.json';if(!is_file($planPad)||is_link($planPad))continue;
        try{$ctx=lifecycle48PlanLeesEnValideer($planPad);$p=$ctx['plan'];$s=cpeState($p);$rows[]=['tenant_key'=>$key,'canonical_host'=>(string)$p['canonical_host'],'status'=>$s['status'],'transition'=>$s['transition'],'healthy'=>cpeGezond($p,$s['status']),'updated_at_utc'=>$s['updated_at_utc'],'last_export'=>cpeVeiligeExport($s['last_export']),'delete_export'=>cpeVeiligeExport($s['delete_export']),'purge_not_before_utc'=>$s['purge_not_before_utc']];}
        catch(Throwable$e){$rows[]=['tenant_key'=>$key,'canonical_host'=>'','status'=>'invalid','transition'=>null,'healthy'=>false,'updated_at_utc'=>null,'last_export'=>null,'delete_export'=>null,'purge_not_before_utc'=>null];}
    }
    usort($rows,fn($a,$b)=>strcmp($a['tenant_key'],$b['tenant_key']));return['schema'=>1,'phase'=>'5.1-snapshot','generated_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'tenants'=>$rows];
}
function cpeRequest(string$f):array
{
    if(is_link($f)||!is_file($f))throw new RuntimeException('Queue-item is geen veilig regulier bestand.');$base=basename($f);if(preg_match('/^([0-9a-f]{32})\.json$/D',$base,$m)!==1)throw new RuntimeException('Queue-bestandsnaam is ongeldig.');$raw=@file_get_contents($f);try{$r=is_string($raw)?json_decode($raw,true,64,JSON_THROW_ON_ERROR):null;}catch(Throwable$e){$r=null;}
    if(!is_array($r)||(int)($r['schema']??0)!==1||($r['phase']??'')!=='5.1-request'||!hash_equals($m[1],(string)($r['request_id']??'')))throw new RuntimeException('Queue-schema of request-id is ongeldig.');
    if(!runtime41CanoniekeTenantKey((string)($r['tenant_key']??'')))throw new RuntimeException('Queue bevat ongeldige tenant-key.');if(preg_match('/^[A-Za-z0-9][A-Za-z0-9._@-]{1,63}$/D',(string)($r['operator']??''))!==1)throw new RuntimeException('Queue bevat ongeldige operator.');
    $actions=['adopt-active','suspend','activate','recover','export','delete','cancel-delete','purge'];if(!in_array((string)($r['action']??''),$actions,true))throw new RuntimeException('Queue bevat niet-toegestane actie.');$ts=strtotime((string)($r['requested_at_utc']??''));if($ts===false||abs(time()-$ts)>900)throw new RuntimeException('Queue-aanvraag is verlopen.');if(!is_array($r['confirm']??null))throw new RuntimeException('Queue-confirmatieschema ontbreekt.');return$r;
}
function cpeCommand(array$c,array$r):array
{
    $key=$r['tenant_key'];$plan=$c['tenants_root'].'/'.$key.'/lifecycle/lifecycle-plan.json';$ctx=lifecycle48PlanLeesEnValideer($plan);if(!hash_equals($key,(string)$ctx['plan']['tenant_key']))throw new RuntimeException('Lifecycleplan hoort bij andere tenant.');$a=$r['action'];$cmd=['/usr/bin/php',$c['lifecycle_apply'],'--plan='.$plan,'--'.$a];
    if(in_array($a,['delete','purge'],true)){$ct=(string)($r['confirm']['tenant']??'');$sha=(string)($r['confirm']['export_sha256']??'');if(!hash_equals($key,$ct)||preg_match('/^[0-9a-f]{64}$/D',$sha)!==1)throw new RuntimeException('Destructieve bevestiging is ongeldig.');$cmd[]='--confirm-tenant='.$ct;$cmd[]='--confirm-export-sha='.$sha;}
    if($a==='purge'){if(!hash_equals('VERWIJDER-DEFINITIEF',(string)($r['confirm']['purge']??'')))throw new RuntimeException('Purgebevestiging is ongeldig.');$cmd[]='--confirm-purge=VERWIJDER-DEFINITIEF';}
    return$cmd;
}
function cpeResult(array$c,array$r,string$result,string$message):void
{
    cpeDir($c['results_dir'],0750,0,$c['runtime_user']);$safe=substr(preg_replace('/\s+/',' ',trim($message))??'',0,500);cpeWrite($c['results_dir'].'/'.$r['request_id'].'.json',['schema'=>1,'phase'=>'5.1-result','request_id'=>$r['request_id'],'tenant_key'=>$r['tenant_key'],'action'=>$r['action'],'operator'=>$r['operator'],'result'=>$result,'message'=>$safe,'completed_at_utc'=>gmdate('Y-m-d\TH:i:s\Z')],0640,$c['runtime_user']);
}

foreach($_SERVER['argv']??[]as$a)if(preg_match('/^--(?:password|pass|secret|token|credential|webhook)(?:=|$)/i',(string)$a)===1)cpeStop('Secrets horen niet in executor-argumenten.');
$o=getopt('',['config:','refresh-only','help']);if(isset($o['help'])){echo"Gebruik: sudo php bin/control-plane-executor.php --config=/etc/verenigingsplatform/control-plane/runtime.json [--refresh-only]\n";exit(0);}if(PHP_OS_FAMILY!=='Linux'||!function_exists('posix_geteuid')||posix_geteuid()!==0)cpeStop('Control-plane executor vereist Linux root.');
try{$c=cpeConfig(trim((string)($o['config']??'')));$lockDir=dirname($c['executor_lock']);if(runtime41SymlinkInPad($lockDir)!==null||!is_dir($lockDir))throw new RuntimeException('Systeem-lockmap ontbreekt of is onveilig.');$lh=@fopen($c['executor_lock'],'c');if(!is_resource($lh)||!flock($lh,LOCK_EX|LOCK_NB))throw new RuntimeException('Er draait al een control-plane executor.');@chown($c['executor_lock'],0);@chgrp($c['executor_lock'],0);@chmod($c['executor_lock'],0600);
    cpeDir($c['pending_dir'],0730,$c['runtime_user'],$c['runtime_user']);cpeDir($c['processing_dir'],0700,0,0);cpeDir($c['results_dir'],0750,0,$c['runtime_user']);
    cpeWrite($c['snapshot_file'],cpeSnapshot($c),0640,$c['runtime_user']);if(isset($o['refresh-only'])){echo"REFRESH OK\n";exit(0);}foreach(glob($c['pending_dir'].'/*.json')?:[]as$f){$id=pathinfo($f,PATHINFO_FILENAME);$dst=$c['processing_dir'].'/'.$id.'.json';if(is_link($f)||file_exists($dst)||!@rename($f,$dst))continue;$r=['request_id'=>$id,'tenant_key'=>null,'action'=>null,'operator'=>null];try{$r=cpeRequest($dst);$cmd=cpeCommand($c,$r);[$code,$out,$err]=cpeRun($cmd);if($code!==0)throw new RuntimeException($err!==''?$err:$out);$msg=$out!==''?$out:'OK';cpeResult($c,$r,'ok',$msg);cpeAudit($c,$r,'ok',$msg);}catch(Throwable$e){$msg=$e->getMessage();try{cpeResult($c,$r,'failed',$msg);}catch(Throwable$ignored){}try{cpeAudit($c,$r,'failed',$msg);}catch(Throwable$ignored){}}finally{@unlink($dst);}cpeWrite($c['snapshot_file'],cpeSnapshot($c),0640,$c['runtime_user']);}
    echo"EXECUTOR OK\n";
}catch(Throwable$e){cpeStop($e->getMessage());}
