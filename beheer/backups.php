<?php
// ============================================================
// Modulaire beheerpagina: Back-ups
// ============================================================
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/app/data-slot.php';
require_once dirname(__DIR__) . '/app/auth-capabilities.php';
require_once dirname(__DIR__) . '/app/storage/tenant-backup-store.php';

if (!$ingelogd) { header('Location: ./'); exit; }
if (!authHeeftCapability('system.backups.manage', true)) {
    http_response_code(403); echo 'Geen toegang tot Back-ups.'; exit;
}

$tenantBackupMode = tenantBackupActief();
if ($tenantBackupMode) {
    require_once dirname(__DIR__) . '/app/content/public-content-store.php';
    require_once dirname(__DIR__) . '/app/content/public-asset-store.php';
    require_once dirname(__DIR__) . '/app/storage/private-store.php';
}
$bestanden = require __DIR__ . '/backup-registry.php';

function buEsc($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function buFlash(string $tekst, string $type='ok'): void { $_SESSION['flash_backups']=['tekst'=>$tekst,'type'=>$type]; }

// ===== Legacy/standalone helpers =====
function buSchrijfBestand(string $pad, $data, string $type): bool {
    global $dataBackupMap, $dataBackupBewaardagen, $dataBackupMaxPerBestand;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    $inhoud = $type === 'phpjson' ? "<?php exit; ?>\n" . $json : $json;
    $map = dirname($pad);
    if (!is_dir($map) && !@mkdir($map, 0755, true)) return false;
    maakDataBackup($pad, $dataBackupMap, $dataBackupBewaardagen, $dataBackupMaxPerBestand);
    try { $suffix = bin2hex(random_bytes(5)); } catch (Throwable $e) { $suffix = str_replace('.','',(string)microtime(true)); }
    $tmp = $pad . '.restore.' . $suffix;
    if (@file_put_contents($tmp, $inhoud, LOCK_EX) === false) return false;
    if (!@rename($tmp, $pad)) { @unlink($tmp); return false; }
    return true;
}
function buLeesBackupData(string $raw, string $type, ?string &$fout = null) {
    $fout = null;
    if ($type === 'phpjson') {
        $start = strpos($raw, '{');
        if ($start === false) { $fout = 'Private back-up bevat geen JSON-payload.'; return null; }
        $raw = substr($raw, $start);
    }
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) { $fout = 'Back-up bevat beschadigde JSON: ' . json_last_error_msg(); return null; }
    return $data;
}
function buBackupLijst(string $map, string $doelPad): array {
    $basis = basename($doelPad);
    $lijst = @glob($map . '/*_' . $basis) ?: [];
    $lijst = array_values(array_filter($lijst, 'is_file'));
    usort($lijst, static fn($a,$b)=>(@filemtime($b)?:0) <=> (@filemtime($a)?:0));
    return $lijst;
}

// ===== Tenant helpers =====
function buTenantLijst(array $info): array {
    return ($info['type'] ?? '') === 'assets'
        ? tenantBackupAssetLijst((string)($info['source'] ?? ''))
        : tenantBackupDataLijst((string)($info['backup_key'] ?? ''));
}

function buTenantMaakSnapshot(array $info, ?string &$fout = null): ?string {
    global $usersBestand;
    $fout = null;
    $type = (string)($info['type'] ?? '');
    $source = (string)($info['source'] ?? '');
    $backupKey = (string)($info['backup_key'] ?? '');
    try {
        if ($type === 'assets') {
            $pad = tenantBackupMaakAssetSnapshot($source);
            if ($pad === null) $fout = 'Er zijn geen veilige assets om te snapshotten, of de assetlimiet is bereikt.';
            return $pad;
        }
        if ($type === 'public') {
            $data = publicContentLees($source);
            if ($data === null) { $fout = 'Deze publieke dataset is nog niet aangemaakt.'; return null; }
            $pad = tenantBackupMaakArray($backupKey, $data);
            if ($pad === null) $fout = 'Publieke snapshot kon niet worden opgeslagen.';
            return $pad;
        }
        if ($type === 'private') {
            $data = privateStoreLees($source, static fn()=>[]);
            $pad = tenantBackupMaakArray($backupKey, $data);
            if ($pad === null) $fout = 'Private snapshot kon niet worden opgeslagen.';
            return $pad;
        }
        if ($type === 'users') {
            $data = laadGebruikers($usersBestand);
            $pad = tenantBackupMaakArray($backupKey, $data);
            if ($pad === null) $fout = 'Gebruikerssnapshot kon niet worden opgeslagen.';
            return $pad;
        }
    } catch (Throwable $e) {
        error_log('[platform] tenant snapshot mislukt: ' . $e->getMessage());
        $fout = 'Snapshot kon niet veilig worden gemaakt.';
        return null;
    }
    $fout = 'Onbekend back-uptype.';
    return null;
}

function buTenantHerstel(array $info, string $naam, ?string &$fout = null): bool {
    global $usersBestand;
    $fout = null;
    $type = (string)($info['type'] ?? '');
    $source = (string)($info['source'] ?? '');
    $backupKey = (string)($info['backup_key'] ?? '');
    try {
        if ($type === 'assets') return tenantBackupHerstelAssetSnapshot($source, $naam, $fout);

        $data = tenantBackupLeesArray($backupKey, $naam, $fout);
        if ($data === null) return false;
        if ($type === 'public') return publicContentSchrijfTenant($source, $data, true);
        if ($type === 'private') return privateStoreSchrijf($source, $data, static fn($d)=>false);
        if ($type === 'users') {
            $huidig = laadGebruikers($usersBestand);
            tenantBackupMaakArray($backupKey, $huidig);
            return schrijfGebruikers($usersBestand, $data);
        }
        $fout = 'Onbekend back-uptype.';
        return false;
    } catch (Throwable $e) {
        error_log('[platform] tenant restore mislukt: ' . $e->getMessage());
        $fout = 'Terugzetten is afgebroken door de tenantbeveiliging of storage-laag.';
        return false;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrfOk()) {
        buFlash('Sessie verlopen. Ververs de pagina en probeer opnieuw.', 'fout');
        header('Location: backups.php'); exit;
    }

    $formulier = (string)($_POST['formulier'] ?? '');
    if ($tenantBackupMode && $formulier === 'backup_maken') {
        $sleutel = (string)($_POST['sleutel'] ?? '');
        $info = $bestanden[$sleutel] ?? null;
        if (!$info) buFlash('Onbekend back-uponderdeel.', 'fout');
        else {
            $fout = null;
            $pad = buTenantMaakSnapshot($info, $fout);
            if ($pad !== null) {
                schrijfLog($logBestand, $huidigeGebruiker, 'backup_snapshot', (string)$info['label']);
                buFlash($info['label'] . ' is als tenant-snapshot opgeslagen.');
            } else buFlash($fout ?: 'Snapshot maken mislukt.', 'fout');
        }
        header('Location: backups.php'); exit;
    }

    if ($formulier === 'backup_herstellen') {
        $sleutel = (string)($_POST['sleutel'] ?? '');
        $naam = basename((string)($_POST['backup_bestand'] ?? ''));
        $bevestiging = trim((string)($_POST['bevestiging'] ?? ''));
        $info = $bestanden[$sleutel] ?? null;
        $fout = '';
        if (!$info) $fout = 'Onbekend databestand.';
        elseif ($bevestiging !== 'HERSTEL') $fout = 'Typ HERSTEL om het terugzetten te bevestigen.';
        elseif ($naam === '') $fout = 'Ongeldige back-up geselecteerd.';
        elseif ($tenantBackupMode) {
            $slot = dataSlotOpen();
            try { $ok = buTenantHerstel($info, $naam, $fout); }
            finally { dataSlotDicht($slot); }
            if ($ok) {
                schrijfLog($logBestand, $huidigeGebruiker, 'backup_hersteld', $info['label'] . ' (' . $naam . ')');
                buFlash($info['label'] . ' is teruggezet. De toestand van vlak vóór het herstel is opnieuw als tenant-snapshot bewaard voor zover er al data bestond.');
                header('Location: backups.php'); exit;
            }
            if ($fout === '') $fout = 'Terugzetten mislukt.';
        } else {
            $verwacht = '_' . basename($info['pad']);
            if (!str_ends_with($naam, $verwacht)) $fout = 'Ongeldige back-up geselecteerd.';
            else {
                $pad = $dataBackupMap . '/' . $naam;
                $realMap = realpath($dataBackupMap);
                $realPad = realpath($pad);
                if ($realMap === false || $realPad === false || dirname($realPad) !== $realMap || !is_file($realPad)) {
                    $fout = 'Deze back-up bestaat niet meer.';
                } else {
                    $raw = @file_get_contents($realPad);
                    $parseFout = null;
                    $herstelData = $raw === false ? null : buLeesBackupData($raw, (string)($info['schrijffunctie'] ?? 'json'), $parseFout);
                    if ($raw === false || $parseFout !== null) $fout = $parseFout ?: 'Back-up kon niet gelezen worden.';
                    elseif (!is_array($herstelData)) $fout = 'Back-up heeft geen geldig object/array-formaat.';
                    else {
                        $slot = dataSlotOpen();
                        try {
                            $type = (string)($info['schrijffunctie'] ?? 'json');
                            $ok = $type === 'gebruikers'
                                ? schrijfGebruikers($info['pad'], $herstelData)
                                : buSchrijfBestand($info['pad'], $herstelData, $type);
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
    $lijst = $tenantBackupMode ? buTenantLijst($info) : buBackupLijst($dataBackupMap, $info['pad']);
    $overzicht[$sleutel] = ['info'=>$info,'lijst'=>$lijst];
}
?><!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Back-ups</title>
<style>body{margin:0;background:#f6f2e8;color:#26351d;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.top{position:sticky;top:0;background:#fff;border-bottom:1px solid #ddd8c0;padding:15px 22px}.topin,.wrap{max-width:1120px;margin:auto}.top a{font-weight:700;color:#2d6260;text-decoration:none}.wrap{padding:28px 22px 70px}.melding{padding:12px 14px;border-radius:9px;margin:14px 0}.ok{background:#e8f5ee;color:#205b38}.fout{background:#fdeceb;color:#8b2e27}.kaart{background:#fff;border:1px solid #ddd8c0;border-radius:14px;padding:20px;margin-bottom:16px}.kop{display:flex;justify-content:space-between;gap:16px;align-items:center}.meta{color:#66705e;font-size:14px}.backup{display:grid;grid-template-columns:minmax(230px,1fr) auto;gap:12px;align-items:center;border-top:1px solid #ece8dc;padding:12px 0}.naam{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:12px;word-break:break-all}.btn{background:#fff;border:1px solid #c9c2aa;border-radius:8px;padding:9px 12px;font-weight:700;cursor:pointer}.bevestig{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.bevestig input{width:100px;padding:8px;border:1px solid #c9c2aa;border-radius:7px}.waarschuwing{background:#fff4d6;padding:12px;border-radius:9px;margin:12px 0}.snapshot-form{margin:0}@media(max-width:650px){.backup{grid-template-columns:1fr}.kop{align-items:flex-start;flex-direction:column}}</style></head><body>
<div class="top"><div class="topin"><a href="./">← Terug naar beheer</a></div></div><main class="wrap"><h1>Back-ups</h1>
<?php if($tenantBackupMode):?>
<p class="meta">Tenant-snapshots staan uitsluitend onder de private opslag van <strong><?=buEsc(tenantBackupTenantKey())?></strong>. Data-snapshots bevatten tenant- en onderdeelbinding die vóór herstel strikt wordt gevalideerd; dit is geen cryptografische ondertekening. Fotoboek- en sponsorbestanden gebruiken eveneens tenantgebonden manifests.</p>
<div class="waarschuwing"><strong>Herstellen overschrijft de huidige data of assets.</strong> Typ per herstel expliciet <code>HERSTEL</code>. Retentie: <?=tenantBackupBewaardagen()?> dagen, maximaal <?=tenantBackupMaxPerItem()?> datasnapshots per onderdeel en maximaal <?=tenantBackupMaxAssetSnapshots()?> assetsnapshots per scope; assetbackups worden bovendien begrensd op <?=number_format(tenantBackupMaxAssetBytes()/1024/1024,0,',','.')?> MB totaal.</div>
<?php else:?>
<p class="meta">Automatische snapshots van website- én verenigingsdata. Private PHP+JSON-data wordt bij herstel opnieuw met de server-side beschermingsregel geschreven.</p>
<div class="waarschuwing"><strong>Herstellen overschrijft de huidige data.</strong> Typ per herstel expliciet <code>HERSTEL</code>. De huidige versie wordt vóór het overschrijven opnieuw geback-upt.</div>
<?php endif;?>
<?php if(is_array($flash)):?><div class="melding <?=buEsc($flash['type']??'ok')?>"><?=buEsc($flash['tekst']??'')?></div><?php endif;?>
<?php foreach($overzicht as $sleutel=>$blok): $info=$blok['info']; $lijst=$blok['lijst']; ?>
<section class="kaart"><div class="kop"><div><h2><?=buEsc($info['label'])?></h2><div class="meta"><?=count($lijst)?> snapshot(s)<?php if(!$tenantBackupMode):?> · huidig bestand: <?=is_file($info['pad'])?'aanwezig':'nog niet aangemaakt'?><?php else:?> · <?=buEsc((string)($info['type']??''))?><?php endif;?></div></div>
<?php if($tenantBackupMode):?><form method="post" class="snapshot-form"><input type="hidden" name="formulier" value="backup_maken"><input type="hidden" name="csrf" value="<?=buEsc($csrfToken)?>"><input type="hidden" name="sleutel" value="<?=buEsc($sleutel)?>"><button class="btn" type="submit">Nu snapshot maken</button></form><?php endif;?></div>
<?php if(!$lijst):?><p class="meta">Nog geen snapshots beschikbaar.</p><?php else: foreach(array_slice($lijst,0,20) as $pad): $naam=basename($pad); $tijd=$tenantBackupMode&&is_dir($pad)?(@filemtime($pad.'/manifest.json')?:0):(@filemtime($pad)?:0); ?>
<div class="backup"><div><strong><?=buEsc($tijd?date('d-m-Y H:i:s',$tijd):'Onbekende datum')?></strong><div class="naam"><?=buEsc($naam)?></div></div><form class="bevestig" method="post"><input type="hidden" name="formulier" value="backup_herstellen"><input type="hidden" name="csrf" value="<?=buEsc($csrfToken)?>"><input type="hidden" name="sleutel" value="<?=buEsc($sleutel)?>"><input type="hidden" name="backup_bestand" value="<?=buEsc($naam)?>"><label>Typ <input name="bevestiging" autocomplete="off" placeholder="HERSTEL" required></label><button class="btn" type="submit">Deze versie herstellen</button></form></div>
<?php endforeach; if(count($lijst)>20):?><p class="meta">Alleen de 20 nieuwste worden hier getoond; oudere versies blijven zolang ze binnen de ingestelde retentie vallen.</p><?php endif; endif;?></section>
<?php endforeach;?></main></body></html>
