<?php
// ============================================================
// Fase 4.6 — monitoring & logging contract
// ============================================================
// Pure helpers: binden fase 4.6 byte-exact aan TLS 4.4 + database 4.5.
// Geen root-acties, service-reloads of alertdelivery in deze laag.
// ============================================================
require_once __DIR__ . '/tls-contract.php';
require_once __DIR__ . '/database-contract.php';

function monitoring46Context(string $tlsPlanPad, string $databasePlanPad): array
{
    $tlsCtx=tls44PlanLeesEnValideer($tlsPlanPad,false);$dbCtx=database45PlanLeesEnValideer($databasePlanPad);
    $tls=$tlsCtx['plan'];$db=$dbCtx['plan'];$tenant=(string)($tls['tenant_key']??'');
    if(!hash_equals($tenant,(string)($db['tenant_key']??'')))throw new RuntimeException('TLS- en databaseplan horen niet bij dezelfde tenant.');
    $tlsRuntime=(string)($tlsCtx['context']['web']['source']['runtime_plan_file']??'');$dbRuntime=(string)($db['source']['runtime_plan_file']??'');
    if($tlsRuntime===''||!hash_equals(runtime41NormPad($tlsRuntime),runtime41NormPad($dbRuntime)))throw new RuntimeException('TLS en database zijn niet aan hetzelfde runtimeplan gebonden.');
    $runtimeCtx=runtime41PlanLeesEnValideer($dbRuntime);$runtime=$runtimeCtx['plan'];$host=(string)($tls['canonical_host']??'');
    if(!hash_equals($host,(string)($tlsCtx['context']['host']??'')))throw new RuntimeException('Canonical host in TLS-context wijkt af.');
    $tlsRaw=@file_get_contents($tlsCtx['path']);$dbRaw=@file_get_contents($dbCtx['path']);$runtimeRaw=@file_get_contents($runtimeCtx['path']);
    if(!is_string($tlsRaw)||!is_string($dbRaw)||!is_string($runtimeRaw))throw new RuntimeException('Fase-4.6 bronplan kon niet byte-exact worden gelezen.');
    return[
        'tenant_key'=>$tenant,'canonical_host'=>$host,'tenant_root'=>(string)$runtime['filesystem']['tenant_root']['path'],'private_root'=>(string)$runtime['filesystem']['private_root']['path'],
        'app_root'=>(string)$runtime['filesystem']['shared_code']['path'],'runtime_user'=>(string)$runtime['os']['user'],'runtime_group'=>(string)$runtime['os']['group'],'php_version'=>(string)$runtime['settings']['php_version'],
        'pool'=>(string)$runtime['php_fpm']['pool'],'socket'=>(string)$runtime['php_fpm']['socket'],'runtime_plan_path'=>$runtimeCtx['path'],'runtime_plan_sha256'=>hash('sha256',$runtimeRaw),
        'tls_plan_path'=>$tlsCtx['path'],'tls_plan_sha256'=>hash('sha256',$tlsRaw),'database_plan_path'=>$dbCtx['path'],'database_plan_sha256'=>hash('sha256',$dbRaw),
        'certificate_fullchain'=>(string)$tls['certificate']['fullchain'],'certificate_privkey'=>(string)$tls['certificate']['privkey'],'tenant_https_filename'=>(string)$tls['apache']['tenant_https_filename'],
        'database'=>(string)$db['isolation']['database'],'database_user'=>(string)$db['isolation']['app_role'],
    ];
}
function monitoring46OutputDir(string $tenantRoot):string{$pad=runtime41NormPad($tenantRoot.'/monitoring');if(!runtime41Binnen($pad,$tenantRoot)||$pad===runtime41NormPad($tenantRoot))throw new RuntimeException('Monitoringbundle valt niet veilig binnen de tenantroot.');$link=runtime41SymlinkInPad($pad);if($link!==null)throw new RuntimeException("Monitoringbundle mag geen symlink bevatten: {$link}");return$pad;}
function monitoring46Plan(array $c):array
{
    $tenant=$c['tenant_key'];$output=monitoring46OutputDir($c['tenant_root']);$php=$c['php_version'];if(!runtime41PhpVersie($php))throw new RuntimeException('Monitoring vereist een geldige runtime PHP-versie.');
    $service='verenigingsplatform-health-'.$tenant.'.service';$timer='verenigingsplatform-health-'.$tenant.'.timer';$privateMonitoring=$c['private_root'].'/monitoring';
    return[
      'schema'=>1,'phase'=>'4.6','tenant_key'=>$tenant,'canonical_host'=>$c['canonical_host'],
      'source'=>['runtime_plan_file'=>$c['runtime_plan_path'],'runtime_plan_sha256'=>$c['runtime_plan_sha256'],'tls_plan_file'=>$c['tls_plan_path'],'tls_plan_sha256'=>$c['tls_plan_sha256'],'database_plan_file'=>$c['database_plan_path'],'database_plan_sha256'=>$c['database_plan_sha256']],
      'health'=>['public_path'=>'/healthz.php','public_url'=>'https://'.$c['canonical_host'].'/healthz.php','success_http_status'=>204,'failure_http_status'=>503,'standalone_http_status'=>404,'local_resolve_address'=>'127.0.0.1','interval_seconds'=>60,'timeout_seconds'=>15,'certificate_warning_seconds'=>1209600,'disk_minimum_free_percent'=>10,'disk_minimum_free_bytes'=>536870912],
      'runtime'=>['user'=>$c['runtime_user'],'group'=>$c['runtime_group'],'php_version'=>$php,'fpm_service'=>'php'.$php.'-fpm.service','fpm_pool'=>$c['pool'],'fpm_socket'=>$c['socket'],'apache_service'=>'apache2.service','postgresql_service'=>'postgresql.service','tenant_https_enabled'=>'/etc/apache2/sites-enabled/'.$c['tenant_https_filename']],
      'database'=>['database'=>$c['database'],'user'=>$c['database_user'],'peer_only'=>true],
      'certificate'=>['fullchain'=>$c['certificate_fullchain'],'private_key'=>$c['certificate_privkey']],
      'logging'=>['root'=>'/var/log/verenigingsplatform','apache_access'=>'/var/log/verenigingsplatform/apache-access.log','apache_error'=>'/var/log/verenigingsplatform/apache-error.log','fpm_journal_unit'=>'php'.$php.'-fpm.service','app_dir'=>$privateMonitoring,'app_operations'=>$privateMonitoring.'/operations.jsonl','health_status'=>'/var/lib/verenigingsplatform/monitoring/'.$tenant.'-health.json','retention_days'=>14,'apache_access_excludes_ip'=>true,'apache_access_excludes_path'=>true,'apache_access_excludes_query'=>true,'apache_access_excludes_user_agent'=>true,'apache_access_excludes_referrer'=>true,'apache_access_excludes_auth_and_cookies'=>true],
      'alerts'=>['adapter'=>'/etc/verenigingsplatform/monitoring/alert-command','adapter_must_be_root_owned'=>true,'adapter_must_not_be_group_or_world_writable'=>true,'payload_via_stdin'=>true,'secret_in_plan_forbidden'=>true,'reminder_seconds'=>3600,'state_file'=>'/var/lib/verenigingsplatform/monitoring/'.$tenant.'-alert.json','alert_on_failure_transition'=>true,'alert_on_recovery_transition'=>true],
      'systemd'=>['service_filename'=>$service,'timer_filename'=>$timer,'unit_dir'=>'/etc/systemd/system'],
      'apache'=>['config_available'=>'/etc/apache2/conf-available/90-verenigingsplatform-monitoring.conf','config_enabled'=>'/etc/apache2/conf-enabled/90-verenigingsplatform-monitoring.conf','control_binary'=>'/usr/sbin/apache2ctl'],
      'logrotate'=>['global_file'=>'/etc/logrotate.d/verenigingsplatform-apache','tenant_file'=>'/etc/logrotate.d/verenigingsplatform-'.$tenant],
      'bundle'=>['output_dir'=>$output,'plan_file'=>$output.'/monitoring-plan.json','apache_config'=>$output.'/90-verenigingsplatform-monitoring.conf','systemd_service'=>$output.'/'.$service,'systemd_timer'=>$output.'/'.$timer,'logrotate_global'=>$output.'/verenigingsplatform-apache.logrotate','logrotate_tenant'=>$output.'/verenigingsplatform-'.$tenant.'.logrotate'],
      'security'=>['no_secrets_in_bundle'=>true,'health_endpoint_discloses_no_tenant_identity'=>true,'health_endpoint_discloses_no_failure_detail'=>true,'monitoring_paths_outside_document_root'=>true,'alert_adapter_outside_git'=>true,'raw_request_headers_forbidden_in_platform_access_log'=>true,'fpm_service_log_uses_system_journal'=>true],
    ];
}
function monitoring46Json(array $d):string{$j=json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);if(!is_string($j))throw new RuntimeException('Monitoringcontract kon niet als JSON worden opgebouwd.');return$j."\n";}
function monitoring46ApacheConfig(array $p):string{return implode("\n",['# Fase 4.6 — platformbrede, privacybewuste Apache logging.','# Geen client-IP, URI/querystring, referrer, user-agent, cookies of Authorization in de accesslog.','LogFormat "%{%Y-%m-%dT%H:%M:%S%z}t\\t%v\\t%m\\t%>s\\t%B\\t%D" vp_safe','CustomLog "'.$p['logging']['apache_access'].'" vp_safe','ErrorLog "'.$p['logging']['apache_error'].'"','ErrorLogFormat "[%{cu}t] [%-m:%l] [vhost %V] [pid %P] %E: %M"','LogLevel warn','']);}
function monitoring46SystemdService(array $p):string{$root=runtime41PlanLeesEnValideer($p['source']['runtime_plan_file'])['plan']['filesystem']['shared_code']['path'];return implode("\n",['[Unit]','Description=Verenigingsplatform healthcheck '.$p['tenant_key'],'After=network-online.target apache2.service '.$p['runtime']['fpm_service'].' postgresql.service','','[Service]','Type=oneshot','ExecStart=/usr/bin/php '.$root.'/bin/check-vps-health.php --monitoring-plan='.$p['bundle']['plan_file'].' --probe --write-status --alert','NoNewPrivileges=true','PrivateTmp=true','ProtectHome=true','ProtectSystem=strict','ReadWritePaths='.$p['logging']['app_dir'].' /var/lib/verenigingsplatform/monitoring','','']);}
function monitoring46SystemdTimer(array $p):string{return implode("\n",['[Unit]','Description=Verenigingsplatform health timer '.$p['tenant_key'],'','[Timer]','OnCalendar=minutely','AccuracySec=1s','RandomizedDelaySec=5s','Persistent=true','Unit='.$p['systemd']['service_filename'],'','[Install]','WantedBy=timers.target','']);}
function monitoring46LogrotateGlobal(array $p):string{return implode("\n",[$p['logging']['apache_access'].' '.$p['logging']['apache_error'].' {','    daily','    rotate '.(int)$p['logging']['retention_days'],'    compress','    delaycompress','    missingok','    notifempty','    copytruncate','    maxsize 50M','}','']);}
function monitoring46LogrotateTenant(array $p):string{return implode("\n",[$p['logging']['app_operations'].' {','    daily','    rotate '.(int)$p['logging']['retention_days'],'    compress','    delaycompress','    missingok','    notifempty','    copytruncate','    maxsize 25M','    su '.$p['runtime']['user'].' '.$p['runtime']['group'],'    create 0640 '.$p['runtime']['user'].' '.$p['runtime']['group'],'}','']);}
function monitoring46Artifacts(array $p):array{return[$p['bundle']['apache_config']=>monitoring46ApacheConfig($p),$p['bundle']['systemd_service']=>monitoring46SystemdService($p),$p['bundle']['systemd_timer']=>monitoring46SystemdTimer($p),$p['bundle']['logrotate_global']=>monitoring46LogrotateGlobal($p),$p['bundle']['logrotate_tenant']=>monitoring46LogrotateTenant($p)];}
function monitoring46PlanLeesEnValideer(string $pad):array
{
 $pad=runtime41BestaandPad($pad,'monitoring-plan.json');$raw=@file_get_contents($pad);if(!is_string($raw))throw new RuntimeException('monitoring-plan.json kon niet worden gelezen.');try{$p=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(JsonException$e){throw new RuntimeException('monitoring-plan.json bevat ongeldige JSON.');}
 if(!is_array($p)||(int)($p['schema']??0)!==1||($p['phase']??'')!=='4.6')throw new RuntimeException('monitoring-plan.json heeft een onbekend schema/fase.');$c=monitoring46Context((string)($p['source']['tls_plan_file']??''),(string)($p['source']['database_plan_file']??''));$verwacht=monitoring46Plan($c);
 if(!hash_equals(monitoring46Json($verwacht),monitoring46Json($p)))throw new RuntimeException('monitoring-plan.json wijkt af van het actuele 4.4/4.5/runtimecontract.');if(!hash_equals(runtime41NormPad(dirname($pad)),runtime41NormPad($p['bundle']['output_dir'])))throw new RuntimeException('monitoring-plan.json staat niet in de vaste tenant monitoringbundle.');
 foreach(monitoring46Artifacts($p)as$f=>$inh){$real=runtime41BestaandPad($f,'monitoringartifact');$h=@file_get_contents($real);if(!is_string($h)||!hash_equals(hash('sha256',$inh),hash('sha256',$h)))throw new RuntimeException('Monitoringartifact wijkt af van monitoring-plan.json.');}
 return['path'=>$pad,'sha256'=>hash('sha256',$raw),'plan'=>$p,'context'=>$c];
}
