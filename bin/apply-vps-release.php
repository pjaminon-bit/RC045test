<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Alleen via CLI beschikbaar.'); }
require_once dirname(__DIR__) . '/app/deployment/release-contract.php';
require_once dirname(__DIR__) . '/app/deployment/process-runner.php';

function apply47Stop(string $m, int $c = 1): void { fwrite(STDERR, "FOUT: {$m}\n"); exit($c); }
function apply47Run(array $cmd, ?array $env = null): array
{
    return process521Run($cmd, null, null, $env, 3600);
}
function apply47Deps(): void
{
    foreach(['/usr/sbin/runuser','/usr/bin/env','/usr/bin/systemctl','/usr/sbin/apache2ctl'] as $b) {
        if(!is_file($b)||!is_executable($b))apply47Stop('Vereiste release-executable ontbreekt: '.$b);
    }
    if(!str_starts_with(PHP_BINARY,'/')||!is_file(PHP_BINARY)||!is_executable(PHP_BINARY))apply47Stop('Actieve PHP CLI is geen veilig absoluut executablepad.');
}
function apply47RootMeta(string $pad, int $mode, bool $map): void
{
    $s=@lstat($pad);if(!is_array($s)||is_link($pad)||($map?!is_dir($pad):!is_file($pad)))apply47Stop('Onveilig serverobject: '.$pad);
    if((int)$s['uid']!==0||(int)$s['gid']!==0||(((int)$s['mode']&0777)!==$mode))apply47Stop('Owner/group/mode wijkt af van root-immutabilitycontract: '.$pad);
}
function apply47SafeDir(string $pad, int $mode = 0755): void
{
    $pad=release47VeiligAbsoluut($pad,'Servermap'); $link=runtime41SymlinkInPad($pad); if($link!==null)apply47Stop("Symlink in serverpad: {$link}");
    if(!is_dir($pad)&&!@mkdir($pad,$mode,true)&&!is_dir($pad))apply47Stop("Map kon niet worden aangemaakt: {$pad}");
    if(!@chown($pad,0)||!@chgrp($pad,0)||!@chmod($pad,$mode))apply47Stop('Servermaprechten konden niet exact worden gezet: '.$pad);apply47RootMeta($pad,$mode,true);
}
function apply47AtomicJson(string $pad, array $data, int $mode = 0644): void
{
    if(runtime41SymlinkInPad($pad)!==null)apply47Stop("Symlink statebestand geweigerd: {$pad}");
    $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT); if(!is_string($json))apply47Stop('State kon niet als JSON worden opgebouwd.');
    $tmp=dirname($pad).'/.'.basename($pad).'.tmp.'.bin2hex(random_bytes(6));
    if(@file_put_contents($tmp,$json."\n",LOCK_EX)===false)apply47Stop('State kon niet tijdelijk worden geschreven.');
    if(!@chown($tmp,0)||!@chgrp($tmp,0)||!@chmod($tmp,$mode)){@unlink($tmp);apply47Stop('Tijdelijke state kon niet veilig worden gemetadateerd.');}
    if(is_link($pad)){@unlink($tmp);apply47Stop('Statepad werd tijdens write een symlink.');}
    if(!@rename($tmp,$pad)){@unlink($tmp);apply47Stop('State kon niet atomisch worden geplaatst.');}
    if(!@chown($pad,0)||!@chgrp($pad,0)||!@chmod($pad,$mode))apply47Stop('State-rechten konden niet worden genormaliseerd.');apply47RootMeta($pad,$mode,false);
}
function apply47Event(array $plan, string $event, array $context=[]): void
{
    $pad=(string)$plan['paths']['events']; if(runtime41SymlinkInPad($pad)!==null)apply47Stop('Release-eventlog mag geen symlink bevatten.');
    $safe=[]; foreach(['mode','from_commit','to_commit','result','tenant_count','reason']as$k){if(isset($context[$k])&&(is_scalar($context[$k])||$context[$k]===null))$safe[$k]=$context[$k];}
    $regel=['ts_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'event'=>$event,'context'=>$safe];
    $json=json_encode($regel,JSON_UNESCAPED_SLASHES); if(!is_string($json)||@file_put_contents($pad,$json."\n",FILE_APPEND|LOCK_EX)===false)apply47Stop('Release-eventlog kon niet worden geschreven.');
    if(!@chown($pad,0)||!@chgrp($pad,0)||!@chmod($pad,0644))apply47Stop('Release-eventlogrechten konden niet worden genormaliseerd.');apply47RootMeta($pad,0644,false);
}
function apply47ImmutableRechten(string $root): void
{
    if(runtime41SymlinkInPad($root)!==null||!is_dir($root))apply47Stop('Immutable release-root is onveilig: '.$root);apply47RootMeta($root,0555,true);
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
    foreach($it as$info){$p=$info->getPathname();if(is_link($p))apply47Stop('Symlink in immutable release: '.$p);if($info->isDir())apply47RootMeta($p,0555,true);elseif($info->isFile())apply47RootMeta($p,0444,false);else apply47Stop('Onverwacht object in immutable release: '.$p);}
}
function apply47MarkerEntry(string $pad, string $releases): array
{
    $ctx=release47MarkerLees($pad,true); if(!hash_equals(runtime41NormPad(dirname($ctx['path'])),runtime41NormPad($releases)))apply47Stop('Release ligt niet direct onder releases/.');apply47ImmutableRechten((string)$ctx['path']);
    return release47StateEntry($ctx);
}
function apply47EntryGelijk(array $a, array $b): bool
{
    foreach(['commit','path','manifest_sha256']as$k){if(!isset($a[$k],$b[$k])||!hash_equals((string)$a[$k],(string)$b[$k]))return false;}return true;
}
function apply47StateLees(array $plan, bool $magOntbreken=false): ?array
{
    $pad=(string)$plan['paths']['state']; if(!file_exists($pad)){if($magOntbreken)return null;apply47Stop('release-state.json ontbreekt.');}
    if(runtime41SymlinkInPad($pad)!==null||!is_file($pad))apply47Stop('release-state.json moet een veilig regulier bestand zijn.');apply47RootMeta($pad,0644,false);
    $raw=@file_get_contents($pad);$s=is_string($raw)?json_decode($raw,true):null;
    if(!is_array($s)||(int)($s['schema']??0)!==1||($s['phase']??'')!=='4.7-state'||!is_array($s['active']??null))apply47Stop('release-state.json is ongeldig.');
    $active=apply47MarkerEntry((string)($s['active']['path']??''),(string)$plan['paths']['releases_root']);
    if(!apply47EntryGelijk($active,$s['active']))apply47Stop('Actieve release-state wijkt af van marker.');
    if(isset($s['previous'])&&$s['previous']!==null){if(!is_array($s['previous']))apply47Stop('Previous release-state is ongeldig.');$p=apply47MarkerEntry((string)($s['previous']['path']??''),(string)$plan['paths']['releases_root']);if(!apply47EntryGelijk($p,$s['previous']))apply47Stop('Previous release-state wijkt af van marker.');}
    if(isset($s['transition'])&&$s['transition']!==null){$tr=$s['transition'];if(!is_array($tr)||!in_array((string)($tr['mode']??''),['deploy','rollback'],true)||!is_array($tr['from']??null)||!is_array($tr['to']??null))apply47Stop('Release transition-state is ongeldig.');$from=apply47MarkerEntry((string)($tr['from']['path']??''),(string)$plan['paths']['releases_root']);$to=apply47MarkerEntry((string)($tr['to']['path']??''),(string)$plan['paths']['releases_root']);if(!apply47EntryGelijk($from,$tr['from'])||!apply47EntryGelijk($to,$tr['to'])||!apply47EntryGelijk($from,$active)||hash_equals((string)$from['commit'],(string)$to['commit']))apply47Stop('Release transition is niet exact aan active/from/to gebonden.');}
    return $s;
}
function apply47GeenTransition(array $state): void
{
    if(isset($state['transition'])&&$state['transition']!==null)apply47Stop('Er staat een onafgeronde release-transition. Voer eerst --recover uit.');
}
function apply47CurrentReal(array $plan): string
{
    $current=(string)$plan['paths']['current'];if(!is_link($current))apply47Stop('current moet een symlink zijn.');$real=realpath($current);if($real===false)apply47Stop('current kan niet fysiek worden opgelost.');return runtime41NormPad($real);
}
function apply47CurrentMoet(array $plan, array $entry): void
{
    if(!hash_equals(apply47CurrentReal($plan),runtime41NormPad((string)$entry['path'])))apply47Stop('current wijst niet naar de actieve release-state.');apply47ImmutableRechten((string)$entry['path']);
}
function apply47Switch(array $plan, array $entry): void
{
    apply47ImmutableRechten((string)$entry['path']);$current=(string)$plan['paths']['current'];$tmp=dirname($current).'/.current.tmp.'.bin2hex(random_bytes(6));
    $rel='releases/'.(string)$entry['commit']; if(!@symlink($rel,$tmp))apply47Stop('Tijdelijke current-symlink kon niet worden gemaakt.');
    if(!@rename($tmp,$current)){@unlink($tmp);apply47Stop('current kon niet atomisch worden gewisseld.');}
    $real=realpath($current);if($real===false||!hash_equals(runtime41NormPad($real),runtime41NormPad((string)$entry['path'])))apply47Stop('current-wissel kon niet worden geverifieerd.');
}
function apply47Freeze(string $root): void
{
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as$info){$p=$info->getPathname();if(is_link($p))throw new RuntimeException("Symlink in kandidaat-release: {$p}");$mode=$info->isDir()?0555:0444;if(!@chown($p,0)||!@chgrp($p,0)||!@chmod($p,$mode))throw new RuntimeException('Kandidaatrelease kon niet immutable worden gemaakt: '.$p);}
    if(!@chown($root,0)||!@chgrp($root,0)||!@chmod($root,0555))throw new RuntimeException('Release-root kon niet immutable worden gemaakt.');apply47ImmutableRechten($root);
}
function apply47Stage(array $plan, array $manifest): array
{
    $final=(string)$plan['paths']['release_dir'];$releases=(string)$plan['paths']['releases_root'];
    if(file_exists($final)||is_link($final)){
        if(is_link($final)||!is_dir($final))apply47Stop('Bestaande release is geen veilige directory.');
        $entry=apply47MarkerEntry($final,$releases);if(!hash_equals((string)$entry['commit'],(string)$plan['commit'])||!hash_equals((string)$entry['manifest_sha256'],(string)$plan['source']['manifest_sha256']))apply47Stop('Bestaande immutable release wijkt af; overschrijven is verboden.');return $entry;
    }
    $tmp=$releases.'/.'.(string)$plan['commit'].'.tmp.'.bin2hex(random_bytes(6));if(!@mkdir($tmp,0755))apply47Stop('Tijdelijke releasedirectory kon niet worden gemaakt.');if(!@chown($tmp,0)||!@chgrp($tmp,0))apply47Stop('Tijdelijke release-root kon niet root-owned worden gemaakt.');
    try{
        foreach($manifest['files']as$rel=>$meta){$src=(string)$manifest['root'].'/'.$rel;$dst=$tmp.'/'.$rel;$dir=dirname($dst);if(!is_dir($dir)&&!@mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException("Release submap kon niet worden gemaakt: {$rel}");if(is_link($src)||!is_file($src)||!@copy($src,$dst))throw new RuntimeException("Releasebestand kon niet veilig worden gekopieerd: {$rel}");if(!@chown($dst,0)||!@chgrp($dst,0)||!@chmod($dst,0444))throw new RuntimeException('Releasebestand kon niet root-owned/read-only worden gemaakt: '.$rel);}
        $na=release47Manifest($tmp);if(!hash_equals((string)$manifest['sha256'],(string)$na['sha256'])||$manifest['file_count']!==$na['file_count']||$manifest['bytes']!==$na['bytes'])throw new RuntimeException('Gekopieerde release wijkt af van bronmanifest.');
        $marker=release47Marker($plan);$markerPad=$tmp.'/.verenigingsplatform-release.json';if(@file_put_contents($markerPad,release47Json($marker),LOCK_EX)===false)throw new RuntimeException('Release marker kon niet worden geschreven.');if(!@chown($markerPad,0)||!@chgrp($markerPad,0)||!@chmod($markerPad,0444))throw new RuntimeException('Release marker kon niet veilig worden gemetadateerd.');
        apply47Freeze($tmp);if(file_exists($final)||is_link($final))throw new RuntimeException('Release verscheen tijdens staging; overschrijven geweigerd.');if(!@rename($tmp,$final))throw new RuntimeException('Immutable release kon niet atomisch worden geplaatst.');apply47ImmutableRechten($final);
    }catch(Throwable$e){if(is_dir($tmp)){@chmod($tmp,0755);$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as$i){@chmod($i->getPathname(),$i->isDir()?0755:0644);$i->isDir()?@rmdir($i->getPathname()):@unlink($i->getPathname());}@rmdir($tmp);}apply47Stop($e->getMessage());}
    return apply47MarkerEntry($final,$releases);
}
function apply47TenantLijst(array $plan): array
{
    $base=runtime41BestaandPad((string)$plan['paths']['tenant_base'],'Tenantbasis',true);$uit=[];
    foreach(scandir($base)?:[]as$n){if($n==='.'||$n==='..')continue;$root=$base.'/'.$n;if(is_link($root))apply47Stop("Symlinktenant geweigerd: {$root}");if(!is_dir($root))apply47Stop("Onverwacht object in tenantbasis: {$root}");if(!runtime41CanoniekeTenantKey($n))apply47Stop("Ongeldige tenantdirectory in tenantbasis: {$n}");
        $runtime=$root.'/runtime/runtime-plan.json';$monitor=$root.'/monitoring/monitoring-plan.json';$config=$root.'/config.php';$private=$root.'/private';foreach([$runtime,$monitor,$config]as$f){if(is_link($f)||!is_file($f))apply47Stop("Tenant {$n} mist vereist 4.7-preflightbestand: {$f}");}if(is_link($private)||!is_dir($private))apply47Stop("Tenant {$n} mist veilige private root.");
        $raw=@file_get_contents($runtime);$rp=is_string($raw)?json_decode($raw,true):null;if(!is_array($rp)||!hash_equals($n,(string)($rp['tenant_key']??'')))apply47Stop("Runtimeplan tenantbinding ongeldig voor {$n}.");$user=(string)($rp['os']['user']??'');if(!hash_equals(runtime41VerwachteOsUser($n),$user))apply47Stop("Runtimeuser wijkt af voor {$n}.");$php=(string)($rp['settings']['php_version']??'');if(!runtime41PhpVersie($php))apply47Stop("PHP-versie ongeldig voor {$n}.");
        $uit[]=['tenant'=>$n,'root'=>$root,'config'=>$config,'private'=>$private,'runtime_plan'=>$runtime,'monitoring_plan'=>$monitor,'user'=>$user,'php_version'=>$php];
    }
    if($uit===[])apply47Stop('Geen provisioned tenants gevonden voor releasepreflight.');usort($uit,static fn($a,$b)=>strcmp($a['tenant'],$b['tenant']));return $uit;
}
function apply47PhpSyntax(string $release, array $manifest, array $tenants=[]): void
{
    $binaries=[];
    if($tenants===[])$binaries[PHP_BINARY]=true;
    else foreach($tenants as$t){$bin='/usr/bin/php'.(string)$t['php_version'];if(!is_file($bin)||!is_executable($bin))apply47Stop('PHP CLI voor tenantversie ontbreekt of is niet executable: '.$bin);$binaries[$bin]=true;}
    foreach(array_keys($binaries)as$bin){foreach(array_keys($manifest['files'])as$rel){if(!str_ends_with(strtolower($rel),'.php'))continue;[$c,,$e]=apply47Run([$bin,'-l',$release.'/'.$rel]);if($c!==0)apply47Stop("PHP syntax faalt onder {$bin} in kandidaat {$rel}: {$e}");}}
}
function apply47CandidateProbe(string $release, array $tenants): void
{
    $checker=$release.'/bin/check-release-tenant.php';foreach($tenants as$t){$php='/usr/bin/php'.(string)$t['php_version'];if(!is_file($php)||!is_executable($php))apply47Stop('PHP CLI voor tenantprobe ontbreekt of is niet executable: '.$php);$env=['VERENIGING_REQUIRE_TENANT_CONFIG'=>'1','VERENIGING_CONFIG_FILE'=>$t['config'],'VERENIGING_PRIVATE_ROOT'=>$t['private'],'PATH'=>'/usr/sbin:/usr/bin:/sbin:/bin'];[$c,,$e]=apply47Run(['/usr/sbin/runuser','-u',$t['user'],'--','/usr/bin/env','VERENIGING_REQUIRE_TENANT_CONFIG=1','VERENIGING_CONFIG_FILE='.$t['config'],'VERENIGING_PRIVATE_ROOT='.$t['private'],$php,$checker,'--expected-tenant='.$t['tenant']],$env);if($c!==0)apply47Stop('Kandidaatrelease faalt tenantprobe voor '.$t['tenant'].($e!==''?': '.$e:''));}
}
function apply47Health(string $release, array $tenants, bool $stop=true): bool
{
    $checker=$release.'/bin/check-vps-health.php';foreach($tenants as$t){$php='/usr/bin/php'.(string)$t['php_version'];[$c,,$e]=apply47Run([$php,$checker,'--monitoring-plan='.$t['monitoring_plan'],'--probe','--write-status']);if($c!==0){if($stop)apply47Stop('Healthcheck faalt voor '.$t['tenant'].($e!==''?': '.$e));return false;}}return true;
}
function apply47FpmReload(array $tenants): bool
{
    $services=[];foreach($tenants as$t)$services['php'.$t['php_version'].'-fpm.service']=true;foreach(array_keys($services)as$s){[$c,,$e]=apply47Run(['/usr/bin/systemctl','reload',$s]);if($c!==0){fwrite(STDERR,"FOUT: reload {$s} faalt: {$e}\n");return false;}}return true;
}
function apply47ApacheTest(): void { [$c,,$e]=apply47Run(['/usr/sbin/apache2ctl','configtest']);if($c!==0)apply47Stop('Apache configtest faalt: '.$e); }
function apply47State(array $active, ?array $previous, ?array $transition, int $tenants, bool $bootstrap=false): array
{
    return ['schema'=>1,'phase'=>'4.7-state','active'=>$active,'previous'=>$previous,'transition'=>$transition,'validated_tenant_count'=>$tenants,'bootstrap'=>$bootstrap,'updated_at_utc'=>gmdate('Y-m-d\TH:i:s\Z')];
}
function apply47Herstel(array $plan, array $origineel, array $tenants, string $mislukteCommit): void
{
    $old=$origineel['active'];apply47Switch($plan,$old);$reload=apply47FpmReload($tenants);$health=$reload&&apply47Health((string)$old['path'],$tenants,false);apply47AtomicJson((string)$plan['paths']['state'],apply47State($old,$origineel['previous']??null,null,count($tenants),(bool)($origineel['bootstrap']??false)));
    apply47Event($plan,'deploy_failed_rolled_back',['mode'=>'deploy','from_commit'=>$old['commit'],'to_commit'=>$mislukteCommit,'result'=>$health?'rollback-ok':'rollback-health-failed','tenant_count'=>count($tenants)]);
    if(!$health)apply47Stop('Nieuwe release faalde en rollback kon niet als gezond worden bewezen.',3);apply47Stop('Nieuwe release faalde; current is succesvol teruggezet naar vorige gezonde release.',2);
}
function apply47Recover(array $plan): void
{
    $state=apply47StateLees($plan);$tr=$state['transition']??null;if(!is_array($tr))apply47Stop('Er is geen onafgeronde release-transition om te herstellen.');$tenants=apply47TenantLijst($plan);$from=$tr['from'];$to=$tr['to'];$current=apply47CurrentReal($plan);$result='';
    if(hash_equals($current,runtime41NormPad((string)$to['path']))){apply47Switch($plan,$from);$result='candidate-reverted';}
    elseif(hash_equals($current,runtime41NormPad((string)$from['path']))){$result='transition-cleared-before-switch';}
    else apply47Stop('current wijst bij recovery naar noch transition-from noch transition-to; handmatige inspectie vereist.');
    $healthy=apply47FpmReload($tenants)&&apply47Health((string)$from['path'],$tenants,false);
    if(!$healthy)apply47Stop('Recovery heeft current naar de oorspronkelijke release gebracht, maar die kon niet als gezond worden bewezen.',4);
    apply47AtomicJson((string)$plan['paths']['state'],apply47State($from,$state['previous']??null,null,count($tenants),(bool)($state['bootstrap']??false)));
    apply47Event($plan,'transition_recovered',['mode'=>(string)$tr['mode'],'from_commit'=>$from['commit'],'to_commit'=>$to['commit'],'result'=>$result,'tenant_count'=>count($tenants)]);
    echo'RECOVER OK  commit='.$from['commit'].' tenants='.count($tenants)."\n";
}

foreach($_SERVER['argv']??[]as$a){if(preg_match('/^--(?:password|secret|token|key|dsn|webhook)(?:=|$)/i',(string)$a)===1)apply47Stop('Secrets horen niet in fase-4.7 CLI-argumenten.');}
$opt=getopt('',['plan:','platform-root::','tenant-base::','check','bootstrap','deploy','rollback','recover','help']);if(isset($opt['help'])){echo"Gebruik:\n  php bin/apply-vps-release.php --plan=... --check\n  sudo php bin/apply-vps-release.php --plan=... --bootstrap|--deploy\n  sudo php bin/apply-vps-release.php --rollback|--recover [--platform-root=/srv/verenigingsplatform --tenant-base=/srv/verenigingen]\n";exit(0);} 
$modes=array_filter(['check'=>isset($opt['check']),'bootstrap'=>isset($opt['bootstrap']),'deploy'=>isset($opt['deploy']),'rollback'=>isset($opt['rollback']),'recover'=>isset($opt['recover'])]);if(count($modes)!==1)apply47Stop('Kies exact één van --check, --bootstrap, --deploy, --rollback of --recover.');$mode=array_key_first($modes);
if(!in_array($mode,['rollback','recover'],true)){$planPad=trim((string)($opt['plan']??''));if($planPad==='')apply47Stop('--plan is verplicht.');try{$ctx=release47PlanLeesEnValideer($planPad);$plan=$ctx['plan'];$manifest=$ctx['manifest'];}catch(Throwable$e){apply47Stop($e->getMessage());}if($mode==='check'){echo'CHECK OK  commit='.$plan['commit'].' files='.$plan['source']['file_count']."\n";exit(0);}}
else{$platform=release47VeiligAbsoluut(trim((string)($opt['platform-root']??'/srv/verenigingsplatform')),'Platformroot');$tenBase=release47VeiligAbsoluut(trim((string)($opt['tenant-base']??'/srv/verenigingen')),'Tenantbasis');$plan=['paths'=>['platform_root'=>$platform,'releases_root'=>$platform.'/releases','current'=>$platform.'/current','state'=>$platform.'/release-state.json','events'=>$platform.'/release-events.jsonl','lock'=>'/var/lock/verenigingsplatform-release.lock','tenant_base'=>$tenBase]];}
if(PHP_OS_FAMILY!=='Linux'||!function_exists('posix_geteuid')||posix_geteuid()!==0)apply47Stop('Release-activatie vereist Linux root.');
apply47Deps();
$lock=@fopen((string)$plan['paths']['lock'],'c+');if(!is_resource($lock)||!flock($lock,LOCK_EX|LOCK_NB))apply47Stop('Een andere releasehandeling is al actief.');if(!@chown((string)$plan['paths']['lock'],0)||!@chgrp((string)$plan['paths']['lock'],0)||!@chmod((string)$plan['paths']['lock'],0600))apply47Stop('Release-lock kon niet root-only worden gemaakt.');apply47RootMeta((string)$plan['paths']['lock'],0600,false);
apply47SafeDir((string)$plan['paths']['platform_root']);apply47SafeDir((string)$plan['paths']['releases_root']);
if($mode==='recover'){apply47Recover($plan);exit(0);}
if($mode==='bootstrap'){
    if(file_exists((string)$plan['paths']['current'])||is_link((string)$plan['paths']['current'])||file_exists((string)$plan['paths']['state']))apply47Stop('Bootstrap mag alleen zonder bestaande current/release-state.');
    if(is_dir((string)$plan['paths']['tenant_base'])){foreach(scandir((string)$plan['paths']['tenant_base'])?:[]as$n){if($n!=='.'&&$n!=='..')apply47Stop('Bootstrap is alleen bedoeld vóór tenant-activatie; tenantbasis is niet leeg.');}}
    $entry=apply47Stage($plan,$manifest);apply47PhpSyntax((string)$entry['path'],$manifest);apply47Switch($plan,$entry);apply47AtomicJson((string)$plan['paths']['state'],apply47State($entry,null,null,0,true));apply47Event($plan,'bootstrap',['mode'=>'bootstrap','to_commit'=>$entry['commit'],'result'=>'ok','tenant_count'=>0]);echo'BOOTSTRAP OK  commit='.$entry['commit']."\n";exit(0);
}
if($mode==='deploy'){
    $state=apply47StateLees($plan);apply47GeenTransition($state);apply47CurrentMoet($plan,$state['active']);$tenants=apply47TenantLijst($plan);apply47Health((string)$state['active']['path'],$tenants,true);$candidate=apply47Stage($plan,$manifest);if(hash_equals((string)$candidate['commit'],(string)$state['active']['commit']))apply47Stop('Kandidaatrelease is al actief.');apply47PhpSyntax((string)$candidate['path'],$manifest,$tenants);apply47CandidateProbe((string)$candidate['path'],$tenants);apply47ApacheTest();
    $transition=['mode'=>'deploy','from'=>$state['active'],'to'=>$candidate,'started_at_utc'=>gmdate('Y-m-d\TH:i:s\Z')];apply47AtomicJson((string)$plan['paths']['state'],apply47State($state['active'],$state['previous']??null,$transition,count($tenants),(bool)($state['bootstrap']??false)));apply47Switch($plan,$candidate);if(!apply47FpmReload($tenants)||!apply47Health((string)$candidate['path'],$tenants,false))apply47Herstel($plan,$state,$tenants,(string)$candidate['commit']);
    $nieuw=apply47State($candidate,$state['active'],null,count($tenants),false);apply47AtomicJson((string)$plan['paths']['state'],$nieuw);apply47Event($plan,'deploy_succeeded',['mode'=>'deploy','from_commit'=>$state['active']['commit'],'to_commit'=>$candidate['commit'],'result'=>'ok','tenant_count'=>count($tenants)]);echo'DEPLOY OK  commit='.$candidate['commit'].' tenants='.count($tenants)."\n";exit(0);
}
$state=apply47StateLees($plan);apply47GeenTransition($state);apply47CurrentMoet($plan,$state['active']);if(!is_array($state['previous']??null))apply47Stop('Geen vorige gevalideerde release beschikbaar voor rollback.');$tenants=apply47TenantLijst($plan);$target=apply47MarkerEntry((string)$state['previous']['path'],(string)$plan['paths']['releases_root']);apply47CandidateProbe((string)$target['path'],$tenants);apply47ApacheTest();$transition=['mode'=>'rollback','from'=>$state['active'],'to'=>$target,'started_at_utc'=>gmdate('Y-m-d\TH:i:s\Z')];apply47AtomicJson((string)$plan['paths']['state'],apply47State($state['active'],$state['previous'],$transition,count($tenants),false));apply47Switch($plan,$target);
if(!apply47FpmReload($tenants)||!apply47Health((string)$target['path'],$tenants,false)){
    apply47Switch($plan,$state['active']);$restored=apply47FpmReload($tenants)&&apply47Health((string)$state['active']['path'],$tenants,false);apply47AtomicJson((string)$plan['paths']['state'],apply47State($state['active'],$state['previous'],null,count($tenants),false));apply47Event($plan,'rollback_failed',['mode'=>'rollback','from_commit'=>$state['active']['commit'],'to_commit'=>$target['commit'],'result'=>$restored?'current-restored-healthy':'restore-health-failed','tenant_count'=>count($tenants)]);if(!$restored)apply47Stop('Rollbackdoel faalde; oorspronkelijke current is teruggezet maar kon niet als gezond worden bewezen.',4);apply47Stop('Rollbackdoel werd niet gezond; oorspronkelijke gezonde release is hersteld.',3);
}
apply47AtomicJson((string)$plan['paths']['state'],apply47State($target,$state['active'],null,count($tenants),false));apply47Event($plan,'rollback_succeeded',['mode'=>'rollback','from_commit'=>$state['active']['commit'],'to_commit'=>$target['commit'],'result'=>'ok','tenant_count'=>count($tenants)]);echo'ROLLBACK OK  commit='.$target['commit'].' tenants='.count($tenants)."\n";