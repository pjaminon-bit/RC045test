<?php
// ============================================================
// Beheer > Aanmeldingen
// ============================================================
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/app/auth-capabilities.php';
require_once dirname(__DIR__) . '/app/data-slot.php';
require_once dirname(__DIR__) . '/aanmeldingen-opslag.php';
require_once dirname(__DIR__) . '/app/leden/service.php';
require_once dirname(__DIR__) . '/app/leden/lidmaatschap.php';
if(!$ingelogd){header('Location: ./');exit;}
if(!authHeeftCapability('applications.manage')){http_response_code(403);echo'Geen toegang tot Aanmeldingen.';exit;}
function aaEsc($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function aaFlash(string $tekst,string $type='ok'):void{$_SESSION['aanmeldingen_flash']=['tekst'=>$tekst,'type'=>$type];}
function aaRedirect():void{header('Location: aanmeldingen.php');exit;}
function aaNaam(array $a):string{return trim(implode(' ',array_filter([(string)($a['voornaam']??''),(string)($a['tussenvoegsel']??''),(string)($a['achternaam']??'')])));}
$flash=$_SESSION['aanmeldingen_flash']??null;unset($_SESSION['aanmeldingen_flash']);
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    if(!csrfOk()){aaFlash('Sessie verlopen. Ververs de pagina.','fout');aaRedirect();}
    $actie=(string)($_POST['actie']??'');$id=trim((string)($_POST['id']??''));$slot=dataSlotOpen();
    try{
        $inbox=aanmeldingenLees();$idx=aanmeldingenVindIndex($inbox,$id);
        if($idx===null){aaFlash('Aanmelding niet gevonden.','fout');}
        else{
            $a=$inbox['aanmeldingen'][$idx];$status=(string)($a['status']??'nieuw');
            if($actie==='accepteren'){
                if($status!=='nieuw'){aaFlash('Alleen een nieuwe aanmelding kan worden geaccepteerd.','fout');}
                else{
                    $leden=ledenServiceLees();$bestaand=null;
                    foreach((array)($leden['leden']??[]) as $lidIndex=>$lid){if(is_array($lid)&&($lid['aanmelding_id']??'')===$id){$bestaand=$lidIndex;break;}}
                    if($bestaand===null){
                        $lid=ledenNormaliseer(['voornaam'=>$a['voornaam']??'','tussenvoegsel'=>$a['tussenvoegsel']??'','achternaam'=>$a['achternaam']??'','geboortedatum'=>$a['geboortedatum']??'','straat'=>$a['straat']??'','huisnummer'=>$a['huisnummer']??'','postcode'=>$a['postcode']??'','gemeente'=>$a['gemeente']??'','land'=>$a['land']??'','telefoon'=>$a['telefoon']??'','email'=>$a['email']??'','status'=>'verificatie','inschrijfdatum'=>date('Y-m-d')]);
                        $lid['nummer']=ledenVolgendNummer($leden);$lid['bron']='aanmelding';$lid['aanmelding_id']=$id;$lid['lidmaatschap_type']=(string)($a['lidmaatschap_type']??'');$type=lidmaatschapTypeOpId($lid['lidmaatschap_type']);
                        $lid=ledenZetContributie($lid,(int)date('Y'),['status'=>'open','bedrag'=>$a['berekend_bedrag']??'','inschrijfgeld'=>$type['inschrijfgeld']??'','betaald_op'=>'','opmerking'=>'Aangemaakt bij acceptatie van online aanmelding.']);
                        $leden['leden'][]=$lid;$leden['volgnummer']=max((int)($leden['volgnummer']??0),(int)$lid['nummer']);
                        if(!ledenServiceSchrijf($leden)){aaFlash('Lid kon niet worden opgeslagen. Aanmelding is niet gewijzigd.','fout');$lid=null;}
                    }else{$lid=$leden['leden'][$bestaand];}
                    if(isset($lid)&&is_array($lid)){
                        $inbox['aanmeldingen'][$idx]['status']='geaccepteerd';$inbox['aanmeldingen'][$idx]['beoordeeld_op']=date('c');$inbox['aanmeldingen'][$idx]['beoordeeld_door']=$huidigeGebruiker;$inbox['aanmeldingen'][$idx]['lid_id']=$lid['id']??'';$inbox['aanmeldingen'][$idx]['gewijzigd']=date('c');
                        if(aanmeldingenSchrijf($inbox)){schrijfLog($logBestand,$huidigeGebruiker,'aanmelding_geaccepteerd',$id.' · '.aaNaam($a));aaFlash('Aanmelding geaccepteerd. Het lid staat nu in de ledenadministratie met status “In verificatie”.');}else aaFlash('Lid is aangemaakt, maar de inboxstatus kon niet worden bijgewerkt. Opnieuw accepteren herkent hetzelfde lid en maakt geen dubbel record.','fout');
                    }
                }
            }elseif($actie==='afwijzen'){
                if($status!=='nieuw')aaFlash('Alleen een nieuwe aanmelding kan worden afgewezen.','fout');else{$inbox['aanmeldingen'][$idx]['status']='afgewezen';$inbox['aanmeldingen'][$idx]['beoordeeld_op']=date('c');$inbox['aanmeldingen'][$idx]['beoordeeld_door']=$huidigeGebruiker;$inbox['aanmeldingen'][$idx]['opmerking']=trim(substr((string)($_POST['opmerking']??''),0,500));$inbox['aanmeldingen'][$idx]['gewijzigd']=date('c');if(aanmeldingenSchrijf($inbox)){schrijfLog($logBestand,$huidigeGebruiker,'aanmelding_afgewezen',$id.' · '.aaNaam($a));aaFlash('Aanmelding afgewezen.');}else aaFlash('Opslaan mislukt.','fout');}
            }elseif($actie==='verwijderen'){
                if($status==='nieuw')aaFlash('Beoordeel de aanmelding eerst; een open aanvraag wordt niet stil verwijderd.','fout');else{array_splice($inbox['aanmeldingen'],$idx,1);if(aanmeldingenSchrijf($inbox)){schrijfLog($logBestand,$huidigeGebruiker,'aanmelding_verwijderd',$id);aaFlash('Afgehandelde aanmelding uit de inbox verwijderd.');}else aaFlash('Verwijderen mislukt.','fout');}
            }else aaFlash('Onbekende actie.','fout');
        }
    }finally{dataSlotDicht($slot);}aaRedirect();
}
$data=aanmeldingenLees();$filter=(string)($_GET['status']??'open');$lijst=$data['aanmeldingen'];usort($lijst,static fn($a,$b)=>strcmp((string)($b['aangemaakt']??''),(string)($a['aangemaakt']??'')));if($filter==='open')$lijst=array_values(array_filter($lijst,static fn($a)=>($a['status']??'nieuw')==='nieuw'));elseif(in_array($filter,['geaccepteerd','afgewezen'],true))$lijst=array_values(array_filter($lijst,static fn($a)=>($a['status']??'')===$filter));
?><!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Aanmeldingen</title><style>body{margin:0;background:#f6f2e8;color:#26351d;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.top{background:#fff;border-bottom:1px solid #ddd8c0;padding:15px 22px}.top a{color:#2d6260;font-weight:750;text-decoration:none}.wrap{max-width:1100px;margin:30px auto;padding:0 20px 70px}.filters{display:flex;gap:8px;flex-wrap:wrap}.filters a,.btn{display:inline-block;padding:9px 12px;border-radius:8px;text-decoration:none;border:1px solid #d5cfba;background:#fff;color:#2d6260;font-weight:700;cursor:pointer}.kaart{background:#fff;border:1px solid #ddd8c0;border-radius:14px;padding:20px;margin:15px 0}.meta{color:#68705f;font-size:13px}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.btn.ok{background:#3a7a77;color:#fff;border:0}.btn.gevaar{background:#fff0ed;color:#8b2e27}.flash{padding:12px;border-radius:9px;margin:15px 0;background:#eaf6ee}.flash.fout{background:#fdeceb;color:#8b2e27}textarea{width:100%;box-sizing:border-box;min-height:70px}@media(max-width:700px){.grid{grid-template-columns:1fr}}</style></head><body><div class="top"><a href="./">← Beheer</a></div><main class="wrap"><h1>Aanmeldingen</h1><p>Nieuwe aanvragen worden hier beoordeeld voordat ze lid worden.</p><?php if($flash):?><div class="flash <?=aaEsc($flash['type']??'')?>"><?=aaEsc($flash['tekst']??'')?></div><?php endif;?><nav class="filters"><a href="?status=open">Open</a><a href="?status=geaccepteerd">Geaccepteerd</a><a href="?status=afgewezen">Afgewezen</a><a href="?status=alles">Alles</a></nav><?php if(!$lijst):?><p>Geen aanmeldingen in deze selectie.</p><?php endif;?><?php foreach($lijst as $a):?><section class="kaart"><h2><?=aaEsc(aaNaam($a))?></h2><p class="meta"><?=aaEsc($a['status']??'nieuw')?> · <?=aaEsc($a['aangemaakt']??'')?> · id <?=aaEsc($a['id']??'')?></p><div class="grid"><div><strong>Contact</strong><br><?=aaEsc($a['email']??'')?> <br><?=aaEsc($a['telefoon']??'')?></div><div><strong>Adres</strong><br><?=aaEsc(($a['straat']??'').' '.($a['huisnummer']??''))?><br><?=aaEsc(($a['postcode']??'').' '.($a['gemeente']??''))?></div><div><strong>Lidmaatschap</strong><br><?=aaEsc($a['lidmaatschap_type']??'')?> · berekend €<?=aaEsc(number_format((float)($a['berekend_bedrag']??0),2,',','.'))?><br>Geboren <?=aaEsc($a['geboortedatum']??'')?></div></div><?php if(($a['status']??'nieuw')==='nieuw'):?><div class="filters" style="margin-top:15px"><form method="post"><input type="hidden" name="csrf" value="<?=aaEsc($csrfToken)?>"><input type="hidden" name="actie" value="accepteren"><input type="hidden" name="id" value="<?=aaEsc($a['id']??'')?>"><button class="btn ok" type="submit">Accepteren → lid</button></form><form method="post"><input type="hidden" name="csrf" value="<?=aaEsc($csrfToken)?>"><input type="hidden" name="actie" value="afwijzen"><input type="hidden" name="id" value="<?=aaEsc($a['id']??'')?>"><textarea name="opmerking" maxlength="500" placeholder="Reden/notitie (optioneel)"></textarea><button class="btn gevaar" type="submit">Afwijzen</button></form></div><?php else:?><p class="meta">Beoordeeld door <?=aaEsc($a['beoordeeld_door']??'')?> op <?=aaEsc($a['beoordeeld_op']??'')?><?=($a['lid_id']??'')!==''?' · lid-id '.aaEsc($a['lid_id']):''?></p><form method="post" onsubmit="return confirm('Afgehandelde aanvraag uit de inbox verwijderen?');"><input type="hidden" name="csrf" value="<?=aaEsc($csrfToken)?>"><input type="hidden" name="actie" value="verwijderen"><input type="hidden" name="id" value="<?=aaEsc($a['id']??'')?>"><button class="btn gevaar" type="submit">Inboxrecord verwijderen</button></form><?php endif;?></section><?php endforeach;?></main></body></html>
