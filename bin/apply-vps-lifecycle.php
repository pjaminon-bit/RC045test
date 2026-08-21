<?php
// ============================================================
// Fase 4.8 — tenant lifecycle root-apply
// ============================================================
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Alleen via CLI beschikbaar.'); }
require_once dirname(__DIR__) . '/app/deployment/lifecycle-contract.php';
require_once dirname(__DIR__) . '/app/deployment/lifecycle-purge-hardening.php';
require_once dirname(__DIR__) . '/app/deployment/process-runner.php';

function apply48Stop(string $m, int $c = 1): never { fwrite(STDERR, "FOUT: {$m}\n"); exit($c); }
function apply48Binary(string $name): string
{
    static $cache = [];
    if (isset($cache[$name])) return $cache[$name];
    if (str_starts_with($name, '/')) {
        if (!is_file($name) || !is_executable($name)) throw new RuntimeException('Vereiste lifecycle-executable ontbreekt: ' . $name);
        return $cache[$name] = $name;
    }
    $known = [
        'runuser' => ['/usr/sbin/runuser','/usr/bin/runuser'],
        'psql' => ['/usr/bin/psql'],
        'pg_dump' => ['/usr/bin/pg_dump'],
        'systemctl' => ['/usr/bin/systemctl'],
        'pgrep' => ['/usr/bin/pgrep'],
        'certbot' => ['/usr/bin/certbot','/usr/local/bin/certbot'],
        'userdel' => ['/usr/sbin/userdel'],
        'groupdel' => ['/usr/sbin/groupdel'],
    ];
    if (!isset($known[$name])) throw new RuntimeException('Niet-toegestane lifecycle PATH-binary: ' . $name);
    foreach ($known[$name] as $candidate) {
        if (is_file($candidate) && is_executable($candidate)) return $cache[$name] = $candidate;
    }
    throw new RuntimeException('Vereiste lifecycle-executable ontbreekt: ' . $name);
}
function apply48Run(array $cmd, ?string $stdin = null, ?string $stdoutFile = null): array
{
    if ($cmd === [] || !isset($cmd[0])) throw new RuntimeException('Lifecycle subprocesscommando ontbreekt.');
    $cmd[0] = apply48Binary((string)$cmd[0]);
    if (basename((string)$cmd[0]) === 'runuser') {
        $sep = array_search('--', $cmd, true);
        if ($sep === false || !isset($cmd[$sep + 1])) throw new RuntimeException('runuser lifecyclecommando mist exact child-executable.');
        $cmd[$sep + 1] = apply48Binary((string)$cmd[$sep + 1]);
    }
    return process521Run($cmd, $stdin, $stdoutFile, null, 3600);
}
function apply48Deps(array $p): void
{
    foreach (['runuser','psql','pg_dump','systemctl','pgrep','certbot','userdel','groupdel'] as $name) apply48Binary($name);
    foreach ([(string)$p['apache']['control_binary'],(string)$p['runtime']['php_binary'],(string)$p['runtime']['fpm_test_binary'],'/usr/bin/tar'] as $binary) apply48Binary($binary);
}
function apply48Pg(string $sql, string $db = 'postgres'): string
{
    [$c,$o,$e] = apply48Run(['runuser','-u','postgres','--','psql','-X','-w','-v','ON_ERROR_STOP=1','-At','-d',$db,'-c',$sql]);
    if ($c !== 0) throw new RuntimeException('PostgreSQL-query faalde: ' . ($e !== '' ? $e : $o));
    return trim($o);
}
function apply48Root(): void
{
    if (PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid') || posix_geteuid() !== 0) throw new RuntimeException('Lifecyclemutaties vereisen Linux root.');
}
function apply48Uid(string|int $owner): int
{
    if (is_int($owner) || ctype_digit((string)$owner)) return (int)$owner;
    if (!function_exists('posix_getpwnam')) throw new RuntimeException('Lifecycle ownercontrole vereist posix_getpwnam.');
    $u=@posix_getpwnam((string)$owner);if(!is_array($u))throw new RuntimeException('Verwachte lifecycle-owner bestaat niet: '.$owner);return(int)$u['uid'];
}
function apply48Gid(string|int $group): int
{
    if (is_int($group) || ctype_digit((string)$group)) return (int)$group;
    if (!function_exists('posix_getgrnam')) throw new RuntimeException('Lifecycle groepscontrole vereist posix_getgrnam.');
    $g=@posix_getgrnam((string)$group);if(!is_array($g))throw new RuntimeException('Verwachte lifecycle-groep bestaat niet: '.$group);return(int)$g['gid'];
}
function apply48Meta(string $p,int $mode,bool $dir,string|int $owner=0,string|int $group=0):void
{
    $s=@lstat($p);if(!is_array($s)||is_link($p)||($dir?!is_dir($p):!is_file($p))||(int)$s['uid']!==apply48Uid($owner)||(int)$s['gid']!==apply48Gid($group)||(((int)$s['mode']&0777)!==$mode))throw new RuntimeException('Lifecycle owner/group/mode wijkt af: '.$p);
}
function apply48FileMeta(string$p,int$mode=0600,string|int$group=0):void
{
    if(runtime41SymlinkInPad($p)!==null||!is_file($p))throw new RuntimeException('Lifecyclebestand ontbreekt of bevat symlink: '.$p);if(!@chown($p,0)||!@chgrp($p,$group)||!@chmod($p,$mode))throw new RuntimeException('Lifecyclebestand kon niet veilig worden gemetadateerd: '.$p);apply48Meta($p,$mode,false,0,$group);
}
function apply48SafeDir(string $p, int $mode = 0750, string|int $group = 0): void
{
    $l = runtime41SymlinkInPad($p); if ($l !== null) throw new RuntimeException('Symlink in lifecyclepad: ' . $l);
    if (!is_dir($p) && !@mkdir($p, $mode, true) && !is_dir($p)) throw new RuntimeException('Map kon niet worden aangemaakt: ' . $p);
    if (runtime41SymlinkInPad($p) !== null || !@chown($p,0) || !@chgrp($p,$group) || !@chmod($p,$mode)) throw new RuntimeException('Onveilige lifecyclemap: ' . $p);apply48Meta($p,$mode,true,0,$group);
}
function apply48Write(string $p, string $inhoud, int $mode = 0640): void
{
    if (runtime41SymlinkInPad($p) !== null) throw new RuntimeException('Schrijfpad bevat symlink: ' . $p);
    $d=dirname($p); if(!is_dir($d)) throw new RuntimeException('Schrijfmap ontbreekt: '.$d);
    $tmp=$d.'/.'.basename($p).'.tmp.'.bin2hex(random_bytes(6));
    if(@file_put_contents($tmp,$inhoud,LOCK_EX)===false) throw new RuntimeException('Tijdelijke lifecyclewrite faalde.');
    if(!@chown($tmp,0)||!@chgrp($tmp,0)||!@chmod($tmp,$mode)){@unlink($tmp);throw new RuntimeException('Tijdelijke lifecyclewrite kon niet veilig worden gemetadateerd.');}apply48Meta($tmp,$mode,false,0,0);
    if(is_link($p)||!@rename($tmp,$p)){@unlink($tmp);throw new RuntimeException('Lifecyclewrite kon niet atomisch worden geplaatst.');}
    if(!@chown($p,0)||!@chgrp($p,0)||!@chmod($p,$mode))throw new RuntimeException('Lifecyclewrite-rechten konden niet worden genormaliseerd: '.$p);apply48Meta($p,$mode,false,0,0);
}
function apply48Utc(): string { return gmdate('Y-m-d\TH:i:s\Z'); }
function apply48Lock(array $p)
{
    $lock=(string)$p['filesystem']['lock_file'];
    if(!runtime41IsAbsoluutPad($lock)||runtime41HeeftRelatieveSegmenten($lock)||runtime41SymlinkInPad($lock)!==null)throw new RuntimeException('Lifecycle-lockpad is onveilig.');
    $dir=dirname($lock);if(!is_dir($dir)||runtime41SymlinkInPad($dir)!==null)throw new RuntimeException('Lifecycle-lockmap ontbreekt of is onveilig.');
    $h=@fopen($lock,'c');if(!is_resource($h))throw new RuntimeException('Lifecycle-lock kon niet worden geopend.');if(!@chown($lock,0)||!@chgrp($lock,0)||!@chmod($lock,0600)){fclose($h);throw new RuntimeException('Lifecycle-lock kon niet root-only worden gemaakt.');}apply48Meta($lock,0600,false,0,0);
    if(!flock($h,LOCK_EX|LOCK_NB)){fclose($h);throw new RuntimeException('Er draait al een lifecycleactie voor deze tenant.');}
    return $h;
}
function apply48StateValideer(array $p, array $s): array
{
    if((int)($s['schema']??0)!==1||($s['phase']??'')!=='4.8-state'||!hash_equals((string)$p['tenant_key'],(string)($s['tenant_key']??'')))throw new RuntimeException('Lifecycle-state is ongeldig of hoort bij andere tenant.');
    if(!in_array((string)($s['status']??''),(array)$p['lifecycle']['managed_states'],true))throw new RuntimeException('Lifecycle-state heeft onbekende status.');
    if((int)($s['generation']??0)<1)throw new RuntimeException('Lifecycle-state generation is ongeldig.');
    if(isset($s['transition'])&&$s['transition']!==null){$t=$s['transition'];if(!is_array($t)||!in_array((string)($t['action']??''),['activate','suspend'],true))throw new RuntimeException('Lifecycle transition-state is ongeldig.');}
    return $s;
}
function apply48StateLees(array $p, bool $magOntbreken=false): ?array
{
    $f=(string)$p['filesystem']['state_file'];if(!file_exists($f)){if($magOntbreken)return null;throw new RuntimeException('Lifecycle-state ontbreekt; gebruik eerst --adopt-active.');}
    if(is_link($f)||!is_file($f))throw new RuntimeException('Lifecycle-state is geen veilig regulier bestand.');
    $raw=@file_get_contents($f);$s=is_string($raw)?json_decode($raw,true):null;if(!is_array($s))throw new RuntimeException('Lifecycle-state bevat ongeldige JSON.');return apply48StateValideer($p,$s);
}
function apply48StateSchrijf(array $p, array $s): void { apply48StateValideer($p,$s); apply48Write((string)$p['filesystem']['state_file'], lifecycle48Json($s)); }
function apply48NieuweState(array $p,string $status,array $extra=[]):array{return array_merge(['schema'=>1,'phase'=>'4.8-state','tenant_key'=>$p['tenant_key'],'status'=>$status,'generation'=>1,'updated_at_utc'=>apply48Utc(),'transition'=>null,'last_export'=>null],$extra);}
function apply48CommitState(array $p,array $s,string $status,array $extra=[]):array{$n=array_merge($s,$extra);$n['status']=$status;$n['generation']=(int)$s['generation']+1;$n['updated_at_utc']=apply48Utc();$n['transition']=null;apply48StateSchrijf($p,$n);return$n;}
function apply48Transition(array $p,array $s,string $actie):array{if(($s['transition']??null)!==null)throw new RuntimeException('Er staat al een onafgeronde lifecycle-transition; gebruik --recover.');$s['transition']=['action'=>$actie,'started_at_utc'=>apply48Utc(),'from'=>$s['status']];$s['updated_at_utc']=apply48Utc();apply48StateSchrijf($p,$s);return$s;}
function apply48Audit(array $p,string $action,string $result,string $before,?string $after=null,array $extra=[]):void
{
    $f=(string)$p['filesystem']['audit_file'];apply48SafeDir(dirname($f),0750,'adm');if(is_link($f))throw new RuntimeException('Lifecycle auditdoel mag geen symlink zijn.');
    $r=['timestamp_utc'=>apply48Utc(),'tenant_key'=>$p['tenant_key'],'action'=>$action,'result'=>$result,'status_before'=>$before,'status_after'=>$after];foreach(['export_sha256','generation']as$k)if(isset($extra[$k]))$r[$k]=$extra[$k];
    $j=json_encode($r,JSON_UNESCAPED_SLASHES);if(!is_string($j)||@file_put_contents($f,$j."\n",FILE_APPEND|LOCK_EX)===false)throw new RuntimeException('Lifecycle audit kon niet worden geschreven.');if(!@chown($f,0)||!@chgrp($f,'adm')||!@chmod($f,0640))throw new RuntimeException('Lifecycle auditmetadata kon niet worden genormaliseerd.');apply48Meta($f,0640,false,0,'adm');
}
function apply48BasisMappen(array $p,string $planRaw):void
{
    apply48SafeDir((string)$p['filesystem']['state_dir']);apply48SafeDir((string)$p['filesystem']['plan_snapshot_dir']);apply48SafeDir((string)$p['filesystem']['tombstone_dir']);
    apply48Write((string)$p['filesystem']['plan_snapshot_file'],$planRaw,0640);
}
function apply48ExactFile(string $pad,string $bron,string $label):void
{
    if(runtime41SymlinkInPad($pad)!==null||!is_file($pad)||runtime41SymlinkInPad($bron)!==null||!is_file($bron))throw new RuntimeException($label.' ontbreekt of is onveilig.');
    $a=@file_get_contents($pad);$b=@file_get_contents($bron);if(!is_string($a)||!is_string($b)||!hash_equals(hash('sha256',$a),hash('sha256',$b)))throw new RuntimeException($label.' wijkt af van gevalideerde bundle.');
}
function apply48LinkExact(string $link,string $doel):bool
{
    if(is_link($link)){if(realpath($link)!==realpath($doel))throw new RuntimeException('Apache symlink wijst naar onverwacht doel: '.$link);return true;}
    if(file_exists($link))throw new RuntimeException('Apache enabled-doel is geen symlink: '.$link);return false;
}
function apply48ApacheTestReload(array $p):void
{
    [$c,$o,$e]=apply48Run([(string)$p['apache']['control_binary'],'configtest']);if($c!==0)throw new RuntimeException('Apache configtest faalt: '.trim($o."\n".$e));
    [$c,,$e]=apply48Run(['systemctl','reload','apache2']);if($c!==0)throw new RuntimeException('Apache reload faalt: '.$e);
}
function apply48ApacheAan(array $p):void
{
    apply48ExactFile((string)$p['apache']['tenant_http_available'],(string)$p['apache']['tenant_http_bundle'],'HTTP-vhost');
    apply48ExactFile((string)$p['apache']['tenant_https_available'],(string)$p['apache']['tenant_https_bundle'],'HTTPS-vhost');
    $paren=[[$p['apache']['tenant_http_available'],$p['apache']['tenant_http_enabled']],[$p['apache']['tenant_https_available'],$p['apache']['tenant_https_enabled']]];$nieuw=[];
    try{foreach($paren as[$d,$l]){$bestond=apply48LinkExact((string)$l,(string)$d);$nieuw[(string)$l]=!$bestond;if(!$bestond&&!@symlink((string)$d,(string)$l))throw new RuntimeException('Tenant Apache-link kon niet worden gemaakt.');}apply48ApacheTestReload($p);}catch(Throwable$e){foreach($nieuw as$l=>$n)if($n&&is_link($l))@unlink($l);try{apply48ApacheTestReload($p);}catch(Throwable$ignored){}throw$e;}
}
function apply48ApacheSuspend(array $p):void
{
    apply48ExactFile((string)$p['apache']['tenant_http_available'],(string)$p['apache']['tenant_http_bundle'],'HTTP-01 vhost');
    if(!apply48LinkExact((string)$p['apache']['tenant_http_enabled'],(string)$p['apache']['tenant_http_available']))throw new RuntimeException('HTTP-01 tenantroute moet actief blijven tijdens suspend.');
    $l=(string)$p['apache']['tenant_https_enabled'];$d=(string)$p['apache']['tenant_https_available'];$weg=false;
    if(is_link($l)){if(realpath($l)!==realpath($d))throw new RuntimeException('Onverwachte tenant HTTPS-link.');if(!@unlink($l))throw new RuntimeException('Tenant HTTPS-link kon niet worden uitgeschakeld.');$weg=true;}elseif(file_exists($l))throw new RuntimeException('Tenant HTTPS enabled-doel is geen symlink.');
    try{apply48ApacheTestReload($p);}catch(Throwable$e){if($weg&&!file_exists($l)&&!is_link($l))@symlink($d,$l);try{apply48ApacheTestReload($p);}catch(Throwable$ignored){}throw$e;}
}
function apply48ApachePurge(array $p):void
{
    $weg=[];foreach([[$p['apache']['tenant_http_enabled'],$p['apache']['tenant_http_available']],[$p['apache']['tenant_https_enabled'],$p['apache']['tenant_https_available']]]as[$l,$d]){if(is_link((string)$l)){if(realpath((string)$l)!==realpath((string)$d))throw new RuntimeException('Onverwachte tenant Apache-link: '.$l);$weg[]=[(string)$l,(string)$d];}elseif(file_exists((string)$l))throw new RuntimeException('Tenant Apache enabled-doel is geen symlink.');}
    foreach($weg as[$l])if(!@unlink($l))throw new RuntimeException('Tenant Apache-link kon niet worden verwijderd: '.$l);
    try{apply48ApacheTestReload($p);}catch(Throwable$e){foreach($weg as[$l,$d])if(!file_exists($l)&&!is_link($l))@symlink($d,$l);try{apply48ApacheTestReload($p);}catch(Throwable$ignored){}throw$e;}
}
function apply48DbBestaat(string $type,string $naam):bool{$lit=database45SqlLiteral($naam);$sql=$type==='role'?"SELECT count(*) FROM pg_roles WHERE rolname={$lit}":"SELECT count(*) FROM pg_database WHERE datname={$lit}";return apply48Pg($sql)==='1';}
function apply48DbMarker(string$type,string$naam):string{$lit=database45SqlLiteral($naam);return$type==='role'?apply48Pg("SELECT COALESCE(shobj_description(oid,'pg_authid'),'') FROM pg_roles WHERE rolname={$lit}"):apply48Pg("SELECT COALESCE(shobj_description(oid,'pg_database'),'') FROM pg_database WHERE datname={$lit}");}
function apply48DbBinding(array$p):void{foreach([['role',$p['database']['app_role']],['role',$p['database']['owner_role']],['database',$p['database']['database']]]as[$t,$n]){if(!apply48DbBestaat($t,(string)$n)||!hash_equals((string)$p['database']['marker'],apply48DbMarker($t,(string)$n)))throw new RuntimeException('PostgreSQL object ontbreekt of tenantmarker wijkt af: '.$n);}}
function apply48DbUit(array$p):void{apply48DbBinding($p);$u=(string)$p['database']['app_role'];$d=(string)$p['database']['database'];apply48Pg('ALTER ROLE '.$u.' NOLOGIN PASSWORD NULL');apply48Pg('SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE pid<>pg_backend_pid() AND (usename='.database45SqlLiteral($u).' OR datname='.database45SqlLiteral($d).')');$v=apply48Pg('SELECT rolcanlogin::text FROM pg_roles WHERE rolname='.database45SqlLiteral($u));if($v!=='false')throw new RuntimeException('Tenant database-role bleef LOGIN.');}
function apply48DbAan(array$p):void
{
    apply48DbBinding($p);$u=(string)$p['database']['app_role'];apply48Pg('ALTER ROLE '.$u.' LOGIN INHERIT NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS CONNECTION LIMIT 10 PASSWORD NULL');
    $php=(string)$p['runtime']['php_binary'];$check=(string)$p['runtime']['app_root'].'/bin/check-vps-database.php';[$c,$o,$e]=apply48Run(['runuser','-u',$u,'--',$php,$check,'--database-plan='.(string)$p['database']['plan_file']]);if($c!==0)throw new RuntimeException('Database runtimecheck na activatie faalt: '.trim($o."\n".$e));
}
function apply48TimerUit(array$p):void{[$c,,$e]=apply48Run(['systemctl','disable','--now',(string)$p['monitoring']['timer_unit']]);if($c!==0){[$q]=apply48Run(['systemctl','is-enabled',(string)$p['monitoring']['timer_unit']]);if($q===0)throw new RuntimeException('Monitoring timer kon niet worden uitgeschakeld: '.$e);}}
function apply48TimerAan(array$p):void{[$c,,$e]=apply48Run(['systemctl','enable','--now',(string)$p['monitoring']['timer_unit']]);if($c!==0)throw new RuntimeException('Monitoring timer kon niet worden geactiveerd: '.$e);}
function apply48ProcessenStil(array$p):bool
{
    $u=(string)$p['runtime']['user'];if(function_exists('posix_getpwnam')&&!is_array(@posix_getpwnam($u)))return true;
    [$c,$o]=apply48Run(['pgrep','-u',$u]);return$c===1||($c===0&&trim($o)==='');
}
function apply48FpmUit(array$p):void
{
    $dst=(string)$p['runtime']['pool_installed'];$src=(string)$p['runtime']['pool_bundle'];if(is_file($dst)){apply48ExactFile($dst,$src,'FPM poolconfig');if(!@unlink($dst))throw new RuntimeException('FPM poolconfig kon niet worden uitgeschakeld.');}elseif(file_exists($dst)||is_link($dst))throw new RuntimeException('FPM pooldoel is onveilig.');
    [$c,$o,$e]=apply48Run([(string)$p['runtime']['fpm_test_binary'],'-t']);if($c!==0)throw new RuntimeException('PHP-FPM configtest na uitschakelen faalt: '.trim($o."\n".$e));[$c,,$e]=apply48Run(['systemctl','reload',(string)$p['runtime']['fpm_service']]);if($c!==0)throw new RuntimeException('PHP-FPM reload faalt: '.$e);
    for($i=0;$i<20;$i++){clearstatcache(true,(string)$p['runtime']['socket']);if(!file_exists((string)$p['runtime']['socket'])&&apply48ProcessenStil($p))return;usleep(250000);}throw new RuntimeException('Tenant FPM socket/processen bleven actief na uitschakelen.');
}
function apply48FpmAan(array$p):void
{
    $u=(string)$p['runtime']['user'];if(!function_exists('posix_getpwnam')||!is_array(@posix_getpwnam($u)))throw new RuntimeException('Tenant Linux-user ontbreekt; activatie geweigerd.');
    $src=(string)$p['runtime']['pool_bundle'];$dst=(string)$p['runtime']['pool_installed'];if(runtime41SymlinkInPad($src)!==null||!is_file($src))throw new RuntimeException('FPM bundlebron ontbreekt.');
    if(is_file($dst))apply48ExactFile($dst,$src,'FPM poolconfig');elseif(file_exists($dst)||is_link($dst))throw new RuntimeException('FPM pooldoel is onveilig.');else{$raw=@file_get_contents($src);if(!is_string($raw))throw new RuntimeException('FPM bundlebron onleesbaar.');$tmp=dirname($dst).'/.'.basename($dst).'.tmp.'.bin2hex(random_bytes(5));if(@file_put_contents($tmp,$raw,LOCK_EX)===false)throw new RuntimeException('FPM poolconfig kon niet worden geplaatst.');if(!@chown($tmp,0)||!@chgrp($tmp,0)||!@chmod($tmp,0644)){@unlink($tmp);throw new RuntimeException('Tijdelijke FPM poolconfig kon niet veilig worden gemetadateerd.');}apply48Meta($tmp,0644,false,0,0);if(!@rename($tmp,$dst)){@unlink($tmp);throw new RuntimeException('FPM poolconfig activatie faalde.');}if(!@chown($dst,0)||!@chgrp($dst,0)||!@chmod($dst,0644))throw new RuntimeException('FPM poolconfig-rechten konden niet worden genormaliseerd.');apply48Meta($dst,0644,false,0,0);}
    [$c,$o,$e]=apply48Run([(string)$p['runtime']['fpm_test_binary'],'-t']);if($c!==0)throw new RuntimeException('PHP-FPM configtest faalt: '.trim($o."\n".$e));[$c,,$e]=apply48Run(['systemctl','reload',(string)$p['runtime']['fpm_service']]);if($c!==0)throw new RuntimeException('PHP-FPM reload faalt: '.$e);for($i=0;$i<20;$i++){clearstatcache(true,(string)$p['runtime']['socket']);if(file_exists((string)$p['runtime']['socket']))return;usleep(250000);}throw new RuntimeException('Tenant FPM socket verscheen niet na activatie.');
}
function apply48Health(array$p):void{$php=(string)$p['runtime']['php_binary'];$check=(string)$p['runtime']['app_root'].'/bin/check-vps-health.php';[$c,$o,$e]=apply48Run([$php,$check,'--monitoring-plan='.(string)$p['monitoring']['plan_file'],'--probe','--write-status']);if($c!==0)throw new RuntimeException('Tenant healthcheck faalt: '.trim($o."\n".$e));}
function apply48AdoptControle(array$p):void
{
    apply48ExactFile((string)$p['runtime']['pool_installed'],(string)$p['runtime']['pool_bundle'],'FPM poolconfig');if(!file_exists((string)$p['runtime']['socket']))throw new RuntimeException('Tenant FPM socket is niet actief.');
    foreach([[$p['apache']['tenant_http_enabled'],$p['apache']['tenant_http_available']],[$p['apache']['tenant_https_enabled'],$p['apache']['tenant_https_available']]]as[$l,$d])if(!apply48LinkExact((string)$l,(string)$d))throw new RuntimeException('Tenant Apache-site is niet actief.');
    apply48DbBinding($p);$v=apply48Pg('SELECT rolcanlogin::text FROM pg_roles WHERE rolname='.database45SqlLiteral((string)$p['database']['app_role']));if($v!=='true')throw new RuntimeException('Tenant database-role is niet LOGIN.');
    [$c]=apply48Run(['systemctl','is-enabled',(string)$p['monitoring']['timer_unit']]);if($c!==0)throw new RuntimeException('Tenant monitoring timer is niet enabled.');apply48Health($p);
}
function apply48SuspendedResources(array$p):void{apply48ApacheSuspend($p);if(apply48DbBestaat('role',(string)$p['database']['app_role']))apply48DbUit($p);apply48TimerUit($p);apply48FpmUit($p);}
function apply48TreeGeenSymlinks(string$root):void{if(is_link($root)||!is_dir($root))throw new RuntimeException('Tenantboom is geen veilige directory.');$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);foreach($it as$i)if(is_link($i->getPathname()))throw new RuntimeException('Symlink in tenantboom: '.$i->getPathname());}
function apply48DeleteBoundary(array$p,?array$tombstone=null):string
{
    $b=lifecycle481DeleteBinding($p,$tombstone);$base=(string)$b['tenant_base'];$root=(string)$b['tenant_root'];
    foreach([$base,$root]as$pad){$link=runtime41SymlinkInPad($pad);if($link!==null)throw new RuntimeException('Purgepad bevat symlink-ancestor: '.$link);}
    $baseReal=realpath($base);if($baseReal===false||!is_dir($baseReal)||!hash_equals(runtime41NormPad($base),runtime41NormPad($baseReal)))throw new RuntimeException('Tenantbasis voor purge kan niet exact symlinkvrij worden bewezen.');
    if(file_exists($root)||is_link($root)){
        if(is_link($root)||!is_dir($root))throw new RuntimeException('Tenantroot voor purge is geen veilige directory.');
        $rootReal=realpath($root);$expected=runtime41NormPad($baseReal.'/'.$b['tenant_key']);if($rootReal===false||!hash_equals($expected,runtime41NormPad($rootReal)))throw new RuntimeException('Tenantroot voor purge valt niet exact binnen de bewezen tenantbasis.');
        apply48TreeGeenSymlinks($root);
    }
    return$root;
}
function apply48Unlink(string$p,string$label):void{if(is_link($p))throw new RuntimeException($label.' mag geen symlink zijn: '.$p);if(is_file($p)){if(!@unlink($p))throw new RuntimeException($label.' kon niet worden verwijderd: '.$p);clearstatcache(true,$p);if(file_exists($p)||is_link($p))throw new RuntimeException($label.' bleef bestaan na verwijdering: '.$p);}elseif(file_exists($p))throw new RuntimeException($label.' is geen veilig regulier bestand: '.$p);}
function apply48RmStrict(string$p):void{if(is_link($p)){if(!@unlink($p))throw new RuntimeException('Symlink kon niet worden verwijderd: '.$p);return;}if(!file_exists($p))return;if(is_dir($p)){$items=scandir($p);if($items===false)throw new RuntimeException('Directory kon niet worden gelezen voor verwijdering: '.$p);foreach($items as$n){if($n==='.'||$n==='..')continue;apply48RmStrict($p.'/'.$n);}if(!@rmdir($p))throw new RuntimeException('Directory kon niet worden verwijderd: '.$p);}elseif(is_file($p)){if(!@unlink($p))throw new RuntimeException('Bestand kon niet worden verwijderd: '.$p);}else throw new RuntimeException('Onverwacht object tijdens lifecycleverwijdering: '.$p);clearstatcache(true,$p);if(file_exists($p)||is_link($p))throw new RuntimeException('Object bleef bestaan na lifecycleverwijdering: '.$p);}
function apply48RmBestEffort(string$p):void{try{apply48RmStrict($p);}catch(Throwable$ignored){}}
function apply48Export(array$p,array$s):array
{
    if($s['status']!=='suspended'||($s['transition']??null)!==null)throw new RuntimeException('Export vereist een stabiele suspended tenant.');apply48TreeGeenSymlinks((string)$p['filesystem']['tenant_root']);if(!apply48ProcessenStil($p))throw new RuntimeException('Export vereist nul tenant-runtimeprocessen.');
    $root=(string)$p['filesystem']['export_root'];apply48SafeDir(dirname($root),0700);apply48SafeDir($root,0700);$stamp=gmdate('Ymd_His').'-'.bin2hex(random_bytes(4));$stage=$root.'/.stage-'.$stamp;apply48SafeDir($stage,0700);
    try{$dbDump=$stage.'/database.dump';[$c,,$e]=apply48Run(['runuser','-u','postgres','--','pg_dump','-Fc','-d',(string)$p['database']['database']],null,$dbDump);if($c!==0)throw new RuntimeException('PostgreSQL export faalt: '.$e);apply48FileMeta($dbDump,0600);
        $fs=$stage.'/tenant-files.tar.gz';[$c,,$e]=apply48Run(['/usr/bin/tar','--create','--gzip','--file='.$fs,'--directory='.dirname((string)$p['filesystem']['tenant_root']),basename((string)$p['filesystem']['tenant_root'])]);if($c!==0)throw new RuntimeException('Tenant filesystem-export faalt: '.$e);apply48FileMeta($fs,0600);
        $manifest=['schema'=>1,'phase'=>'4.8-export','tenant_key'=>$p['tenant_key'],'created_at_utc'=>apply48Utc(),'state_generation'=>(int)$s['generation'],'database_sha256'=>hash_file('sha256',$dbDump),'tenant_files_sha256'=>hash_file('sha256',$fs)];apply48Write($stage.'/export-manifest.json',lifecycle48Json($manifest),0600);
        $pkg=$root.'/'.$stamp.'-tenant-export.tar.gz';if(file_exists($pkg)||is_link($pkg))throw new RuntimeException('Exportdoel bestaat onverwacht al.');[$c,,$e]=apply48Run(['/usr/bin/tar','--create','--gzip','--file='.$pkg,'--directory='.$stage,'database.dump','tenant-files.tar.gz','export-manifest.json']);if($c!==0)throw new RuntimeException('Exportpakket kon niet worden gemaakt: '.$e);apply48FileMeta($pkg,0600);$sha=hash_file('sha256',$pkg);if(!is_string($sha))throw new RuntimeException('Exportchecksum kon niet worden berekend.');return['path'=>$pkg,'sha256'=>$sha,'created_at_utc'=>$manifest['created_at_utc'],'state_generation'=>(int)$s['generation']];
    }finally{apply48RmBestEffort($stage);}
}
function apply48ExportControle(array$s,string$sha):array{$x=$s['last_export']??null;if(!is_array($x)||!preg_match('/^[0-9a-f]{64}$/D',(string)($x['sha256']??''))||!hash_equals((string)$x['sha256'],$sha))throw new RuntimeException('Bevestigde export-SHA hoort niet bij laatste lifecycle-export.');$p=(string)($x['path']??'');if(is_link($p)||!is_file($p)||!hash_equals($sha,(string)hash_file('sha256',$p)))throw new RuntimeException('Laatste lifecycle-export ontbreekt of checksum wijkt af.');return$x;}
function apply48PurgeInfra(array$p):void
{
    apply48TimerUit($p);apply48ApachePurge($p);
    if(apply48DbBestaat('role',(string)$p['database']['app_role']))apply48DbUit($p);if(is_file((string)$p['runtime']['pool_installed']))apply48FpmUit($p);elseif(!apply48ProcessenStil($p))throw new RuntimeException('Tenantprocessen zijn nog actief tijdens purge.');
    $db=(string)$p['database']['database'];$app=(string)$p['database']['app_role'];$owner=(string)$p['database']['owner_role'];if(apply48DbBestaat('database',$db)){if(!hash_equals((string)$p['database']['marker'],apply48DbMarker('database',$db)))throw new RuntimeException('Database-marker wijkt af bij purge.');apply48Pg('SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE pid<>pg_backend_pid() AND datname='.database45SqlLiteral($db));apply48Pg('DROP DATABASE '.$db);}foreach([[$app,'app'],[$owner,'owner']]as[$r,$label])if(apply48DbBestaat('role',$r)){if(!hash_equals((string)$p['database']['marker'],apply48DbMarker('role',$r)))throw new RuntimeException($label.'-role marker wijkt af bij purge.');apply48Pg('DROP ROLE '.$r);}
    $hba=(string)$p['database']['hba_file'];if(is_link($hba))throw new RuntimeException('Tenant HBA is een symlink.');if(is_file($hba)&&!@unlink($hba))throw new RuntimeException('Tenant HBA kon niet worden verwijderd.');if(apply48Pg('SELECT pg_reload_conf()')!=='t')throw new RuntimeException('PostgreSQL HBA reload faalde tijdens purge.');
    foreach([(string)$p['monitoring']['timer_file'],(string)$p['monitoring']['service_file'],(string)$p['monitoring']['tenant_logrotate'],(string)$p['monitoring']['health_status'],(string)$p['monitoring']['alert_state']]as$f)apply48Unlink($f,'Tenant monitoringartifact');[$dc,,$de]=apply48Run(['systemctl','daemon-reload']);if($dc!==0)throw new RuntimeException('systemd daemon-reload faalde tijdens tenantpurge: '.$de);
    foreach([(string)$p['apache']['tenant_http_available'],(string)$p['apache']['tenant_https_available'],(string)$p['apache']['routing_fragment']]as$f)apply48Unlink($f,'Apache tenantartifact');apply48ApacheTestReload($p);
    $renew=(string)$p['tls']['renewal_conf'];if(file_exists($renew)||is_dir('/etc/letsencrypt/live/'.(string)$p['tls']['cert_name'])){[$c,$o,$e]=apply48Run(['certbot','delete','--cert-name',(string)$p['tls']['cert_name'],'--non-interactive']);if($c!==0)throw new RuntimeException('Certbot tenantcertificaat kon niet veilig worden verwijderd: '.trim($o."\n".$e));}
    $acme=(string)$p['tls']['acme_webroot'];if(file_exists($acme)){if(runtime41SymlinkInPad($acme)!==null)throw new RuntimeException('ACME webroot is onveilig.');apply48RmStrict($acme);}
    $u=(string)$p['runtime']['user'];if(!apply48ProcessenStil($p))throw new RuntimeException('Tenant Linux-user heeft nog processen vóór userdel.');if(function_exists('posix_getpwnam')&&is_array(@posix_getpwnam($u))){[$c,,$e]=apply48Run(['userdel',$u]);if($c!==0||is_array(@posix_getpwnam($u)))throw new RuntimeException('Tenant system user kon niet worden verwijderd: '.$e);}if(function_exists('posix_getgrnam')&&is_array(@posix_getgrnam((string)$p['runtime']['group']))){[$c,,$e]=apply48Run(['groupdel',(string)$p['runtime']['group']]);if($c!==0||is_array(@posix_getgrnam((string)$p['runtime']['group'])))throw new RuntimeException('Tenant system group kon niet worden verwijderd: '.$e);}
}
function apply48Tombstone(array$p,array$data):void{apply48SafeDir((string)$p['filesystem']['tombstone_dir']);apply48Write((string)$p['filesystem']['tombstone_file'],lifecycle48Json(array_merge(['schema'=>1,'phase'=>'4.8-tombstone','tenant_key'=>$p['tenant_key']],$data)),0640);}
function apply48RecoveryMetaFile(string$p,string$label):void
{
    $link=runtime41SymlinkInPad($p);$s=@lstat($p);if($link!==null||!is_array($s)||!is_file($p)||(int)$s['uid']!==0||(int)$s['gid']!==0||(((int)$s['mode']&0777)!==0640))throw new RuntimeException($label.' ontbreekt, bevat een symlink-ancestor of heeft onveilige metadata.');
}
function apply48RecoveryMeta(string $tenant): array
{
    $snap='/var/lib/verenigingsplatform/lifecycle/plans/'.$tenant.'.json';$tomb='/var/lib/verenigingsplatform/lifecycle/tombstones/'.$tenant.'.json';
    apply48RecoveryMetaFile($snap,'Recovery plansnapshot');apply48RecoveryMetaFile($tomb,'Recovery tombstone');
    $pr=json_decode((string)file_get_contents($snap),true);$tb=json_decode((string)file_get_contents($tomb),true);if(!is_array($pr)||!is_array($tb))throw new RuntimeException('Recovery metadata bevat ongeldige JSON.');
    lifecycle481DeleteBinding($pr,$tb);
    $sha=hash_file('sha256',$snap);if(!is_string($sha)||!hash_equals($sha,(string)($tb['plan_snapshot_sha256']??'')))throw new RuntimeException('Recovery plansnapshot wijkt af van de purge-tombstone.');
    return[$pr,$tb,$snap];
}

foreach($_SERVER['argv']??[]as$a)if(preg_match('/^--(?:password|pass|secret|token|dsn|webhook|credential|pgpassword|pgpass)(?:=|$)/i',(string)$a)===1)apply48Stop('Secrets horen niet in fase-4.8 CLI-argumenten.');
$o=getopt('',['plan::','check','status','adopt-active','suspend','activate','recover','export','delete','cancel-delete','purge','recover-purge','tenant::','confirm-tenant::','confirm-export-sha::','confirm-purge::','help']);
if(isset($o['help'])){echo"Gebruik: php bin/apply-vps-lifecycle.php --plan=... --check | sudo php ... --adopt-active|--suspend|--activate|--recover|--export|--delete|--cancel-delete|--purge\nDefinitieve purge vereist --confirm-tenant=<key> --confirm-export-sha=<sha256> --confirm-purge=VERWIJDER-DEFINITIEF.\nRecovery na crash tijdens infrastructuur- of dataverwijdering: sudo php ... --recover-purge --tenant=<key>.\n";exit(0);}
$acties=['check','status','adopt-active','suspend','activate','recover','export','delete','cancel-delete','purge','recover-purge'];$gekozen=array_values(array_filter($acties,fn($a)=>isset($o[$a])));if(count($gekozen)!==1)apply48Stop('Kies exact één lifecycleactie.');$actie=$gekozen[0];
try{
    if($actie==='recover-purge'){
        apply48Root();$tenant=trim((string)($o['tenant']??''));if(!runtime41CanoniekeTenantKey($tenant))throw new RuntimeException('--tenant is verplicht en moet canoniek zijn.');[$pr,$tb,$snap]=apply48RecoveryMeta($tenant);apply48Deps($pr);$lock=apply48Lock($pr);$root=apply48DeleteBoundary($pr,$tb);$status=(string)($tb['status']??'');
        if($status==='purging_infrastructure'){apply48PurgeInfra($pr);apply48Tombstone($pr,array_merge($tb,['status'=>'data_delete','data_delete_started_at_utc'=>apply48Utc(),'tenant_root'=>$root]));$tb['status']='data_delete';$tb['tenant_root']=$root;}
        if(($tb['status']??'')!=='data_delete')throw new RuntimeException('recover-purge accepteert alleen purging_infrastructure of data_delete tombstones.');
        $root=apply48DeleteBoundary($pr,$tb);if(file_exists($root)){apply48RmStrict($root);if(file_exists($root))throw new RuntimeException('Tenantroot bleef bestaan na purge-recovery.');}
        apply48Tombstone($pr,array_merge($tb,['status'=>'deleted','completed_at_utc'=>apply48Utc()]));apply48Unlink((string)$pr['filesystem']['state_file'],'Lifecycle-state');apply48Audit($pr,'recover-purge','ok','pending_delete','deleted',['export_sha256'=>(string)($tb['export']['sha256']??'')]);apply48Unlink($snap,'Lifecycle plansnapshot');echo'RECOVER PURGE OK tenant='.$tenant."\n";exit(0);
    }
    $planPad=trim((string)($o['plan']??''));if($planPad==='')throw new RuntimeException('--plan is verplicht.');$ctx=lifecycle48PlanLeesEnValideer($planPad);$p=$ctx['plan'];if($actie==='check'){echo'CHECK OK tenant='.$p['tenant_key']."\n";exit(0);}apply48Root();if($actie!=='status')apply48Deps($p);$lock=apply48Lock($p);
    if($actie==='status'){$state=apply48StateLees($p,true);echo lifecycle48Json(['tenant_key'=>$p['tenant_key'],'status'=>$state['status']??'unmanaged','state'=>$state]);exit(0);}
    if(is_file((string)$p['filesystem']['tombstone_file']))throw new RuntimeException('Tenant heeft een lifecycle-tombstone; hergebruik/activatie wordt fail-closed geweigerd.');$raw=@file_get_contents($ctx['path']);if(!is_string($raw))throw new RuntimeException('Lifecycleplan kon niet voor rootsnapshot worden gelezen.');apply48BasisMappen($p,$raw);$state=apply48StateLees($p,true);
    if($actie==='adopt-active'){if($state!==null)throw new RuntimeException('Tenant heeft al lifecycle-state; adoptie is eenmalig.');apply48AdoptControle($p);$state=apply48NieuweState($p,'active',['adopted_at_utc'=>apply48Utc()]);apply48StateSchrijf($p,$state);apply48Audit($p,$actie,'ok','unmanaged','active',['generation'=>1]);echo'ADOPT OK tenant='.$p['tenant_key'].' status=active'."\n";exit(0);}
    if($state===null)throw new RuntimeException('Tenant is nog unmanaged; gebruik eerst --adopt-active.');
    if($actie==='recover'){if(($state['transition']??null)===null)throw new RuntimeException('Er is geen onafgeronde lifecycle-transition.');$from=$state['status'];apply48SuspendedResources($p);$state=apply48CommitState($p,$state,'suspended',['recovered_at_utc'=>apply48Utc()]);apply48Audit($p,$actie,'ok',$from,'suspended',['generation'=>$state['generation']]);echo'RECOVER OK tenant='.$p['tenant_key'].' status=suspended'."\n";exit(0);}
    if(($state['transition']??null)!==null)throw new RuntimeException('Er staat een onafgeronde lifecycle-transition; gebruik --recover.');
    if($actie==='suspend'){if($state['status']==='suspended'){echo'ONGEWIJZIGD tenant='.$p['tenant_key'].' status=suspended'."\n";exit(0);}if($state['status']!=='active')throw new RuntimeException('Alleen active tenant kan worden gesuspendeerd.');$state=apply48Transition($p,$state,'suspend');try{apply48SuspendedResources($p);$state=apply48CommitState($p,$state,'suspended',['suspended_at_utc'=>apply48Utc()]);apply48Audit($p,$actie,'ok','active','suspended',['generation'=>$state['generation']]);echo'SUSPEND OK tenant='.$p['tenant_key']."\n";}catch(Throwable$e){try{apply48Audit($p,$actie,'failed','active',null);}catch(Throwable$ignored){}throw$e;}exit(0);}
    if($actie==='activate'){if($state['status']==='active'){apply48AdoptControle($p);echo'ONGEWIJZIGD tenant='.$p['tenant_key'].' status=active'."\n";exit(0);}if($state['status']!=='suspended')throw new RuntimeException('Alleen suspended tenant kan worden geactiveerd.');$state=apply48Transition($p,$state,'activate');try{apply48FpmAan($p);apply48DbAan($p);apply48ApacheAan($p);apply48Health($p);apply48TimerAan($p);$state=apply48CommitState($p,$state,'active',['activated_at_utc'=>apply48Utc()]);apply48Audit($p,$actie,'ok','suspended','active',['generation'=>$state['generation']]);echo'ACTIVATE OK tenant='.$p['tenant_key']."\n";}catch(Throwable$e){try{apply48SuspendedResources($p);$state=apply48CommitState($p,$state,'suspended',['activation_failed_at_utc'=>apply48Utc()]);}catch(Throwable$ignored){}try{apply48Audit($p,$actie,'failed','suspended','suspended');}catch(Throwable$ignored){}throw$e;}exit(0);}
    if($actie==='export'){$x=apply48Export($p,$state);$state=apply48CommitState($p,$state,'suspended',['last_export'=>$x]);apply48Audit($p,$actie,'ok','suspended','suspended',['export_sha256'=>$x['sha256'],'generation'=>$state['generation']]);echo'EXPORT OK tenant='.$p['tenant_key'].' sha256='.$x['sha256']."\n";exit(0);}
    if($actie==='delete'){if($state['status']!=='suspended')throw new RuntimeException('Delete-aanvraag vereist suspended status.');$ct=trim((string)($o['confirm-tenant']??''));$cs=trim((string)($o['confirm-export-sha']??''));if(!hash_equals((string)$p['tenant_key'],$ct))throw new RuntimeException('--confirm-tenant moet exact de tenant-key zijn.');$x=apply48ExportControle($state,$cs);$notBefore=time()+(int)$p['lifecycle']['purge_grace_seconds'];$state=apply48CommitState($p,$state,'pending_delete',['delete_requested_at_utc'=>apply48Utc(),'purge_not_before_utc'=>gmdate('Y-m-d\TH:i:s\Z',$notBefore),'delete_export'=>$x]);apply48Audit($p,$actie,'ok','suspended','pending_delete',['export_sha256'=>$cs,'generation'=>$state['generation']]);echo'DELETE PENDING tenant='.$p['tenant_key'].' purge_not_before='.$state['purge_not_before_utc']."\n";exit(0);}
    if($actie==='cancel-delete'){if($state['status']!=='pending_delete')throw new RuntimeException('Er is geen pending_delete om te annuleren.');unset($state['delete_requested_at_utc'],$state['purge_not_before_utc'],$state['delete_export']);$state=apply48CommitState($p,$state,'suspended',['delete_cancelled_at_utc'=>apply48Utc()]);apply48Audit($p,$actie,'ok','pending_delete','suspended',['generation'=>$state['generation']]);echo'CANCEL DELETE OK tenant='.$p['tenant_key']."\n";exit(0);}
    if($actie==='purge'){if($state['status']!=='pending_delete')throw new RuntimeException('Purge vereist pending_delete status.');$ct=trim((string)($o['confirm-tenant']??''));$cs=trim((string)($o['confirm-export-sha']??''));$cp=trim((string)($o['confirm-purge']??''));if(!hash_equals((string)$p['tenant_key'],$ct)||!hash_equals('VERWIJDER-DEFINITIEF',$cp))throw new RuntimeException('Purge vereist exacte tenant- en definitieve bevestiging.');$x=apply48ExportControle($state,$cs);$nb=strtotime((string)($state['purge_not_before_utc']??''));if($nb===false||time()<$nb)throw new RuntimeException('Purge-wachttijd is nog niet verstreken.');$deleteRoot=apply48DeleteBoundary($p);$snapSha=hash_file('sha256',(string)$p['filesystem']['plan_snapshot_file']);if(!is_string($snapSha))throw new RuntimeException('Lifecycle plansnapshot checksum ontbreekt.');apply48Tombstone($p,['status'=>'purging_infrastructure','started_at_utc'=>apply48Utc(),'plan_snapshot_sha256'=>$snapSha,'export'=>$x]);apply48PurgeInfra($p);apply48Tombstone($p,['status'=>'data_delete','started_at_utc'=>apply48Utc(),'plan_snapshot_sha256'=>$snapSha,'export'=>$x,'tenant_root'=>$deleteRoot]);$deleteRoot=apply48DeleteBoundary($p,['schema'=>1,'phase'=>'4.8-tombstone','tenant_key'=>$p['tenant_key'],'status'=>'data_delete','tenant_root'=>$deleteRoot]);apply48RmStrict($deleteRoot);if(file_exists($deleteRoot))throw new RuntimeException('Tenantroot bleef bestaan na definitieve purge.');apply48Tombstone($p,['status'=>'deleted','completed_at_utc'=>apply48Utc(),'plan_snapshot_sha256'=>$snapSha,'export'=>$x]);apply48Unlink((string)$p['filesystem']['state_file'],'Lifecycle-state');apply48Audit($p,$actie,'ok','pending_delete','deleted',['export_sha256'=>$cs]);apply48Unlink((string)$p['filesystem']['plan_snapshot_file'],'Lifecycle plansnapshot');echo'PURGE OK tenant='.$p['tenant_key'].' DNS-records niet automatisch gewijzigd'."\n";exit(0);}
    throw new RuntimeException('Onbekende lifecycleactie.');
} catch(Throwable$e){apply48Stop($e->getMessage());}