<?php
// ============================================================
// Aanmeldingen-inbox
// ============================================================
// Openbare inschrijvingen worden niet meer direct als lid opgeslagen. Ze
// komen eerst in deze private inbox en worden pas na acceptatie een lid.
// ============================================================
require_once __DIR__ . '/app/data-slot.php';
require_once __DIR__ . '/app/storage/private-store.php';

define('AANMELDINGEN_VOORLOOP', "<?php exit; ?>\n");

function aanmeldingenBestandPad(): string { return __DIR__ . '/aanmeldingen-data.php'; }
function aanmeldingenLeeg(): array { return ['updated'=>date('c'),'aanmeldingen'=>[]]; }
function aanmeldingNieuwId(): string { return 'app_' . bin2hex(random_bytes(10)); }

function aanmeldingenJsonLees(): array
{
    $pad=aanmeldingenBestandPad();
    if(!is_file($pad))return aanmeldingenLeeg();
    $ruw=@file_get_contents($pad);if($ruw===false)return aanmeldingenLeeg();
    $start=strpos($ruw,'{');if($start===false)return aanmeldingenLeeg();
    $data=json_decode(substr($ruw,$start),true);
    return is_array($data)&&isset($data['aanmeldingen'])&&is_array($data['aanmeldingen'])?$data:aanmeldingenLeeg();
}

function aanmeldingenJsonSchrijf(array $data): bool
{
    $data['updated']=date('c');
    $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);if($json===false)return false;
    $pad=aanmeldingenBestandPad();
    if(function_exists('maakDataBackup')){
        global $dataBackupMap,$dataBackupBewaardagen,$dataBackupMaxPerBestand;
        maakDataBackup($pad,$dataBackupMap,$dataBackupBewaardagen,$dataBackupMaxPerBestand);
    }
    $tmp=$pad.'.tmp.'.bin2hex(random_bytes(4));
    if(file_put_contents($tmp,AANMELDINGEN_VOORLOOP.$json,LOCK_EX)===false)return false;
    if(!@rename($tmp,$pad)){@unlink($tmp);return false;}
    return true;
}

function aanmeldingenLees(): array
{
    $data=privateStoreLees('aanmeldingen','aanmeldingenJsonLees');
    return isset($data['aanmeldingen'])&&is_array($data['aanmeldingen'])?$data:aanmeldingenLeeg();
}

function aanmeldingenSchrijf(array $data): bool
{
    return privateStoreSchrijf('aanmeldingen',$data,'aanmeldingenJsonSchrijf');
}

function aanmeldingKort($waarde,int $max): string
{
    $tekst=trim(is_scalar($waarde)?(string)$waarde:'');
    return function_exists('mb_substr')?mb_substr($tekst,0,$max,'UTF-8'):substr($tekst,0,$max);
}

function aanmeldingNormaliseer(array $invoer): array
{
    $nu=date('c');
    return [
        'id'=>aanmeldingNieuwId(),
        'status'=>'nieuw',
        'voornaam'=>aanmeldingKort($invoer['voornaam']??'',60),
        'tussenvoegsel'=>aanmeldingKort($invoer['tussenvoegsel']??'',30),
        'achternaam'=>aanmeldingKort($invoer['achternaam']??'',80),
        'geboortedatum'=>aanmeldingKort($invoer['geboortedatum']??'',10),
        'straat'=>aanmeldingKort($invoer['straat']??'',100),
        'huisnummer'=>aanmeldingKort($invoer['huisnummer']??'',20),
        'postcode'=>aanmeldingKort($invoer['postcode']??'',20),
        'gemeente'=>aanmeldingKort($invoer['gemeente']??'',80),
        'land'=>aanmeldingKort($invoer['land']??'',40),
        'telefoon'=>aanmeldingKort($invoer['telefoon']??'',40),
        'email'=>aanmeldingKort($invoer['email']??'',120),
        'lidmaatschap_type'=>aanmeldingKort($invoer['lidmaatschap_type']??'',40),
        'berekend_bedrag'=>isset($invoer['berekend_bedrag'])&&is_numeric($invoer['berekend_bedrag'])?round(max(0,(float)$invoer['berekend_bedrag']),2):null,
        'bron'=>(string)($invoer['bron']??'aanmeldformulier'),
        'aangemaakt'=>$nu,
        'gewijzigd'=>$nu,
        'beoordeeld_op'=>'',
        'beoordeeld_door'=>'',
        'lid_id'=>'',
        'opmerking'=>'',
    ];
}

function aanmeldingenVindIndex(array $data,string $id): ?int
{
    foreach($data['aanmeldingen'] as $i=>$a)if(is_array($a)&&($a['id']??'')===$id)return $i;
    return null;
}

function aanmeldingenOpen(): array
{
    $lijst=array_values(array_filter(aanmeldingenLees()['aanmeldingen'],static fn($a)=>is_array($a)&&($a['status']??'nieuw')==='nieuw'));
    usort($lijst,static fn($a,$b)=>strcmp((string)($b['aangemaakt']??''),(string)($a['aangemaakt']??'')));
    return $lijst;
}
