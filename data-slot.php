<?php
// ============================================================
// RC045 slot om lees-wijzig-schrijf heen
// ------------------------------------------------------------
// Alle databestanden van de vereniging zijn JSON (of PHP met JSON erin) en
// worden gelezen, in het geheugen aangepast en daarna in hun geheel
// teruggeschreven. LOCK_EX op file_put_contents() beschermt alleen dat
// laatste stukje. Twee verzoeken die vlak na elkaar binnenkomen lezen dan
// allebei dezelfde versie en de tweede schrijft het werk van de eerste
// stilzwijgend weg.
//
// Dit bestand geeft één slot voor alle databestanden samen. Niet één per
// bestand: een enkele opslagactie raakt er vaak meerdere tegelijk (een lid
// dat aan een commissie wordt gekoppeld, een taak bij een vergadering), en
// met losse sloten kunnen twee verzoeken elkaar dan alsnog klemzetten.
// Kort vasthouden dus, en altijd weer vrijgeven.
//
// Gebruik:
//
//   $slot = dataSlotOpen();
//   ... lezen, aanpassen, schrijven ...
//   dataSlotDicht($slot);
//
// Neem het slot nooit twee keer in hetzelfde verzoek: de tweede aanvraag
// wacht dan op zichzelf. In beheer.php en leden.php zit het om het hele
// opslaan-blok heen, dus binnen zo'n blok is er niets meer te doen.
//
// Lukt het openen of vergrendelen niet, dan stopt de schrijfrequest met 503.
// Bewust fail-closed: een zichtbare, tijdelijke opslagfout is veiliger dan
// stil doorgaan zonder lock en daardoor wijzigingen van een ander verliezen.
//
// Het lockbestand staat in data-backups/, want die map is server-only.
// ============================================================

function dataSlotPad() {
  return __DIR__ . '/data-backups/.data.lock';
}

// Stopt een schrijfrequest veilig wanneer de centrale lock niet beschikbaar
// is. Voor het openbare JSON-endpoint blijft de response ook geldig JSON.
function dataSlotStop($reden) {
  error_log('[RC045] data-slot niet beschikbaar: ' . $reden);
  if (!headers_sent()) {
    http_response_code(503);
    header('Retry-After: 5');
    header('Cache-Control: no-store');
  }

  $tekst = 'Opslaan is tijdelijk niet beschikbaar. Probeer het over enkele seconden opnieuw.';
  $json = false;
  foreach (headers_list() as $header) {
    if (stripos($header, 'Content-Type:') === 0 && stripos($header, 'application/json') !== false) {
      $json = true;
      break;
    }
  }
  echo $json
    ? json_encode(['ok' => false, 'melding' => $tekst], JSON_UNESCAPED_UNICODE)
    : $tekst;
  exit;
}

// Geeft altijd een werkelijk vergrendeld bestandshandvat terug. Kan dat niet,
// dan beëindigt dataSlotStop() de schrijfrequest met HTTP 503.
function dataSlotOpen() {
  $pad = dataSlotPad();
  $map = dirname($pad);
  if (!is_dir($map) && !@mkdir($map, 0755, true)) {
    dataSlotStop('lockmap kon niet worden aangemaakt');
  }
  $handvat = @fopen($pad, 'c');
  if ($handvat === false) {
    dataSlotStop('lockbestand kon niet worden geopend');
  }
  if (!flock($handvat, LOCK_EX)) {
    fclose($handvat);
    dataSlotStop('flock(LOCK_EX) mislukt');
  }
  return $handvat;
}

// Geeft het slot weer vrij.
function dataSlotDicht($handvat) {
  if (!$handvat) return;
  flock($handvat, LOCK_UN);
  fclose($handvat);
}
