<?php
require_once dirname(__DIR__) . '/storage/domein-repositories.php';
require_once dirname(__DIR__,2) . '/app/data-slot.php';
require_once __DIR__ . '/groepen.php';
require_once __DIR__ . '/labels.php';

function portaalModuleActief(string $module): bool{$config=privateStoreConfig();return (($config['modules'][$module]??false)===true);}
if(!portaalModuleActief('ledenadministratie')){if(!headers_sent()){http_response_code(404);header('X-Robots-Tag: noindex, nofollow');header('Cache-Control: no-store');}echo'Het ledenportaal is voor deze vereniging niet ingeschakeld.';exit;}
function portaalVergaderingenVoorLid(): array{if(!portaalModuleActief('vergaderingen'))return[];$lijst=[];foreach(vergaderingenVanSoort(repoVergaderingenLees(),'leden') as $v)if(vergaderingAgendaZichtbaarVoorLeden($v)||vergaderingNotulenZichtbaarVoorLeden($v))$lijst[]=$v;return$lijst;}
function portaalTakenVoorLid(string $lidId): array{if(!portaalModuleActief('taken'))return[];$lijst=[];foreach((array)(repoTakenLees()['taken']??[]) as $t)if(is_array($t)&&($t['toegewezen_aan']??'')===$lidId)$lijst[]=$t;return$lijst;}
function portaalOperationeleTakenVoorLid(string $lidId,bool $bestuurslid): array{if(!portaalModuleActief('operationele_taken'))return[];$lijst=[];foreach((array)(repoOperationeleTakenLees()['taken']??[]) as $t)if(is_array($t)&&($t['toegewezen_aan']??'')===$lidId&&(($t['zichtbaarheid']??'leden')==='leden'||$bestuurslid))$lijst[]=$t;return$lijst;}

// Bestuursleden mogen ook bestuursevenementen zien en publieke evenementen
// vóór de publieke inschrijfstart alvast bekijken. De daadwerkelijke
// inschrijfperiode blijft een aparte controle en wordt hiermee niet omzeild.
function portaalEvenementZichtbaarInContext(array $e,bool $bestuurslid): bool
{
    if(evenementZichtbaarVoorLeden($e))return true;
    if(!$bestuurslid)return false;
    $zichtbaarheid=(string)($e['zichtbaarheid']??'leden');
    return array_key_exists($zichtbaarheid,evenementZichtbaarheden());
}

function portaalEvenementenVoorLid(bool $bestuurslid): array
{
    if(!portaalModuleActief('evenementen'))return[];
    $lijst=[];
    foreach(evenementenGesorteerd(repoEvenementenLees()) as $e){
        if(is_array($e)&&portaalEvenementZichtbaarInContext($e,$bestuurslid))$lijst[]=$e;
    }
    return$lijst;
}

function portaalGroepenVoorLid(string $lidId): array{$groepen=groepenVoorLid(groepenLeesDocument(),$lidId);return array_values(array_filter($groepen,static function($g){if(($g['type']??'')==='werkgroep')return portaalModuleActief('werkgroepen');return true;}));}
function portaalLabelsVoorLid(string $lidId): array{return labelsVoorLid(labelsLeesDocument(),$lidId);}

// Mutaties vertrouwen niet op een lid-id uit de caller alleen. Het lid moet
// in de actuele tenant bestaan en mag niet gearchiveerd zijn. Dit is dezelfde
// basisbinding die het ledenportaal voor een ingelogd account hanteert.
function portaalActiefLidVoorDeelname(string $lidId): ?array
{
    $lidId=trim($lidId);
    if($lidId==='')return null;
    foreach((array)(repoLedenLees()['leden']??[]) as $lid){
        if(!is_array($lid)||!empty($lid['gearchiveerd_op']))continue;
        if(hash_equals((string)($lid['id']??''),$lidId))return$lid;
    }
    return null;
}

function portaalEvenementDeelnameMogelijkheden(array $e,array $lid): array
{
    $lidId=trim((string)($lid['id']??''));
    $toegankelijk=$lidId!==''&&portaalEvenementZichtbaarInContext($e,ledenIsBestuurslid($lid));
    return evenementDeelnameMogelijkheden($e,$lidId,$toegankelijk);
}

function portaalEvenementDeelnameWijzigen(string $evenementId,string $lidId,bool $aanmelden,?string &$fout=null): bool
{
    $fout='';
    if(!portaalModuleActief('evenementen')){$fout='De evenementenmodule is niet beschikbaar.';return false;}
    $evenementId=trim($evenementId);$lidId=trim($lidId);
    if($evenementId===''||$lidId===''){$fout='Onbekend evenement of lid.';return false;}

    $slot=dataSlotOpen();
    try{
        $lid=portaalActiefLidVoorDeelname($lidId);
        if($lid===null){$fout='Dit lid is niet beschikbaar.';return false;}

        $data=repoEvenementenLees();$idx=null;
        foreach((array)($data['evenementen']??[]) as $i=>$e){
            if(is_array($e)&&($e['id']??'')===$evenementId){$idx=$i;break;}
        }
        if($idx===null){$fout='Dit evenement bestaat niet meer.';return false;}

        $e=$data['evenementen'][$idx];
        $mogelijk=portaalEvenementDeelnameMogelijkheden($e,$lid);
        if(!$mogelijk['toegankelijk']){$fout='Dit evenement is niet voor jou beschikbaar.';return false;}
        if(!$mogelijk['aankomend']){$fout='Dit evenement is al afgelopen.';return false;}

        $al=$mogelijk['ingeschreven'];
        if($aanmelden&&$al)return true;
        if(!$aanmelden&&!$al)return true;

        if($aanmelden){
            if(!$mogelijk['inschrijfperiode_open']){$fout='De inschrijving voor dit evenement is gesloten.';return false;}
            if($mogelijk['vol']){$fout='Dit evenement zit vol.';return false;}
            $e['deelnemers'][]=$lidId;
        }else{
            $e['deelnemers']=array_values(array_filter((array)($e['deelnemers']??[]),static fn($id)=>$id!==$lidId));
        }
        $e['gewijzigd']=date('c');$data['evenementen'][$idx]=$e;
        if(!repoEvenementenSchrijf($data)){$fout='Opslaan mislukt. Probeer het nog eens.';return false;}
        return true;
    }finally{dataSlotDicht($slot);}
}
