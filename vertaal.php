<?php
// ============================================================
// vertaal.php — Automatisch vertalen van CMS-teksten via de DeepL API.
// Wordt alleen aangeroepen vanuit de "Vertaal"-knopjes in beheer.php
// (fetch, zelfde origin), nooit rechtstreeks door bezoekers.
//
// De DeepL-sleutel staat in vertaal-config.php, dat NIET in GitHub staat
// (zie .gitignore) en eenmalig handmatig via FTP wordt geupload, net als
// beheer-config.php. Zonder dat bestand geeft deze pagina een duidelijke
// foutmelding in plaats van een kale 500.
// ============================================================

// Dezelfde sessie-instellingen als auth.php, en om dezelfde reden: dit
// bestand start zijn eigen sessie en zou zonder deze regels bij een verzoek
// zonder bestaande sessie een cookie zetten met de standaardinstellingen,
// dus zonder Secure en zonder SameSite. auth.php blijft de plek waar de
// waarden worden bepaald; wijzig ze daar, en hier mee.
ini_set('session.use_strict_mode', '1');
$sessieduur = 60 * 60 * 24 * 7;
session_set_cookie_params([
  'lifetime' => $sessieduur,
  'path' => '/',
  'secure' => true,
  'httponly' => true,
  'samesite' => 'Lax',
]);
session_start();

header('Content-Type: application/json');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

// Zelfde inlogstatus als beheer.php: ingelogd zodra $_SESSION['gebruiker'] gezet is.
if (empty($_SESSION['gebruiker'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Niet ingelogd']);
  exit;
}

define('BEHEER_INTERN', true);

$configPad = __DIR__ . '/vertaal-config.php';
if (!file_exists($configPad)) {
  http_response_code(500);
  echo json_encode(['error' => 'vertaal-config.php ontbreekt op de server. Zie het vertaalplan voor de inhoud, en upload het bestand via FTP naast beheer.php.']);
  exit;
}
require $configPad;

if (!defined('DEEPL_API_KEY') || DEEPL_API_KEY === '') {
  http_response_code(500);
  echo json_encode(['error' => 'DEEPL_API_KEY is niet ingevuld in vertaal-config.php']);
  exit;
}

// ===== Invoer inlezen =====
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
  http_response_code(400);
  echo json_encode(['error' => 'Ongeldige aanvraag']);
  exit;
}

$teksten = isset($input['teksten']) && is_array($input['teksten']) ? $input['teksten'] : [];
$teksten = array_values(array_filter(array_map(function ($t) {
  return is_string($t) ? trim($t) : '';
}, $teksten), function ($t) {
  return $t !== '';
}));

$doeltalenToegestaan = ['EN', 'DE'];
$doeltalen = isset($input['doeltalen']) && is_array($input['doeltalen']) ? $input['doeltalen'] : $doeltalenToegestaan;
$doeltalen = array_values(array_intersect($doeltalen, $doeltalenToegestaan));

if (empty($teksten)) {
  http_response_code(400);
  echo json_encode(['error' => 'Geen tekst opgegeven']);
  exit;
}
if (empty($doeltalen)) {
  http_response_code(400);
  echo json_encode(['error' => 'Geen geldige doeltaal opgegeven']);
  exit;
}
// Ruime marge boven wat één taal-scope-blok ooit aan velden heeft, voorkomt misbruik van het endpoint.
if (count($teksten) > 30) {
  http_response_code(400);
  echo json_encode(['error' => 'Te veel teksten in één aanvraag']);
  exit;
}

// ===== Eén DeepL-aanroep per doeltaal, met alle teksten van dat blok tegelijk =====
$resultaat = [];

foreach ($doeltalen as $taal) {
  $velden = [];
  foreach ($teksten as $tekst) {
    $velden[] = 'text=' . rawurlencode($tekst);
  }
  $velden[] = 'target_lang=' . rawurlencode($taal);
  $velden[] = 'source_lang=NL';
  // Geen tag_handling: de velden bevatten platte tekst, geen HTML. Met
  // tag_handling=html codeerde DeepL leestekens als &#x27; om (HTML-entities),
  // wat als letterlijke tekst in de invoervelden terechtkwam.
  // Informele toon past bij de bestaande siteteksten; heeft alleen effect op Duits.
  $velden[] = 'formality=prefer_less';

  $ch = curl_init(rtrim(DEEPL_API_HOST, '/') . '/v2/translate');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => implode('&', $velden),
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/x-www-form-urlencoded',
      'Authorization: DeepL-Auth-Key ' . DEEPL_API_KEY,
    ],
    CURLOPT_TIMEOUT => 20,
  ]);
  $response = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlFout = curl_error($ch);
  curl_close($ch);

  if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => "Verbinding met DeepL mislukt: $curlFout"]);
    exit;
  }
  if ($status !== 200) {
    http_response_code(502);
    $foutdetail = json_decode($response, true);
    $foutmelding = $foutdetail['message'] ?? "HTTP $status";
    echo json_encode(['error' => "DeepL-fout bij $taal: $foutmelding"]);
    exit;
  }

  $data = json_decode($response, true);
  $vertalingen = $data['translations'] ?? [];
  $resultaat[$taal] = array_map(function ($v) {
    return $v['text'] ?? '';
  }, $vertalingen);
}

echo json_encode($resultaat);
