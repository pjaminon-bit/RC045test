<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function c154(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";return;}$fout++;fwrite(STDERR,"FOUT: {$l}\n");}
require_once $root.'/app/deployment/monitoring-alert.php';

$enabled=['enabled'=>true,'adapter'=>'/definitely/missing/alert-command','reminder_seconds'=>3600];
$disabled=['enabled'=>false,'adapter'=>'/definitely/missing/alert-command','reminder_seconds'=>3600];
$now=2000000000;

c154(monitoring46AlertsEnabled(['adapter'=>'/legacy'])===true,'legacy fase-4.6 plan zonder enabled blijft fail-closed als enabled');
c154(monitoring46AlertsEnabled($disabled)===false,'expliciet disabled alerting wordt onderscheiden');
c154(monitoring46AlertAdapterFout($enabled)==='adapter_missing','enabled missing adapter is expliciete deliveryfout');
c154(monitoring46AlertAdapterFout($disabled)===null,'disabled alerting vereist bewust geen adapter');
c154(monitoring46AlertAdapterMetadataFout('relative',true,true,false,true,['uid'=>0,'mode'=>0100755])==='adapter_not_absolute','adapter moet absoluut zijn');
c154(monitoring46AlertAdapterMetadataFout('/adapter',true,true,true,true,['uid'=>0,'mode'=>0100755])==='adapter_symlink','adapter mag geen symlink zijn');
c154(monitoring46AlertAdapterMetadataFout('/adapter',true,true,false,true,['uid'=>1000,'mode'=>0100755])==='adapter_not_root_owned','adapter moet root-owned zijn');
c154(monitoring46AlertAdapterMetadataFout('/adapter',true,true,false,true,['uid'=>0,'mode'=>0100775])==='adapter_group_or_world_writable','group/world-writable adapter wordt geweigerd');
c154(monitoring46AlertAdapterMetadataFout('/adapter',true,true,false,false,['uid'=>0,'mode'=>0100644])==='adapter_not_executable','adapter moet executable zijn');
c154(monitoring46AlertAdapterMetadataFout('/adapter',true,true,false,true,['uid'=>0,'mode'=>0100750])===null,'veilige synthetische adaptermetadata wordt geaccepteerd');

// Eerste outage: transition moet worden afgeleverd.
$d1=monitoring46AlertBeslissing($enabled,[],'down',$now);
c154($d1['send']===true&&$d1['reason']==='failure_transition'&&$d1['previous_delivered_state']==='unknown','eerste outage veroorzaakt failure transition');
$s1=monitoring46AlertNieuweState([],'down',$now,$d1,'pending','adapter_missing');
c154(($s1['last_delivered_state']??'')==='unknown'&&($s1['last_alert_epoch']??-1)===0,'mislukte transition vervalst geen succesvolle deliverystate/timestamp');
c154(($s1['delivery']['status']??'')==='pending'&&($s1['delivery']['error_code']??'')==='adapter_missing','mislukte transition blijft expliciet pending met foutcode');
$dRetry=monitoring46AlertBeslissing($enabled,$s1,'down',$now+60);
c154($dRetry['send']===true&&$dRetry['reason']==='failure_transition','mislukte transition wordt volgende probe direct opnieuw geprobeerd');

// Succes maakt pas nu delivery authoritative.
$s2=monitoring46AlertNieuweState($s1,'down',$now+60,$dRetry,'delivered');
c154(($s2['last_delivered_state']??'')==='down'&&($s2['last_alert_epoch']??0)===$now+60,'alleen succesvolle delivery werkt last delivered state en epoch bij');
$dQuiet=monitoring46AlertBeslissing($enabled,$s2,'down',$now+120);
c154($dQuiet['send']===false,'geen reminder vóór één uur na succesvolle delivery');
$dReminder=monitoring46AlertBeslissing($enabled,$s2,'down',$now+3660);
c154($dReminder['send']===true&&$dReminder['reason']==='reminder','down-state krijgt hourly reminder na succesvolle vorige delivery');
$sReminderFail=monitoring46AlertNieuweState($s2,'down',$now+3660,$dReminder,'pending','adapter_exit');
c154(($sReminderFail['last_alert_epoch']??0)===$now+60&&($sReminderFail['last_delivered_state']??'')==='down','mislukte reminder schuift succesvolle timestamp niet op');
$dReminderRetry=monitoring46AlertBeslissing($enabled,$sReminderFail,'down',$now+3720);
c154($dReminderRetry['send']===true&&$dReminderRetry['reason']==='reminder','mislukte hourly reminder blijft per volgende probe retrybaar');

// Recovery na een pending outage mag niet verdwijnen als de laatst succesvol
// afgeleverde state al up was.
$oudUp=['schema'=>2,'state'=>'up','last_delivered_state'=>'up','last_alert_epoch'=>$now-100,'delivery'=>['enabled'=>true,'status'=>'delivered','reason'=>'recovery_transition','error_code'=>null,'delivered_at_utc'=>'2026-01-01T00:00:00Z']];
$dDown=monitoring46AlertBeslissing($enabled,$oudUp,'down',$now);
$sDownFail=monitoring46AlertNieuweState($oudUp,'down',$now,$dDown,'pending','adapter_exit');
$dRecovery=monitoring46AlertBeslissing($enabled,$sDownFail,'up',$now+60);
c154($dRecovery['send']===true&&$dRecovery['reason']==='recovery_transition','recovery na niet-afgeleverde outage wordt alsnog expliciet aangeboden');
$sRecovery=monitoring46AlertNieuweState($sDownFail,'up',$now+60,$dRecovery,'delivered');
c154(($sRecovery['last_delivered_state']??'')==='up'&&($sRecovery['last_alert_epoch']??0)===$now+60,'succesvolle recoverydelivery wordt authoritative');

// Oude schema-1 state kan door #154 ten onrechte een delivery suggereren. De
// migratie vertrouwt die state daarom niet en synchroniseert één keer opnieuw.
$legacy=['state'=>'down','last_alert_epoch'=>$now-10,'updated_at_utc'=>'2026-01-01T00:00:00Z'];
$dLegacy=monitoring46AlertBeslissing($enabled,$legacy,'down',$now);
c154($dLegacy['send']===true&&$dLegacy['reason']==='failure_transition'&&$dLegacy['previous_delivered_state']==='unknown','legacy alertstate wordt niet als bewezen delivery vertrouwd');

$dDisabled=monitoring46AlertBeslissing($disabled,[],'down',$now);
$sDisabled=monitoring46AlertNieuweState([],'down',$now,$dDisabled,'disabled');
c154($dDisabled['send']===false&&($sDisabled['delivery']['status']??'')==='disabled'&&($sDisabled['last_alert_epoch']??-1)===0,'disabled alerting registreert health zonder deliveryfout of fake timestamp');

$health=(string)file_get_contents($root.'/bin/check-vps-health.php');
$apply=(string)file_get_contents($root.'/bin/apply-vps-monitoring.php');
$prepare=(string)file_get_contents($root.'/bin/prepare-vps-monitoring.php');
$contract=(string)file_get_contents($root.'/app/deployment/monitoring-contract.php');
c154(str_contains($health,"'pending',$adapterFout")&&str_contains($health,"'pending','adapter_exit'")&&str_contains($health,"if($alertFailed)exit(3)"),'runtime bewaart delivery failure als pending en faalt observably');
c154(str_contains($health,"$status['alert_delivery']")&&strpos($health,"health46Alert($plan,$status)")<strpos($health,"health46AtomicJson((string)$plan['logging']['health_status'],$status)"),'healthstatus bevat afzonderlijke alert-deliverystatus na deliverypoging');
c154(str_contains($apply,'monitoring46AlertAdapterFout((array)$p[\'alerts\'])')&&strpos($apply,'monitoring46AlertAdapterFout((array)$p[\'alerts\'])')<strpos($apply,"apply46SafeDir('/var/log/verenigingsplatform'"),'provisioning valideert enabled adapter vóór monitoringmutaties');
c154(str_contains($apply,"'--probe','--write-status','--alert'")&&strpos($apply,"'--probe','--write-status','--alert'")<strpos($apply,"'enable','--now'"),'eerste provisioningprobe bewijst health én delivery vóór timeractivatie');
c154(str_contains($prepare,"'alerts:'")&&str_contains($prepare,"['enabled', 'disabled']")&&str_contains($contract,"'enabled'=>$alertsEnabled"),'monitoringconfig heeft expliciete enabled/disabled alertmodus');
c154(str_contains($contract,'$legacy=!array_key_exists(\'enabled\',$alerts)')&&str_contains($contract,"if($legacy)$p['alerts']['enabled']=true"),'bestaande schema-1 monitoringplannen migreren compatibel als fail-closed enabled');

echo"Issue #154 monitoring alert delivery: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
