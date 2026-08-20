<?php
$root = dirname(__DIR__);
$ok = 0; $fout = 0;
function check42(bool $cond, string $label): void { global $ok,$fout; if($cond){$ok++;echo"OK: $label\n";}else{$fout++;fwrite(STDERR,"FOUT: $label\n");} }
function rr42(string $pad): void { if(is_link($pad)||is_file($pad)){@unlink($pad);return;} if(!is_dir($pad))return; foreach(scandir($pad)?:[] as $i){if($i==='.'||$i==='..')continue;rr42($pad.DIRECTORY_SEPARATOR.$i);}@rmdir($pad); }
function run42(array $args, ?string $stdin=null): array {
    $desc=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
    $p=proc_open($args,$desc,$pipes,null,null,['bypass_shell'=>true]);
    if(!is_resource($p))return[255,'proc_open mislukt'];
    if($stdin!==null)fwrite($pipes[0],$stdin); fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]);fclose($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[2]);
    return[proc_close($p),trim((string)$out."\n".(string)$err)];
}
function provision42(string $root,string $base,string $key,string $url): int { return run42([PHP_BINARY,$root.'/bin/provision-tenant.php','--key='.$key,'--name=Web '.ucfirst($key),'--url='.$url,'--root='.$base,'--modules=website,ledenadministratie'])[0]; }
function bootstrap42(string $root,string $config,string $secret): int { return run42([PHP_BINARY,$root.'/bin/bootstrap-tenant-admin.php','--config='.$config,'--password-stdin'],$secret."\n")[0]; }
function deploy42(string $root,string $config): int { return run42([PHP_BINARY,$root.'/bin/prepare-vps-deployment.php','--config='.$config,'--app-root='.$root])[0]; }
function runtime42(string $root,string $deployment): int { return run42([PHP_BINARY,$root.'/bin/prepare-vps-runtime.php','--deployment='.$deployment])[0]; }
function prepare42test(string $root,string $runtimePlan,array $extra=[]): array { return run42(array_merge([PHP_BINARY,$root.'/bin/prepare-vps-webserver.php','--runtime-plan='.$runtimePlan],$extra)); }
function checkBundle42(string $root,string $plan): array { return run42([PHP_BINARY,$root.'/bin/apply-vps-webserver.php','--plan='.$plan,'--check']); }

$tmp=sys_get_temp_dir().DIRECTORY_SEPARATOR.'rc045-phase42-'.bin2hex(random_bytes(5));
$base=$tmp.'/tenants';@mkdir($base,0750,true);
try {
    $a=$base.'/noorderhaven'; $b=$base.'/duinrand';
    check42(provision42($root,$base,'noorderhaven','https://noorderhaven.example')===0&&provision42($root,$base,'duinrand','https://duinrand.example')===0,'twee tenants worden via bestaande provisioner aangemaakt');
    check42(bootstrap42($root,$a.'/config.php','Noorderhaven-Webserver-Admin-2026!')===0&&bootstrap42($root,$b.'/config.php','Duinrand-Webserver-Admin-2026!')===0,'beide tenants krijgen veilige mastercredential');
    check42(deploy42($root,$a.'/config.php')===0&&deploy42($root,$b.'/config.php')===0,'fase 3.5 deploymentcontract bestaat voor beide tenants');
    check42(runtime42($root,$a.'/deployment.json')===0&&runtime42($root,$b.'/deployment.json')===0,'fase 4.1 runtimebundle bestaat voor beide tenants');

    $runtimeA=$a.'/runtime/runtime-plan.json'; $runtimeB=$b.'/runtime/runtime-plan.json';
    [$dryCode,$dryOut]=prepare42test($root,$runtimeA,['--dry-run']);
    $dry=json_decode($dryOut,true);
    check42($dryCode===0&&is_array($dry)&&($dry['phase']??'')==='4.2'&&($dry['server']??'')==='apache2'&&!is_dir($a.'/webserver'),'dry-run levert valide Apache-plan zonder filesystemwrite');
    check42(($dry['apache']['platform']??'')==='ubuntu-debian-apache-2.4'&&($dry['apache']['minimum_version']??'')==='2.4.49','4.2 kiest expliciet Apache 2.4 op Ubuntu/Debian met StrictHostCheck-capabele minimumversie');
    check42(($dry['activation']['artifacts_are_inactive']??false)===true&&($dry['activation']['reload_or_restart_forbidden_in_phase_4_2']??false)===true,'fase 4.2 houdt webserverartifacts bewust inactief');
    check42(($dry['security']['default_http_vhost_must_be_first']??false)===true&&str_starts_with((string)($dry['apache']['http_catchall_filename']??''),'000-'),'default/catch-all wordt deterministisch als eerste vhost voorbereid');

    [$prepA,$outA]=prepare42test($root,$runtimeA); [$prepB,$outB]=prepare42test($root,$runtimeB);
    $planA=$a.'/webserver/web-plan.json'; $planB=$b.'/webserver/web-plan.json';
    $jA=is_file($planA)?json_decode((string)file_get_contents($planA),true):null;
    $jB=is_file($planB)?json_decode((string)file_get_contents($planB),true):null;
    check42($prepA===0&&$prepB===0&&is_array($jA)&&is_array($jB),'Apache webserverbundles worden voor twee tenants geschreven');
    check42(($jA['canonical_host']??'')==='noorderhaven.example'&&($jB['canonical_host']??'')==='duinrand.example','iedere webserverbundle bindt aan exact de eigen canonieke host');
    check42(($jA['php_fpm']['socket']??'')!==($jB['php_fpm']['socket']??'')&&($jA['php_fpm']['backend']??'')!==($jB['php_fpm']['backend']??''),'iedere host krijgt eigen FPM socket én unieke FastCGI backendidentity');

    $catchA=(string)file_get_contents((string)$jA['bundle']['http_catchall_file']);
    $catchB=(string)file_get_contents((string)$jB['bundle']['http_catchall_file']);
    $httpA=(string)file_get_contents((string)$jA['bundle']['tenant_http_file']);
    $httpB=(string)file_get_contents((string)$jB['bundle']['tenant_http_file']);
    $httpsA=(string)file_get_contents((string)$jA['bundle']['https_routing_fragment']);
    $httpsB=(string)file_get_contents((string)$jB['bundle']['https_routing_fragment']);

    check42(hash_equals($catchA,$catchB),'globale HTTP catch-all is byte-identiek voor alle tenants');
    check42(str_contains($catchA,'ServerName invalid.verenigingsplatform.invalid')&&str_contains($catchA,'StrictHostCheck On')&&str_contains($catchA,'Require all denied'),'catch-all weigert onbekende hosts fail-closed met StrictHostCheck');
    check42(!str_contains($catchA,'SetHandler')&&!str_contains($catchA,'/run/php/')&&!str_contains($catchA,'ServerAlias'),'catch-all bevat geen tenant-, PHP- of aliasrouting');

    check42(str_contains($httpA,'ServerName noorderhaven.example')&&str_contains($httpB,'ServerName duinrand.example'),'HTTP-vhosts gebruiken uitsluitend hun exacte ServerName');
    check42(!str_contains($httpA,'ServerAlias')&&!str_contains($httpB,'ServerAlias'),'tenant HTTP-vhosts definiëren bewust geen ServerAlias');
    check42(str_contains($httpA,'Redirect permanent "/" "https://noorderhaven.example/"')&&str_contains($httpB,'Redirect permanent "/" "https://duinrand.example/"'),'HTTP redirects gebruiken een literal canonieke HTTPS-doelhost');
    check42(!str_contains($httpA,'HTTP_HOST')&&!str_contains($httpA,'$host')&&!str_contains($httpB,'HTTP_HOST')&&!str_contains($httpB,'$host'),'geen request Host kan in redirects worden teruggespiegeld');
    check42(!str_contains($httpA,'SetHandler')&&!str_contains($httpA,(string)$jA['php_fpm']['socket'])&&!str_contains($httpB,'SetHandler'),'HTTP-vhost routeert nooit direct naar PHP/FPM');

    check42(str_contains($httpsA,'DocumentRoot "'.$root.'"')&&str_contains($httpsB,'DocumentRoot "'.$root.'"'),'HTTPS-routing gebruikt uitsluitend de gedeelde logische release als DocumentRoot');
    check42(!str_contains($httpsA,$a.'/private')&&!str_contains($httpsB,$b.'/private')&&!str_contains($httpsA,'Alias '),'private tenantdata wordt niet als DocumentRoot of Alias geëxposeerd');
    check42(str_contains($httpsA,'proxy:unix:'.($jA['php_fpm']['socket']??'').'|'.($jA['php_fpm']['backend']??'')),'tenant A PHP-handler bindt exact eigen Unix socket en backend');
    check42(str_contains($httpsB,'proxy:unix:'.($jB['php_fpm']['socket']??'').'|'.($jB['php_fpm']['backend']??'')),'tenant B PHP-handler bindt exact eigen Unix socket en backend');
    check42(!str_contains($httpsA,(string)$jB['php_fpm']['socket'])&&!str_contains($httpsB,(string)$jA['php_fpm']['socket']),'HTTPS-fragment kan niet naar de socket van de andere tenant wijzen');
    check42(!str_contains($httpsA,'ProxyPass ')&&!str_contains($httpsA,'ProxyPassMatch')&&str_contains($httpsA,'ProxyRequests Off'),'geen generieke reverse/forward proxyroute om tenant-FPM-binding heen');
    check42(str_contains($httpsA,'(?:app|bin|tests|docs|\\.github|\\.git)')&&str_contains($httpsA,'Require all denied'),'serverconfig blokkeert tooling- en VCS-routes onafhankelijk van .htaccess');
    check42(str_contains($httpsA,'site-config(?:\\.local)?\\.php')&&str_contains($httpsA,'dev-build\\.json'),'serverconfig blokkeert gevoelige config/data-bestandsnamen');
    check42(str_contains($httpsA,'AllowOverride All')&&str_contains($httpsA,'Options -Indexes -ExecCGI +FollowSymLinks'),'gedeelde immutable release behoudt vertrouwde .htaccess-functionaliteit met beperkte directoryopties');
    check42(str_contains($httpsA,'<Directory "/">')&&str_contains($httpsA,'AllowOverride None'),'filesystem buiten gedeelde DocumentRoot wordt standaard geweigerd');
    check42(!str_contains($httpsA,'ServerName ')&&!str_contains($httpsA,'ServerAlias'),'HTTPS-routingfragment laat ServerName/TLS-wrapper bewust aan fase 4.4');

    $runtimeHash=hash_file('sha256',$runtimeA);
    check42(is_string($runtimeHash)&&hash_equals($runtimeHash,(string)$jA['source']['runtime_plan_sha256']),'web-plan bindt byte-exact aan het gevalideerde runtime-plan');
    $perm=fileperms($planA); check42($perm!==false&&(($perm&0777)===0640),'web-plan.json krijgt server-only mode 0640');
    [$checkCode,$checkOut]=checkBundle42($root,$planA);
    check42($checkCode===0&&str_contains($checkOut,'CHECK OK'),'root-vrije webserver --check valideert plan en alle artifacts');

    [$idemCode,$idemOut]=prepare42test($root,$runtimeA);
    check42($idemCode===0&&substr_count($idemOut,'ONGEWIJZIGD')===4,'identieke webservergeneratie is volledig deterministisch en idempotent');

    $fragPad=(string)$jA['bundle']['https_routing_fragment'];$fragOrig=(string)file_get_contents($fragPad);file_put_contents($fragPad,$fragOrig."# tamper\n");
    [$tamperCode,$tamperOut]=checkBundle42($root,$planA);
    check42($tamperCode!==0&&str_contains($tamperOut,'wijkt af'),'webserver --check weigert handmatig gewijzigd Apache-artifact');
    check42(prepare42test($root,$runtimeA,['--force'])[0]===0,'--force kan tenant-lokale webserverbundle gecontroleerd uit broncontract herstellen');

    $runtimeOrig=(string)file_get_contents($runtimeA);file_put_contents($runtimeA,$runtimeOrig."\n");
    [$staleCode,$staleOut]=checkBundle42($root,$planA);
    check42($staleCode!==0&&str_contains($staleOut,'gewijzigd sinds'),'webserverplan wordt ongeldig zodra bron-runtimeplan byte-inhoudelijk verandert');
    file_put_contents($runtimeA,$runtimeOrig);@chmod($runtimeA,0640);
    check42(prepare42test($root,$runtimeA,['--force'])[0]===0,'webserverbundle kan na bronherstel opnieuw worden gegenereerd');

    $outside=$tmp.'/web-buiten'; [$outsideCode,$outsideOut]=prepare42test($root,$runtimeA,['--output-dir='.$outside]);
    check42($outsideCode!==0&&!is_dir($outside)&&str_contains($outsideOut,'binnen de tenantroot'),'webserverbundle kan niet buiten eigen tenantroot worden geschreven');

    $canary=$tmp.'/web-canary';file_put_contents($canary,'NIET WIJZIGEN');$link=$a.'/web-link';
    if(function_exists('symlink')&&@symlink($canary,$link)){
        [$symCode,$symOut]=prepare42test($root,$runtimeA,['--output-dir='.$link]);
        check42($symCode!==0&&file_get_contents($canary)==='NIET WIJZIGEN','symlink als webserver outputmap wordt geweigerd zonder extern doel te wijzigen');
        @unlink($link);
    } else check42(true,'webserver output symlinktest overgeslagen op platform zonder symlinkondersteuning');

    [$secretCode,$secretOut]=run42([PHP_BINARY,$root.'/bin/prepare-vps-webserver.php','--runtime-plan='.$runtimeA,'--private-key=verboden']);
    check42($secretCode!==0&&str_contains($secretOut,'Secrets'),'webservergenerator weigert secretachtige CLI-argumenten');

    $applySrc=(string)file_get_contents($root.'/bin/apply-vps-webserver.php');
    $contractSrc=(string)file_get_contents($root.'/app/deployment/webserver-contract.php');
    check42(str_contains($applySrc,"PHP_OS_FAMILY !== 'Linux'")&&str_contains($applySrc,'posix_geteuid() !== 0'),'Apache root-installatie vereist expliciet Linux EUID 0');
    check42(str_contains($applySrc,"'/etc/apache2/sites-available'")&&str_contains($applySrc,"'/etc/verenigingsplatform/apache/fragments'"),'root-installatie gebruikt alleen vaste Ubuntu/Debian Apache-doelpaden');
    check42(str_contains($applySrc,"'-M'")&&str_contains($applySrc,'required_modules')&&str_contains($contractSrc,"'proxy_fcgi_module'"),'root-installatie controleert vereiste geladen Apache-modules');
    check42(str_contains($applySrc,'version_compare')&&str_contains($applySrc,'2.4.49'),'root-installatie bewaakt Apache-minimumversie voor StrictHostCheck');
    check42(str_contains($applySrc,"'-t'")&&str_contains($applySrc,"'-c'")&&str_contains($applySrc,'Include'),'gegenereerde inactieve artifacts krijgen een echte Apache syntaxtest vóór installatie');
    check42(str_contains($applySrc,'fase 4.2 wijzigt nooit live sites-enabled configuratie'),'afwijkend reeds actief sitebestand wordt nooit door 4.2 overschreven');
    check42(!str_contains($applySrc,"apply42Run(['a2ensite'")&&!str_contains($applySrc,'symlink('),'4.2 activeert sites niet via a2ensite of sites-enabled symlinks');
    check42(!str_contains($applySrc,"apply42Run(['systemctl'")&&!str_contains($applySrc,"apply42Run(['service'")&&!str_contains($applySrc,"apply42Run(['apache2ctl', 'graceful'"),'4.2 voert geen webserver reload/restart uit');

    $required=(array)($jA['apache']['required_modules']??[]);
    check42(in_array('alias_module',$required,true)&&in_array('proxy_module',$required,true)&&in_array('proxy_fcgi_module',$required,true)&&in_array('rewrite_module',$required,true),'plan benoemt alle kernmodules voor redirect, FPM en gedeelde .htaccess-routes');
} finally { rr42($tmp); }

echo "Phase 4.2 Apache vhosts: $ok OK, $fout fout(en)\n";
exit($fout===0?0:1);
