<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function c52(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}
function r52(array$a,?string$stdin=null):array{$d=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];$p=proc_open($a,$d,$x,null,null,['bypass_shell'=>true]);if(!is_resource($p))return[255,''];if($stdin!==null)fwrite($x[0],$stdin);fclose($x[0]);$o=stream_get_contents($x[1]);fclose($x[1]);$e=stream_get_contents($x[2]);fclose($x[2]);return[proc_close($p),trim((string)$o."\n".(string)$e)];}
function rm52(string$p):void{if(is_link($p)||is_file($p)){@unlink($p);return;}if(!is_dir($p))return;foreach(scandir($p)?:[]as$n){if($n==='.'||$n==='..')continue;rm52($p.'/'.$n);}@rmdir($p);}
$tmp=sys_get_temp_dir().'/rc045-phase52-'.bin2hex(random_bytes(5));$bundle=$tmp.'/bundle';$platform=$tmp.'/platform';$tenants=$tmp.'/tenants';@mkdir($tmp,0750,true);
try{
 $base=[PHP_BINARY,$root.'/bin/prepare-first-vps-bootstrap.php','--source='.$root,'--commit='.str_repeat('a',40),'--output='.$bundle,'--platform-root='.$platform,'--tenant-base='.$tenants,'--platform-host=beheer.platform.example','--platform-strategy=direct','--platform-ipv4=203.0.113.10','--tenant-key=voorbeeld','--tenant-name=Voorbeeldvereniging','--tenant-host=voorbeeld.platform.example','--tenant-strategy=direct','--tenant-ipv4=203.0.113.10','--operator-user=platformadmin','--php-version=8.5','--modules=website,ledenadministratie'];
 [$dc,$do]=r52(array_merge($base,['--dry-run']));$dry=json_decode($do,true);c52($dc===0&&is_array($dry)&&($dry['phase']??'')==='5.2','dry-run levert een geldig fase-5.2 plan');
 c52(!is_dir($bundle),'dry-run schrijft geen bootstrapbundle');
 c52(($dry['paths']['current']??'')===$platform.'/current'&&($dry['paths']['tenant_base']??'')===$tenants,'bootstrap bindt aan immutable current en aparte tenantbasis');
 c52(($dry['workflow']['release_bootstrap_first']??false)===true&&($dry['workflow']['control_plane_before_first_tenant']??false)===true,'release en control-plane staan contractueel vóór eerste tenant');
 c52(($dry['workflow']['tenant_database_before_fpm_activation']??false)===true,'databaseprovisioning staat contractueel vóór tenant-FPM activatie');
 c52(($dry['platform']['acme']['minimum_samples']??0)===3&&($dry['platform']['acme']['minimum_interval_seconds']??0)===2,'platform-DNS vereist drie samples met minimaal twee seconden');
 c52(($dry['platform']['acme']['readiness_max_age_seconds']??0)===900,'platform bootstrap-readiness is maximaal vijftien minuten');
 c52(($dry['security']['dns_provider_writes_forbidden']??false)===true&&($dry['security']['provider_credentials_forbidden']??false)===true,'DNS-providerwrites en providercredentials zijn verboden');
 c52(($dry['security']['passwords_stdin_only']??false)===true&&!isset($dry['password'])&&!isset($dry['secret']),'bootstrapplan is secretvrij en vereist STDIN voor wachtwoorden');
 c52(($dry['tenant']['private_driver']??'')==='pdo'&&($dry['tenant']['timezone']??'')==='Europe/Amsterdam','eerste tenant is PDO-productieprofiel in Europe/Amsterdam');
 c52(($dry['tenant']['modules']??[])===['website','ledenadministratie'],'moduleprofiel is expliciet en deterministisch');
 c52(($dry['apache']['http_catchall_filename']??'')==='000-000-verenigingsplatform-http-catchall.conf'&&($dry['apache']['https_catchall_filename']??'')==='000-000-verenigingsplatform-https-catchall.conf','5.2 gebruikt exact de neutrale 4.4 catchallnamen');
 c52(($dry['apache']['default_cert']??'')==='/etc/verenigingsplatform/tls/default-reject.crt'&&($dry['apache']['default_key']??'')==='/etc/verenigingsplatform/tls/default-reject.key','unknown SNI gebruikt vaste neutrale rejectcertificaatpaden');
 c52(($dry['workflow']['final_platform_smoke_expected_http']??0)===401&&($dry['workflow']['final_tenant_health_expected_http']??0)===204,'eindbewijs vereist platform 401 en tenant health 204');

 [$bc,$bo]=r52($base);c52($bc===0,'fase-5.2 bundle wordt root-vrij geschreven');
 $plan=json_decode((string)file_get_contents($bundle.'/first-vps-bootstrap-plan.json'),true);c52(is_array($plan)&&is_file($bundle.'/release-plan.json'),'bootstrapplan en gebonden 4.7 releaseplan bestaan');
 c52(is_file($bundle.'/000-000-verenigingsplatform-http-catchall.conf')&&is_file($bundle.'/000-000-verenigingsplatform-https-catchall.conf'),'neutrale HTTP/HTTPS catchall-artifacts bestaan');
 c52(is_file($bundle.'/050-verenigingsplatform-control-plane-bootstrap-http.conf')&&is_file($bundle.'/50-verenigingsplatform-apache-reload'),'tijdelijke HTTP-01 vhost en renewal-hook bestaan');
 [$cc,$co]=r52([PHP_BINARY,$root.'/bin/apply-first-vps-bootstrap.php','--plan='.$bundle.'/first-vps-bootstrap-plan.json','--check']);c52($cc===0&&str_contains($co,'CHECK OK phase=5.2'),'root-vrije apply --check valideert de volledige bundle opnieuw');

 $http=(string)file_get_contents($bundle.'/050-verenigingsplatform-control-plane-bootstrap-http.conf');c52(str_contains($http,'/.well-known/acme-challenge')&&str_contains($http,'Require all granted'),'tijdelijke platformvhost serveert alleen de HTTP-01 challenge-directory');
 c52(str_contains($http,'RewriteRule ^ - [R=404,L]')&&!str_contains($http,'proxy:unix:'),'tijdelijke platformvhost geeft buiten ACME geen app/FPM-content');
 $httpsCatch=(string)file_get_contents($bundle.'/000-000-verenigingsplatform-https-catchall.conf');c52(str_contains($httpsCatch,'invalid.verenigingsplatform.invalid')&&str_contains($httpsCatch,'SSLStrictSNIVHostCheck On'),'HTTPS default catchall is neutraal en SNI-strict');
 $hook=(string)file_get_contents($bundle.'/50-verenigingsplatform-apache-reload');c52(str_contains($hook,'apache2ctl configtest')&&str_contains($hook,'systemctl reload apache2'),'Certbot deploy-hook doet configtest vóór reload');

 require_once $root.'/app/deployment/first-vps-bootstrap-contract.php';
 require_once $root.'/app/deployment/security-hardening.php';
 $prof=$plan['platform']['dns'];$good=['a'=>['203.0.113.10'],'aaaa'=>[],'cname'=>[]];$bad=['a'=>['203.0.113.10','203.0.113.11'],'aaaa'=>[],'cname'=>[]];
 c52((bootstrap52DnsBeoordeel($prof,'beheer.platform.example',$good,null)['ready']??false)===true,'exact direct platform-DNS-profiel is ready');
 c52((bootstrap52DnsBeoordeel($prof,'beheer.platform.example',$bad,null)['ready']??true)===false,'extra stale platform-IP maakt DNS fail-closed');
 $cnameProf=bootstrap52Dns('cname','203.0.113.10','','vps.platform.example','beheer2.platform.example');$owner=['a'=>[],'aaaa'=>[],'cname'=>['vps.platform.example']];$terminal=['a'=>['203.0.113.10'],'aaaa'=>[],'cname'=>[]];
 c52((bootstrap52DnsBeoordeel($cnameProf,'beheer2.platform.example',$owner,$terminal)['ready']??false)===true,'platform-DNS ondersteunt exact één CNAME-hop naar verwachte adressen');
 $rc=str_repeat('c',40);$rm=str_repeat('d',64);$rp='/srv/verenigingsplatform/releases/'.$rc;$marker=['schema'=>1,'phase'=>'4.7-release','immutable'=>true,'commit'=>$rc,'manifest_sha256'=>$rm];$state=['schema'=>1,'phase'=>'4.7-state','active'=>['commit'=>$rc,'path'=>$rp,'manifest_sha256'=>$rm],'previous'=>null,'transition'=>null];
 try{security521ReleaseBinding($rc,$rp,$rm,$rp,$marker,$state);c52(true,'exacte fase-5.2 releasebinding wordt geaccepteerd');}catch(Throwable$e){c52(false,'exacte fase-5.2 releasebinding wordt geaccepteerd');}
 try{security521ReleaseBinding($rc,$rp,$rm,'/srv/verenigingsplatform/releases/'.str_repeat('e',40),$marker,$state);c52(false,'onverwachte current-wissel wordt fail-closed geweigerd');}catch(Throwable$e){c52(str_contains($e->getMessage(),'current'),'onverwachte current-wissel wordt fail-closed geweigerd');}
 $stateAndere=$state;$stateAndere['active']['commit']=str_repeat('e',40);try{security521ReleaseBinding($rc,$rp,$rm,$rp,$marker,$stateAndere);c52(false,'andere actieve releasecommit in state wordt geweigerd');}catch(Throwable$e){c52(str_contains($e->getMessage(),'Actieve'),'andere actieve releasecommit in state wordt geweigerd');}
 $stateTransition=$state;$stateTransition['transition']=['mode'=>'deploy','from'=>$state['active'],'to'=>$state['active']];try{security521ReleaseBinding($rc,$rp,$rm,$rp,$marker,$stateTransition);c52(false,'lopende 4.7 transition blokkeert first-VPS vervolg');}catch(Throwable$e){c52(str_contains($e->getMessage(),'stabiel'),'lopende 4.7 transition blokkeert first-VPS vervolg');}
 try{security521GitSourceBinding($rc,$root,$root,$rc,'');c52(true,'Git source-binding accepteert exact root, HEAD en schone working tree');}catch(Throwable$e){c52(false,'Git source-binding accepteert exact root, HEAD en schone working tree');}
 try{security521GitSourceBinding($rc,$root,$root,str_repeat('e',40),'');c52(false,'Git source-binding weigert een andere HEAD dan het plan');}catch(Throwable$e){c52(str_contains($e->getMessage(),'HEAD'),'Git source-binding weigert een andere HEAD dan het plan');}
 try{security521GitSourceBinding($rc,$root,$root,$rc,' M auth.php');c52(false,'Git source-binding weigert een dirty working tree');}catch(Throwable$e){c52(str_contains($e->getMessage(),'wijzigingen'),'Git source-binding weigert een dirty working tree');}

 $apply=(string)file_get_contents($root.'/bin/apply-first-vps-bootstrap.php');
 c52(str_contains($apply,'plan_sha256')&&str_contains($apply,"\$p['paths']['state_file']")&&str_contains($apply,"\$p['paths']['lock_file']")&&str_contains($apply,'flock($lh,LOCK_EX|LOCK_NB)'),'resume-state is plan-gebonden en globaal gelockt');
 c52(str_contains($apply,"'--bootstrap'")&&str_contains($apply,'apply-vps-release.php'),'first-VPS flow hergebruikt expliciet fase-4.7 release-bootstrap');
 c52(strpos($apply,'apply-vps-release.php')<strpos($apply,"'provision-tenant.php'"),'immutable release-bootstrap staat in bron vóór tenantprovisioning');
 c52(str_contains($apply,"'--register-unsafely-without-email'")&&!str_contains($apply,'--email='),'eerste ACME-account serializeert geen e-mailadres');
 c52(str_contains($apply,"'--webroot'")&&str_contains($apply,"'--key-type','ecdsa'")&&str_contains($apply,"'--elliptic-curve','secp256r1'"),'platformcertificaat gebruikt Certbot webroot en ECDSA P-256');
 c52(str_contains($apply,'b52LiveDns')&&str_contains($apply,"b52Cert(\$p,\$bins['certbot'])"),'platform-DNS wordt vóór certificaatuitgifte live bewezen');
 c52(str_contains($apply,'bootstrap-control-plane-operator.php')&&str_contains($apply,"'--password-stdin'"),'platformoperator loopt via bestaande bcrypt STDIN-bootstrap');
 c52(str_contains($apply,'bootstrap-tenant-admin.php')&&str_contains($apply,"'--password-stdin'"),'eerste tenantbeheerder loopt via bestaande STDIN-bootstrap');
 $flagPos=strpos($apply,"if(!isset(\$o['secrets-stdin']))");$readPos=strpos($apply,'fgets(STDIN)');c52($flagPos!==false&&$readPos!==false&&$flagPos<$readPos&&!str_contains($apply,'stream_get_contents(STDIN)'),'secrets-stdin vlag wordt vóór één stagegebonden JSON-regelread gecontroleerd; bulk-read is verwijderd');
 $opSecret=strpos($apply,"b52SecretLees('operator_password')");$certStage=strpos($apply,"if(b52Voor(\$state['stage'],'platform_certificate_ready'))");$tenantSecret=strpos($apply,"b52SecretLees('tenant_admin_password')");$provision=strpos($apply,"if(b52Voor(\$state['stage'],'tenant_provisioned'))");c52($opSecret!==false&&$certStage!==false&&$certStage<$opSecret&&$tenantSecret!==false&&$provision!==false&&$provision<$tenantSecret,'wachtwoorden worden pas vlak in hun eigen operator- en tenant-adminstage gelezen');
 c52(str_contains($apply,"count(\$s)!==1||!array_key_exists(\$key,\$s)")&&!str_contains($apply,'array_keys($s)!=='),'secret-JSON valideert exact één vereiste sleutel zonder object-keyvolgorde-afhankelijkheid');
 c52(substr_count($apply,'finally{b52SecretWis(')===2&&str_contains($apply,'$raw=str_repeat("\\0",strlen($raw))'),'beide stagecredentials en hun ruwe JSON-regel worden ook bij fouten direct gewist');
 c52(!str_contains($apply,'$needSecrets')&&!str_contains($apply,'$secrets=b52Secrets'),'bootstrap houdt geen vroeg ingelezen secrets-array meer vast');
 c52(!str_contains($apply,'shell_exec(')&&!str_contains($apply,'`')&&!str_contains($apply,'rm -rf'),'5.2 gebruikt geen shell escape of rm-rf');
 c52(str_contains($apply,"require_once dirname(__DIR__).'/app/deployment/process-runner.php'")&&str_contains($apply,'process521Run($cmd,$stdin,null,null,3600)')&&!str_contains($apply,'proc_open('),'childprocessen gebruiken gedeelde deadlock-veilige argv-runner zonder shell');
 c52(str_contains($apply,'packages_are_not_auto_installed')===false&&!str_contains($apply,"'apt'")&&!str_contains($apply,'apt-get'),'root-flow installeert bewust geen packages');
 c52(strpos($apply,'b52TenantDb($p,$current)')<strpos($apply,'b52TenantFpm($p)'),'database apply gebeurt vóór tenant-FPM reload');
 c52(strpos($apply,'b52TenantTls($p,$current)')<strpos($apply,'b52TenantMonitoring($p,$current)'),'tenant-TLS staat vóór monitoring');
 c52(strpos($apply,'b52TenantMonitoring($p,$current)')<strpos($apply,'b52TenantLifecycle($p,$current)'),'monitoring staat vóór lifecycle-adoptie');
 c52(str_contains($apply,"'--adopt-active'")&&str_contains($apply,"'--refresh-only'"),'gezonde tenant wordt geadopteerd en control-plane snapshot daarna ververst');
 c52(str_contains($apply,"trim(\$o)!=='401'")&&str_contains($apply,"'--probe','--write-status'"),'eindcontrole bewijst Basic Auth 401 en tenant healthprobe');
 c52(str_contains($apply,"'operator_password'")&&str_contains($apply,"'tenant_admin_password'")&&!str_contains($apply,'VERENIGING_PASSWORD'),'secrets hebben alleen de twee bootstrapdoelen en gaan niet via environment');
 c52(str_contains($apply,'b52TrustedReleasePlan')&&str_contains($apply,'b52SourceTrust')&&str_contains($apply,"b52PlatformHttp(\$p,\$ctx['artifacts'])"),'root-apply gebruikt root-owned releaseplan en in-memory gevalideerde bootstrapartifacts');
 c52(str_contains($apply,"(int)\$s['uid']!==0")&&str_contains($apply,"((int)\$s['mode']&0022)!==0"),'first-VPS root-apply weigert niet-root-owned of group/world-writable releasebron');
 c52(str_contains($apply,'b52ExactBytes')&&!str_contains($apply,'function b52ExactInstall'),'serverartifacts worden uit gevalideerde bytes geplaatst en niet opnieuw uit mutable bundle gelezen');
 c52(str_contains($apply,'b52ReleaseEnsure')&&str_contains($apply,'b52ReleaseLock')&&substr_count($apply,'b52ReleaseExact($p)')>=10,'first-VPS reconcileert bestaande release, houdt 4.7 lock vast en herbewijst releasebinding rond mutaties');
 c52(str_contains($apply,"if(\$c||\$s){if(!\$c||!\$s)")&&str_contains($apply,'b52ReleaseExact($p);return;'),'crash na geslaagde release-bootstrap wordt gereconcileerd in plaats van opnieuw gebootstrapt');
 c52(str_contains($apply,"if(\$status==='unmanaged')")&&str_contains($apply,"if(\$status==='active')")&&str_contains($apply,"'--activate'"),'lifecycle-resume adopteert alleen unmanaged en valideert reeds active idempotent');
 c52(str_contains($apply,"'/usr/bin/git'")&&str_contains($apply,"'rev-parse','--verify','HEAD^{commit}'")&&str_contains($apply,"'status','--porcelain=v1','--untracked-files=all'")&&str_contains($apply,'security521GitSourceBinding'),'production bootstrap bindt bron expliciet aan Git top-level, HEAD en schone working tree');
 $preflightPos=strpos($apply,"if(\$mode!=='status')b52ProductionPreflight(\$p,\$bins)");$lockPos=strpos($apply,"\$lh=@fopen(\$p['paths']['lock_file'],'c')");c52($preflightPos!==false&&$lockPos!==false&&$preflightPos<$lockPos,'volledige production preflight gebeurt vóór het eerste bootstrap-lockbestand');
 c52(str_contains($apply,"'/usr/sbin/apache2ctl','configtest'")&&str_contains($apply,"\$bins['fpm'],'-t'")&&str_contains($apply,"\$bins['fail2ban'],'-t'")&&str_contains($apply,"'SELECT 1;'"),'production preflight bewijst Apache, PHP-FPM, Fail2ban en PostgreSQL vóór mutaties');
 c52(str_contains($apply,"'apache2','php'.\$v.'-fpm.service','postgresql'")&&str_contains($apply,"'is-active','--quiet'"),'kritieke productieservices moeten al actief zijn vóór first-VPS mutaties');

 $cpApply=(string)file_get_contents($root.'/bin/apply-vps-control-plane.php');
 c52(str_contains($cpApply,"\$art=\$ctx['artifacts']")&&str_contains($cpApply,'cpaExactBytes')&&!str_contains($cpApply,'function cpaExact(string$src'),'control-plane root-apply installeert uitsluitend in-memory gevalideerde artifactbytes');
 c52(str_contains($cpApply,'cpaVerifyMeta')&&str_contains($cpApply,'chown($dst,0)')&&str_contains($cpApply,"['mode']&0777"),'control-plane normaliseert en verifieert owner/group/mode van bestaande artifacts');
 $releaseApply=(string)file_get_contents($root.'/bin/apply-vps-release.php');
 c52(str_contains($releaseApply,'apply47ImmutableRechten')&&str_contains($releaseApply,'0555')&&str_contains($releaseApply,'0444')&&str_contains($releaseApply,"['uid']!==0")&&str_contains($releaseApply,"['gid']!==0"),'4.7 bewijst root:root en exact read-only metadata voor iedere immutable release');
 c52(str_contains($releaseApply,"'/usr/sbin/runuser'")&&str_contains($releaseApply,"'/usr/bin/systemctl'"),'kritieke 4.7 childprocessen gebruiken absolute systeembinaries');

 $cp=(string)file_get_contents($root.'/app/deployment/control-plane-contract.php');c52(str_contains($cp,"'acme_webroot'")&&str_contains($cp,'/.well-known/acme-challenge'),'definitieve 5.1 control-plane blijft ACME-renewal ondersteunen');
 c52(str_contains($cp,'RewriteCond %{REQUEST_URI}')&&str_contains($cp,'acme-challenge')&&str_contains($cp,'[R=308,L,NE]')&&str_contains($cp,"'    RewriteRule ^ https://' . \$host"),'definitieve HTTP-vhost serveert alleen challenge en redirect overige paden vast naar HTTPS');
 $docs=(string)file_get_contents($root.'/docs/VPS-FIRST-BOOTSTRAP.md');c52(str_contains($docs,'schrijft geen DNS-records')&&str_contains($docs,'Packages worden niet automatisch'),'operatorgrenzen staan expliciet in VPS-bootstrapdocumentatie');
 $workflow=(string)file_get_contents($root.'/.github/workflows/deploy-dev.yml');c52(str_contains($workflow,'phase52-first-vps-bootstrap.php'),'fase-5.2 test draait in CI');
}finally{rm52($tmp);}echo"Phase 5.2 first VPS bootstrap: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);