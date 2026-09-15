<?php
// Resumable, root-only tenant onboarding orchestration for the platform console.
// Reuses the existing phase 3.5/4.x prepare/apply scripts. No secrets enter the
// queue: the first tenant administrator must already exist before this starts.

require_once __DIR__ . '/dns-contract.php';
require_once dirname(__DIR__) . '/content/tenant-content-policy.php';

function control59Stages(): array
{
    return ['start','plans_ready','runtime_applied','database_applied','fpm_active','dns_ready','tls_active','monitoring_active','acceptance_ready','lifecycle_active','pilot_ready','complete'];
}

function control59StageIndex(string $stage): int
{
    $i=array_search($stage,control59Stages(),true);
    if($i===false)throw new RuntimeException('Onbekende onboardingstage.');
    return $i;
}

function control59Before(string $current,string $target): bool
{
    return control59StageIndex($current)<control59StageIndex($target);
}

function control59EvidenceHash(array $evidence): string
{
    $json=json_encode($evidence,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if(!is_string($json))throw new RuntimeException('Onboardingbewijs kon niet worden gebonden.');
    return hash('sha256',$json);
}

function control59AcceptanceValid(mixed $evidence,string $tenant): bool
{
    if(!is_array($evidence)||(int)($evidence['schema']??0)!==1||($evidence['phase']??'')!=='5.9-acceptance'||!hash_equals($tenant,(string)($evidence['tenant_key']??'')))return false;
    if(!web42CanoniekeHost((string)($evidence['canonical_host']??''))||strtotime((string)($evidence['accepted_at_utc']??''))===false)return false;
    $checks=$evidence['checks']??null;if(!is_array($checks)||$checks===[])return false;
    foreach($checks as$key=>$value)if(!is_string($key)||!is_string($value)||$value!=='ok')return false;
    $bindings=$evidence['bindings']??null;if(!is_array($bindings))return false;
    foreach(['tenant_manifest_sha256','monitoring_plan_sha256','lifecycle_plan_sha256']as$key)if(preg_match('/^[0-9a-f]{64}$/D',(string)($bindings[$key]??''))!==1)return false;
    $modules=$evidence['modules']??null;if(!is_array($modules)||!array_is_list($modules)||!in_array('website',$modules,true))return false;
    foreach($modules as$module)if(!is_string($module)||preg_match('/^[a-z0-9_]{2,40}$/D',$module)!==1)return false;
    return true;
}

function control59PilotReadinessValid(mixed $evidence,string $tenant,?array $acceptance=null): bool
{
    if(!is_array($evidence)||(int)($evidence['schema']??0)!==1||($evidence['phase']??'')!=='5.9-pilot-readiness'||!hash_equals($tenant,(string)($evidence['tenant_key']??'')))return false;
    $host=(string)($evidence['canonical_host']??'');if(!web42CanoniekeHost($host)||strtotime((string)($evidence['confirmed_at_utc']??''))===false||strtotime((string)($evidence['export_created_at_utc']??''))===false)return false;
    $operator=(string)($evidence['operator']??'');if(preg_match('/^[A-Za-z0-9][A-Za-z0-9._@-]{1,63}$/D',$operator)!==1)return false;
    $checks=$evidence['checks']??null;if(!is_array($checks))return false;
    foreach(['branding_reviewed','homepage_customized','recovery_restore','health_probe','https_root']as$key)if(($checks[$key]??null)!=='ok')return false;
    $bindings=$evidence['bindings']??null;if(!is_array($bindings))return false;
    foreach(['acceptance_sha256','export_sha256','homepage_sha256','restore_database_sha256','restore_files_sha256']as$key)if(preg_match('/^[0-9a-f]{64}$/D',(string)($bindings[$key]??''))!==1)return false;
    if($acceptance!==null){
        if(!control59AcceptanceValid($acceptance,$tenant)||!hash_equals(strtolower((string)$acceptance['canonical_host']),strtolower($host)))return false;
        if(!hash_equals(control59EvidenceHash($acceptance),(string)$bindings['acceptance_sha256']))return false;
        $accepted=strtotime((string)$acceptance['accepted_at_utc']);$exported=strtotime((string)$evidence['export_created_at_utc']);if($accepted===false||$exported===false||$exported<$accepted)return false;
    }
    return true;
}

function control59PilotReadinessPayload(array $admin): array
{
    $keys=array_keys($admin);sort($keys,SORT_STRING);
    if($keys!==['branding_reviewed','recovery_restored','restore_database_sha256','restore_files_sha256'])throw new RuntimeException('Pilot-readinesspayload heeft onbekende velden.');
    if(($admin['branding_reviewed']??null)!==true||($admin['recovery_restored']??null)!==true)throw new RuntimeException('Pilot-readiness vereist expliciete branding/content- en recoverybevestiging.');
    $db=(string)($admin['restore_database_sha256']??'');$files=(string)($admin['restore_files_sha256']??'');
    if(preg_match('/^[0-9a-f]{64}$/D',$db)!==1||preg_match('/^[0-9a-f]{64}$/D',$files)!==1)throw new RuntimeException('Pilot-readiness vereist geldige SHA-256-bewijzen van de herstelde database en tenantbestanden.');
    return['branding_reviewed'=>true,'recovery_restored'=>true,'restore_database_sha256'=>$db,'restore_files_sha256'=>$files];
}

function control59StateFile(array $c,string $tenant): string
{
    if(!runtime41CanoniekeTenantKey($tenant))throw new RuntimeException('Ongeldige tenant-key voor onboardingstate.');
    return control58StatePaths($c)['root'].'/onboarding/'.$tenant.'.json';
}

function control59StateRead(array $c,string $tenant): array
{
    $file=control59StateFile($c,$tenant);
    if(!file_exists($file)&&!is_link($file))return['schema'=>1,'phase'=>'5.9-onboarding','tenant_key'=>$tenant,'stage'=>'start','updated_at_utc'=>gmdate('Y-m-d\TH:i:s\Z')];
    if(is_link($file)||!is_file($file)||!is_readable($file))throw new RuntimeException('Onboardingstate is onveilig.');
    $raw=@file_get_contents($file);$s=is_string($raw)?json_decode($raw,true):null;
    if(!is_array($s)||(int)($s['schema']??0)!==1||($s['phase']??'')!=='5.9-onboarding'||!hash_equals($tenant,(string)($s['tenant_key']??'')))throw new RuntimeException('Onboardingstate heeft ongeldig schema.');
    $stage=(string)($s['stage']??'');control59StageIndex($stage);
    if(strtotime((string)($s['updated_at_utc']??''))===false)throw new RuntimeException('Onboardingstate mist geldige timestamp.');
    // Oudere states mogen nooit stil door een nieuwere readinessgrens heen vallen.
    if(in_array($stage,['lifecycle_active','pilot_ready','complete'],true)&&!control59AcceptanceValid($s['acceptance']??null,$tenant)){$s['stage']='monitoring_active';return$s;}
    if(in_array($stage,['pilot_ready','complete'],true)&&!control59PilotReadinessValid($s['pilot_readiness']??null,$tenant,$s['acceptance']))$s['stage']='lifecycle_active';
    return$s;
}

function control59Checkpoint(array $c,array &$state,string $stage): void
{
    if(control59StageIndex($stage)<control59StageIndex((string)$state['stage']))throw new RuntimeException('Onboardingcheckpoint mag niet achteruit gaan.');
    $dir=dirname(control59StateFile($c,(string)$state['tenant_key']));cpeDir($dir,0750,0,$c['runtime_user']);
    $state['stage']=$stage;$state['updated_at_utc']=gmdate('Y-m-d\TH:i:s\Z');
    cpeWrite(control59StateFile($c,(string)$state['tenant_key']),$state,0640,$c['runtime_user']);
}

function control59DnsProfile(array $admin,string $host): array
{
    $keys=array_keys($admin);sort($keys,SORT_STRING);
    if($keys!==['cname','ipv4','ipv6','strategy'])throw new RuntimeException('Onboarding DNS-payload heeft onbekende velden.');
    $strategy=strtolower(trim((string)$admin['strategy']));
    if(!in_array($strategy,['direct','cname'],true))throw new RuntimeException('DNS-strategie moet direct of cname zijn.');
    $ipv4=dns43IpLijst((string)$admin['ipv4'],4);$ipv6=dns43IpLijst((string)$admin['ipv6'],6);
    if($ipv4===[]&&$ipv6===[])throw new RuntimeException('Onboarding vereist minimaal één verwacht IPv4- of IPv6-adres.');
    $cname=trim((string)$admin['cname']);
    if($strategy==='direct'){
        if($cname!=='')throw new RuntimeException('Direct DNS-profiel mag geen CNAME bevatten.');
        $cname='';
    }else{
        $cname=dns43Naam($cname);
        if(hash_equals(strtolower($host),strtolower($cname)))throw new RuntimeException('CNAME-doel moet verschillen van de tenant-host.');
    }
    return['strategy'=>$strategy,'ipv4'=>$ipv4,'ipv6'=>$ipv6,'cname'=>$cname];
}

function control59DnsPlanProfile(string $tenantRoot): ?array
{
    $file=$tenantRoot.'/dns/dns-plan.json';
    if(!file_exists($file)&&!is_link($file))return null;
    if(is_link($file)||!is_file($file)||!is_readable($file))throw new RuntimeException('Bestaand DNS-plan is onveilig.');
    $raw=@file_get_contents($file);$p=is_string($raw)?json_decode($raw,true):null;
    if(!is_array($p)||(int)($p['schema']??0)!==1||($p['phase']??'')!=='4.3')throw new RuntimeException('Bestaand DNS-plan heeft ongeldig schema.');
    $strategy=(string)($p['strategy']??'');$terminal=(array)($p['expected']['terminal']??[]);$owner=(array)($p['expected']['owner']??[]);
    $ipv4=array_values((array)($terminal['a']??[]));$ipv6=array_values((array)($terminal['aaaa']??[]));$cname='';
    if($strategy==='cname'){$cnameList=(array)($owner['cname']??[]);$cname=(string)($cnameList[0]??'');}
    return['strategy'=>$strategy,'ipv4'=>$ipv4,'ipv6'=>$ipv6,'cname'=>$cname];
}

function control59RunPhp(array $c,string $script,array $args,bool $soft=false): array
{
    $allowed=['prepare-vps-deployment.php','prepare-vps-runtime.php','prepare-vps-webserver.php','prepare-vps-database.php','prepare-vps-dns.php','apply-vps-runtime.php','apply-vps-webserver.php','apply-vps-database.php','check-vps-dns.php','prepare-vps-tls.php','apply-vps-tls.php','prepare-vps-monitoring.php','apply-vps-monitoring.php','prepare-vps-lifecycle.php','apply-vps-lifecycle.php','check-vps-health.php'];
    if(!in_array($script,$allowed,true))throw new RuntimeException('Onboarding probeerde een niet-toegestaan script te starten.');
    $php=PHP_BINARY;if(preg_match('#^/usr/bin/php([0-9]{1,2}\.[0-9]{1,2})$#D',$php,$m)!==1||!is_file($php)||!is_executable($php))throw new RuntimeException('Onboarding vereist een exact gepinde productie-PHP-binary.');
    $path=rtrim((string)$c['app_root'],'/').'/bin/'.$script;if(is_link($path)||!is_file($path))throw new RuntimeException('Onboarding-script ontbreekt of is onveilig: '.$script);
    [$code,$out,$err]=cpeRun(array_merge([$php,$path],$args));
    if($code!==0&&!$soft)throw new RuntimeException($script.' faalde: '.substr(trim($err!==''?$err:$out),0,420));
    return[$code,$out,$err,$m[1]];
}

function control59RunSystem(array $cmd,string $label): void
{
    if($cmd===[]||!is_string($cmd[0])||!str_starts_with($cmd[0],'/'))throw new RuntimeException('Onboarding systeemcommando is niet absoluut.');
    [$code,$out,$err]=cpeRun($cmd);if($code!==0)throw new RuntimeException($label.' faalde: '.substr(trim($err!==''?$err:$out),0,420));
}

function control59TenantManifestAcceptance(string $root,string $tenant,string $host): array
{
    $file=$root.'/tenant.json';
    if(runtime41SymlinkInPad($file)!==null||!is_file($file)||!is_readable($file))throw new RuntimeException('Pilotacceptatie mist een veilig tenantmanifest.');
    $raw=@file_get_contents($file);try{$m=is_string($raw)?json_decode($raw,true,64,JSON_THROW_ON_ERROR):null;}catch(Throwable$e){$m=null;}
    if(!is_array($m)||(int)($m['schema']??0)!==1||!hash_equals($tenant,(string)($m['tenant_key']??'')))throw new RuntimeException('Pilotacceptatie: tenantmanifestbinding is ongeldig.');
    $url=(string)($m['site_url']??'');$parts=parse_url($url);$manifestHost=is_array($parts)?strtolower((string)($parts['host']??'')):'';
    if(!hash_equals(strtolower($host),$manifestHost)||strtolower((string)($parts['scheme']??''))!=='https')throw new RuntimeException('Pilotacceptatie: tenantmanifest is niet aan de canonieke HTTPS-host gebonden.');
    $mods=$m['modules']??null;if(!is_array($mods)||!array_is_list($mods)||!in_array('website',$mods,true))throw new RuntimeException('Pilotacceptatie: tenantmanifest mist een geldig moduleprofiel.');
    foreach($mods as$module)if(!is_string($module)||preg_match('/^[a-z0-9_]{2,40}$/D',$module)!==1)throw new RuntimeException('Pilotacceptatie: moduleprofiel bevat een ongeldige module.');
    return['sha256'=>hash('sha256',(string)$raw),'modules'=>array_values($mods)];
}

function control59HomepageReadiness(string $root,string $name): array
{
    if($name==='')throw new RuntimeException('Pilot-readiness mist de verenigingsnaam voor contentcontrole.');
    $homepage=$root.'/private/public-content/homepage.json';if(runtime41SymlinkInPad($homepage)!==null||!is_file($homepage)||!is_readable($homepage))throw new RuntimeException('Pilot-readiness: homepagecontent ontbreekt of is onveilig.');
    $raw=@file_get_contents($homepage);try{$home=is_string($raw)?json_decode($raw,true,128,JSON_THROW_ON_ERROR):null;}catch(Throwable$e){$home=null;}
    if(!is_array($home))throw new RuntimeException('Pilot-readiness: homepagecontent is niet geldig JSON.');
    if($home===tenantContentNeutraleHomepage($name))throw new RuntimeException('Pilot-readiness: homepage staat nog op de neutrale provisioninginhoud. Pas tenant-eigen content aan voordat de pilot wordt afgerond.');
    return['sha256'=>hash('sha256',(string)$raw)];
}

function control59FreshServingChecks(array $c,string $host,string $monitoring,string $label): void
{
    control59RunPhp($c,'check-vps-health.php',['--monitoring-plan='.$monitoring,'--probe','--write-status']);
    $curl='/usr/bin/curl';if(!is_file($curl)||!is_executable($curl))throw new RuntimeException($label.': vaste curl-binary ontbreekt.');
    [$code,$out,$err]=cpeRun([$curl,'--silent','--show-error','--output','/dev/null','--write-out','%{http_code}','--connect-timeout','5','--max-time','15','--resolve',$host.':443:127.0.0.1','https://'.$host.'/']);
    if($code!==0||trim($out)!=='200')throw new RuntimeException($label.': canonieke HTTPS-root gaf geen HTTP 200.'.($err!==''?' '.substr(trim($err),0,180):''));
}

function control59PilotAcceptance(array $c,array &$state,string $root,string $tenant,string $host,string $monitoring,string $lifecycle): void
{
    $master=$root.'/private/auth/master.php';if(is_link($master)||!is_file($master))throw new RuntimeException('Pilotacceptatie: eerste tenantbeheerder ontbreekt.');
    $manifest=control59TenantManifestAcceptance($root,$tenant,$host);
    $homepage=$root.'/private/public-content/homepage.json';if(runtime41SymlinkInPad($homepage)!==null||!is_file($homepage)||!is_readable($homepage))throw new RuntimeException('Pilotacceptatie: homepagecontent ontbreekt of is onveilig.');
    $homeRaw=@file_get_contents($homepage);try{$home=is_string($homeRaw)?json_decode($homeRaw,true,64,JSON_THROW_ON_ERROR):null;}catch(Throwable$e){$home=null;}if(!is_array($home))throw new RuntimeException('Pilotacceptatie: homepagecontent is niet geldig JSON.');
    $lifeCtx=lifecycle48PlanLeesEnValideer($lifecycle);$lifePlan=$lifeCtx['plan'];
    if(!hash_equals($tenant,(string)$lifePlan['tenant_key'])||!hash_equals(strtolower($host),strtolower((string)$lifePlan['canonical_host'])))throw new RuntimeException('Pilotacceptatie: lifecyclecontract hoort niet bij deze tenant/host.');
    if(($lifePlan['lifecycle']['export_requires_suspended']??false)!==true||($lifePlan['lifecycle']['delete_requires_suspended_and_verified_export']??false)!==true||($lifePlan['security']['root_only_mutations']??false)!==true)throw new RuntimeException('Pilotacceptatie: backup/recovery-lifecyclecontract is niet fail-closed.');
    control59FreshServingChecks($c,$host,$monitoring,'Pilotacceptatie');
    $monitoringRaw=@file_get_contents($monitoring);$lifecycleRaw=@file_get_contents($lifecycle);if(!is_string($monitoringRaw)||!is_string($lifecycleRaw))throw new RuntimeException('Pilotacceptatie: planbinding kon niet byte-exact worden gelezen.');
    $state['acceptance']=[
        'schema'=>1,'phase'=>'5.9-acceptance','tenant_key'=>$tenant,'canonical_host'=>$host,'accepted_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),
        'checks'=>['first_admin'=>'ok','tenant_manifest'=>'ok','module_profile'=>'ok','homepage_content'=>'ok','health_probe'=>'ok','https_root'=>'ok','lifecycle_export_contract'=>'ok'],
        'modules'=>$manifest['modules'],
        'bindings'=>['tenant_manifest_sha256'=>$manifest['sha256'],'monitoring_plan_sha256'=>hash('sha256',$monitoringRaw),'lifecycle_plan_sha256'=>hash('sha256',$lifecycleRaw)],
    ];
    if(!control59AcceptanceValid($state['acceptance'],$tenant))throw new RuntimeException('Pilotacceptatiebewijs kon niet veilig worden opgebouwd.');
    control59Checkpoint($c,$state,'acceptance_ready');
}

function control59SnapshotReadiness(array $c,string $root,array $row): array
{
    $tenant=(string)($row['tenant_key']??'');$status=(string)($row['status']??'');$host=(string)($row['canonical_host']??'');
    $out=['technical_acceptance'=>false,'lifecycle_active'=>false,'homepage_customized'=>false,'branding_ready'=>false,'recovery_ready'=>false,'pilot_complete'=>false];
    if(!runtime41CanoniekeTenantKey($tenant))return$out;
    try{$state=control59StateRead($c,$tenant);}catch(Throwable$e){return$out;}
    $acceptance=$state['acceptance']??null;$out['technical_acceptance']=control59AcceptanceValid($acceptance,$tenant);
    $stage=(string)($state['stage']??'start');$out['lifecycle_active']=$status==='active'&&!control59Before($stage,'lifecycle_active');
    $name=is_string($row['name']??null)?trim((string)$row['name']):'';
    try{control59HomepageReadiness($root,$name);$out['homepage_customized']=true;}catch(Throwable$e){}
    $pilot=$state['pilot_readiness']??null;$valid=control59PilotReadinessValid($pilot,$tenant,is_array($acceptance)?$acceptance:null);
    $export=$row['last_export']??null;$exportSha=is_array($export)?(string)($export['sha256']??''):'';$bound=is_array($pilot)?(string)($pilot['bindings']['export_sha256']??''):'';
    $exportBound=$valid&&preg_match('/^[0-9a-f]{64}$/D',$exportSha)===1&&hash_equals($exportSha,$bound);
    $out['branding_ready']=$valid&&$out['homepage_customized']&&(($pilot['checks']['branding_reviewed']??null)==='ok');
    $out['recovery_ready']=$exportBound&&(($pilot['checks']['recovery_restore']??null)==='ok');
    $out['pilot_complete']=$stage==='complete'&&$out['technical_acceptance']&&$out['lifecycle_active']&&$out['branding_ready']&&$out['recovery_ready'];
    return$out;
}

function control59CompletePilotReadiness(array $c,array $r,array $row,string $root,string $host): string
{
    $tenant=(string)$r['tenant_key'];$payload=control59PilotReadinessPayload((array)($r['admin']??[]));$state=control59StateRead($c,$tenant);
    if((string)$state['stage']==='complete')return'Pilot-readiness was al volledig bevestigd.';
    if((string)$state['stage']!=='lifecycle_active')throw new RuntimeException('Pilot-readiness kan pas na aantoonbaar actieve lifecycle worden bevestigd.');
    $acceptance=$state['acceptance']??null;if(!control59AcceptanceValid($acceptance,$tenant)||!hash_equals(strtolower((string)$acceptance['canonical_host']),strtolower($host)))throw new RuntimeException('Pilot-readiness mist geldig technisch acceptatiebewijs.');
    if(($row['status']??'')!=='active'||($row['transition']??null)!==null)throw new RuntimeException('Pilot-readiness vereist een stabiele actieve tenant zonder lifecycle-transition.');
    $export=$row['last_export']??null;$exportSha=is_array($export)?(string)($export['sha256']??''):'';$exportAt=is_array($export)?(string)($export['created_at_utc']??''):'';
    if(preg_match('/^[0-9a-f]{64}$/D',$exportSha)!==1||strtotime($exportAt)===false)throw new RuntimeException('Pilot-readiness vereist een actuele geverifieerde lifecycle-export.');
    $accepted=strtotime((string)$acceptance['accepted_at_utc']);$exported=strtotime($exportAt);if($accepted===false||$exported===false||$exported<$accepted)throw new RuntimeException('Pilot-readiness vereist een export die na de technische pilotacceptatie is gemaakt.');
    $name=is_string($row['name']??null)?trim((string)$row['name']):'';$homepage=control59HomepageReadiness($root,$name);
    $monitoring=$root.'/monitoring/monitoring-plan.json';control59FreshServingChecks($c,$host,$monitoring,'Pilot-readiness');
    $state['pilot_readiness']=[
        'schema'=>1,'phase'=>'5.9-pilot-readiness','tenant_key'=>$tenant,'canonical_host'=>$host,'confirmed_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'operator'=>(string)$r['operator'],'export_created_at_utc'=>$exportAt,
        'checks'=>['branding_reviewed'=>'ok','homepage_customized'=>'ok','recovery_restore'=>'ok','health_probe'=>'ok','https_root'=>'ok'],
        'bindings'=>[
            'acceptance_sha256'=>control59EvidenceHash($acceptance),'export_sha256'=>$exportSha,'homepage_sha256'=>$homepage['sha256'],
            'restore_database_sha256'=>$payload['restore_database_sha256'],'restore_files_sha256'=>$payload['restore_files_sha256'],
        ],
    ];
    if(!control59PilotReadinessValid($state['pilot_readiness'],$tenant,$acceptance))throw new RuntimeException('Pilot-readinessbewijs kon niet veilig worden opgebouwd.');
    control59Checkpoint($c,$state,'pilot_ready');control59Checkpoint($c,$state,'complete');
    return'Onboarding volledig afgerond: technische acceptatie, tenant-eigen content/branding, lifecycle-export en geïsoleerd herstelbewijs zijn aantoonbaar vastgelegd.';
}

function control59Resume(array $c,array $r): string
{
    $tenant=(string)$r['tenant_key'];$row=control58FindTenant($c,$tenant);$status=(string)($row['status']??'');
    if(!in_array($status,['setup_required','unmanaged','active'],true))throw new RuntimeException('Onboarding hervatten is niet toegestaan voor deze tenantstatus.');
    $root=rtrim((string)$c['tenants_root'],'/').'/'.$tenant;
    if(runtime41SymlinkInPad($root)!==null||!is_dir($root))throw new RuntimeException('Tenantroot ontbreekt of bevat een symlink.');
    $master=$root.'/private/auth/master.php';if(is_link($master)||!is_file($master))throw new RuntimeException('Eerste tenantbeheerder ontbreekt. Stel die eerst server-side in; wachtwoorden gaan nooit via de beheerconsole.');
    $host=(string)($row['canonical_host']??'');if(!web42CanoniekeHost($host))throw new RuntimeException('Tenant heeft geen geldige canonieke host.');
    $state=control59StateRead($c,$tenant);
    if($status==='active')return control59CompletePilotReadiness($c,$r,$row,$root,$host);
    $profile=control59DnsProfile((array)$r['admin'],$host);
    $existing=control59DnsPlanProfile($root);if($existing!==null&&$existing!==$profile)throw new RuntimeException('DNS-profiel wijkt af van het bestaande tenantplan. Wijzig dit niet automatisch; controleer het bestaande plan eerst server-side.');

    $config=$root.'/config.php';$deployment=$root.'/deployment.json';$runtime=$root.'/runtime/runtime-plan.json';$web=$root.'/webserver/web-plan.json';$database=$root.'/database/database-plan.json';$dns=$root.'/dns/dns-plan.json';$readiness=$root.'/dns/dns-readiness.json';$tls=$root.'/tls/tls-plan.json';$monitoring=$root.'/monitoring/monitoring-plan.json';$lifecycle=$root.'/lifecycle/lifecycle-plan.json';

    if(control59Before((string)$state['stage'],'plans_ready')){
        [, , , $phpVersion]=control59RunPhp($c,'prepare-vps-deployment.php',['--config='.$config,'--app-root='.$c['app_root']]);
        control59RunPhp($c,'prepare-vps-runtime.php',['--deployment='.$deployment,'--php-version='.$phpVersion]);
        control59RunPhp($c,'prepare-vps-webserver.php',['--runtime-plan='.$runtime]);
        control59RunPhp($c,'prepare-vps-database.php',['--runtime-plan='.$runtime]);
        $dnsArgs=['--web-plan='.$web,'--strategy='.$profile['strategy'],'--ipv4='.implode(',',$profile['ipv4']),'--ipv6='.implode(',',$profile['ipv6'])];if($profile['strategy']==='cname')$dnsArgs[]='--cname='.$profile['cname'];
        control59RunPhp($c,'prepare-vps-dns.php',$dnsArgs);
        control59RunPhp($c,'apply-vps-runtime.php',['--plan='.$runtime,'--check']);control59RunPhp($c,'apply-vps-webserver.php',['--plan='.$web,'--check']);control59RunPhp($c,'apply-vps-database.php',['--database-plan='.$database,'--check']);
        control59Checkpoint($c,$state,'plans_ready');
    }

    preg_match('#^/usr/bin/php([0-9]{1,2}\.[0-9]{1,2})$#D',PHP_BINARY,$pm);$phpVersion=(string)($pm[1]??'');if($phpVersion===''||!runtime41PhpVersie($phpVersion))throw new RuntimeException('Onboarding kon de PHP-FPM-versie niet afleiden.');
    if(control59Before((string)$state['stage'],'runtime_applied')){control59RunPhp($c,'apply-vps-runtime.php',['--plan='.$runtime,'--apply','--fpm-pool-dir=/etc/php/'.$phpVersion.'/fpm/pool.d']);control59RunPhp($c,'apply-vps-webserver.php',['--plan='.$web,'--apply']);control59Checkpoint($c,$state,'runtime_applied');}
    if(control59Before((string)$state['stage'],'database_applied')){control59RunPhp($c,'apply-vps-database.php',['--database-plan='.$database,'--apply']);control59Checkpoint($c,$state,'database_applied');}
    if(control59Before((string)$state['stage'],'fpm_active')){$fpm='/usr/sbin/php-fpm'.$phpVersion;if(!is_file($fpm)||!is_executable($fpm))throw new RuntimeException('PHP-FPM testbinary ontbreekt.');control59RunSystem([$fpm,'-t'],'PHP-FPM configtest');control59RunSystem(['/usr/bin/systemctl','reload','php'.$phpVersion.'-fpm.service'],'PHP-FPM reload');control59Checkpoint($c,$state,'fpm_active');}
    if(control59Before((string)$state['stage'],'dns_ready')){[$code,$out,$err]=control59RunPhp($c,'check-vps-dns.php',['--plan='.$dns,'--samples=3','--interval=2'],true);if($code!==0){$detail=substr(preg_replace('/\s+/',' ',trim($err!==''?$err:$out))??'',0,260);return'Onboarding voorbereid tot DNS. Pas de providerrecords aan volgens het vastgelegde profiel en kies daarna opnieuw Hervatten.'.($detail!==''?' DNS-controle: '.$detail:'');}control59Checkpoint($c,$state,'dns_ready');}
    if(control59Before((string)$state['stage'],'tls_active')){control59RunPhp($c,'prepare-vps-tls.php',['--dns-readiness='.$readiness]);control59RunPhp($c,'apply-vps-tls.php',['--plan='.$tls,'--check']);control59RunPhp($c,'apply-vps-tls.php',['--plan='.$tls,'--apply']);control59Checkpoint($c,$state,'tls_active');}
    if(control59Before((string)$state['stage'],'monitoring_active')){control59RunPhp($c,'prepare-vps-monitoring.php',['--tls-plan='.$tls,'--database-plan='.$database]);control59RunPhp($c,'apply-vps-monitoring.php',['--monitoring-plan='.$monitoring,'--check']);control59RunPhp($c,'apply-vps-monitoring.php',['--monitoring-plan='.$monitoring,'--apply']);control59Checkpoint($c,$state,'monitoring_active');}
    if(control59Before((string)$state['stage'],'acceptance_ready')){control59RunPhp($c,'prepare-vps-lifecycle.php',['--monitoring-plan='.$monitoring]);[, $out]=control59RunPhp($c,'apply-vps-lifecycle.php',['--plan='.$lifecycle,'--status']);$statusDoc=json_decode($out,true);$life=is_array($statusDoc)?(string)($statusDoc['status']??''):'';if(!in_array($life,['unmanaged','active'],true))throw new RuntimeException('Pilotacceptatie verwacht unmanaged of active lifecycle, kreeg: '.$life);control59PilotAcceptance($c,$state,$root,$tenant,$host,$monitoring,$lifecycle);}
    if(control59Before((string)$state['stage'],'lifecycle_active')){[, $out]=control59RunPhp($c,'apply-vps-lifecycle.php',['--plan='.$lifecycle,'--status']);$statusDoc=json_decode($out,true);$life=is_array($statusDoc)?(string)($statusDoc['status']??''):'';if($life==='unmanaged')control59RunPhp($c,'apply-vps-lifecycle.php',['--plan='.$lifecycle,'--adopt-active']);elseif($life!=='active')throw new RuntimeException('Lifecycle-onboarding verwacht unmanaged of active, kreeg: '.$life);control59Checkpoint($c,$state,'lifecycle_active');}
    return'Infrastructuur en technische pilotacceptatie zijn actief. Rond nu tenant-eigen content/branding af, voer op TEST een suspend → export → geïsoleerde restore → activate-herstelproef uit en bevestig daarna het pilot-readinesscheckpoint in de onboardingwizard.';
}
