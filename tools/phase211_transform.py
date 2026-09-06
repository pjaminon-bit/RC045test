from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def replace(path, old, new):
    p = ROOT / path
    s = p.read_text()
    if old not in s:
        raise SystemExit(f"expected text missing in {path}: {old[:120]!r}")
    p.write_text(s.replace(old, new, 1))

# Canonical provisioning default: PDO/PostgreSQL. JSON remains explicit compatibility.
replace(
    "bin/provision-tenant.php",
    '    echo "  --driver=json|pdo\\n";\n',
    '    echo "  --driver=pdo|json          standaard pdo; json alleen expliciete standalone/legacycompatibiliteit\\n";\n',
)
replace(
    "bin/provision-tenant.php",
    "$driver = strtolower(trim((string)($opt['driver'] ?? 'json')));",
    "$driver = strtolower(trim((string)($opt['driver'] ?? 'pdo')));",
)
replace(
    "bin/provision-tenant.php",
    "if (!in_array($driver, ['json', 'pdo'], true)) provisionStop('--driver moet json of pdo zijn.');",
    "if (!in_array($driver, ['pdo', 'json'], true)) provisionStop('--driver moet pdo of json zijn.');",
)

# External tenant configs must carry an explicit valid datastore contract.
replace(
    "site-config.php",
    "    if (!is_array($lokaal)) {\n        throw new RuntimeException('Verenigingsconfiguratie moet een array retourneren.');\n    }\n    $config = array_replace_recursive($config, $lokaal);",
    "    if (!is_array($lokaal)) {\n        throw new RuntimeException('Verenigingsconfiguratie moet een array retourneren.');\n    }\n    if ($externPad !== null) {\n        $externDriver = $lokaal['opslag']['private_driver'] ?? null;\n        if (!is_string($externDriver) || !in_array(strtolower(trim($externDriver)), ['pdo', 'json'], true)) {\n            throw new RuntimeException('Externe tenantconfig moet private_driver expliciet als pdo of json vastleggen.');\n        }\n    }\n    $config = array_replace_recursive($config, $lokaal);",
)

replace(
    "app/storage/private-store.php",
    "function privateStoreDriver(): string{$config=privateStoreConfig();$driver=strtolower(trim((string)($config['opslag']['private_driver']??'json')));return$driver==='pdo'?'pdo':'json';}",
    "function privateStoreDriver(): string\n{\n    $config=privateStoreConfig();\n    $driver=strtolower(trim((string)($config['opslag']['private_driver']??'')));\n    if($driver==='pdo'||$driver==='json')return$driver;\n    throw new RuntimeException('Private datastore-driver moet expliciet pdo of json zijn.');\n}",
)

# The second-tenant proof intentionally exercises the filesystem JSON compatibility backend.
replace(
    "tests/phase33-second-tenant.php",
    "        . ' --root=' . escapeshellarg($base)\n        . ' --modules=' . escapeshellarg($modules);",
    "        . ' --root=' . escapeshellarg($base)\n        . ' --driver=json'\n        . ' --modules=' . escapeshellarg($modules);",
)
replace(
    "tests/phase33-second-tenant.php",
    "        'iedere tenant heeft een eigen private root'\n    );",
    "        'iedere tenant heeft een eigen private root'\n    );\n    check33(\n        ($cfgA['opslag']['private_driver'] ?? '') === 'json'\n        && ($cfgB['opslag']['private_driver'] ?? '') === 'json',\n        'filesystem-isolatietest kiest JSON uitsluitend expliciet als compatibiliteitsbackend'\n    );",
)

# Documentation: canonical new-tenant route is PDO; JSON is explicit compatibility.
replace(
    "docs/PROVISIONING.md",
    "  --root=/srv/verenigingen \\\n  --modules=website,ledenadministratie,aanmelden,sponsors",
    "  --root=/srv/verenigingen \\\n  --driver=pdo \\\n  --modules=website,ledenadministratie,aanmelden,sponsors",
)
replace(
    "docs/PROVISIONING.md",
    "--driver=json\n--driver=pdo",
    "--driver=pdo   # standaard/canoniek voor nieuwe tenants\n--driver=json  # alleen expliciete standalone/legacycompatibiliteit",
)
replace(
    "docs/PROVISIONING.md",
    "Bij `--driver=pdo` gebruikt dezelfde repositorylaag tenant-key isolatie in de gedeelde PDO-store. Een externe PDO-tenant valt niet terug naar legacy JSON wanneer zijn collectie leeg is.",
    "Nieuwe tenants gebruiken canoniek `--driver=pdo`. De first-VPS/productiebootstrap geeft dit bovendien expliciet door en provisiont PostgreSQL vóór tenant-FPM wordt geactiveerd. `--driver=json` blijft alleen beschikbaar als bewuste standalone/legacycompatibiliteitskeuze. Een externe PDO-tenant valt nooit terug naar legacy JSON wanneer de database ontbreekt, faalt of een collectie leeg is.",
)

replace(
    "docs/VPS-DATABASE.md",
    "Status: **code/automation en CI-contract gereed; daadwerkelijke PostgreSQL provisioning volgt op de echte VPS.**",
    "Status: **code/automation, CI én echte VPS-validatie afgerond. PostgreSQL/PDO is de canonieke private datastore voor nieuwe VPS-tenants.**",
)
replace(
    "docs/VPS-DATABASE.md",
    "Fase 4.5 kiest één canoniek productiemodel voor private PDO-opslag:",
    "Fase 4.5 kiest één canoniek productiemodel voor private PDO-opslag. Sinds platformstap #211 gebruikt nieuwe tenantprovisioning standaard PDO; `--driver=json` blijft uitsluitend een expliciete standalone/legacycompatibiliteitskeuze:\n",
)

# New fail-closed regression for the Phase-A contract.
test = r'''<?php
$root = dirname(__DIR__);
$ok = 0; $fout = 0;
function c211(bool $c, string $label): void { global $ok,$fout; if($c){$ok++;echo "OK: {$label}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$label}\n");} }
function rr211(string $p): void { if(is_link($p)){@unlink($p);return;} if(!is_dir($p))return; foreach(scandir($p)?:[] as $i){if($i==='.'||$i==='..')continue;$x=$p.DIRECTORY_SEPARATOR.$i;if(is_dir($x)&&!is_link($x))rr211($x);else@unlink($x);}@rmdir($p); }
function run211(array $argv): array { $d=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];$p=proc_open($argv,$d,$pipes,null,null,['bypass_shell'=>true]);if(!is_resource($p))return[255,'','proc_open'];fclose($pipes[0]);$o=stream_get_contents($pipes[1]);$e=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return[proc_close($p),(string)$o,(string)$e]; }
function cfg211(string $path, string $key, string $privateRoot, ?string $driver): void { $opslag=['private_root'=>$privateRoot,'pdo'=>['dsn'=>'','user'=>'','password'=>'']];if($driver!==null)$opslag['private_driver']=$driver;$cfg=['vereniging'=>['sleutel'=>$key,'naam'=>'Test '.$key,'site_url'=>'https://'.$key.'.example','timezone'=>'Europe/Amsterdam'],'opslag'=>$opslag];file_put_contents($path,"<?php\nreturn ".var_export($cfg,true).";\n");@chmod($path,0640); }
$tmp=sys_get_temp_dir().'/platform211-'.bin2hex(random_bytes(5));$base=$tmp.'/tenants';@mkdir($base,0750,true);$provision=$root.'/bin/provision-tenant.php';
try {
    [$codePdo,$outPdo,$errPdo]=run211([PHP_BINARY,$provision,'--key=default-pdo','--name=Default PDO','--url=https://default-pdo.example','--root='.$base]);
    $cfgPdo=is_file($base.'/default-pdo/config.php')?require $base.'/default-pdo/config.php':[];
    $manifestPdo=is_file($base.'/default-pdo/tenant.json')?json_decode((string)file_get_contents($base.'/default-pdo/tenant.json'),true):null;
    c211($codePdo===0&&($cfgPdo['opslag']['private_driver']??'')==='pdo','provisioner kiest PDO als standaard private datastore');
    c211(is_array($manifestPdo)&&($manifestPdo['private_driver']??'')==='pdo','tenantmanifest legt standaard PDO expliciet vast');

    [$codeJson]=run211([PHP_BINARY,$provision,'--key=legacy-json','--name=Legacy JSON','--url=https://legacy-json.example','--root='.$base,'--driver=json']);
    $cfgJson=is_file($base.'/legacy-json/config.php')?require $base.'/legacy-json/config.php':[];
    c211($codeJson===0&&($cfgJson['opslag']['private_driver']??'')==='json','JSON blijft uitsluitend als expliciete compatibiliteitskeuze beschikbaar');

    [$codeBad,, $errBad]=run211([PHP_BINARY,$provision,'--key=bad-driver','--name=Bad Driver','--url=https://bad-driver.example','--root='.$base,'--driver=onbekend']);
    c211($codeBad!==0&&!is_dir($base.'/bad-driver')&&str_contains($errBad,'pdo of json'),'onbekende driver faalt vóór tenantwrites gesloten');

    $firstContract=(string)file_get_contents($root.'/app/deployment/first-vps-bootstrap-contract.php');
    $firstApply=(string)file_get_contents($root.'/bin/apply-first-vps-bootstrap.php');
    c211(str_contains($firstContract,"'private_driver' => 'pdo'"),'first-VPS plan bindt tenant expliciet aan PDO');
    c211(str_contains($firstApply,"'--driver=pdo'"),'first-VPS apply geeft PDO expliciet aan de provisioner door');

    $extRoot=$tmp.'/external';@mkdir($extRoot.'/private',0750,true);
    $missing=$extRoot.'/missing.php';cfg211($missing,'missing-driver',$extRoot.'/private',null);
    $worker='putenv("VERENIGING_REQUIRE_TENANT_CONFIG=1");putenv("VERENIGING_CONFIG_FILE=".$argv[1]);require $argv[2]."/site-config.php";echo "LOADED\\n";';
    [$missingCode,,$missingErr]=run211([PHP_BINARY,'-r',$worker,$missing,$root]);
    c211($missingCode!==0&&str_contains($missingErr,'private_driver'),'externe tenantconfig zonder expliciete driver faalt gesloten');

    $bad=$extRoot.'/bad.php';cfg211($bad,'bad-external',$extRoot.'/private','anders');
    [$badCode,,$badErr]=run211([PHP_BINARY,'-r',$worker,$bad,$root]);
    c211($badCode!==0&&str_contains($badErr,'private_driver'),'externe tenantconfig met onbekende driver faalt gesloten');

    $jsonExt=$extRoot.'/json.php';cfg211($jsonExt,'json-external',$extRoot.'/private','json');
    $driverWorker='putenv("VERENIGING_REQUIRE_TENANT_CONFIG=1");putenv("VERENIGING_CONFIG_FILE=".$argv[1]);require $argv[2]."/app/storage/private-store.php";echo privateStoreDriver();';
    [$jsonCode,$jsonOut,$jsonErr]=run211([PHP_BINARY,'-r',$driverWorker,$jsonExt,$root]);
    c211($jsonCode===0&&trim($jsonOut)==='json','expliciete externe JSON-compatibiliteit blijft geldig');

    $pdoExt=$extRoot.'/pdo.php';cfg211($pdoExt,'pdo-external',$extRoot.'/private','pdo');
    [$pdoCode,$pdoOut,$pdoErr]=run211([PHP_BINARY,'-r',$driverWorker,$pdoExt,$root]);
    c211($pdoCode===0&&trim($pdoOut)==='pdo','expliciete externe PDO-config wordt zonder fallback geselecteerd');
} finally { rr211($tmp); }
echo "Platform #211 PostgreSQL canonical: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
'''
(ROOT / "tests/platform211-postgresql-canonical.php").write_text(test)
