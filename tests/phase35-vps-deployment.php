<?php
$root = dirname(__DIR__);
$ok = 0; $fout = 0;
function check35(bool $cond, string $label): void { global $ok,$fout; if($cond){$ok++;echo"OK: $label\n";}else{$fout++;fwrite(STDERR,"FOUT: $label\n");} }
function rrmdir35(string $dir): void { if(!is_dir($dir) || is_link($dir)) return; foreach(scandir($dir)?:[] as $item){ if($item==='.'||$item==='..')continue; $p=$dir.DIRECTORY_SEPARATOR.$item; if(is_link($p)||is_file($p))@unlink($p); elseif(is_dir($p))rrmdir35($p); } @rmdir($dir); }
function run35(array $args, ?string $stdin = null): array {
    $desc=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
    $proc=proc_open($args,$desc,$pipes,null,null,['bypass_shell'=>true]);
    if(!is_resource($proc)) return [255,'proc_open mislukt'];
    if($stdin!==null) fwrite($pipes[0],$stdin); fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]); fclose($pipes[1]);
    $err=stream_get_contents($pipes[2]); fclose($pipes[2]);
    $code=proc_close($proc);
    return[$code,trim((string)$out."\n".(string)$err)];
}
function provision35(string $root,string $base,string $key,string $url): array {
    return run35([PHP_BINARY,$root.'/bin/provision-tenant.php','--key='.$key,'--name=Test '.ucfirst($key),'--url='.$url,'--root='.$base,'--modules=website,ledenadministratie']);
}
function bootstrap35(string $root,string $cfg,string $secret): array {
    return run35([PHP_BINARY,$root.'/bin/bootstrap-tenant-admin.php','--config='.$cfg,'--password-stdin'],$secret."\n");
}
function prepare35(string $root,string $cfg,string $appRoot,array $extra=[]): array {
    return run35(array_merge([PHP_BINARY,$root.'/bin/prepare-vps-deployment.php','--config='.$cfg,'--app-root='.$appRoot],$extra));
}

$tmp=sys_get_temp_dir().DIRECTORY_SEPARATOR.'rc045-phase35-'.bin2hex(random_bytes(5));
$tenants=$tmp.'/tenants';
@mkdir($tenants,0750,true);
try {
    [$codeA]=$a=provision35($root,$tenants,'noorderhaven','https://noorderhaven.example');
    [$codeB]=$b=provision35($root,$tenants,'duinrand','https://duinrand.example');
    $cfgA=$tenants.'/noorderhaven/config.php'; $cfgB=$tenants.'/duinrand/config.php';
    check35($codeA===0&&$codeB===0,'twee tenants worden met bestaande provisioner voorbereid');
    check35(bootstrap35($root,$cfgA,'Noorderhaven-VPS-Admin-2026!')[0]===0&&bootstrap35($root,$cfgB,'Duinrand-VPS-Admin-2026!')[0]===0,'fase 3.4 bootstrap is verplichte voorwaarde voor beide tenants');

    [$prepA,$outA]=prepare35($root,$cfgA,$root);
    [$prepB,$outB]=prepare35($root,$cfgB,$root);
    $depA=$tenants.'/noorderhaven/deployment.json'; $depB=$tenants.'/duinrand/deployment.json';
    $jsonA=is_file($depA)?json_decode((string)file_get_contents($depA),true):null;
    $jsonB=is_file($depB)?json_decode((string)file_get_contents($depB),true):null;
    check35($prepA===0&&$prepB===0&&is_array($jsonA)&&is_array($jsonB),'deploymentcontract wordt voor twee tenants succesvol opgebouwd');
    check35(($jsonA['schema']??0)===1&&($jsonB['schema']??0)===1,'deploymentdescriptor heeft expliciete schema-versie');
    check35(($jsonA['canonical_host']??'')==='noorderhaven.example'&&($jsonB['canonical_host']??'')==='duinrand.example','ieder deploymentcontract bindt aan eigen canonieke host');
    check35(($jsonA['shared_code']['app_root_real']??'')===realpath($root)&&($jsonB['shared_code']['app_root_real']??'')===realpath($root),'beide tenants delen exact dezelfde fysieke applicatiecode');
    check35(($jsonA['shared_code']['document_root']??'')===$root&&($jsonB['shared_code']['document_root']??'')===$root,'web documentroot wijst voor beide tenants naar gedeelde code en niet naar tenantdata');
    check35(($jsonA['tenant']['private_root']??'')!==($jsonB['tenant']['private_root']??''),'private roots blijven per tenant fysiek gescheiden');
    check35(($jsonA['php_fpm']['pool']??'')!==($jsonB['php_fpm']['pool']??'')&&($jsonA['php_fpm']['socket']??'')!==($jsonB['php_fpm']['socket']??''),'iedere tenant krijgt een eigen deterministische PHP-FPM pool en socket');
    check35(($jsonA['php_fpm']['recommended_os_user']??'')!==($jsonB['php_fpm']['recommended_os_user']??''),'aanbevolen OS-runtime identity is per tenant uniek');
    check35(($jsonA['php_fpm']['clear_env']??false)===true&&($jsonA['php_fpm']['one_pool_per_tenant']??false)===true,'PHP-FPM contract vereist clear_env en één pool per tenant');
    check35(($jsonA['runtime_env']['VERENIGING_REQUIRE_TENANT_CONFIG']??'')==='1'&&($jsonA['runtime_env']['VERENIGING_CONFIG_FILE']??'')===$cfgA,'runtimecontract injecteert fail-closed tenantconfig exact');
    check35(($jsonA['readiness']['admin_bootstrapped']??false)===true&&($jsonA['readiness']['tenant_storage_outside_app_root']??false)===true,'readiness bewijst adminbootstrap en opslag buiten app-root');
    $rawA=(string)file_get_contents($depA);
    check35(!str_contains(strtolower($rawA),'password')&&!str_contains(strtolower($rawA),'dsn')&&!str_contains($rawA,'BEHEER_WACHTWOORD_HASH'),'deploymentdescriptor bevat geen database- of authenticatiesecrets');
    $perm=fileperms($depA); check35($perm!==false&&(($perm&0777)===0640),'deployment.json krijgt server-only bestandsrechten 0640');

    [$idem,$idemOut]=prepare35($root,$cfgA,$root);
    check35($idem===0&&str_contains($idemOut,'ONGEWIJZIGD'),'identieke tweede run is deterministisch en idempotent');

    $dry=$tenants.'/noorderhaven/dry-run.json';
    [$dryCode,$dryOut]=prepare35($root,$cfgA,$root,['--dry-run','--output='.$dry]);
    $dryJson=json_decode($dryOut,true);
    check35($dryCode===0&&is_array($dryJson)&&!file_exists($dry),'dry-run valideert en toont descriptor zonder filesystemwrite');

    $linkApp=$tmp.'/current';
    if(function_exists('symlink')&&@symlink($root,$linkApp)) {
        [$linkCode,$linkOut]=prepare35($root,$cfgA,$linkApp,['--dry-run']);
        $linkJson=json_decode($linkOut,true);
        check35($linkCode===0&&($linkJson['shared_code']['app_root']??'')===$linkApp&&($linkJson['shared_code']['app_root_real']??'')===realpath($root),'release-symlink current is toegestaan maar fysiek releasepad wordt vastgelegd');
        @unlink($linkApp);
    } else { check35(true,'release-symlinktest overgeslagen op platform zonder symlinkondersteuning'); }

    $cfgBOrig=(string)file_get_contents($cfgB);
    file_put_contents($cfgB,(string)file_get_contents($cfgA));
    [$crossCode,$crossOut]=prepare35($root,$cfgB,$root,['--dry-run']);
    check35($crossCode!==0&&str_contains($crossOut,'dezelfde provisioned tenantroot'),'gekopieerde tenantconfig kan niet als deployment van een andere tenant worden gebruikt');
    file_put_contents($cfgB,$cfgBOrig); @chmod($cfgB,0640);

    $manifestA=$tenants.'/noorderhaven/tenant.json'; $manifestOrig=(string)file_get_contents($manifestA);
    $manifest=json_decode($manifestOrig,true); $manifest['site_url']='https://ander-domein.example'; file_put_contents($manifestA,json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    [$manifestCode,$manifestOut]=prepare35($root,$cfgA,$root,['--dry-run']);
    check35($manifestCode!==0&&str_contains($manifestOut,'site_url'),'gemanipuleerd manifest met ander domein wordt fail-closed geweigerd');
    file_put_contents($manifestA,$manifestOrig); @chmod($manifestA,0640);

    $runtimeA=$tenants.'/noorderhaven/runtime.env'; $runtimeOrig=(string)file_get_contents($runtimeA);
    file_put_contents($runtimeA,str_replace('/noorderhaven/config.php','/duinrand/config.php',$runtimeOrig));
    [$runtimeCode,$runtimeOut]=prepare35($root,$cfgA,$root,['--dry-run']);
    check35($runtimeCode!==0&&str_contains($runtimeOut,'VERENIGING_CONFIG_FILE'),'runtime.env kan niet naar andere tenantconfig worden omgebogen');
    file_put_contents($runtimeA,$runtimeOrig); @chmod($runtimeA,0640);

    [$codeC]=provision35($root,$tenants,'zonderadmin','https://zonderadmin.example');
    [$noAdminCode,$noAdminOut]=prepare35($root,$tenants.'/zonderadmin/config.php',$root,['--dry-run']);
    check35($codeC===0&&$noAdminCode!==0&&str_contains($noAdminOut,'Mastercredential'),'tenant zonder fase 3.4 beheerder is nog niet VPS-deployment-ready');

    [$codeHttp]=provision35($root,$tenants,'httpclub','http://httpclub.example');
    bootstrap35($root,$tenants.'/httpclub/config.php','HttpClub-Admin-2026!');
    [$httpCode,$httpOut]=prepare35($root,$tenants.'/httpclub/config.php',$root,['--dry-run']);
    check35($codeHttp===0&&$httpCode!==0&&str_contains($httpOut,'https'),'productie-VPS contract weigert niet-HTTPS site_url');

    [$codePath]=provision35($root,$tenants,'subpadclub','https://subpadclub.example/vereniging');
    bootstrap35($root,$tenants.'/subpadclub/config.php','SubpadClub-Admin-2026!');
    [$pathCode,$pathOut]=prepare35($root,$tenants.'/subpadclub/config.php',$root,['--dry-run']);
    check35($codePath===0&&$pathCode!==0&&str_contains($pathOut,'URL-subpad'),'VPS-contract weigert tenantdeployment onder URL-subpad');

    $buiten=$tmp.'/buiten-deployment.json';
    [$outCode,$outMsg]=prepare35($root,$cfgA,$root,['--output='.$buiten]);
    check35($outCode!==0&&!file_exists($buiten)&&str_contains($outMsg,'binnen de provisioned tenantroot'),'deploymentoutput kan niet buiten tenantroot worden geschreven');

    $canary=$tmp.'/symlink-canary'; file_put_contents($canary,'NIET WIJZIGEN');
    $linkOut=$tenants.'/noorderhaven/link-deployment.json';
    if(function_exists('symlink')&&@symlink($canary,$linkOut)) {
        [$symCode,$symOut]=prepare35($root,$cfgA,$root,['--output='.$linkOut,'--force']);
        check35($symCode!==0&&file_get_contents($canary)==='NIET WIJZIGEN','symlink als deploymentdoel wordt geweigerd zonder extern bestand te wijzigen');
        @unlink($linkOut);
    } else { check35(true,'output-symlinktest overgeslagen op platform zonder symlinkondersteuning'); }

    [$secretCode,$secretOut]=run35([PHP_BINARY,$root.'/bin/prepare-vps-deployment.php','--config='.$cfgA,'--app-root='.$root,'--password=mag-nooit']);
    check35($secretCode!==0&&str_contains($secretOut,'Secrets'),'deploymenttool weigert secretachtige CLI-argumenten expliciet');

    $ht=(string)file_get_contents($root.'/.htaccess');
    check35(!str_contains($ht,'https://rc045.nl%{REQUEST_URI}')&&!str_contains($ht,'RC045_HTTPS'),'gedeelde Apache-laag bevat geen vaste RC045 HTTPS-redirect of tenantnaam meer');
    check35(str_contains($ht,'VST_HTTPS')&&str_contains($ht,'https://%1%{REQUEST_URI}'),'Apache fallbackredirect behoudt veilige host en is tenant-neutraal');
    check35(str_contains($ht,'app|bin|tests|docs'),'server-only ontwikkel- en beheertooling is centraal uit HTTP-surface verwijderd');

    $src=(string)file_get_contents($root.'/bin/prepare-vps-deployment.php');
    check35(str_contains($src,'read_only_for_tenant_runtime')&&str_contains($src,'one_pool_per_tenant'),'deploymentcontract legt shared-code read-only en per-tenant runtime-isolatie vast');
    check35(!str_contains($src,"'password' =>")&&!str_contains($src,"'dsn' =>"),'deploymenttool serializeert bewust geen secretvelden');
} finally {
    rrmdir35($tmp);
}

echo "Phase 3.5 VPS deployment: $ok OK, $fout fout(en)\n";
exit($fout===0?0:1);
