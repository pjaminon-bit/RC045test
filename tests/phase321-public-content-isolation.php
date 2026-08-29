<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function check321content(bool $cond, string $label): void
{
    global $ok, $fout;
    if ($cond) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

function rrmdir321content(string $pad): void
{
    if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        rrmdir321content($pad . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($pad);
}

function tenantConfig321content(string $pad, string $key, string $privateRoot, bool $metPrivateRoot = true): void
{
    $config = [
        'vereniging' => ['sleutel'=>$key,'naam'=>$key,'volledige_naam'=>$key,'site_url'=>'https://'.$key.'.example','timezone'=>'Europe/Amsterdam','standaard_taal'=>'nl'],
        'opslag' => ['private_driver'=>'json','private_root'=>$metPrivateRoot?$privateRoot:'','pdo'=>['dsn'=>'','user'=>'','password'=>'']],
    ];
    file_put_contents($pad, "<?php\nreturn " . var_export($config, true) . ";\n");
}

function runTenant321content(string $root, string $tmp, string $configPad, ?string $privateRoot, string $body): array
{
    $launcher = $tmp . '/run-' . bin2hex(random_bytes(4)) . '.php';
    $code = "<?php\n"
        . 'putenv(' . var_export('VERENIGING_REQUIRE_TENANT_CONFIG=1', true) . ");\n"
        . 'putenv(' . var_export('VERENIGING_CONFIG_FILE=' . $configPad, true) . ");\n";
    if ($privateRoot !== null) $code .= 'putenv(' . var_export('VERENIGING_PRIVATE_ROOT=' . $privateRoot, true) . ");\n";
    else $code .= "putenv('VERENIGING_PRIVATE_ROOT');\n";
    $code .= '$ROOT=' . var_export($root, true) . ";\n" . $body . "\n";
    file_put_contents($launcher, $code);
    $out = [];
    exec(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($launcher) . ' 2>&1', $out, $codeExit);
    @unlink($launcher);
    return [$codeExit, implode("\n", $out)];
}

require_once $root . '/app/content/public-content-store.php';

$definities = publicContentDefinities();
check321content(isset($definities['homepage'],$definities['contact'],$definities['lidmaatschapstypen']), 'publieke contentstore registreert kern-datasets expliciet');
check321content(isset($definities['media'],$definities['media-pagina'],$definities['fotoboek'],$definities['fotoboek-pagina']), 'media en fotoboekmetadata vallen vanaf optie 8 onder dezelfde tenantcontentgrens');
check321content(publicContentBestandsnaam('../auth/users') === null, 'willekeurige/traversal datasetkey is niet toegestaan');
check321content(publicContentPad('lidmaatschapstypen') === $root . '/data/lidmaatschapstypen.json', 'standalone RC045 behoudt legacy datapad');
$legacyMembership = @file_get_contents($root . '/data/lidmaatschapstypen.json');
check321content(is_string($legacyMembership) && $legacyMembership !== '', 'legacy lidmaatschapstypen zijn beschikbaar als canary');

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rc045-phase321-content-' . bin2hex(random_bytes(4));
$tenantA = $tmp . '/tenant-a';
$tenantB = $tmp . '/tenant-b';
$privateA = $tenantA . '/private';
$privateB = $tenantB . '/private';
@mkdir($privateA . '/public-content', 0750, true);
@mkdir($privateB . '/public-content', 0750, true);
$configA = $tenantA . '/config.php';
$configB = $tenantB . '/config.php';
tenantConfig321content($configA, 'tenant-a', $privateA);
tenantConfig321content($configB, 'tenant-b', $privateB);
file_put_contents($privateA . '/public-content/homepage.json', json_encode(['marker'=>'TENANT-A-CANARY'], JSON_UNESCAPED_SLASHES));
file_put_contents($privateB . '/public-content/homepage.json', json_encode(['marker'=>'TENANT-B-CANARY'], JSON_UNESCAPED_SLASHES));

$rootContact = $root . '/data/contact.json';
$contactBestond = is_file($rootContact);
$contactVoor = $contactBestond ? hash_file('sha256', $rootContact) : null;
$membershipVoor = hash_file('sha256', $root . '/data/lidmaatschapstypen.json');

try {
    [$codeA,$outA] = runTenant321content($root,$tmp,$configA,$privateA,
        "require \$ROOT.'/app/content/public-content-store.php'; echo json_encode(['pad'=>publicContentPad('homepage'),'data'=>publicContentLees('homepage')]);");
    $a = json_decode($outA,true);
    check321content($codeA===0 && ($a['pad']??'')===$privateA.'/public-content/homepage.json', 'tenant A resolveert homepage uitsluitend in eigen private root');
    check321content(($a['data']['marker']??'')==='TENANT-A-CANARY', 'tenant A leest uitsluitend eigen homepage-canary');

    [$codeB,$outB] = runTenant321content($root,$tmp,$configB,$privateB,
        "require \$ROOT.'/app/content/public-content-store.php'; echo json_encode(['pad'=>publicContentPad('homepage'),'data'=>publicContentLees('homepage'),'membership'=>publicContentLees('lidmaatschapstypen'),'mapped'=>publicContentMapLegacyPad(\$ROOT.'/data/contact.json')]);");
    $b = json_decode($outB,true);
    check321content($codeB===0 && ($b['pad']??'')===$privateB.'/public-content/homepage.json', 'tenant B resolveert homepage uitsluitend in eigen private root');
    check321content(($b['data']['marker']??'')==='TENANT-B-CANARY', 'tenant B leest uitsluitend eigen homepage-canary');
    check321content(array_key_exists('membership',$b) && $b['membership']===null, 'ontbrekende tenantdataset valt niet terug op bestaande RC045 canary');
    check321content(($b['mapped']??'')===$privateB.'/public-content/contact.json', 'legacy beheerpad wordt exact naar tenantcontent omgebogen');

    [$codeGeneric,$outGeneric] = runTenant321content($root,$tmp,$configA,$privateA,
        "require \$ROOT.'/app/content/content-pagina.php'; echo contentPaginaDataPad('homepage');");
    check321content($codeGeneric===0 && $outGeneric===$privateA.'/public-content/homepage.json', 'generieke contentpagina gebruikt tenant-aware opslagpad');

    [$codeWrite,$outWrite] = runTenant321content($root,$tmp,$configB,$privateB,
        "require \$ROOT.'/app/beheer/editor-hulp.php'; \$ok=beheerEditorSchrijfJson(\$ROOT.'/data/contact.json',['marker'=>'TENANT-B-WRITE']); echo \$ok?'OK':'FAIL';");
    $bContact = json_decode((string)@file_get_contents($privateB.'/public-content/contact.json'),true);
    check321content($codeWrite===0 && $outWrite==='OK' && ($bContact['marker']??'')==='TENANT-B-WRITE', 'oude beheereditor schrijft publieke content alleen tenant-lokaal');
    check321content(!$contactBestond ? !is_file($rootContact) : hash_file('sha256',$rootContact)===$contactVoor, 'tenant editorwrite wijzigt gedeeld RC045 contactbestand niet');

    [$codeMembership,$outMembership] = runTenant321content($root,$tmp,$configB,$privateB,
        "require \$ROOT.'/app/leden/lidmaatschap.php'; \$ok=lidmaatschapSchrijf([['id'=>'tenanttype','labels'=>['nl'=>'Tenanttype'],'actief'=>true,'leeftijd_min'=>null,'leeftijd_max'=>null,'jaarbedrag'=>42,'inschrijfgeld'=>3,'pro_rata'=>true]]); echo json_encode(['ok'=>\$ok,'pad'=>lidmaatschapBestand(),'lees'=>lidmaatschapLees()]);");
    $m = json_decode($outMembership,true);
    check321content($codeMembership===0 && !empty($m['ok']) && ($m['pad']??'')===$privateB.'/public-content/lidmaatschapstypen.json', 'lidmaatschapstypen schrijven naar tenant-lokale publieke content');
    check321content(($m['lees']['types'][0]['id']??'')==='tenanttype', 'tenant leest eigen lidmaatschapstype terug');
    check321content(hash_file('sha256',$root.'/data/lidmaatschapstypen.json')===$membershipVoor, 'tenant tariefwrite wijzigt RC045 lidmaatschapscanary niet');

    $endpointBody = <<<'PHP'
$_SERVER['REQUEST_METHOD']='GET';
$_GET['key']='homepage';
http_response_code(200);
register_shutdown_function(static function(){echo "\nSTATUS=".http_response_code();});
include $ROOT.'/public-content.php';
PHP;
    [$codeEndpoint,$outEndpoint] = runTenant321content($root,$tmp,$configA,$privateA,$endpointBody);
    check321content($codeEndpoint===0 && str_contains($outEndpoint,'TENANT-A-CANARY') && str_contains($outEndpoint,'STATUS=200'), 'publiek endpoint serveert alleen actieve tenantdataset');

    $missingBody = <<<'PHP'
$_SERVER['REQUEST_METHOD']='GET';
$_GET['key']='lidmaatschapstypen';
http_response_code(200);
register_shutdown_function(static function(){echo "STATUS=".http_response_code();});
include $ROOT.'/public-content.php';
PHP;
    [$codeMissing,$outMissing] = runTenant321content($root,$tmp,$configA,$privateA,$missingBody);
    check321content(
        $codeMissing===0 && trim($outMissing)==='[]STATUS=200',
        'publiek endpoint geeft lege 200-dataset voor ontbrekende tenantcontent zonder legacy fallback'
    );

    $traversalBody = <<<'PHP'
$_SERVER['REQUEST_METHOD']='GET';
$_GET['key']='../auth/users';
http_response_code(200);
register_shutdown_function(static function(){echo "STATUS=".http_response_code();});
include $ROOT.'/public-content.php';
PHP;
    [$codeTraversal,$outTraversal] = runTenant321content($root,$tmp,$configA,$privateA,$traversalBody);
    check321content($codeTraversal===0 && trim($outTraversal)==='STATUS=404', 'publiek endpoint weigert niet-whitelisted/traversal key');

    $configZonder = $tmp . '/zonder-private.php';
    tenantConfig321content($configZonder,'tenant-zonder',$tmp.'/niet-gebruikt',false);
    [$codeZonder,$outZonder] = runTenant321content($root,$tmp,$configZonder,null,
        "require \$ROOT.'/app/content/public-content-store.php'; echo publicContentPad('homepage');");
    check321content($codeZonder!==0 && stripos($outZonder,'private_root')!==false, 'externe tenant zonder private_root faalt gesloten in plaats van /data te gebruiken');

    $htaccess = (string) file_get_contents($root.'/.htaccess');
    check321content(str_contains($htaccess,'public-content.php?key=$1'), 'legacy /data JSON-routes lopen via publiek tenantendpoint');
    check321content(str_contains($htaccess,'lidmaatschapstypen|changelog'), 'rewrite omvat ook publieke tarief- en changelogdata');
} finally {
    rrmdir321content($tmp);
}

echo "Phase 3.2.1 public content isolation: {$ok} OK, {$fout} fout(en)\n";
exit($fout===0?0:1);
