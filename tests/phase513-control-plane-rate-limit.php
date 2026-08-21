<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function c513(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}
function r513(array$a):array{$d=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];$p=proc_open($a,$d,$x,null,null,['bypass_shell'=>true]);if(!is_resource($p))return[255,''];fclose($x[0]);$o=stream_get_contents($x[1]);fclose($x[1]);$e=stream_get_contents($x[2]);fclose($x[2]);return[proc_close($p),trim((string)$o."\n".(string)$e)];}
function rm513(string$p):void{if(is_link($p)||is_file($p)){@unlink($p);return;}if(!is_dir($p))return;foreach(scandir($p)?:[]as$n){if($n==='.'||$n==='..')continue;rm513($p.'/'.$n);}@rmdir($p);}
$tmp=sys_get_temp_dir().'/rc045-phase513-'.bin2hex(random_bytes(5));$tenants=$tmp.'/tenants';$bundle=$tmp.'/bundle';@mkdir($tenants,0750,true);
try{
 [$dc,$do]=r513([PHP_BINARY,$root.'/bin/prepare-vps-control-plane.php','--host=beheer.platform.example','--app-root='.$root,'--tenants-root='.$tenants,'--output='.$bundle,'--dry-run']);$dry=json_decode($do,true);
 c513($dc===0&&is_array($dry)&&($dry['phase']??'')==='5.1','fase-5.1 dry-run blijft geldig met rate-limitcontract');
 c513(($dry['rate_limit']['provider']??'')==='fail2ban'&&($dry['rate_limit']['filter']??'')==='apache-auth','control-plane rate-limit gebruikt expliciet Fail2ban apache-auth');
 c513(($dry['rate_limit']['maxretry']??0)===5&&($dry['rate_limit']['findtime_seconds']??0)===600&&($dry['rate_limit']['bantime_seconds']??0)===3600,'limiet is exact 5 fouten per 10 minuten met 1 uur ban');
 c513(($dry['security']['failed_auth_rate_limit_required']??false)===true&&($dry['security']['rate_limit_is_scoped_to_control_plane_log']??false)===true,'brute-forcebegrenzing en control-plane-only scope zijn contractueel verplicht');
 c513(($dry['apache']['error_log']??'')==='/var/log/apache2/verenigingsplatform-control-plane-error.log','Basic-Auth fouten krijgen een eigen vast Apache errorlog');
 c513(($dry['rate_limit']['jail_target']??'')==='/etc/fail2ban/jail.d/verenigingsplatform-control-plane.local','Fail2ban jail heeft een apart vast serverdoel');

 [$bc,$bo]=r513([PHP_BINARY,$root.'/bin/prepare-vps-control-plane.php','--host=beheer.platform.example','--app-root='.$root,'--tenants-root='.$tenants,'--output='.$bundle]);c513($bc===0,'control-plane bundle met Fail2ban-artifact wordt root-vrij gegenereerd');
 $plan=$bundle.'/control-plane-plan.json';$jail=$bundle.'/verenigingsplatform-control-plane.local';$apache=$bundle.'/050-verenigingsplatform-control-plane.conf';c513(is_file($plan)&&is_file($jail)&&is_file($apache),'plan, Fail2ban-jail en Apache-artifact bestaan');
 [$cc,$co]=r513([PHP_BINARY,$root.'/bin/apply-vps-control-plane.php','--plan='.$plan,'--check']);c513($cc===0&&str_contains($co,'CHECK OK'),'root-vrije bundlecheck valideert ook het Fail2ban-artifact');
 $j=(string)file_get_contents($jail);c513(str_contains($j,'[verenigingsplatform-control-plane]')&&str_contains($j,'filter = apache-auth'),'jail is uitsluitend de control-plane apache-auth jail');
 c513(str_contains($j,'logpath = /var/log/apache2/verenigingsplatform-control-plane-error.log')&&!str_contains($j,'/var/log/apache2/*')&&!str_contains($j,'other_vhosts_access'),'jail leest uitsluitend het dedicated control-plane errorlog');
 c513(str_contains($j,'maxretry = 5')&&str_contains($j,'findtime = 600')&&str_contains($j,'bantime = 3600'),'gegenereerde jail bevat exact de afgesproken limieten');
 $a=(string)file_get_contents($apache);c513(str_contains($a,'ErrorLog "/var/log/apache2/verenigingsplatform-control-plane-error.log"'),'beheer-vhost schrijft authenticatiefouten naar het bewaakte dedicated log');

 $apply=(string)file_get_contents($root.'/bin/apply-vps-control-plane.php');
 c513(str_contains($apply,"(string)\$p['rate_limit']['client_binary']")&&str_contains($apply,'cpaRateLimitPreflight($p)'),'root-apply vereist Fail2ban binary en preflight vóór mutaties');
 c513(str_contains($apply,'Fail2ban apache-auth filter moet root-owned en niet group/world-writable zijn.')&&str_contains($apply,'Fail2ban jail.d moet root-owned en niet group/world-writable zijn.'),'filter en jail.d trust anchors worden op root ownership en schrijfbaarheid gecontroleerd');
 c513(str_contains($apply,'function cpaLogFile')&&str_contains($apply,"@chgrp(\$p,'adm')")&&str_contains($apply,'@chmod($p,0640)'),'dedicated errorlog wordt root:adm 0640 genormaliseerd');
 $activate=strpos($apply,'function cpaRateLimitActivate');$log=$activate===false?false:strpos($apply,'cpaLogFile(',$activate);$install=$log===false?false:strpos($apply,'cpaExactBytes(',$log);c513($activate!==false&&$log!==false&&$install!==false&&$activate<$log&&$log<$install,'veilig errorlog bestaat vóór installatie van de jailconfig');
 c513(str_contains($apply,"'client_binary'],'-t'")&&str_contains($apply,"'enable','--now',(string)\$r['service']")&&str_contains($apply,"'client_binary'],'reload'")&&str_contains($apply,"'client_binary'],'status',(string)\$r['jail_name']"),'Fail2ban configtest, service, reload en jailstatus worden allemaal bewezen');
 $ratePos=strpos($apply,'cpaRateLimitActivate($p,$art)');$linkPos=$ratePos===false?false:strpos($apply,"\$link=\$p['apache']['site_enabled']",$ratePos);c513($ratePos!==false&&$linkPos!==false&&$ratePos<$linkPos,'rate-limit is aantoonbaar actief vóór de beheer-vhost wordt enabled');
 $lower=strtolower($apply);c513(!str_contains($lower,'apt-get')&&!str_contains($lower,'apt install')&&!str_contains($lower,'dnf install')&&!str_contains($lower,'yum install'),'root-apply installeert geen pakketten stilzwijgend');
 $workflow=(string)file_get_contents($root.'/.github/workflows/deploy-dev.yml');c513(str_contains($workflow,'phase513-control-plane-rate-limit.php'),'fase 5.1.3 rate-limittest draait in CI');
}finally{rm513($tmp);}echo"Phase 5.1.3 control-plane rate limit: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
