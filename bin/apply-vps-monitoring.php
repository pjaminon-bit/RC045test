<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Alleen via CLI beschikbaar.');}
require_once dirname(__DIR__).'/app/deployment/monitoring-contract.php';
require_once dirname(__DIR__).'/app/deployment/process-runner.php';

function apply46Stop(string$m,int$c=1):void{fwrite(STDERR,"FOUT: {$m}\n");exit($c);}
function apply46Binary(string$name):string
{
    static $cache=[];
    if(isset($cache[$name]))return$cache[$name];
    $known=['systemd-analyze'=>['/usr/bin/systemd-analyze'],'logrotate'=>['/usr/sbin/logrotate','/usr/bin/logrotate'],'systemctl'=>['/usr/bin/systemctl']];
    if(!isset($known[$name]))throw new RuntimeException('Niet-toegestane monitoring PATH-binary: '.$name);
    foreach($known[$name]as$b)if(is_file($b)&&is_executable($b))return$cache[$name]=$b;
    throw new RuntimeException('Vereiste monitoring-executable ontbreekt: '.$name);
}
function apply46Run(array$cmd):array
{
    if($cmd===[]||!isset($cmd[0]))throw new RuntimeException('Monitoring subprocesscommando ontbreekt.');
    $first=(string)$cmd[0];
    if(!str_starts_with($first,'/'))$cmd[0]=apply46Binary($first);
    return process521Run($cmd,null,null,null,900);
}
function apply46Deps(array$p):string
{
    foreach(['systemd-analyze','logrotate','systemctl']as$n)apply46Binary($n);
    $apache=(string)$p['apache']['control_binary'];if(!is_file($apache)||!is_executable($apache))throw new RuntimeException('Apache control-binary ontbreekt: '.$apache);
    $php=PHP_BINARY;if(!preg_match('#^/usr/bin/php[0-9]{1,2}\.[0-9]{1,2}$#D',$php)||!is_file($php)||!is_executable($php))throw new RuntimeException('Host-engine PHP CLI is niet exact gepind.');
    return$php;
}
function apply46SafeDir(string$p,string$owner,string$group,int$mode):void{$l=runtime41SymlinkInPad($p);if($l!==null)apply46Stop("Symlink in monitoringpad: {$l}");if(!is_dir($p)&&!@mkdir($p,$mode,true)&&!is_dir($p))apply46Stop("Map kon niet worden aangemaakt: {$p}");if(!@chown($p,$owner)||!@chgrp($p,$group)||!@chmod($p,$mode))apply46Stop("Rechten konden niet worden gezet: {$p}");}
function apply46Install(string$src,string$dst,int$mode,bool$force):string{if(is_link($dst))apply46Stop("Symlinkdoel geweigerd: {$dst}");$inh=@file_get_contents($src);if(!is_string($inh))apply46Stop("Bron onleesbaar: {$src}");if(is_file($dst)){$oud=@file_get_contents($dst);if(is_string($oud)&&hash_equals(hash('sha256',$oud),hash('sha256',$inh)))return'ongewijzigd';if(!$force)apply46Stop("Afwijkend bestaand bestand: {$dst}; gebruik --force na controle.");}elseif(file_exists($dst))apply46Stop("Doel is geen regulier bestand: {$dst}");$tmp=dirname($dst).'/.'.basename($dst).'.tmp.'.bin2hex(random_bytes(5));if(@file_put_contents($tmp,$inh,LOCK_EX)===false)apply46Stop("Tijdelijk bestand mislukt: {$dst}");@chown($tmp,'root');@chgrp($tmp,'root');@chmod($tmp,$mode);if(is_link($dst)){@unlink($tmp);apply46Stop("Doel werd symlink: {$dst}");}if(!@rename($tmp,$dst)){@unlink($tmp);apply46Stop("Installatie mislukt: {$dst}");}@chown($dst,'root');@chgrp($dst,'root');@chmod($dst,$mode);return'geschreven';}
function apply46RollbackNieuweApacheLink(string$enabled,bool$nieuw):void{if($nieuw&&is_link($enabled))@unlink($enabled);}

foreach($_SERVER['argv']??[]as$a){if(preg_match('/^--(?:password|secret|token|key|dsn|webhook)(?:=|$)/i',(string)$a)===1)apply46Stop('Secrets horen niet in fase-4.6 CLI-argumenten.');}
$o=getopt('',['monitoring-plan:','check','apply','force','help']);if(isset($o['help'])){echo"Gebruik: php bin/apply-vps-monitoring.php --monitoring-plan=... --check | sudo verenigingsplatform-host-php monitoring-apply ... --apply [--force]\n";exit(0);} $pad=trim((string)($o['monitoring-plan']??''));if($pad==='')apply46Stop('--monitoring-plan is verplicht.');try{$ctx=monitoring46PlanLeesEnValideer($pad);$p=$ctx['plan'];}catch(Throwable$e){apply46Stop($e->getMessage());}$check=isset($o['check']);$apply=isset($o['apply']);if($check===$apply)apply46Stop('Kies exact --check of --apply.');if($check){echo'CHECK OK  tenant='.$p['tenant_key']."\n";exit(0);}if(PHP_OS_FAMILY!=='Linux'||!function_exists('posix_geteuid')||posix_geteuid()!==0)apply46Stop('--apply vereist Linux root.');

try{$hostRoot=process521HostEngineRoot();}catch(Throwable$e){apply46Stop($e->getMessage());}
$selfRoot=realpath(dirname(__DIR__));
if($hostRoot===null||$selfRoot===false||!hash_equals($hostRoot,rtrim($selfRoot,'/')))apply46Stop('--apply mag alleen vanuit de root-owned host-engine worden uitgevoerd.');
try{$php=apply46Deps($p);}catch(Throwable$e){apply46Stop($e->getMessage());}

$force=isset($o['force']);apply46SafeDir('/var/log/verenigingsplatform','root','adm',0750);apply46SafeDir('/var/lib/verenigingsplatform/monitoring','root','root',0750);apply46SafeDir((string)$p['logging']['app_dir'],(string)$p['runtime']['user'],(string)$p['runtime']['group'],0750);
$targets=[[(string)$p['bundle']['apache_config'],(string)$p['apache']['config_available'],0644],[(string)$p['bundle']['systemd_service'],(string)$p['systemd']['unit_dir'].'/'.$p['systemd']['service_filename'],0644],[(string)$p['bundle']['systemd_timer'],(string)$p['systemd']['unit_dir'].'/'.$p['systemd']['timer_filename'],0644],[(string)$p['bundle']['logrotate_global'],(string)$p['logrotate']['global_file'],0644],[(string)$p['bundle']['logrotate_tenant'],(string)$p['logrotate']['tenant_file'],0644]];foreach($targets as[$s,$d,$m])apply46Install($s,$d,$m,$force);

$enabled=(string)$p['apache']['config_enabled'];$available=(string)$p['apache']['config_available'];$enabledNieuw=false;if(is_link($enabled)){$r=realpath($enabled);if($r===false||!hash_equals(runtime41NormPad($r),runtime41NormPad($available)))apply46Stop('Apache monitoring symlink wijst naar onverwacht doel.');}elseif(file_exists($enabled))apply46Stop('Apache monitoring enabled-doel is geen symlink.');else{if(!@symlink('../conf-available/'.basename($available),$enabled))apply46Stop('Apache monitoring config kon niet worden enabled.');$enabledNieuw=true;}
[$ac,,$ae]=apply46Run([(string)$p['apache']['control_binary'],'configtest']);if($ac!==0){apply46RollbackNieuweApacheLink($enabled,$enabledNieuw);apply46Stop('Apache configtest faalt: '.$ae);}[$sv,,$se]=apply46Run(['systemd-analyze','verify',(string)$p['systemd']['unit_dir'].'/'.$p['systemd']['service_filename'],(string)$p['systemd']['unit_dir'].'/'.$p['systemd']['timer_filename']]);if($sv!==0){apply46RollbackNieuweApacheLink($enabled,$enabledNieuw);apply46Stop('systemd unit verify faalt: '.$se);}[$lr,,$le]=apply46Run(['logrotate','--debug',(string)$p['logrotate']['global_file']]);if($lr!==0){apply46RollbackNieuweApacheLink($enabled,$enabledNieuw);apply46Stop('Logrotate global validatie faalt: '.$le);}[$lr2,,$le2]=apply46Run(['logrotate','--debug',(string)$p['logrotate']['tenant_file']]);if($lr2!==0){apply46RollbackNieuweApacheLink($enabled,$enabledNieuw);apply46Stop('Logrotate tenant validatie faalt: '.$le2);}
[$dr,,$de]=apply46Run(['systemctl','daemon-reload']);if($dr!==0){apply46RollbackNieuweApacheLink($enabled,$enabledNieuw);apply46Stop('systemd daemon-reload faalt: '.$de);}[$ar,,$are]=apply46Run(['systemctl','reload','apache2']);if($ar!==0){if($enabledNieuw){@unlink($enabled);apply46Run([(string)$p['apache']['control_binary'],'configtest']);apply46Run(['systemctl','reload','apache2']);}apply46Stop('Apache reload faalt: '.$are);} $checker=$hostRoot.'/bin/check-vps-health.php';[$hc,,$he]=apply46Run([$php,$checker,'--monitoring-plan='.$pad,'--probe','--write-status']);if($hc!==0)apply46Stop('Eerste health probe faalt; timer niet geactiveerd: '.$he);[$en,,$ene]=apply46Run(['systemctl','enable','--now',(string)$p['systemd']['timer_filename']]);if($en!==0)apply46Stop('Health timer kon niet worden geactiveerd: '.$ene);echo'APPLY OK  tenant='.$p['tenant_key'].' monitoring actief'."\n";
