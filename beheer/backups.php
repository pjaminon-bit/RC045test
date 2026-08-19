<?php
// ============================================================
// Modulaire beheerpagina: Back-ups
// ============================================================
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/data-slot.php';

if (!$ingelogd) { header('Location: ../beheer.php'); exit; }
// Back-ups is een gevoelig recht: oude accounts zonder expliciete tabselectie
// mogen dit niet via de brede compatibiliteitsfallback verkrijgen.
if (!$isMaster && !authHeeftExplicietRecht('backups')) {
    http_response_code(403); echo 'Geen toegang tot Back-ups.'; exit;
}

$bestanden = require __DIR__ . '/backup-registry.php';

function buEsc($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function buFlash(string $tekst, string $type='ok'): void { $_SESSION['flash_backups']=['tekst'=>$tekst,'type'=>$type]; }
function buSchrijfJson(string $pad, $data): bool {
    global $dataBackupMap, $dataBackupBewaardagen, $dataBackupMaxPerBestand;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    $map = dirname($pad);
    if (!is_dir($map) && !@mkdir($map, 0755, true)) return false;
    maakDataBackup($pad, $dataBackupMap, $dataBackupBewaardagen, $dataBackupMaxPerBestand);
    $tmp = @tempnam($map, '.restore-');
    if ($tmp === false) return false;
    $ok = @file_put_contents($tmp, $json, LOCK_EX) !== false;
    if ($ok) $ok = @rename($tmp, $pad);
    if (!$ok && is_file($tmp)) @unlink($tmp);
    return $ok;
}
function buBackupLijst(string $map, string $doelPad): array {
    $basis = basename($doelPad);
    $lijst = @glob($map . '/*_' . $basis) ?: [];
    $lijst = array_values(array_filter($lijst, 'is_file'));
    usort($lijst, static fn($a,$b)=>(@filemtime($b)?:0) <=> (@filemtime($a)?:0));
    return $lijst;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrfOk()) {
        buFlash('Sessie verlopen. Ververs de pagina en probeer opnieuw.', 'fout');
        header('Location: backups.php'); exit;
    }
    if (($_POST['formulier'] ?? '') === 'backup_herstellen') {
        $sleutel = (string)($_POST['sleutel'] ?? '');
        $naam = basename((string)($_POST['backup_bestand'] ?? ''));
        $bevestiging = trim((string)($_POST['bevestiging'] ?? ''));
        $info = $bestanden[$sleutel] ?? null;
        $verwacht = $info ? '_' . basename($info['pad']) : '';
        $fout = '';
        if (!$info) $fout = 'Onbekend databestand.';
        elseif ($bevestiging !== 'HERSTEL') $fout = 'Typ HERSTEL om het terugzetten te bevestigen.';
        elseif ($naam === '' || $verwacht === '' || !str_ends_with($naam, $verwacht)) $fout = 'Ongeldige back-up geselecteerd.';
        else {
            $pad = $dataBackupMap . '/' . $naam;
            // realpath-controle voorkomt dat zelfs een onverwachte bestandsnaam
            // buiten data-backups gelezen kan worden.
            $realMap = realpath($dataBackupMap);
            $realPad = realpath($pad);
            if ($realMap === false || $realPad === false || dirname($realPad) !== $realMap || !is_file($realPad)) {
                $fout = 'Deze back-up bestaat niet meer.';
            } else {
                $raw = @file_get_contents($realPad);
                $herstelData = $raw === false ? null : json_decode($raw, true);
                if ($raw === false || json_last_error() !== JSON_ERROR_NONE) {
                    $fout = 'Back-up kon niet gelezen worden (beschadigd JSON-bestand).';
                } else {
                    $slot = dataSlotOpen();
                    if (!$slot) {
                        $fout = 'Herstellen tijdelijk niet mogelijk: dataslot kon niet worden verkregen.';
                    } else {
                        try {
                            $ok = ($info['schrijffunctie'] === 'gebruikers')
                                ? schrijfGebruikers($info['pad'], $herstelData)
                                : buSchrijfJson($info['pad'], $herstelData);
                        } finally { dataSlotDicht($slot); }
                        if ($ok) {
                            $tijd = @filemtime($realPad) ?: time();
                            schrijfLog($logBestand, $huidigeGebruiker, 'backup_hersteld', $info['label'] . ' (' . $naam . ')');
                            buFlash($info['label'] . ' is teruggezet naar de versie van ' . date('d-m-Y H:i', $tijd) . '. De versie van vlak vóór het herstel is automatisch ook als back-up bewaard.');
                            header('Location: backups.php'); exit;
                        }
                        $fout = 'Terugzetten mislukt. Controleer de schrijfrechten op de server.';
                    }
                }
            }
        }
        buFlash($fout, 'fout'); header('Location: backups.php'); exit;
    }
}

$flash = $_SESSION['flash_backups'] ?? null; unset($_SESSION['flash_backups']);
$overzicht = [];
foreach ($bestanden as $sleutel=>$info) {
    $lijst = buBackupLijst($dataBackupMap, $info['pad']);
    $overzicht[$sleutel] = ['info'=>$info,'lijst'=>$lijst];
}
?><!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Back-ups</title>
<style>body{margin:0;background:#f6f2e8;color:#26351d;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.top{position:sticky;top:0;background:#fff;border-bottom:1px solid #ddd8c0;padding:15px 22px}.topin,.wrap{max-width:1120px;margin:auto}.top a{font-weight:700;color:#2d6260;text-decoration:none}.wrap{padding:28px 22px 70px}.melding{padding:12px 14px;border-radius:9px;margin:14px 0}.ok{background:#e8f5ee;color:#205b38}.fout{background:#fdeceb;color:#8b2e27}.kaart{background:#fff;border:1px solid #ddd8c0;border-radius:14px;padding:20px;margin-bottom:16px}.kop{display:flex;justify-content:space-between;gap:16px;align-items:center}.meta{color:#66705e;font-size:14px}.backup{display:grid;grid-template-columns:minmax(230px,1fr) auto;gap:12px;align-items:center;border-top:1px solid #ece8dc;padding:12px 0}.naam{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:12px;word-break:break-all}.btn{background:#fff;border:1px solid #c9c2aa;border-radius:8px;padding:9px 12px;font-weight:700;cursor:pointer}.bevestig{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.bevestig input{width:100px;padding:8px;border:1px solid #c9c2aa;border-radius:7px}.waarschuwing{background:#fff4d6;padding:12px;border-radius:9px;margin:12px 0}@media(max-width:650px){.backup{grid-template-columns:1fr}.kop{align-items:flex-start;flex-direction:column}}</style></head><body>
<div class="top"><div class="topin"><a href="../beheer.php">← Terug naar beheer</a></div></div><main class="wrap"><h1>Back-ups</h1><p class="meta">Automatische snapshots van beheerdata. Bij iedere gewone opslag én vóór een herstel wordt de huidige versie eerst veiliggesteld. Bewaartermijn: maximaal <?= (int)$dataBackupBewaardagen ?> dagen en maximaal <?= (int)$dataBackupMaxPerBestand ?> versies per bestand.</p>
<div class="waarschuwing"><strong>Herstellen overschrijft de huidige data.</strong> Daarom moet je per herstel expliciet <code>HERSTEL</code> typen. De huidige versie wordt vóór het overschrijven automatisch opnieuw geback-upt.</div>
<?php if(is_array($flash)):?><div class="melding <?=buEsc($flash['type']??'ok')?>"><?=buEsc($flash['tekst']??'')?></div><?php endif;?>
<?php foreach($overzicht as $sleutel=>$blok): $info=$blok['info']; $lijst=$blok['lijst']; ?>
<section class="kaart"><div class="kop"><div><h2><?=buEsc($info['label'])?></h2><div class="meta"><?=count($lijst)?> back-up(s) · huidig bestand: <?=is_file($info['pad'])?'aanwezig':'nog niet aangemaakt'?></div></div></div>
<?php if(!$lijst):?><p class="meta">Nog geen back-ups beschikbaar.</p><?php else: foreach(array_slice($lijst,0,20) as $pad): $naam=basename($pad); $tijd=@filemtime($pad)?:0; ?>
<div class="backup"><div><strong><?=buEsc($tijd?date('d-m-Y H:i:s',$tijd):'Onbekende datum')?></strong><div class="naam"><?=buEsc($naam)?></div></div><form class="bevestig" method="post"><input type="hidden" name="formulier" value="backup_herstellen"><input type="hidden" name="csrf" value="<?=buEsc($csrfToken)?>"><input type="hidden" name="sleutel" value="<?=buEsc($sleutel)?>"><input type="hidden" name="backup_bestand" value="<?=buEsc($naam)?>"><label>Typ <input name="bevestiging" autocomplete="off" placeholder="HERSTEL" required></label><button class="btn" type="submit">Deze versie herstellen</button></form></div>
<?php endforeach; if(count($lijst)>20):?><p class="meta">Alleen de 20 nieuwste worden hier getoond; oudere versies blijven volgens de bewaartermijn op de server staan.</p><?php endif; endif;?></section>
<?php endforeach;?></main></body></html>
