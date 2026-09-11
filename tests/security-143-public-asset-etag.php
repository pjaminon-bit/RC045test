<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function check143(bool $cond, string $label): void
{
    global $ok, $fout;
    if ($cond) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

function rrmdir143(string $pad): void
{
    if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        rrmdir143($pad . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($pad);
}

function request143(string $url, string $method = 'GET', array $headers = []): array
{
    $context = stream_context_create(['http' => [
        'method' => $method,
        'ignore_errors' => true,
        'timeout' => 5,
        'header' => implode("\r\n", $headers),
    ]]);
    $body = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $status = 0;
    $parsed = [];
    foreach ($responseHeaders as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $match) === 1) { $status = (int)$match[1]; continue; }
        $colon = strpos($line, ':');
        if ($colon === false) continue;
        $parsed[strtolower(trim(substr($line, 0, $colon)))] = trim(substr($line, $colon + 1));
    }
    return ['status'=>$status, 'body'=>$body === false ? '' : $body, 'headers'=>$parsed];
}

function header143(array $response, string $naam): ?string
{
    $waarde = $response['headers'][strtolower($naam)] ?? null;
    return is_string($waarde) ? $waarde : null;
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rc045-security-143-' . bin2hex(random_bytes(4));
$privateRoot = $tmp . '/private';
$assetRoot = $privateRoot . '/public-assets/fotoboek/etag-test';
$configPad = $tmp . '/tenant-config.php';
$serverLog = $tmp . '/php-server.log';
@mkdir($assetRoot, 0750, true);

$config = [
    'vereniging' => [
        'sleutel'=>'etag-test', 'naam'=>'ETag test', 'volledige_naam'=>'ETag test',
        'site_url'=>'http://127.0.0.1', 'timezone'=>'Europe/Amsterdam', 'standaard_taal'=>'nl',
    ],
    'opslag' => [
        'private_driver'=>'json', 'private_root'=>$privateRoot,
        'pdo'=>['dsn'=>'', 'user'=>'', 'password'=>''],
    ],
];
file_put_contents($configPad, "<?php\nreturn " . var_export($config, true) . ";\n");

$inhoudA = '0123456789ABCDEF';
$inhoudB = 'FEDCBA9876543210';
$padA = $assetRoot . '/a.mp4';
$padB = $assetRoot . '/b.mp4';
file_put_contents($padA, $inhoudA);
file_put_contents($padB, $inhoudB);
$gelijkeMtime = 1760000000;
touch($padA, $gelijkeMtime);
touch($padB, $gelijkeMtime);
clearstatcache(true);

check143(filesize($padA) === filesize($padB), 'testassets A en B hebben exact dezelfde bytegrootte');
check143(filemtime($padA) === filemtime($padB), 'testassets A en B hebben exact dezelfde mtime');
check143(file_get_contents($padA) !== file_get_contents($padB), 'testassets A en B bevatten aantoonbaar verschillende bytes');

$socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if ($socket === false) { rrmdir143($tmp); fwrite(STDERR, "FOUT: vrije lokale testpoort kon niet worden gereserveerd: {$errstr}\n"); exit(1); }
$socketName = (string)stream_socket_get_name($socket, false);
fclose($socket);
$colon = strrpos($socketName, ':');
$port = $colon === false ? 0 : (int)substr($socketName, $colon + 1);
if ($port < 1) { rrmdir143($tmp); fwrite(STDERR, "FOUT: ongeldige lokale testpoort\n"); exit(1); }

$env = getenv(); if (!is_array($env)) $env = [];
$env['VERENIGING_REQUIRE_TENANT_CONFIG'] = '1';
$env['VERENIGING_CONFIG_FILE'] = $configPad;
$env['VERENIGING_PRIVATE_ROOT'] = $privateRoot;
$descriptors = [0=>['pipe','r'], 1=>['file',$serverLog,'a'], 2=>['file',$serverLog,'a']];
$server = @proc_open([PHP_BINARY, '-d', 'display_errors=0', '-S', '127.0.0.1:' . $port, '-t', $root], $descriptors, $pipes, $root, $env, ['bypass_shell'=>true]);
if (!is_resource($server)) { rrmdir143($tmp); fwrite(STDERR, "FOUT: lokale PHP-testserver kon niet starten\n"); exit(1); }
if (isset($pipes[0]) && is_resource($pipes[0])) fclose($pipes[0]);

try {
    $gereed = false;
    for ($poging=0; $poging<50; $poging++) {
        $probe = @fsockopen('127.0.0.1', $port, $probeErrno, $probeErrstr, 0.1);
        if (is_resource($probe)) { fclose($probe); $gereed = true; break; }
        usleep(50000);
    }
    check143($gereed, 'lokale public-asset HTTP-testserver start');

    if ($gereed) {
        $url = static fn(string $bestand): string => 'http://127.0.0.1:' . $port . '/public-asset.php?' . http_build_query([
            'scope'=>'fotoboek', 'path'=>'etag-test/' . $bestand,
        ]);
        $cache = 'public, max-age=3600, stale-while-revalidate=86400';

        $getA = request143($url('a.mp4'));
        $getB = request143($url('b.mp4'));
        $etagA = header143($getA, 'ETag');
        $etagB = header143($getB, 'ETag');
        check143($getA['status'] === 200 && $getA['body'] === $inhoudA, 'normale GET serveert asset A volledig');
        check143($getB['status'] === 200 && $getB['body'] === $inhoudB, 'normale GET serveert asset B volledig');
        check143(is_string($etagA) && preg_match('/^"asset-v1-[0-9a-f]{64}"$/D', $etagA) === 1, 'ETag gebruikt de sterke versiegebonden assetvalidator');
        check143(is_string($etagA) && is_string($etagB) && !hash_equals($etagA, $etagB), 'verschillende content met gelijke size en mtime krijgt verschillende ETags');

        $notModified = request143($url('a.mp4'), 'GET', ['If-None-Match: ' . $etagA]);
        check143($notModified['status'] === 304 && $notModified['body'] === '', 'If-None-Match geeft 304 voor ongewijzigde assetversie');
        check143(header143($notModified, 'ETag') === $etagA, '304 behoudt dezelfde validator');
        check143(header143($notModified, 'Cache-Control') === $cache, '304 behoudt bestaande cachepolicy');

        $head = request143($url('a.mp4'), 'HEAD');
        check143($head['status'] === 200 && $head['body'] === '', 'HEAD blijft bodyloos en succesvol');
        check143(header143($head, 'ETag') === $etagA, 'HEAD gebruikt dezelfde validator als GET');
        check143(header143($head, 'Content-Length') === (string)strlen($inhoudA), 'HEAD behoudt correcte Content-Length');
        check143(header143($head, 'Accept-Ranges') === 'bytes', 'HEAD behoudt byte-range advertentie');

        $range = request143($url('a.mp4'), 'GET', ['Range: bytes=2-5']);
        check143($range['status'] === 206 && $range['body'] === substr($inhoudA, 2, 4), 'Range-request blijft correcte 206-body leveren');
        check143(header143($range, 'Content-Range') === 'bytes 2-5/' . strlen($inhoudA), '206 behoudt correcte Content-Range');
        check143(header143($range, 'ETag') === $etagA, '206 gebruikt validator van de volledige representatie');

        // Zelfde publieke pad, andere bytes, exact dezelfde size en mtime. De
        // sibling-tempfile bestaat gelijktijdig met de oude inode en wordt
        // daarna atomair geactiveerd, conform het productie-writercontract.
        $vervanging = $padA . '.tmp.replace';
        file_put_contents($vervanging, $inhoudB);
        touch($vervanging, $gelijkeMtime);
        check143(filesize($vervanging) === strlen($inhoudA), 'vervangingsasset houdt exact dezelfde grootte');
        check143(filemtime($vervanging) === $gelijkeMtime, 'vervangingsasset houdt exact dezelfde mtime');
        check143(@rename($vervanging, $padA), 'same-path assetvervanging wordt atomair geactiveerd');
        clearstatcache(true, $padA);

        $naVervanging = request143($url('a.mp4'), 'GET', ['If-None-Match: ' . $etagA]);
        $etagNieuw = header143($naVervanging, 'ETag');
        check143($naVervanging['status'] === 200 && $naVervanging['body'] === $inhoudB, 'oude validator veroorzaakt na replacement geen foutieve 304');
        check143(is_string($etagNieuw) && is_string($etagA) && !hash_equals($etagNieuw, $etagA), 'same-size/same-mtime replacement krijgt deterministisch nieuwe ETag');
        check143(header143($naVervanging, 'Cache-Control') === $cache, 'replacement behoudt bestaande cachepolicy');
    }
} finally {
    @proc_terminate($server);
    @proc_close($server);
    rrmdir143($tmp);
}

if ($fout > 0) { fwrite(STDERR, "security-143 public-asset ETag: {$fout} fout(en), {$ok} checks geslaagd\n"); exit(1); }
echo "security-143 public-asset ETag: {$ok} checks geslaagd\n";
