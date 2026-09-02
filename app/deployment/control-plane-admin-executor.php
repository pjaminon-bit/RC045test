<?php
// Privileged helpers for the root control-plane executor. This file is never
// included by the web runtime. All process execution remains fixed/allowlisted.

require_once __DIR__ . '/control-plane-admin-suite-contract.php';
require_once __DIR__ . '/control-plane-auth-hardening.php';
require_once __DIR__ . '/tls-contract.php';
require_once __DIR__ . '/control-plane-onboarding-executor.php';

function control58ExecutorPaths(array $c): array
{
    $p = control58StatePaths($c);
    $config = (string)($c['_config_file'] ?? '');
    if (!runtime41IsAbsoluutPad($config) || runtime41HeeftRelatieveSegmenten($config)) throw new RuntimeException('Executor mist veilig runtimeconfigpad.');
    $p['auth_file'] = runtime41NormPad(dirname($config) . '/operators.htpasswd');
    return $p;
}

function control58HtpasswdUsers(array $c): array
{
    $file = control58ExecutorPaths($c)['auth_file'];
    if (runtime41SymlinkInPad($file) !== null || !is_file($file) || !is_readable($file)) return [];
    $raw = @file_get_contents($file);
    if (!is_string($raw)) return [];
    control512HtpasswdValidate($raw);
    $users=[];
    foreach(preg_split('/\r?\n/',trim($raw))?:[]as$line){
        if($line==='')continue;$pos=strpos($line,':');if($pos===false)throw new RuntimeException('Operatorbestand bevat ongeldige regel.');
        $user=substr($line,0,$pos);if(!control58OperatorValid($user))throw new RuntimeException('Operatorbestand bevat ongeldige gebruikersnaam.');$users[]=$user;
    }
    $users=array_values(array_unique($users));sort($users,SORT_STRING);return$users;
}

function control58ReadRoles(array $c): ?array
{
    $file=control58ExecutorPaths($c)['roles_file'];
    if(!file_exists($file)&&!is_link($file))return null;
    if(is_link($file)||!is_file($file)||!is_readable($file))throw new RuntimeException('Operatorrollenbestand is onveilig.');
    $raw=@file_get_contents($file);$data=is_string($raw)?json_decode($raw,true):null;$doc=control58RolesDocument($data);
    if($doc===null)throw new RuntimeException('Operatorrollenbestand heeft ongeldig schema.');return$doc;
}

function control58RolesWarning(array $c): ?string
{
    $file=control58ExecutorPaths($c)['roles_file'];
    if(!file_exists($file)&&!is_link($file))return'Operatorrollenstate ontbreekt; alle beheeroperators zijn fail-closed alleen-lezen totdat root de rollen expliciet bootstrapt.';
    try{$doc=control58ReadRoles($c);}catch(Throwable$e){return'Operatorrollenstate is ongeldig of onveilig; alle beheeroperators zijn fail-closed alleen-lezen totdat root herstel uitvoert.';}
    if($doc===null||$doc['roles']===[])return'Operatorrollenstate bevat geen actieve owner; alle beheeroperators zijn fail-closed alleen-lezen totdat root herstel uitvoert.';
    try{$users=control58HtpasswdUsers($c);}catch(Throwable$e){return'Basic-Auth operatorstate is ongeldig; rollen kunnen niet veilig worden gesynchroniseerd.';}
    if($users!==[]){$hasOwner=false;foreach($users as$user)if(($doc['roles'][$user]??null)==='owner'){$hasOwner=true;break;}if(!$hasOwner)return'Geen huidige Basic-Auth operator heeft de ownerrol; beheer is fail-closed totdat root herstel uitvoert.';}
    return null;
}

function control58SyncRoles(array $c): ?array
{
    $paths=control58ExecutorPaths($c);
    try{$existing=control58ReadRoles($c);}catch(Throwable$e){error_log('[control-plane security] '.$e->getMessage().' Automatische rollenreconstructie is geweigerd.');return null;}
    if($existing===null){error_log('[control-plane security] Operatorrollenstate ontbreekt; automatische owner-toekenning is geweigerd.');return null;}
    $users=control58HtpasswdUsers($c);if($users===[])return$existing;
    $roles=[];foreach($users as$user)$roles[$user]=(string)($existing['roles'][$user]??'viewer');
    if(!in_array('owner',$roles,true)){
        $doc=['schema'=>1,'phase'=>'5.8-operators','updated_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'roles'=>[]];
        if($existing['roles']!==[])cpeWrite($paths['roles_file'],$doc,0640,$c['runtime_user']);
        error_log('[control-plane security] Geen geauthenticeerde owner resteert; rollen zijn fail-closed en root-herstel is vereist.');
        return$existing['roles']===[]?$existing:$doc;
    }
    ksort($roles,SORT_STRING);$doc=['schema'=>1,'phase'=>'5.8-operators','updated_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'roles'=>$roles];
    if($existing['roles']===$roles)return$existing;
    cpeWrite($paths['roles_file'],$doc,0640,$c['runtime_user']);return$doc;
}

function control58ExecutorRole(array $c,string $operator): string
{
    try{$doc=control58ReadRoles($c);}catch(Throwable$e){return'viewer';}
    if($doc===null)return'viewer';
    return(string)($doc['roles'][$operator]??'viewer');
}

function control58CapabilityForAction(string $action): string
{
    if($action==='admin-refresh')return'read';
    if($action==='operator-role-set')return'roles';
    if(in_array($action,['schedule-create','schedule-cancel'],true))return'schedule';
    if($action==='diagnose')return'diagnose';
    if($action==='tls-renew')return'tls';
    return'mutate';
}

function control58ValidateAdminRequest(array $c,array $r): void
{
    $action=(string)($r['action']??'');if(!in_array($action,control58PlatformActions(),true))return;
    $role=control58ExecutorRole($c,(string)$r['operator']);$cap=control58CapabilityForAction($action);
    if(!control58RoleCan($role,$cap))throw new RuntimeException('Operatorrol staat deze platformactie niet toe.');
    $admin=$r['admin']??[];if(!is_array($admin))throw new RuntimeException('Adminpayload ontbreekt.');
    if($action==='admin-refresh'){
        if((string)$r['tenant_key']!=='platform'||$admin!==[])throw new RuntimeException('Refreshpayload is ongeldig.');return;
    }
    if($action==='operator-role-set'){
        if((string)$r['tenant_key']!=='platform'||array_keys($admin)!==['target_operator','role'])throw new RuntimeException('Rollenpayload is ongeldig.');
        if(!control58OperatorValid((string)$admin['target_operator'])||!in_array((string)$admin['role'],control58Roles(),true))throw new RuntimeException('Rollenpayload bevat ongeldige waarde.');return;
    }
    if($action==='schedule-create'){
        $keys=array_keys($admin);sort($keys,SORT_STRING);if($keys!==['execute_at_utc','target_action'])throw new RuntimeException('Schedulepayload is ongeldig.');
        if((string)$r['tenant_key']==='platform'||!runtime41CanoniekeTenantKey((string)$r['tenant_key'])||!in_array((string)$admin['target_action'],control58ScheduleActions(),true))throw new RuntimeException('Schedule bevat ongeldige tenant/actie.');
        $ts=strtotime((string)$admin['execute_at_utc']);$requested=strtotime((string)($r['requested_at_utc']??''));if($ts===false||$requested===false||$ts<$requested+30||$ts>$requested+366*86400)throw new RuntimeException('Schedulemoment valt buiten toegestane periode ten opzichte van de oorspronkelijke aanvraag.');return;
    }
    if($action==='schedule-cancel'){
        if(array_keys($admin)!==['schedule_id']||preg_match('/^[0-9a-f]{32}$/D',(string)$admin['schedule_id'])!==1)throw new RuntimeException('Schedule-cancel payload is ongeldig.');return;
    }
    if($action==='onboarding-resume'){
        $key=(string)$r['tenant_key'];if(!runtime41CanoniekeTenantKey($key))throw new RuntimeException('Onboarding bevat ongeldige tenant-key.');
        $tenant=control58FindTenant($c,$key);$status=(string)($tenant['status']??'');if(!in_array($status,['setup_required','unmanaged'],true))throw new RuntimeException('Tenant staat niet in een hervatbare onboardingstatus.');
        control59DnsProfile($admin,(string)($tenant['canonical_host']??''));return;
    }
    if(in_array($action,['diagnose','tls-renew'],true)){
        if($admin!==[]||(string)$r['tenant_key']==='platform'||!runtime41CanoniekeTenantKey((string)$r['tenant_key']))throw new RuntimeException('Tenantbeheerpayload is ongeldig.');
    }
}

function control58TenantManifestMeta(string $tenantRoot): array
{
    $file=$tenantRoot.'/tenant.json';if(is_link($file)||!is_file($file))return['name'=>null,'modules'=>[]];
    $raw=@file_get_contents($file);$m=is_string($raw)?json_decode($raw,true):null;if(!is_array($m))return['name'=>null,'modules'=>[]];
    $name=is_string($m['name']??null)?trim((string)$m['name']):null;$mods=[];
    if(is_array($m['modules']??null))foreach($m['modules']as$module)if(is_string($module)&&preg_match('/^[a-z0-9_]{2,40}$/D',$module)===1)$mods[]=$module;
    return['name'=>$name!==''?$name:null,'modules'=>array_values(array_unique($mods))];
}

function control58TenantStorage(string $tenantRoot,int $maxFiles=100000): array
{
    if(runtime41SymlinkInPad($tenantRoot)!==null||!is_dir($tenantRoot))return['bytes'=>null,'files'=>null,'truncated'=>false];
    $bytes=0;$files=0;$truncated=false;
    try{
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tenantRoot,FilesystemIterator::SKIP_DOTS));
        foreach($it as$info){$path=$info->getPathname();if(is_link($path)||!$info->isFile())continue;$files++;$size=$info->getSize();if(is_int($size)&&$size>0)$bytes+=$size;if($files>=$maxFiles){$truncated=true;break;}}
    }catch(Throwable$e){return['bytes'=>null,'files'=>null,'truncated'=>false];}
    return['bytes'=>$bytes,'files'=>$files,'truncated'=>$truncated];
}

function control58CertbotFullchainBestand(string $cert,string $liveRoot='/etc/letsencrypt/live',string $archiveRoot='/etc/letsencrypt/archive'): ?string
{
    if(preg_match('/^[a-z0-9][a-z0-9-]{2,80}$/D',$cert)!==1)return null;
    $liveRoot=rtrim($liveRoot,'/');$archiveRoot=rtrim($archiveRoot,'/');
    if($liveRoot===''||$archiveRoot===''||!str_starts_with($liveRoot,'/')||!str_starts_with($archiveRoot,'/'))return null;
    $file=$liveRoot.'/'.$cert.'/fullchain.pem';
    if(!is_link($file))return null;
    $real=@realpath($file);$archiveBase=@realpath($archiveRoot.'/'.$cert);
    if($real===false||$archiveBase===false||!is_file($real)||!is_readable($real)||!is_dir($archiveBase))return null;
    $prefix=rtrim($archiveBase,'/').'/';
    if(!str_starts_with($real,$prefix))return null;
    return $real;
}

function control58TlsStatusFromPlan(array $plan): array
{
    $cert=(string)($plan['tls']['cert_name']??'');$host=(string)($plan['canonical_host']??'');
    if(preg_match('/^[a-z0-9][a-z0-9-]{2,80}$/D',$cert)!==1||!web42CanoniekeHost($host))return['status'=>'unknown','valid_to_utc'=>null,'days_remaining'=>null];
    $file=control58CertbotFullchainBestand($cert);if($file===null)return['status'=>'missing','valid_to_utc'=>null,'days_remaining'=>null,'cert_name'=>$cert];
    $raw=@file_get_contents($file);$x=is_string($raw)?@openssl_x509_read($raw):false;$info=$x!==false?@openssl_x509_parse($x):false;
    if(!is_array($info))return['status'=>'invalid','valid_to_utc'=>null,'days_remaining'=>null,'cert_name'=>$cert];
    $to=(int)($info['validTo_time_t']??0);$from=(int)($info['validFrom_time_t']??PHP_INT_MAX);$san=(string)($info['extensions']['subjectAltName']??'');$names=[];foreach(explode(',',$san)as$item){$item=trim($item);if(str_starts_with($item,'DNS:'))$names[]=strtolower(substr($item,4));}
    if($from>time()+300||$to<=0||!in_array(strtolower($host),$names,true))return['status'=>'invalid','valid_to_utc'=>$to>0?gmdate('Y-m-d\TH:i:s\Z',$to):null,'days_remaining'=>null,'cert_name'=>$cert];
    $seconds=$to-time();$days=(int)floor($seconds/86400);$status=$seconds<=0?'expired':($days<=30?'expiring':'valid');
    return['status'=>$status,'valid_to_utc'=>gmdate('Y-m-d\TH:i:s\Z',$to),'days_remaining'=>$days,'cert_name'=>$cert];
}

function control58Onboarding(string $tenantRoot,string $status): array
{
    $checks=[
        ['key'=>'basis','label'=>'Basis tenant','done'=>is_file($tenantRoot.'/tenant.json')&&is_file($tenantRoot.'/config.php')&&is_file($tenantRoot.'/runtime.env')&&is_dir($tenantRoot.'/private')],
        ['key'=>'admin','label'=>'Eerste beheerder','done'=>is_file($tenantRoot.'/private/auth/master.php')&&!is_link($tenantRoot.'/private/auth/master.php')],
        ['key'=>'runtime','label'=>'PHP runtime','done'=>is_file($tenantRoot.'/runtime/runtime-plan.json')],
        ['key'=>'database','label'=>'Database','done'=>is_file($tenantRoot.'/database/database-plan.json')],
        ['key'=>'web','label'=>'Webserver','done'=>is_file($tenantRoot.'/webserver/web-plan.json')],
        ['key'=>'dns','label'=>'DNS readiness','done'=>is_file($tenantRoot.'/dns/dns-readiness.json')],
        ['key'=>'tls','label'=>'TLS/HTTPS','done'=>is_file($tenantRoot.'/tls/tls-plan.json')],
        ['key'=>'monitoring','label'=>'Monitoring','done'=>is_file($tenantRoot.'/monitoring/monitoring-plan.json')],
        ['key'=>'lifecycle','label'=>'Lifecycle','done'=>is_file($tenantRoot.'/lifecycle/lifecycle-plan.json')],
        ['key'=>'active','label'=>'Actief','done'=>$status==='active'],
    ];
    return['steps'=>$checks];
}

function control58EnrichTenantRow(array $c,string $tenantRoot,array $row,?array $lifecyclePlan=null): array
{
    $meta=control58TenantManifestMeta($tenantRoot);$row['name']=$meta['name'];$row['modules']=$meta['modules'];$row['storage']=control58TenantStorage($tenantRoot);
    $export=$row['last_export']??null;$created=is_array($export)?(string)($export['created_at_utc']??''):'';$ts=$created!==''?strtotime($created):false;
    $row['backup']=['available'=>is_array($export)&&preg_match('/^[0-9a-f]{64}$/D',(string)($export['sha256']??''))===1,'created_at_utc'=>$created!==''?$created:null,'age_days'=>$ts!==false?max(0,(int)floor((time()-$ts)/86400)):null];
    if($lifecyclePlan!==null)$row['tls']=control58TlsStatusFromPlan($lifecyclePlan);
    else{
        $tlsPath=$tenantRoot.'/tls/tls-plan.json';
        try{$ctx=is_file($tlsPath)&&!is_link($tlsPath)?tls44PlanLeesEnValideer($tlsPath,false):null;$fake=is_array($ctx)?['tls'=>['cert_name'=>(string)$ctx['plan']['acme']['cert_name']],'canonical_host'=>(string)$ctx['plan']['canonical_host']]:null;$row['tls']=$fake!==null?control58TlsStatusFromPlan($fake):['status'=>'not_configured','valid_to_utc'=>null,'days_remaining'=>null];}
        catch(Throwable$e){$row['tls']=['status'=>'invalid','valid_to_utc'=>null,'days_remaining'=>null];}
    }
    try{$row['dns_profile']=control59DnsPlanProfile($tenantRoot);}catch(Throwable$e){$row['dns_profile']=null;}
    $row['onboarding']=control58Onboarding($tenantRoot,(string)($row['status']??''));return$row;
}

function control58ScheduleFiles(array $c): array
{
    $dir=control58ExecutorPaths($c)['schedules_dir'];if(!is_dir($dir)||is_link($dir))return[];$files=glob($dir.'/*.json')?:[];sort($files,SORT_STRING);return$files;
}

function control58ReadSchedule(array $c,string $id): array
{
    if(preg_match('/^[0-9a-f]{32}$/D',$id)!==1)throw new RuntimeException('Schedule-id is ongeldig.');$file=control58ExecutorPaths($c)['schedules_dir'].'/'.$id.'.json';
    if(is_link($file)||!is_file($file))throw new RuntimeException('Schedule bestaat niet.');$raw=@file_get_contents($file);$doc=control58ScheduleDocument(is_string($raw)?json_decode($raw,true):null);if($doc===null||!hash_equals($id,$doc['schedule_id']))throw new RuntimeException('Schedulebestand is ongeldig.');return$doc;
}

function control58WriteSchedule(array $c,array $doc): void
{
    $valid=control58ScheduleDocument($doc);if($valid===null)throw new RuntimeException('Schedule kan niet veilig worden opgeslagen.');$paths=control58ExecutorPaths($c);cpeDir($paths['schedules_dir'],0750,0,$c['runtime_user']);cpeWrite($paths['schedules_dir'].'/'.$valid['schedule_id'].'.json',$valid,0640,$c['runtime_user']);
}

function control58ScheduleUnit(string $id): string
{
    if(preg_match('/^[0-9a-f]{32}$/D',$id)!==1)throw new RuntimeException('Schedule-id is ongeldig voor systemd.');return'vp-control-schedule-'.$id;
}

function control58FindTenant(array $c,string $key): array
{
    foreach(cpeSnapshot($c)['tenants']as$row)if(is_array($row)&&hash_equals($key,(string)($row['tenant_key']??'')))return$row;throw new RuntimeException('Tenant staat niet in actuele platformstatus.');
}

function control58ScheduleCreate(array $c,array $r): string
{
    $a=$r['admin'];$target=(string)$a['target_action'];$key=(string)$r['tenant_key'];$tenant=control58FindTenant($c,$key);$status=(string)($tenant['status']??'');
    $allowed=match($target){'suspend'=>$status==='active','activate'=>$status==='suspended','export'=>$status==='suspended','cancel-delete'=>$status==='pending_delete',default=>false};if(!$allowed)throw new RuntimeException('Geplande actie past niet bij de actuele tenantstatus.');
    $id=bin2hex(random_bytes(16));$execute=control58Utc((string)$a['execute_at_utc']);$delay=max(1,strtotime($execute)-time());$doc=['schema'=>1,'phase'=>'5.8-schedule','schedule_id'=>$id,'tenant_key'=>$key,'operator'=>(string)$r['operator'],'action'=>$target,'execute_at_utc'=>$execute,'status'=>'scheduled','request_id'=>null,'message'=>null];control58WriteSchedule($c,$doc);
    $systemd='/usr/bin/systemd-run';if(!is_file($systemd)||!is_executable($systemd))throw new RuntimeException('systemd-run ontbreekt voor geplande acties.');$script=$c['app_root'].'/bin/control-plane-scheduled-run.php';if(is_link($script)||!is_file($script))throw new RuntimeException('Scheduled-run helper ontbreekt.');$unit=control58ScheduleUnit($id);
    [$code,$out,$err]=cpeRun([$systemd,'--unit='.$unit,'--on-active='.$delay.'s','--property=Type=oneshot','--property=User=root','--property=Group=root','--property=UMask=0027',PHP_BINARY,$script,'--config='.$c['_config_file'],'--schedule='.$id]);
    if($code!==0){@unlink(control58ExecutorPaths($c)['schedules_dir'].'/'.$id.'.json');throw new RuntimeException('Geplande systemd-run kon niet worden aangemaakt: '.trim($err!==''?$err:$out));}
    return'Actie '.$target.' gepland voor '.$execute.' (schedule '.substr($id,0,8).').';
}

function control58ScheduleCancel(array $c,array $r): string
{
    throw new RuntimeException('Schedule-cancel mag uitsluitend via de gelockte root-executorroute worden uitgevoerd.');
}

function control58RoleSet(array $c,array $r): string
{
    $doc=control58SyncRoles($c);if($doc===null||$doc['roles']===[])throw new RuntimeException('Operatorrollen zijn niet veilig geïnitialiseerd; gebruik de root-only rollenbootstrap voor herstel.');$target=(string)$r['admin']['target_operator'];$role=(string)$r['admin']['role'];if(!array_key_exists($target,$doc['roles']))throw new RuntimeException('Operator staat niet in het Basic-Auth operatorbestand.');
    $roles=$doc['roles'];$before=$roles[$target];$roles[$target]=$role;if($before==='owner'&&$role!=='owner'&&count(array_filter($roles,static fn($x)=>$x==='owner'))<1)throw new RuntimeException('De laatste Eigenaar kan niet worden gedegradeerd.');ksort($roles,SORT_STRING);cpeWrite(control58ExecutorPaths($c)['roles_file'],['schema'=>1,'phase'=>'5.8-operators','updated_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'roles'=>$roles],0640,$c['runtime_user']);return'Rol van '.$target.' gewijzigd naar '.control58RoleLabel($role).'.';
}

function control58Diagnose(array $c,array $r): string
{
    $row=control58FindTenant($c,(string)$r['tenant_key']);$tls=is_array($row['tls']??null)?$row['tls']:[];$storage=is_array($row['storage']??null)?$row['storage']:[];$backup=is_array($row['backup']??null)?$row['backup']:[];
    $parts=['status='.(string)($row['status']??'unknown'),'health='.(($row['healthy']??false)?'up':'not-up'),'tls='.(string)($tls['status']??'unknown'),'storage='.(is_int($storage['bytes']??null)?(string)$storage['bytes']:'unknown'),'export='.(($backup['available']??false)?'available':'missing')];return'DIAGNOSE OK '.implode(' ',$parts);
}

function control58TlsRenew(array $c,array $r): string
{
    $key=(string)$r['tenant_key'];$planFile=$c['tenants_root'].'/'.$key.'/lifecycle/lifecycle-plan.json';$ctx=lifecycle48PlanLeesEnValideer($planFile);$plan=$ctx['plan'];$tls=control58TlsStatusFromPlan($plan);$days=$tls['days_remaining']??null;if(!is_int($days)||$days>35)throw new RuntimeException('TLS-certificaat valt niet binnen de veilige renew-periode.');
    $cert=(string)$plan['tls']['cert_name'];$certbot=is_file('/usr/bin/certbot')?'/usr/bin/certbot':(is_file('/usr/local/bin/certbot')?'/usr/local/bin/certbot':'');if($certbot===''||!is_executable($certbot))throw new RuntimeException('Certbot ontbreekt.');[$code,$out,$err]=cpeRun([$certbot,'renew','--cert-name',$cert,'--non-interactive','--quiet']);if($code!==0)throw new RuntimeException('Certbot renew faalde: '.trim($err!==''?$err:$out));
    $after=control58TlsStatusFromPlan($plan);if(in_array($after['status']??'', ['missing','invalid','expired'],true))throw new RuntimeException('TLS-certificaat is na renew niet geldig.');[$code,$out,$err]=cpeRun(['/usr/sbin/apache2ctl','configtest']);if($code!==0)throw new RuntimeException('Apache configtest faalde na TLS-renew: '.trim($err!==''?$err:$out));[$code,,$err]=cpeRun(['/usr/bin/systemctl','reload','apache2']);if($code!==0)throw new RuntimeException('Apache reload faalde na TLS-renew: '.$err);return'TLS renew gecontroleerd; certificaatstatus '.(string)$after['status'].'.';
}

function control58AuditRefresh(array $c,int $limit=500): void
{
    $audit=(string)$c['audit_file'];$rows=[];
    if(is_file($audit)&&!is_link($audit)&&is_readable($audit)){
        $lines=@file($audit,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);if(is_array($lines))foreach(array_slice($lines,-$limit)as$line){$r=json_decode($line,true);if(!is_array($r))continue;$op=(string)($r['operator']??'');$tenant=(string)($r['tenant_key']??'');$result=(string)($r['result']??'');if(!control58OperatorValid($op)||($tenant!=='platform'&&!runtime41CanoniekeTenantKey($tenant))||!in_array($result,['ok','failed'],true))continue;$rows[]=['timestamp_utc'=>(string)($r['timestamp_utc']??gmdate('Y-m-d\TH:i:s\Z')),'operator'=>$op,'tenant_key'=>$tenant,'action'=>substr((string)($r['action']??''),0,64),'result'=>$result,'message'=>substr((string)($r['message']??''),0,300)];}
    }
    cpeWrite(control58ExecutorPaths($c)['audit_view_file'],['schema'=>1,'phase'=>'5.8-audit-view','generated_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'rows'=>$rows],0640,$c['runtime_user']);
}

function control58ExecutorRefresh(array $c): void
{
    control58SyncRoles($c);$warning=control58RolesWarning($c);if($warning!==null)error_log('[control-plane security] '.$warning);control58AuditRefresh($c);$paths=control58ExecutorPaths($c);cpeDir($paths['schedules_dir'],0750,0,$c['runtime_user']);
}

function control58AdminRefreshMessage(array $c): string
{
    $warning=control58RolesWarning($c);return$warning===null?'Platformstatus, rollen en auditweergave vernieuwd.':'Platformstatus en auditweergave vernieuwd. SECURITY: '.$warning;
}

function control58ExecuteAdminAction(array $c,array $r): ?array
{
    $action=(string)$r['action'];if(!in_array($action,control58PlatformActions(),true))return null;
    $message=match($action){
        'admin-refresh'=>control58AdminRefreshMessage($c),
        'operator-role-set'=>control58RoleSet($c,$r),
        'schedule-create'=>control58ScheduleCreate($c,$r),
        'schedule-cancel'=>control58ScheduleCancel($c,$r),
        'diagnose'=>control58Diagnose($c,$r),
        'tls-renew'=>control58TlsRenew($c,$r),
        'onboarding-resume'=>control59Resume($c,$r),
        default=>throw new RuntimeException('Onbekende adminactie.'),
    };
    return[0,$message,''];
}

function control58MarkScheduleResult(array $c,string $requestId,string $result,string $message): void
{
    foreach(control58ScheduleFiles($c)as$file){$raw=@file_get_contents($file);$doc=control58ScheduleDocument(is_string($raw)?json_decode($raw,true):null);if($doc===null||($doc['request_id']??null)!==$requestId)continue;$doc['status']=$result==='ok'?'completed':'failed';$doc['message']=substr($message,0,500);control58WriteSchedule($c,$doc);break;}
}
