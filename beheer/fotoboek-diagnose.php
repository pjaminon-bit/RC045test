<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/site.php';
require_once __DIR__ . '/fotoboek-lib.php';

if (!$ingelogd) { header('Location: ../beheer.php'); exit; }
$rechten = authRechten(['fotoboek' => 'Fotoboek'], []);
if (!$isMaster && !in_array('fotoboek', $rechten['toegestaneTabs'] ?? [], true)) {
    http_response_code(403); echo 'Geen toegang tot Fotoboek.'; exit;
}

$root = dirname(__DIR__);
$pad = $root . '/data/fotoboek.json';
$data = fbLees($pad);
$status = $GLOBALS['fbLeesStatus'] ?? ['ok'=>false,'code'=>'onbekend','melding'=>'Geen status beschikbaar.'];
$fotoMap = $root . '/images/fotoboek';
$albumMappen = [];
if (is_dir($fotoMap)) {
    foreach ((array) @scandir($fotoMap) as $item) {
        if ($item === '.' || $item === '..') continue;
        if (is_dir($fotoMap . '/' . $item)) $albumMappen[] = $item;
    }
}
?><!DOCTYPE html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Fotoboek diagnose</title><style>body{font-family:system-ui;margin:32px;background:#f6f2e8;color:#26351d}.kaart{max-width:800px;background:#fff;border:1px solid #ddd8c0;border-radius:14px;padding:24px}.ok{color:#205b38}.fout{color:#8b2e27}code{background:#f2efe7;padding:2px 5px;border-radius:4px}dt{font-weight:700;margin-top:14px}dd{margin:4px 0 0}</style></head><body><div class="kaart"><p><a href="fotoboek.php">← Terug naar Fotoboek</a></p><h1>Fotoboek diagnose</h1><p>Alleen-lezen controle; deze pagina wijzigt niets.</p><dl><dt>Status</dt><dd class="<?=!empty($status['ok'])?'ok':'fout'?>"><?=fbEsc(!empty($status['ok'])?'OK':'FOUT')?> — <?=fbEsc($status['code']??'onbekend')?></dd><dt>Melding</dt><dd><?=fbEsc($status['melding']??'')?></dd><dt>Databestand</dt><dd><code><?=fbEsc($pad)?></code></dd><dt>Bestand bestaat</dt><dd><?=is_file($pad)?'ja':'nee'?></dd><dt>Bestandsgrootte</dt><dd><?=is_file($pad)?number_format((int)@filesize($pad),0,',','.').' bytes':'—'?></dd><dt>Albums in JSON</dt><dd><?=count($data['albums']??[])?></dd><dt>Albummappen op schijf</dt><dd><?=count($albumMappen)?></dd></dl><?php if(!$status['ok'] && count($albumMappen)>0):?><p class="fout"><strong>Belangrijk:</strong> er staan wel albummappen/foto's op schijf, maar de albumadministratie wordt niet gelezen. Maak nu geen nieuw album aan en sla niets op voordat dit is hersteld.</p><?php endif;?></div></body></html>