<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Alleen via CLI beschikbaar.'); }
require_once dirname(__DIR__) . '/app/deployment/monitoring-contract.php';

function health46Stop(string $m, int $c = 1): void { fwrite(STDERR, "FOUT: {$m}\n"); exit($c); }
function health46Run(array $cmd): array
{
    $d=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
    $p=@proc_open($cmd,$d,$x,null,null,['bypass_shell'=>true]);
    if(!is_resource($p)) return [255,'','proces kon niet starten'];
    fclose($x[0]); $o=stream_get_contents($x[1]); fclose($x[1]); $e=stream_get_contents($x[2]); fclose($x[2]);
    return [proc_close($p),trim((string)$o),trim((string)$e)];
}
function health46Check(bool $ok, string $code, array &$checks): void { $checks[$code]=$ok?'ok':'fail'; }
function health46SafeDir(string $dir, int $mode = 0750): void
{
    $link=runtime41SymlinkInPad($dir); if($link!==null) health46Stop("Symlink in statuspad geweigerd: {$link}");
    if(!is_dir($dir)&&!@mkdir($dir,$mode,true)&&!is_dir($dir)) health46Stop("Statusmap kon niet worden aangemaakt: {$dir}");
    @chmod($dir,$mode);
}
function health46AtomicJson(string $pad, array $data, int $mode = 0640): void
{
    if(is_link($pad)) health46Stop("Symlink statusbestand geweigerd: {$pad}");
    $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    if(!is_string($json)) health46Stop('Status kon niet als JSON worden opgebouwd.');
    $tmp=$pad.'.tmp.'.bin2hex(random_bytes(6));
    if(@file_put_contents($tmp,$json."\n",LOCK_EX)===false) health46Stop('Status kon niet tijdelijk worden geschreven.');
    @chmod($tmp,$mode); @chown($tmp,'root'); @chgrp($tmp,'root');
    if(is_link($pad)){@unlink($tmp);health46Stop('Statusdoel werd tijdens write een symlink.');}
    if(!@rename($tmp,$pad)){@unlink($tmp);health46Stop('Status kon niet atomisch worden geplaatst.');}
    @chmod($pad,$mode); @chown($pad,'root'); @chgrp($pad,'root');
}
function health46CertBinnenEigenLineage(string $pad): bool
{
    if(!is_file($pad)) return false;
    $liveDir=dirname($pad); $certName=basename($liveDir);
    if(!str_starts_with($liveDir,'/etc/letsencrypt/live/')||$certName===''||str_contains($certName,'/')) return false;
    $real=realpath($pad); if($real===false) return false;
    return str_starts_with($real,'/etc/letsencrypt/archive/'.$certName.'/');
}
function health46Alert(array $plan, array $status): void
{
    $statePad=(string)$plan['alerts']['state_file']; health46SafeDir(dirname($statePad));
    $oud=[]; if(is_file($statePad)&&!is_link($statePad)){ $r=@file_get_contents($statePad); $j=is_string($r)?json_decode($r,true):null; if(is_array($j))$oud=$j; }
    $nu=(string)$status['state']; $vorig=(string)($oud['state']??'unknown'); $laatst=(int)($oud['last_alert_epoch']??0); $epoch=time();
    $stuur=$vorig!==$nu || ($nu==='down' && $epoch-$laatst >= (int)$plan['alerts']['reminder_seconds']);
    $nieuw=['state'=>$nu,'last_alert_epoch'=>$stuur?$epoch:$laatst,'updated_at_utc'=>gmdate('Y-m-d\TH:i:s\Z',$epoch)];
    if($stuur){
        $adapter=(string)$plan['alerts']['adapter'];
        if(is_file($adapter)&&!is_link($adapter)){
            $st=@stat($adapter); $mode=is_array($st)?((int)$st['mode']&0777):-1;
            if(!is_array($st)||(int)$st['uid']!==0||($mode&0022)!==0||!is_executable($adapter)) health46Stop('Alert-adapter bestaat maar is niet veilig root-owned/executable.');
            $payload=['schema'=>1,'tenant_key'=>$plan['tenant_key'],'state'=>$nu,'previous_state'=>$vorig,'checked_at_utc'=>$status['checked_at_utc'],'failed_checks'=>array_keys(array_filter($status['checks'],static fn($v)=>$v==='fail'))];
            $d=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']]; $p=@proc_open([$adapter],$d,$x,null,null,['bypass_shell'=>true]);
            if(!is_resource($p)) health46Stop('Alert-adapter kon niet worden gestart.');
            fwrite($x[0],json_encode($payload,JSON_UNESCAPED_SLASHES)."\n"); fclose($x[0]); stream_get_contents($x[1]); fclose($x[1]); $err=stream_get_contents($x[2]); fclose($x[2]);
            if(proc_close($p)!==0) health46Stop('Alert-adapter meldde een fout'.($err!==''?': '.trim($err):'.'));
        }
    }
    health46AtomicJson($statePad,$nieuw);
}

foreach($_SERVER['argv']??[]as$arg){if(preg_match('/^--(?:password|secret|token|key|dsn|webhook)(?:=|$)/i',(string)$arg)===1)health46Stop('Secrets horen niet in health CLI-argumenten.');}
$opt=getopt('',['monitoring-plan:','check','probe','write-status','alert','help']);
if(isset($opt['help'])){echo "Gebruik: php bin/check-vps-health.php --monitoring-plan=... --check | --probe [--write-status] [--alert]\n";exit(0);}
$planPad=trim((string)($opt['monitoring-plan']??'')); if($planPad==='')health46Stop('--monitoring-plan is verplicht.');
try{$ctx=monitoring46PlanLeesEnValideer($planPad);$plan=$ctx['plan'];}catch(Throwable$e){health46Stop($e->getMessage());}
if(isset($opt['check'])&&!isset($opt['probe'])){echo 'CHECK OK  tenant='.$plan['tenant_key']."\n";exit(0);}
if(!isset($opt['probe'])||isset($opt['check']))health46Stop('Kies exact --check of --probe.');
if(PHP_OS_FAMILY!=='Linux'||!function_exists('posix_geteuid')||posix_geteuid()!==0)health46Stop('--probe vereist Linux root.');

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
if(isset($opt['write-status'])){health46SafeDir(dirname((string)$plan['logging']['health_status']));health46AtomicJson((string)$plan['logging']['health_status'],$status);}
if(isset($opt['alert']))health46Alert($plan,$status);
echo strtoupper($status['state']).'  tenant='.$plan['tenant_key'].' checks='.count($checks)."\n";
exit($status['state']==='up'?0:2);
