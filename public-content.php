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
    // Standalone/DEV heeft voor deze datasets altijd server-side template-
    // defaults. Een bestaand maar ongeldig/onleesbaar legacybestand is daar
    // dus een onbruikbare optionele override, niet een reden om de publieke
    // pagina met 500-responses te vervuilen. Log de afwijking wel zodat beheer
    // hem kan herstellen, maar geef de browser expliciet "geen override".
    // Externe tenants blijven strikt: zij mogen nooit naar RC045/defaultdata
    // terugvallen en krijgen bij ontbrekende eigen content 404.
    if ($externPad === null && !$configVerplicht) {
        error_log('[platform] standalone override is ongeldig voor dataset ' . $sleutel . '; template-default blijft actief');
        http_response_code(204);
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        exit;
    }
    http_response_code(404);
    header('Cache-Control: no-store');
    exit;
}

// De vaste DEV-codeacceptatie onder /dev bevat bewust geen tenantuploads uit
// images/sponsors/. De beheerdata kan daar nog wel de namen van historische
// RC045-sponsorbestanden bevatten. Laat die metadata op DEV niet leiden tot
// browserrequests naar bestanden die per ontwerp niet worden gedeployd: behoud
// de CTA/overige sponsorconfig, maar lever een lege items-lijst. De standalone
// productiesite buiten /dev en alle externe tenants blijven volledig ongemoeid.
$requestPad = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if (!is_string($requestPad)) $requestPad = '';
$isDevRequest = preg_match('#^/dev(?:/|$)#', $requestPad) === 1;
if ($sleutel === 'sponsors'
    && $externPad === null
    && !$configVerplicht
    && $isDevRequest
    && is_array($data)) {
    $data['items'] = [];
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
