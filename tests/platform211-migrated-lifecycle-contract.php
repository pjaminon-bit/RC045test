<?php
$root = dirname(__DIR__); $ok=0; $fout=0;
function p211lcCheck(bool $c,string $l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}
function p211lcRm(string$p):void{if(is_link($p)||is_file($p)){@unlink($p);return;}if(!is_dir($p))return;foreach(scandir($p)?:[]as$n){if($n==='.'||$n==='..')continue;p211lcRm($p.'/'.$n);}@rmdir($p);}
function p211lcRun(array$a,?string$in=null):array{$d=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];$p=proc_open($a,$d,$x,null,null,['bypass_shell'=>true]);if(!is_resource($p))return[255,'','proc_open mislukt'];if($in!==null)fwrite($x[0],$in);fclose($x[0]);$o=stream_get_contents($x[1]);fclose($x[1]);$e=stream_get_contents($x[2]);fclose($x[2]);return[proc_close($p),trim((string)$o),trim((string)$e)];}
function p211lcCliFailure(string$label,int$code,string$out,string$err):void{if($code===0)return;fwrite(STDERR,"CLI {$label}: exitcode={$code}\n");if($out!=='')fwrite(STDERR,"stdout:\n{$out}\n");if($err!=='')fwrite(STDERR,"stderr:\n{$err}\n");}

require_once $root.'/app/deployment/lifecycle-contract.php';
require_once $root.'/app/core/tenant-runtime.php';
require_once $root.'/app/storage/private-store-runtime-state.php';

function p211lcReady(string$dns,string$tenant,string$host,string$ip):string{
    $j=json_decode((string)file_get_contents($dns),true);$now=time();$p=dirname($dns).'/dns-readiness.json';
    $s=['schema'=>1,'phase'=>'4.3-readiness','tenant_key'=>$tenant,'canonical_host'=>$host,'strategy'=>'direct','ready'=>true,'resolver_mode'=>'system','checked_at_utc'=>gmdate('Y-m-d\\TH:i:s\\Z',$now),'expires_at_utc'=>gmdate('Y-m-d\\TH:i:s\\Z',$now+900),'source'=>['dns_plan_file'=>$dns,'dns_plan_sha256'=>hash_file('sha256',$dns),'web_plan_sha256'=>$j['source']['web_plan_sha256']],'propagation'=>['sample_count'=>3,'interval_seconds'=>2,'scope'=>'configured-system-resolver'],'observed'=>['owner'=>['a'=>[$ip],'aaaa'=>[],'cname'=>[],'ttl_min'=>60],'terminal'=>null]];
    file_put_contents($p,dns43Json($s));@chmod($p,0640);return$p;
}

$tmp=sys_get_temp_dir().'/platform211-lifecycle-'.bin2hex(random_bytes(5));$base=$tmp.'/tenants';@mkdir($base,0750,true);
$tenant='migrated-lifecycle';$t=$base.'/'.$tenant;
try{
    [$pc,$po,$pe]=p211lcRun([PHP_BINARY,$root.'/bin/provision-tenant.php','--key='.$tenant,'--name=Migrated Lifecycle','--url=https://'.$tenant.'.example','--root='.$base,'--driver=json','--modules=website,ledenadministratie']);
    p211lcCliFailure('provision-tenant',$pc,$po,$pe);
    p211lcCheck($pc===0,'JSON-geprovisioneerde tenantfixture is aangemaakt');
    [$bc,$bo,$be]=p211lcRun([PHP_BINARY,$root.'/bin/bootstrap-tenant-admin.php','--config='.$t.'/config.php','--password-stdin'],'Lifecycle-Migrate-Admin-2026!'."\n");
    p211lcCliFailure('bootstrap-tenant-admin',$bc,$bo,$be);
    p211lcCheck($bc===0,'tenantfixture heeft geldige private/bootstrapcontext');

    $prep=[
        ['prepare-vps-deployment',[PHP_BINARY,$root.'/bin/prepare-vps-deployment.php','--config='.$t.'/config.php','--app-root='.$root]],
        ['prepare-vps-runtime',[PHP_BINARY,$root.'/bin/prepare-vps-runtime.php','--deployment='.$t.'/deployment.json']],
        ['prepare-vps-webserver',[PHP_BINARY,$root.'/bin/prepare-vps-webserver.php','--runtime-plan='.$t.'/runtime/runtime-plan.json']],
        ['prepare-vps-dns',[PHP_BINARY,$root.'/bin/prepare-vps-dns.php','--web-plan='.$t.'/webserver/web-plan.json','--strategy=direct','--ipv4=203.0.113.73']],
    ];
    $prepOk=true;foreach($prep as[$label,$c]){[$code,$out,$err]=p211lcRun($c);if($code!==0){$prepOk=false;p211lcCliFailure($label,$code,$out,$err);break;}}
    p211lcCheck($prepOk,'JSON-tenant doorloopt deployment/runtime/web/DNS plangeneratie');

    $ready=p211lcReady($t.'/dns/dns-plan.json',$tenant,$tenant.'.example','203.0.113.73');
    [$tc,$to,$te]=p211lcRun([PHP_BINARY,$root.'/bin/prepare-vps-tls.php','--dns-readiness='.$ready]);
    [$dc,$do,$de]=p211lcRun([PHP_BINARY,$root.'/bin/prepare-vps-database.php','--runtime-plan='.$t.'/runtime/runtime-plan.json','--migration-target']);
    [$mc,$mo,$me]=p211lcRun([PHP_BINARY,$root.'/bin/prepare-vps-monitoring.php','--tls-plan='.$t.'/tls/tls-plan.json','--database-plan='.$t.'/database/database-plan.json','--alerts=disabled']);
    [$lc,$lo,$le]=p211lcRun([PHP_BINARY,$root.'/bin/prepare-vps-lifecycle.php','--monitoring-plan='.$t.'/monitoring/monitoring-plan.json']);
    p211lcCliFailure('prepare-vps-tls',$tc,$to,$te);p211lcCliFailure('prepare-vps-database',$dc,$do,$de);p211lcCliFailure('prepare-vps-monitoring',$mc,$mo,$me);p211lcCliFailure('prepare-vps-lifecycle',$lc,$lo,$le);
    p211lcCheck($tc===0&&$dc===0&&$mc===0&&$lc===0,'migration-target database → monitoring → lifecycle bronketen wordt geldig opgebouwd');

    $db=json_decode((string)file_get_contents($t.'/database/database-plan.json'),true);
    $mon=json_decode((string)file_get_contents($t.'/monitoring/monitoring-plan.json'),true);
    $lifePad=$t.'/lifecycle/lifecycle-plan.json';$lifeRaw=(string)file_get_contents($lifePad);$life=json_decode($lifeRaw,true);
    p211lcCheck(($db['source']['migration_target']??false)===true,'fase-4.5 plan is expliciet een JSON migration-target');
    p211lcCheck(
        is_array($mon)&&is_array($life)
        &&($mon['source']['database_plan_sha256']??'')===hash_file('sha256',$t.'/database/database-plan.json')
        &&($life['source']['database_plan_sha256']??'')===hash_file('sha256',$t.'/database/database-plan.json')
        &&($life['source']['monitoring_plan_sha256']??'')===hash_file('sha256',$t.'/monitoring/monitoring-plan.json'),
        'monitoring en lifecycle zijn byte-exact aan het migration-target gebonden'
    );
    p211lcCheck(($life['database']['database']??'')===($db['isolation']['database']??''),'lifecycle-export gebruikt exact de migration-target PostgreSQL-database');

    [$cc,$co,$ce]=p211lcRun([PHP_BINARY,$root.'/bin/apply-vps-lifecycle.php','--plan='.$lifePad,'--check']);
    p211lcCliFailure('apply-vps-lifecycle pre-cutover check',$cc,$co,$ce);
    p211lcCheck($cc===0&&str_contains($co,'CHECK OK'),'lifecyclecontract valideert vóór cutover');

    $private=$t.'/private';$proofDir=$private.'/migrations/private-json-to-pdo';$runtimeDir=$t.'/storage-runtime';@mkdir($proofDir,0750,true);@mkdir($runtimeDir,0750,true);
    $targetHash=hash('sha256','platform211-lifecycle-target');$proofPad=$proofDir.'/proof.json';
    $proof=['schema'=>1,'phase'=>'private-json-to-pdo','status'=>'verified','tenant_key'=>$tenant,'target'=>['aggregate_sha256'=>$targetHash]];
    file_put_contents($proofPad,json_encode($proof,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");@chmod($proofPad,0640);
    $proofSha=hash_file('sha256',$proofPad);
    $state=['schema'=>1,'phase'=>'json-to-pdo-cutover','tenant_key'=>$tenant,'source_driver'=>'json','effective_driver'=>'pdo','created_at'=>gmdate('c'),'proof_path'=>$proofPad,'proof_sha256'=>$proofSha,'target_aggregate_sha256'=>$targetHash];
    file_put_contents($runtimeDir.'/private-store-runtime.json',json_encode($state,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");@chmod($runtimeDir.'/private-store-runtime.json',0640);
    putenv('VERENIGING_CONFIG_FILE='.$t.'/config.php');
    $cfg=require $t.'/config.php';
    p211lcCheck(privateStoreEffectiveDriver($cfg,'json')==='pdo','geldige cutover-state maakt dezelfde JSON-tenant effectief PDO');

    [$ac,$ao,$ae]=p211lcRun([PHP_BINARY,$root.'/bin/apply-vps-lifecycle.php','--plan='.$lifePad,'--check']);
    p211lcCliFailure('apply-vps-lifecycle post-cutover check',$ac,$ao,$ae);
    p211lcCheck($ac===0&&str_contains($ao,'CHECK OK'),'lifecyclecontract blijft na effectieve PDO-cutover volledig geldig');
    p211lcCheck(hash('sha256',$lifeRaw)===hash_file('sha256',$lifePad),'cutover wijzigt lifecycleplan niet');

    $apply=(string)file_get_contents($root.'/bin/apply-vps-lifecycle.php');
    p211lcCheck(str_contains($apply,"'pg_dump','-Fc'")&&str_contains($apply,"(string)\$p['database']['database']"),'lifecycle-export dumpt de aan migration-target gebonden PostgreSQL-database');
    p211lcCheck(str_contains($apply,'apply48ExportControle($state,$cs)')&&str_contains($apply,'delete_export'),'delete/purge blijven aan geverifieerde export gebonden');
} finally {
    putenv('VERENIGING_CONFIG_FILE');
    p211lcRm($tmp);
}

echo "Platform #211 migrated lifecycle contract: {$ok} OK, {$fout} fout(en)\n";
exit($fout===0?0:1);
