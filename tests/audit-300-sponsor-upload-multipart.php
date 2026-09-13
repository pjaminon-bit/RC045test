<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function r300Check(bool $cond, string $label): void
{
    global $ok, $fout;
    if ($cond) {
        $ok++;
        echo "OK: {$label}\n";
        return;
    }
    $fout++;
    fwrite(STDERR, "FOUT: {$label}\n");
}

function r300Wis(string $pad): void
{
    if (is_link($pad) || is_file($pad)) {
        @unlink($pad);
        return;
    }
    if (!is_dir($pad)) return;
    foreach ((array) @scandir($pad) as $item) {
        if ($item === '.' || $item === '..') continue;
        r300Wis($pad . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($pad);
}

function r300VrijePoort(): int
{
    $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if ($server === false) throw new RuntimeException("Vrije lokale poort kon niet worden bepaald: {$errstr}");
    $naam = (string) stream_socket_get_name($server, false);
    fclose($server);
    $pos = strrpos($naam, ':');
    $poort = $pos === false ? 0 : (int) substr($naam, $pos + 1);
    if ($poort < 1024 || $poort > 65535) throw new RuntimeException('Ongeldige vrije lokale poort bepaald.');
    return $poort;
}

function r300Http(int $poort, string $methode, string $pad, array $headers = [], string $body = ''): array
{
    $socket = @stream_socket_client("tcp://127.0.0.1:{$poort}", $errno, $errstr, 5);
    if ($socket === false) throw new RuntimeException("HTTP-connectie faalde: {$errstr}");
    stream_set_timeout($socket, 15);

    $methode = strtoupper($methode);
    $regels = [
        "{$methode} {$pad} HTTP/1.1",
        "Host: 127.0.0.1:{$poort}",
        'Connection: close',
    ];
    foreach ($headers as $naam => $waarde) $regels[] = $naam . ': ' . $waarde;
    if ($methode === 'POST' || $body !== '') $regels[] = 'Content-Length: ' . strlen($body);
    $request = implode("\r\n", $regels) . "\r\n\r\n" . $body;

    $offset = 0;
    while ($offset < strlen($request)) {
        $geschreven = fwrite($socket, substr($request, $offset));
        if ($geschreven === false || $geschreven === 0) {
            fclose($socket);
            throw new RuntimeException('HTTP-request kon niet volledig worden geschreven.');
        }
        $offset += $geschreven;
    }

    $raw = stream_get_contents($socket);
    $meta = stream_get_meta_data($socket);
    fclose($socket);
    if (!is_string($raw) || !empty($meta['timed_out'])) throw new RuntimeException('HTTP-response ontbrak of timeoutte.');

    [$kop, $responseBody] = array_pad(explode("\r\n\r\n", $raw, 2), 2, '');
    $kopRegels = explode("\r\n", $kop);
    $statusRegel = array_shift($kopRegels) ?: '';
    if (preg_match('/^HTTP\/1\.[01] ([0-9]{3})\b/', $statusRegel, $m) !== 1) {
        throw new RuntimeException('Ongeldige HTTP-statusregel: ' . $statusRegel);
    }
    $responseHeaders = [];
    foreach ($kopRegels as $regel) {
        $pos = strpos($regel, ':');
        if ($pos === false) continue;
        $naam = strtolower(trim(substr($regel, 0, $pos)));
        $responseHeaders[$naam][] = trim(substr($regel, $pos + 1));
    }
    return ['status' => (int) $m[1], 'headers' => $responseHeaders, 'body' => $responseBody];
}

function r300Cookie(array $response, ?string $huidig = null): ?string
{
    foreach ((array) ($response['headers']['set-cookie'] ?? []) as $setCookie) {
        $eerste = trim(explode(';', (string) $setCookie, 2)[0] ?? '');
        if ($eerste !== '' && str_contains($eerste, '=')) return $eerste;
    }
    return $huidig;
}

function r300Csrf(string $html): ?string
{
    if (preg_match('/<input[^>]+name="csrf"[^>]+value="([a-f0-9]{64})"/i', $html, $m) === 1) return $m[1];
    if (preg_match('/<input[^>]+value="([a-f0-9]{64})"[^>]+name="csrf"/i', $html, $m) === 1) return $m[1];
    return null;
}

function r300Multipart(array $velden, string $fileField, string $fileName, string $mime, string $bytes): array
{
    $boundary = '------------------------rc045' . bin2hex(random_bytes(12));
    $body = '';
    foreach ($velden as $naam => $waarde) {
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="' . str_replace(['"', "\r", "\n"], '', (string) $naam) . '"' . "\r\n\r\n";
        $body .= (string) $waarde . "\r\n";
    }
    $body .= '--' . $boundary . "\r\n";
    $body .= 'Content-Disposition: form-data; name="' . $fileField . '"; filename="' . $fileName . '"' . "\r\n";
    $body .= 'Content-Type: ' . $mime . "\r\n\r\n";
    $body .= $bytes . "\r\n";
    $body .= '--' . $boundary . "--\r\n";
    return ['body' => $body, 'content_type' => 'multipart/form-data; boundary=' . $boundary];
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rc045-a300-' . bin2hex(random_bytes(5));
$private = $tmp . DIRECTORY_SEPARATOR . 'private';
$sessions = $tmp . DIRECTORY_SEPARATOR . 'sessions';
$configPad = $tmp . DIRECTORY_SEPARATOR . 'tenant.php';
$serverOut = $tmp . DIRECTORY_SEPARATOR . 'php-server.out';
$serverErr = $tmp . DIRECTORY_SEPARATOR . 'php-server.err';
$server = null;

try {
    if (!function_exists('proc_open')) throw new RuntimeException('proc_open is vereist voor de echte HTTP-uploadregressie.');
    if (!@mkdir($private . DIRECTORY_SEPARATOR . 'auth', 0750, true) && !is_dir($private . DIRECTORY_SEPARATOR . 'auth')) {
        throw new RuntimeException('Tijdelijke tenant-private-root kon niet worden aangemaakt.');
    }
    if (!@mkdir($sessions, 0700, true) && !is_dir($sessions)) throw new RuntimeException('Tijdelijke PHP-sessiemap kon niet worden aangemaakt.');

    $poort = r300VrijePoort();
    $password = 'E2E-' . bin2hex(random_bytes(24));
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($hash)) throw new RuntimeException('Master password_hash kon niet worden gemaakt.');
    $masterPad = $private . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'master.php';
    file_put_contents($masterPad, "<?php\n\$BEHEER_WACHTWOORD_HASH = " . var_export($hash, true) . ";\n");
    @chmod($masterPad, 0640);

    $config = [
        'vereniging' => [
            'sleutel' => 'audit300',
            'naam' => 'Audit 300',
            'volledige_naam' => 'Audit 300',
            'site_url' => "http://127.0.0.1:{$poort}",
            'timezone' => 'Europe/Amsterdam',
        ],
        'modules' => ['sponsors' => true],
        'opslag' => [
            'private_driver' => 'json',
            'private_root' => $private,
            'pdo' => ['dsn' => '', 'user' => '', 'password' => ''],
            'backups' => ['bewaardagen' => 7, 'max_per_item' => 5, 'max_asset_snapshots' => 5, 'max_asset_mb' => 50],
        ],
    ];
    file_put_contents($configPad, "<?php\nreturn " . var_export($config, true) . ";\n");
    @chmod($configPad, 0640);

    $env = getenv();
    if (!is_array($env)) $env = [];
    $env['VERENIGING_REQUIRE_TENANT_CONFIG'] = '1';
    $env['VERENIGING_CONFIG_FILE'] = $configPad;
    $server = proc_open(
        [PHP_BINARY, '-d', 'session.save_path=' . $sessions, '-S', "127.0.0.1:{$poort}", '-t', $root],
        [0 => ['pipe', 'r'], 1 => ['file', $serverOut, 'ab'], 2 => ['file', $serverErr, 'ab']],
        $pipes,
        $root,
        $env
    );
    if (!is_resource($server)) throw new RuntimeException('PHP built-in HTTP-server kon niet worden gestart.');
    if (isset($pipes[0]) && is_resource($pipes[0])) fclose($pipes[0]);

    $start = null;
    for ($i = 0; $i < 50; $i++) {
        try {
            $start = r300Http($poort, 'GET', '/beheer/');
            break;
        } catch (Throwable $e) {
            usleep(100000);
        }
    }
    if (!is_array($start)) throw new RuntimeException('Lokale HTTP-server werd niet bereikbaar.');
    r300Check($start['status'] === 200, 'echte HTTP-runtime serveert het canonieke beheerloginformulier');
    $cookie = r300Cookie($start);
    $csrf = r300Csrf((string) $start['body']);
    r300Check(is_string($cookie) && $cookie !== '' && is_string($csrf), 'loginruntime levert geïsoleerde sessiecookie en CSRF-token');
    if (!is_string($cookie) || !is_string($csrf)) throw new RuntimeException('Logincontext kon niet worden opgebouwd.');

    $loginBody = http_build_query([
        'formulier' => 'inloggen',
        'csrf' => $csrf,
        'gebruikersnaam' => '',
        'wachtwoord' => $password,
    ], '', '&', PHP_QUERY_RFC3986);
    $login = r300Http($poort, 'POST', '/beheer/', [
        'Cookie' => $cookie,
        'Content-Type' => 'application/x-www-form-urlencoded',
    ], $loginBody);
    $cookie = r300Cookie($login, $cookie);
    r300Check(in_array($login['status'], [302, 303], true) && is_string($cookie) && $cookie !== '', 'hash-only masterlogin slaagt via echte HTTP-sessie');

    $beheer = r300Http($poort, 'GET', '/beheer/sponsors.php', ['Cookie' => $cookie]);
    $csrf = r300Csrf((string) $beheer['body']);
    r300Check(
        $beheer['status'] === 200
        && is_string($csrf)
        && str_contains((string) $beheer['body'], 'id="sponsor-form"')
        && str_contains((string) $beheer['body'], 'enctype="multipart/form-data"'),
        'ingelogde master bereikt het echte multipart sponsorformulier'
    );
    if (!is_string($csrf)) throw new RuntimeException('Sponsor-CSRF-token ontbreekt.');

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    if (!is_string($png)) throw new RuntimeException('Test-PNG kon niet worden opgebouwd.');
    $positief = r300Multipart([
        'csrf' => $csrf,
        'cta_nl' => 'E2E sponsor CTA',
        'cta_en' => '',
        'cta_de' => '',
        'sponsor[0][name]' => 'E2E Sponsor Upload',
        'sponsor[0][url]' => 'https://example.test/sponsor',
    ], 'sponsor_logo_0', 'logo.png', 'image/png', $png);
    $upload = r300Http($poort, 'POST', '/beheer/sponsors.php', [
        'Cookie' => $cookie,
        'Content-Type' => $positief['content_type'],
    ], $positief['body']);
    r300Check(
        $upload['status'] === 200
        && str_contains((string) $upload['body'], 'Opgeslagen. De sponsoren op de website zijn bijgewerkt.'),
        'geldige multipart sponsorlogo-upload passeert PHP uploaded-file validatie en wordt opgeslagen'
    );

    $asset = r300Http($poort, 'GET', '/public-asset.php?scope=sponsors&path=sponsor-1.png');
    $assetType = strtolower((string) (($asset['headers']['content-type'][0] ?? '')));
    r300Check(
        $asset['status'] === 200
        && str_starts_with($assetType, 'image/png')
        && hash_equals(hash('sha256', $png), hash('sha256', (string) $asset['body'])),
        'geüploade bytes zijn via de normale publieke assetgateway exact beschikbaar'
    );

    $beheerNaUpload = r300Http($poort, 'GET', '/beheer/sponsors.php', ['Cookie' => $cookie]);
    $csrf = r300Csrf((string) $beheerNaUpload['body']);
    if (!is_string($csrf)) throw new RuntimeException('CSRF-token na geldige upload ontbreekt.');
    $negatief = r300Multipart([
        'csrf' => $csrf,
        'cta_nl' => 'E2E sponsor CTA',
        'cta_en' => '',
        'cta_de' => '',
        'sponsor[0][name]' => 'E2E Sponsor Upload',
        'sponsor[0][url]' => 'https://example.test/sponsor',
    ], 'sponsor_logo_0', 'geen-afbeelding.txt', 'text/plain', "dit is bewust geen afbeelding\n");
    $afwijzing = r300Http($poort, 'POST', '/beheer/sponsors.php', [
        'Cookie' => $cookie,
        'Content-Type' => $negatief['content_type'],
    ], $negatief['body']);
    r300Check(
        $afwijzing['status'] === 200
        && str_contains((string) $afwijzing['body'], 'bestand is geen geldige afbeelding.'),
        'ongeldige echte multipart-upload wordt fail-closed afgewezen'
    );

    $assetNaFout = r300Http($poort, 'GET', '/public-asset.php?scope=sponsors&path=sponsor-1.png');
    r300Check(
        $assetNaFout['status'] === 200
        && hash_equals(hash('sha256', $png), hash('sha256', (string) $assetNaFout['body'])),
        'afgewezen multipart-upload laat de eerder geldige sponsorasset ongewijzigd'
    );

    $sponsorJson = $private . DIRECTORY_SEPARATOR . 'public-content' . DIRECTORY_SEPARATOR . 'sponsors.json';
    $document = is_file($sponsorJson) ? json_decode((string) file_get_contents($sponsorJson), true) : null;
    r300Check(
        is_array($document)
        && (($document['items'][0]['name'] ?? '') === 'E2E Sponsor Upload')
        && (($document['items'][0]['logo'] ?? '') === 'sponsor-1.png'),
        'persistente sponsorstate verwijst naar exact de geactiveerde upload'
    );
} catch (Throwable $e) {
    $fout++;
    fwrite(STDERR, 'FOUT: audit #300 exception: ' . $e->getMessage() . "\n");
    if (is_file($serverErr)) {
        $log = trim((string) file_get_contents($serverErr));
        if ($log !== '') fwrite(STDERR, "PHP server stderr:\n{$log}\n");
    }
} finally {
    if (is_resource($server)) {
        @proc_terminate($server);
        usleep(100000);
        $status = @proc_get_status($server);
        if (is_array($status) && !empty($status['running'])) @proc_terminate($server, 9);
        @proc_close($server);
    }
    r300Wis($tmp);
}

echo "Audit #300 sponsor multipart upload: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
