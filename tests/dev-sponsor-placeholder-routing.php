<?php
$root = dirname(__DIR__);
$htaccess = @file_get_contents($root . '/.htaccess');
$gateway = @file_get_contents($root . '/public-asset.php');
$store = @file_get_contents($root . '/app/content/public-asset-store.php');
if (!is_string($htaccess) || !is_string($gateway) || !is_string($store)) {
    fwrite(STDERR, "FOUT: sponsorplaceholder-bronnen ontbreken\n");
    exit(1);
}

$errors = [];
$required = [
    'RewriteCond %{REQUEST_URI} ^/dev/images/sponsors/ [NC]',
    'RewriteRule ^images/sponsors/([A-Za-z0-9][A-Za-z0-9._-]{0,180})$ public-asset.php?scope=sponsors&path=$1&dev_placeholder=1 [L,QSA,NE]',
    'RewriteRule ^images/(?!sponsors/)(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9][A-Za-z0-9._-]{0,180}\\.(?:jpe?g|png|webp|gif|svg)$ images/template-placeholder.svg [L,NC]',
    'RewriteRule ^images/sponsors/([A-Za-z0-9][A-Za-z0-9._-]{0,180})$ public-asset.php?scope=sponsors&path=$1 [L,QSA,NE]',
];
foreach ($required as $needle) {
    if (!str_contains($htaccess, $needle)) $errors[] = 'ontbrekend contract: ' . $needle;
}

if (str_contains($htaccess, '[R=302,L,NC,NE,QSD]')) {
    $errors[] = 'DEV sponsorassets mogen niet meer via de oude 302-placeholderroute lopen';
}
if (!str_contains($gateway, "\$devPlaceholder = isset(\$_GET['dev_placeholder'])")
    || !str_contains($gateway, "#^/dev/images/sponsors/")
    || !str_contains($gateway, 'hash_equals((string) $match[1], $relatief)')
    || !str_contains($gateway, "if (\$pad === null && \$scope === 'sponsors' && \$devSponsorRoute)")
    || !str_contains($gateway, 'publicAssetStandaloneSponsorPlaceholder()')
    || !str_contains($gateway, "\$mime = 'image/svg+xml';")) {
    $errors[] = 'assetgateway mist de strikt aan de DEV afbeeldingsroute gebonden placeholderresponse';
}
if (!str_contains($store, 'function publicAssetStandaloneSponsorPlaceholder(): ?string')
    || !str_contains($store, 'if (publicAssetTenantRoot() !== null) return null;')) {
    $errors[] = 'placeholderhelper is niet aantoonbaar beperkt tot standalone/DEV';
}

function sponsorGatewayRun(string $root, string $naam, string $requestUri, bool $marker): array
{
    $tmp = sys_get_temp_dir() . '/rc045-sponsor-gateway-' . bin2hex(random_bytes(5)) . '.php';
    $runner = "<?php\n"
        . "putenv('VERENIGING_REQUIRE_TENANT_CONFIG');\n"
        . "putenv('VERENIGING_CONFIG_FILE');\n"
        . "putenv('VERENIGING_PRIVATE_ROOT');\n"
        . "\$_SERVER['REQUEST_METHOD']='GET';\n"
        . "\$_SERVER['REQUEST_URI']=" . var_export($requestUri, true) . ";\n"
        . "\$_GET['scope']='sponsors';\n"
        . "\$_GET['path']=" . var_export($naam, true) . ";\n"
        . ($marker ? "\$_GET['dev_placeholder']='1';\n" : '')
        . "http_response_code(200);\n"
        . "register_shutdown_function(static function(){echo \"\\nSTATUS=\".http_response_code();});\n"
        . "include " . var_export($root . '/public-asset.php', true) . ";\n";
    file_put_contents($tmp, $runner);
    $out = [];
    $code = 255;
    exec(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
    @unlink($tmp);
    return [$code, implode("\n", $out)];
}

$naam = 'ontbreekt-' . bin2hex(random_bytes(4)) . '.png';
[$directCode, $directBody] = sponsorGatewayRun($root, $naam, '/dev/public-asset.php?scope=sponsors&path=' . rawurlencode($naam), false);
if ($directCode !== 0 || !str_contains($directBody, 'STATUS=404') || str_contains($directBody, '<svg')) {
    $errors[] = 'directe ontbrekende public-asset sponsorquery moet fail-closed HTTP 404 blijven';
}

[$spoofCode, $spoofBody] = sponsorGatewayRun($root, $naam, '/dev/public-asset.php?scope=sponsors&path=' . rawurlencode($naam) . '&dev_placeholder=1', true);
if ($spoofCode !== 0 || !str_contains($spoofBody, 'STATUS=404') || str_contains($spoofBody, '<svg')) {
    $errors[] = 'dev_placeholder querymarker zonder echte images/sponsors route mag geen fallback activeren';
}

[$routeCode, $routeBody] = sponsorGatewayRun($root, $naam, '/dev/images/sponsors/' . $naam . '?verify=1', true);
if ($routeCode !== 0 || !str_contains($routeBody, '<svg') || !str_contains($routeBody, 'STATUS=200')) {
    $errors[] = 'gemarkeerde echte DEV sponsorroute moet ontbrekend logo direct als HTTP 200 SVG serveren';
}

if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, "FOUT: {$error}\n");
    exit(1);
}

echo "OK: DEV sponsorplaceholder is direct, redirectloos en uitsluitend aan de echte DEV afbeeldingsroute gebonden\n";
