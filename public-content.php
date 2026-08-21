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
$bestand = publicContentBestandsnaam($sleutel);
if ($sleutel === '' || $bestand === null) {
    http_response_code(404);
    header('Cache-Control: no-store');
    exit;
}

// Standalone/DEV gebruikt ingebouwde template-defaults wanneer een optionele
// beheer-override nog niet als JSON-bestand bestaat. Detecteer dat vóór de
// tenant-store wordt geresolved: zo is een ontbrekend legacybestand geen
// configuratiefout en veroorzaakt de browser geen 404/500. Zodra een externe
// tenantconfiguratie actief/verplicht is, geldt deze compatibiliteitsroute
// nadrukkelijk niet en blijft de tenant-store fail-closed.
$externPad = tenantRuntimeExternConfigPad();
$configVerplicht = tenantRuntimeConfigVerplicht();
if ($externPad === null && !$configVerplicht) {
    $legacyPad = publicContentLegacyRoot() . DIRECTORY_SEPARATOR . $bestand;
    if (!is_file($legacyPad)) {
        http_response_code(204);
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        exit;
    }
}

try {
    $data = publicContentLees($sleutel);
} catch (Throwable $e) {
    error_log('[platform] publieke content-store faalde voor dataset ' . $sleutel . ': ' . $e->getMessage());
    http_response_code(500);
    header('Cache-Control: no-store');
    exit;
}

if ($data === null) {
    // Een bestaand maar onleesbaar/ongeldig standalone bestand is géén
    // ontbrekende override en mag dus niet stil als 204 worden gemaskeerd.
    // Voor externe tenants betekent null eveneens dat de eigen dataset niet
    // beschikbaar is; daar blijft 404 de veilige uitkomst.
    if ($externPad === null && !$configVerplicht) {
        http_response_code(500);
        header('Cache-Control: no-store');
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
