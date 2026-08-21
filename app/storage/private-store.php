<?php
// ============================================================
// Tenant-aware private storage abstraction
// ============================================================
require_once dirname(__DIR__) . '/core/tenant-runtime.php';
require_once __DIR__ . '/tenant-backup-store.php';
require_once __DIR__ . '/pdo-runtime.php';

function privateStoreConfig(): array{static$config=null;if($config===null){$geladen=require dirname(__DIR__,2).'/site-config.php';$config=is_array($geladen)?$geladen:[];}return$config;}
function privateStoreTenant(): string{$config=privateStoreConfig();return tenantRuntimeVeiligeSleutel((string)($config['vereniging']['sleutel']??'default'));}
function privateStoreDriver(): string{$config=privateStoreConfig();$driver=strtolower(trim((string)($config['opslag']['private_driver']??'json')));return$driver==='pdo'?'pdo':'json';}
function privateStoreJsonRoot(): ?string{return tenantRuntimePrivateRoot(privateStoreConfig());}
function privateStoreBackupSleutel(string $collectie): string{return'private-'.tenantRuntimeCollectieSleutel($collectie);}

/**
 * Legacy JSON/PHP fallback is uitsluitend bedoeld voor de bestaande losse
 * RC045-installatie tijdens de migratie. Zodra een tenant expliciet via een
 * extern configbestand draait, of een eigen private_root heeft, is terugvallen
 * op projectrootdata verboden. Een ontbrekende PDO-collectie betekent dan
 * bewust: deze tenant heeft nog geen data.
 */
function privateStoreLegacyFallbackToegestaan(): bool
{
    $externConfig = trim((string)(getenv('VERENIGING_CONFIG_FILE') ?: ''));
    return $externConfig === '' && privateStoreJsonRoot() === null;
}

function privateStoreJsonLees(string $collectie): array
{
    $root=privateStoreJsonRoot();if($root===null)return[];$pad=tenantRuntimeCollectiePad($root,$collectie);
    if(!is_file($pad))return[];
    $ruw=@file_get_contents($pad);if($ruw===false)throw new RuntimeException('Private tenantopslag kon niet worden gelezen.');
    $data=json_decode($ruw,true);if(json_last_error()!==JSON_ERROR_NONE||!is_array($data)){
        error_log('[platform] ongeldige tenant JSON voor '.privateStoreTenant().', collectie '.$collectie);
        throw new RuntimeException('Private tenantopslag bevat ongeldige data.');
    }
    return$data;
}

function privateStoreJsonSchrijf(string $collectie,array $data): bool
{
    $root=privateStoreJsonRoot();if($root===null)return false;$pad=tenantRuntimeCollectiePad($root,$collectie);$map=dirname($pad);
    if(!is_dir($map)&&!@mkdir($map,0750,true))throw new RuntimeException('Private tenantopslag kon niet worden aangemaakt.');
    $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);if($json===false)return false;
    try{$suffix=bin2hex(random_bytes(5));}catch(Throwable $e){$suffix=str_replace('.','',(string)microtime(true));}
    $tmp=$pad.'.tmp.'.$suffix;
    if(@file_put_contents($tmp,$json,LOCK_EX)===false)return false;
    @chmod($tmp,0640);
    if(!@rename($tmp,$pad)){@unlink($tmp);return false;}
    @chmod($pad,0640);
    return true;
}

/**
 * Resolutievolgorde voor PDO:
 * 1. bestaande expliciete DSN in serverconfig (legacy/standalone compatibiliteit);
 * 2. bestaande VERENIGING_DB_* environment (legacy compatibiliteit);
 * 3. fase 4.5 serverruntime: vast database-runtime.json + lokale peer-auth,
 *    uitsluitend voor een externe tenant met lege DSN/user/password.
 */
function privateStorePdoVerbindingsdata(): array
{
    $config=privateStoreConfig();
    $pdoConfig=$config['opslag']['pdo']??[];if(!is_array($pdoConfig))$pdoConfig=[];
    $dsnConfig=trim((string)($pdoConfig['dsn']??''));
    $dsnEnv=trim((string)(getenv('VERENIGING_DB_DSN')?:''));
    $userConfig=(string)($pdoConfig['user']??'');
    $passConfig=(string)($pdoConfig['password']??'');
    $userEnv=(string)(getenv('VERENIGING_DB_USER')?:'');
    $passEnv=(string)(getenv('VERENIGING_DB_PASSWORD')?:'');

    if($dsnConfig!=='')return['dsn'=>$dsnConfig,'user'=>$userConfig!==''?$userConfig:$userEnv,'password'=>$passConfig!==''?$passConfig:$passEnv,'source'=>'config','runtime'=>null];
    if($dsnEnv!=='')return['dsn'=>$dsnEnv,'user'=>$userConfig!==''?$userConfig:$userEnv,'password'=>$passConfig!==''?$passConfig:$passEnv,'source'=>'env','runtime'=>null];

    $extern=trim((string)(getenv('VERENIGING_CONFIG_FILE')?:''));
    if($extern!==''){
        if($userConfig!==''||$passConfig!==''||$userEnv!==''||$passEnv!==''){
            throw new RuntimeException('Fase-4.5 PDO-tenant mag geen losse databaseuser/password naast peer-runtime bevatten.');
        }
        $runtime=pdoRuntime45ServerConfig(privateStoreTenant());
        if(is_array($runtime))return['dsn'=>(string)$runtime['dsn'],'user'=>(string)$runtime['user'],'password'=>'','source'=>'phase45-peer','runtime'=>$runtime];
    }

    throw new RuntimeException('PDO-driver geselecteerd zonder DSN of fase-4.5 database-runtime.');
}

function privateStorePdo(): PDO
{
    static$pdo=null;static$fout=null;if($pdo instanceof PDO)return$pdo;if($fout instanceof Throwable)throw new RuntimeException('Private verenigingsopslag is tijdelijk niet beschikbaar.',0,$fout);
    try{
        $verbinding=privateStorePdoVerbindingsdata();
        $pdo=new PDO((string)$verbinding['dsn'],(string)$verbinding['user'],(string)$verbinding['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        try{$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);}catch(Throwable $ignored){}
        if(($verbinding['source']??'')==='phase45-peer'){
            pdoRuntime45ValideerSchema($pdo,(array)$verbinding['runtime'],privateStoreTenant());
        }else{
            privateStoreEnsureSchema($pdo);
        }
        return$pdo;
    }
    catch(Throwable $e){
        if(str_contains($e->getMessage(),'zonder DSN of fase-4.5'))error_log('[platform] private PDO: driver=pdo maar geen veilige verbinding geconfigureerd');
        else error_log('[platform] private PDO niet beschikbaar: '.get_class($e).' · '.$e->getMessage());
        $fout=$e;throw new RuntimeException('Private verenigingsopslag is tijdelijk niet beschikbaar.',0,$e);
    }
}

/** Alleen legacy/direct PDO mag nog een minimaal schema lazy aanmaken. Fase 4.5 PostgreSQL doet nooit DDL vanuit een webrequest. */
function privateStoreEnsureSchema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS vereniging_private_store (tenant_key VARCHAR(80) NOT NULL, collection_key VARCHAR(120) NOT NULL, payload TEXT NOT NULL, updated_at VARCHAR(40) NOT NULL, PRIMARY KEY (tenant_key, collection_key))');
}

function privateStoreTransactie(callable $callback)
{
    if(privateStoreDriver()!=='pdo')return$callback();$pdo=privateStorePdo();$eigen=!$pdo->inTransaction();if($eigen)$pdo->beginTransaction();
    try{$resultaat=$callback();if($eigen)$pdo->commit();return$resultaat;}catch(Throwable $e){if($eigen&&$pdo->inTransaction())$pdo->rollBack();throw$e;}
}
function privateStoreLees(string $collectie,callable $jsonLezer): array
{
    $collectie=trim($collectie);if($collectie==='')return[];
    if(privateStoreDriver()!=='pdo'){
        if(privateStoreJsonRoot()!==null)return privateStoreJsonLees($collectie);
        $data=$jsonLezer();return is_array($data)?$data:[];
    }
    $pdo=privateStorePdo();
    try{$stmt=$pdo->prepare('SELECT payload FROM vereniging_private_store WHERE tenant_key = :tenant AND collection_key = :collection');$stmt->execute(['tenant'=>privateStoreTenant(),'collection'=>$collectie]);$rij=$stmt->fetch();}
    catch(Throwable $e){error_log('[platform] private store read mislukt voor '.$collectie.': '.$e->getMessage());throw new RuntimeException('Private verenigingsopslag kon niet worden gelezen.',0,$e);}
    if(!$rij){
        if(!privateStoreLegacyFallbackToegestaan())return[];
        $fallback=$jsonLezer();return is_array($fallback)?$fallback:[];
    }
    $payload=(string)($rij['payload']??'');$data=json_decode($payload,true);if(json_last_error()!==JSON_ERROR_NONE||!is_array($data)){error_log('[platform] ongeldige private-store payload voor tenant '.privateStoreTenant().', collectie '.$collectie);throw new RuntimeException('Private verenigingsopslag bevat ongeldige data.');}return$data;
}
function privateStoreSchrijf(string $collectie,array $data,callable $jsonSchrijver): bool
{
    $collectie=trim($collectie);if($collectie==='')return false;
    if(privateStoreDriver()!=='pdo'){
        if(privateStoreJsonRoot()!==null){
            $root=privateStoreJsonRoot();$pad=$root===null?null:tenantRuntimeCollectiePad($root,$collectie);
            if($pad!==null&&is_file($pad)){
                $oud=privateStoreJsonLees($collectie);
                tenantBackupMaakArray(privateStoreBackupSleutel($collectie),$oud);
            }
            return privateStoreJsonSchrijf($collectie,$data);
        }
        return(bool)$jsonSchrijver($data);
    }
    $pdo=privateStorePdo();$json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);if($json===false)return false;$tenant=privateStoreTenant();$nu=date('c');$driver=strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    try{
        $oudStmt=$pdo->prepare('SELECT payload FROM vereniging_private_store WHERE tenant_key = :tenant AND collection_key = :collection');
        $oudStmt->execute(['tenant'=>$tenant,'collection'=>$collectie]);$oudRij=$oudStmt->fetch();
        if($oudRij){$oudPayload=(string)($oudRij['payload']??'');$oudData=json_decode($oudPayload,true);if(json_last_error()===JSON_ERROR_NONE&&is_array($oudData))tenantBackupMaakArray(privateStoreBackupSleutel($collectie),$oudData);}
    }catch(Throwable $e){error_log('[platform] private store pre-backup read mislukt voor '.$collectie.': '.$e->getMessage());throw new RuntimeException('Private verenigingsopslag kon niet veilig worden geback-upt.',0,$e);}
    if($driver==='pgsql')$sql='INSERT INTO vereniging_private_store (tenant_key, collection_key, payload, updated_at) VALUES (:tenant,:collection,:payload,:updated) ON CONFLICT (tenant_key, collection_key) DO UPDATE SET payload = EXCLUDED.payload, updated_at = EXCLUDED.updated_at';
    elseif($driver==='sqlite')$sql='INSERT INTO vereniging_private_store (tenant_key, collection_key, payload, updated_at) VALUES (:tenant,:collection,:payload,:updated) ON CONFLICT(tenant_key, collection_key) DO UPDATE SET payload = excluded.payload, updated_at = excluded.updated_at';
    else$sql='INSERT INTO vereniging_private_store (tenant_key, collection_key, payload, updated_at) VALUES (:tenant,:collection,:payload,:updated) ON DUPLICATE KEY UPDATE payload=VALUES(payload), updated_at=VALUES(updated_at)';
    try{$stmt=$pdo->prepare($sql);return$stmt->execute(['tenant'=>$tenant,'collection'=>$collectie,'payload'=>$json,'updated'=>$nu]);}catch(Throwable $e){error_log('[platform] private store write mislukt voor '.$collectie.': '.$e->getMessage());throw new RuntimeException('Private verenigingsopslag kon niet worden opgeslagen.',0,$e);}
}
