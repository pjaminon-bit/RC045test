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

/**
 * Publieke abuse-state hoort nooit in de gedeelde release. Externe tenants
 * krijgen een eigen bestand onder private_root/security; standalone RC045
 * gebruikt de reeds server-only/gegitignorede data-backups-map.
 */
function aanmeldenPogingenPad(): string
{
    $config=require __DIR__.'/site-config.php';
    $privateRoot=tenantRuntimePrivateRoot(is_array($config)?$config:[]);
    if($privateRoot!==null)return $privateRoot.DIRECTORY_SEPARATOR.'security'.DIRECTORY_SEPARATOR.'aanmelden-pogingen.json';

    $map=__DIR__.DIRECTORY_SEPARATOR.'data-backups';
    // Standalone installaties kunnen deze gitignored runtime-map nog niet
    // hebben. Alleen het exacte lokale childpad wordt aangemaakt; een symlink
    // of ander bestaand bestand wordt niet gevolgd en faalt verderop gesloten.
    if(!is_dir($map)&&!is_link($map)&&!file_exists($map)){
        if(@mkdir($map,0750,true))@chmod($map,0750);
    }
    return $map.DIRECTORY_SEPARATOR.'aanmelden-pogingen.json';
}

function aanmeldenPogingenPadVeilig(string $pad): bool
{
    $map=dirname($pad);
    if(!is_dir($map)||is_link($map)||is_link($pad))return false;
    $mapReal=realpath($map);if($mapReal===false)return false;
    $config=require __DIR__.'/site-config.php';
    $privateRoot=tenantRuntimePrivateRoot(is_array($config)?$config:[]);
    if($privateRoot!==null){
        if(is_link($privateRoot))return false;
        $rootReal=realpath($privateRoot);if($rootReal===false)return false;
        $verwacht=$rootReal.DIRECTORY_SEPARATOR.'security';
        return hash_equals(str_replace('\\','/',$verwacht),str_replace('\\','/',$mapReal));
    }
    $legacy=realpath(__DIR__.DIRECTORY_SEPARATOR.'data-backups');
    return $legacy!==false&&hash_equals(str_replace('\\','/',$legacy),str_replace('\\','/',$mapReal));
}

function aanmeldenPogingenLees(string $pad): array
{
    if(!aanmeldenPogingenPadVeilig($pad))throw new RuntimeException('Aanmeld-rate-limitopslag valt buiten de veilige tenantlocatie.');
    if(!file_exists($pad))return[];
    if(!is_file($pad)||is_link($pad))throw new RuntimeException('Aanmeld-rate-limitopslag is geen veilig regulier bestand.');
    $raw=@file_get_contents($pad);if($raw===false)throw new RuntimeException('Aanmeld-rate-limitopslag kon niet worden gelezen.');
    $data=json_decode($raw,true);if(!is_array($data))throw new RuntimeException('Aanmeld-rate-limitopslag bevat ongeldige JSON.');
    return$data;
}

function aanmeldenPogingenSchrijf(string $pad,array $pogingen): bool
{
    if(!aanmeldenPogingenPadVeilig($pad))return false;
    $json=json_encode($pogingen,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);if($json===false)return false;
    try{$suffix=bin2hex(random_bytes(5));}catch(Throwable $e){return false;}
    $tmp=$pad.'.tmp.'.$suffix;
    if(is_link($tmp)||@file_put_contents($tmp,$json,LOCK_EX)===false)return false;
    @chmod($tmp,0640);
    if(!aanmeldenPogingenPadVeilig($pad)||is_link($pad)||!@rename($tmp,$pad)){@unlink($tmp);return false;}
    @chmod($pad,0640);
    return true;
}

/** Consumeert één poging. false betekent limiet bereikt; opslagfouten gooien. */
function aanmeldenPogingRegistreer(string $ipSleutel,int $nu,int $drempel=5,int $venster=3600): bool
{
    if(preg_match('/^[0-9a-f]{64}$/D',$ipSleutel)!==1)throw new RuntimeException('Ongeldige aanmeld-rate-limitsleutel.');
    $drempel=max(1,min(100,$drempel));$venster=max(60,min(86400,$venster));
    $pad=aanmeldenPogingenPad();$pogingen=aanmeldenPogingenLees($pad);
    foreach($pogingen as $k=>$tijden){
        if(preg_match('/^[0-9a-f]{64}$/D',(string)$k)!==1){unset($pogingen[$k]);continue;}
        $recent=array_values(array_filter((array)$tijden,static fn($t)=>is_numeric($t)&&(int)$t>$nu-$venster));
        if($recent)$pogingen[$k]=$recent;else unset($pogingen[$k]);
    }
    $recent=(array)($pogingen[$ipSleutel]??[]);
    if(count($recent)>=$drempel){
        // Pruning ook bij blokkade duurzaam opslaan; kan dit niet, dan fail-closed.
        if(!aanmeldenPogingenSchrijf($pad,$pogingen))throw new RuntimeException('Aanmeld-rate-limitopslag kon niet worden bijgewerkt.');
        return false;
    }
    $recent[]=$nu;$pogingen[$ipSleutel]=$recent;
    if(!aanmeldenPogingenSchrijf($pad,$pogingen))throw new RuntimeException('Aanmeld-rate-limitopslag kon niet worden bijgewerkt.');
    return true;
}

function aanmeldingenVindIndex(array $data,string $id): ?int{foreach($data['aanmeldingen'] as $i=>$a)if(is_array($a)&&($a['id']??'')===$id)return$i;return null;}
function aanmeldingenOpen(): array{$lijst=array_values(array_filter(aanmeldingenLees()['aanmeldingen'],static fn($a)=>is_array($a)&&($a['status']??'nieuw')==='nieuw'));usort($lijst,static fn($a,$b)=>strcmp((string)($b['aangemaakt']??''),(string)($a['aangemaakt']??'')));return$lijst;}
function aanmeldingenPasRetentieToe(array &$data,?int $nu=null): int
{
    $nu=$nu??time();$grens=$nu-aanmeldingenBewaardagen()*86400;$voor=count((array)($data['aanmeldingen']??[]));
    $data['aanmeldingen']=array_values(array_filter((array)($data['aanmeldingen']??[]),static function($a)use($grens){
        if(!is_array($a))return false;
        $status=(string)($a['status']??'nieuw');
        // Onbeoordeelde persoonsgegevens hebben eveneens een maximale termijn,
        // gerekend vanaf ontvangst. Een latere wijziging mag die termijn niet
        // ongemerkt verlengen. Beoordeelde records rekenen vanaf beoordeling.
        $bron=$status==='nieuw'?($a['aangemaakt']??''):($a['beoordeeld_op']??$a['gewijzigd']??$a['aangemaakt']??'');
        $moment=strtotime((string)$bron);
        return $moment!==false&&$moment>=$grens;
    }));
    return$voor-count($data['aanmeldingen']);
}
function aanmeldingenOpschonenBewaartermijn(): int{$data=aanmeldingenLees();$aantal=aanmeldingenPasRetentieToe($data);if($aantal>0&&!aanmeldingenSchrijf($data))return 0;return$aantal;}
