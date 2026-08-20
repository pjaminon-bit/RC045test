<?php
// Publiek, read-only endpoint voor expliciet toegestane tenantcontent.
require_once __DIR__ . '/app/content/public-content-store.php';

$methode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($methode, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    header('Cache-Control: no-store');
    exit;
}

$sleutel = isset($_GET['key']) && is_string($_GET['key']) ? $_GET['key'] : '';
if ($sleutel === '' || publicContentBestandsnaam($sleutel) === null) {
    http_response_code(404);
    header('Cache-Control: no-store');
    exit;
}

$data = publicContentLees($sleutel);
if ($data === null) {
    http_response_code(404);
    header('Cache-Control: no-store');
    exit;
}

$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    error_log('[platform] publieke content kon niet als JSON worden uitgevoerd: ' . $sleutel);
    http_response_code(500);
    header('Cache-Control: no-store');
    exit;
}

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
if ($methode !== 'HEAD') echo $json;
