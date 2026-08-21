<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function c45(bool $c,string $l):void{global$ok,$fout;if($c){$ok++;echo"OK: $l\n";}else{$fout++;fwrite(STDERR,"FOUT: $l\n");}}
function rr45(string $p):void{if(is_link($p)||is_file($p)){@unlink($p);return;}if(!is_dir($p))return;foreach(scandir($p)?:[]as$i){if($i==='.'||$i==='..')continue;rr45($p.DIRECTORY_SEPARATOR.$i);}@rmdir($p);}
function run45(array $a,?string$stdin=null):array{$d=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];$p=proc_open($a,$d,$x,null,null,['bypass_shell'=>true]);if(!is_resource($p))return[255,'proc_open mislukt'];if($stdin!==null)fwrite($x[0],$stdin);fclose($x[0]);$o=stream_get_contents($x[1]);fclose($x[1]);$e=stream_get_contents($x[2]);fclose($x[2]);return[proc_close($p),trim((string)$o."\n".(string)$e)];}
function tenant45(string$r,string$b,string$k,string$driver='pdo'):array{
 $t=$b.'/'.$k;$p=run45([PHP_BINARY,$r.'/bin/provision-tenant.php','--key='.$k,'--name=Database '.ucfirst($k),'--url=https://'.$k.'.example','--root='.$b,'--driver='.$driver,'--modules=website,ledenadministratie']);
 if($p[0]!==0)return[$t,false,$p[1]];
 $boot=run45([PHP_BINARY,$r.'/bin/bootstrap-tenant-admin.php','--config='.$t.'/config.php','--password-stdin'],'Database-'.$k.'-Admin-2026!' . "\n");if($boot[0]!==0)return[$t,false,$boot[1]];
 $dep=run45([PHP_BINARY,$r.'/bin/prepare-vps-deployment.php','--config='.$t.'/config.php','--app-root='.$r]);if($dep[0]!==0)return[$t,false,$dep[1]];
 $runtime=run45([PHP_BINARY,$r.'/bin/prepare-vps-runtime.php','--deployment='.$t.'/deployment.json']);return[$t,$runtime[0]===0,$runtime[1]];
}

$tmp=sys_get_temp_dir().'/rc045-phase45-'.bin2hex(random_bytes(5));$base=$tmp.'/tenants';@mkdir($base,0750,true);
try{
 [$a,$aOk,$aOut]=tenant45($root,$base,'noorderhaven');[$b,$bOk,$bOut]=tenant45($root,$base,'duinrand');
 c45($aOk&&$bOk,'twee PDO-tenants doorlopen provisioning, admin, deployment en runtime als fase-4.5 bron');
 $rA=$a.'/runtime/runtime-plan.json';$rB=$b.'/runtime/runtime-plan.json';
 [$dryCode,$dryOut]=run45([PHP_BINARY,$root.'/bin/prepare-vps-database.php','--runtime-plan='.$rA,'--dry-run']);$dry=json_decode($dryOut,true);
 c45($dryCode===0&&is_array($dry)&&($dry['phase']??'')==='4.5'&&!is_dir($a.'/database'),'database dry-run schrijft niets');
 c45(($dry['isolation']['one_database_per_tenant']??false)===true&&($dry['isolation']['app_role_equals_os_user']??false)===true,'isolatiemodel is één database per tenant en DB-login is exact de FPM Linux-user');
 c45(($dry['isolation']['owner_role_login']??true)===false&&($dry['isolation']['app_role_owns_database']??true)===false&&($dry['isolation']['app_role_ddl_forbidden']??false)===true,'aparte NOLOGIN owner houdt DDL buiten de app-role');
 c45(($dry['connection']['auth_method']??'')==='peer'&&($dry['connection']['password_required']??true)===false&&($dry['connection']['unix_socket_dir']??'')==='/var/run/postgresql','productieverbinding gebruikt uitsluitend lokale Unix-socket peer-auth zonder password');
 c45(($dry['postgresql']['minimum_major_version']??0)===16&&($dry['postgresql']['hba_allow_own_database_only']??false)===true&&($dry['postgresql']['hba_reject_other_databases_for_tenant_user']??false)===true,'PostgreSQL 16+ en expliciete allow-own/reject-other HBA zijn contractueel verplicht');
 c45(($dry['security']['no_database_secret_in_git']??false)===true&&($dry['security']['no_database_secret_in_runtime_bundle']??false)===true&&($dry['filesystem']['database_secrets_file']??'x')===null,'databasecontract serializeert bewust geen secretbestand of wachtwoord');
 c45(($dry['schema_contract']['schema_name']??'')==='vst'&&($dry['schema_contract']['schema_version']??0)===1&&($dry['schema_contract']['runtime_ddl_forbidden']??false)===true,'schema v1 wordt via provisioning beheerd en runtime-DDL is verboden');

 [$prepA,$outA]=run45([PHP_BINARY,$root.'/bin/prepare-vps-database.php','--runtime-plan='.$rA]);[$prepB,$outB]=run45([PHP_BINARY,$root.'/bin/prepare-vps-database.php','--runtime-plan='.$rB]);
 $pA=$a.'/database/database-plan.json';$pB=$b.'/database/database-plan.json';$jA=is_file($pA)?json_decode((string)file_get_contents($pA),true):null;$jB=is_file($pB)?json_decode((string)file_get_contents($pB),true):null;
 c45($prepA===0&&$prepB===0&&is_array($jA)&&is_array($jB),'fase-4.5 bundles worden voor twee tenants geschreven');
 c45(($jA['isolation']['database']??'')!==($jB['isolation']['database']??'')&&($jA['isolation']['owner_role']??'')!==($jB['isolation']['owner_role']??''),'database en NOLOGIN owner zijn per tenant uniek');
 $runtimeA=json_decode((string)file_get_contents($a.'/database/database-runtime.json'),true);$runtimeB=json_decode((string)file_get_contents($b.'/database/database-runtime.json'),true);
 c45(is_array($runtimeA)&&($runtimeA['user']??'')===($jA['isolation']['app_role']??'')&&($runtimeA['database']??'')===($jA['isolation']['database']??''),'runtimebestand bindt exact aan eigen app-role en database');
 c45(!array_key_exists('password',$runtimeA??[])&&!array_key_exists('secret',$runtimeA??[])&&($runtimeA['password_required']??true)===false,'database-runtime bevat geen password/secret');
 c45(($runtimeA['dsn']??'')==='pgsql:host=/var/run/postgresql;dbname='.($jA['isolation']['database']??''),'DSN is exact lokale PostgreSQL socket + eigen database');
 c45(($runtimeA['database']??'')!==($runtimeB['database']??'')&&($runtimeA['user']??'')!==($runtimeB['user']??''),'twee runtimebestanden kunnen niet naar dezelfde database/login wijzen');

 $hba=(string)file_get_contents($a.'/database/'.($jA['postgresql']['tenant_hba_filename']??''));
 c45(str_contains($hba,'local '.($jA['isolation']['database']??'').' '.($jA['isolation']['app_role']??'').' peer'),'HBA staat eigen database voor eigen kernel/DB-user via peer toe');
 c45(str_contains($hba,'local all '.($jA['isolation']['app_role']??'').' reject'),'HBA weigert dezelfde tenantuser daarna iedere andere database');
 c45(!str_contains(strtolower($hba),'trust')&&!str_contains(strtolower($hba),'scram')&&!str_contains(strtolower($hba),'password'),'tenant HBA bevat geen trust of passwordauth');

 $sql=(string)file_get_contents($a.'/database/001-private-store.sql');
 c45(str_contains($sql,'CREATE SCHEMA IF NOT EXISTS vst AUTHORIZATION '.($jA['isolation']['owner_role']??''))&&str_contains($sql,'ALTER TABLE vst.vereniging_private_store OWNER TO '.($jA['isolation']['owner_role']??'')),'schema en tabel blijven eigendom van NOLOGIN owner');
 c45(str_contains($sql,'REVOKE ALL ON SCHEMA public FROM PUBLIC')&&str_contains($sql,'REVOKE ALL ON SCHEMA vst FROM PUBLIC'),'PUBLIC krijgt geen public- of tenant-schemarechten');
 c45(str_contains($sql,'GRANT USAGE ON SCHEMA vst TO '.($jA['isolation']['app_role']??''))&&str_contains($sql,'GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE vst.vereniging_private_store TO '.($jA['isolation']['app_role']??'')),'app-role krijgt alleen benodigde schema-USAGE en DML');
 c45(!str_contains($sql,'GRANT CREATE')&&!str_contains($sql,'GRANT ALL'),'migratie verleent geen CREATE/ALL aan app-role');
 c45(str_contains($sql,"VALUES ('private_store', 1, 'noorderhaven')"),'database bevat vaste tenantmarker + schemaversie');

 $perm=fileperms($pA);c45($perm!==false&&(($perm&0777)===0640),'database-plan.json krijgt server-only 0640');
 [$checkCode,$checkOut]=run45([PHP_BINARY,$root.'/bin/apply-vps-database.php','--database-plan='.$pA,'--check']);c45($checkCode===0&&str_contains($checkOut,'bundle valide'),'root-vrije database --check valideert plan en artifacts');
 [$idemCode,$idemOut]=run45([PHP_BINARY,$root.'/bin/prepare-vps-database.php','--runtime-plan='.$rA]);c45($idemCode===0&&str_contains($idemOut,'ONGEWIJZIGD'),'identieke databasegeneratie is idempotent');

 $runtimePad=$a.'/database/database-runtime.json';$runtimeOrig=(string)file_get_contents($runtimePad);file_put_contents($runtimePad,$runtimeOrig."\n");
 [$tamperCode,$tamperOut]=run45([PHP_BINARY,$root.'/bin/apply-vps-database.php','--database-plan='.$pA,'--check']);c45($tamperCode!==0&&str_contains($tamperOut,'wijkt af'),'bundlecheck weigert gemanipuleerd database-runtime artifact');
 c45(run45([PHP_BINARY,$root.'/bin/prepare-vps-database.php','--runtime-plan='.$rA,'--force'])[0]===0,'--force herstelt tenant-lokale databasebundle uit broncontract');

 [$secretCode,$secretOut]=run45([PHP_BINARY,$root.'/bin/prepare-vps-database.php','--runtime-plan='.$rA,'--password=verboden']);c45($secretCode!==0&&str_contains($secretOut,'credentials/secrets'),'generator weigert secretachtige CLI-argumenten');
 [$applySecretCode,$applySecretOut]=run45([PHP_BINARY,$root.'/bin/apply-vps-database.php','--database-plan='.$pA,'--check','--dsn=verboden']);c45($applySecretCode!==0&&str_contains($applySecretOut,'credentials/secrets'),'apply-tool weigert DSN/secret via CLI');

 [$jsonTenant,$jsonOk]=tenant45($root,$base,'jsonclub','json');
 [$jsonCode,$jsonOut]=run45([PHP_BINARY,$root.'/bin/prepare-vps-database.php','--runtime-plan='.$jsonTenant.'/runtime/runtime-plan.json','--dry-run']);
 c45($jsonOk&&$jsonCode!==0&&str_contains($jsonOut,'private_driver=pdo'),'fase 4.5 weigert JSON-tenant in plaats van storage stil om te schakelen');

 require_once $root.'/app/storage/pdo-runtime.php';
 putenv('VERENIGING_CONFIG_FILE='.$a.'/config.php');
 try{$metaA=pdoRuntime45ServerConfig('noorderhaven');c45(is_array($metaA)&&($metaA['auth_method']??'')==='peer'&&($metaA['password_required']??true)===false,'app-runtime leest vaste tenantgebonden peer metadata zonder secret');}catch(Throwable$e){c45(false,'app-runtime leest vaste tenantgebonden peer metadata zonder secret');}
 $origRuntime=(string)file_get_contents($a.'/database/database-runtime.json');file_put_contents($a.'/database/database-runtime.json',(string)file_get_contents($b.'/database/database-runtime.json'));@chmod($a.'/database/database-runtime.json',0640);
 try{pdoRuntime45ServerConfig('noorderhaven');c45(false,'gekopieerd database-runtimebestand van andere tenant wordt geweigerd');}catch(Throwable$e){c45(str_contains($e->getMessage(),'contract'),'gekopieerd database-runtimebestand van andere tenant wordt geweigerd');}
 file_put_contents($a.'/database/database-runtime.json',$origRuntime);@chmod($a.'/database/database-runtime.json',0640);putenv('VERENIGING_CONFIG_FILE');

 $store=(string)file_get_contents($root.'/app/storage/private-store.php');$pdoRuntime=(string)file_get_contents($root.'/app/storage/pdo-runtime.php');$apply=(string)file_get_contents($root.'/bin/apply-vps-database.php');$check=(string)file_get_contents($root.'/bin/check-vps-database.php');
 c45(str_contains($store,"'source'=>'phase45-peer'")&&str_contains($store,'pdoRuntime45ValideerSchema')&&str_contains($store,'privateStoreEnsureSchema($pdo)'),'private-store gebruikt strikte fase-4.5 validatie maar behoudt legacy PDO-compatibiliteit');
 c45(str_contains($pdoRuntime,'SET search_path TO vst, pg_catalog')&&str_contains($pdoRuntime,'vereniging_schema_meta')&&str_contains($pdoRuntime,'schema_version'),'serverruntime valideert expliciet DB-identiteit, tenantmarker en schema');
 c45(!str_contains($pdoRuntime,'CREATE TABLE')&&!str_contains($pdoRuntime,'CREATE SCHEMA'),'fase-4.5 webrequest-runtime voert geen DDL uit');
 c45(str_contains($apply,'server_version_num')&&str_contains($apply,'160000')&&str_contains($apply,'pg_hba_file_rules'),'root-apply vereist PostgreSQL 16+ en preflight HBA-parserregels');
 c45(str_contains($apply,"SHOW listen_addresses")&&str_contains($apply,"listen_addresses=''"),'root-apply vereist socket-only PostgreSQL zonder TCP-listener');
 c45(str_contains($apply,"'runuser', '-u', 'postgres'")&&str_contains($apply,'function apply45PeerCheck'),'adminacties lopen via postgres OS-user en tenant-login heeft een aparte peercheck');
 c45(str_contains($apply,'PASSWORD NULL')&&!str_contains($apply,'PGPASSWORD'),'app-role heeft expliciet geen PostgreSQL password en tool gebruikt geen PGPASSWORD');
 c45(str_contains($apply,"local all")===false&&str_contains($apply,'database45HbaConfig'),'HBA-inhoud komt uitsluitend uit het gevalideerde pure contract');
 c45(str_contains($apply,'strpos(file_name')&&str_contains($apply,'eersteBuitenPlatform'),'HBA ordering vergelijkt tenantregels met eerste niet-platformregel en ondersteunt meerdere tenant-HBA-bestanden');
 c45(str_contains($apply,"'-d', 'postgres'")&&str_contains($apply,'Cross-database HBA-reject'),'apply-tool bewijst dat tenant OS-user niet naar postgres/andere database kan uitwijken');
 c45(str_contains($apply,'REVOKE ALL ON DATABASE')&&str_contains($apply,'has_schema_privilege')&&str_contains($apply,"'CREATE')::text"),'root-apply controleert least-privilege database- en schemarechten');
 c45(str_contains($check,'posix_geteuid()')&&str_contains($check,"extension_loaded('pdo_pgsql')"),'runtimecheck moet als exact tenant Linux-user met pdo_pgsql draaien');
 c45(str_contains($check,'beginTransaction()')&&str_contains($check,'rollBack()')&&str_contains($check,'CREATE TABLE vst.__phase45_ddl_probe'),'runtimecheck test DML rollback-safe en verwacht DDL-weigering');
}finally{putenv('VERENIGING_CONFIG_FILE');rr45($tmp);}
echo"Phase 4.5 database provisioning: $ok OK, $fout fout(en)\n";exit($fout===0?0:1);