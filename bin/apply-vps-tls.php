<?php
// ============================================================
// Fase 4.4 — valideer en activeer TLS/HTTPS op de echte VPS
// ============================================================
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Alleen via CLI beschikbaar.'); }
require_once dirname(__DIR__) . '/app/deployment/tls-contract.php';

function apply44Stop(string $m, int $c = 1): void { fwrite(STDERR, "FOUT: {$m}\n"); exit($c); }
function apply44Help(): void
{
    echo "Gebruik:\n  php bin/apply-vps-tls.php --plan=/srv/verenigingen/club/tls/tls-plan.json --check\n  sudo php bin/apply-vps-tls.php --plan=... --apply [--force]\n\n";
    echo "--check vereist verse DNS-readiness maar geen root. --apply activeert HTTP-01, vraagt via Certbot webroot het certificaat aan, valideert certificaat+key, test de volledige Apache-config en activeert pas daarna HTTPS.\n";
}
function apply44Run(array $cmd): array
{
    $d=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']]; $p=@proc_open($cmd,$d,$pipes,null,null,['bypass_shell'=>true]);
    if(!is_resource($p)) return [255,'','proces kon niet worden gestart']; fclose($pipes[0]);
    $o=stream_get_contents($pipes[1]);fclose($pipes[1]);$e=stream_get_contents($pipes[2]);fclose($pipes[2]);
    return [proc_close($p),trim((string)$o),trim((string)$e)];
}
function apply44Bundle(string $planPad, bool $vers): array
{
    try {
        $ctx=tls44PlanLeesEnValideer($planPad,$vers);
        foreach($ctx['artifacts'] as $pad=>$verwacht){$real=runtime41BestaandPad((string)$pad,'TLS-artifact');$h=@file_get_contents($real);if(!is_string($h)||!hash_equals(hash('sha256',$verwacht),hash('sha256',$h)))throw new RuntimeException('TLS-artifact wijkt af: '.basename($real));}
        return $ctx;
    } catch(Throwable $e){apply44Stop($e->getMessage());}
}
function apply44ApachePreflight(array $plan): void
{
    [$c,$o,$e]=apply44Run([$plan['apache']['control_binary'],'-v']);$t=trim($o."\n".$e);
    if($c!==0||preg_match('~Apache/([0-9]+\.[0-9]+\.[0-9]+)~',$t,$m)!==1||version_compare($m[1],'2.4.49','<'))apply44Stop('Apache >= 2.4.49 kon niet worden bevestigd.');
    [$c,$o,$e]=apply44Run([$plan['apache']['control_binary'],'-M']);if($c!==0)apply44Stop('Apache modulelijst kon niet worden gelezen.');
    $mods=[];foreach(preg_split('/\r?\n/',$o."\n".$e)?:[] as $r)if(preg_match('/\b([a-z0-9_]+_module)\b/i',$r,$mm)===1)$mods[strtolower($mm[1])]=true;
    foreach($plan['apache']['required_modules'] as $m)if(!isset($mods[strtolower((string)$m)]))apply44Stop('Vereiste Apache-module ontbreekt: '.$m);
}
function apply44CertbotPreflight(array $plan): void
{
    [$c,$o,$e]=apply44Run(['certbot','--version']);$t=trim($o."\n".$e);
    if($c!==0||preg_match('/certbot\s+([0-9]+\.[0-9]+(?:\.[0-9]+)?)/i',$t,$m)!==1||version_compare($m[1],$plan['acme']['minimum_version'],'<'))apply44Stop('Certbot '.$plan['acme']['minimum_version'].'+ is vereist.');
    [$c]=$x=apply44Run(['certbot','show_account','--non-interactive']);if($c!==0)apply44Stop('Een vooraf geregistreerd Certbot/ACME-account is vereist; tenantautomation registreert geen account of e-mail.');
}
function apply44Dirs(array $plan): array
{
    try{$sa=runtime41BestaandPad($plan['apache']['sites_available_dir'],'sites-available',true);$se=runtime41BestaandPad($plan['apache']['sites_enabled_dir'],'sites-enabled',true);}catch(Throwable $e){apply44Stop($e->getMessage());}
    if($sa!=='/etc/apache2/sites-available'||$se!=='/etc/apache2/sites-enabled')apply44Stop('Alleen vaste Ubuntu/Debian Apache-paden zijn toegestaan.');
    foreach([$plan['apache']['default_tls_dir'],$plan['acme']['webroot'],$plan['acme']['webroot'].'/.well-known',$plan['acme']['webroot'].'/.well-known/acme-challenge'] as $dir){
        if(runtime41SymlinkInPad($dir)!==null)apply44Stop('VPS TLS/ACME-pad mag niet via symlink lopen: '.$dir);
        if(!is_dir($dir)&&!@mkdir($dir,0755,true)&&!is_dir($dir))apply44Stop('VPS TLS/ACME-map kon niet worden aangemaakt: '.$dir);
        @chown($dir,'root');@chgrp($dir,'root');@chmod($dir,0755);
    }
    return ['sa'=>$sa,'se'=>$se];
}
function apply44RootWrite(string $doel,string $inhoud,int $mode,bool $force,?string $toegestaneVoorganger=null): string
{
    if(is_link($doel))apply44Stop('Root-configdoel mag geen symlink zijn: '.$doel);$map=dirname($doel);if(!is_dir($map)||is_link($map))apply44Stop('Onveilige root-configmap.');
    if(is_file($doel)){$h=@file_get_contents($doel);if(!is_string($h))apply44Stop('Bestaand root-configbestand is onleesbaar.');if(hash_equals(hash('sha256',$h),hash('sha256',$inhoud)))return'ongewijzigd';$voorganger=$toegestaneVoorganger!==null&&hash_equals(hash('sha256',$h),hash('sha256',$toegestaneVoorganger));if(!$voorganger&&!$force)apply44Stop('Afwijkend root-configbestand bestaat; gebruik --force na controle: '.$doel);}elseif(file_exists($doel))apply44Stop('Root-configdoel is geen regulier bestand.');
    $tmp=$map.'/.'.basename($doel).'.tmp.'.bin2hex(random_bytes(8));if(@file_put_contents($tmp,$inhoud,LOCK_EX)===false)apply44Stop('Root-config kon niet tijdelijk worden geschreven.');
    if(!@chown($tmp,'root')||!@chgrp($tmp,'root')||!@chmod($tmp,$mode)){@unlink($tmp);apply44Stop('Root-config kreeg niet de vereiste rechten.');}
    clearstatcache(true,$doel);if(is_link($doel)){@unlink($tmp);apply44Stop('Root-configdoel werd tijdens write een symlink.');}if(!@rename($tmp,$doel)){@unlink($tmp);apply44Stop('Root-config kon niet atomisch worden geplaatst.');}return'geschreven';
}
function apply44Link(string $source,string $link): bool
{
    if(is_link($link)){if(realpath($link)!==realpath($source))apply44Stop('sites-enabled symlink wijst naar onverwacht doel: '.$link);return false;}
    if(file_exists($link))apply44Stop('sites-enabled doel bestaat maar is geen veilige symlink: '.$link);
    if(!@symlink($source,$link))apply44Stop('Apache-site kon niet atomisch worden enabled: '.$link);return true;
}
function apply44Configtest(array $plan): void
{
    [$c,$o,$e]=apply44Run([$plan['apache']['control_binary'],'configtest']);if($c!==0)apply44Stop('Apache configtest faalde: '.trim($o."\n".$e));
}
function apply44Reload(): void
{
    [$c,$o,$e]=apply44Run(['/usr/bin/systemctl','reload','apache2']);if($c!==0)apply44Stop('Apache reload faalde: '.trim($o."\n".$e));
}
function apply44EersteCatchall(array $plan,string $se): void
{
    $bestanden=[];foreach(scandir($se)?:[] as $f){if($f==='.'||$f==='..'||!str_ends_with($f,'.conf'))continue;$bestanden[]=$f;}sort($bestanden,SORT_STRING);
    if($bestanden===[]||$bestanden[0]!==$plan['apache']['http_catchall_filename'])apply44Stop('Platform HTTP catch-all is niet het eerste ingeladen sites-enabled .conf-bestand.');
    $httpsPos=array_search($plan['apache']['https_catchall_filename'],$bestanden,true);$tenantPos=array_search($plan['apache']['tenant_https_filename'],$bestanden,true);
    if($tenantPos!==false&&($httpsPos===false||$httpsPos>$tenantPos))apply44Stop('HTTPS catch-all staat niet vóór tenant HTTPS-vhost.');
}
function apply44DefaultCert(array $plan): void
{
    $crt=$plan['apache']['default_cert'];$key=$plan['apache']['default_key'];
    if(is_file($crt)&&is_file($key))return;
    if(file_exists($crt)||file_exists($key)||is_link($crt)||is_link($key))apply44Stop('Default reject-certificaat is incompleet/onveilig.');
    $tmpC=$crt.'.tmp.'.bin2hex(random_bytes(5));$tmpK=$key.'.tmp.'.bin2hex(random_bytes(5));
    [$c,$o,$e]=apply44Run(['openssl','req','-x509','-newkey','rsa:2048','-sha256','-days','3650','-nodes','-subj','/CN=invalid.verenigingsplatform.invalid','-addext','subjectAltName=DNS:invalid.verenigingsplatform.invalid','-keyout',$tmpK,'-out',$tmpC]);
    if($c!==0){@unlink($tmpC);@unlink($tmpK);apply44Stop('Neutraal default TLS-certificaat kon niet worden gemaakt: '.trim($o."\n".$e));}
    @chown($tmpC,'root');@chgrp($tmpC,'root');@chmod($tmpC,0644);@chown($tmpK,'root');@chgrp($tmpK,'root');@chmod($tmpK,0600);
    if(!@rename($tmpC,$crt)||!@rename($tmpK,$key))apply44Stop('Default reject-certificaat kon niet atomisch worden geplaatst.');
}
function apply44CertValideer(array $plan): void
{
    $fc=$plan['certificate']['fullchain'];$pk=$plan['certificate']['privkey'];
    foreach([$fc,$pk] as $p){if(!is_file($p))apply44Stop('Certbot certificaatbestand ontbreekt: '.$p);$r=realpath($p);$basis='/etc/letsencrypt/archive/'.$plan['acme']['cert_name'].'/';if($r===false||!str_starts_with($r,$basis))apply44Stop('Certbot live-symlink resolveert buiten verwachte archive-lineage.');}
    $certRaw=@file_get_contents($fc);$keyRaw=@file_get_contents($pk);if(!is_string($certRaw)||!is_string($keyRaw))apply44Stop('Certificaat/key kon niet veilig worden gelezen.');
    $cert=@openssl_x509_read($certRaw);$key=@openssl_pkey_get_private($keyRaw);if($cert===false||$key===false||!openssl_x509_check_private_key($cert,$key))apply44Stop('TLS private key hoort niet bij het uitgegeven certificaat.');
    $info=openssl_x509_parse($cert);if(!is_array($info))apply44Stop('Certificaat kon niet worden geparseerd.');$now=time();
    if((int)($info['validFrom_time_t']??PHP_INT_MAX)>$now+300||(int)($info['validTo_time_t']??0)<$now+(int)$plan['certificate']['minimum_remaining_seconds'])apply44Stop('Certificaat is nog niet geldig of heeft te weinig resterende geldigheid.');
    $san=(string)($info['extensions']['subjectAltName']??'');$dns=[];foreach(explode(',',$san) as $x){$x=trim($x);if(str_starts_with($x,'DNS:'))$dns[]=strtolower(substr($x,4));}sort($dns,SORT_STRING);
    if($dns!==[$plan['canonical_host']])apply44Stop('Certificaat-SAN is niet exact de canonical tenant-host.');
    $perm=@fileperms(realpath($pk));if($perm===false||($perm&0077)!==0)apply44Stop('Certbot private key is groep/wereld-toegankelijk.');
    if(!is_file($plan['certificate']['renewal_conf']))apply44Stop('Certbot renewal-config ontbreekt.');
}
function apply44RollbackHttp(array $plan,array $dirs,bool $tenantWasActief,bool $catchWasActief): void
{
    if(!$tenantWasActief)@unlink($dirs['se'].'/'.$plan['apache']['tenant_http_filename']);if(!$catchWasActief)@unlink($dirs['se'].'/'.$plan['apache']['http_catchall_filename']);
    [$c]=apply44Run([$plan['apache']['control_binary'],'configtest']);if($c===0)apply44Run(['/usr/bin/systemctl','reload','apache2']);
}

foreach($_SERVER['argv']??[] as $arg)if(preg_match('/^--(?:password|hash|secret|dsn|db-password|token|key|certificate|private-key|email)(?:=|$)/i',(string)$arg)===1)apply44Stop('Secrets/contactdata horen niet in fase-4.4 CLI-argumenten.');
$opt=getopt('',['plan:','check','apply','force','help']);if(isset($opt['help'])){apply44Help();exit(0);} $planPad=trim((string)($opt['plan']??''));if($planPad==='')apply44Stop('--plan is verplicht.');
$check=isset($opt['check']);$apply=isset($opt['apply']);if($check===$apply)apply44Stop('Kies exact één van --check of --apply.');
$ctx=apply44Bundle($planPad,true);$plan=$ctx['plan'];
if($check){echo 'CHECK OK  tenant='.$plan['tenant_key'].' host='.$plan['canonical_host'].' cert='.$plan['acme']['cert_name']."\n";exit(0);}
if(PHP_OS_FAMILY!=='Linux'||!function_exists('posix_geteuid')||posix_geteuid()!==0)apply44Stop('--apply vereist Linux root (EUID 0).');
apply44ApachePreflight($plan);apply44CertbotPreflight($plan);$dirs=apply44Dirs($plan);$force=isset($opt['force']);
// Fase 4.1/4.2 moeten werkelijk op de VPS toegepast zijn.
if(@filetype((string)$ctx['context']['web']['php_fpm']['socket'])!=='socket')apply44Stop('Tenant PHP-FPM socket is niet actief; voer fase 4.1 root-runtime eerst uit.');
$frag=$plan['apache']['routing_fragment_installed'];$src=(string)$ctx['context']['routing_fragment_source'];if(!is_file($frag)||!is_file($src)||!hash_equals(hash_file('sha256',$frag),hash_file('sha256',$src)))apply44Stop('Geïnstalleerd 4.2 HTTPS-routingfragment ontbreekt of wijkt af.');
apply44DefaultCert($plan);
$sa=$dirs['sa'];$se=$dirs['se'];
$doelHC=$sa.'/'.$plan['apache']['http_catchall_filename'];$doelHT=$sa.'/'.$plan['apache']['tenant_http_filename'];$doelSC=$sa.'/'.$plan['apache']['https_catchall_filename'];$doelST=$sa.'/'.$plan['apache']['tenant_https_filename'];
$oudeHttp=web42TenantHttpConfig($ctx['context']['web']);
apply44RootWrite($doelHC,tls44HttpCatchall($plan),0644,$force);apply44RootWrite($doelHT,tls44TenantHttp($plan),0644,$force,$oudeHttp);
$catchLink=$se.'/'.$plan['apache']['http_catchall_filename'];$tenantHttpLink=$se.'/'.$plan['apache']['tenant_http_filename'];$catchWas=is_link($catchLink);$tenantWas=is_link($tenantHttpLink);
apply44Link($doelHC,$catchLink);apply44Link($doelHT,$tenantHttpLink);apply44EersteCatchall($plan,$se);apply44Configtest($plan);apply44Reload();
// Certbot wijzigt Apache niet: alleen webroot-authenticatie en eigen /etc/letsencrypt lineage.
[$cc,$co,$ce]=apply44Run(['certbot','certonly','--webroot','--webroot-path',$plan['acme']['webroot'],'--cert-name',$plan['acme']['cert_name'],'-d',$plan['canonical_host'],'--preferred-challenges','http-01','--non-interactive','--agree-tos','--keep-until-expiring']);
if($cc!==0){apply44RollbackHttp($plan,$dirs,$tenantWas,$catchWas);apply44Stop('Certbot HTTP-01 uitgifte faalde; HTTP-activatie is zo mogelijk teruggedraaid: '.trim($co."\n".$ce),2);}
apply44CertValideer($plan);
apply44RootWrite($plan['apache']['renewal_hook'],tls44RenewalHook($plan),0755,$force);
apply44RootWrite($doelSC,tls44HttpsCatchall($plan),0644,$force);apply44RootWrite($doelST,tls44TenantHttps($plan),0644,$force);
apply44Link($doelSC,$se.'/'.$plan['apache']['https_catchall_filename']);apply44Link($doelST,$se.'/'.$plan['apache']['tenant_https_filename']);apply44EersteCatchall($plan,$se);
// Volledige actieve kandidaat inclusief echte certs, FPM-fragment en beide catch-alls.
apply44Configtest($plan);apply44Reload();
echo 'APPLY OK  tenant='.$plan['tenant_key'].' host='.$plan['canonical_host'].' HTTPS actief. Certbot renewal gebruikt configtest-before-reload hook.' . "\n";
