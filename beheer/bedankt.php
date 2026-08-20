<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/app/data-slot.php';
require_once dirname(__DIR__) . '/app/core/site.php';
require_once dirname(__DIR__) . '/app/content/public-content-store.php';

if (!$ingelogd) { header('Location: ../beheer.php'); exit; }
if (!siteModuleActief('aanmelden')) { http_response_code(404); echo 'De aanmeldmodule is voor deze vereniging niet ingeschakeld.'; exit; }
$rechten = authRechten(['bedankt' => 'Bedankt-pagina'], []);
if (!$isMaster && !in_array('bedankt', $rechten['toegestaneTabs'] ?? [], true)) { http_response_code(403); echo 'Geen toegang tot Bedankt-pagina.'; exit; }

$bestand = publicContentPad('bedankt');
if ($bestand === null) throw new RuntimeException('Bedankt-content is niet geregistreerd in de tenantcontentstore.');
$velden = [
    'title' => ['Titel', 200],
    'sub' => ['Introtekst', 500],
    'stap1' => ['Stap 1', 500],
    'stap2' => ['Stap 2', 500],
    'stap3' => ['Stap 3', 500],
    'iban_title' => ['Titel betalingsgegevens', 200],
    'iban_name' => ['Naam rekeninghouder', 200],
    'iban_ref' => ['Omschrijving / betalingskenmerk', 500],
    'btn_home' => ['Knop hoofdpagina', 200],
    'btn_location' => ['Knop locatie', 200],
];
$standaard = [
    'iban_number' => 'NL51 RABO 0367 6153 63',
    'title' => ['nl'=>'Welkom bij RC045!','en'=>'Welcome to RC045!','de'=>'Willkommen bei RC045!'],
    'sub' => ['nl'=>'Je aanmelding is ontvangen. Het bestuur neemt zo snel mogelijk contact met je op om je aanmelding te bevestigen.','en'=>'Your registration has been received. The board will contact you as soon as possible to confirm your membership.','de'=>'Deine Anmeldung ist eingegangen. Der Vorstand wird sich so schnell wie möglich bei dir melden, um deine Mitgliedschaft zu bestätigen.'],
    'stap1' => ['nl'=>'Maak de contributie over via onderstaande gegevens. Vermeld je naam duidelijk.','en'=>'Transfer the membership fee using the details below. Include your name clearly.','de'=>'Überweise den Mitgliedsbeitrag mit den untenstehenden Angaben. Gib deinen Namen deutlich an.'],
    'stap2' => ['nl'=>'Wacht op bevestiging van het bestuur per e-mail of WhatsApp.','en'=>'Wait for confirmation from the board by email or WhatsApp.','de'=>'Warte auf die Bestätigung des Vorstands per E-Mail oder WhatsApp.'],
    'stap3' => ['nl'=>'Je bent van harte welkom op onze baan zodra je lidmaatschap is bevestigd!','en'=>'You are very welcome at our track once your membership is confirmed!','de'=>'Du bist herzlich willkommen auf unserer Strecke, sobald deine Mitgliedschaft bestätigt ist!'],
    'iban_title' => ['nl'=>'Betalingsgegevens','en'=>'Payment details','de'=>'Zahlungsdaten'],
    'iban_name' => ['nl'=>'T.n.v. RC045','en'=>'In the name of RC045','de'=>'Auf den Namen RC045'],
    'iban_ref' => ['nl'=>'Vermeld bij overboeking: voornaam + achternaam + "contributie RC045 {jaar}"','en'=>'Reference: first name + last name + "contributie RC045 {jaar}"','de'=>'Verwendungszweck: Vorname + Nachname + "contributie RC045 {jaar}"'],
    'btn_home' => ['nl'=>'🏠 Naar de hoofdpagina','en'=>'🏠 Go to the homepage','de'=>'🏠 Zur Hauptseite'],
    'btn_location' => ['nl'=>'📍 Hoe kom ik er?','en'=>'📍 How to find us?','de'=>'📍 Wie komme ich hin?'],
];

function bdEsc($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function bdKort($v, int $max): string { $v=trim(is_scalar($v)?(string)$v:''); return function_exists('mb_substr')?mb_substr($v,0,$max,'UTF-8'):substr($v,0,$max); }
function bdMeng(array $standaard, array $data): array {
    $r=$standaard;
    foreach($data as $k=>$v){
        if($k==='iban_number' && is_scalar($v) && trim((string)$v)!==''){$r[$k]=(string)$v;continue;}
        if(!isset($r[$k])||!is_array($r[$k])||!is_array($v))continue;
        foreach(['nl','en','de'] as $t) if(isset($v[$t])&&is_scalar($v[$t])&&trim((string)$v[$t])!=='') $r[$k][$t]=(string)$v[$t];
    }
    return $r;
}
function bdSchrijf(string $pad, array $data): bool {
    global $dataBackupMap,$dataBackupBewaardagen,$dataBackupMaxPerBestand;
    if(publicContentIsTenantPad($pad)){
        $sleutel=publicContentSleutelVoorPad($pad);
        return $sleutel!==null&&publicContentSchrijfTenant($sleutel,$data,true);
    }
    if(function_exists('maakDataBackup')) maakDataBackup($pad,$dataBackupMap,$dataBackupBewaardagen,$dataBackupMaxPerBestand);
    $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); if($json===false||!is_dir(dirname($pad)))return false;
    $tmp=$pad.'.tmp.'.bin2hex(random_bytes(4));
    if(file_put_contents($tmp,$json,LOCK_EX)===false)return false;
    if(!@rename($tmp,$pad)){@unlink($tmp);return false;}
    return true;
}

$data=$standaard;
if(is_file($bestand)){ $raw=@file_get_contents($bestand); $j=$raw===false?null:json_decode($raw,true); if(is_array($j))$data=bdMeng($standaard,$j); }
$melding=''; $type='';
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    if(!csrfOk()){ $melding='Sessie verlopen. Ververs de pagina en probeer opnieuw.';$type='fout'; }
    else{
        $nieuw=['iban_number'=>bdKort($_POST['iban_number']??'',40)];
        foreach($velden as $k=>$info){ foreach(['nl','en','de'] as $t) $nieuw[$k][$t]=bdKort($_POST['bd'][$k][$t]??'',$info[1]); }
        if($nieuw['iban_number']===''||trim($nieuw['iban_name']['nl']??'')===''){ $melding='IBAN en Nederlandse naam rekeninghouder zijn verplicht.';$type='fout'; }
        else{
            $slot=dataSlotOpen();
            try{$ok=bdSchrijf($bestand,$nieuw);}finally{dataSlotDicht($slot);}
            if($ok){$data=bdMeng($standaard,$nieuw);$melding='Bedankt-pagina opgeslagen.';$type='ok';schrijfLog($logBestand,$huidigeGebruiker,'bedankt','bedankt-pagina bijgewerkt');}
            else{$melding='Opslaan mislukt.';$type='fout';}
        }
    }
}
?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Bedankt-pagina beheren</title>
<style>body{margin:0;background:#f6f2e8;color:#26351d;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.top{background:#fff;border-bottom:1px solid #ddd8c0;padding:16px 24px}.topin,.wrap{max-width:1180px;margin:auto}.topin{display:flex;justify-content:space-between}.top a{color:#2d6260;text-decoration:none;font-weight:700}.wrap{padding:30px 24px 70px}.kaart{background:#fff;border:1px solid #ddd8c0;border-radius:14px;padding:22px;margin-bottom:18px}.talen{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.veld{margin:0 0 18px}.veld label{display:block;font-weight:700;margin-bottom:6px}.taal label{font-size:12px;color:#68705f}.taal input,.taal textarea,.veld>input{width:100%;box-sizing:border-box;border:1px solid #cfcab7;border-radius:8px;padding:10px;font:inherit}.taal textarea{min-height:90px}.melding{padding:12px 14px;border-radius:9px;margin-bottom:18px}.ok{background:#e8f5ee;color:#205b38}.fout{background:#fdeceb;color:#8b2e27}.btn{background:#3a7a77;color:#fff;border:0;border-radius:9px;padding:11px 18px;font-weight:700;cursor:pointer}@media(max-width:800px){.talen{grid-template-columns:1fr}}</style></head><body>
<div class="top"><div class="topin"><a href="./">← Terug naar beheer</a><a href="../bedankt.html" target="_blank" rel="noopener">Bekijk pagina ↗</a></div></div>
<main class="wrap"><h1>Bedankt-pagina</h1><p>Modulaire editor voor de bevestigingspagina na aanmelden.</p>
<?php if($melding!==''):?><div class="melding <?=bdEsc($type)?>"><?=bdEsc($melding)?></div><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=bdEsc($csrfToken)?>">
<section class="kaart"><div class="veld"><label>IBAN-nummer</label><input name="iban_number" maxlength="40" value="<?=bdEsc($data['iban_number']??'')?>"></div><p>Het token <code>{jaar}</code> in het betalingskenmerk blijft automatisch dynamisch.</p></section>
<?php foreach($velden as $k=>$info): $blok=$info[1]>200;?><section class="kaart"><div class="veld"><label><?=bdEsc($info[0])?></label><div class="talen"><?php foreach(['nl'=>'Nederlands','en'=>'Engels','de'=>'Duits'] as $t=>$tl):?><div class="taal"><label><?=bdEsc($tl)?><?=$t==='nl'?'':' (optioneel)'?></label><?php if($blok):?><textarea name="bd[<?=bdEsc($k)?>][<?=$t?>]" maxlength="<?=$info[1]?>"><?=bdEsc($data[$k][$t]??'')?></textarea><?php else:?><input name="bd[<?=bdEsc($k)?>][<?=$t?>]" maxlength="<?=$info[1]?>" value="<?=bdEsc($data[$k][$t]??'')?>"><?php endif;?></div><?php endforeach;?></div></div></section><?php endforeach;?>
<button class="btn" type="submit">Opslaan</button></form></main></body></html>