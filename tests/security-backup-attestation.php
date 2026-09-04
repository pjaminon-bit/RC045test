<?php
$root = dirname(__DIR__);
$ok = 0; $fout = 0;
function checkBA(bool $cond, string $label): void { global $ok, $fout; if ($cond) { $ok++; echo "OK: {$label}\n"; } else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); } }
function rrBA(string $dir): void { if (is_link($dir)) { @unlink($dir); return; } if (!is_dir($dir)) return; foreach (scandir($dir) ?: [] as $i) { if ($i === '.' || $i === '..') continue; $p=$dir.DIRECTORY_SEPARATOR.$i; if (is_dir($p) && !is_link($p)) rrBA($p); else @unlink($p); } @rmdir($dir); }

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rc045-backup-attestation-' . bin2hex(random_bytes(4));
mkdir($tmp, 0750, true);
$privateRoot = $tmp . '/tenant/private';
mkdir($privateRoot, 0750, true);
$config = $tmp . '/tenant/config.php';
mkdir(dirname($config), 0750, true);
$cfg = [
    'vereniging' => ['sleutel'=>'tenant-a','naam'=>'Tenant A','volledige_naam'=>'Tenant A','site_url'=>'https://tenant-a.example'],
    'opslag' => ['private_driver'=>'json','private_root'=>$privateRoot,'backups'=>['bewaardagen'=>90,'max_per_item'=>20,'max_asset_snapshots'=>5,'max_asset_mb'=>50]],
];
file_put_contents($config, "<?php\nreturn " . var_export($cfg, true) . ";\n");
putenv('VERENIGING_REQUIRE_TENANT_CONFIG=1');
putenv('VERENIGING_CONFIG_FILE=' . $config);

$key = openssl_pkey_new(['private_key_bits'=>2048, 'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
if ($key === false) { fwrite(STDERR, "FOUT: test-RSA key kon niet worden gemaakt\n"); rrBA($tmp); exit(1); }
openssl_pkey_export($key, $privatePem);
$details = openssl_pkey_get_details($key);
$publicPem = is_array($details) ? (string)($details['key'] ?? '') : '';
$pubPath = $tmp . '/public.pem';
file_put_contents($pubPath, $publicPem);
chmod($pubPath, 0644);
putenv('VERENIGING_BACKUP_ATTESTATION_TEST_PUBLIC_KEY=' . $pubPath);
putenv('VERENIGING_BACKUP_ATTESTATION_TEST_SOCKET=' . $tmp . '/missing-attestor.sock');

require_once $root . '/app/storage/tenant-backup-store.php';
require_once $root . '/app/storage/private-store-prewrite.php';

function attestBA(array $statement, $privateKey, string $publicPem): array
{
    $signed = backupAttestatieCanoniek($statement);
    if (!is_string($signed) || !openssl_sign($signed, $signature, $privateKey, OPENSSL_ALGO_SHA256)) return [];
    return [
        'schema'=>1,
        'algorithm'=>'rsa-sha256',
        'key_id'=>hash('sha256', $publicPem),
        'signed'=>base64_encode($signed),
        'signature'=>base64_encode($signature),
    ];
}

function writeAttBA(string $sidecar, array $att): void
{
    file_put_contents($sidecar, json_encode($att, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");
    chmod($sidecar, 0640);
}

try {
    checkBA(backupAttestatieActief(), 'test-public-key activeert cryptografisch backupcontract');

    $map = tenantBackupDataMap('public-contact');
    mkdir($map, 0750, true);
    $dataPath = $map . '/2026-09-04_120000_000000_test.json';
    $env = ['schema'=>2,'tenant_key'=>'tenant-a','backup_key'=>'public-contact','created_at'=>'2026-09-04T12:00:00+02:00','data'=>['value'=>'origineel']];
    $raw = json_encode($env, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents($dataPath, $raw); chmod($dataPath, 0640);
    $statement = backupAttestatieStatementData($dataPath, 'tenant-a', 'public-contact');
    $att = is_array($statement) ? attestBA($statement, $key, $publicPem) : [];
    writeAttBA(backupAttestatieSidecarData($dataPath), $att);

    checkBA(backupAttestatieVerifieerData($dataPath, 'tenant-a', 'public-contact'), 'geldige schema-2 datasnapshot verifieert cryptografisch');
    checkBA(backupAttestatieVerifieerDataRaw($dataPath, $raw, 'tenant-a', 'public-contact'), 'exact reeds ingelezen snapshotbytes verifiëren tegen dezelfde signature');
    $err = null; $read = tenantBackupLeesArray('public-contact', basename($dataPath), $err);
    checkBA(is_array($read) && ($read['value'] ?? '') === 'origineel' && $err === null, 'geldige geattesteerde datasnapshot blijft herstelbaar');

    $tampered = $env; $tampered['data']['value'] = 'gemanipuleerd';
    file_put_contents($dataPath, json_encode($tampered, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    checkBA(backupAttestatieVerifieerDataRaw($dataPath, $raw, 'tenant-a', 'public-contact'), 'in-memory restorebytes blijven verifieerbaar als bestand na de read wijzigt');
    $err = null; $read = tenantBackupLeesArray('public-contact', basename($dataPath), $err);
    checkBA($read === null && str_contains((string)$err, 'Cryptografische'), 'wijziging van snapshotdata wordt vóór restore gedetecteerd');

    file_put_contents($dataPath, $raw);
    $bindingTamper = $env; $bindingTamper['backup_key'] = 'public-homepage';
    file_put_contents($dataPath, json_encode($bindingTamper, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $err = null; $read = tenantBackupLeesArray('public-contact', basename($dataPath), $err);
    checkBA($read === null && str_contains((string)$err, 'Cryptografische'), 'wijziging van bindingmetadata wordt door signature gedetecteerd');
    file_put_contents($dataPath, $raw);

    checkBA(!backupAttestatieVerifieerData($dataPath, 'tenant-b', 'public-contact'), 'signature is cryptografisch aan tenant gebonden');
    checkBA(!backupAttestatieVerifieerData($dataPath, 'tenant-a', 'public-homepage'), 'signature is cryptografisch aan backup-key gebonden');

    @unlink(backupAttestatieSidecarData($dataPath));
    $err = null; $read = tenantBackupLeesArray('public-contact', basename($dataPath), $err);
    checkBA($read === null && str_contains((string)$err, 'Cryptografische'), 'ontbrekende attestatie faalt restore gesloten');
    writeAttBA(backupAttestatieSidecarData($dataPath), $att);

    $legacyPath = $map . '/2026-09-04_110000_000000_legacy.json';
    $legacy = $env; $legacy['schema'] = 1;
    file_put_contents($legacyPath, json_encode($legacy, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $err = null; $read = tenantBackupLeesArray('public-contact', basename($legacyPath), $err);
    checkBA($read === null && str_contains((string)$err, 'Legacy'), 'legacy schema-1 snapshot wordt na crypto-activatie expliciet als ongeauthenticeerd geweigerd');

    $assetRoot = tenantBackupAssetScopeRoot('sponsors');
    mkdir($assetRoot, 0750, true);
    $asset = $assetRoot . '/2026-09-04_120000_000000_test';
    mkdir($asset . '/payload', 0750, true);
    file_put_contents($asset . '/payload/logo.png', 'PNG-ORIGINEEL');
    file_put_contents($asset . '/manifest.json', json_encode(['schema'=>2,'tenant_key'=>'tenant-a','asset_scope'=>'sponsors','created_at'=>'2026-09-04T12:00:00+02:00'], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    $assetStatement = backupAttestatieStatementAsset($asset, 'tenant-a', 'sponsors');
    $assetAtt = is_array($assetStatement) ? attestBA($assetStatement, $key, $publicPem) : [];
    writeAttBA(backupAttestatieSidecarAsset($asset), $assetAtt);
    checkBA(backupAttestatieVerifieerAsset($asset, 'tenant-a', 'sponsors'), 'geldige assetattestatie bindt volledige payloadmanifestset');

    $stagePayload = $tmp . '/stage-payload';
    mkdir($stagePayload, 0750, true);
    copy($asset . '/payload/logo.png', $stagePayload . '/logo.png');
    checkBA(backupAttestatieVerifieerAssetStaging($asset, $stagePayload, 'tenant-a', 'sponsors'), 'gestagede assetkopie wordt opnieuw tegen ondertekende filelijst geverifieerd');
    file_put_contents($stagePayload . '/logo.png', 'PNG-STAGING-GEMANIPULEERD');
    checkBA(!backupAttestatieVerifieerAssetStaging($asset, $stagePayload, 'tenant-a', 'sponsors'), 'wijziging tijdens bron-naar-staging venster wordt vóór swap gedetecteerd');

    file_put_contents($asset . '/payload/logo.png', 'PNG-GEMANIPULEERD');
    checkBA(!backupAttestatieVerifieerAsset($asset, 'tenant-a', 'sponsors'), 'wijziging van assetpayload wordt vóór restore gedetecteerd');
    file_put_contents($asset . '/payload/logo.png', 'PNG-ORIGINEEL');
    file_put_contents($asset . '/manifest.json', json_encode(['schema'=>2,'tenant_key'=>'tenant-a','asset_scope'=>'fotoboek','created_at'=>'2026-09-04T12:00:00+02:00'], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    checkBA(!backupAttestatieVerifieerAsset($asset, 'tenant-a', 'sponsors'), 'wijziging van assetmanifestbinding wordt gedetecteerd');

    $before = tenantBackupDataLijst('private-failclosed');
    $created = tenantBackupMaakArray('private-failclosed', ['value'=>'mag-niet-ongetekend-landen']);
    $after = tenantBackupDataLijst('private-failclosed');
    checkBA($created === null && count($before) === count($after), 'actieve crypto zonder attestor laat geen ongetekende schema-2 backup achter');
    checkBA(privatePrewriteMaak($privateRoot, 'tenant-a', 'private-failclosed', ['value'=>'oud']) === null, 'legacy prewrite-fallback kan actieve cryptografische backupgate niet omzeilen');

    $phpSource = file_get_contents($root . '/app/storage/backup-attestation.php');
    checkBA(is_string($phpSource) && !preg_match('/\b(?:exec|system|shell_exec|proc_open|passthru)\s*\(/', $phpSource) && !str_contains($phpSource, 'private.pem'), 'tenant PHP-verifier bevat geen shell/root-exec of private-keypad');
    $storeSource = file_get_contents($root . '/app/storage/tenant-backup-store.php');
    checkBA(is_string($storeSource) && str_contains($storeSource, 'backupAttestatieVerifieerDataRaw($realPad, $raw') && str_contains($storeSource, 'backupAttestatieVerifieerAssetStaging('), 'restorepad gebruikt exact-read data-attestatie en staging-herverificatie');
    $attestorSource = file_get_contents($root . '/ops/vps-test-deploy/verenigingsplatform-backup-attestor');
    checkBA(is_string($attestorSource) && str_contains($attestorSource, 'SO_PEERCRED') && str_contains($attestorSource, "deployment.json") && str_contains($attestorSource, "private.pem") && str_contains($attestorSource, "schema', 0)) != 2"), 'root-attestor bindt peer-UID/deployment en accepteert alleen schema 2');
    $installer = file_get_contents($root . '/ops/vps-test-deploy/install-backup-attestation');
    checkBA(is_string($installer) && str_contains($installer, '0600') && str_contains($installer, 'NoNewPrivileges=true') && str_contains($installer, 'ProtectSystem=strict') && str_contains($installer, 'ReadOnlyPaths=/srv/verenigingen'), 'root-installer borgt keymetadata en systemd sandbox');

    $cmd = 'python3 -c ' . escapeshellarg('import pathlib,sys; p=pathlib.Path(sys.argv[1]); compile(p.read_text(encoding="utf-8"), str(p), "exec")') . ' ' . escapeshellarg($root . '/ops/vps-test-deploy/verenigingsplatform-backup-attestor');
    exec($cmd . ' 2>&1', $pyOut, $pyCode);
    checkBA($pyCode === 0, 'backup-attestor Python-bron compileert');
} finally {
    putenv('VERENIGING_BACKUP_ATTESTATION_TEST_PUBLIC_KEY');
    putenv('VERENIGING_BACKUP_ATTESTATION_TEST_SOCKET');
    putenv('VERENIGING_REQUIRE_TENANT_CONFIG');
    putenv('VERENIGING_CONFIG_FILE');
    rrBA($tmp);
}

echo "Security backup attestation: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);