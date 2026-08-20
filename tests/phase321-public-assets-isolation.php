<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function check321assets(bool $cond, string $label): void
{
    global $ok, $fout;
    if ($cond) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

function rrmdir321assets(string $pad): void
{
    if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        rrmdir321assets($pad . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($pad);
}

function tenantConfig321assets(string $pad, string $key, string $privateRoot, bool $metPrivateRoot = true): void
{
    $config = [
        'vereniging' => [
            'sleutel'=>$key,'naam'=>$key,'volledige_naam'=>$key,
            'site_url'=>'https://'.$key.'.example','timezone'=>'Europe/Amsterdam','standaard_taal'=>'nl',
        ],
        'opslag' => [
            'private_driver'=>'json','private_root'=>$metPrivateRoot?$privateRoot:'',
            'pdo'=>['dsn'=>'','user'=>'','password'=>''],
        ],
    ];
    file_put_contents($pad, "<?php\nreturn " . var_export($config, true) . ";\n");
}

function runTenant321assets(string $root, string $tmp, string $configPad, ?string $privateRoot, string $body): array
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
    exec(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($launcher) . ' 2>&1', $out, $exitCode);
    @unlink($launcher);
    return [$exitCode, implode("\n", $out)];
}

require_once $root . '/app/content/public-asset-store.php';
require_once $root . '/app/content/public-content-store.php';

$assetDefs = publicAssetDefinities();
check321assets(array_keys($assetDefs) === ['fotoboek','sponsors'], 'publieke assetstore heeft alleen fotoboek en sponsors als namespaces');
check321assets(publicAssetRelatiefPad('sponsors','logo.jpg') === 'logo.jpg', 'geldig sponsorbestand wordt geaccepteerd');
check321assets(publicAssetRelatiefPad('fotoboek','zomer-2026/thumbs/foto-1.jpg') !== null, 'geldig fotoboekthumbnailpad wordt geaccepteerd');
check321assets(publicAssetRelatiefPad('fotoboek','../auth/users.json') === null, 'traversal naar private data wordt geweigerd');
check321assets(publicAssetRelatiefPad('fotoboek','album/../../config.php') === null, 'meervoudige traversal wordt geweigerd');
check321assets(publicAssetRelatiefPad('sponsors','shell.php') === null, 'uitvoerbare PHP-extensie wordt geweigerd');
check321assets(publicAssetRelatiefPad('sponsors','logo.svg') === null, 'niet-whitelisted SVG wordt geweigerd');
check321assets(publicAssetRelatiefPad('fotoboek','album/file.html') === null, 'HTML-upload kan niet publiek worden geserveerd');
check321assets(publicAssetNamespaceRoot('sponsors') === $root.'/images/sponsors', 'standalone RC045 behoudt bestaande sponsorassetmap');
check321assets(publicAssetNamespaceRoot('fotoboek') === $root.'/images/fotoboek', 'standalone RC045 behoudt bestaande fotoboekassetmap');

$contentDefs = publicContentDefinities();
check321assets(isset($contentDefs['media'],$contentDefs['media-pagina'],$contentDefs['fotoboek'],$contentDefs['fotoboek-pagina']), 'media- en fotoboekmetadata vallen onder tenantcontentstore');

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rc045-phase321-assets-' . bin2hex(random_bytes(4));
$tenantA = $tmp . '/tenant-a';
$tenantB = $tmp . '/tenant-b';
$privateA = $tenantA . '/private';
$privateB = $tenantB . '/private';
@mkdir($privateA,0750,true);
@mkdir($privateB,0750,true);
$configA = $tenantA . '/config.php';
$configB = $tenantB . '/config.php';
tenantConfig321assets($configA,'tenant-a',$privateA);
tenantConfig321assets($configB,'tenant-b',$privateB);

try {
    [$codeCreateA,$outCreateA] = runTenant321assets($root,$tmp,$configA,$privateA, <<<'PHP'
require $ROOT.'/app/content/public-asset-store.php';
$s=publicAssetMaakNamespaceMap('sponsors');
$f=publicAssetMaakNamespaceMap('fotoboek');
@mkdir($f.'/album-a/thumbs',0750,true);
file_put_contents($s.'/sponsor-a.jpg','TENANT-A-SPONSOR');
file_put_contents($f.'/album-a/foto-a.jpg','TENANT-A-FULL');
file_put_contents($f.'/album-a/thumbs/foto-a.jpg','TENANT-A-THUMB');
file_put_contents($f.'/album-a/video-a.mp4','0123456789ABCDEF');
foreach([$s.'/sponsor-a.jpg',$f.'/album-a/foto-a.jpg',$f.'/album-a/thumbs/foto-a.jpg',$f.'/album-a/video-a.mp4'] as $p) publicAssetBeveiligBestand($p);
echo json_encode(['s'=>$s,'f'=>$f,'read'=>publicAssetVeiligLeesPad('sponsors','sponsor-a.jpg')]);
PHP);
    $createA = json_decode($outCreateA,true);
    check321assets($codeCreateA===0 && ($createA['s']??'')===$privateA.'/public-assets/sponsors', 'tenant A sponsorassets landen uitsluitend in eigen private root');
    check321assets(($createA['f']??'')===$privateA.'/public-assets/fotoboek', 'tenant A fotoboekassets landen uitsluitend in eigen private root');
    check321assets(($createA['read']??'')===$privateA.'/public-assets/sponsors/sponsor-a.jpg', 'tenant A kan eigen sponsorasset veilig resolveren');

    [$codeB,$outB] = runTenant321assets($root,$tmp,$configB,$privateB, <<<'PHP'
require $ROOT.'/app/content/public-asset-store.php';
echo json_encode([
 's'=>publicAssetNamespaceRoot('sponsors'),
 'f'=>publicAssetNamespaceRoot('fotoboek'),
 'aSponsor'=>publicAssetVeiligLeesPad('sponsors','sponsor-a.jpg'),
 'aFoto'=>publicAssetVeiligLeesPad('fotoboek','album-a/foto-a.jpg'),
]);
PHP);
    $b = json_decode($outB,true);
    check321assets($codeB===0 && ($b['s']??'')===$privateB.'/public-assets/sponsors', 'tenant B resolveert eigen sponsorroot');
    check321assets(($b['f']??'')===$privateB.'/public-assets/fotoboek', 'tenant B resolveert eigen fotoboekroot');
    check321assets(array_key_exists('aSponsor',$b) && $b['aSponsor']===null, 'tenant B kan sponsorasset van tenant A niet lezen');
    check321assets(array_key_exists('aFoto',$b) && $b['aFoto']===null, 'tenant B kan fotoboekasset van tenant A niet lezen');

    [$codeContentB,$outContentB] = runTenant321assets($root,$tmp,$configB,$privateB, <<<'PHP'
require $ROOT.'/app/content/public-content-store.php';
echo json_encode(['media'=>publicContentLees('media'),'foto'=>publicContentLees('fotoboek'),'mediaPad'=>publicContentPad('media'),'fotoPad'=>publicContentPad('fotoboek')]);
PHP);
    $cb = json_decode($outContentB,true);
    check321assets($codeContentB===0 && $cb['media']===null && $cb['foto']===null, 'lege tenant B erft geen media- of fotoboekmetadata');
    check321assets(($cb['mediaPad']??'')===$privateB.'/public-content/media.json' && ($cb['fotoPad']??'')===$privateB.'/public-content/fotoboek.json', 'media- en fotoboekmetadata wijzen fysiek naar tenant B');

    $endpointBody = <<<'PHP'
$_SERVER['REQUEST_METHOD']='GET';
$_GET['scope']='sponsors';
$_GET['path']='sponsor-a.jpg';
http_response_code(200);
register_shutdown_function(static function(){echo "\nSTATUS=".http_response_code();});
include $ROOT.'/public-asset.php';
PHP;
    [$codeEndpointA,$outEndpointA] = runTenant321assets($root,$tmp,$configA,$privateA,$endpointBody);
    check321assets($codeEndpointA===0 && str_contains($outEndpointA,'TENANT-A-SPONSOR') && str_contains($outEndpointA,'STATUS=200'), 'assetgateway serveert alleen asset van actieve tenant');

    [$codeEndpointB,$outEndpointB] = runTenant321assets($root,$tmp,$configB,$privateB,$endpointBody);
    check321assets($codeEndpointB===0 && !str_contains($outEndpointB,'TENANT-A-SPONSOR') && str_contains($outEndpointB,'STATUS=404'), 'assetgateway valt voor tenant B niet terug op tenant A');

    $invalidEndpoint = <<<'PHP'
$_SERVER['REQUEST_METHOD']='GET';
$_GET['scope']='sponsors';
$_GET['path']='shell.php';
http_response_code(200);
register_shutdown_function(static function(){echo "STATUS=".http_response_code();});
include $ROOT.'/public-asset.php';
PHP;
    [$codeInvalid,$outInvalid] = runTenant321assets($root,$tmp,$configA,$privateA,$invalidEndpoint);
    check321assets($codeInvalid===0 && trim($outInvalid)==='Bestand niet gevonden.STATUS=404', 'assetgateway weigert niet-whitelisted uitvoerbare extensie');

    $rangeBody = <<<'PHP'
$_SERVER['REQUEST_METHOD']='GET';
$_SERVER['HTTP_RANGE']='bytes=2-5';
$_GET['scope']='fotoboek';
$_GET['path']='album-a/video-a.mp4';
http_response_code(200);
register_shutdown_function(static function(){echo "\nSTATUS=".http_response_code();});
include $ROOT.'/public-asset.php';
PHP;
    [$codeRange,$outRange] = runTenant321assets($root,$tmp,$configA,$privateA,$rangeBody);
    check321assets($codeRange===0 && str_starts_with($outRange,'2345') && str_contains($outRange,'STATUS=206'), 'MP4 gateway ondersteunt begrensde byte-range zonder ander bestand te lezen');

    $outside = $tmp.'/outside';
    @mkdir($outside,0750,true);
    file_put_contents($outside.'/secret.jpg','OUTSIDE-SECRET');
    @mkdir($privateA.'/public-assets/sponsors',0750,true);
    $symlinkOk = @symlink($outside.'/secret.jpg',$privateA.'/public-assets/sponsors/link.jpg');
    if ($symlinkOk) {
        [$codeLink,$outLink] = runTenant321assets($root,$tmp,$configA,$privateA, <<<'PHP'
require $ROOT.'/app/content/public-asset-store.php';
var_export(publicAssetVeiligLeesPad('sponsors','link.jpg'));
PHP);
        check321assets($codeLink===0 && trim($outLink)==='NULL', 'symlinkasset naar buiten tenantroot wordt niet geserveerd');
    } else {
        check321assets(true, 'symlinkassettest overgeslagen omdat platform geen symlink toestaat');
    }

    $deleteRoot = $privateA.'/public-assets/fotoboek/delete-test';
    @mkdir($deleteRoot.'/thumbs',0750,true);
    file_put_contents($deleteRoot.'/eigen.jpg','EIGEN');
    $deleteOutside = $tmp.'/delete-outside';
    @mkdir($deleteOutside,0750,true);
    file_put_contents($deleteOutside.'/behouden.txt','CANARY-BEHOUDEN');
    $deleteLink = @symlink($deleteOutside,$deleteRoot.'/link-buiten');
    [$codeDelete,$outDelete] = runTenant321assets($root,$tmp,$configA,$privateA,
        "require \$ROOT.'/beheer/fotoboek-lib.php'; fbVerwijderMap(".var_export($deleteRoot,true)."); echo is_file(".var_export($deleteOutside.'/behouden.txt',true).")?'BEHOUDEN':'VERWIJDERD';");
    check321assets($codeDelete===0 && trim($outDelete)==='BEHOUDEN' && !file_exists($deleteRoot), 'recursief album verwijderen volgt geen symlink buiten tenantroot');

    $configZonder = $tmp.'/zonder-private.php';
    tenantConfig321assets($configZonder,'tenant-zonder',$tmp.'/niet-gebruikt',false);
    [$codeZonder,$outZonder] = runTenant321assets($root,$tmp,$configZonder,null,
        "require \$ROOT.'/app/content/public-asset-store.php'; echo publicAssetNamespaceRoot('sponsors');");
    check321assets($codeZonder!==0 && stripos($outZonder,'private_root')!==false, 'externe tenant zonder private_root faalt gesloten voor assets');

    $htaccess = (string) file_get_contents($root.'/.htaccess');
    check321assets(str_contains($htaccess,'public-asset.php?scope=sponsors&path=$1'), 'sponsor-URL loopt via whitelisted assetgateway');
    check321assets(str_contains($htaccess,'public-asset.php?scope=fotoboek&path=$1/$2$3'), 'fotoboek-URL loopt via whitelisted assetgateway');
    check321assets(str_contains($htaccess,'media|media-pagina|fotoboek|fotoboek-pagina'), 'media- en fotoboek-JSON lopen via tenantcontentgateway');

    $bronFotoboek = (string) file_get_contents($root.'/beheer/fotoboek.php');
    $bronFbLib = (string) file_get_contents($root.'/beheer/fotoboek-lib.php');
    $bronSponsors = (string) file_get_contents($root.'/beheer/sponsors.php');
    $bronMedia = (string) file_get_contents($root.'/beheer/media.php');
    check321assets(str_contains($bronFotoboek,"publicContentPad('fotoboek')") && str_contains($bronFotoboek,"publicAssetMaakNamespaceMap('fotoboek')"), 'fotoboekbeheer gebruikt tenantcontent en tenantassets');
    check321assets(!str_contains($bronFotoboek,"$root.'/images/fotoboek'") && str_contains($bronFotoboek,'publicAssetTenantRoot()===null'), 'fotoboekbeheer heeft geen hardcoded gedeelde uploadroot en geen RC045-logo voor externe tenant');
    check321assets(str_contains($bronFbLib,'is_link($p)') && str_contains($bronFbLib,'publicAssetBeveiligBestand'), 'fotoboeklib bewaakt symlinks en tenantbestandsrechten');
    check321assets(str_contains($bronSponsors,"publicAssetMaakNamespaceMap('sponsors')") && str_contains($bronSponsors,'is_uploaded_file($tmp)'), 'sponsorbeheer gebruikt tenantassets en echte HTTP-uploadvalidatie');
    check321assets(str_contains($bronMedia,"publicContentPad('media')") && str_contains($bronMedia,'publicContentTenantRoot() === null ? ['), 'mediabeheer gebruikt tenantcontent en beperkt RC045 fallback tot standalone');
} finally {
    rrmdir321assets($tmp);
}

echo "Phase 3.2.1 public assets isolation: {$ok} OK, {$fout} fout(en)\n";
exit($fout===0?0:1);
