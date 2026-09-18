<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Alleen via CLI beschikbaar.'); }
require_once dirname(__DIR__) . '/app/deployment/monitoring-contract.php';
require_once dirname(__DIR__) . '/app/deployment/monitoring-alert.php';
require_once dirname(__DIR__) . '/app/deployment/process-runner.php';

function health46Stop(string $m, int $c = 1): void { fwrite(STDERR, "FOUT: {$m}\n"); exit($c); }
function health46Binary(string $name): string
{
    static $cache=[];
    if(isset($cache[$name]))return$cache[$name];
    $known=[
        'systemctl'=>['/usr/bin/systemctl'],
        'openssl'=>['/usr/bin/openssl'],
        'runuser'=>['/usr/sbin/runuser','/usr/bin/runuser'],
        'psql'=>['/usr/bin/psql'],
        'curl'=>['/usr/bin/curl'],
    ];
    if(!isset($known[$name]))throw new RuntimeException('Niet-toegestane health PATH-binary: '.$name);
    foreach($known[$name]as$b)if(is_file($b)&&is_executable($b))return$cache[$name]=$b;
    throw new RuntimeException('Vereiste health-executable ontbreekt: '.$name);
}
function health46Run(array $cmd, ?string $stdin=null): array
{
    if($cmd===[]||!isset($cmd[0]))throw new RuntimeException('Health subprocesscommando ontbreekt.');
    $first=(string)$cmd[0];if(!str_starts_with($first,'/'))$cmd[0]=health46Binary($first);
    if(basename((string)$cmd[0])==='runuser'){
        $sep=array_search('--',$cmd,true);if($sep===false||!isset($cmd[$sep+1]))throw new RuntimeException('runuser healthcommando mist exact child-executable.');
        $child=(string)$cmd[$sep+1];if(!str_starts_with($child,'/'))$cmd[$sep+1]=health46Binary($child);
    }
    return process521Run($cmd,$stdin,null,null,120);
}
function health46Deps(array $plan): void
{
    foreach(['systemctl','openssl','runuser','psql','curl']as$n)health46Binary($n);
    $apache=(string)$plan['apache']['control_binary'];if(!str_starts_with($apache,'/')||!is_file($apache)||!is_executable($apache))throw new RuntimeException('Apache health control-binary ontbreekt of is niet absoluut.');
}
function health46Check(bool $ok, string $code, array &$checks): void { $checks[$code]=$ok?'ok':'fail'; }
function health46ModeExact(string $pad, int $mode, bool $dir, bool $rootOwned = false): void
{
    $mode &= 0777;
    if (!@chmod($pad, $mode)) health46Stop('Statuspadmode kon niet exact worden gezet: ' . $pad);
    if ($rootOwned && (!@chown($pad, 0) || !@chgrp($pad, 0))) health46Stop('Statusbestand kon niet root-owned worden gemaakt: ' . $pad);
    clearstatcache(true, $pad);
    $st = @lstat($pad);
    if (!is_array($st) || is_link($pad) || ($dir ? !is_dir($pad) : !is_file($pad)) || (((int)$st['mode'] & 0777) !== $mode)
        || ($rootOwned && ((int)$st['uid'] !== 0 || (int)$st['gid'] !== 0))) {
        health46Stop('Statuspadmetadata wijkt na normalisatie af: ' . $pad);
    }
}
function health46SafeDir(string $dir, int $mode = 0750): void
{
    $link=runtime41SymlinkInPad($dir); if($link!==null) health46Stop("Symlink in statuspad geweigerd: {$link}");
    if(!is_dir($dir)&&!@mkdir($dir,$mode,true)&&!is_dir($dir)) health46Stop("Statusmap kon niet worden aangemaakt: {$dir}");
    health46ModeExact($dir,$mode,true);
}
function health46AtomicJson(string $pad, array $data, int $mode = 0640): void
{
    if(is_link($pad)) health46Stop("Symlink statusbestand geweigerd: {$pad}");
    $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    if(!is_string($json)) health46Stop('Status kon niet als JSON worden opgebouwd.');
    $tmp=$pad.'.tmp.'.bin2hex(random_bytes(6));
    if(@file_put_contents($tmp,$json."\n",LOCK_EX)===false) health46Stop('Status kon niet tijdelijk worden geschreven.');
    health46ModeExact($tmp,$mode,false,true);
    if(is_link($pad)){@unlink($tmp);health46Stop('Statusdoel werd tijdens write een symlink.');}
    if(!@rename($tmp,$pad)){@unlink($tmp);health46Stop('Status kon niet atomisch worden geplaatst.');}
    health46ModeExact($pad,$mode,false,true);
}
function health46RootConfigMeta(string $pad): void
{
    clearstatcache(true, $pad);
    $st=@lstat($pad);
    if(!is_array($st)||is_link($pad)||!is_file($pad)||(int)$st['uid']!==0||(int)$st['gid']!==0||(((int)$st['mode']&0777)!==0644)){
        throw new RuntimeException('FPM poolconfig heeft onverwachte metadata.');
    }
}
function health46FpmConfigWrite(string $pad, string $inhoud): void
{
    if(runtime41SymlinkInPad($pad)!==null||is_link($pad))throw new RuntimeException('FPM poolconfigpad bevat een symlink.');
    $dir=dirname($pad);
    if(!is_dir($dir)||is_link($dir))throw new RuntimeException('FPM poolconfigmap is onveilig.');
    $tmp=$dir.'/.'.basename($pad).'.pilot319.'.bin2hex(random_bytes(6));
    if(@file_put_contents($tmp,$inhoud,LOCK_EX)===false)throw new RuntimeException('Tijdelijke FPM poolconfig kon niet worden geschreven.');
    if(!@chown($tmp,0)||!@chgrp($tmp,0)||!@chmod($tmp,0644)){@unlink($tmp);throw new RuntimeException('Tijdelijke FPM poolconfig kreeg onveilige metadata.');}
    health46RootConfigMeta($tmp);
    if(is_link($pad)||!@rename($tmp,$pad)){@unlink($tmp);throw new RuntimeException('FPM poolconfig kon niet atomisch worden vervangen.');}
    health46RootConfigMeta($pad);
}
function health46Pilot319FpmReconcile(array $monitorPlan): void
{
    if(!hash_equals('pilot319',(string)($monitorPlan['tenant_key']??'')))return;

    $runtimePad=(string)($monitorPlan['source']['runtime_plan_file']??'');
    $ctx=runtime41PlanLeesEnValideer($runtimePad);
    $runtime=$ctx['plan'];
    $deployment=$ctx['deployment'];
    if(!hash_equals('pilot319',(string)($runtime['tenant_key']??''))||!hash_equals('pilot319',(string)($deployment['tenant_key']??''))){
        throw new RuntimeException('pilot319 runtimebinding wijkt af.');
    }

    $configFile=(string)$deployment['config_file'];
    $tenantConfig=require $configFile;
    if(!is_array($tenantConfig))throw new RuntimeException('pilot319 tenantconfig is geen array.');
    $configTenant=(string)($tenantConfig['vereniging']['sleutel']??'');
    $configPrivate=runtime41NormPad((string)($tenantConfig['opslag']['private_root']??''));
    $expectedPrivate=runtime41NormPad((string)($runtime['filesystem']['private_root']['path']??''));
    if(!hash_equals('pilot319',$configTenant)||!hash_equals($expectedPrivate,$configPrivate)||!hash_equals(runtime41NormPad((string)$deployment['private_root']),$expectedPrivate)){
        throw new RuntimeException('pilot319 config, deployment en runtimeplan bevestigen niet dezelfde private root.');
    }

    $bundle=(string)($runtime['bundle']['php_fpm_file']??'');
    if(runtime41SymlinkInPad($bundle)!==null||is_link($bundle)||!is_file($bundle))throw new RuntimeException('pilot319 FPM runtimebundle ontbreekt of is onveilig.');
    $expected=runtime41FpmConfig($runtime);
    $bundleRaw=@file_get_contents($bundle);
    if(!is_string($bundleRaw)||!hash_equals($expected,$bundleRaw))throw new RuntimeException('pilot319 FPM runtimebundle wijkt af van het runtimecontract.');

    $phpVersion=(string)($runtime['settings']['php_version']??'');
    if(!runtime41PhpVersie($phpVersion)||!hash_equals($phpVersion,(string)($monitorPlan['runtime']['php_version']??''))){
        throw new RuntimeException('pilot319 PHP-versiebinding wijkt af.');
    }
    $installed='/etc/php/'.$phpVersion.'/fpm/pool.d/'.basename($bundle);
    if(runtime41SymlinkInPad($installed)!==null||is_link($installed)||!is_file($installed))throw new RuntimeException('pilot319 geïnstalleerde FPM poolconfig ontbreekt of is onveilig.');
    health46RootConfigMeta($installed);
    $current=@file_get_contents($installed);
    if(!is_string($current))throw new RuntimeException('pilot319 geïnstalleerde FPM poolconfig is onleesbaar.');
    if(hash_equals($expected,$current))return;

    $drift=runtime41FpmTempPathDrift($runtime,$current);
    if(!hash_equals('repairable_other_tenant_temp_paths',$drift)){
        throw new RuntimeException('pilot319 FPM pooldrift is breder dan de twee tenant-private tijdelijke paden; automatische repair geweigerd.');
    }

    $fpm='/usr/sbin/php-fpm'.$phpVersion;
    if(!is_file($fpm)||!is_executable($fpm))throw new RuntimeException('pilot319 FPM testbinary ontbreekt.');
    $service='php'.$phpVersion.'-fpm.service';

    health46FpmConfigWrite($installed,$expected);
    [$testCode,$testOut,$testErr]=health46Run([$fpm,'-t']);
    if($testCode!==0){
        health46FpmConfigWrite($installed,$current);
        throw new RuntimeException('pilot319 FPM configtest faalde na repair; oorspronkelijke config is hersteld: '.trim($testErr!==''?$testErr:$testOut));
    }

    [$reloadCode,,$reloadErr]=health46Run(['systemctl','reload',$service]);
    if($reloadCode!==0){
        health46FpmConfigWrite($installed,$current);
        [$rollbackTest]=health46Run([$fpm,'-t']);
        [$rollbackReload]=health46Run(['systemctl','reload',$service]);
        if($rollbackTest!==0||$rollbackReload!==0)throw new RuntimeException('pilot319 FPM reload faalde en rollback kon niet volledig worden bewezen.');
        throw new RuntimeException('pilot319 FPM reload faalde; oorspronkelijke config is hersteld: '.$reloadErr);
    }

    echo "PILOT319 FPM RUNTIME RECONCILED  scope=session.save_path,upload_tmp_dir\n";
}

function health46CertBinnenEigenLineage(string $pad): bool
{
    if(!is_file($pad)) return false;
    $liveDir=dirname($pad); $certName=basename($liveDir);
    if(!str_starts_with($liveDir,'/etc/letsencrypt/live/')||$certName===''||str_contains($certName,'/')) return false;
    $real=realpath($pad); if($real===false) return false;
    return str_starts_with($real,'/etc/letsencrypt/archive/'.$certName.'/');
}
function health46AlertOudeState(string $statePad): array
{
    if(!is_file($statePad)||is_link($statePad))return[];
    $raw=@file_get_contents($statePad);if(!is_string($raw))return[];
    $json=json_decode($raw,true);return is_array($json)?$json:[];
}
function health46AlertSamenvatting(array $state): array
{
    $delivery=is_array($state['delivery']??null)?$state['delivery']:[];
    return[
        'enabled'=>(bool)($delivery['enabled']??false),
        'status'=>(string)($delivery['status']??'unknown'),
        'reason'=>$delivery['reason']??null,
        'error_code'=>$delivery['error_code']??null,
    ];
}
function health46Alert(array $plan, array $status): array
{
    $alerts=(array)$plan['alerts'];$statePad=(string)$alerts['state_file'];health46SafeDir(dirname($statePad));
    $oud=health46AlertOudeState($statePad);$nu=(string)$status['state'];$epoch=time();$beslissing=monitoring46AlertBeslissing($alerts,$oud,$nu,$epoch);

    if(!$beslissing['enabled']){
        $nieuw=monitoring46AlertNieuweState($oud,$nu,$epoch,$beslissing,'disabled');health46AtomicJson($statePad,$nieuw);
        return health46AlertSamenvatting($nieuw)+['failed'=>false];
    }
    if(!$beslissing['send']){
        $nieuw=monitoring46AlertNieuweState($oud,$nu,$epoch,$beslissing,'idle');health46AtomicJson($statePad,$nieuw);
        return health46AlertSamenvatting($nieuw)+['failed'=>false];
    }

    $adapterFout=monitoring46AlertAdapterFout($alerts);
    if($adapterFout!==null){
        $nieuw=monitoring46AlertNieuweState($oud,$nu,$epoch,$beslissing,'pending',$adapterFout);health46AtomicJson($statePad,$nieuw);
        return health46AlertSamenvatting($nieuw)+['failed'=>true];
    }

    $adapter=(string)$alerts['adapter'];
    $payload=['schema'=>1,'tenant_key'=>$plan['tenant_key'],'state'=>$nu,'previous_state'=>$beslissing['previous_delivered_state'],'reason'=>$beslissing['reason'],'checked_at_utc'=>$status['checked_at_utc'],'failed_checks'=>array_keys(array_filter($status['checks'],static fn($v)=>$v==='fail'))];
    $json=json_encode($payload,JSON_UNESCAPED_SLASHES);if(!is_string($json))health46Stop('Alert-payload kon niet veilig worden opgebouwd.');
    [$code,,$err]=health46Run([$adapter],$json."\n");
    unset($err);
    if($code!==0){
        $nieuw=monitoring46AlertNieuweState($oud,$nu,$epoch,$beslissing,'pending','adapter_exit');health46AtomicJson($statePad,$nieuw);
        return health46AlertSamenvatting($nieuw)+['failed'=>true];
    }
    $nieuw=monitoring46AlertNieuweState($oud,$nu,$epoch,$beslissing,'delivered');health46AtomicJson($statePad,$nieuw);
    return health46AlertSamenvatting($nieuw)+['failed'=>false];
}

foreach($_SERVER['argv']??[]as$arg){if(preg_match('/^--(?:password|secret|token|key|dsn|webhook)(?:=|$)/i',(string)$arg)===1)health46Stop('Secrets horen niet in health CLI-argumenten.');}
$opt=getopt('',['monitoring-plan:','check','probe','write-status','alert','help']);
if(isset($opt['help'])){echo "Gebruik: php bin/check-vps-health.php --monitoring-plan=... --check | --probe [--write-status] [--alert]\n";exit(0);}
$planPad=trim((string)($opt['monitoring-plan']??'')); if($planPad==='')health46Stop('--monitoring-plan is verplicht.');
try{$ctx=monitoring46PlanLeesEnValideer($planPad);$plan=$ctx['plan'];}catch(Throwable$e){health46Stop($e->getMessage());}
if(isset($opt['check'])&&!isset($opt['probe'])){echo 'CHECK OK  tenant='.$plan['tenant_key']."\n";exit(0);}
if(!isset($opt['probe'])||isset($opt['check']))health46Stop('Kies exact --check of --probe.');
if(PHP_OS_FAMILY!=='Linux'||!function_exists('posix_geteuid')||posix_geteuid()!==0)health46Stop('--probe vereist Linux root.');
try{health46Deps($plan);health46Pilot319FpmReconcile($plan);}catch(Throwable$e){health46Stop($e->getMessage());}

$checks=[];
foreach([$plan['runtime']['apache_service'],$plan['runtime']['fpm_service'],$plan['runtime']['postgresql_service']]as$svc){[$c,$o]=health46Run(['systemctl','is-active',(string)$svc]);health46Check($c===0&&$o==='active','service:'.(string)$svc,$checks);}
[$ac]=health46Run([(string)$plan['apache']['control_binary'],'configtest']); health46Check($ac===0,'apache:configtest',$checks);
$socket=(string)$plan['runtime']['fpm_socket']; health46Check(file_exists($socket)&&filetype($socket)==='socket','fpm:socket',$checks);
$full=(string)$plan['certificate']['fullchain']; $key=(string)$plan['certificate']['private_key'];
health46Check(health46CertBinnenEigenLineage($full)&&health46CertBinnenEigenLineage($key),'tls:lineage',$checks);
[$xc]=health46Run(['openssl','x509','-in',$full,'-checkend',(string)$plan['health']['certificate_warning_seconds'],'-noout']); health46Check($xc===0,'tls:remaining',$checks);
$db=(string)$plan['database']['database']; $user=(string)$plan['database']['user'];
[$pc,$po]=health46Run(['runuser','-u',$user,'--','psql','-h','/var/run/postgresql','-d',$db,'-Atqc','SELECT current_database() || chr(124) || current_user']);
health46Check($pc===0&&hash_equals($db.'|'.$user,$po),'database:peer',$checks);
$host=(string)$plan['canonical_host'];
[$cc,$co]=health46Run(['curl','--silent','--show-error','--output','/dev/null','--write-out','%{http_code}','--connect-timeout','5','--max-time',(string)$plan['health']['timeout_seconds'],'--resolve',$host.':443:127.0.0.1','https://'.$host.'/healthz.php']);
health46Check($cc===0&&$co==='204','app:https-health',$checks);
$free=@disk_free_space((string)$ctx['context']['tenant_root']); $total=@disk_total_space((string)$ctx['context']['tenant_root']);
$diskOk=is_float($free)||is_int($free); $diskOk=$diskOk&&(is_float($total)||is_int($total))&&$total>0&&$free>=(int)$plan['health']['disk_minimum_free_bytes']&&(($free/$total)*100)>=(int)$plan['health']['disk_minimum_free_percent'];
health46Check($diskOk,'disk:free-space',$checks);
$status=['schema'=>1,'phase'=>'4.6-health','tenant_key'=>$plan['tenant_key'],'state'=>in_array('fail',$checks,true)?'down':'up','checked_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'checks'=>$checks];
$alertFailed=false;
if(isset($opt['alert'])){$delivery=health46Alert($plan,$status);$alertFailed=(bool)($delivery['failed']??false);unset($delivery['failed']);$status['alert_delivery']=$delivery;}
else{$status['alert_delivery']=['enabled'=>monitoring46AlertsEnabled((array)$plan['alerts']),'status'=>'not_requested','reason'=>null,'error_code'=>null];}
if(isset($opt['write-status'])){health46SafeDir(dirname((string)$plan['logging']['health_status']));health46AtomicJson((string)$plan['logging']['health_status'],$status);}
echo strtoupper($status['state']).'  tenant='.$plan['tenant_key'].' checks='.count($checks).' alert='.$status['alert_delivery']['status']."\n";
if($alertFailed)exit(3);
exit($status['state']==='up'?0:2);
