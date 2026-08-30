<?php
$root = dirname(__DIR__);
$ok = 0; $fout = 0;
function c516(bool $c, string $label): void { global $ok,$fout; if($c){$ok++;echo"OK: {$label}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$label}\n");} }
function rm516(string $p): void { if(is_link($p)||is_file($p)){@unlink($p);return;} if(!is_dir($p))return; foreach(scandir($p)?:[] as $n){if($n==='.'||$n==='..')continue;rm516($p.'/'.$n);}@rmdir($p); }
$tmp=sys_get_temp_dir().'/rc045-phase516-'.bin2hex(random_bytes(5));
$state=$tmp.'/state';$tenants=$tmp.'/tenants';foreach([$state.'/requests/pending',$state.'/requests/processing',$state.'/results',$state.'/sessions',$tenants]as$d)@mkdir($d,0770,true);
$cfg=['schema'=>1,'phase'=>'5.1-runtime','host'=>'beheer.example.test','app_root'=>$root,'tenants_root'=>$tenants,'runtime_user'=>get_current_user()?:'runner','pending_dir'=>$state.'/requests/pending','processing_dir'=>$state.'/requests/processing','results_dir'=>$state.'/results','sessions_dir'=>$state.'/sessions','snapshot_file'=>$state.'/snapshot.json','executor_lock'=>$tmp.'/executor.lock','audit_file'=>$tmp.'/audit.jsonl','lifecycle_apply'=>$root.'/bin/apply-vps-lifecycle.php'];
file_put_contents($tmp.'/runtime.json',json_encode($cfg));$snapshot=['schema'=>1,'phase'=>'5.1-snapshot','generated_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'tenants'=>[]];file_put_contents($cfg['snapshot_file'],json_encode($snapshot));putenv('VP_CONTROL_PLANE_CONFIG='.$tmp.'/runtime.json');$_SERVER['REMOTE_USER']='operator.test';
try{
 require_once $root.'/app/control-plane/control-plane-runtime.php';require_once $root.'/app/control-plane/control-plane-observability.php';
 $sys=cpAdminSysteemStatus();
 c516(($sys['php_version']??'')===PHP_VERSION,'capaciteitssnapshot toont exact de actieve PHP-versie');
 c516(is_int($sys['cpu_count']??null)&&$sys['cpu_count']>=1,'CPU-threadcount is minimaal één');
 c516(isset($sys['load'])&&array_keys($sys['load'])===['one','five','fifteen'],'load-average heeft vaste niet-gevoelige velden');
 c516(is_int($sys['disk']['total_bytes']??null)&&$sys['disk']['total_bytes']>0,'platformdiskcapaciteit wordt read-only bepaald op tenantroot');
 c516(is_float($sys['disk']['used_percent']??null)&&$sys['disk']['used_percent']>=0&&$sys['disk']['used_percent']<=100,'platformdiskpercentage blijft begrensd tussen 0 en 100');
 c516(($sys['memory']['used_percent']??null)===null||(is_float($sys['memory']['used_percent'])&&$sys['memory']['used_percent']>=0&&$sys['memory']['used_percent']<=100),'geheugenpercentage is null of veilig begrensd');
 c516(($sys['uptime_seconds']??null)===null||(is_int($sys['uptime_seconds'])&&$sys['uptime_seconds']>=0),'uptime is null of een niet-negatieve waarde');
 c516(($sys['release_sha']??null)===null||preg_match('/^[0-9a-f]{40}$/D',(string)$sys['release_sha'])===1,'release-identiteit lekt alleen een geldige commit-SHA');
 c516(cpAdminBytesLabel(1073741824)==='1,0 GB'&&cpAdminUptimeLabel(90061)==='1d 1u','capaciteitslabels zijn compact en Nederlands leesbaar');
 $obs=(string)file_get_contents($root.'/app/control-plane/control-plane-observability.php');
 c516(!str_contains($obs,'proc_open(')&&!str_contains($obs,'shell_exec(')&&!str_contains($obs,'system(')&&!str_contains($obs,'exec('),'capaciteitstelemetrie start geen processen');
 c516(str_contains($obs,"['/proc/uptime','/proc/meminfo','/proc/cpuinfo']"),'proc-lezer gebruikt een vaste allowlist');
 c516(str_contains($obs,'$diskPercent >= 97.0')&&str_contains($obs,'lifecyclemutaties zijn uit voorzorg geblokkeerd'),'kritiek volle disk blokkeert beheerwrites fail-closed');
 $ui=(string)file_get_contents($root.'/app/control-plane-web/index.php');
 c516(str_contains($ui,'Systeem & capaciteit')&&str_contains($ui,'Platformopslag')&&str_contains($ui,'Geheugen')&&str_contains($ui,'Release'),'platformconsole maakt capaciteit zichtbaar');
}finally{rm516($tmp);}echo"Phase 5.1.6 platform capacity: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
