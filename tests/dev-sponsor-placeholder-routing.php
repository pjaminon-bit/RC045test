<?php
$root = dirname(__DIR__);
$htaccess = @file_get_contents($root . '/.htaccess');
$gateway = @file_get_contents($root . '/public-asset.php');
$png = $root . '/images/template-placeholder.png';
if (!is_string($htaccess) || !is_string($gateway)) {
    fwrite(STDERR, "FOUT: sponsorplaceholder-bronnen ontbreken\n");
    exit(1);
}

$errors = [];
$required = [
    'RewriteCond %{REQUEST_URI} ^/dev/images/sponsors/ [NC]',
    'RewriteCond %{REQUEST_FILENAME} !-f',
    'RewriteRule ^images/sponsors/[A-Za-z0-9][A-Za-z0-9._-]{0,180}\\.(?:jpe?g|png|webp)$ images/template-placeholder.png [L,NC]',
    'RewriteRule ^images/sponsors/([A-Za-z0-9][A-Za-z0-9._-]{0,180})$ public-asset.php?scope=sponsors&path=$1 [L,QSA,NE]',
];
foreach ($required as $needle) {
    if (!str_contains($htaccess, $needle)) $errors[] = 'ontbrekend contract: ' . $needle;
}

if (str_contains($htaccess, 'dev_placeholder=1')) {
    $errors[] = 'DEV sponsorrouting mag niet meer via een dynamische gatewaymarker lopen';
}
if (str_contains($htaccess, '[R=302,L,NC,NE,QSD]')) {
    $errors[] = 'DEV sponsorassets mogen niet via de oude 302-placeholderroute lopen';
}
if (str_contains($htaccess, 'images/template-placeholder.svg [L,NC]')
    && str_contains($htaccess, 'RewriteRule ^images/sponsors/')) {
    // De algemene DEV fallback mag SVG blijven gebruiken; alleen de specifieke
    // sponsorregel moet naar de echte PNG wijzen. Dit wordt hierboven exact geëist.
}

$pngInfo = is_file($png) ? @getimagesize($png) : false;
if (!is_array($pngInfo) || ($pngInfo['mime'] ?? '') !== 'image/png') {
    $errors[] = 'template-placeholder.png ontbreekt of is geen echte PNG';
}
$signature = is_file($png) ? @file_get_contents($png, false, null, 0, 8) : false;
if ($signature !== "\x89PNG\r\n\x1a\n") {
    $errors[] = 'template-placeholder.png heeft geen geldige PNG-signatuur';
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
    $errors[] = 'oude dev_placeholder marker mag via directe gatewayquery geen fallback activeren';
}

if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, "FOUT: {$error}\n");
    exit(1);
}

echo "OK: DEV sponsorplaceholder is een echte statische PNG; gateway blijft fail-closed\n";
