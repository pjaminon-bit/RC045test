<?php
$root = dirname(__DIR__);
$ok = 0; $fout = 0;
function check41(bool $cond, string $label): void { global $ok,$fout; if($cond){$ok++;echo"OK: $label\n";}else{$fout++;fwrite(STDERR,"FOUT: $label\n");} }
function rr41(string $pad): void { if(is_link($pad)||is_file($pad)){@unlink($pad);return;} if(!is_dir($pad))return; foreach(scandir($pad)?:[] as $i){if($i==='.'||$i==='..')continue;rr41($pad.DIRECTORY_SEPARATOR.$i);}@rmdir($pad); }
function run41(array $args, ?string $stdin=null): array {
    $desc=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
    $p=proc_open($args,$desc,$pipes,null,null,['bypass_shell'=>true]);
    if(!is_resource($p))return[255,'proc_open mislukt'];
    if($stdin!==null)fwrite($pipes[0],$stdin); fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]);fclose($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[2]);
    return[proc_close($p),trim((string)$out."\n".(string)$err)];
}
function provision41(string $root,string $base,string $key,string $url): int {
    return run41([PHP_BINARY,$root.'/bin/provision-tenant.php','--key='.$key,'--name=Runtime '.ucfirst($key),'--url='.$url,'--root='.$base,'--modules=website,ledenadministratie'])[0];
}
function bootstrap41(string $root,string $config,string $secret): int {
    return run41([PHP_BINARY,$root.'/bin/bootstrap-tenant-admin.php','--config='.$config,'--password-stdin'],$secret."\n")[0];
}
function deploy41(string $root,string $config): int {
    return run41([PHP_BINARY,$root.'/bin/prepare-vps-deployment.php','--config='.$config,'--app-root='.$root])[0];
}
function prepare41(string $root,string $deployment,array $extra=[]): array {
    return run41(array_merge([PHP_BINARY,$root.'/bin/prepare-vps-runtime.php','--deployment='.$deployment],$extra));
}
function applyCheck41(string $root,string $plan,array $extra=[]): array {
    return run41(array_merge([PHP_BINARY,$root.'/bin/apply-vps-runtime.php','--plan='.$plan,'--check'],$extra));
}

$tmp=sys_get_temp_dir().DIRECTORY_SEPARATOR.'rc045-phase41-'.bin2hex(random_bytes(5));
$base=$tmp.'/tenants';@mkdir($base,0750,true);
try {
    $a=$base.'/noorderhaven'; $b=$base.'/duinrand';
    check41(provision41($root,$base,'noorderhaven','https://noorderhaven.example')===0&&provision41($root,$base,'duinrand','https://duinrand.example')===0,'twee tenants worden via bestaande provisioner aangemaakt');
    check41(bootstrap41($root,$a.'/config.php','Noorderhaven-Runtime-Admin-2026!')===0&&bootstrap41($root,$b.'/config.php','Duinrand-Runtime-Admin-2026!')===0,'beide tenants krijgen veilige mastercredential');
    check41(deploy41($root,$a.'/config.php')===0&&deploy41($root,$b.'/config.php')===0,'fase 3.5 deploymentcontract bestaat voor beide tenants');

    $depA=$a.'/deployment.json'; $depB=$b.'/deployment.json';
    [$dryCode,$dryOut]=prepare41($root,$depA,['--dry-run']);
    $dry=json_decode($dryOut,true);
    check41($dryCode===0&&is_array($dry)&&($dry['phase']??'')==='4.1'&&!is_dir($a.'/runtime'),'dry-run levert valide plan zonder filesystemwrite');
    check41(($dry['os']['user']??'')===($dry['os']['group']??'')&&preg_match('/^vst[a-f0-9]{16}$/D',(string)($dry['os']['user']??''))===1,'tenant krijgt unieke beperkte system user met eigen primary group');
    check41(($dry['os']['shell']??'')==='/usr/sbin/nologin'&&($dry['os']['home']??'')==='/nonexistent'&&($dry['os']['supplementary_groups']??null)===[],'runtimeaccount heeft geen login, home of supplementary groups');
    check41(($dry['php_fpm']['clear_env']??false)===true&&($dry['php_fpm']['one_pool_per_tenant']??false)===true,'FPM-plan houdt clear_env en één pool per tenant hard vast');
    check41(($dry['php_fpm']['session_save_path']??'')===$a.'/private/sessions'&&($dry['php_fpm']['upload_tmp_dir']??'')===$a.'/private/tmp','sessies en uploadtmp zijn tenant-private');
    check41(($dry['filesystem']['tenant_root']['owner']??'')==='root'&&($dry['filesystem']['metadata_mode']??'')==='0640','tenantmetadata blijft root-owned en alleen groep-leesbaar');
    check41(($dry['filesystem']['private_root']['owner']??'')===($dry['os']['user']??'')&&($dry['filesystem']['private_root']['directory_mode']??'')==='0750','private root wordt uitsluitend aan tenant-runtimeidentity gegeven');
    check41(($dry['filesystem']['sessions']['directory_mode']??'')==='0700'&&($dry['filesystem']['sessions']['file_mode']??'')==='0600','sessieopslag krijgt strengere 0700/0600 rechten');
    check41(($dry['apply_contract']['shared_code_is_never_chowned']??false)===true&&($dry['filesystem']['shared_code']['must_not_be_writable_by_tenant_identity']??false)===true,'gedeelde code wordt alleen gecontroleerd en nooit tenant-owned gemaakt');

    [$prepA,$outA]=prepare41($root,$depA);
    [$prepB,$outB]=prepare41($root,$depB);
    $planA=$a.'/runtime/runtime-plan.json';$planB=$b.'/runtime/runtime-plan.json';
    $jA=is_file($planA)?json_decode((string)file_get_contents($planA),true):null;
    $jB=is_file($planB)?json_decode((string)file_get_contents($planB),true):null;
    check41($prepA===0&&$prepB===0&&is_array($jA)&&is_array($jB),'runtimebundles worden voor twee tenants geschreven');
    check41(($jA['os']['user']??'')!==($jB['os']['user']??'')&&($jA['php_fpm']['pool']??'')!==($jB['php_fpm']['pool']??''),'OS identities en PHP-FPM pools zijn per tenant uniek');
    check41(($jA['php_fpm']['socket']??'')!==($jB['php_fpm']['socket']??''),'iedere tenant heeft eigen Unix socket');
    $fpmA=(string)file_get_contents((string)$jA['bundle']['php_fpm_file']);
    check41(str_contains($fpmA,'clear_env = yes')&&str_contains($fpmA,'env[VERENIGING_REQUIRE_TENANT_CONFIG] = "1"'),'gegenereerde FPM-config activeert fail-closed tenantenvironment');
    check41(str_contains($fpmA,'php_admin_value[session.save_path] = "'.$a.'/private/sessions"')&&str_contains($fpmA,'php_admin_value[upload_tmp_dir] = "'.$a.'/private/tmp"'),'FPM-config bindt tijdelijke runtime-opslag aan eigen private root');
    check41(!str_contains(strtolower($fpmA),'password')&&!str_contains(strtolower($fpmA),'dsn')&&!str_contains($fpmA,'BEHEER_WACHTWOORD_HASH'),'FPM-config bevat geen authenticatie- of databasesecrets');
    $perm=fileperms($planA); check41($perm!==false&&(($perm&0777)===0640),'runtime-plan.json krijgt server-only mode 0640');

    [$checkCode,$checkOut]=applyCheck41($root,$planA);
    check41($checkCode===0&&str_contains($checkOut,'CHECK OK'),'root-vrije apply --check valideert bundle volledig');

    [$idemCode,$idemOut]=prepare41($root,$depA);
    check41($idemCode===0&&substr_count($idemOut,'ONGEWIJZIGD')===2,'identieke runtimegeneratie is deterministisch en idempotent');

    $fpmPad=(string)$jA['bundle']['php_fpm_file'];$fpmOrig=(string)file_get_contents($fpmPad);file_put_contents($fpmPad,$fpmOrig."; tamper\n");
    [$tamperCode,$tamperOut]=applyCheck41($root,$planA);
    check41($tamperCode!==0&&str_contains($tamperOut,'wijkt af'),'apply --check weigert handmatig gewijzigde FPM-config');
    check41(prepare41($root,$depA,['--force'])[0]===0,'--force kan bundle gecontroleerd uit broncontract herstellen');

    $depOrig=(string)file_get_contents($depA);file_put_contents($depA,$depOrig."\n");
    [$staleCode,$staleOut]=applyCheck41($root,$planA);
    check41($staleCode!==0&&str_contains($staleOut,'gewijzigd sinds'),'runtimeplan wordt ongeldig zodra bron-deployment.json verandert');
    file_put_contents($depA,$depOrig);@chmod($depA,0640);
    check41(prepare41($root,$depA,['--force'])[0]===0,'runtimebundle kan na bronherstel opnieuw worden gegenereerd');

    $depData=json_decode($depOrig,true);$depData['php_fpm']['recommended_os_user']='root';file_put_contents($depA,json_encode($depData,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    [$identityCode,$identityOut]=prepare41($root,$depA,['--dry-run']);
    check41($identityCode!==0&&str_contains($identityOut,'gemanipuleerd'),'gemanipuleerde OS/FPM identity in deployment.json wordt fail-closed geweigerd');
    file_put_contents($depA,$depOrig);@chmod($depA,0640);

    $outside=$tmp.'/runtime-buiten';
    [$outsideCode,$outsideOut]=prepare41($root,$depA,['--output-dir='.$outside]);
    check41($outsideCode!==0&&!is_dir($outside)&&str_contains($outsideOut,'binnen de tenantroot'),'runtimebundle kan niet buiten eigen tenantroot worden geschreven');

    $canary=$tmp.'/canary';file_put_contents($canary,'NIET WIJZIGEN');$link=$a.'/runtime-link';
    if(function_exists('symlink')&&@symlink($canary,$link)){
        [$symCode,$symOut]=prepare41($root,$depA,['--output-dir='.$link]);
        check41($symCode!==0&&file_get_contents($canary)==='NIET WIJZIGEN','symlink als runtime outputmap wordt geweigerd zonder extern doel te wijzigen');
        @unlink($link);
    } else check41(true,'runtime output symlinktest overgeslagen op platform zonder symlinkondersteuning');

    [$secretCode,$secretOut]=run41([PHP_BINARY,$root.'/bin/prepare-vps-runtime.php','--deployment='.$depA,'--password=verboden']);
    check41($secretCode!==0&&str_contains($secretOut,'Secrets'),'runtimegenerator weigert secretachtige CLI-argumenten');

    [$badPhpCode,$badPhpOut]=prepare41($root,$depA,['--dry-run','--php-version=8.3;rm']);
    check41($badPhpCode!==0&&str_contains($badPhpOut,'PHP-versie'),'PHP-versie kan niet als configuratie-injectie worden misbruikt');
    [$badWebCode,$badWebOut]=prepare41($root,$depA,['--dry-run','--web-user=www data']);
    check41($badWebCode!==0&&str_contains($badWebOut,'Linux-naam'),'webserver identity accepteert alleen veilige Linux-accountnamen');

    $applySrc=(string)file_get_contents($root.'/bin/apply-vps-runtime.php');
    $contractSrc=(string)file_get_contents($root.'/app/deployment/runtime-contract.php');
    check41(str_contains($applySrc,"posix_geteuid() !== 0")&&str_contains($applySrc,"PHP_OS_FAMILY !== 'Linux'"),'root-toepassing vereist expliciet Linux EUID 0');
    check41(str_contains($applySrc,"'groupadd', '--system'")&&str_contains($applySrc,"'useradd', '--system'")&&str_contains($contractSrc,"'shell' => '/usr/sbin/nologin'")&&str_contains($contractSrc,"'home' => '/nonexistent'"),'apply-tool en contract maken uitsluitend system account zonder login/home aan');
    check41(str_contains($applySrc,'Supplementary groups')&&str_contains($applySrc,"'id', '-G'"),'apply-tool weigert onverwachte supplementary groups');
    check41(str_contains($applySrc,'apply41SymlinksVerboden')&&str_contains($applySrc,'Shared code is world-writable'),'apply-tool controleert tenantboomsymlinks en schrijfbaarheid van gedeelde code');
    check41(!str_contains($applySrc,"apply41Run(['systemctl'")&&!str_contains($applySrc,"apply41Run(['service'"),'apply-tool roept geen automatische PHP-FPM reloadcommand aan');
} finally { rr41($tmp); }

echo "Phase 4.1 VPS runtime isolation: $ok OK, $fout fout(en)\n";
exit($fout===0?0:1);
