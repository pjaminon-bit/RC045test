<?php
$root = dirname(__DIR__);
$htaccess = @file_get_contents($root . '/.htaccess');
$publicContent = @file_get_contents($root . '/public-content.php');
$gateway = @file_get_contents($root . '/public-asset.php');
if (!is_string($htaccess) || !is_string($publicContent) || !is_string($gateway)) {
    fwrite(STDERR, "FOUT: DEV sponsorcontract-bronnen ontbreken\n");
    exit(1);
}

$errors = [];
$genericGateway = 'RewriteRule ^images/sponsors/([A-Za-z0-9][A-Za-z0-9._-]{0,180})$ public-asset.php?scope=sponsors&path=$1 [L,QSA,NE]';
if (!str_contains($htaccess, $genericGateway)) {
    $errors[] = 'generieke tenantbewuste sponsorgateway ontbreekt';
}
if (str_contains($htaccess, 'RewriteCond %{REQUEST_URI} ^/dev/images/sponsors/')) {
    $errors[] = 'DEV mag ontbrekende sponsoruploads niet meer via een Apache-special-case afhandelen';
}
if (str_contains($htaccess, 'dev_placeholder=1')
    || str_contains($htaccess, 'images/template-placeholder.png [R=302')
    || str_contains($htaccess, 'images/template-placeholder.png [L,NC]')) {
    $errors[] = 'legacy DEV sponsorplaceholderroute is nog aanwezig';
}

$requiredContent = [
    "\$sleutel === 'sponsors'",
    "preg_match('#^/dev(?:/|$)#', \$requestPad) === 1",
    '\$externPad === null',
    '!\$configVerplicht',
    "\$data['items'] = [];",
];
foreach ($requiredContent as $needle) {
    if (!str_contains($publicContent, $needle)) {
        $errors[] = 'DEV sponsorcontent-filter mist contract: ' . $needle;
    }
}

function sponsorPublicContentRun(string $root, string $requestUri): array
{
    $tmp = sys_get_temp_dir() . '/rc045-sponsor-content-' . bin2hex(random_bytes(5)) . '.php';
    $runner = "<?php\n"
        . "putenv('VERENIGING_REQUIRE_TENANT_CONFIG');\n"
        . "putenv('VERENIGING_CONFIG_FILE');\n"
        . "putenv('VERENIGING_PRIVATE_ROOT');\n"
        . "\$_SERVER['REQUEST_METHOD']='GET';\n"
        . "\$_SERVER['REQUEST_URI']=" . var_export($requestUri, true) . ";\n"
        . "\$_GET['key']='sponsors';\n"
        . "include " . var_export($root . '/public-content.php', true) . ";\n";
    file_put_contents($tmp, $runner);
    $out = [];
    $code = 255;
    exec(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
    @unlink($tmp);
    return [$code, implode("\n", $out)];
}

$dataDir = $root . '/data';
$sponsorFile = $dataDir . '/sponsors.json';
$hadDir = is_dir($dataDir);
$hadFile = is_file($sponsorFile);
$oldData = $hadFile ? @file_get_contents($sponsorFile) : false;

try {
    if (!$hadDir && !@mkdir($dataDir, 0750, true)) {
        throw new RuntimeException('tijdelijke data-map kon niet worden gemaakt');
    }
    $fixture = [
        'updated' => 'regression-fixture',
        'items' => [['name' => 'Fixture sponsor', 'logo' => 'fixture.png']],
        'cta' => ['nl' => 'Fixture CTA'],
    ];
    $json = json_encode($fixture, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || @file_put_contents($sponsorFile, $json) === false) {
        throw new RuntimeException('tijdelijke sponsorsfixture kon niet worden geschreven');
    }

    [$devCode, $devBody] = sponsorPublicContentRun($root, '/dev/data/sponsors.json');
    $devData = json_decode($devBody, true);
    if ($devCode !== 0 || !is_array($devData) || ($devData['items'] ?? null) !== []) {
        $errors[] = 'DEV publieke sponsorcontent moet tenant-uploaditems volledig onderdrukken';
    }
    if (($devData['cta']['nl'] ?? null) !== 'Fixture CTA') {
        $errors[] = 'DEV sponsorfilter mag CTA/overige publieke sponsorconfig niet verwijderen';
    }

    [$prodCode, $prodBody] = sponsorPublicContentRun($root, '/data/sponsors.json');
    $prodData = json_decode($prodBody, true);
    if ($prodCode !== 0 || !is_array($prodData) || count($prodData['items'] ?? []) !== 1) {
        $errors[] = 'standalone productie buiten /dev moet echte sponsoritems behouden';
    }
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
} finally {
    if ($hadFile && is_string($oldData)) {
        @file_put_contents($sponsorFile, $oldData);
    } elseif (!$hadFile) {
        @unlink($sponsorFile);
    }
    if (!$hadDir) @rmdir($dataDir);
}

if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, "FOUT: {$error}\n");
    exit(1);
}

echo "OK: DEV vraagt geen tenant-sponsoruploads aan; productie en tenantgateway blijven intact\n";
