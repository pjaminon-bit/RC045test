<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Alleen via CLI beschikbaar.'); }
require_once dirname(__DIR__) . '/app/deployment/first-vps-bootstrap-contract.php';
require_once dirname(__DIR__) . '/app/deployment/php-runtime-requirements.php';

function prep52Stop(string $m, int $c=1): never { fwrite(STDERR,"FOUT: {$m}\n"); exit($c); }
function prep52Help(): void
{
    echo "Gebruik:\n";
    echo "  php bin/prepare-first-vps-bootstrap.php \\\n";
    echo "    --source=/staging/RC045test --commit=<40hex> --output=/root/first-vps \\\n";
    echo "    --platform-host=beheer.example.nl --platform-strategy=direct --platform-ipv4=203.0.113.10 \\\n";
    echo "    --tenant-key=voorbeeld --tenant-name='Voorbeeldvereniging' --tenant-host=voorbeeld.example.nl \\\n";
    echo "    --tenant-strategy=direct --tenant-ipv4=203.0.113.10 --operator-user=platformadmin [opties]\n\n";
    echo "Opties: --platform-root=/srv/verenigingsplatform --tenant-base=/srv/verenigingen --php-version=8.5\n";
    echo "        --platform-ipv6=... --platform-cname=... --tenant-ipv6=... --tenant-cname=...\n";
    echo "        --modules=website,ledenadministratie,... --cert-name=verenigingsplatform-beheer --force --dry-run\n";
    echo "DNS-providerrecords moeten operator-side worden gezet; deze tool schrijft nooit naar een DNS-provider en accepteert geen secrets.\n";
}
function prep52Write(string $pad,string $inhoud,bool $force,int $mode=0640): string
{
    $dir=dirname($pad);if(runtime41SymlinkInPad($dir)!==null)prep52Stop('Bootstrap outputmap bevat een symlink.');
    if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir))prep52Stop('Bootstrap outputmap kon niet worden aangemaakt.');@chmod($dir,0750);
    if(is_link($pad))prep52Stop('Bootstrapartifact mag geen symlinkdoel overschrijven.');
    if(is_file($pad)){$old=@file_get_contents($pad);if(is_string($old)&&hash_equals(hash('sha256',$old),hash('sha256',$inhoud))){@chmod($pad,$mode);return'ongewijzigd';}if(!$force)prep52Stop('Afwijkend bootstrapartifact bestaat al: '.basename($pad).'; gebruik --force na controle.');}
    elseif(file_exists($pad))prep52Stop('Bootstrapartifactdoel is geen regulier bestand: '.$pad);
    $tmp=$dir.'/.'.basename($pad).'.tmp.'.bin2hex(random_bytes(6));if(runtime41SymlinkInPad($tmp)!==null||@file_put_contents($tmp,$inhoud,LOCK_EX)===false)prep52Stop('Tijdelijke bootstrapwrite faalde.');@chmod($tmp,$mode);
    if(is_link($pad)||!@rename($tmp,$pad)){@unlink($tmp);prep52Stop('Bootstrapartifact kon niet atomisch worden geplaatst.');}@chmod($pad,$mode);return'geschreven';
}
foreach($_SERVER['argv']??[]as$a){if(preg_match('/^--(?:password|pass|secret|token|credential|api-key|key-file|email)(?:=|$)/i',(string)$a)===1)prep52Stop('Secrets/contactdata horen niet in fase-5.2 planargumenten.');}
$o=getopt('',['source:','commit:','output:','platform-root::','tenant-base::','php-version::','platform-host:','platform-strategy:','platform-ipv4::','platform-ipv6::','platform-cname::','tenant-key:','tenant-name:','tenant-host:','tenant-strategy:','tenant-ipv4::','tenant-ipv6::','tenant-cname::','operator-user:','modules::','cert-name::','force','dry-run','help']);
if(isset($o['help'])){prep52Help();exit(0);}foreach(['source','commit','output','platform-host','platform-strategy','tenant-key','tenant-name','tenant-host','tenant-strategy','operator-user']as$k)if(trim((string)($o[$k]??''))==='')prep52Stop('--'.$k.' is verplicht.');
try{
    $defs=require dirname(__DIR__).'/app/core/platform-definities.php';$alleModules=array_keys((array)($defs['features']??[]));
    $modules=array_key_exists('modules',$o)?(string)$o['modules']:implode(',',$alleModules);
    $in=[
        'source'=>(string)$o['source'],'commit'=>(string)$o['commit'],'output_dir'=>(string)$o['output'],
        'platform_root'=>(string)($o['platform-root']??'/srv/verenigingsplatform'),'tenant_base'=>(string)($o['tenant-base']??'/srv/verenigingen'),
        'php_version'=>(string)($o['php-version']??'8.5'),'platform_host'=>(string)$o['platform-host'],'operator_user'=>(string)$o['operator-user'],
        'cert_name'=>(string)($o['cert-name']??'verenigingsplatform-beheer'),'platform_dns_strategy'=>(string)$o['platform-strategy'],
        'platform_ipv4'=>(string)($o['platform-ipv4']??''),'platform_ipv6'=>(string)($o['platform-ipv6']??''),'platform_cname'=>(string)($o['platform-cname']??''),
        'tenant_key'=>(string)$o['tenant-key'],'tenant_name'=>(string)$o['tenant-name'],'tenant_host'=>(string)$o['tenant-host'],'modules'=>$modules,
        'tenant_dns_strategy'=>(string)$o['tenant-strategy'],'tenant_ipv4'=>(string)($o['tenant-ipv4']??''),'tenant_ipv6'=>(string)($o['tenant-ipv6']??''),'tenant_cname'=>(string)($o['tenant-cname']??''),
    ];
    $plan=bootstrap52Plan($in);
    // De eerste serverbootstrap en gewone releases gebruiken exact dezelfde
    // runtimecontractbron. Zo kan een nieuwe VPS niet opnieuw zonder DOM (of
    // een later vereiste extensie) door de eerste preflight heen komen.
    $plan['preflight']['required_php_modules']=platformPhpRequiredExtensions();
    $json=bootstrap52Json($plan);$art=bootstrap52Artifacts($plan);
}catch(Throwable$e){prep52Stop($e->getMessage());}
if(isset($o['dry-run'])){echo$json;exit(0);} $force=isset($o['force']);
$out=(string)$plan['paths']['output_dir'];if(!is_dir($out)&&!@mkdir($out,0750,true)&&!is_dir($out))prep52Stop('Fase-5.2 outputmap kon niet worden aangemaakt.');@chmod($out,0750);
foreach($art as$pad=>$inhoud)echo strtoupper(prep52Write((string)$pad,$inhoud,$force,basename((string)$pad)==='50-verenigingsplatform-apache-reload'?0750:0640)).'  '.$pad."\n";
echo strtoupper(prep52Write((string)$plan['bundle']['plan_file'],$json,$force,0640)).'  '.$plan['bundle']['plan_file']."\n";
echo 'Fase 5.2 bootstrapbundle gereed. Voer eerst --check uit; DNS-records blijven een expliciete provider-side operatorhandeling.'."\n";
