<?php
$root = dirname(__DIR__);
require_once $root . '/app/core/tenant-runtime.php';
require_once $root . '/app/storage/private-store-migration-guard.php';
require_once $root . '/app/storage/private-store-runtime-state.php';
require_once $root . '/app/storage/private-json-pdo-migration.php';
require_once $root . '/app/storage/private-store-migration-operational-guard.php';

$ok = 0; $fout = 0;
function c211b(bool $cond, string $label): void { global $ok,$fout; if($cond){$ok++;echo "OK: {$label}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$label}\n");} }
function rr211b(string $p): void { if(is_link($p)||is_file($p)){@unlink($p);return;} if(!is_dir($p))return; foreach(scandir($p)?:[] as $i){if($i==='.'||$i==='..')continue;rr211b($p.DIRECTORY_SEPARATOR.$i);}@rmdir($p); }
function throws211b(callable $fn, string $needle=''): bool { try{$fn();return false;}catch(Throwable $e){return $needle===''||str_contains($e->getMessage(),$needle);} }
function pdo211b(): PDO { $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('CREATE TABLE vereniging_private_store (tenant_key VARCHAR(80) NOT NULL, collection_key VARCHAR(120) NOT NULL, payload TEXT NOT NULL, updated_at VARCHAR(40) NOT NULL, PRIMARY KEY (tenant_key, collection_key))');return $pdo; }

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) { fwrite(STDERR,"FOUT: pdo_sqlite is vereist voor de deterministische migratieregressie.\n"); exit(1); }
$tmp = sys_get_temp_dir().'/platform211b-'.bin2hex(random_bytes(5));
$private = $tmp.'/tenant/private';
@mkdir($private.'/collections',0750,true);
$tenant = 'migratie-test';
try {
    $alphaRaw = "{\n  \"z\": 1,\n  \"a\": {\"y\":2,\"x\":1}\n}\n";
    $betaRaw = "[\n  {\"id\":2,\"naam\":\"Bèta\"},\n  {\"id\":1,\"naam\":\"Alpha\"}\n]\n";
    file_put_contents($private.'/collections/alpha.json',$alphaRaw);
    file_put_contents($private.'/collections/beta.json',$betaRaw);

    $inv = privateMigrationInventory($private);
    c211b(array_keys($inv)===['alpha','beta'],'inventaris bevat exact alle private JSON-collecties');
    $reordered=['a'=>['x'=>1,'y'=>2],'z'=>1];
    c211b(hash_equals($inv['alpha']['canonical_sha256'],privateMigrationDataHash($reordered)),'canonieke hash negeert object-key volgorde maar niet documentinhoud');
    c211b($inv['beta']['count']===2,'inventaris bewaart top-level elementcount');

    $snapshot = privateMigrationSnapshot($private,$tenant,$inv);
    c211b(hash_equals(hash('sha256',$alphaRaw),(string)hash_file('sha256',$snapshot['source_dir'].'/alpha.json')),'snapshot bewaart raw JSON byte-identiek');

    $pdo = pdo211b();
    $import = privateMigrationImport($pdo,$tenant,$inv);
    c211b($import['status']==='imported'&&$import['comparison']['exact']===true,'lege PDO-target wordt transactioneel exact geïmporteerd');
    $target = privateMigrationTargetInventory($pdo,$tenant);
    c211b(privateMigrationVergelijk($inv,$target)['exact']===true,'bron/doel collectiekeys, counts en canonieke hashes zijn gelijk');

    $proof = privateMigrationProofSchrijf($tenant,$private,$snapshot,$inv,$import['comparison'],['config_sha256'=>str_repeat('a',64)]);
    $verified = privateMigrationProofControleer($pdo,$tenant,$private,$proof['path']);
    c211b($verified['rollback_safe']===true&&hash_equals($proof['sha256'],$verified['proof_sha256']),'proof verifieert snapshot, live JSON en PDO cryptografisch');

    $second = privateMigrationImport($pdo,$tenant,privateMigrationInventory($private));
    c211b($second['status']==='already-exact'&&$second['comparison']['exact']===true,'herhaalde import is idempotent wanneer target al exact gelijk is');

    $pdoMismatch = pdo211b();
    $stmt=$pdoMismatch->prepare('INSERT INTO vereniging_private_store VALUES (:t,:c,:p,:u)');
    $stmt->execute(['t'=>$tenant,'c'=>'alpha','p'=>'{"anders":true}','u'=>gmdate('c')]);
    c211b(throws211b(fn()=>privateMigrationImport($pdoMismatch,$tenant,$inv),'niet leeg en wijkt af'),'niet-leeg afwijkend PDO-target wordt zonder overwrite geweigerd');

    $pdoForeign = pdo211b();
    $stmt=$pdoForeign->prepare('INSERT INTO vereniging_private_store VALUES (:t,:c,:p,:u)');
    $stmt->execute(['t'=>'andere-tenant','c'=>'alpha','p'=>'{}','u'=>gmdate('c')]);
    c211b(throws211b(fn()=>privateMigrationTargetInventory($pdoForeign,$tenant),'andere tenant'),'foreign tenant-row in PDO-target faalt gesloten');

    $pdoRollback = pdo211b();
    $pdoRollback->exec("CREATE TRIGGER fail_beta BEFORE INSERT ON vereniging_private_store WHEN NEW.collection_key='beta' BEGIN SELECT RAISE(ABORT, 'forced failure'); END;");
    c211b(throws211b(fn()=>privateMigrationImport($pdoRollback,$tenant,$inv),'forced failure'),'geforceerde tweede-collectie DB-fout breekt import af');
    c211b((int)$pdoRollback->query('SELECT COUNT(*) FROM vereniging_private_store')->fetchColumn()===0,'geforceerde importfout rolt ook eerdere collectie volledig terug');

    $pdo->exec("UPDATE vereniging_private_store SET payload='[]' WHERE collection_key='alpha'");
    c211b(throws211b(fn()=>privateMigrationProofControleer($pdo,$tenant,$private,$proof['path']),'PDO-doel'),'rollback-check weigert zodra PDO sinds cutover is gewijzigd');
    $pdo->exec('DELETE FROM vereniging_private_store');
    privateMigrationImport($pdo,$tenant,$inv);
    file_put_contents($private.'/collections/alpha.json',"{\"gewijzigd\":true}\n");
    c211b(throws211b(fn()=>privateMigrationProofControleer($pdo,$tenant,$private,$proof['path']),'Live JSON-bron'),'rollback-check weigert zodra bewaarde JSON-bron is gewijzigd');
    file_put_contents($private.'/collections/alpha.json',$alphaRaw);

    $proofRaw=file_get_contents($proof['path']);
    file_put_contents($proof['path'],str_replace('"status": "verified"','"status": "tampered"',(string)$proofRaw));
    c211b(throws211b(fn()=>privateMigrationProofLees($proof['path'],$private,$tenant),'contractversie'),'proof-tampering wordt geweigerd');
    file_put_contents($proof['path'],$proofRaw);

    file_put_contents($private.'/collections/onverwacht.tmp','x');
    c211b(throws211b(fn()=>privateMigrationInventory($private),'onverwachte bestandsnaam'),'onverwacht object in collections faalt inventaris gesloten');
    unlink($private.'/collections/onverwacht.tmp');
    if (function_exists('symlink')) {
        @symlink($private.'/collections/alpha.json',$private.'/collections/link.json');
        if (is_link($private.'/collections/link.json')) {
            c211b(throws211b(fn()=>privateMigrationInventory($private),'symlink'),'symlink in private collecties wordt geweigerd');
            unlink($private.'/collections/link.json');
        }
    }

    // Runtime-state: alleen een intact proof mag een JSON-config effectief PDO maken.
    $tenantRoot=$tmp.'/tenant';$runtimeDir=$tenantRoot.'/storage-runtime';@mkdir($runtimeDir,0750,true);
    $configPad=$tenantRoot.'/config.php';file_put_contents($configPad,"<?php return [];\n");@chmod($configPad,0640);
    putenv('VERENIGING_CONFIG_FILE='.$configPad);
    $state=[
        'schema'=>1,'phase'=>'json-to-pdo-cutover','tenant_key'=>$tenant,'source_driver'=>'json','effective_driver'=>'pdo','created_at'=>gmdate('c'),
        'proof_path'=>$proof['path'],'proof_sha256'=>hash_file('sha256',$proof['path']),'target_aggregate_sha256'=>$proof['data']['target']['aggregate_sha256'],
    ];
    file_put_contents($runtimeDir.'/private-store-runtime.json',json_encode($state,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));@chmod($runtimeDir.'/private-store-runtime.json',0640);
    $cfg=['vereniging'=>['sleutel'=>$tenant],'opslag'=>['private_driver'=>'json','private_root'=>$private]];
    c211b(privateStoreEffectiveDriver($cfg,'json')==='pdo','intact root-runtime-state contract activeert effectief PDO boven JSON-provisioning');
    $state['proof_sha256']=str_repeat('0',64);file_put_contents($runtimeDir.'/private-store-runtime.json',json_encode($state));@chmod($runtimeDir.'/private-store-runtime.json',0640);
    c211b(throws211b(fn()=>privateStoreEffectiveDriver($cfg,'json'),'migratiebewijs'),'runtime-state met verkeerde proofhash faalt gesloten');
    unlink($runtimeDir.'/private-store-runtime.json');

    // Write barrier: marker wordt na shared-lock-acquisitie gecontroleerd.
    $lock=$runtimeDir.'/private-store-migration.lock';file_put_contents($lock,'');@chmod($lock,0660);
    privateStoreMigrationWriteGuardEnter();privateStoreMigrationWriteGuardLeave();
    c211b(true,'write guard laat normale writewindow zonder marker door');
    file_put_contents($runtimeDir.'/private-store-migration.active','active');
    c211b(throws211b(fn()=>privateStoreMigrationWriteGuardEnter(),'tijdelijk alleen-lezen'),'active-marker blokkeert nieuwe private-store writes fail-closed');
    unlink($runtimeDir.'/private-store-migration.active');

    // Downstream operationele plannen moeten aan het actuele migration-target databaseplan zijn gebonden.
    $opsRoot=$tmp.'/ops-tenant';@mkdir($opsRoot.'/database',0750,true);
    $opsTenant='ops-test';$dbPlan=$opsRoot.'/database/database-plan.json';
    $none=privateMigrationOperationalPlansValidate($opsRoot,$opsTenant);
    c211b($none===['monitoring'=>'absent','lifecycle'=>'absent'],'migratieguard laat tenant zonder bestaande monitoring/lifecycleplannen door');

    @mkdir($opsRoot.'/monitoring',0750,true);file_put_contents($opsRoot.'/monitoring/monitoring-plan.json',"{}\n");
    $monitoringActueel=fn(string $pad):array=>['plan'=>['tenant_key'=>$opsTenant,'source'=>['database_plan_file'=>$dbPlan]]];
    $monitoringStale=fn(string $pad):array=>['plan'=>['tenant_key'=>$opsTenant,'source'=>['database_plan_file'=>$opsRoot.'/database/oude-database-plan.json']]];
    $lifecycleOngebruikt=fn(string $pad):array=>['plan'=>[]];
    c211b(throws211b(fn()=>privateMigrationOperationalPlansValidate($opsRoot,$opsTenant,$monitoringStale,$lifecycleOngebruikt),'actuele migration-target databaseplan'),'stale monitoringbinding blokkeert storage-migratie fail-closed');
    $alleenMonitoring=privateMigrationOperationalPlansValidate($opsRoot,$opsTenant,$monitoringActueel,$lifecycleOngebruikt);
    c211b($alleenMonitoring===['monitoring'=>'valid','lifecycle'=>'absent'],'opnieuw gebonden monitoringplan laat migratiepreflight door');

    @mkdir($opsRoot.'/lifecycle',0750,true);file_put_contents($opsRoot.'/lifecycle/lifecycle-plan.json',"{}\n");
    $lifecycleStale=fn(string $pad):array=>['plan'=>['tenant_key'=>$opsTenant,'source'=>['database_plan_file'=>$opsRoot.'/database/oude-database-plan.json','monitoring_plan_file'=>$opsRoot.'/monitoring/monitoring-plan.json']]];
    $lifecycleActueel=fn(string $pad):array=>['plan'=>['tenant_key'=>$opsTenant,'source'=>['database_plan_file'=>$dbPlan,'monitoring_plan_file'=>$opsRoot.'/monitoring/monitoring-plan.json']]];
    c211b(throws211b(fn()=>privateMigrationOperationalPlansValidate($opsRoot,$opsTenant,$monitoringActueel,$lifecycleStale),'actuele monitoring/databaseplannen'),'monitoring vernieuwd maar stale lifecyclebinding blokkeert migratie');
    $beideActueel=privateMigrationOperationalPlansValidate($opsRoot,$opsTenant,$monitoringActueel,$lifecycleActueel);
    c211b($beideActueel===['monitoring'=>'valid','lifecycle'=>'valid'],'monitoring en lifecycle opnieuw gebonden aan migration-target worden geaccepteerd');
    unlink($opsRoot.'/monitoring/monitoring-plan.json');
    c211b(throws211b(fn()=>privateMigrationOperationalPlansValidate($opsRoot,$opsTenant,$monitoringActueel,$lifecycleActueel),'zonder actueel monitoringplan'),'lifecycleplan zonder monitoringplan wordt fail-closed geweigerd');

    $coordinatorRaw=(string)file_get_contents($root.'/bin/apply-private-store-migration.php');
    c211b(str_contains($coordinatorRaw,"privateMigrationOperationalPlansValidate(\$tenantRoot, \$tenant);"),'productiecoordinator voert operationele-planbindingcontrole vóór cutover uit');
} finally {
    putenv('VERENIGING_CONFIG_FILE');
    rr211b($tmp);
}

echo "Platform #211 JSON→PDO migratie: {$ok} OK, {$fout} fout(en)\n";
exit($fout===0?0:1);
