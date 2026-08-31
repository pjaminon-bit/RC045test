<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function c59(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}
function throws59(callable$f):bool{try{$f();return false;}catch(Throwable$e){return true;}}
require_once $root.'/app/deployment/control-plane-admin-suite-contract.php';
require_once $root.'/app/deployment/control-plane-onboarding-executor.php';
require_once $root.'/app/control-plane/control-plane-runtime.php';
require_once $root.'/app/control-plane/control-plane-onboarding.php';

c59(in_array('onboarding-resume',control58PlatformActions(),true),'onboarding-resume is een expliciete platformactie');
c59(!in_array('delete',control58ScheduleActions(),true)&&!in_array('purge',control58ScheduleActions(),true),'destructieve lifecycleacties blijven niet-planbaar');
c59(control59Stages()===['start','plans_ready','runtime_applied','database_applied','fpm_active','dns_ready','tls_active','monitoring_active','lifecycle_active','complete'],'onboarding heeft vaste resumable checkpointvolgorde');
c59(control59Before('plans_ready','dns_ready')&&!control59Before('tls_active','dns_ready'),'checkpointvergelijking gaat alleen vooruit');
c59(throws59(static fn()=>control59StageIndex('evil')),'onbekende onboardingstage faalt gesloten');

$direct=control59DnsProfile(['strategy'=>'direct','ipv4'=>'203.0.113.10','ipv6'=>'','cname'=>''],'club.example.nl');
c59($direct['strategy']==='direct'&&$direct['ipv4']===['203.0.113.10']&&$direct['ipv6']===[]&&$direct['cname']==='','direct DNS-profiel wordt canoniek gevalideerd');
$cname=control59DnsProfile(['strategy'=>'cname','ipv4'=>'203.0.113.10','ipv6'=>'2001:db8::10','cname'=>'vps.example.nl'],'club.example.nl');
c59($cname['strategy']==='cname'&&$cname['cname']==='vps.example.nl','CNAME-profiel vereist afzonderlijk canoniek doel');
c59(throws59(static fn()=>control59DnsProfile(['strategy'=>'direct','ipv4'=>'','ipv6'=>'','cname'=>''],'club.example.nl')),'DNS-profiel zonder eindadres wordt geweigerd');
c59(throws59(static fn()=>control59DnsProfile(['strategy'=>'cname','ipv4'=>'203.0.113.10','ipv6'=>'','cname'=>'club.example.nl'],'club.example.nl')),'CNAME naar eigen tenant-host wordt geweigerd');
c59(cpOnboardIpCsv('203.0.113.10, 203.0.113.10',4)==='203.0.113.10','webhelper dedupliceert publieke DNS-adressen');
c59(throws59(static fn()=>cpOnboardIpCsv('geen-ip',4)),'webhelper weigert ongeldige IP-invoer vóór queuewrite');

$tmp=sys_get_temp_dir().'/rc045-phase59-'.bin2hex(random_bytes(5));@mkdir($tmp.'/dns',0770,true);$dnsFile=$tmp.'/dns/dns-plan.json';
file_put_contents($dnsFile,json_encode(['schema'=>1,'phase'=>'4.3','strategy'=>'cname','expected'=>['owner'=>['cname'=>['vps.example.nl']],'terminal'=>['a'=>['203.0.113.10'],'aaaa'=>[]]]],JSON_UNESCAPED_SLASHES));
$profile=control59DnsPlanProfile($tmp);c59(is_array($profile)&&$profile['strategy']==='cname'&&$profile['ipv4']===['203.0.113.10']&&$profile['cname']==='vps.example.nl','bestaand DNS-plan levert gesanitiseerd herbruikbaar profiel');
@unlink($dnsFile);@rmdir($tmp.'/dns');@rmdir($tmp);

$exec=(string)file_get_contents($root.'/app/deployment/control-plane-onboarding-executor.php');
$adminExec=(string)file_get_contents($root.'/app/deployment/control-plane-admin-executor.php');
$web=(string)file_get_contents($root.'/app/control-plane/control-plane-onboarding.php');
$page=(string)file_get_contents($root.'/app/control-plane-web/onboarding.php');
$js=(string)file_get_contents($root.'/app/control-plane-web/app.js');
$rootExec=(string)file_get_contents($root.'/bin/control-plane-executor.php');
foreach(['prepare-vps-deployment.php','prepare-vps-runtime.php','prepare-vps-webserver.php','prepare-vps-database.php','prepare-vps-dns.php','check-vps-dns.php','prepare-vps-tls.php','prepare-vps-monitoring.php','prepare-vps-lifecycle.php']as$script)c59(str_contains($exec,"'{$script}'"),'onboarding hergebruikt bestaande vaste fase: '.$script);
c59(str_contains($exec,'control59Checkpoint')&&str_contains($exec,"'plans_ready'")&&str_contains($exec,"'dns_ready'")&&str_contains($exec,"'complete'"),'root-onboarding bewaart expliciete checkpoints');
c59(str_contains($exec,"check-vps-dns.php")&&str_contains($exec,'Onboarding voorbereid tot DNS')&&str_contains($exec,'Hervatten'),'DNS is een bewust hervatbaar extern wachtpunt');
c59(!str_contains($exec,'bootstrap-tenant-admin.php')&&!str_contains($exec,'password-stdin')&&!str_contains($exec,'--force'),'automatische orchestrator verwerkt geen beheerwachtwoord en gebruikt geen force-opties');
c59(!str_contains($exec,'shell_exec(')&&!str_contains($exec,'proc_open(')&&!str_contains($exec,'passthru(')&&!str_contains($exec,'system('),'onboarding introduceert geen vrije shell/process primitive');
c59(str_contains($exec,"'/usr/bin/systemctl','reload'")&&str_contains($exec,"'/usr/sbin/php-fpm'"),'enige directe systeemmutatie is vaste PHP-FPM test/reloadroute');
c59(str_contains($adminExec,"'onboarding-resume'=>control59Resume")&&str_contains($adminExec,'control59DnsProfile($admin'),'root-executor valideert en routeert onboarding-resume');
c59(str_contains($adminExec,"$row['dns_profile']=control59DnsPlanProfile")||str_contains($adminExec,"['dns_profile']=control59DnsPlanProfile"),'snapshot exposeert alleen gesanitiseerd DNS-profiel voor hervatten');
c59(str_contains($rootExec,'control58PlatformActions()')&&str_contains($rootExec,'control58ValidateAdminRequest'),'queue gebruikt gedeeld actioncontract en root-side adminvalidatie');
c59(str_contains($web,"cpSuiteQueue($tenant,'onboarding-resume'")&&str_contains($web,'cpSuiteRequire(\'mutate\')'),'webhelper vereist mutatierecht en schrijft alleen geschematiseerde onboardingrequest');
c59(str_contains($page,'Onboarding hervatten')&&str_contains($page,'Wachtwoorden en providercredentials blijven buiten de browser')&&str_contains($page,'dns_strategy'),'dedicated wizard maakt secretgrens en DNS-input duidelijk');
c59(str_contains($page,'bootstrap-tenant-admin.php')&&str_contains($page,'server-side stap'),'eerste beheerder blijft expliciet een server-side secretstap');
c59(str_contains($js,"href = '/onboarding.php'")&&str_contains($js,'Automatische onboarding openen'),'bestaande Onboarding-navigatie linkt CSP-safe naar automatische wizard');

echo"Phase 5.9 control-plane onboarding: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);