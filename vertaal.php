<?php
// ============================================================
// Automatisch vertalen van beheer/CMS-teksten via DeepL
// ============================================================
// Dit endpoint draait bewust onder exact dezelfde centrale auth-, tenant- en
// sessiebeveiliging als de rest van beheer. Er bestaat geen eigen session_start
// of losse controle op alleen $_SESSION['gebruiker'] meer.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/app/auth-capabilities.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

function vertaalAntwoord(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') vertaalAntwoord(405, ['error' => 'Alleen POST.']);
if (empty($ingelogd)) vertaalAntwoord(401, ['error' => 'Niet ingelogd.']);

$vertaalCapabilities = [
    'content.homepage.manage','content.ontstaan.manage','content.reglement.manage',
    'content.aanmelden.manage','content.bedankt.manage','content.mededeling.manage',
    'content.nieuws.manage','events.agenda.manage','content.contact.manage',
    'content.sponsors.manage','content.faq.manage','content.media.manage',
    'content.fotoboek.manage',
];
$magVertalen = !empty($isMaster);
if (!$magVertalen) {
    foreach ($vertaalCapabilities as $capability) {
        if (authHeeftCapability($capability)) { $magVertalen = true; break; }
    }
}
if (!$magVertalen) vertaalAntwoord(403, ['error' => 'Geen rechten om teksten te vertalen.']);

// CSRF-verdediging voor de JSON-fetch: alleen application/json van dezelfde
// origin wordt geaccepteerd. Een cross-site formulier kan deze Content-Type
// niet zetten; cross-site fetch krijgt door de browser een CORS-preflight en
// wordt bovendien op Origin/Sec-Fetch-Site afgewezen. De sessiecookie is ook
// SameSite=Lax via auth.php.
$contentType = strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')));
if (!str_starts_with($contentType, 'application/json')) vertaalAntwoord(415, ['error' => 'Alleen JSON wordt geaccepteerd.']);
$fetchSite = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
if ($fetchSite !== '' && $fetchSite !== 'same-origin') vertaalAntwoord(403, ['error' => 'Aanvraag geweigerd.']);
$siteUrl = trim((string)($authSiteConfig['vereniging']['site_url'] ?? ''));
$siteParts = parse_url($siteUrl);
$verwachteOrigin = '';
if (is_array($siteParts) && isset($siteParts['scheme'], $siteParts['host'])) {
    $verwachteOrigin = strtolower($siteParts['scheme'] . '://' . $siteParts['host'] . (isset($siteParts['port']) ? ':' . (int)$siteParts['port'] : ''));
}
$origin = strtolower(rtrim(trim((string)($_SERVER['HTTP_ORIGIN'] ?? '')), '/'));
if ($origin !== '' && ($verwachteOrigin === '' || !hash_equals($verwachteOrigin, $origin))) vertaalAntwoord(403, ['error' => 'Aanvraag geweigerd.']);

// Beperk zowel requestgrootte als kostenmisbruik per ingelogde sessie.
$nu = time();
$venster = 10 * 60;
$maxAanroepen = 20;
$recenteAanroepen = array_values(array_filter((array)($_SESSION['translation_attempts'] ?? []), static fn($t) => is_int($t) && $t > $nu - $venster));
if (count($recenteAanroepen) >= $maxAanroepen) vertaalAntwoord(429, ['error' => 'Te veel vertaalaanvragen. Probeer het later opnieuw.']);
$recenteAanroepen[] = $nu;
$_SESSION['translation_attempts'] = $recenteAanroepen;

$ruw = file_get_contents('php://input');
if (!is_string($ruw) || $ruw === '' || strlen($ruw) > 65536) vertaalAntwoord(400, ['error' => 'Ongeldige of te grote aanvraag.']);
try { $input = json_decode($ruw, true, 32, JSON_THROW_ON_ERROR); }
catch (Throwable $e) { $input = null; }
if (!is_array($input)) vertaalAntwoord(400, ['error' => 'Ongeldige aanvraag.']);

$tekstenIn = isset($input['teksten']) && is_array($input['teksten']) ? $input['teksten'] : [];
if (count($tekstenIn) > 30) vertaalAntwoord(400, ['error' => 'Te veel teksten in één aanvraag.']);
$teksten = [];
$totaalBytes = 0;
foreach ($tekstenIn as $tekst) {
    if (!is_string($tekst)) vertaalAntwoord(400, ['error' => 'Ongeldige tekstinvoer.']);
    $tekst = trim($tekst);
    if ($tekst === '') continue;
    $bytes = strlen($tekst);
    if ($bytes > 8192) vertaalAntwoord(400, ['error' => 'Een tekst is te lang.']);
    $totaalBytes += $bytes;
    if ($totaalBytes > 49152) vertaalAntwoord(400, ['error' => 'Te veel tekst in één aanvraag.']);
    $teksten[] = $tekst;
}
if (!$teksten) vertaalAntwoord(400, ['error' => 'Geen tekst opgegeven.']);

$doeltalenToegestaan = ['EN', 'DE'];
$doeltalenIn = isset($input['doeltalen']) && is_array($input['doeltalen']) ? $input['doeltalen'] : $doeltalenToegestaan;
$doeltalen = [];
foreach ($doeltalenIn as $taal) {
    if (!is_string($taal)) continue;
    $taal = strtoupper(trim($taal));
    if (in_array($taal, $doeltalenToegestaan, true) && !in_array($taal, $doeltalen, true)) $doeltalen[] = $taal;
}
if (!$doeltalen) vertaalAntwoord(400, ['error' => 'Geen geldige doeltaal opgegeven.']);

define('BEHEER_INTERN', true);
$configPad = __DIR__ . '/vertaal-config.php';
if (!is_file($configPad) || is_link($configPad)) vertaalAntwoord(503, ['error' => 'Vertalen is tijdelijk niet beschikbaar.']);
require $configPad;
if (!defined('DEEPL_API_KEY') || !is_string(DEEPL_API_KEY) || trim(DEEPL_API_KEY) === '') vertaalAntwoord(503, ['error' => 'Vertalen is tijdelijk niet beschikbaar.']);
if (!defined('DEEPL_API_HOST') || !is_string(DEEPL_API_HOST) || !preg_match('#^https://(api-free|api)\.deepl\.com$#D', rtrim(DEEPL_API_HOST, '/'))) {
    error_log('[vertaal] ongeldige DeepL API-hostconfiguratie');
    vertaalAntwoord(503, ['error' => 'Vertalen is tijdelijk niet beschikbaar.']);
}

$resultaat = [];
foreach ($doeltalen as $taal) {
    $velden = [];
    foreach ($teksten as $tekst) $velden[] = 'text=' . rawurlencode($tekst);
    $velden[] = 'target_lang=' . rawurlencode($taal);
    $velden[] = 'source_lang=NL';
    $velden[] = 'formality=prefer_less';

    $ch = curl_init(rtrim(DEEPL_API_HOST, '/') . '/v2/translate');
    if ($ch === false) vertaalAntwoord(502, ['error' => 'Vertalen is tijdelijk niet beschikbaar.']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => implode('&', $velden),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: DeepL-Auth-Key ' . DEEPL_API_KEY,
        ],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlFout = curl_error($ch);
    curl_close($ch);

    if ($response === false || $status !== 200) {
        error_log('[vertaal] DeepL-aanroep faalde: taal=' . $taal . ' status=' . $status . ($curlFout !== '' ? ' curl=' . $curlFout : ''));
        vertaalAntwoord(502, ['error' => 'Vertalen via DeepL is tijdelijk niet beschikbaar.']);
    }

    try { $data = json_decode((string)$response, true, 64, JSON_THROW_ON_ERROR); }
    catch (Throwable $e) { $data = null; }
    $vertalingen = is_array($data) && is_array($data['translations'] ?? null) ? $data['translations'] : null;
    if ($vertalingen === null || count($vertalingen) !== count($teksten)) {
        error_log('[vertaal] onverwachte DeepL-response voor ' . $taal);
        vertaalAntwoord(502, ['error' => 'Vertalen via DeepL is tijdelijk niet beschikbaar.']);
    }
    $resultaat[$taal] = array_map(static fn($v) => is_array($v) && is_string($v['text'] ?? null) ? $v['text'] : '', $vertalingen);
}

vertaalAntwoord(200, $resultaat);
