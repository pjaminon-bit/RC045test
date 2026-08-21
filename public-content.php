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
    // Standalone/DEV draait met ingebouwde template-defaults als een optionele
    // beheeroverride nog niet bestaat. Voor dat compatibiliteitsscenario is
    // "geen override" geen fout: 204 voorkomt onnodige browser-404's terwijl
    // de pagina zijn bestaande defaults behoudt. Externe tenants mogen juist
    // nooit op gedeelde/legacy data terugvallen en blijven daarom fail-closed
    // met 404 wanneer hun eigen tenantdataset ontbreekt.
    if (publicContentTenantRoot() === null) {
        http_response_code(204);
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        exit;
    }
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
