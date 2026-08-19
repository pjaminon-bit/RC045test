<?php
require_once dirname(__DIR__) . '/storage/domein-repositories.php';
require_once dirname(__DIR__,2) . '/app/data-slot.php';

function portaalModuleActief(string $module): bool
{
    $config = privateStoreConfig();
    return (($config['modules'][$module] ?? false) === true);
}

// Dit bestand wordt uitsluitend door /leden/ geladen. Een uitgeschakelde
// ledenadministratie betekent daarom ook: geen persoonlijk ledenportaal.
if (!portaalModuleActief('ledenadministratie')) {
    if (!headers_sent()) {
        http_response_code(404);
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');
    }
    echo 'Het ledenportaal is voor deze vereniging niet ingeschakeld.';
    exit;
}

function portaalVergaderingenVoorLid(): array
{
    if (!portaalModuleActief('vergaderingen')) return [];
    $lijst=[];
    foreach(vergaderingenVanSoort(repoVergaderingenLees(),'leden') as $v){
        if(vergaderingAgendaZichtbaarVoorLeden($v)||vergaderingNotulenZichtbaarVoorLeden($v))$lijst[]=$v;
    }
    return $lijst;
}
function portaalTakenVoorLid(string $lidId): array
{
    if (!portaalModuleActief('taken')) return [];
    $lijst=[];foreach((array)(repoTakenLees()['taken']??[]) as $t)if(is_array($t)&&($t['toegewezen_aan']??'')===$lidId)$lijst[]=$t;return$lijst;
}
function portaalOperationeleTakenVoorLid(string $lidId,bool $bestuurslid): array
{
    if (!portaalModuleActief('operationele_taken')) return [];
    $lijst=[];foreach((array)(repoOperationeleTakenLees()['taken']??[]) as $t)if(is_array($t)&&($t['toegewezen_aan']??'')===$lidId&&(($t['zichtbaarheid']??'leden')==='leden'||$bestuurslid))$lijst[]=$t;return$lijst;
}
function portaalEvenementenVoorLid(bool $bestuurslid): array
{
    if (!portaalModuleActief('evenementen')) return [];
    $lijst=[];foreach(evenementenGesorteerd(repoEvenementenLees()) as $e)if(evenementZichtbaarVoorLeden($e)||$bestuurslid)$lijst[]=$e;return$lijst;
}
function portaalEvenementDeelnameWijzigen(string $evenementId,string $lidId,bool $aanmelden,?string &$fout=null): bool
{
    $fout='';
    if (!portaalModuleActief('evenementen')) { $fout='De evenementenmodule is niet beschikbaar.'; return false; }
    $evenementId=trim($evenementId);$lidId=trim($lidId);
    if($evenementId===''||$lidId===''){$fout='Onbekend evenement of lid.';return false;}
    $slot=dataSlotOpen();
    try{
        $data=repoEvenementenLees();$idx=null;foreach((array)($data['evenementen']??[]) as $i=>$e)if(is_array($e)&&($e['id']??'')===$evenementId){$idx=$i;break;}
        if($idx===null){$fout='Dit evenement bestaat niet meer.';return false;}
        $e=$data['evenementen'][$idx];
        if(!evenementZichtbaarVoorLeden($e)){$fout='Dit evenement is niet voor jou beschikbaar.';return false;}
        if(!evenementInschrijvingOpen($e)){$fout='De inschrijving voor dit evenement is gesloten.';return false;}
        $al=evenementHeeftDeelnemer($e,$lidId);if($aanmelden&&$al)return true;if(!$aanmelden&&!$al)return true;
        if($aanmelden){if(evenementIsVol($e)){$fout='Dit evenement zit vol.';return false;}$e['deelnemers'][]=$lidId;}
        else $e['deelnemers']=array_values(array_filter((array)($e['deelnemers']??[]),static fn($id)=>$id!==$lidId));
        $e['gewijzigd']=date('c');$data['evenementen'][$idx]=$e;
        if(!repoEvenementenSchrijf($data)){$fout='Opslaan mislukt. Probeer het nog eens.';return false;}
        return true;
    }finally{dataSlotDicht($slot);}
}
