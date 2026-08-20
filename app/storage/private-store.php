<?php
// ============================================================
// Tenant-aware private storage abstraction
// ============================================================
function privateStoreConfig(): array{static$config=null;if($config===null){$geladen=require dirname(__DIR__,2).'/site-config.php';$config=is_array($geladen)?$geladen:[];}return$config;}
function privateStoreTenant(): string{$config=privateStoreConfig();$sleutel=strtolower(trim((string)($config['vereniging']['sleutel']??'default')));$sleutel=preg_replace('/[^a-z0-9_-]+/','-',$sleutel);return trim((string)$sleutel,'-')?:'default';}
function privateStoreDriver(): string{$config=privateStoreConfig();$driver=strtolower(trim((string)($config['opslag']['private_driver']??'json')));return$driver==='pdo'?'pdo':'json';}
function privateStorePdo(): PDO
{
    static$pdo=null;static$fout=null;if($pdo instanceof PDO)return$pdo;if($fout instanceof Throwable)throw new RuntimeException('Private verenigingsopslag is tijdelijk niet beschikbaar.',0,$fout);
    $config=privateStoreConfig();$dsnConfig=trim((string)($config['opslag']['pdo']['dsn']??''));$dsnEnv=trim((string)(getenv('VERENIGING_DB_DSN')?:''));$dsn=$dsnConfig!==''?$dsnConfig:$dsnEnv;
    if($dsn===''){$fout=new RuntimeException('PDO-driver geselecteerd zonder DSN.');error_log('[platform] private PDO: driver=pdo maar geen DSN geconfigureerd');throw new RuntimeException('Private verenigingsopslag is niet geconfigureerd.',0,$fout);}
    $userConfig=(string)($config['opslag']['pdo']['user']??'');$passConfig=(string)($config['opslag']['pdo']['password']??'');$user=$userConfig!==''?$userConfig:(string)(getenv('VERENIGING_DB_USER')?:'');$pass=$passConfig!==''?$passConfig:(string)(getenv('VERENIGING_DB_PASSWORD')?:'');
    try{$pdo=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);try{$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);}catch(Throwable $ignored){}privateStoreEnsureSchema($pdo);return$pdo;}
    catch(Throwable $e){error_log('[platform] private PDO niet beschikbaar: '.get_class($e).' · '.$e->getMessage());$fout=$e;throw new RuntimeException('Private verenigingsopslag is tijdelijk niet beschikbaar.',0,$e);}
}
function privateStoreEnsureSchema(PDO $pdo): void{$pdo->exec('CREATE TABLE IF NOT EXISTS vereniging_private_store (tenant_key VARCHAR(80) NOT NULL, collection_key VARCHAR(120) NOT NULL, payload TEXT NOT NULL, updated_at VARCHAR(40) NOT NULL, PRIMARY KEY (tenant_key, collection_key))');}
function privateStoreTransactie(callable $callback)
{
    if(privateStoreDriver()!=='pdo')return$callback();$pdo=privateStorePdo();$eigen=!$pdo->inTransaction();if($eigen)$pdo->beginTransaction();
    try{$resultaat=$callback();if($eigen)$pdo->commit();return$resultaat;}catch(Throwable $e){if($eigen&&$pdo->inTransaction())$pdo->rollBack();throw$e;}
}
function privateStoreLees(string $collectie,callable $jsonLezer): array
{
    $collectie=trim($collectie);if($collectie==='')return[];if(privateStoreDriver()!=='pdo'){$data=$jsonLezer();return is_array($data)?$data:[];}$pdo=privateStorePdo();
    try{$stmt=$pdo->prepare('SELECT payload FROM vereniging_private_store WHERE tenant_key = :tenant AND collection_key = :collection');$stmt->execute(['tenant'=>privateStoreTenant(),'collection'=>$collectie]);$rij=$stmt->fetch();}
    catch(Throwable $e){error_log('[platform] private store read mislukt voor '.$collectie.': '.$e->getMessage());throw new RuntimeException('Private verenigingsopslag kon niet worden gelezen.',0,$e);}
    if(!$rij){$fallback=$jsonLezer();return is_array($fallback)?$fallback:[];}$payload=(string)($rij['payload']??'');$data=json_decode($payload,true);if(json_last_error()!==JSON_ERROR_NONE||!is_array($data)){error_log('[platform] ongeldige private-store payload voor tenant '.privateStoreTenant().', collectie '.$collectie);throw new RuntimeException('Private verenigingsopslag bevat ongeldige data.');}return$data;
}
function privateStoreSchrijf(string $collectie,array $data,callable $jsonSchrijver): bool
{
    $collectie=trim($collectie);if($collectie==='')return false;if(privateStoreDriver()!=='pdo')return(bool)$jsonSchrijver($data);$pdo=privateStorePdo();$json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);if($json===false)return false;$tenant=privateStoreTenant();$nu=date('c');$driver=strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    if($driver==='pgsql')$sql='INSERT INTO vereniging_private_store (tenant_key, collection_key, payload, updated_at) VALUES (:tenant,:collection,:payload,:updated) ON CONFLICT (tenant_key, collection_key) DO UPDATE SET payload = EXCLUDED.payload, updated_at = EXCLUDED.updated_at';
    elseif($driver==='sqlite')$sql='INSERT INTO vereniging_private_store (tenant_key, collection_key, payload, updated_at) VALUES (:tenant,:collection,:payload,:updated) ON CONFLICT(tenant_key, collection_key) DO UPDATE SET payload = excluded.payload, updated_at = excluded.updated_at';
    else$sql='INSERT INTO vereniging_private_store (tenant_key, collection_key, payload, updated_at) VALUES (:tenant,:collection,:payload,:updated) ON DUPLICATE KEY UPDATE payload=VALUES(payload), updated_at=VALUES(updated_at)';
    try{$stmt=$pdo->prepare($sql);return$stmt->execute(['tenant'=>$tenant,'collection'=>$collectie,'payload'=>$json,'updated'=>$nu]);}catch(Throwable $e){error_log('[platform] private store write mislukt voor '.$collectie.': '.$e->getMessage());throw new RuntimeException('Private verenigingsopslag kon niet worden opgeslagen.',0,$e);}
}
