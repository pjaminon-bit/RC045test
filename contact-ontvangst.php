<?php
// ============================================================
// Publiek contactformulier -> private contactinbox
// ============================================================
header('X-Robots-Tag: noindex, nofollow');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/app/core/site.php';
require_once __DIR__.'/contactberichten-opslag.php';
require_once __DIR__.'/aanmeldingen-opslag.php'; // hergebruik geharde publieke rate-limitopslag

function contactAntwoord(int $status,string $tekst): void
{
    http_response_code($status);
    echo json_encode(['ok'=>$status<400,'melding'=>$tekst],JSON_UNESCAPED_UNICODE);
    exit;
}

if(!siteModuleActief('website'))contactAntwoord(404,'Contact is niet beschikbaar.');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')contactAntwoord(405,'Alleen POST.');
if(trim((string)($_POST['website']??''))!=='')contactAntwoord(200,'Ontvangen.');

$naam=trim((string)($_POST['naam']??''));
$email=trim((string)($_POST['email']??''));
$telefoon=trim((string)($_POST['telefoon']??''));
$onderwerp=trim((string)($_POST['onderwerp']??''));
$bericht=trim((string)($_POST['bericht']??''));

if($naam==='')contactAntwoord(400,'Naam is verplicht.');
if($bericht==='')contactAntwoord(400,'Bericht is verplicht.');
if($email===''&&$telefoon==='')contactAntwoord(400,'Vul een mailadres of telefoonnummer in.');
if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))contactAntwoord(400,'Dat mailadres ziet er niet geldig uit.');
if($telefoon!==''&&strlen((string)preg_replace('/\D+/','',$telefoon))<9)contactAntwoord(400,'Dat telefoonnummer ziet er niet geldig uit.');
if((function_exists('mb_strlen')?mb_strlen($bericht,'UTF-8'):strlen($bericht))>5000)contactAntwoord(400,'Het bericht is te lang.');

$slot=dataSlotOpen();
try{
    $nu=time();
    $ipSleutel=hash('sha256','contact|'.(string)($_SERVER['REMOTE_ADDR']??'onbekend'));
    try{
        if(!aanmeldenPogingRegistreer($ipSleutel,$nu,10,3600))contactAntwoord(429,'Te veel berichten achter elkaar. Probeer het later opnieuw.');
    }catch(Throwable $e){
        error_log('[platform] contact-rate-limit niet beschikbaar: '.$e->getMessage());
        contactAntwoord(503,'Contact is tijdelijk niet beschikbaar. Probeer het later opnieuw.');
    }

    $inbox=contactBerichtenLees();
    contactBerichtenPasRetentieToe($inbox,$nu);

    // Herhaalde browser-submit binnen tien minuten wordt idempotent behandeld.
    $emailKlein=strtolower($email);$telCompact=(string)preg_replace('/\D+/','',$telefoon);$berichtHash=hash('sha256',$naam."\n".$onderwerp."\n".$bericht);
    foreach((array)($inbox['berichten']??[]) as $b){
        if(!is_array($b))continue;
        $gemaakt=strtotime((string)($b['aangemaakt']??''));
        if($gemaakt===false||$gemaakt<$nu-600)continue;
        $zelfdeContact=($emailKlein!==''&&strtolower(trim((string)($b['email']??'')))===$emailKlein)||($telCompact!==''&&(string)preg_replace('/\D+/','',(string)($b['telefoon']??''))===$telCompact);
        $zelfdeBericht=hash_equals(hash('sha256',(string)($b['naam']??'')."\n".(string)($b['onderwerp']??'')."\n".(string)($b['bericht']??'')),$berichtHash);
        if($zelfdeContact&&$zelfdeBericht)contactAntwoord(200,'Ontvangen.');
    }

    $inbox['berichten'][]=contactBerichtNormaliseer(['naam'=>$naam,'email'=>$email,'telefoon'=>$telefoon,'onderwerp'=>$onderwerp,'bericht'=>$bericht]);
    if(!contactBerichtenSchrijf($inbox))contactAntwoord(500,'Opslaan mislukt.');
}finally{
    dataSlotDicht($slot);
}
contactAntwoord(200,'Ontvangen.');
