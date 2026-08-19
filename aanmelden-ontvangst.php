<?php
// ============================================================
// Openbare aanmelding ontvangen -> private inbox
// ============================================================
// Een inzending wordt pas een lid nadat een bevoegde beheerder haar in
// Beheer > Aanmeldingen accepteert. Browserbedragen worden niet vertrouwd;
// contributie wordt hier opnieuw afgeleid uit de configureerbare typen.
// ============================================================
header('X-Robots-Tag: noindex, nofollow');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/app/core/site.php';
require_once __DIR__ . '/leden-opslag.php';
require_once __DIR__ . '/aanmeldingen-opslag.php';
require_once __DIR__ . '/app/leden/lidmaatschap.php';

function aanmeldenAntwoord(int $status,string $tekst): void
{
    http_response_code($status);
    echo json_encode(['ok'=>$status<400,'melding'=>$tekst],JSON_UNESCAPED_UNICODE);
    exit;
}

if(!siteModuleActief('aanmelden'))aanmeldenAntwoord(404,'Aanmelden is niet beschikbaar.');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')aanmeldenAntwoord(405,'Alleen POST.');
if(trim((string)($_POST['website']??''))!=='')aanmeldenAntwoord(200,'Ontvangen.');

$voornaam=trim((string)($_POST['voornaam']??''));
$achternaam=trim((string)($_POST['achternaam']??''));
$email=trim((string)($_POST['email']??''));
$telefoon=trim((string)($_POST['mobiel']??''));
if($voornaam===''||$achternaam==='')aanmeldenAntwoord(400,'Voornaam en achternaam zijn verplicht.');
if($email===''&&$telefoon==='')aanmeldenAntwoord(400,'Vul een mailadres of telefoonnummer in.');
if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))aanmeldenAntwoord(400,'Dat mailadres ziet er niet geldig uit.');

// Eén lock beschermt limiter, duplicate-check en inbox-write tegen parallelle
// inzendingen. Hierdoor kan dubbelklikken nooit twee records aanmaken.
$slot=dataSlotOpen();
try{
    $pogingenPad=__DIR__.'/aanmelden-pogingen.php';
    $nu=time();$ip=(string)($_SERVER['REMOTE_ADDR']??'onbekend');$ipSleutel=hash('sha256',$ip);$pogingen=[];
    if(is_file($pogingenPad)){
        $ruw=@file_get_contents($pogingenPad);$start=$ruw===false?false:strpos($ruw,'{');
        if($start!==false){$gelezen=json_decode(substr($ruw,$start),true);if(is_array($gelezen))$pogingen=$gelezen;}
    }
    foreach($pogingen as $k=>$tijden){$pogingen[$k]=array_values(array_filter((array)$tijden,static fn($t)=>is_numeric($t)&&(int)$t>$nu-3600));if(!$pogingen[$k])unset($pogingen[$k]);}
    if(count((array)($pogingen[$ipSleutel]??[]))>=5)aanmeldenAntwoord(429,'Te veel aanmeldingen achter elkaar. Probeer het later opnieuw.');
    $pogingen[$ipSleutel][]=$nu;
    @file_put_contents($pogingenPad,"<?php exit; ?>\n".json_encode($pogingen,JSON_UNESCAPED_UNICODE),LOCK_EX);

    $inbox=aanmeldingenLees();
    $emailKlein=strtolower($email);$telCompact=preg_replace('/\D+/','',$telefoon);
    foreach($inbox['aanmeldingen'] as $a){
        if(!is_array($a)||($a['status']??'nieuw')!=='nieuw')continue;
        $gemaakt=strtotime((string)($a['aangemaakt']??''));if($gemaakt!==false&&$gemaakt<$nu-86400)continue;
        $zelfdeEmail=$emailKlein!==''&&strtolower(trim((string)($a['email']??'')))===$emailKlein;
        $zelfdeTel=$telCompact!==''&&preg_replace('/\D+/','',(string)($a['telefoon']??''))===$telCompact;
        if($zelfdeEmail||$zelfdeTel)aanmeldenAntwoord(200,'Ontvangen.');
    }
    // Een reeds bestaand lid niet opnieuw als aanvraag klaarzetten.
    $leden=ledenLees();
    if($emailKlein!=='')foreach($leden['leden'] as $lid)if(strtolower(trim((string)($lid['email']??'')))===$emailKlein)aanmeldenAntwoord(200,'Ontvangen.');

    [$straat,$huisnummer]=ledenSplitsAdres($_POST['straat']??'',$_POST['huisnummer']??'');
    $geb=ledenParseDatum($_POST['geboortedatum']??'');
    $leeftijd=$geb===''?null:ledenLeeftijd($geb,date('Y').'-01-01');
    $type=lidmaatschapTypeVoorLeeftijd($leeftijd);
    // Geen leeftijdsregel? Gebruik het eerste actieve type als veilige fallback.
    if($type===null)foreach(lidmaatschapLees()['types'] as $kandidaat)if(!empty($kandidaat['actief'])){$type=$kandidaat;break;}
    $bedrag=$type?lidmaatschapBedragVoorMaand($type,(int)date('n')):null;

    $aanmelding=aanmeldingNormaliseer([
        'voornaam'=>$voornaam,'tussenvoegsel'=>$_POST['tussenvoegsel']??'','achternaam'=>$achternaam,
        'geboortedatum'=>$geb,'straat'=>$straat,'huisnummer'=>$huisnummer,'postcode'=>$_POST['postcode']??'',
        'gemeente'=>$_POST['stad']??'','land'=>$_POST['land']??'','telefoon'=>$telefoon,'email'=>$email,
        'lidmaatschap_type'=>$type['id']??'','berekend_bedrag'=>$bedrag,'bron'=>'aanmeldformulier',
    ]);
    $inbox['aanmeldingen'][]=$aanmelding;
    if(!aanmeldingenSchrijf($inbox))aanmeldenAntwoord(500,'Opslaan mislukt.');
}finally{dataSlotDicht($slot);}

aanmeldenAntwoord(200,'Ontvangen.');
