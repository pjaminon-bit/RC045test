<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Alleen via CLI beschikbaar.'); }
require_once dirname(__DIR__) . '/app/deployment/lifecycle-contract.php';
require_once dirname(__DIR__) . '/app/deployment/process-runner.php';

function cpeStop(string $m, int $c=1): never { fwrite(STDERR,"FOUT: {$m}\n"); exit($c); }
function cpeRun(array $cmd): array
{
    return process521Run($cmd, null, null, null, 3600);
}
function cpeAbs(string$p):bool{return str_starts_with($p,'/')&&!str_contains($p,"\0")&&!preg_match('#(?:^|/)\.\.?(/|$)#',$p);}
function cpeUid(string|int$owner):int{if(is_int($owner)||ctype_digit((string)$owner))return(int)$owner;if(!function_exists('posix_getpwnam'))throw new RuntimeException('Ownercontrole vereist posix_getpwnam.');$u=@posix_getpwnam((string)$owner);if(!is_array($u))throw new RuntimeException('Verwachte owner bestaat niet: '.$owner);return(int)$u['uid'];}
function cpeGid(string|int$group):int{if(is_int($group)||ctype_digit((string)$group))return(int)$group;if(!function_exists('posix_getgrnam'))throw new RuntimeException('Groepscontrole vereist posix_getgrnam.');$g=@posix_getgrnam((string)$group);if(!is_array($g))throw new RuntimeException('Verwachte groep bestaat niet: '.$group);return(int)$g['gid'];}
function cpeMeta(string$p,int$mode,bool$dir,string|int$owner=0,string|int$group=0):void{$s=@lstat($p);if(!is_array($s)||is_link($p)||($dir?!is_dir($p):!is_file($p))||(int)$s['uid']!==cpeUid($owner)||(int)$s['gid']!==cpeGid($group)||(((int)$s['mode']&0777)!==$mode))throw new RuntimeException('Control-plane owner/group/mode wijkt af: '.$p);}
function cpeConfig(string $pad): array
{
    if(!cpeAbs($pad)||runtime41SymlinkInPad($pad)!==null||!is_file($pad))throw new RuntimeException('Control-plane runtimeconfig is onveilig.');
    $raw=@file_get_contents($pad);try{$c=is_string($raw)?json_decode($raw,true,64,JSON_THROW_ON_ERROR):null;}catch(Throwable$e){$c=null;}
    $keys=['host','app_root','tenants_root','runtime_user','pending_dir','processing_dir','results_dir','sessions_dir','snapshot_file','executor_lock','audit_file','lifecycle_apply'];
    if(!is_array($c)||(int)($c['schema']??0)!==1||($c['phase']??'')!=='5.1-runtime')throw new RuntimeException('Control-plane runtimeconfig heeft onbekend schema.');
    foreach($keys as$k)if(!isset($c[$k])||!is_string($c[$k])||$c[$k]==='')throw new RuntimeException('Runtimeconfig mist '.$k.'.');
    foreach(['app_root','tenants_root','pending_dir','processing_dir','results_dir','sessions_dir','snapshot_file','executor_lock','audit_file','lifecycle_apply']as$k)if(!cpeAbs($c[$k]))throw new RuntimeException('Onveilig runtimepad: '.$k);
    return$c;
}
function cpeDir(string$p,int$mode=0750,string|int$owner=0,string|int$group=0):void
{
    if(runtime41SymlinkInPad($p)!==null)throw new RuntimeException('Symlink in control-plane map: '.$p);if(!is_dir($p)&&!@mkdir($p,$mode,true)&&!is_dir($p))throw new RuntimeException('Map kon niet worden gemaakt: '.$p);if(!@chown($p,$owner)||!@chgrp($p,$group)||!@chmod($p,$mode))throw new RuntimeException('Control-plane maprechten konden niet exact worden gezet: '.$p);cpeMeta($p,$mode,true,$owner,$group);
}
function cpeWrite(string$p,array$d,int$mode=0640,string|int$group='vst-control'):void
{
    if(runtime41SymlinkInPad($p)!==null)throw new RuntimeException('Symlink in control-plane writepad.');$j=json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);if(!is_string($j))throw new RuntimeException('JSON write faalde.');$tmp=dirname($p).'/.'.basename($p).'.tmp.'.bin2hex(random_bytes(5));if(@file_put_contents($tmp,$j."\n",LOCK_EX)===false)throw new RuntimeException('Tijdelijke write faalde.');if(!@chown($tmp,0)||!@chgrp($tmp,$group)||!@chmod($tmp,$mode)){@unlink($tmp);throw new RuntimeException('Tijdelijke control-plane write kon niet veilig worden gemetadateerd.');}cpeMeta($tmp,$mode,false,0,$group);if(is_link($p)||!@rename($tmp,$p)){@unlink($tmp);throw new RuntimeException('Atomische write faalde.');}if(!@chown($p,0)||!@chgrp($p,$group)||!@chmod($p,$mode))throw new RuntimeException('Control-plane write-rechten konden niet worden genormaliseerd: '.$p);cpeMeta($p,$mode,false,0,$group);
}
function cpeAudit(array$c,array$r,string$result,string$message=''):void
{
    cpeDir(dirname($c['audit_file']),0750,0,'adm');$row=['timestamp_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'request_id'=>$r['request_id']??null,'operator'=>$r['operator']??null,'tenant_key'=>$r['tenant_key']??null,'action'=>$r['action']??null,'result'=>$result];if($message!=='')$row['message']=substr(preg_replace('/\s+/',' ',trim($message))??'',0,300);$j=json_encode($row,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);if(!is_string($j)||@file_put_contents($c['audit_file'],$j."\n",FILE_APPEND|LOCK_EX)===false)throw new RuntimeException('Control-plane auditwrite faalde.');if(!@chown($c['audit_file'],0)||!@chgrp($c['audit_file'],'adm')||!@chmod($c['audit_file'],0640))throw new RuntimeException('Control-plane auditmetadata kon niet worden genormaliseerd.');cpeMeta($c['audit_file'],0640,false,0,'adm');
}
function cpeUnlink(string$p,string$label):void{if(is_link($p)||is_file($p)){if(!@unlink($p))throw new RuntimeException($label.' kon niet worden verwijderd: '.$p);clearstatcache(true,$p);if(file_exists($p)||is_link($p))throw new RuntimeException($label.' bleef bestaan na verwijdering: '.$p);}elseif(file_exists($p))throw new RuntimeException($label.' is geen veilig regulier bestand: '.$p);}
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
function cpeProvisionManifestRow(string$tenant,string$key):array
{
    $manifestPad=$tenant.'/tenant.json';$configPad=$tenant.'/config.php';$envPad=$tenant.'/runtime.env';$private=$tenant.'/private';
    if(is_link($manifestPad)||!is_file($manifestPad)||is_link($configPad)||!is_file($configPad)||is_link($envPad)||!is_file($envPad)||is_link($private)||!is_dir($private))throw new RuntimeException('Basisprovisioning is onvolledig.');
    $raw=@file_get_contents($manifestPad);try{$m=is_string($raw)?json_decode($raw,true,64,JSON_THROW_ON_ERROR):null;}catch(Throwable$e){$m=null;}
    if(!is_array($m)||(int)($m['schema']??0)!==1||!hash_equals($key,(string)($m['tenant_key']??''))||($m['require_tenant_config']??false)!==true)throw new RuntimeException('Tenantmanifest is ongeldig.');
    $url=(string)($m['site_url']??'');$parts=parse_url($url);$host=strtolower((string)($parts['host']??''));
    if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'||isset($parts['user'])||isset($parts['pass'])||isset($parts['port'])||isset($parts['query'])||isset($parts['fragment'])||!web42CanoniekeHost($host))throw new RuntimeException('Tenantmanifest bevat geen geldige productiehost.');
    $path=(string)($parts['path']??'');if($path!==''&&$path!=='/')throw new RuntimeException('Tenantmanifest bevat een URL-subpad.');
    $mtime=@filemtime($manifestPad);$updated=is_int($mtime)&&$mtime>0?gmdate('Y-m-d\TH:i:s\Z',$mtime):null;
    return['tenant_key'=>$key,'canonical_host'=>$host,'status'=>'setup_required','transition'=>null,'healthy'=>false,'updated_at_utc'=>$updated,'last_export'=>null,'delete_export'=>null,'purge_not_before_utc'=>null];
}
function cpeSnapshot(array$c):array
{
    $root=$c['tenants_root'];if(runtime41SymlinkInPad($root)!==null||!is_dir($root))throw new RuntimeException('Tenantroot ontbreekt of is onveilig.');$rows=[];
    foreach(scandir($root)?:[]as$key){if($key==='.'||$key==='..'||!runtime41CanoniekeTenantKey($key))continue;$tenant=$root.'/'.$key;if(is_link($tenant)||!is_dir($tenant))continue;$planPad=$tenant.'/lifecycle/lifecycle-plan.json';
        if(is_file($planPad)&&!is_link($planPad)){
            try{$ctx=lifecycle48PlanLeesEnValideer($planPad);$p=$ctx['plan'];$s=cpeState($p);$rows[]=['tenant_key'=>$key,'canonical_host'=>(string)$p['canonical_host'],'status'=>$s['status'],'transition'=>$s['transition'],'healthy'=>cpeGezond($p,$s['status']),'updated_at_utc'=>$s['updated_at_utc'],'last_export'=>cpeVeiligeExport($s['last_export']),'delete_export'=>cpeVeiligeExport($s['delete_export']),'purge_not_before_utc'=>$s['purge_not_before_utc']];}
            catch(Throwable$e){$rows[]=['tenant_key'=>$key,'canonical_host'=>'','status'=>'invalid','transition'=>null,'healthy'=>false,'updated_at_utc'=>null,'last_export'=>null,'delete_export'=>null,'purge_not_before_utc'=>null];}
            continue;
        }
        try{$rows[]=cpeProvisionManifestRow($tenant,$key);}catch(Throwable$e){$rows[]=['tenant_key'=>$key,'canonical_host'=>'','status'=>'invalid','transition'=>null,'healthy'=>false,'updated_at_utc'=>null,'last_export'=>null,'delete_export'=>null,'purge_not_before_utc'=>null];}
    }
    usort($rows,fn($a,$b)=>strcmp($a['tenant_key'],$b['tenant_key']));return['schema'=>1,'phase'=>'5.1-snapshot','generated_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'tenants'=>$rows];
}
function cpeProvisionModules():array{return['website','ledenadministratie','werkgroepen','evenementen','vergaderingen','taken','operationele_taken','fotoboek','sponsors','media','aanmelden'];}
function cpeProvisionTenantKey(string$key):string
{
    if(strlen($key)<3||strlen($key)>63||$key==='default'||str_contains($key,'--')||preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D',$key)!==1||!runtime41CanoniekeTenantKey($key))throw new RuntimeException('Provisioning tenant-key is niet canoniek.');return$key;
}
function cpeProvisionPayload(array$r):array
{
    cpeProvisionTenantKey((string)($r['tenant_key']??''));$p=$r['provision']??null;if(!is_array($p))throw new RuntimeException('Provisioningpayload ontbreekt.');$keys=array_keys($p);sort($keys,SORT_STRING);if($keys!==['host','modules','name'])throw new RuntimeException('Provisioningpayload bevat onbekende velden.');
    $name=trim((string)($p['name']??''));if($name===''||mb_strlen($name)>120||preg_match('/[\x00-\x1F\x7F]/u',$name)===1)throw new RuntimeException('Provisioningnaam is ongeldig.');
    $host=(string)($p['host']??'');if($host!==strtolower(trim($host))||!web42CanoniekeHost($host))throw new RuntimeException('Provisioninghost is niet canoniek.');
    $mods=$p['modules']??null;if(!is_array($mods)||!array_is_list($mods)||$mods===[])throw new RuntimeException('Provisioningmodules ontbreken.');$seen=[];foreach($mods as$m){if(!is_string($m)||!in_array($m,cpeProvisionModules(),true)||isset($seen[$m]))throw new RuntimeException('Provisioningmodules zijn ongeldig.');$seen[$m]=true;}if(!isset($seen['website']))throw new RuntimeException('Provisioning mist kernmodule website.');$canon=[];foreach(cpeProvisionModules()as$m)if(isset($seen[$m]))$canon[]=$m;
    return['name'=>$name,'host'=>$host,'modules'=>$canon];
}
function cpeRequest(string$f):array
{
    if(is_link($f)||!is_file($f))throw new RuntimeException('Queue-item is geen veilig regulier bestand.');$base=basename($f);if(preg_match('/^([0-9a-f]{32})\.json$/D',$base,$m)!==1)throw new RuntimeException('Queue-bestandsnaam is ongeldig.');$raw=@file_get_contents($f);try{$r=is_string($raw)?json_decode($raw,true,64,JSON_THROW_ON_ERROR):null;}catch(Throwable$e){$r=null;}
    if(!is_array($r)||(int)($r['schema']??0)!==1||($r['phase']??'')!=='5.1-request'||!hash_equals($m[1],(string)($r['request_id']??'')))throw new RuntimeException('Queue-schema of request-id is ongeldig.');
    if(!runtime41CanoniekeTenantKey((string)($r['tenant_key']??'')))throw new RuntimeException('Queue bevat ongeldige tenant-key.');if(preg_match('/^[A-Za-z0-9][A-Za-z0-9._@-]{1,63}$/D',(string)($r['operator']??''))!==1)throw new RuntimeException('Queue bevat ongeldige operator.');
    $lifecycleActions=['adopt-active','suspend','activate','recover','export','delete','cancel-delete','purge'];$actions=array_merge(['provision'],$lifecycleActions);$action=(string)($r['action']??'');if(!in_array($action,$actions,true))throw new RuntimeException('Queue bevat niet-toegestane actie.');$ts=strtotime((string)($r['requested_at_utc']??''));if($ts===false||abs(time()-$ts)>900)throw new RuntimeException('Queue-aanvraag is verlopen.');if(!is_array($r['confirm']??null))throw new RuntimeException('Queue-confirmatieschema ontbreekt.');
    $verwacht=['action','confirm','operator','phase','request_id','requested_at_utc','schema','tenant_key'];if($action==='provision')$verwacht[]='provision';sort($verwacht,SORT_STRING);$werkelijk=array_keys($r);sort($werkelijk,SORT_STRING);if($werkelijk!==$verwacht)throw new RuntimeException('Queue bevat onbekende top-level velden.');
    if($action==='provision'){if(($r['confirm']??[])!==[])throw new RuntimeException('Provisioning accepteert geen confirmatievelden.');cpeProvisionPayload($r);}elseif(isset($r['provision']))throw new RuntimeException('Lifecycle-aanvraag bevat onverwachte provisioningdata.');
    return$r;
}
function cpeProvisionUniek(array$c,array$r):void
{
    $key=(string)$r['tenant_key'];$p=cpeProvisionPayload($r);$doel=$c['tenants_root'].'/'.$key;if(file_exists($doel)||is_link($doel))throw new RuntimeException('Tenant-key bestaat al op de server.');
    foreach(cpeSnapshot($c)['tenants']as$t){if(hash_equals($key,(string)($t['tenant_key']??'')))throw new RuntimeException('Tenant-key bestaat al in platformstatus.');$h=strtolower((string)($t['canonical_host']??''));if($h!==''&&hash_equals($p['host'],$h))throw new RuntimeException('Domeinnaam is al aan een tenant gekoppeld.');}
    foreach(scandir($c['tenants_root'])?:[]as$other){if($other==='.'||$other==='..'||!runtime41CanoniekeTenantKey($other))continue;$manifest=$c['tenants_root'].'/'.$other.'/tenant.json';if(is_link($manifest)||!is_file($manifest))continue;$raw=@file_get_contents($manifest);$m=is_string($raw)?json_decode($raw,true):null;if(!is_array($m))continue;$parts=parse_url((string)($m['site_url']??''));$h=is_array($parts)?strtolower((string)($parts['host']??'')):'';if($h!==''&&hash_equals($p['host'],$h))throw new RuntimeException('Domeinnaam is al aanwezig in een tenantmanifest.');}
}
function cpeProvisionCommand(array$c,array$r,bool$dryRun=false):array
{
    $p=cpeProvisionPayload($r);$php=PHP_BINARY;if(!preg_match('#^/usr/bin/php[0-9]{1,2}\.[0-9]{1,2}$#D',$php)||!is_file($php)||!is_executable($php))throw new RuntimeException('Executor draait niet met een exact gepinde productie-PHP-binary.');$script=$c['app_root'].'/bin/provision-tenant.php';if(!is_file($script)||is_link($script))throw new RuntimeException('Tenant-provisioner ontbreekt of is onveilig.');
    $cmd=[$php,$script,'--key='.$r['tenant_key'],'--name='.$p['name'],'--url=https://'.$p['host'],'--root='.$c['tenants_root'],'--driver=pdo','--modules='.implode(',',$p['modules'])];if($dryRun)$cmd[]='--dry-run';return$cmd;
}
function cpeCommand(array$c,array$r):array
{
    $key=$r['tenant_key'];$plan=$c['tenants_root'].'/'.$key.'/lifecycle/lifecycle-plan.json';$ctx=lifecycle48PlanLeesEnValideer($plan);if(!hash_equals($key,(string)$ctx['plan']['tenant_key']))throw new RuntimeException('Lifecycleplan hoort bij andere tenant.');$php=(string)($ctx['plan']['runtime']['php_binary']??'');if(!preg_match('#^/usr/bin/php[0-9]{1,2}\.[0-9]{1,2}$#D',$php)||!is_file($php)||!is_executable($php))throw new RuntimeException('Lifecycleplan bevat geen beschikbare exacte tenant-PHP-binary.');$a=$r['action'];$cmd=[$php,$c['lifecycle_apply'],'--plan='.$plan,'--'.$a];
    if(in_array($a,['delete','purge'],true)){$ct=(string)($r['confirm']['tenant']??'');$sha=(string)($r['confirm']['export_sha256']??'');if(!hash_equals($key,$ct)||preg_match('/^[0-9a-f]{64}$/D',$sha)!==1)throw new RuntimeException('Destructieve bevestiging is ongeldig.');$cmd[]='--confirm-tenant='.$ct;$cmd[]='--confirm-export-sha='.$sha;}
    if($a==='purge'){if(!hash_equals('VERWIJDER-DEFINITIEF',(string)($r['confirm']['purge']??'')))throw new RuntimeException('Purgebevestiging is ongeldig.');$cmd[]='--confirm-purge=VERWIJDER-DEFINITIEF';}
    return$cmd;
}
function cpeUitvoeren(array$c,array$r):array
{
    if(($r['action']??'')!=='provision')return cpeRun(cpeCommand($c,$r));
    cpeProvisionUniek($c,$r);[$preCode,$preOut,$preErr]=cpeRun(cpeProvisionCommand($c,$r,true));if($preCode!==0)return[$preCode,$preOut,$preErr];
    [$code,$out,$err]=cpeRun(cpeProvisionCommand($c,$r,false));if($code===0)$out='Basisprovisioning voltooid. Activeer nu de eerste beheerder en rond daarna de VPS-infrastructuur af.';return[$code,$out,$err];
}
function cpeResult(array$c,array$r,string$result,string$message):void
{
    cpeDir($c['results_dir'],0750,0,$c['runtime_user']);$safe=substr(preg_replace('/\s+/',' ',trim($message))??'',0,500);cpeWrite($c['results_dir'].'/'.$r['request_id'].'.json',['schema'=>1,'phase'=>'5.1-result','request_id'=>$r['request_id'],'tenant_key'=>$r['tenant_key'],'action'=>$r['action'],'operator'=>$r['operator'],'result'=>$result,'message'=>$safe,'completed_at_utc'=>gmdate('Y-m-d\TH:i:s\Z')],0640,$c['runtime_user']);
}

foreach($_SERVER['argv']??[]as$a)if(preg_match('/^--(?:password|pass|secret|token|credential|webhook)(?:=|$)/i',(string)$a)===1)cpeStop('Secrets horen niet in executor-argumenten.');
$o=getopt('',['config:','refresh-only','help']);if(isset($o['help'])){echo"Gebruik: sudo php bin/control-plane-executor.php --config=/etc/verenigingsplatform/control-plane/runtime.json [--refresh-only]\n";exit(0);}if(PHP_OS_FAMILY!=='Linux'||!function_exists('posix_geteuid')||posix_geteuid()!==0)cpeStop('Control-plane executor vereist Linux root.');
try{$c=cpeConfig(trim((string)($o['config']??'')));$lockDir=dirname($c['executor_lock']);if(runtime41SymlinkInPad($lockDir)!==null||!is_dir($lockDir))throw new RuntimeException('Systeem-lockmap ontbreekt of is onveilig.');$lh=@fopen($c['executor_lock'],'c');if(!is_resource($lh))throw new RuntimeException('Control-plane executor-lock kon niet worden geopend.');if(!@chown($c['executor_lock'],0)||!@chgrp($c['executor_lock'],0)||!@chmod($c['executor_lock'],0600)){fclose($lh);throw new RuntimeException('Control-plane executor-lock kon niet root-only worden gemaakt.');}cpeMeta($c['executor_lock'],0600,false,0,0);if(!flock($lh,LOCK_EX|LOCK_NB)){fclose($lh);throw new RuntimeException('Er draait al een control-plane executor.');}
    cpeDir($c['pending_dir'],0730,$c['runtime_user'],$c['runtime_user']);cpeDir($c['processing_dir'],0700,0,0);cpeDir($c['results_dir'],0750,0,$c['runtime_user']);
    cpeWrite($c['snapshot_file'],cpeSnapshot($c),0640,$c['runtime_user']);if(isset($o['refresh-only'])){echo"REFRESH OK\n";exit(0);}
    while(true){$files=glob($c['pending_dir'].'/*.json')?:[];if($files===[])break;sort($files,SORT_STRING);$verwerkt=0;foreach($files as$f){$id=pathinfo($f,PATHINFO_FILENAME);$dst=$c['processing_dir'].'/'.$id.'.json';if(is_link($f)||file_exists($dst)||!@rename($f,$dst))continue;$verwerkt++;$r=['request_id'=>$id,'tenant_key'=>null,'action'=>null,'operator'=>null];try{$r=cpeRequest($dst);[$code,$out,$err]=cpeUitvoeren($c,$r);if($code!==0)throw new RuntimeException($err!==''?$err:$out);$msg=$out!==''?$out:'OK';cpeResult($c,$r,'ok',$msg);cpeAudit($c,$r,'ok',$msg);}catch(Throwable$e){$msg=$e->getMessage();try{cpeResult($c,$r,'failed',$msg);}catch(Throwable$ignored){}try{cpeAudit($c,$r,'failed',$msg);}catch(Throwable$ignored){}}finally{cpeUnlink($dst,'Verwerkt control-plane queue-item');}cpeWrite($c['snapshot_file'],cpeSnapshot($c),0640,$c['runtime_user']);}if($verwerkt===0)break;}
    cpeWrite($c['snapshot_file'],cpeSnapshot($c),0640,$c['runtime_user']);echo"EXECUTOR OK\n";
}catch(Throwable$e){cpeStop($e->getMessage());}
