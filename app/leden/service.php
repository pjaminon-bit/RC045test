<?php
// ============================================================
// Ledenservice fase 2.5
// ============================================================
// Nieuwe beheer- en portaalroutes gebruiken deze service in plaats van zelf
// rechtstreeks leden-data.php te muteren. De bestaande opslagfuncties blijven
// als compatibility backend beschikbaar.
// ============================================================
require_once dirname(__DIR__, 2) . '/leden-opslag.php';
require_once dirname(__DIR__) . '/auth-capabilities.php';
require_once __DIR__ . '/lidmaatschap.php';

function ledenServiceLees(): array { return ledenLees(); }
function ledenServiceSchrijf(array $data,bool $backup=true): bool { return ledenSchrijf($data,$backup); }

function ledenServiceUserId(array $lid): string
{
    $id=trim((string)($lid['user_id']??''));
    if($id!=='')return $id;
    $naam=trim((string)($lid['beheer_account']??''));
    if($naam==='')return '';
    $record=authGebruikerRecordOpNaam($naam);
    return is_array($record)?authGebruikerId($record):'';
}

function ledenServiceVindVoorAccount(string $userId,string $gebruikersnaam=''): ?array
{
    $userId=trim($userId);$gebruikersnaam=trim($gebruikersnaam);
    foreach(ledenServiceLees()['leden'] as $lid){
        if(!is_array($lid)||!empty($lid['gearchiveerd_op']))continue;
        $lidUser=trim((string)($lid['user_id']??''));
        if($userId!==''&&$lidUser!==''&&hash_equals($lidUser,$userId))return $lid;
        if($gebruikersnaam!==''&&strcasecmp(trim((string)($lid['beheer_account']??'')),$gebruikersnaam)===0)return $lid;
    }
    return null;
}

function ledenServiceMigreerUserLinks(array &$data): int
{
    $aantal=0;
    foreach($data['leden'] as $i=>$lid){
        if(!is_array($lid)||trim((string)($lid['user_id']??''))!=='')continue;
        $naam=trim((string)($lid['beheer_account']??''));if($naam==='')continue;
        $record=authGebruikerRecordOpNaam($naam);if(!is_array($record))continue;
        $id=authGebruikerId($record);if($id==='')continue;
        $data['leden'][$i]['user_id']=$id;$data['leden'][$i]['gewijzigd']=date('c');$aantal++;
    }
    return $aantal;
}

function ledenServiceKoppelAccount(array &$lid,string $userId,string $gebruikersnaam=''): void
{
    $lid['user_id']=trim($userId);
    // Tijdelijke compatibiliteit zolang auth.php de oude username-koppeling
    // nog ondersteunt. Nieuwe logica gebruikt user_id als primaire sleutel.
    $lid['beheer_account']=trim($gebruikersnaam);
    $lid['gewijzigd']=date('c');
}

function ledenServiceArchiveer(array &$data,string $lidId,string $door): ?array
{
    foreach($data['leden'] as $i=>$lid){
        if(!is_array($lid)||($lid['id']??'')!==$lidId)continue;
        if(!empty($lid['gearchiveerd_op']))return $lid;
        $data['leden'][$i]['gearchiveerd_op']=date('c');
        $data['leden'][$i]['gearchiveerd_door']=$door;
        $data['leden'][$i]['status']='opgezegd';
        $data['leden'][$i]['user_id']='';
        $data['leden'][$i]['beheer_account']='';
        $data['leden'][$i]['gewijzigd']=date('c');
        return $data['leden'][$i];
    }
    return null;
}

function ledenServiceHerstelArchief(array &$data,string $lidId): ?array
{
    foreach($data['leden'] as $i=>$lid){
        if(!is_array($lid)||($lid['id']??'')!==$lidId)continue;
        $data['leden'][$i]['gearchiveerd_op']='';$data['leden'][$i]['gearchiveerd_door']='';$data['leden'][$i]['gewijzigd']=date('c');
        return $data['leden'][$i];
    }
    return null;
}

function ledenServiceIsLijst(array $array): bool
{
    return function_exists('array_is_list')?array_is_list($array):(array_keys($array)===range(0,count($array)-1));
}

function ledenServicePurgeId(&$waarde,string $id): void
{
    if(!is_array($waarde))return;
    if(ledenServiceIsLijst($waarde)){
        $nieuw=[];
        foreach($waarde as $item){
            if(is_string($item)&&hash_equals($item,$id))continue;
            if(is_array($item))ledenServicePurgeId($item,$id);
            $nieuw[]=$item;
        }
        $waarde=$nieuw;return;
    }
    foreach($waarde as $k=>&$item){
        if(is_string($item)&&hash_equals($item,$id)){$item='';continue;}
        if(is_array($item))ledenServicePurgeId($item,$id);
    }
    unset($item);
}

function ledenServiceVerwijderRelaties(string $lidId): bool
{
    require_once dirname(__DIR__,2).'/vergaderingen-opslag.php';
    require_once dirname(__DIR__,2).'/taken-opslag.php';
    require_once dirname(__DIR__,2).'/operationele-taken-opslag.php';
    require_once dirname(__DIR__,2).'/evenementen-opslag.php';
    $bronnen=[
        ['vergaderingenLees','vergaderingenSchrijf'],
        ['takenLees','takenSchrijf'],
        ['otakenLees','otakenSchrijf'],
        ['evenementenLees','evenementenSchrijf'],
    ];
    foreach($bronnen as [$lezer,$schrijver]){
        if(!function_exists($lezer)||!function_exists($schrijver))continue;
        $data=$lezer();if(!is_array($data))continue;
        $voor=json_encode($data);ledenServicePurgeId($data,$lidId);$na=json_encode($data);
        if($voor!==$na&&!$schrijver($data))return false;
    }
    return true;
}

function ledenServiceDefinitiefVerwijder(array &$data,string $lidId): ?array
{
    $gevonden=null;$over=[];
    foreach($data['leden'] as $lid){if(is_array($lid)&&($lid['id']??'')===$lidId){$gevonden=$lid;continue;}$over[]=$lid;}
    if($gevonden===null)return null;
    if(!ledenServiceVerwijderRelaties($lidId))return null;
    $data['leden']=$over;
    return $gevonden;
}

function ledenServiceType(array $lid): ?array
{
    $id=trim((string)($lid['lidmaatschap_type']??''));
    if($id!==''){$type=lidmaatschapTypeOpId($id);if($type)return $type;}
    $jaar=(int)date('Y');$leeftijd=ledenLeeftijd((string)($lid['geboortedatum']??''),$jaar.'-01-01');
    return lidmaatschapTypeVoorLeeftijd($leeftijd);
}
