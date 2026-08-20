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
    echo "--check vereist verse DNS-readiness maar geen root. --apply activeert eerst uitsluitend HTTP-01, vraagt via Certbot webroot het certificaat aan, valideert certificaat+key en activeert HTTPS pas na volledige Apache configtest.\n";
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
    [$c,$o,$e]=apply44Run(['certbot','show_account','--non-interactive']);if($c!==0)apply44Stop('Een vooraf geregistreerd Certbot/ACME-account is vereist; tenantautomation registreert geen account of e-mail.');
}
function apply44Dirs(array $plan): array
{
    try{$sa=runtime41BestaandPad($plan['apache']['sites_available_dir'],'sites-available',true);$se=runtime41BestaandPad($plan['apache']['sites_enabled_dir'],'sites-enabled',true);}catch(Throwable $e){apply44Stop($e->getMessage());}
    if($sa!=='/etc/apache2/sites-available'||$se!=='/etc/apache2/sites-enabled')apply44Stop('Alleen vaste Ubuntu/Debian Apache-paden zijn toegestaan.');
    $dirs=[$plan['apache']['default_tls_dir'],$plan['acme']['webroot'],$plan['acme']['webroot'].'/.well-known',$plan['acme']['webroot'].'/.well-known/acme-challenge',dirname($plan['apache']['renewal_hook'])];
    foreach($dirs as $dir){if(runtime41SymlinkInPad($dir)!==null)apply44Stop('VPS TLS/ACME-pad mag niet via symlink lopen: '.$dir);if(!is_dir($dir)&&!@mkdir($dir,0755,true)&&!is_dir($dir))apply44Stop('VPS TLS/ACME-map kon niet worden aangemaakt: '.$dir);@chown($dir,'root');@chgrp($dir,'root');@chmod($dir,0755);}
    return ['sa'=>$sa,'se'=>$se];
}
function apply44RootWrite(string $doel,string $inhoud,int $mode,bool $force,?string $toegestaneVoorganger=null,?string $actiefPad=null): string
{
    if(is_link($doel))apply44Stop('Root-configdoel mag geen symlink zijn: '.$doel);$map=dirname($doel);if(!is_dir($map)||is_link($map))apply44Stop('Onveilige root-configmap.');
    $actief=$actiefPad!==null&&(is_link($actiefPad)||file_exists($actiefPad));
    if(is_file($doel)){
        $h=@file_get_contents($doel);if(!is_string($h))apply44Stop('Bestaand root-configbestand is onleesbaar.');if(hash_equals(hash('sha256',$h),hash('sha256',$inhoud)))return'ongewijzigd';
        if($actief)apply44Stop('Afwijkend Apache-vhostbestand is al actief en wordt nooit in-place overschreven: '.$doel);
        $voorganger=$toegestaneVoorganger!==null&&hash_equals(hash('sha256',$h),hash('sha256',$toegestaneVoorganger));if(!$voorganger&&!$force)apply44Stop('Afwijkend root-configbestand bestaat; gebruik --force na controle: '.$doel);
    }elseif(file_exists($doel))apply44Stop('Root-configdoel bestaat maar is geen regulier bestand.');
    elseif($actief)apply44Stop('sites-enabled bevat een actief/dangling doel zonder veilig sites-available bronbestand.');
    $tmp=$map.'/.'.basename($doel).'.tmp.'.bin2hex(random_bytes(8));if(runtime41SymlinkInPad($tmp)!==null)apply44Stop('Onveilig tijdelijk root-configpad.');if(@file_put_contents($tmp,$inhoud,LOCK_EX)===false)apply44Stop('Root-config kon niet tijdelijk worden geschreven.');
    if(!@chown($tmp,'root')||!@chgrp($tmp,'root')||!@chmod($tmp,$mode)){@unlink($tmp);apply44Stop('Root-config kreeg niet de vereiste rechten.');}clearstatcache(true,$doel);if(is_link($doel)){@unlink($tmp);apply44Stop('Root-configdoel werd tijdens write een symlink.');}if(!@rename($tmp,$doel)){@unlink($tmp);apply44Stop('Root-config kon niet atomisch worden geplaatst.');}return'geschreven';
}
function apply44LinkVoorbereid(string $source,string $link): bool
{
    if(is_link($link)){if(realpath($link)!==realpath($source))throw new RuntimeException('sites-enabled symlink wijst naar onverwacht doel: '.$link);return false;}
    if(file_exists($link))throw new RuntimeException('sites-enabled doel bestaat maar is geen veilige symlink: '.$link);
    return true;
}
function apply44LinksPlaats(array $paren): array
{
    $nieuw=[];
    foreach($paren as [$source,$link])$nieuw[$link]=apply44LinkVoorbereid($source,$link);
    try { foreach($paren as [$source,$link])if($nieuw[$link]&&!@symlink($source,$link))throw new RuntimeException('Apache-site kon niet worden enabled: '.$link); }
    catch(Throwable $e){foreach($nieuw as $link=>$isNieuw)if($isNieuw&&is_link($link))@unlink($link);throw $e;}
    return $nieuw;
}
function apply44Configtest(array $plan): array
{
    [$c,$o,$e]=apply44Run([$plan['apache']['control_binary'],'configtest']);return[$c===0,trim($o."\n".$e)];
}
function apply44Reload(): array
{
    [$c,$o,$e]=apply44Run(['/usr/bin/systemctl','reload','apache2']);return[$c===0,trim($o."\n".$e)];
}
function apply44HerstelLinks(array $links): void { foreach($links as $link=>$nieuw)if($nieuw&&is_link($link))@unlink($link); }
function apply44CatchallFout(array $plan,string $se): ?string
{
    $f=[];foreach(scandir($se)?:[] as $x){if($x==='.'||$x==='..'||!str_ends_with($x,'.conf'))continue;$f[]=$x;}sort($f,SORT_STRING);
    if($f===[]||$f[0]!==$plan['apache']['http_catchall_filename'])return'Platform HTTP catch-all is niet het eerste sites-enabled .conf-bestand.';
    $hp=array_search($plan['apache']['https_catchall_filename'],$f,true);$tp=array_search($plan['apache']['tenant_https_filename'],$f,true);if($tp!==false&&($hp===false||$hp>$tp))return'HTTPS catch-all staat niet vóór tenant HTTPS-vhost.';
    return null;
}
function apply44Candidate(array $plan,string $se,array $nieuweLinks,string $fase): void
{
    $volgorde=apply44CatchallFout($plan,$se);
    if($volgorde!==null){apply44HerstelLinks($nieuweLinks);throw new RuntimeException($fase.' geweigerd: '.$volgorde);}
    [$ok,$melding]=apply44Configtest($plan);
    if(!$ok){apply44HerstelLinks($nieuweLinks);[$herstelOk]=apply44Configtest($plan);if(!$herstelOk)throw new RuntimeException($fase.' configtest faalde én rollbackconfig is niet geldig; handmatige interventie vereist: '.$melding);throw new RuntimeException($fase.' configtest faalde; nieuw geplaatste site-links zijn teruggedraaid: '.$melding);}
    [$reloadOk,$reloadMelding]=apply44Reload();
    if(!$reloadOk){apply44HerstelLinks($nieuweLinks);[$herstelOk]=apply44Configtest($plan);if($herstelOk)apply44Reload();throw new RuntimeException($fase.' Apache reload faalde; nieuw geplaatste site-links zijn zo mogelijk teruggedraaid: '.$reloadMelding);}
}
function apply44DefaultCertValideer(array $plan): bool
{
    $crt=$plan['apache']['default_cert'];$key=$plan['apache']['default_key'];if(!is_file($crt)||!is_file($key)||is_link($crt)||is_link($key))return false;
    $cr=@file_get_contents($crt);$kr=@file_get_contents($key);if(!is_string($cr)||!is_string($kr))return false;$c=@openssl_x509_read($cr);$k=@openssl_pkey_get_private($kr);if($c===false||$k===false||!openssl_x509_check_private_key($c,$k))return false;
    $i=openssl_x509_parse($c);if(!is_array($i))return false;$san=(string)($i['extensions']['subjectAltName']??'');$now=time();$perm=@fileperms($key);
    return str_contains($san,'DNS:invalid.verenigingsplatform.invalid')&&(int)($i['validFrom_time_t']??PHP_INT_MAX)<=$now+300&&(int)($i['validTo_time_t']??0)>$now+86400&&$perm!==false&&(($perm&0077)===0);
}
function apply44DefaultCert(array $plan): void
{
    $crt=$plan['apache']['default_cert'];$key=$plan['apache']['default_key'];if(apply44DefaultCertValideer($plan))return;if(file_exists($crt)||file_exists($key)||is_link($crt)||is_link($key))apply44Stop('Bestaand default reject-certificaat/key is ongeldig; verwijder het na handmatige inspectie.');
    $tmpC=$crt.'.tmp.'.bin2hex(random_bytes(5));$tmpK=$key.'.tmp.'.bin2hex(random_bytes(5));[$c,$o,$e]=apply44Run(['openssl','req','-x509','-newkey','rsa:2048','-sha256','-days','3650','-nodes','-subj','/CN=invalid.verenigingsplatform.invalid','-addext','subjectAltName=DNS:invalid.verenigingsplatform.invalid','-keyout',$tmpK,'-out',$tmpC]);
    if($c!==0){@unlink($tmpC);@unlink($tmpK);apply44Stop('Neutraal default TLS-certificaat kon niet worden gemaakt: '.trim($o."\n".$e));}@chown($tmpC,'root');@chgrp($tmpC,'root');@chmod($tmpC,0644);@chown($tmpK,'root');@chgrp($tmpK,'root');@chmod($tmpK,0600);if(!@rename($tmpC,$crt)||!@rename($tmpK,$key)||!apply44DefaultCertValideer($plan))apply44Stop('Default reject-certificaat kon niet veilig worden geplaatst/gevalideerd.');
}
function apply44DnsNu(array $ctx): void
{
    $dns=$ctx['context']['dns']['plan'];$owner=dns43Resolve((string)$dns['canonical_host']);$terminal=null;if(($dns['strategy']??'')==='cname')$terminal=dns43Resolve((string)$dns['expected']['terminal']['name']);$r=dns43Beoordeel($dns,$owner,$terminal);if(($r['ready']??false)!==true)throw new RuntimeException('Live DNS wijkt vlak vóór ACME opnieuw af van fase 4.3.');
}
function apply44CertValideer(array $plan): void
{
    $fc=$plan['certificate']['fullchain'];$pk=$plan['certificate']['privkey'];foreach([$fc,$pk] as $p){if(!is_file($p))throw new RuntimeException('Certbot certificaatbestand ontbreekt: '.$p);$r=realpath($p);$basis='/etc/letsencrypt/archive/'.$plan['acme']['cert_name'].'/';if($r===false||!str_starts_with($r,$basis))throw new RuntimeException('Certbot live-symlink resolveert buiten verwachte archive-lineage.');}
    $certRaw=@file_get_contents($fc);$keyRaw=@file_get_contents($pk);$cert=is_string($certRaw)?@openssl_x509_read($certRaw):false;$key=is_string($keyRaw)?@openssl_pkey_get_private($keyRaw):false;if($cert===false||$key===false||!openssl_x509_check_private_key($cert,$key))throw new RuntimeException('TLS private key hoort niet bij het uitgegeven certificaat.');
    $info=openssl_x509_parse($cert);if(!is_array($info))throw new RuntimeException('Certificaat kon niet worden geparseerd.');$now=time();if((int)($info['validFrom_time_t']??PHP_INT_MAX)>$now+300||(int)($info['validTo_time_t']??0)<$now+(int)$plan['certificate']['minimum_remaining_seconds'])throw new RuntimeException('Certificaat is nog niet geldig of heeft te weinig resterende geldigheid.');
    $dns=[];foreach(explode(',',(string)($info['extensions']['subjectAltName']??'')) as $x){$x=trim($x);if(str_starts_with($x,'DNS:'))$dns[]=strtolower(substr($x,4));}sort($dns,SORT_STRING);if($dns!==[$plan['canonical_host']])throw new RuntimeException('Certificaat-SAN is niet exact de canonical tenant-host.');
    $realKey=realpath($pk);$perm=$realKey!==false?@fileperms($realKey):false;if($perm===false||($perm&0077)!==0)throw new RuntimeException('Certbot private key is onleesbaar of groep/wereld-toegankelijk.');
    $renew=$plan['certificate']['renewal_conf'];if(!is_file($renew)||is_link($renew))throw new RuntimeException('Certbot renewal-config ontbreekt of is een symlink.');$rr=@file_get_contents($renew);if(!is_string($rr)||preg_match('/^authenticator\s*=\s*webroot\s*$/mi',$rr)!==1)throw new RuntimeException('Certbot renewal-config gebruikt niet aantoonbaar webroot-authenticatie.');
}
function apply44RollbackHttp(array $plan,array $dirs,bool $tenantWas,bool $catchWas): void
{
    if(!$tenantWas)@unlink($dirs['se'].'/'.$plan['apache']['tenant_http_filename']);if(!$catchWas)@unlink($dirs['se'].'/'.$plan['apache']['http_catchall_filename']);[$ok]=apply44Configtest($plan);if($ok)apply44Reload();
}

foreach($_SERVER['argv']??[] as $arg)if(preg_match('/^--(?:password|hash|secret|dsn|db-password|token|key|certificate|private-key|email)(?:=|$)/i',(string)$arg)===1)apply44Stop('Secrets/contactdata horen niet in fase-4.4 CLI-argumenten.');
$opt=getopt('',['plan:','check','apply','force','help']);if(isset($opt['help'])){apply44Help();exit(0);}$planPad=trim((string)($opt['plan']??''));if($planPad==='')apply44Stop('--plan is verplicht.');$check=isset($opt['check']);$apply=isset($opt['apply']);if($check===$apply)apply44Stop('Kies exact één van --check of --apply.');
$ctx=apply44Bundle($planPad,true);$plan=$ctx['plan'];if($check){echo 'CHECK OK  tenant='.$plan['tenant_key'].' host='.$plan['canonical_host'].' cert='.$plan['acme']['cert_name']."\n";exit(0);}if(PHP_OS_FAMILY!=='Linux'||!function_exists('posix_geteuid')||posix_geteuid()!==0)apply44Stop('--apply vereist Linux root (EUID 0).');
apply44ApachePreflight($plan);apply44CertbotPreflight($plan);$dirs=apply44Dirs($plan);$force=isset($opt['force']);
if(@filetype((string)$ctx['context']['web']['php_fpm']['socket'])!=='socket')apply44Stop('Tenant PHP-FPM socket is niet actief; voer fase 4.1 root-runtime eerst uit.');
$frag=$plan['apache']['routing_fragment_installed'];$src=(string)$ctx['context']['routing_fragment_source'];if(!is_file($frag)||!is_file($src)||!hash_equals(hash_file('sha256',$frag),hash_file('sha256',$src)))apply44Stop('Geïnstalleerd 4.2 HTTPS-routingfragment ontbreekt of wijkt af.');
apply44DefaultCert($plan);$sa=$dirs['sa'];$se=$dirs['se'];$doelHC=$sa.'/'.$plan['apache']['http_catchall_filename'];$doelHT=$sa.'/'.$plan['apache']['tenant_http_filename'];$doelSC=$sa.'/'.$plan['apache']['https_catchall_filename'];$doelST=$sa.'/'.$plan['apache']['tenant_https_filename'];$linkHC=$se.'/'.$plan['apache']['http_catchall_filename'];$linkHT=$se.'/'.$plan['apache']['tenant_http_filename'];$linkSC=$se.'/'.$plan['apache']['https_catchall_filename'];$linkST=$se.'/'.$plan['apache']['tenant_https_filename'];$oudeHttp=web42TenantHttpConfig($ctx['context']['web']);
// Stage alle inactieve bestanden vóór de eerste wijziging aan sites-enabled.
apply44RootWrite($doelHC,tls44HttpCatchall($plan),0644,$force,null,$linkHC);apply44RootWrite($doelHT,tls44TenantHttp($plan),0644,$force,$oudeHttp,$linkHT);apply44RootWrite($plan['apache']['renewal_hook'],tls44RenewalHook($plan),0755,$force);apply44RootWrite($doelSC,tls44HttpsCatchall($plan),0644,$force,null,$linkSC);apply44RootWrite($doelST,tls44TenantHttps($plan),0644,$force,null,$linkST);
$catchWas=is_link($linkHC);$tenantWas=is_link($linkHT);
try {$httpLinks=apply44LinksPlaats([[$doelHC,$linkHC],[$doelHT,$linkHT]]);apply44Candidate($plan,$se,$httpLinks,'HTTP-01 kandidaat');}
catch(Throwable $e){apply44Stop($e->getMessage());}
try {
    apply44DnsNu($ctx);
    [$cc,$co,$ce]=apply44Run(['certbot','certonly','--webroot','--webroot-path',$plan['acme']['webroot'],'--cert-name',$plan['acme']['cert_name'],'-d',$plan['canonical_host'],'--preferred-challenges','http','--non-interactive','--agree-tos','--keep-until-expiring']);
    if($cc!==0)throw new RuntimeException('Certbot HTTP-01 uitgifte faalde: '.trim($co."\n".$ce));
    apply44CertValideer($plan);
    $httpsLinks=apply44LinksPlaats([[$doelSC,$linkSC],[$doelST,$linkST]]);apply44Candidate($plan,$se,$httpsLinks,'HTTPS kandidaat');
} catch(Throwable $e) {
    apply44RollbackHttp($plan,$dirs,$tenantWas,$catchWas);
    apply44Stop($e->getMessage().'; nieuwe HTTP-activatie is zo mogelijk teruggedraaid.',2);
}
echo 'APPLY OK  tenant='.$plan['tenant_key'].' host='.$plan['canonical_host'].' HTTPS actief. Renewal hook doet configtest vóór reload.' . "\n";
