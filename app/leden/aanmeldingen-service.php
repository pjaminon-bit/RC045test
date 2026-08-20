<?php
// ============================================================
// Aanmeldingenservice
// ============================================================
require_once dirname(__DIR__) . '/data-slot.php';
require_once dirname(__DIR__) . '/storage/private-store.php';
require_once __DIR__ . '/service.php';
require_once __DIR__ . '/lidmaatschap.php';
require_once __DIR__ . '/contributies.php';
require_once dirname(__DIR__,2) . '/aanmeldingen-opslag.php';
function aanmeldingServiceJaar(array $a): int{$jaar=(int)($a['contributie_jaar']??date('Y'));return$jaar>=2000&&$jaar<=2099?$jaar:(int)date('Y');}
function aanmeldingServiceMaand(array $a): int{$maand=(int)($a['contributie_maand']??date('n'));return$maand>=1&&$maand<=12?$maand:(int)date('n');}
function aanmeldingServiceLeeftijd(array $a): ?int{$geb=ledenParseDatum($a['geboortedatum']??'');return$geb===''?null:ledenLeeftijd($geb,aanmeldingServiceJaar($a).'-01-01');}
function aanmeldingServiceToegestaneTypes(array $a): array{return lidmaatschapTypesVoorLeeftijd(aanmeldingServiceLeeftijd($a));}
function aanmeldingServiceTypeGeldig(array $a,string $id): ?array{$type=lidmaatschapTypeOpId($id);return$type&&lidmaatschapTypeToegestaanVoorLeeftijd($type,aanmeldingServiceLeeftijd($a))?$type:null;}
function aanmeldingServiceBedragen(array $a,array $type): array{$zelfde=(string)($a['lidmaatschap_type']??'')===(string)($type['id']??'');$bedrag=$zelfde&&is_numeric($a['berekend_bedrag']??null)?(float)$a['berekend_bedrag']:lidmaatschapBedragVoorMaand($type,aanmeldingServiceMaand($a));$inschrijf=$zelfde&&is_numeric($a['berekend_inschrijfgeld']??null)?(float)$a['berekend_inschrijfgeld']:(float)($type['inschrijfgeld']??0);return[round(max(0,$bedrag),2),round(max(0,$inschrijf),2)];}
function aanmeldingServiceAccepteer(string $aanmeldingId,string $typeId,string $door): array
{
    $aanmeldingId=trim($aanmeldingId);$typeId=trim($typeId);$door=trim($door);if($aanmeldingId===''||$typeId==='')throw new InvalidArgumentException('Aanmelding en lidmaatschapstype zijn verplicht.');
    $slot=dataSlotOpen();
    try{return privateStoreTransactie(function()use($aanmeldingId,$typeId,$door){
        $inbox=aanmeldingenLees();$idx=aanmeldingenVindIndex($inbox,$aanmeldingId);if($idx===null)throw new RuntimeException('Aanmelding niet gevonden.');$a=$inbox['aanmeldingen'][$idx];if(($a['status']??'nieuw')!=='nieuw')throw new RuntimeException('Alleen een nieuwe aanmelding kan worden geaccepteerd.');
        $type=aanmeldingServiceTypeGeldig($a,$typeId);if(!$type)throw new RuntimeException('Het gekozen lidmaatschapstype is niet geldig voor deze aanmelding.');[$bedrag,$inschrijfgeld]=aanmeldingServiceBedragen($a,$type);$jaar=aanmeldingServiceJaar($a);
        $leden=ledenServiceLees();$lidIndex=null;foreach((array)($leden['leden']??[]) as $i=>$record)if(is_array($record)&&($record['aanmelding_id']??'')===$aanmeldingId){$lidIndex=$i;break;}
        if($lidIndex===null){$lid=ledenNormaliseer(['voornaam'=>$a['voornaam']??'','tussenvoegsel'=>$a['tussenvoegsel']??'','achternaam'=>$a['achternaam']??'','geboortedatum'=>$a['geboortedatum']??'','straat'=>$a['straat']??'','huisnummer'=>$a['huisnummer']??'','postcode'=>$a['postcode']??'','gemeente'=>$a['gemeente']??'','land'=>$a['land']??'','telefoon'=>$a['telefoon']??'','email'=>$a['email']??'','status'=>'verificatie','inschrijfdatum'=>date('Y-m-d')]);$lid['nummer']=ledenVolgendNummer($leden);$lid['bron']='aanmelding';$lid['aanmelding_id']=$aanmeldingId;$lid['lidmaatschap_type']=$type['id'];$leden['leden'][]=$lid;$leden['volgnummer']=max((int)($leden['volgnummer']??0),(int)$lid['nummer']);}
        else{$lid=$leden['leden'][$lidIndex];$lid['lidmaatschap_type']=$type['id'];$lid['gewijzigd']=date('c');$leden['leden'][$lidIndex]=$lid;}
        if(!ledenServiceSchrijf($leden))throw new RuntimeException('Lid kon niet worden opgeslagen.');

        $fin=contributiesLees();$finIndex=contributieVindIndex($fin,(string)$lid['id'],$jaar);$bestaandeFinance=$finIndex===null?null:$fin['regels'][$finIndex];
        if(is_array($bestaandeFinance)){
            $betaald=(float)($bestaandeFinance['betaald_bedrag']??0);$status=(string)($bestaandeFinance['status']??'open');
            if($betaald>0||$status!=='open'){
                if(($bestaandeFinance['lidmaatschap_type']??'')!==$type['id'])throw new RuntimeException('De bestaande financieel gewijzigde contributieregel heeft een ander lidmaatschapstype. Controleer Contributie-administratie eerst.');
                $bedrag=(float)($bestaandeFinance['verschuldigd_bedrag']??$bedrag);$inschrijfgeld=(float)($bestaandeFinance['inschrijfgeld']??$inschrijfgeld);
            }else{$bestaandeFinance=null;}
        }
        if($bestaandeFinance===null){contributieUpsert($fin,['lid_id'=>$lid['id'],'jaar'=>$jaar,'lidmaatschap_type'=>$type['id'],'status'=>'open','verschuldigd_bedrag'=>$bedrag,'inschrijfgeld'=>$inschrijfgeld,'betaald_bedrag'=>0,'betaald_op'=>'','vrijstelling_reden'=>'','opmerking'=>'Online aanmelding '.$aanmeldingId.'; aanvraagmaand '.aanmeldingServiceMaand($a).'.']);if(!contributiesSchrijf($fin))throw new RuntimeException('Contributieregel kon niet worden opgeslagen.');}
        $inbox['aanmeldingen'][$idx]['status']='geaccepteerd';$inbox['aanmeldingen'][$idx]['lidmaatschap_type']=$type['id'];$inbox['aanmeldingen'][$idx]['berekend_bedrag']=$bedrag;$inbox['aanmeldingen'][$idx]['berekend_inschrijfgeld']=$inschrijfgeld;$inbox['aanmeldingen'][$idx]['beoordeeld_op']=date('c');$inbox['aanmeldingen'][$idx]['beoordeeld_door']=$door;$inbox['aanmeldingen'][$idx]['lid_id']=$lid['id']??'';$inbox['aanmeldingen'][$idx]['gewijzigd']=date('c');if(!aanmeldingenSchrijf($inbox))throw new RuntimeException('Inboxstatus kon niet worden opgeslagen.');
        return['aanmelding'=>$inbox['aanmeldingen'][$idx],'lid'=>$lid,'contributie_jaar'=>$jaar,'type'=>$type];
    });}finally{dataSlotDicht($slot);}
}
