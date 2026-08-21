<?php
// ============================================================
// Fase 5.1 — platformbeheer/control-plane deploymentcontract
// ============================================================
// Secretvrije, deterministische artifacts voor een aparte platformbeheer-vhost.
// De weblaag draait als niet-root platformidentity. HTTP serveert uitsluitend
// ACME HTTP-01; de GUI is alleen via HTTPS bereikbaar.
// ============================================================
require_once __DIR__ . '/lifecycle-contract.php';

function control51Host(string $host): bool { return web42CanoniekeHost($host); }
function control51PhpVersie(string $php): bool { return runtime41PhpVersie($php); }
function control51Naam(string $naam): bool { return preg_match('/^[a-z0-9][a-z0-9.-]{0,62}$/D',$naam)===1; }
function control51OutputDir(string $pad): string
{
    if(!runtime41IsAbsoluutPad($pad)||runtime41HeeftRelatieveSegmenten($pad))throw new RuntimeException('Control-plane outputmap moet een absoluut veilig pad zijn.');
    $pad=runtime41NormPad($pad);$link=runtime41SymlinkInPad($pad);if($link!==null)throw new RuntimeException('Control-plane outputmap bevat een symlink: '.$link);return$pad;
}
function control51Plan(string$host,string$appRoot,string$tenantsRoot,string$phpVersion,string$certName,string$outputDir):array
{
    if(!control51Host($host))throw new RuntimeException('Ongeldige canonieke platformbeheer-host.');
    if(!control51PhpVersie($phpVersion))throw new RuntimeException('Ongeldige PHP-versie voor platformbeheer.');
    if(!control51Naam($certName))throw new RuntimeException('Ongeldige Certbot lineage voor platformbeheer.');
    foreach(['app_root'=>$appRoot,'tenants_root'=>$tenantsRoot]as$label=>$pad)if(!runtime41IsAbsoluutPad($pad)||runtime41HeeftRelatieveSegmenten($pad))throw new RuntimeException($label.' moet een absoluut veilig POSIX-pad zijn.');
    $appRoot=runtime41NormPad($appRoot);$tenantsRoot=runtime41NormPad($tenantsRoot);if($appRoot==='/'||$tenantsRoot==='/'||runtime41Binnen($tenantsRoot,$appRoot))throw new RuntimeException('Tenantroot moet buiten de gedeelde applicatierelease staan.');
    $webRoot=$appRoot.'/app/control-plane-web';if(!is_file($webRoot.'/index.php'))throw new RuntimeException('Control-plane webapp ontbreekt in de gedeelde release.');
    $outputDir=control51OutputDir($outputDir);$user='vst-control';$group='vst-control';$pool='vst-control';$socket='/run/php/vst-control.sock';$state='/var/lib/verenigingsplatform/control-plane';$etc='/etc/verenigingsplatform/control-plane';$site='050-verenigingsplatform-control-plane.conf';$service='verenigingsplatform-control-plane.service';$pathUnit='verenigingsplatform-control-plane.path';$acme='/var/lib/verenigingsplatform/acme/control-plane';
    return[
        'schema'=>1,'phase'=>'5.1','host'=>$host,'app_root'=>$appRoot,'tenants_root'=>$tenantsRoot,
        'identity'=>['user'=>$user,'group'=>$group,'login_shell'=>'/usr/sbin/nologin','home'=>'/nonexistent'],
        'php_fpm'=>['version'=>$phpVersion,'pool'=>$pool,'service'=>'php'.$phpVersion.'-fpm.service','test_binary'=>'/usr/sbin/php-fpm'.$phpVersion,'socket'=>$socket,'config_target'=>'/etc/php/'.$phpVersion.'/fpm/pool.d/50-vst-control.conf'],
        'apache'=>[
            'control_binary'=>'/usr/sbin/apache2ctl','site_filename'=>$site,'site_target'=>'/etc/apache2/sites-available/'.$site,'site_enabled'=>'/etc/apache2/sites-enabled/'.$site,
            'document_root'=>$webRoot,'auth_file'=>$etc.'/operators.htpasswd','cert_name'=>$certName,'fullchain'=>'/etc/letsencrypt/live/'.$certName.'/fullchain.pem','privkey'=>'/etc/letsencrypt/live/'.$certName.'/privkey.pem',
            'acme_webroot'=>$acme,'acme_challenge_dir'=>$acme.'/.well-known/acme-challenge',
            'required_modules'=>['auth_basic_module','authn_file_module','authz_core_module','headers_module','proxy_module','proxy_fcgi_module','rewrite_module','ssl_module'],
        ],
        'runtime'=>['config_file'=>$etc.'/runtime.json','state_root'=>$state,'pending_dir'=>$state.'/requests/pending','processing_dir'=>$state.'/requests/processing','results_dir'=>$state.'/results','sessions_dir'=>$state.'/sessions','snapshot_file'=>$state.'/snapshot.json','executor_lock'=>'/run/lock/verenigingsplatform-control-plane.lock','audit_file'=>'/var/log/verenigingsplatform/control-plane.jsonl'],
        'systemd'=>['service_unit'=>$service,'path_unit'=>$pathUnit,'service_target'=>'/etc/systemd/system/'.$service,'path_target'=>'/etc/systemd/system/'.$pathUnit,'executor'=>$appRoot.'/bin/control-plane-executor.php'],
        'bundle'=>['output_dir'=>$outputDir,'plan_file'=>$outputDir.'/control-plane-plan.json','runtime_file'=>$outputDir.'/control-plane-runtime.json','fpm_file'=>$outputDir.'/50-vst-control.conf','apache_file'=>$outputDir.'/'.$site,'service_file'=>$outputDir.'/'.$service,'path_file'=>$outputDir.'/'.$pathUnit],
        'security'=>['web_process_is_never_root'=>true,'web_process_cannot_exec_lifecycle'=>true,'queue_schema_is_allowlisted'=>true,'executor_revalidates_phase48_plan'=>true,'apache_basic_auth_over_tls_only'=>true,'operator_passwords_outside_git'=>true,'csrf_required_for_mutations'=>true,'tenant_secrets_never_exposed_in_snapshot'=>true,'ordinary_tenant_admin_has_no_control_plane_access'=>true,'http_only_serves_acme_else_https_redirect'=>true],
    ];
}
function control51Json(array$data):string{$j=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);if(!is_string($j))throw new RuntimeException('Control-plane data kon niet als JSON worden opgebouwd.');return$j."\n";}
function control51RuntimeConfig(array$p):array{return['schema'=>1,'phase'=>'5.1-runtime','host'=>$p['host'],'app_root'=>$p['app_root'],'tenants_root'=>$p['tenants_root'],'runtime_user'=>$p['identity']['user'],'pending_dir'=>$p['runtime']['pending_dir'],'processing_dir'=>$p['runtime']['processing_dir'],'results_dir'=>$p['runtime']['results_dir'],'sessions_dir'=>$p['runtime']['sessions_dir'],'snapshot_file'=>$p['runtime']['snapshot_file'],'executor_lock'=>$p['runtime']['executor_lock'],'audit_file'=>$p['runtime']['audit_file'],'lifecycle_apply'=>$p['app_root'].'/bin/apply-vps-lifecycle.php'];}
function control51FpmConfig(array$p):string
{
    $u=$p['identity']['user'];$g=$p['identity']['group'];return implode("\n",['; Fase 5.1 — aparte niet-root platformbeheerpool','['.$p['php_fpm']['pool'].']','user = '.$u,'group = '.$g,'listen = '.$p['php_fpm']['socket'],'listen.owner = www-data','listen.group = www-data','listen.mode = 0660','pm = ondemand','pm.max_children = 4','pm.process_idle_timeout = 15s','clear_env = yes','env[VP_CONTROL_PLANE_CONFIG] = '.$p['runtime']['config_file'],'php_admin_value[session.save_path] = '.$p['runtime']['sessions_dir'],'php_admin_flag[session.use_strict_mode] = 1','php_admin_flag[session.cookie_httponly] = 1','php_admin_flag[session.cookie_secure] = 1','php_admin_value[session.cookie_samesite] = Strict','php_admin_flag[display_errors] = 0','php_admin_flag[log_errors] = 1','']);
}
function control51ApacheConfig(array$p):string
{
    $host=$p['host'];$hostRe=preg_quote($host,'/');$doc=$p['apache']['document_root'];$socket=$p['php_fpm']['socket'];$backend='fcgi://'.$p['php_fpm']['pool'].'/';$acme=$p['apache']['acme_webroot'];$challenge=$p['apache']['acme_challenge_dir'];
    return implode("\n",[
        '# Fase 5.1 — aparte platformbeheer-vhost',
        '<VirtualHost *:80>','    ServerName '.$host,'    StrictHostCheck On','    ProxyRequests Off','    DocumentRoot "'.$acme.'"','    <Directory "'.$acme.'">','        Options None','        AllowOverride None','        Require all denied','    </Directory>','    <Directory "'.$challenge.'">','        Options None','        AllowOverride None','        Require all granted','    </Directory>','    RewriteEngine On','    RewriteCond %{REQUEST_URI} !^/\\.well-known/acme-challenge/[A-Za-z0-9_-]+$ [NC]','    RewriteRule ^ https://'.$host.'%{REQUEST_URI} [R=308,L,NE]','</VirtualHost>','',
        '<VirtualHost *:443>','    ServerName '.$host,'    StrictHostCheck On','    SSLEngine on','    SSLStrictSNIVHostCheck On','    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1','    SSLCompression Off','    SSLCertificateFile "'.$p['apache']['fullchain'].'"','    SSLCertificateKeyFile "'.$p['apache']['privkey'].'"','    Header always set Strict-Transport-Security "max-age=31536000"','    Header always set X-Content-Type-Options "nosniff"','    Header always set Referrer-Policy "no-referrer"','    Header always set X-Frame-Options "DENY"','    Header always set Content-Security-Policy "default-src \'self\'; style-src \'self\' \'unsafe-inline\'; form-action \'self\'; frame-ancestors \'none\'; base-uri \'none\'"','    RewriteEngine On','    RewriteCond %{SSL:SSL_TLS_SNI} !^'.$hostRe.'$ [NC,OR]','    RewriteCond %{HTTP_HOST} !^'.$hostRe.'(?::443)?$ [NC]','    RewriteRule ^ - [F,L]','    ProxyRequests Off','    DocumentRoot "'.$doc.'"','    DirectoryIndex index.php','    <Directory "/">','        AllowOverride None','        Require all denied','    </Directory>','    <Directory "'.$doc.'">','        Options -Indexes -ExecCGI +FollowSymLinks','        AllowOverride None','        AuthType Basic','        AuthName "Verenigingsplatform beheer"','        AuthBasicProvider file','        AuthUserFile "'.$p['apache']['auth_file'].'"','        Require valid-user','    </Directory>','    <FilesMatch "\\.php$">','        SetHandler "proxy:unix:'.$socket.'|'.$backend.'"','    </FilesMatch>','</VirtualHost>','']);
}
function control51ServiceUnit(array$p):string{return implode("\n",['[Unit]','Description=Verenigingsplatform control-plane executor','After=network.target apache2.service '.$p['php_fpm']['service'],'','[Service]','Type=oneshot','User=root','Group=root','UMask=0027','PrivateTmp=true','ExecStart=/usr/bin/php '.$p['systemd']['executor'].' --config='.$p['runtime']['config_file'],'']);}
function control51PathUnit(array$p):string{return implode("\n",['[Unit]','Description=Start control-plane executor bij nieuwe aanvragen','','[Path]','DirectoryNotEmpty='.$p['runtime']['pending_dir'],'Unit='.$p['systemd']['service_unit'],'','[Install]','WantedBy=multi-user.target','']);}
function control51Artifacts(array$p):array{return[$p['bundle']['runtime_file']=>control51Json(control51RuntimeConfig($p)),$p['bundle']['fpm_file']=>control51FpmConfig($p),$p['bundle']['apache_file']=>control51ApacheConfig($p),$p['bundle']['service_file']=>control51ServiceUnit($p),$p['bundle']['path_file']=>control51PathUnit($p)];}
function control51PlanLeesEnValideer(string$pad):array
{
    $pad=runtime41BestaandPad($pad,'control-plane-plan.json');$raw=@file_get_contents($pad);if(!is_string($raw))throw new RuntimeException('control-plane-plan.json kon niet worden gelezen.');try{$p=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(JsonException$e){throw new RuntimeException('control-plane-plan.json bevat ongeldige JSON.');}
    if(!is_array($p)||(int)($p['schema']??0)!==1||($p['phase']??'')!=='5.1')throw new RuntimeException('control-plane-plan.json heeft een onbekend schema/fase.');$e=control51Plan((string)$p['host'],(string)$p['app_root'],(string)$p['tenants_root'],(string)$p['php_fpm']['version'],(string)$p['apache']['cert_name'],(string)$p['bundle']['output_dir']);if(!hash_equals(control51Json($e),control51Json($p)))throw new RuntimeException('control-plane-plan.json wijkt af van het deterministische fase-5.1 contract.');
    foreach(control51Artifacts($p)as$f=>$inh){if(runtime41SymlinkInPad($f)!==null||!is_file($f))throw new RuntimeException('Control-plane artifact ontbreekt of bevat symlink: '.$f);$a=@file_get_contents($f);if(!is_string($a)||!hash_equals(hash('sha256',$inh),hash('sha256',$a)))throw new RuntimeException('Control-plane artifact wijkt af: '.$f);}return['path'=>$pad,'sha256'=>hash('sha256',$raw),'plan'=>$p,'artifacts'=>control51Artifacts($p)];
}
