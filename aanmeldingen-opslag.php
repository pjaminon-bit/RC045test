<?php
// ============================================================
// Aanmeldingen-inbox
// ============================================================
require_once __DIR__ . '/app/data-slot.php';
require_once __DIR__ . '/app/storage/private-store.php';

define('AANMELDINGEN_VOORLOOP', "<?php exit; ?>\n");
function aanmeldingenBestandPad(): string{return __DIR__.'/aanmeldingen-data.php';}
function aanmeldingenLeeg(): array{return['updated'=>date('c'),'aanmeldingen'=>[]];}
function aanmeldingNieuwId(): string{return'app_'.bin2hex(random_bytes(10));}
function aanmeldingenBewaardagen(): int{$config=require __DIR__.'/site-config.php';$dagen=(int)($config['privacy']['aanmeldingen_bewaardagen']??90);return max(7,min(730,$dagen));}
function aanmeldingenJsonLees(): array{$pad=aanmeldingenBestandPad();if(!is_file($pad))return aanmeldingenLeeg();$ruw=@file_get_contents($pad);if($ruw===false)return aanmeldingenLeeg();$start=strpos($ruw,'{');if($start===false)return aanmeldingenLeeg();$data=json_decode(substr($ruw,$start),true);return is_array($data)&&isset($data['aanmeldingen'])&&is_array($data['aanmeldingen'])?$data:aanmeldingenLeeg();}
function aanmeldingenJsonSchrijf(array $data): bool{$data['updated']=date('c');$json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);if($json===false)return false;$pad=aanmeldingenBestandPad();if(function_exists('maakDataBackup')){global$dataBackupMap,$dataBackupBewaardagen,$dataBackupMaxPerBestand;maakDataBackup($pad,$dataBackupMap,$dataBackupBewaardagen,$dataBackupMaxPerBestand);}try{$suffix=bin2hex(random_bytes(5));}catch(Throwable $e){$suffix=str_replace('.','',(string)microtime(true));}$tmp=$pad.'.tmp.'.$suffix;if(@file_put_contents($tmp,AANMELDINGEN_VOORLOOP.$json,LOCK_EX)===false)return false;if(!@rename($tmp,$pad)){@unlink($tmp);return false;}return true;}
function aanmeldingenLees(): array{$data=privateStoreLees('aanmeldingen','aanmeldingenJsonLees');return isset($data['aanmeldingen'])&&is_array($data['aanmeldingen'])?$data:aanmeldingenLeeg();}
function aanmeldingenSchrijf(array $data): bool{return privateStoreSchrijf('aanmeldingen',$data,'aanmeldingenJsonSchrijf');}
function aanmeldingKort($waarde,int $max): string{$tekst=trim(is_scalar($waarde)?(string)$waarde:'');return function_exists('mb_substr')?mb_substr($tekst,0,$max,'UTF-8'):substr($tekst,0,$max);}
function aanmeldingNormaliseer(array $invoer): array
{
    $nu=date('c');$jaar=(int)($invoer['contributie_jaar']??date('Y'));if($jaar<2000||$jaar>2099)$jaar=(int)date('Y');$maand=(int)($invoer['contributie_maand']??date('n'));$maand=max(1,min(12,$maand));
    return['id'=>aanmeldingNieuwId(),'status'=>'nieuw','voornaam'=>aanmeldingKort($invoer['voornaam']??'',60),'tussenvoegsel'=>aanmeldingKort($invoer['tussenvoegsel']??'',30),'achternaam'=>aanmeldingKort($invoer['achternaam']??'',80),'geboortedatum'=>aanmeldingKort($invoer['geboortedatum']??'',10),'straat'=>aanmeldingKort($invoer['straat']??'',100),'huisnummer'=>aanmeldingKort($invoer['huisnummer']??'',20),'postcode'=>aanmeldingKort($invoer['postcode']??'',20),'gemeente'=>aanmeldingKort($invoer['gemeente']??'',80),'land'=>aanmeldingKort($invoer['land']??'',40),'telefoon'=>aanmeldingKort($invoer['telefoon']??'',40),'email'=>aanmeldingKort($invoer['email']??'',120),'lidmaatschap_type'=>aanmeldingKort($invoer['lidmaatschap_type']??'',40),'contributie_jaar'=>$jaar,'contributie_maand'=>$maand,'berekend_bedrag'=>isset($invoer['berekend_bedrag'])&&is_numeric($invoer['berekend_bedrag'])?round(max(0,(float)$invoer['berekend_bedrag']),2):null,'berekend_inschrijfgeld'=>isset($invoer['berekend_inschrijfgeld'])&&is_numeric($invoer['berekend_inschrijfgeld'])?round(max(0,(float)$invoer['berekend_inschrijfgeld']),2):null,'bron'=>(string)($invoer['bron']??'aanmeldformulier'),'aangemaakt'=>$nu,'gewijzigd'=>$nu,'beoordeeld_op'=>'','beoordeeld_door'=>'','lid_id'=>'','opmerking'=>''];
}
function aanmeldingenVindIndex(array $data,string $id): ?int{foreach($data['aanmeldingen'] as $i=>$a)if(is_array($a)&&($a['id']??'')===$id)return$i;return null;}
function aanmeldingenOpen(): array{$lijst=array_values(array_filter(aanmeldingenLees()['aanmeldingen'],static fn($a)=>is_array($a)&&($a['status']??'nieuw')==='nieuw'));usort($lijst,static fn($a,$b)=>strcmp((string)($b['aangemaakt']??''),(string)($a['aangemaakt']??'')));return$lijst;}
function aanmeldingenPasRetentieToe(array &$data,?int $nu=null): int
{
    $nu=$nu??time();$grens=$nu-aanmeldingenBewaardagen()*86400;$voor=count((array)($data['aanmeldingen']??[]));$data['aanmeldingen']=array_values(array_filter((array)($data['aanmeldingen']??[]),static function($a)use($grens){if(!is_array($a))return false;$status=(string)($a['status']??'nieuw');if($status==='nieuw')return true;$moment=strtotime((string)($a['beoordeeld_op']??$a['gewijzigd']??$a['aangemaakt']??''));return$moment===false||$moment>=$grens;}));return$voor-count($data['aanmeldingen']);
}
function aanmeldingenOpschonenBewaartermijn(): int{$data=aanmeldingenLees();$aantal=aanmeldingenPasRetentieToe($data);if($aantal>0&&!aanmeldingenSchrijf($data))return 0;return$aantal;}
