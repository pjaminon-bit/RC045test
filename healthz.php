<?php
// Fase 4.6 — informatie-arme tenant health endpoint.
// Alleen externe VPS-tenants: 204 = gezond, 503 = ongezond, 404 = standalone/DEV.
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Content-Type: text/plain; charset=utf-8');

try {
    $config = require __DIR__ . '/site-config.php';
    if (!is_array($config) || trim((string)(getenv('VERENIGING_CONFIG_FILE') ?: '')) === '') {
        http_response_code(404);
        exit;
    }
    require_once __DIR__ . '/app/deployment/php-runtime-requirements.php';
    platformPhpAssertRequiredExtensions();
    require_once __DIR__ . '/app/operational-log.php';
    require_once __DIR__ . '/app/storage/private-store.php';

    $private = trim((string)($config['opslag']['private_root'] ?? ''));
    if ($private === '' || !is_dir($private) || !is_readable($private) || !is_writable($private)) {
        throw new RuntimeException('private-root-onbruikbaar');
    }
    if (privateStoreDriver() !== 'pdo') {
        throw new RuntimeException('productie-driver-niet-pdo');
    }
    $pdo = privateStorePdo();
    $probe = $pdo->query('SELECT 1');
    if ($probe === false || (int)$probe->fetchColumn() !== 1) {
        throw new RuntimeException('database-probe-mislukt');
    }

    http_response_code(204);
    exit;
} catch (Throwable $e) {
    if (isset($config) && is_array($config) && function_exists('vpOps46Log')) {
        vpOps46Log($config, 'health_failed', 'error', [
            'component' => 'app',
            'error_class' => get_class($e),
        ]);
    }
    http_response_code(503);
    exit;
}
