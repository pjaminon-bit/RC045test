<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;
function secOk(bool $cond, string $label): void {
    global $ok, $fout;
    if ($cond) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

$allowedProcOpen = [
    'app/deployment/process-runner.php',
];
$forbiddenCalls = [
    'eval(' => 'eval',
    'shell_exec(' => 'shell_exec',
    'passthru(' => 'passthru',
    'popen(' => 'popen',
    'pcntl_exec(' => 'pcntl_exec',
    'unserialize(' => 'unserialize',
    'create_function(' => 'create_function',
];
$secretPatterns = [
    '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/' => 'private key',
    '/\bAKIA[0-9A-Z]{16}\b/' => 'AWS access key',
    '/\bgh[pousr]_[A-Za-z0-9_]{20,}\b/' => 'GitHub token',
    '/\bxox[baprs]-[A-Za-z0-9-]{20,}\b/' => 'Slack token',
];

$productionPhp = [];
$allText = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $info) {
    if (!$info->isFile() || $info->isLink()) continue;
    $path = $info->getPathname();
    $rel = str_replace('\\', '/', substr($path, strlen($root) + 1));
    if (str_starts_with($rel, '.git/') || str_starts_with($rel, 'node_modules/') || str_starts_with($rel, 'vendor/')) continue;
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    if (in_array($ext, ['php','js','json','yml','yaml','md','txt','conf','htaccess','sh'], true) || basename($rel) === '.htaccess') {
        $raw = @file_get_contents($path);
        if (is_string($raw)) $allText[$rel] = $raw;
    }
    if ($ext === 'php' && !str_starts_with($rel, 'tests/')) {
        $raw = @file_get_contents($path);
        if (is_string($raw)) $productionPhp[$rel] = $raw;
    }
}

$danger = [];
foreach ($productionPhp as $rel => $raw) {
    foreach ($forbiddenCalls as $needle => $name) {
        if (stripos($raw, $needle) !== false) $danger[] = "{$rel}:{$name}";
    }
    if (stripos($raw, 'proc_open(') !== false && !in_array($rel, $allowedProcOpen, true)) {
        $danger[] = "{$rel}:proc_open buiten centrale runner";
    }
    $tokens = token_get_all($raw);
    foreach ($tokens as $token) {
        if (is_string($token) && $token === '`') { $danger[] = "{$rel}:backtick shell"; break; }
    }
}
secOk($danger === [], 'productie-PHP bevat geen verboden proces-/deserialisatieprimitieven buiten de centrale runner');
if ($danger !== []) foreach ($danger as $d) fwrite(STDERR, "  {$d}\n");

$secrets = [];
foreach ($allText as $rel => $raw) {
    if (str_starts_with($rel, 'tests/') || str_starts_with($rel, 'docs/')) continue;
    foreach ($secretPatterns as $pattern => $name) {
        if (preg_match($pattern, $raw) === 1) $secrets[] = "{$rel}:{$name}";
    }
}
secOk($secrets === [], 'repository bevat geen herkenbare private keys of bekende tokenformaten in runtimebestanden');
if ($secrets !== []) foreach ($secrets as $s) fwrite(STDERR, "  {$s}\n");

$runner = $productionPhp['app/deployment/process-runner.php'] ?? '';
secOk(str_contains($runner, "['bypass_shell' => true]") && str_contains($runner, 'stream_select('), 'centrale subprocess-runner blijft shellvrij en deadlock-veilig');
secOk(str_contains($runner, 'Privileged subprocess vereist een absoluut executablepad.'), 'centrale subprocess-runner weigert PATH-executables fail-closed');

$ht = $allText['.htaccess'] ?? '';
secOk(str_contains($ht, 'RewriteRule') && str_contains($ht, '[F,L]'), '.htaccess bevat fail-closed blokkeringsregels');
secOk(!str_contains($ht, 'rc045.nl') || str_contains($ht, '#'), '.htaccess dwingt geen vaste RC045-hostredirect af');

$auth = $productionPhp['auth.php'] ?? '';
$masterStart = strpos($auth, 'function authMasterWachtwoordKlopt');
$masterEind = $masterStart === false ? false : strpos($auth, '// ===== Uitloggen =====', $masterStart);
$masterBron = ($masterStart !== false && $masterEind !== false) ? substr($auth, $masterStart, $masterEind - $masterStart) : '';
secOk(
    $masterBron !== ''
    && str_contains($masterBron, 'password_verify')
    && str_contains($masterBron, '$BEHEER_WACHTWOORD_HASH')
    && preg_match('/\$BEHEER_WACHTWOORD(?!_HASH)\b/', $masterBron) !== 1
    && !str_contains($masterBron, 'hash_equals'),
    'masterauth gebruikt uitsluitend password_verify tegen een gehashte credential'
);
secOk(
    str_contains($auth, '$configOk = $beheerHashOk && !$beheerHeeftPlaintext')
    && str_contains($auth, 'unset($BEHEER_WACHTWOORD)')
    && !str_contains($auth, '$configOk = $beheerHashOk || $beheerLegacyOk'),
    'plaintext masterconfig wordt voor alle installaties fail-closed geweigerd'
);
secOk(str_contains($auth, 'hash_equals') && str_contains($auth, 'password_verify'), 'overige authenticatie behoudt constant-time vergelijking en password_verify');
secOk(str_contains($auth, 'session_regenerate_id'), 'authenticatie roteert sessie-id');

$sponsors = $productionPhp['beheer/sponsors.php'] ?? '';
secOk(
    str_contains($sponsors, 'is_uploaded_file')
    && (str_contains($sponsors, 'getimagesize') || str_contains($sponsors, 'finfo')),
    'sponsorupload valideert echt HTTP-uploadbestand en server-side afbeeldingstype'
);

$fotoboek = $productionPhp['beheer/fotoboek.php'] ?? '';
$fotoboekLib = $productionPhp['beheer/fotoboek-lib.php'] ?? '';
secOk(
    str_contains($fotoboek, "require_once __DIR__ . '/fotoboek-lib.php'")
    && str_contains($fotoboek, 'UPLOAD_ERR_OK')
    && str_contains($fotoboek, 'is_uploaded_file($tmp)')
    && str_contains($fotoboek, '$maxFoto=25*1024*1024'),
    'Fotoboek entrypoint vereist geldige HTTP-upload en begrenst bestandsgrootte vóór beeldverwerking'
);
secOk(
    str_contains($fotoboekLib, 'getimagesize($tmp)')
    && str_contains($fotoboekLib, '>16000')
    && str_contains($fotoboekLib, '>60000000')
    && str_contains($fotoboekLib, 'IMAGETYPE_JPEG')
    && str_contains($fotoboekLib, 'IMAGETYPE_PNG')
    && str_contains($fotoboekLib, 'IMAGETYPE_WEBP'),
    'Fotoboek library valideert type, dimensies en image-bombgrenzen vóór decode'
);
secOk(
    str_contains($fotoboekLib, 'imagecreatefromjpeg($tmp)')
    && str_contains($fotoboekLib, 'imagecreatefrompng($tmp)')
    && str_contains($fotoboekLib, 'imagecreatefromwebp($tmp)')
    && str_contains($fotoboekLib, 'imagejpeg($full,$vol,82)')
    && str_contains($fotoboekLib, 'imagejpeg($th,$thumb,78)'),
    'Fotoboek decodeert alleen toegestane formaten en herbouwt fullsize en thumbnail als server-JPEG'
);

echo "Security source regression: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
