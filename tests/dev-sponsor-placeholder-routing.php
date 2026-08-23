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
    'RewriteRule ^images/(?!sponsors/)(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9][A-Za-z0-9._-]{0,180}\\.(?:jpe?g|png|webp|gif|svg)$ images/template-placeholder.svg [L,NC]',
    'RewriteRule ^images/sponsors/([A-Za-z0-9][A-Za-z0-9._-]{0,180})$ public-asset.php?scope=sponsors&path=$1 [L,QSA,NE]',
];
foreach ($required as $needle) {
    if (!str_contains($htaccess, $needle)) $errors[] = 'ontbrekend contract: ' . $needle;
}

if (str_contains($htaccess, '^/dev/images/sponsors/') || str_contains($htaccess, '[R=302,L,NC,NE,QSD]')) {
    $errors[] = 'DEV sponsorassets mogen niet meer via de oude 302-placeholderroute lopen';
}
if (!str_contains($gateway, "if (\$pad === null && \$scope === 'sponsors')")
    || !str_contains($gateway, 'publicAssetStandaloneSponsorPlaceholder()')
    || !str_contains($gateway, "\$mime = 'image/svg+xml';")) {
    $errors[] = 'assetgateway mist de directe standalone sponsorplaceholderresponse';
}
if (!str_contains($store, 'function publicAssetStandaloneSponsorPlaceholder(): ?string')
    || !str_contains($store, 'if (publicAssetTenantRoot() !== null) return null;')) {
    $errors[] = 'placeholderhelper is niet aantoonbaar beperkt tot standalone/DEV';
}

// Bewijs ook functioneel dat een geldig maar ontbrekend standalone sponsorlogo
// direct als HTTP 200 SVG uit de gateway komt, zonder redirectketen.
$tmp = sys_get_temp_dir() . '/rc045-sponsor-gateway-' . bin2hex(random_bytes(5)) . '.php';
$runner = "<?php\n"
    . "putenv('VERENIGING_REQUIRE_TENANT_CONFIG');\n"
    . "putenv('VERENIGING_CONFIG_FILE');\n"
    . "putenv('VERENIGING_PRIVATE_ROOT');\n"
    . "\$_SERVER['REQUEST_METHOD']='GET';\n"
    . "\$_GET['scope']='sponsors';\n"
    . "\$_GET['path']='ontbreekt-" . bin2hex(random_bytes(4)) . ".png';\n"
    . "http_response_code(200);\n"
    . "register_shutdown_function(static function(){echo \"\\nSTATUS=\".http_response_code();});\n"
    . "include " . var_export($root . '/public-asset.php', true) . ";\n";
file_put_contents($tmp, $runner);
$out = [];
$code = 255;
exec(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
@unlink($tmp);
$body = implode("\n", $out);
if ($code !== 0 || !str_contains($body, '<svg') || !str_contains($body, 'STATUS=200')) {
    $errors[] = 'ontbrekend standalone sponsorlogo wordt niet direct als HTTP 200 SVG geserveerd';
}

if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, "FOUT: {$error}\n");
    exit(1);
}

echo "OK: DEV sponsorplaceholder loopt direct via tenantbewuste assetgateway zonder redirectketen\n";
