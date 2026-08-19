<?php
// ============================================================
// Eenmalige DEV-hulp: Fotoboekdata van LIVE naar DEV kopiëren
// ============================================================
// Leest uitsluitend uit de productie-root (oudermap van /dev) en schrijft
// uitsluitend naar de huidige /dev-installatie. Productiebestanden worden
// nooit gewijzigd of verwijderd.
// ============================================================
require_once dirname(__DIR__) . '/auth.php';

if (!$ingelogd) { header('Location: ../beheer.php'); exit; }
if (!$isMaster) { http_response_code(403); echo 'Alleen de hoofdbeheerder mag DEV-data initialiseren.'; exit; }

$devRoot = realpath(dirname(__DIR__));
if ($devRoot === false || basename($devRoot) !== 'dev') {
    http_response_code(403);
    echo 'Deze hulp mag uitsluitend vanuit een map met de naam /dev worden uitgevoerd.';
    exit;
}
$liveRoot = realpath(dirname($devRoot));
if ($liveRoot === false || $liveRoot === $devRoot) {
    http_response_code(500);
    echo 'Productie-root kon niet veilig worden bepaald.';
    exit;
}

$bronnen = [
    'json' => $liveRoot . '/data/fotoboek.json',
    'tekst' => $liveRoot . '/data/fotoboek-pagina.json',
    'fotos' => $liveRoot . '/images/fotoboek',
];
$doelen = [
    'json' => $devRoot . '/data/fotoboek.json',
    'tekst' => $devRoot . '/data/fotoboek-pagina.json',
    'fotos' => $devRoot . '/images/fotoboek',
];

function seedEsc($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function seedTelBestanden(string $pad): int {
    if (!is_dir($pad)) return 0;
    $n = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pad, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $item) if ($item->isFile()) $n++;
    return $n;
}
function seedKopieerMap(string $bron, string $doel): bool {
    if (!is_dir($bron)) return false;
    if (!is_dir($doel) && !@mkdir($doel, 0755, true)) return false;
    foreach (scandir($bron) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $b = $bron . '/' . $item;
        $d = $doel . '/' . $item;
        if (is_dir($b)) {
            if (!seedKopieerMap($b, $d)) return false;
        } elseif (is_file($b)) {
            if (!@copy($b, $d)) return false;
        }
    }
    return true;
}
function seedLeesAlbums(string $pad): ?int {
    if (!is_file($pad)) return null;
    $raw = @file_get_contents($pad);
    if ($raw === false) return null;
    $d = json_decode($raw, true);
    if (!is_array($d)) return null;
    if (isset($d['albums']) && is_array($d['albums'])) return count($d['albums']);
    if (array_is_list($d)) return count($d);
    return null;
}

$bronAlbums = seedLeesAlbums($bronnen['json']);
$bronBestanden = seedTelBestanden($bronnen['fotos']);
$bronOk = is_file($bronnen['json']) && $bronAlbums !== null && is_dir($bronnen['fotos']);
$doelBestaat = is_file($doelen['json']) || is_dir($doelen['fotos']);
$melding = '';
$type = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrfOk()) {
        $melding = 'Sessie verlopen. Ververs de pagina.'; $type = 'fout';
    } elseif (!$bronOk) {
        $melding = 'Kopiëren geweigerd: de LIVE-bron is niet compleet of het JSON-bestand is ongeldig.'; $type = 'fout';
    } elseif ($doelBestaat) {
        $melding = 'Kopiëren geweigerd: DEV bevat inmiddels al Fotoboekdata. Deze hulp overschrijft nooit bestaande DEV-data.'; $type = 'fout';
    } elseif (trim((string)($_POST['bevestiging'] ?? '')) !== 'KOPIEER') {
        $melding = 'Typ KOPIEER om de LIVE-fotoboekdata eenmalig naar DEV te kopiëren.'; $type = 'fout';
    } else {
        $dataDir = dirname($doelen['json']);
        $imgDir = dirname($doelen['fotos']);
        $ok = (is_dir($dataDir) || @mkdir($dataDir, 0755, true)) && (is_dir($imgDir) || @mkdir($imgDir, 0755, true));
        if ($ok) $ok = @copy($bronnen['json'], $doelen['json']);
        if ($ok && is_file($bronnen['tekst'])) $ok = @copy($bronnen['tekst'], $doelen['tekst']);
        if ($ok) $ok = seedKopieerMap($bronnen['fotos'], $doelen['fotos']);

        if ($ok) {
            $melding = 'Gereed: ' . $bronAlbums . ' album(s) en ' . $bronBestanden . ' fotoboekbestand(en) zijn van LIVE naar DEV gekopieerd. LIVE is alleen gelezen.';
            $type = 'ok';
            if (function_exists('schrijfLog')) schrijfLog($logBestand, $huidigeGebruiker, 'dev_fotoboek_seed', $bronAlbums . ' albums, ' . $bronBestanden . ' bestanden');
            $doelBestaat = true;
        } else {
            $melding = 'Kopiëren is niet volledig gelukt. Gebruik Fotoboek nog niet en meld deze fout; productie is niet gewijzigd.';
            $type = 'fout';
        }
    }
}
?><!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Fotoboek LIVE → DEV</title>
<style>body{font-family:system-ui,sans-serif;background:#f6f2e8;color:#26351d;margin:0}.wrap{max-width:820px;margin:40px auto;padding:0 22px}.kaart{background:#fff;border:1px solid #ddd8c0;border-radius:14px;padding:24px;margin-bottom:18px}.rij{display:grid;grid-template-columns:220px 1fr;gap:10px;padding:8px 0;border-bottom:1px solid #eee}.ok{background:#e8f5ee;color:#205b38;padding:12px;border-radius:9px}.fout{background:#fdeceb;color:#8b2e27;padding:12px;border-radius:9px}.btn{border:0;border-radius:9px;padding:11px 16px;font-weight:700;background:#3a7a77;color:white;cursor:pointer}input{padding:10px;border:1px solid #cfcab7;border-radius:8px;font:inherit}code{word-break:break-all}.waarschuwing{background:#fff4d6;padding:12px;border-radius:9px}</style></head><body><main class="wrap"><p><a href="fotoboek-diagnose.php">← Diagnose</a> · <a href="fotoboek.php">Fotoboekbeheer</a></p><section class="kaart"><h1>Fotoboek LIVE → DEV</h1><p>Eenmalige initialisatie. De productiebron wordt alleen gelezen; deze pagina kan uitsluitend naar de huidige <code>/dev</code>-installatie schrijven.</p>
<?php if($melding!==''):?><div class="<?=seedEsc($type)?>"><?=seedEsc($melding)?></div><?php endif;?>
<div class="rij"><strong>LIVE root</strong><code><?=seedEsc($liveRoot)?></code></div><div class="rij"><strong>DEV root</strong><code><?=seedEsc($devRoot)?></code></div><div class="rij"><strong>LIVE fotoboek.json</strong><span><?=is_file($bronnen['json'])?'aanwezig':'ONTBREEKT'?></span></div><div class="rij"><strong>Albums in LIVE JSON</strong><span><?=$bronAlbums===null?'ongeldig / onbekend':(int)$bronAlbums?></span></div><div class="rij"><strong>LIVE bestanden</strong><span><?=$bronBestanden?></span></div><div class="rij"><strong>DEV al gevuld</strong><span><?=$doelBestaat?'ja':'nee'?></span></div>
<?php if(!$bronOk):?><p class="waarschuwing"><strong>Niet kopiëren.</strong> De verwachte productiebron is niet compleet. Er wordt niets gewijzigd.</p><?php elseif($doelBestaat):?><p class="waarschuwing">DEV bevat al Fotoboekdata; deze hulp overschrijft die bewust niet.</p><?php else:?><form method="post"><input type="hidden" name="csrf" value="<?=seedEsc($csrfToken)?>"><p><label>Typ <strong>KOPIEER</strong>: <input name="bevestiging" autocomplete="off" required></label></p><button class="btn" type="submit">Kopieer Fotoboek naar DEV</button></form><?php endif;?>
</section></main></body></html>