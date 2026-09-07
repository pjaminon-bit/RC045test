<?php
// ============================================================
// Openbare aanmelding ontvangen -> private inbox
// ============================================================
header('X-Robots-Tag: noindex, nofollow');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/app/core/site.php';
require_once __DIR__.'/app/storage/domein-repositories.php';
require_once __DIR__.'/aanmeldingen-opslag.php';
require_once __DIR__.'/app/leden/lidmaatschap.php';
require_once __DIR__.'/app/notifications/tenant-notifications.php';
require_once __DIR__.'/app/operational-log.php';
function aanmeldenAntwoord(int $status,string $tekst): void{http_response_code($status);echo json_encode(['ok'=>$status<400,'melding'=>$tekst],JSON_UNESCAPED_UNICODE);exit;}
if(!siteModuleActief('aanmelden'))aanmeldenAntwoord(404,'Aanmelden is niet beschikbaar.');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')aanmeldenAntwoord(405,'Alleen POST.');
if(trim((string)($_POST['website']??''))!=='')aanmeldenAntwoord(200,'Ontvangen.');

$voornaam=trim((string)($_POST['voornaam']??''));$achternaam=trim((string)($_POST['achternaam']??''));$email=trim((string)($_POST['email']??''));$telefoon=trim((string)($_POST['mobiel']??''));
if($voornaam===''||$achternaam==='')aanmeldenAntwoord(400,'Voornaam en achternaam zijn verplicht.');
if($email===''&&$telefoon==='')aanmeldenAntwoord(400,'Vul een mailadres of telefoonnummer in.');
if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))aanmeldenAntwoord(400,'Dat mailadres ziet er niet geldig uit.');

$geb=ledenParseDatum($_POST['geboortedatum']??'');
if($geb==='')aanmeldenAntwoord(400,'Vul een geldige geboortedatum in.');
$jaar=(int)date('Y');$maand=(int)date('n');$leeftijd=ledenLeeftijd($geb,$jaar.'-01-01');
$typeId=trim((string)($_POST['lidmaatschap_type']??''));
$type=$typeId===''?lidmaatschapTypeVoorLeeftijd($leeftijd):lidmaatschapTypeOpId($typeId);
if(!$type||!lidmaatschapTypeToegestaanVoorLeeftijd($type,$leeftijd))aanmeldenAntwoord(400,'Voor deze leeftijd is nog geen geldig lidmaatschapstype ingesteld.');
$bedrag=lidmaatschapBedragVoorMaand($type,$maand);$inschrijfgeld=(float)($type['inschrijfgeld']??0);

$slot=dataSlotOpen();
try{
    $nu=time();$ipSleutel=hash('sha256',(string)($_SERVER['REMOTE_ADDR']??'onbekend'));
    try{
        if(!aanmeldenPogingRegistreer($ipSleutel,$nu,5,3600))aanmeldenAntwoord(429,'Te veel aanmeldingen achter elkaar. Probeer het later opnieuw.');
    }catch(Throwable $e){
        error_log('[platform] aanmeld-rate-limit niet beschikbaar: '.$e->getMessage());
        aanmeldenAntwoord(503,'Aanmelden is tijdelijk niet beschikbaar. Probeer het later opnieuw.');
    }
    $inbox=aanmeldingenLees();$emailKlein=strtolower($email);$telCompact=preg_replace('/\D+/','',$telefoon);
    foreach($inbox['aanmeldingen'] as $a){if(!is_array($a)||($a['status']??'nieuw')!=='nieuw')continue;$gemaakt=strtotime((string)($a['aangemaakt']??''));if($gemaakt!==false&&$gemaakt<$nu-86400)continue;$zelfdeEmail=$emailKlein!==''&&strtolower(trim((string)($a['email']??'')))===$emailKlein;$zelfdeTel=$telCompact!==''&&preg_replace('/\D+/','',(string)($a['telefoon']??''))===$telCompact;if($zelfdeEmail||$zelfdeTel)aanmeldenAntwoord(200,'Ontvangen.');}
    $leden=repoLedenLees();if($emailKlein!=='')foreach((array)($leden['leden']??[]) as $lid)if(is_array($lid)&&strtolower(trim((string)($lid['email']??'')))===$emailKlein)aanmeldenAntwoord(200,'Ontvangen.');
    [$straat,$huisnummer]=ledenSplitsAdres($_POST['straat']??'',$_POST['huisnummer']??'');
    $aanmelding=aanmeldingNormaliseer(['voornaam'=>$voornaam,'tussenvoegsel'=>$_POST['tussenvoegsel']??'','achternaam'=>$achternaam,'geboortedatum'=>$geb,'straat'=>$straat,'huisnummer'=>$huisnummer,'postcode'=>$_POST['postcode']??'','gemeente'=>$_POST['stad']??'','land'=>$_POST['land']??'','telefoon'=>$telefoon,'email'=>$email,'lidmaatschap_type'=>$type['id'],'contributie_jaar'=>$jaar,'contributie_maand'=>$maand,'berekend_bedrag'=>$bedrag,'berekend_inschrijfgeld'=>$inschrijfgeld,'bron'=>'aanmeldformulier']);
    $inbox['aanmeldingen'][]=$aanmelding;

    // De private aanmeldingeninbox is authoritative. Notification enqueue is
    // uitsluitend een opvolgbaar side-effect na een geslaagde primaire write.
    if(!aanmeldingenSchrijf($inbox))aanmeldenAntwoord(500,'Opslaan mislukt.');
    if(tenantNotificationShouldEnqueue()){
        try{
            if(!tenantNotificationEnqueue('membership.received',(string)$aanmelding['id'])){
                vpOps46Log(siteConfig(),'notification_enqueue_failed','error',['component'=>'notification','check'=>'membership.received','code'=>'outbox_write_failed']);
                error_log('[platform] notification enqueue mislukt: outbox_write_failed');
            }
        }catch(Throwable $e){
            vpOps46Log(siteConfig(),'notification_enqueue_failed','error',['component'=>'notification','check'=>'membership.received','code'=>'outbox_exception','error_class'=>get_class($e)]);
            error_log('[platform] notification enqueue mislukt: outbox_exception '.get_class($e));
        }
    }
}finally{dataSlotDicht($slot);}
aanmeldenAntwoord(200,'Ontvangen.');