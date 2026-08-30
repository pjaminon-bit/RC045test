<?php
// ============================================================
// Beheer > Contactberichten
// ============================================================
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/app/auth-capabilities.php';
require_once dirname(__DIR__) . '/app/data-slot.php';
require_once dirname(__DIR__) . '/contactberichten-opslag.php';
if(!$ingelogd){header('Location: ./');exit;}
if(!authHeeftCapability('contact.messages.manage', true)){http_response_code(403);echo'Geen toegang tot Contactberichten.';exit;}

function cbEsc($v): string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function cbFlash(string $tekst,string $type='ok'): void{$_SESSION['contactberichten_flash']=['tekst'=>$tekst,'type'=>$type];}
function cbRedirect(): void{header('Location: contactberichten.php');exit;}

if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
    try{
        $opgeruimd=contactBerichtenOpschonenBewaartermijn();
        if($opgeruimd>0)schrijfLog($logBestand,$huidigeGebruiker,'contactberichten_retentie',(string)$opgeruimd.' contactbericht(en) verwijderd');
    }catch(Throwable $e){
        error_log('[beheer/contactberichten] retentie: '.$e->getMessage());
    }
}

$flash=$_SESSION['contactberichten_flash']??null;unset($_SESSION['contactberichten_flash']);
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    if(!csrfOk()){cbFlash('Sessie verlopen. Ververs de pagina.','fout');cbRedirect();}
    $actie=(string)($_POST['actie']??'');$id=trim((string)($_POST['id']??''));
    $slot=dataSlotOpen();
    try{
        $inbox=contactBerichtenLees();$idx=contactBerichtenVindIndex($inbox,$id);
        if($idx===null){cbFlash('Contactbericht niet gevonden.','fout');}
        else{
            $status=(string)($inbox['berichten'][$idx]['status']??'nieuw');$nu=date('c');
            if($actie==='afhandelen'){
                if($status!=='nieuw')cbFlash('Dit bericht is al afgehandeld.','fout');
                else{
                    $inbox['berichten'][$idx]['status']='afgehandeld';
                    $inbox['berichten'][$idx]['afgehandeld_op']=$nu;
                    $inbox['berichten'][$idx]['afgehandeld_door']=$huidigeGebruiker;
                    $inbox['berichten'][$idx]['notitie']=contactBerichtKort($_POST['notitie']??'',500);
                    $inbox['berichten'][$idx]['gewijzigd']=$nu;
                    if(contactBerichtenSchrijf($inbox)){schrijfLog($logBestand,$huidigeGebruiker,'contactbericht_afgehandeld',$id);cbFlash('Contactbericht als afgehandeld gemarkeerd.');}
                    else cbFlash('Opslaan mislukt.','fout');
                }
            }elseif($actie==='heropenen'){
                if($status!=='afgehandeld')cbFlash('Alleen een afgehandeld bericht kan worden heropend.','fout');
                else{
                    $inbox['berichten'][$idx]['status']='nieuw';
                    $inbox['berichten'][$idx]['afgehandeld_op']='';
                    $inbox['berichten'][$idx]['afgehandeld_door']='';
                    $inbox['berichten'][$idx]['gewijzigd']=$nu;
                    if(contactBerichtenSchrijf($inbox)){schrijfLog($logBestand,$huidigeGebruiker,'contactbericht_heropend',$id);cbFlash('Contactbericht heropend.');}
                    else cbFlash('Opslaan mislukt.','fout');
                }
            }elseif($actie==='verwijderen'){
                if($status==='nieuw')cbFlash('Handel het bericht eerst af; een open bericht wordt niet handmatig verwijderd.','fout');
                else{
                    array_splice($inbox['berichten'],$idx,1);
                    if(contactBerichtenSchrijf($inbox)){schrijfLog($logBestand,$huidigeGebruiker,'contactbericht_verwijderd',$id);cbFlash('Contactbericht verwijderd.');}
                    else cbFlash('Verwijderen mislukt.','fout');
                }
            }else cbFlash('Onbekende actie.','fout');
        }
    }finally{dataSlotDicht($slot);}
    cbRedirect();
}

$data=contactBerichtenLees();$filter=(string)($_GET['status']??'open');$lijst=(array)($data['berichten']??[]);
usort($lijst,static fn($a,$b)=>strcmp((string)($b['aangemaakt']??''),(string)($a['aangemaakt']??'')));
if($filter==='open')$lijst=array_values(array_filter($lijst,static fn($b)=>($b['status']??'nieuw')==='nieuw'));
elseif($filter==='afgehandeld')$lijst=array_values(array_filter($lijst,static fn($b)=>($b['status']??'')==='afgehandeld'));
?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Contactberichten</title><style>
body{margin:0;background:#f6f2e8;color:#26351d;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.top{padding:14px 22px;background:#fff;border-bottom:1px solid #ddd8c0}.top a{color:#2d6260;font-weight:750;text-decoration:none}.wrap{max-width:1050px;margin:30px auto;padding:0 20px 70px}.filters{display:flex;gap:8px;flex-wrap:wrap}.filters a,.btn{display:inline-block;padding:9px 12px;border-radius:8px;text-decoration:none;border:1px solid #d5cfba;background:#fff;color:#2d6260;font-weight:700;cursor:pointer}.kaart{background:#fff;border:1px solid #ddd8c0;border-radius:14px;padding:20px;margin:15px 0}.meta{color:#68705f;font-size:13px}.grid{display:grid;grid-template-columns:1fr 2fr;gap:18px}.bericht{white-space:pre-wrap;line-height:1.55}.btn.ok{background:#3a7a77;color:#fff;border:0}.btn.gevaar{background:#fff0ed;color:#8b2e27}.flash{padding:12px;border-radius:9px;margin:15px 0;background:#eaf6ee}.flash.fout{background:#fdeceb;color:#8b2e27}textarea{width:100%;min-height:70px;box-sizing:border-box;padding:8px;border:1px solid #d5cfba;border-radius:7px}.acties{display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-top:15px}.acties form{margin:0}@media(max-width:700px){.grid{grid-template-columns:1fr}}
</style><link rel="stylesheet" href="ui-2026.css"></head><body><div class="top"><a href="./">← Beheer</a></div><main class="wrap"><h1>Contactberichten</h1><p>Berichten uit het publieke contactformulier blijven maximaal <?=cbEsc(contactBerichtenBewaardagen())?> dagen bewaard. Ook open berichten vallen onder deze maximale termijn.</p><?php if($flash):?><div class="flash <?=cbEsc($flash['type']??'')?>"><?=cbEsc($flash['tekst']??'')?></div><?php endif;?><nav class="filters"><a href="?status=open">Open</a><a href="?status=afgehandeld">Afgehandeld</a><a href="?status=alles">Alles</a></nav><?php if(!$lijst):?><p>Geen contactberichten in deze selectie.</p><?php endif;?><?php foreach($lijst as $b):?><section class="kaart"><h2><?=cbEsc($b['onderwerp']??'Contactvraag')?></h2><p class="meta"><?=cbEsc($b['status']??'nieuw')?> · <?=cbEsc($b['aangemaakt']??'')?> · id <?=cbEsc($b['id']??'')?></p><div class="grid"><div><strong><?=cbEsc($b['naam']??'')?></strong><br><?=cbEsc($b['email']??'')?><br><?=cbEsc($b['telefoon']??'')?></div><div class="bericht"><?=cbEsc($b['bericht']??'')?></div></div><?php if(($b['status']??'nieuw')==='nieuw'):?><div class="acties"><form method="post"><input type="hidden" name="csrf" value="<?=cbEsc($csrfToken)?>"><input type="hidden" name="actie" value="afhandelen"><input type="hidden" name="id" value="<?=cbEsc($b['id']??'')?>"><textarea name="notitie" maxlength="500" placeholder="Interne notitie (optioneel)"></textarea><br><button class="btn ok" type="submit">Markeer afgehandeld</button></form></div><?php else:?><p class="meta">Afgehandeld door <?=cbEsc($b['afgehandeld_door']??'')?> op <?=cbEsc($b['afgehandeld_op']??'')?><?php if(trim((string)($b['notitie']??''))!==''):?><br>Notitie: <?=cbEsc($b['notitie'])?><?php endif;?></p><div class="acties"><form method="post"><input type="hidden" name="csrf" value="<?=cbEsc($csrfToken)?>"><input type="hidden" name="actie" value="heropenen"><input type="hidden" name="id" value="<?=cbEsc($b['id']??'')?>"><button class="btn" type="submit">Heropenen</button></form><form method="post" onsubmit="return confirm('Dit contactbericht definitief verwijderen?');"><input type="hidden" name="csrf" value="<?=cbEsc($csrfToken)?>"><input type="hidden" name="actie" value="verwijderen"><input type="hidden" name="id" value="<?=cbEsc($b['id']??'')?>"><button class="btn gevaar" type="submit">Verwijderen</button></form></div><?php endif;?></section><?php endforeach;?></main></body></html>
