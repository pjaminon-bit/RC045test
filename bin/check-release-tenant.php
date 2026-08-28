<?php
// Read-only compatibilityprobe van een kandidaatrelease tegen één tenant.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Alleen via CLI beschikbaar.'); }
foreach ($_SERVER['argv'] ?? [] as $arg) {
    if (preg_match('/^--(?:password|secret|token|key|dsn)(?:=|$)/i', (string)$arg) === 1) { fwrite(STDERR, "FOUT: secrets niet toegestaan.\n"); exit(1); }
}
$opt = getopt('', ['expected-tenant:', 'help']);
if (isset($opt['help'])) { echo "Gebruik onder tenant-runtimeuser: php bin/check-release-tenant.php --expected-tenant=<key>\n"; exit(0); }
$verwacht = trim((string)($opt['expected-tenant'] ?? ''));
if ($verwacht === '' || preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D', $verwacht) !== 1) { fwrite(STDERR, "FOUT: geldige --expected-tenant is verplicht.\n"); exit(1); }
try {
    require_once dirname(__DIR__) . '/app/deployment/php-runtime-requirements.php';
    platformPhpAssertRequiredExtensions();

    $config = require dirname(__DIR__) . '/site-config.php';
    if (!is_array($config)) throw new RuntimeException('site-config levert geen array.');
    $tenant = (string)($config['vereniging']['sleutel'] ?? '');
    if (!hash_equals($verwacht, $tenant)) throw new RuntimeException('tenant-key wijkt af.');
    $url = parse_url((string)($config['vereniging']['site_url'] ?? ''));
    if (!is_array($url) || strtolower((string)($url['scheme'] ?? '')) !== 'https' || trim((string)($url['host'] ?? '')) === '') {
        throw new RuntimeException('canonieke HTTPS-site ontbreekt.');
    }
    require_once dirname(__DIR__) . '/app/storage/private-store.php';
    if (privateStoreDriver() !== 'pdo') throw new RuntimeException('productietenant gebruikt geen PDO.');
    $pdo = privateStorePdo();
    $q = $pdo->query('SELECT 1');
    if ($q === false || (int)$q->fetchColumn() !== 1) throw new RuntimeException('read-only databaseprobe mislukt.');
    echo 'CANDIDATE OK  tenant=' . $tenant . "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FOUT: kandidaatrelease niet compatibel voor tenant: ' . get_class($e) . "\n");
    exit(2);
}
