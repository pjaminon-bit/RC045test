<?php
// ============================================================
// Contactberichten-inbox
// ============================================================
// Publieke contactvragen worden per tenant privé opgeslagen. Externe tenants
// gebruiken de bestaande private store (PDO of private_root). De standalone
// compatibiliteit gebruikt een afgeschermd PHP+JSON-bestand.
// ============================================================
require_once __DIR__ . '/app/data-slot.php';
require_once __DIR__ . '/app/storage/private-store.php';
require_once __DIR__ . '/app/storage/legacy-private-json.php';

define('CONTACTBERICHTEN_VOORLOOP', "<?php exit; ?>\n");

function contactBerichtenBestandPad(): string{return __DIR__.'/contactberichten-data.php';}
function contactBerichtenLeeg(): array{return ['updated'=>date('c'),'berichten'=>[]];}
function contactBerichtNieuwId(): string{return 'msg_'.bin2hex(random_bytes(10));}
function contactBerichtKort($waarde,int $max): string{$tekst=trim(is_scalar($waarde)?(string)$waarde:'');return function_exists('mb_substr')?mb_substr($tekst,0,$max,'UTF-8'):substr($tekst,0,$max);}

function contactBerichtenBewaardagen(): int
{
    $config=require __DIR__.'/site-config.php';
    $dagen=(int)($config['privacy']['contactberichten_bewaardagen']??180);
    return max(30,min(730,$dagen));
}

function contactBerichtNormaliseer(array $invoer): array
{
    $nu=date('c');
    return [
        'id'=>contactBerichtNieuwId(),
        'status'=>'nieuw',
        'naam'=>contactBerichtKort($invoer['naam']??'',100),
        'email'=>contactBerichtKort($invoer['email']??'',120),
        'telefoon'=>contactBerichtKort($invoer['telefoon']??'',50),
        'onderwerp'=>contactBerichtKort($invoer['onderwerp']??'',120),
        'bericht'=>contactBerichtKort($invoer['bericht']??'',5000),
        'aangemaakt'=>$nu,
        'gewijzigd'=>$nu,
        'afgehandeld_op'=>'',
        'afgehandeld_door'=>'',
        'notitie'=>'',
    ];
}

function contactBerichtenJsonLees(): array
{
    $data=legacyPrivateJsonLees(contactBerichtenBestandPad(),'contactberichten',['berichten']);
    return $data===null?contactBerichtenLeeg():$data;
}

function contactBerichtenJsonSchrijf(array $data): bool
{
    $data['updated']=date('c');
    $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    if($json===false)return false;
    $pad=contactBerichtenBestandPad();
    try{$suffix=bin2hex(random_bytes(5));}catch(Throwable $e){return false;}
    $tmp=$pad.'.tmp.'.$suffix;
    if(@file_put_contents($tmp,CONTACTBERICHTEN_VOORLOOP.$json,LOCK_EX)===false)return false;
    @chmod($tmp,0640);
    if(!@rename($tmp,$pad)){@unlink($tmp);return false;}
    @chmod($pad,0640);
    return true;
}

function contactBerichtenLees(): array
{
    $data=privateStoreLees('contactberichten','contactBerichtenJsonLees');
    if($data===[])return contactBerichtenLeeg();
    if(!isset($data['berichten'])||!is_array($data['berichten']))throw new RuntimeException('Contactberichtenopslag heeft een ongeldige documentstructuur.');
    return $data;
}
function contactBerichtenSchrijf(array $data): bool{return privateStoreSchrijf('contactberichten',$data,'contactBerichtenJsonSchrijf');}
function contactBerichtenVindIndex(array $data,string $id): ?int{foreach((array)($data['berichten']??[]) as $i=>$b)if(is_array($b)&&hash_equals((string)($b['id']??''),$id))return$i;return null;}

function contactBerichtenPasRetentieToe(array &$data,?int $nu=null): int
{
    $nu=$nu??time();$grens=$nu-contactBerichtenBewaardagen()*86400;$voor=count((array)($data['berichten']??[]));
    $data['berichten']=array_values(array_filter((array)($data['berichten']??[]),static function($b)use($grens){
        if(!is_array($b))return false;
        $status=(string)($b['status']??'nieuw');
        $bron=$status==='nieuw'?($b['aangemaakt']??''):($b['afgehandeld_op']??$b['gewijzigd']??$b['aangemaakt']??'');
        $moment=strtotime((string)$bron);
        return $moment!==false&&$moment>=$grens;
    }));
    return$voor-count($data['berichten']);
}

function contactBerichtenOpschonenBewaartermijn(): int
{
    $slot=dataSlotOpen();
    try{
        $data=contactBerichtenLees();
        $aantal=contactBerichtenPasRetentieToe($data);
        if($aantal>0&&!contactBerichtenSchrijf($data))throw new RuntimeException('Contactberichten konden niet veilig worden opgeschoond.');
        return$aantal;
    }finally{
        dataSlotDicht($slot);
    }
}