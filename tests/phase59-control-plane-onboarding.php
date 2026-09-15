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
c59(control59Stages()===['start','plans_ready','runtime_applied','database_applied','fpm_active','dns_ready','tls_active','monitoring_active','acceptance_ready','lifecycle_active','pilot_ready','complete'],'onboarding heeft vaste resumable checkpointvolgorde met pilot-readiness vóór complete');
c59(control59Before('monitoring_active','acceptance_ready')&&control59Before('acceptance_ready','lifecycle_active')&&control59Before('lifecycle_active','pilot_ready')&&control59Before('pilot_ready','complete'),'technische acceptatie, lifecycle en pilot-readiness zijn afzonderlijke grenzen');
c59(throws59(static fn()=>control59StageIndex('evil')),'onbekende onboardingstage faalt gesloten');

$evidence=['schema'=>1,'phase'=>'5.9-acceptance','tenant_key'=>'club-test','canonical_host'=>'club.example.nl','accepted_at_utc'=>'2026-09-15T10:00:00Z','checks'=>['health_probe'=>'ok','https_root'=>'ok'],'modules'=>['website','ledenadministratie'],'bindings'=>['tenant_manifest_sha256'=>str_repeat('a',64),'monitoring_plan_sha256'=>str_repeat('b',64),'lifecycle_plan_sha256'=>str_repeat('c',64)]];
c59(control59AcceptanceValid($evidence,'club-test'),'technisch acceptatiebewijs vereist geldige tenant/host/checks/modules en bronhashes');
$bad=$evidence;$bad['tenant_key']='andere';c59(!control59AcceptanceValid($bad,'club-test'),'acceptatiebewijs van andere tenant wordt geweigerd');
$bad=$evidence;$bad['checks']['https_root']='fail';c59(!control59AcceptanceValid($bad,'club-test'),'acceptatiebewijs met niet-groene check wordt geweigerd');

$pilot=['schema'=>1,'phase'=>'5.9-pilot-readiness','tenant_key'=>'club-test','canonical_host'=>'club.example.nl','confirmed_at_utc'=>'2026-09-15T12:00:00Z','operator'=>'platformadmin','export_created_at_utc'=>'2026-09-15T11:00:00Z','checks'=>['branding_reviewed'=>'ok','homepage_customized'=>'ok','recovery_restore'=>'ok','health_probe'=>'ok','https_root'=>'ok'],'bindings'=>['acceptance_sha256'=>control59EvidenceHash($evidence),'export_sha256'=>str_repeat('d',64),'homepage_sha256'=>str_repeat('e',64),'restore_database_sha256'=>str_repeat('f',64),'restore_files_sha256'=>str_repeat('1',64)]];
c59(control59PilotReadinessValid($pilot,'club-test',$evidence),'pilot-readiness bindt branding/content, actuele export, restorehashes en technische acceptatie');
$bad=$pilot;$bad['bindings']['acceptance_sha256']=str_repeat('0',64);c59(!control59PilotReadinessValid($bad,'club-test',$evidence),'pilot-readiness met verkeerde acceptatiebinding wordt geweigerd');
$bad=$pilot;$bad['export_created_at_utc']='2026-09-15T09:00:00Z';c59(!control59PilotReadinessValid($bad,'club-test',$evidence),'export van vóór technische acceptatie kan de pilot niet afronden');
c59(control59PilotReadinessPayload(['branding_reviewed'=>true,'recovery_restored'=>true,'restore_database_sha256'=>str_repeat('a',64),'restore_files_sha256'=>str_repeat('b',64)])['recovery_restored']===true,'readinesspayload accepteert uitsluitend expliciete bevestiging met twee SHA-256-bewijzen');
c59(throws59(static fn()=>control59PilotReadinessPayload(['branding_reviewed'=>true,'recovery_restored'=>false,'restore_database_sha256'=>str_repeat('a',64),'restore_files_sha256'=>str_repeat('b',64)])),'readinesspayload zonder uitgevoerde restore faalt gesloten');

$stateTmp=sys_get_temp_dir().'/rc045-phase59-state-'.bin2hex(random_bytes(5));@mkdir($stateTmp.'/onboarding',0770,true);$legacy=$stateTmp.'/onboarding/club-test.json';
file_put_contents($legacy,json_encode(['schema'=>1,'phase'=>'5.9-onboarding','tenant_key'=>'club-test','stage'=>'complete','updated_at_utc'=>'2026-09-15T10:00:00Z'],JSON_UNESCAPED_SLASHES));
$legacyState=control59StateRead(['snapshot_file'=>$stateTmp.'/snapshot.json'],'club-test');c59(($legacyState['stage']??'')==='monitoring_active','legacy complete zonder technisch acceptatiebewijs wordt fail-closed teruggebracht');
file_put_contents($legacy,json_encode(['schema'=>1,'phase'=>'5.9-onboarding','tenant_key'=>'club-test','stage'=>'complete','updated_at_utc'=>'2026-09-15T10:00:00Z','acceptance'=>$evidence],JSON_UNESCAPED_SLASHES));
$oldAccepted=control59StateRead(['snapshot_file'=>$stateTmp.'/snapshot.json'],'club-test');c59(($oldAccepted['stage']??'')==='lifecycle_active','oude complete met alleen technische acceptatie blijft open voor pilot-readiness');
file_put_contents($legacy,json_encode(['schema'=>1,'phase'=>'5.9-onboarding','tenant_key'=>'club-test','stage'=>'complete','updated_at_utc'=>'2026-09-15T12:00:00Z','acceptance'=>$evidence,'pilot_readiness'=>$pilot],JSON_UNESCAPED_SLASHES));
$readyState=control59StateRead(['snapshot_file'=>$stateTmp.'/snapshot.json'],'club-test');c59(($readyState['stage']??'')==='complete','complete blijft alleen geldig met technisch én pilot-readinessbewijs');
@unlink($legacy);@rmdir($stateTmp.'/onboarding');@rmdir($stateTmp);

$contentTmp=sys_get_temp_dir().'/rc045-phase59-content-'.bin2hex(random_bytes(5));@mkdir($contentTmp.'/private/public-content',0770,true);$neutral=tenantContentNeutraleHomepage('Club Test');file_put_contents($contentTmp.'/private/public-content/homepage.json',json_encode($neutral,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
c59(throws59(static fn()=>control59HomepageReadiness($GLOBALS['contentTmp'],'Club Test')),'neutrale provisioninghomepage blokkeert pilot-readiness');
$neutral['hero_intro']['nl']='Welkom bij onze eigen pilotvereniging.';file_put_contents($contentTmp.'/private/public-content/homepage.json',json_encode($neutral,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
c59(preg_match('/^[0-9a-f]{64}$/D',control59HomepageReadiness($contentTmp,'Club Test')['sha256'])===1,'tenant-eigen homepage levert bytegebonden readinessbewijs');
@unlink($contentTmp.'/private/public-content/homepage.json');@rmdir($contentTmp.'/private/public-content');@rmdir($contentTmp.'/private');@rmdir($contentTmp);

$direct=control59DnsProfile(['strategy'=>'direct','ipv4'=>'203.0.113.10','ipv6'=>'','cname'=>''],'club.example.nl');
c59($direct['strategy']==='direct'&&$direct['ipv4']===['203.0.113.10']&&$direct['ipv6']===[]&&$direct['cname']==='','direct DNS-profiel wordt canoniek gevalideerd');
$cname=control59DnsProfile(['strategy'=>'cname','ipv4'=>'203.0.113.10','ipv6'=>'2001:db8::10','cname'=>'vps.example.nl'],'club.example.nl');
c59($cname['strategy']==='cname'&&$cname['cname']==='vps.example.nl','CNAME-profiel vereist afzonderlijk canoniek doel');
c59(throws59(static fn()=>control59DnsProfile(['strategy'=>'direct','ipv4'=>'','ipv6'=>'','cname'=>''],'club.example.nl')),'DNS-profiel zonder eindadres wordt geweigerd');
c59(cpOnboardIpCsv('203.0.113.10, 203.0.113.10',4)==='203.0.113.10','webhelper dedupliceert publieke DNS-adressen');
c59(cpOnboardSha256(str_repeat('A',64),'test')===str_repeat('a',64),'webhelper canoniseert veilige recovery-SHA-256');

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
foreach(['prepare-vps-deployment.php','prepare-vps-runtime.php','prepare-vps-webserver.php','prepare-vps-database.php','prepare-vps-dns.php','check-vps-dns.php','prepare-vps-tls.php','prepare-vps-monitoring.php','prepare-vps-lifecycle.php','check-vps-health.php']as$script)c59(str_contains($exec,"'{$script}'"),'onboarding hergebruikt bestaande vaste fase: '.$script);
c59(str_contains($exec,"'pilot_ready'")&&str_contains($exec,"'lifecycle_active'")&&str_contains($exec,"'complete'"),'root-onboarding bewaart afzonderlijke lifecycle-, pilot-readiness- en completecheckpoints');
c59(str_contains($exec,'tenantContentNeutraleHomepage')&&str_contains($exec,'homepage staat nog op de neutrale provisioninginhoud'),'pilot-readiness weigert onbewust neutrale startcontent');
c59(str_contains($exec,"'restore_database_sha256'")&&str_contains($exec,"'restore_files_sha256'")&&str_contains($exec,"'export_sha256'"),'pilot-readiness bindt operationeel herstelbewijs aan actuele lifecycle-export');
c59(str_contains($exec,"'check-vps-health.php'")&&str_contains($exec,"'--probe','--write-status'")&&str_contains($exec,"'https://'.\$host.'/'")&&str_contains($exec,"trim(\$out)!=='200'"),'finale readiness hergebruikt verse healthprobe en echte HTTPS-root HTTP 200');
c59(str_contains($exec,"'lifecycle_export_contract'=>'ok'")&&str_contains($exec,"'tenant_manifest_sha256'")&&str_contains($exec,"'lifecycle_plan_sha256'"),'technisch acceptatiebewijs bindt module- en lifecyclecontracten aan actuele plannen');
$acceptCall=strrpos($exec,'control59PilotAcceptance($c,$state');$adoptCall=strpos($exec,"'--adopt-active'");c59($acceptCall!==false&&$adoptCall!==false&&$acceptCall<$adoptCall,'technische pilotacceptatie draait vóór lifecycle-adoptie');
c59(!str_contains($exec,'bootstrap-tenant-admin.php')&&!str_contains($exec,'password-stdin')&&!str_contains($exec,'--force'),'automatische orchestrator verwerkt geen beheerwachtwoord en gebruikt geen force-opties');
c59(!str_contains($exec,'shell_exec(')&&!str_contains($exec,'proc_open(')&&!str_contains($exec,'passthru(')&&!str_contains($exec,'system('),'onboarding introduceert geen vrije shell/process primitive');
c59(str_contains($adminExec,"\$status==='active'")&&str_contains($adminExec,'control59PilotReadinessPayload'),'root-executor heeft een afzonderlijk streng payloadcontract voor de actieve readinessfase');
c59(str_contains($adminExec,"'Content & branding gereed'")&&str_contains($adminExec,"'Export & herstel bewezen'")&&str_contains($adminExec,"'Pilot gereed'")&&str_contains($adminExec,"'contract'=>'pre-5.9'"),'snapshot maakt nieuwe readinessgrenzen zichtbaar zonder bestaande pre-5.9-tenants stil te herclassificeren');
c59(str_contains($web,"\$status==='active'")&&str_contains($web,"'restore_database_sha256'")&&str_contains($web,"'restore_files_sha256'"),'webhelper gebruikt bij actieve tenant alleen geschoonde readinessmetadata');
c59(str_contains($page,'Pilot-readiness afronden')&&str_contains($page,'suspend → export → geïsoleerde restore → activate')&&str_contains($page,'Infrastructuur actief is nadrukkelijk niet hetzelfde als pilot gereed'),'wizard onderscheidt infrastructuuractief van eindacceptatie');
c59(str_contains($page,'bootstrap-tenant-admin.php')&&str_contains($page,'server-side stap'),'eerste beheerder blijft expliciet een server-side secretstap');
c59(str_contains($js,"href = '/onboarding.php'")&&str_contains($js,'Automatische onboarding openen'),'bestaande Onboarding-navigatie linkt CSP-safe naar automatische wizard');
c59(str_contains($rootExec,"'onboarding-resume'")&&str_contains($rootExec,'cpeMuterendeActie'),'bestaande mutatie-interlock blijft van toepassing op beide onboardingfasen');

echo"Phase 5.9 control-plane onboarding: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);