<?php
// ============================================================
// Tenant-lokaal slot om lees-wijzig-schrijf heen
// ------------------------------------------------------------
// Zonder expliciete private_root blijft de bestaande RC045-locatie werken.
// Nieuwe tenants krijgen hun lockbestand in hun eigen server-only private
// root, zodat schrijfverkeer van vereniging A vereniging B niet blokkeert.
// ============================================================
require_once __DIR__ . '/core/tenant-runtime.php';

function dataSlotConfig(): array {
  static $config = null;
  if ($config === null) {
    $geladen = require dirname(__DIR__) . '/site-config.php';
    $config = is_array($geladen) ? $geladen : [];
  }
  return $config;
}

function dataSlotPad() {
  $privateRoot = tenantRuntimePrivateRoot(dataSlotConfig());
  if ($privateRoot !== null) return $privateRoot . DIRECTORY_SEPARATOR . '.data.lock';
  return dirname(__DIR__) . '/data-backups/.data.lock';
}

// Stopt een schrijfrequest veilig wanneer de lock niet beschikbaar is.
function dataSlotStop($reden) {
  error_log('[platform] data-slot niet beschikbaar: ' . $reden);
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

function dataSlotOpen() {
  $pad = dataSlotPad();
  $map = dirname($pad);
  if (!is_dir($map) && !@mkdir($map, 0750, true)) {
    dataSlotStop('lockmap kon niet worden aangemaakt');
  }
  $handvat = @fopen($pad, 'c');
  if ($handvat === false) {
    dataSlotStop('lockbestand kon niet worden geopend');
  }
  @chmod($pad, 0640);
  if (!flock($handvat, LOCK_EX)) {
    fclose($handvat);
    dataSlotStop('flock(LOCK_EX) mislukt');
  }
  return $handvat;
}

function dataSlotDicht($handvat) {
  if (!$handvat) return;
  flock($handvat, LOCK_UN);
  fclose($handvat);
}
