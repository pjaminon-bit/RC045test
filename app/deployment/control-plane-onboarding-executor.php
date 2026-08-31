<?php
// Resumable, root-only tenant onboarding orchestration for the platform console.
// Reuses the existing phase 3.5/4.x prepare/apply scripts. No secrets enter the
// queue: the first tenant administrator must already exist before this starts.

require_once __DIR__ . '/dns-contract.php';

function control59Stages(): array
{
    return ['start','plans_ready','runtime_applied','database_applied','fpm_active','dns_ready','tls_active','monitoring_active','lifecycle_active','complete'];
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
    control59StageIndex((string)($s['stage']??''));
    if(strtotime((string)($s['updated_at_utc']??''))===false)throw new RuntimeException('Onboardingstate mist geldige timestamp.');
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
    $allowed=['prepare-vps-deployment.php','prepare-vps-runtime.php','prepare-vps-webserver.php','prepare-vps-database.php','prepare-vps-dns.php','apply-vps-runtime.php','apply-vps-webserver.php','apply-vps-database.php','check-vps-dns.php','prepare-vps-tls.php','apply-vps-tls.php','prepare-vps-monitoring.php','apply-vps-monitoring.php','prepare-vps-lifecycle.php','apply-vps-lifecycle.php'];
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

function control59Resume(array $c,array $r): string
{
    $tenant=(string)$r['tenant_key'];$row=control58FindTenant($c,$tenant);$status=(string)($row['status']??'');
    if(!in_array($status,['setup_required','unmanaged'],true))throw new RuntimeException('Onboarding hervatten is alleen toegestaan voor een nog niet actieve tenant.');
    $root=rtrim((string)$c['tenants_root'],'/').'/'.$tenant;
    if(runtime41SymlinkInPad($root)!==null||!is_dir($root))throw new RuntimeException('Tenantroot ontbreekt of bevat een symlink.');
    $master=$root.'/private/auth/master.php';
    if(is_link($master)||!is_file($master))throw new RuntimeException('Eerste tenantbeheerder ontbreekt. Stel die eerst server-side in; wachtwoorden gaan nooit via de beheerconsole.');
    $host=(string)($row['canonical_host']??'');if(!web42CanoniekeHost($host))throw new RuntimeException('Tenant heeft geen geldige canonieke host.');
    $profile=control59DnsProfile((array)$r['admin'],$host);$state=control59StateRead($c,$tenant);
    $existing=control59DnsPlanProfile($root);
    if($existing!==null&&$existing!==$profile)throw new RuntimeException('DNS-profiel wijkt af van het bestaande tenantplan. Wijzig dit niet automatisch; controleer het bestaande plan eerst server-side.');

    $config=$root.'/config.php';$deployment=$root.'/deployment.json';$runtime=$root.'/runtime/runtime-plan.json';$web=$root.'/webserver/web-plan.json';$database=$root.'/database/database-plan.json';$dns=$root.'/dns/dns-plan.json';$readiness=$root.'/dns/dns-readiness.json';$tls=$root.'/tls/tls-plan.json';$monitoring=$root.'/monitoring/monitoring-plan.json';$lifecycle=$root.'/lifecycle/lifecycle-plan.json';

    if(control59Before((string)$state['stage'],'plans_ready')){
        [, , , $phpVersion]=control59RunPhp($c,'prepare-vps-deployment.php',['--config='.$config,'--app-root='.$c['app_root']]);
        control59RunPhp($c,'prepare-vps-runtime.php',['--deployment='.$deployment,'--php-version='.$phpVersion]);
        control59RunPhp($c,'prepare-vps-webserver.php',['--runtime-plan='.$runtime]);
        control59RunPhp($c,'prepare-vps-database.php',['--runtime-plan='.$runtime]);
        $dnsArgs=['--web-plan='.$web,'--strategy='.$profile['strategy'],'--ipv4='.implode(',',$profile['ipv4']),'--ipv6='.implode(',',$profile['ipv6'])];if($profile['strategy']==='cname')$dnsArgs[]='--cname='.$profile['cname'];
        control59RunPhp($c,'prepare-vps-dns.php',$dnsArgs);
        control59RunPhp($c,'apply-vps-runtime.php',['--plan='.$runtime,'--check']);
        control59RunPhp($c,'apply-vps-webserver.php',['--plan='.$web,'--check']);
        control59RunPhp($c,'apply-vps-database.php',['--database-plan='.$database,'--check']);
        control59Checkpoint($c,$state,'plans_ready');
    }

    preg_match('#^/usr/bin/php([0-9]{1,2}\.[0-9]{1,2})$#D',PHP_BINARY,$pm);$phpVersion=(string)($pm[1]??'');
    if($phpVersion===''||!runtime41PhpVersie($phpVersion))throw new RuntimeException('Onboarding kon de PHP-FPM-versie niet afleiden.');
    if(control59Before((string)$state['stage'],'runtime_applied')){
        control59RunPhp($c,'apply-vps-runtime.php',['--plan='.$runtime,'--apply','--fpm-pool-dir=/etc/php/'.$phpVersion.'/fpm/pool.d']);
        control59RunPhp($c,'apply-vps-webserver.php',['--plan='.$web,'--apply']);
        control59Checkpoint($c,$state,'runtime_applied');
    }
    if(control59Before((string)$state['stage'],'database_applied')){
        control59RunPhp($c,'apply-vps-database.php',['--database-plan='.$database,'--apply']);
        control59Checkpoint($c,$state,'database_applied');
    }
    if(control59Before((string)$state['stage'],'fpm_active')){
        $fpm='/usr/sbin/php-fpm'.$phpVersion;if(!is_file($fpm)||!is_executable($fpm))throw new RuntimeException('PHP-FPM testbinary ontbreekt.');
        control59RunSystem([$fpm,'-t'],'PHP-FPM configtest');control59RunSystem(['/usr/bin/systemctl','reload','php'.$phpVersion.'-fpm.service'],'PHP-FPM reload');
        control59Checkpoint($c,$state,'fpm_active');
    }
    if(control59Before((string)$state['stage'],'dns_ready')){
        [$code,$out,$err]=control59RunPhp($c,'check-vps-dns.php',['--plan='.$dns,'--samples=3','--interval=2'],true);
        if($code!==0){$detail=substr(preg_replace('/\s+/',' ',trim($err!==''?$err:$out))??'',0,260);return'Onboarding voorbereid tot DNS. Pas de providerrecords aan volgens het vastgelegde profiel en kies daarna opnieuw Hervatten.'.($detail!==''?' DNS-controle: '.$detail:'');}
        control59Checkpoint($c,$state,'dns_ready');
    }
    if(control59Before((string)$state['stage'],'tls_active')){
        control59RunPhp($c,'prepare-vps-tls.php',['--dns-readiness='.$readiness]);
        control59RunPhp($c,'apply-vps-tls.php',['--plan='.$tls,'--check']);
        control59RunPhp($c,'apply-vps-tls.php',['--plan='.$tls,'--apply']);
        control59Checkpoint($c,$state,'tls_active');
    }
    if(control59Before((string)$state['stage'],'monitoring_active')){
        control59RunPhp($c,'prepare-vps-monitoring.php',['--tls-plan='.$tls,'--database-plan='.$database]);
        control59RunPhp($c,'apply-vps-monitoring.php',['--monitoring-plan='.$monitoring,'--check']);
        control59RunPhp($c,'apply-vps-monitoring.php',['--monitoring-plan='.$monitoring,'--apply']);
        control59Checkpoint($c,$state,'monitoring_active');
    }
    if(control59Before((string)$state['stage'],'lifecycle_active')){
        control59RunPhp($c,'prepare-vps-lifecycle.php',['--monitoring-plan='.$monitoring]);
        [, $out]=control59RunPhp($c,'apply-vps-lifecycle.php',['--plan='.$lifecycle,'--status']);$statusDoc=json_decode($out,true);$life=is_array($statusDoc)?(string)($statusDoc['status']??''):'';
        if($life==='unmanaged')control59RunPhp($c,'apply-vps-lifecycle.php',['--plan='.$lifecycle,'--adopt-active']);
        elseif($life!=='active')throw new RuntimeException('Lifecycle-onboarding verwacht unmanaged of active, kreeg: '.$life);
        control59Checkpoint($c,$state,'lifecycle_active');
    }
    if(control59Before((string)$state['stage'],'complete'))control59Checkpoint($c,$state,'complete');
    return'Onboarding volledig afgerond: runtime, database, webserver, DNS, TLS, monitoring en lifecycle zijn actief.';
}
