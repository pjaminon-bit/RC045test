<?php
// ============================================================
// RC045 aanmeldingen ontvangen
// ------------------------------------------------------------
// aanmelden.html stuurt het formulier eerst naar Formspree (daar komt
// de mail aan het bestuur vandaan) en daarna nog een keer hierheen.
// Deze pagina zet de aanmelding in het ledenbestand met de status
// "Nieuw", want of er betaald is weten we op dat moment nog
// niet. Gaat het hier mis, dan staat de aanmelding nog steeds in de
// mail van Formspree; er raakt dus nooit iets kwijt.
//
// Dit is een openbaar bereikbaar adres. Er is geen inlog en dus ook
// geen csrf-token mogelijk. De afscherming zit in: alleen POST, het
// verborgen honeypotveld, een limiet per ip-adres, harde lengte-
// grenzen op elk veld en een controle op dubbele inzendingen.
// ============================================================

date_default_timezone_set('Europe/Amsterdam');
header('X-Robots-Tag: noindex, nofollow');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/leden-opslag.php';

$pogingenBestand = __DIR__ . '/aanmelden-pogingen.php';
$maxPerUurPerIp  = 5;
$dubbelVenster   = 24 * 60 * 60; // zelfde mailadres binnen een dag: niet nog eens toevoegen

function antwoord($status, $tekst) {
  http_response_code($status);
  echo json_encode(['ok' => $status < 400, 'melding' => $tekst], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  antwoord(405, 'Alleen POST.');
}

// ===== Honeypot =====
// Het veld "website" staat verborgen in het formulier. Een echte bezoeker
// laat het leeg, veel bots vullen alles in. Bewust een net antwoord, zodat
// een bot niet leert dat hij herkend is.
if (trim($_POST['website'] ?? '') !== '') {
  antwoord(200, 'Ontvangen.');
}

// ===== Limiet per ip-adres =====
function pogingenLezen($pad) {
  if (!is_file($pad)) return [];
  $ruw = file_get_contents($pad);
  if ($ruw === false) return [];
  $start = strpos($ruw, '{');
  if ($start === false) return [];
  $json = json_decode(substr($ruw, $start), true);
  return is_array($json) ? $json : [];
}

function pogingenSchrijven($pad, $pogingen) {
  file_put_contents($pad, "<?php exit; ?>\n" . json_encode($pogingen, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'onbekend';
$sleutel = hash('sha256', $ip); // geen ip-adressen op schijf
$nu = time();
$pogingen = pogingenLezen($pogingenBestand);

// Alles ouder dan een uur opruimen.
foreach ($pogingen as $k => $tijden) {
  $pogingen[$k] = array_values(array_filter((array) $tijden, function ($t) use ($nu) {
    return $t > $nu - 3600;
  }));
  if (count($pogingen[$k]) === 0) unset($pogingen[$k]);
}

if (isset($pogingen[$sleutel]) && count($pogingen[$sleutel]) >= $maxPerUurPerIp) {
  antwoord(429, 'Te veel aanmeldingen achter elkaar. Probeer het later opnieuw.');
}
$pogingen[$sleutel][] = $nu;
pogingenSchrijven($pogingenBestand, $pogingen);

// ===== Velden overnemen =====
// De namen links komen uit aanmelden.html, rechts staan de velden van
// het ledenbestand. Alles wordt in ledenNormaliseer() nog afgekapt op
// de maximale lengte per veld.
$voornaam   = trim($_POST['voornaam'] ?? '');
$achternaam = trim($_POST['achternaam'] ?? '');
$email      = trim($_POST['email'] ?? '');
$telefoon   = trim($_POST['mobiel'] ?? '');

if ($voornaam === '' || $achternaam === '') {
  antwoord(400, 'Voornaam en achternaam zijn verplicht.');
}
if ($email === '' && $telefoon === '') {
  antwoord(400, 'Vul een mailadres of een telefoonnummer in.');
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  antwoord(400, 'Dat mailadres ziet er niet geldig uit.');
}

// Vanaf hier wordt het ledenbestand gelezen, aangevuld en teruggeschreven.
// Dat moet als geheel onder het slot: anders kan een aanmelding die
// binnenkomt terwijl het bestuur net een lid opslaat, of terwijl er een
// tweede aanmelding binnenkomt, het werk van de ander overschrijven. Het
// slot valt vanzelf weg als dit script eindigt, ook via antwoord().
$slot = dataSlotOpen();

$data = ledenLees();

// ===== Dubbele inzending =====
// Iemand die twee keer op verzenden drukt, of het formulier opnieuw
// invult, mag niet twee regels opleveren.
if ($email !== '') {
  $emailKlein = strtolower($email);
  foreach ($data['leden'] as $lid) {
    if (strtolower(trim((string) ($lid['email'] ?? ''))) !== $emailKlein) continue;
    $aangemaakt = strtotime($lid['aangemaakt'] ?? '');
    if ($aangemaakt !== false && $aangemaakt > $nu - $dubbelVenster) {
      antwoord(200, 'Ontvangen.');
    }
  }
}

list($straat, $huisnummer) = ledenSplitsAdres($_POST['straat'] ?? '', $_POST['huisnummer'] ?? '');

$lid = ledenNormaliseer([
  'voornaam'       => $voornaam,
  'achternaam'     => $achternaam,
  'geboortedatum'  => $_POST['geboortedatum'] ?? '',
  'straat'         => $straat,
  'huisnummer'     => $huisnummer,
  'postcode'       => $_POST['postcode'] ?? '',
  'gemeente'       => $_POST['stad'] ?? '',
  'land'           => $_POST['land'] ?? '',
  'telefoon'       => $telefoon,
  'email'          => $email,
  'status'         => 'nieuw',
  'inschrijfdatum' => date('Y-m-d'),
]);

$lid['nummer'] = ledenVolgendNummer($data);
$lid['bron'] = 'aanmeldformulier';

// Het bedrag dat de calculator op de aanmeldpagina heeft laten zien,
// alleen als het een plausibel getal is. Het staat op "open": het
// bestuur bepaalt bij de verificatie wat er werkelijk moet komen.
$bedrag = str_replace(',', '.', trim($_POST['contributiebedrag'] ?? ''));
$bedrag = preg_replace('/[^0-9.]/', '', $bedrag);
$contributieRegel = ['status' => 'open', 'bedrag' => '', 'inschrijfgeld' => '', 'betaald_op' => '', 'opmerking' => 'Bedrag uit de calculator op aanmelden.html.'];
if ($bedrag !== '' && is_numeric($bedrag) && (float) $bedrag >= 0 && (float) $bedrag <= 999) {
  $contributieRegel['bedrag'] = (float) $bedrag;
}
$lid = ledenZetContributie($lid, (int) date('Y'), $contributieRegel);

$data['leden'][] = $lid;
$data['volgnummer'] = $lid['nummer'];

$opgeslagen = ledenSchrijf($data);
dataSlotDicht($slot);

if (!$opgeslagen) {
  antwoord(500, 'Opslaan mislukt.');
}

antwoord(200, 'Ontvangen.');
