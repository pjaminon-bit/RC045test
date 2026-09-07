<?php
$root = dirname(__DIR__);
$ok = 0; $fout = 0;
function p211brCheck(bool $cond, string $label): void { global $ok,$fout; if($cond){$ok++;echo "OK: {$label}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$label}\n");} }
function p211brRm(string $p): void { if(is_link($p)||is_file($p)){@unlink($p);return;} if(!is_dir($p))return; foreach(scandir($p)?:[] as $i){if($i==='.'||$i==='..')continue;p211brRm($p.DIRECTORY_SEPARATOR.$i);}@rmdir($p); }

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "FOUT: pdo_sqlite is vereist voor de gemigreerde backup/restore-regressie.\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/platform211-backup-restore-' . bin2hex(random_bytes(5));
$tenantRoot = $tmp . '/tenant';
$privateRoot = $tenantRoot . '/private';
$collections = $privateRoot . '/collections';
$runtimeDir = $tenantRoot . '/storage-runtime';
$proofDir = $privateRoot . '/migrations/private-json-to-pdo';
foreach ([$collections,$runtimeDir,$proofDir] as $dir) if (!@mkdir($dir,0750,true) && !is_dir($dir)) throw new RuntimeException('Fixturemap kon niet worden aangemaakt: '.$dir);

$tenant = 'migrated-backup';
$configPad = $tenantRoot . '/config.php';
$dbPad = $tenantRoot . '/private-store.sqlite';
$jsonPad = $collections . '/leden.json';
$jsonRaw = "[\n  {\"value\": \"legacy-json-bron\"}\n]\n";
file_put_contents($jsonPad,$jsonRaw); @chmod($jsonPad,0640);

$config = [
    'vereniging' => ['sleutel'=>$tenant,'naam'=>'Migrated Backup','volledige_naam'=>'Migrated Backup','site_url'=>'https://example.invalid'],
    'opslag' => [
        'private_driver' => 'json',
        'private_root' => $privateRoot,
        'pdo' => ['dsn'=>'sqlite:'.$dbPad,'user'=>'','password'=>''],
        'backups' => ['bewaardagen'=>90,'max_per_item'=>10,'max_asset_snapshots'=>2,'max_asset_mb'=>50],
    ],
];
file_put_contents($configPad,"<?php\nreturn ".var_export($config,true).";\n"); @chmod($configPad,0640);

$targetHash = hash('sha256','platform211-migrated-backup-target');
$proofPad = $proofDir . '/proof.json';
$proof = [
    'schema'=>1,
    'phase'=>'private-json-to-pdo',
    'status'=>'verified',
    'tenant_key'=>$tenant,
    'target'=>['aggregate_sha256'=>$targetHash],
];
file_put_contents($proofPad,json_encode($proof,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n"); @chmod($proofPad,0640);
$proofSha = hash_file('sha256',$proofPad);
if (!is_string($proofSha)) throw new RuntimeException('Fixtureproof kon niet worden gehasht.');

$state = [
    'schema'=>1,
    'phase'=>'json-to-pdo-cutover',
    'tenant_key'=>$tenant,
    'source_driver'=>'json',
    'effective_driver'=>'pdo',
    'created_at'=>gmdate('c'),
    'proof_path'=>$proofPad,
    'proof_sha256'=>$proofSha,
    'target_aggregate_sha256'=>$targetHash,
];
$statePad = $runtimeDir . '/private-store-runtime.json';
file_put_contents($statePad,json_encode($state,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n"); @chmod($statePad,0640);

putenv('VERENIGING_REQUIRE_TENANT_CONFIG=1');
putenv('VERENIGING_CONFIG_FILE='.$configPad);
try {
    require_once $root . '/app/storage/private-store.php';

    p211brCheck(privateStoreDriver()==='pdo','JSON-geprovisioneerde tenant gebruikt na geldige cutover-state effectief PDO');
    p211brCheck((string)file_get_contents($jsonPad)===$jsonRaw,'legacy JSON-bron is vóór post-cutover writes byte-identiek');

    $w1 = privateStoreSchrijf('leden',['value'=>'pdo-een'],static fn($d)=>false);
    $w2 = privateStoreSchrijf('leden',['value'=>'pdo-twee'],static fn($d)=>false);
    p211brCheck($w1===true&&$w2===true,'post-cutover private writes slagen via effectieve PDO-store');
    p211brCheck((string)file_get_contents($jsonPad)===$jsonRaw,'post-cutover PDO-writes muteren de legacy JSON-bron niet');

    $backups = tenantBackupDataLijst('private-leden');
    p211brCheck(count($backups)>=1,'PDO-prewrite maakt een tenantgebonden private backup');
    $backupNaam = $backups!==[] ? basename($backups[0]) : '';
    $leesFout = null;
    $herstelData = $backupNaam!=='' ? tenantBackupLeesArray('private-leden',$backupNaam,$leesFout) : null;
    p211brCheck(is_array($herstelData)&&($herstelData['value']??null)==='pdo-een'&&$leesFout===null,'post-cutover backup leest de vorige PDO-versie terug');

    $restoreOk = is_array($herstelData) && privateStoreSchrijf('leden',$herstelData,static fn($d)=>false);
    $naRestore = privateStoreLees('leden',static fn()=>['value'=>'MAG-NIET-WORDEN-GEBRUIKT']);
    p211brCheck($restoreOk===true&&($naRestore['value']??null)==='pdo-een','private restore schrijft en leest post-cutover via PDO terug');
    p211brCheck((string)file_get_contents($jsonPad)===$jsonRaw,'backup/restore laat rollbackanker JSON byte-identiek intact');

    $pdo = new PDO('sqlite:'.$dbPad,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $stmt = $pdo->prepare('SELECT payload FROM vereniging_private_store WHERE tenant_key=:tenant AND collection_key=:collection');
    $stmt->execute(['tenant'=>$tenant,'collection'=>'leden']);
    $row = $stmt->fetch();
    $payload = is_array($row) ? json_decode((string)($row['payload']??''),true) : null;
    p211brCheck(is_array($payload)&&($payload['value']??null)==='pdo-een','restore-resultaat staat aantoonbaar in de PDO-tabel');

    $beheerRaw = (string)file_get_contents($root.'/beheer/backups.php');
    p211brCheck(
        str_contains($beheerRaw,"if (\$type === 'private') {\n            \$data = privateStoreLees(\$source")
        && str_contains($beheerRaw,"if (\$type === 'private') return privateStoreSchrijf(\$source, \$data"),
        'beheer backup/restore routeert private tenantdata via dezelfde storage-abstraction'
    );
} finally {
    putenv('VERENIGING_REQUIRE_TENANT_CONFIG');
    putenv('VERENIGING_CONFIG_FILE');
    p211brRm($tmp);
}

echo "Platform #211 migrated backup/restore: {$ok} OK, {$fout} fout(en)\n";
exit($fout===0?0:1);
