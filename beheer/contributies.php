<?php
// ============================================================
// Beheer > Contributie-administratie
// ============================================================
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/app/auth-capabilities.php';
require_once dirname(__DIR__) . '/app/data-slot.php';
require_once dirname(__DIR__) . '/app/leden/service.php';
require_once dirname(__DIR__) . '/app/leden/contributies.php';

if(!$ingelogd){header('Location: ./');exit;}
if(!authHeeftCapability('members.fees.manage')){http_response_code(403);echo'Geen toegang tot Contributie.';exit;}
function coEsc($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function coFlash(string $t,string $type='ok'):void{$_SESSION['contributie_flash']=['tekst'=>$t,'type'=>$type];}
function coRedirect(array $q=[]):void{$qs=$q?'?'.http_build_query($q):'';header('Location: contributies.php'.$qs);exit;}
function coLidMap(array $leden):array{$map=[];foreach($leden as $l)if(is_array($l)&&($l['id']??'')!=='')$map[(string)$l['id']]=$l;return$map;}

$flash=$_SESSION['contributie_flash']??null;unset($_SESSION['contributie_flash']);
$ledenData=ledenServiceLees();$lidMap=coLidMap((array)($ledenData['leden']??[]));

if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    if(!csrfOk()){coFlash('Sessie verlopen. Ververs de pagina.','fout');coRedirect();}
    $actie=(string)($_POST['actie']??'');$slot=dataSlotOpen();
    try{
        $data=contributiesLees();
        if($actie==='migreren'){
            if(contributiesSchrijf($data)){schrijfLog($logBestand,$huidigeGebruiker,'contributies_gemigreerd',count($data['regels']).' regel(s)');coFlash('Bestaande contributieregels zijn vastgelegd in de aparte contributie-administratie.');}
            else coFlash('Migratie kon niet worden opgeslagen.','fout');
        }elseif($actie==='opslaan'){
            $lidId=trim((string)($_POST['lid_id']??''));$jaar=(int)($_POST['jaar']??0);
            if(!isset($lidMap[$lidId]))coFlash('Lid niet gevonden.','fout');
            elseif($jaar<2000||$jaar>2099)coFlash('Ongeldig contributiejaar.','fout');
            else{
                $typeId=trim((string)($_POST['lidmaatschap_type']??''));$type=$typeId===''?null:lidmaatschapTypeOpId($typeId);
                if($typeId!==''&&!$type)coFlash('Onbekend lidmaatschapstype.','fout');
                else{
                    $regel=contributieUpsert($data,[
                        'lid_id'=>$lidId,'jaar'=>$jaar,'lidmaatschap_type'=>$typeId,
                        'status'=>$_POST['status']??'open','verschuldigd_bedrag'=>$_POST['verschuldigd_bedrag']??0,
                        'inschrijfgeld'=>$_POST['inschrijfgeld']??0,'betaald_bedrag'=>$_POST['betaald_bedrag']??0,
                        'betaald_op'=>$_POST['betaald_op']??'','vrijstelling_reden'=>$_POST['vrijstelling_reden']??'',
                        'opmerking'=>$_POST['opmerking']??'',
                    ]);
                    if(contributiesSchrijf($data)){
                        schrijfLog($logBestand,$huidigeGebruiker,'contributie_bijgewerkt',$lidId.' · '.$jaar.' · '.$regel['status'].' · restant '.number_format(contributieRestant($regel),2,'.',''));
                        coFlash('Contributieregel opgeslagen.');
                    }else coFlash('Contributieregel kon niet worden opgeslagen.','fout');
                }
            }
        }elseif($actie==='nieuw'){
            $lidId=trim((string)($_POST['lid_id']??''));$jaar=(int)($_POST['jaar']??date('Y'));
            if(!isset($lidMap[$lidId]))coFlash('Kies een bestaand lid.','fout');
            elseif($jaar<2000||$jaar>2099)coFlash('Ongeldig contributiejaar.','fout');
            elseif(contributieVindIndex($data,$lidId,$jaar)!==null)coFlash('Voor dit lid en jaar bestaat al een contributieregel.','fout');
            else{
                $lid=$lidMap[$lidId];$type=ledenServiceType($lid);$bedrag=$type?(float)($type['jaarbedrag']??0):0;$inschrijf=0;
                $regel=contributieUpsert($data,['lid_id'=>$lidId,'jaar'=>$jaar,'lidmaatschap_type'=>$type['id']??'','status'=>'open','verschuldigd_bedrag'=>$bedrag,'inschrijfgeld'=>$inschrijf,'betaald_bedrag'=>0]);
                if(contributiesSchrijf($data)){schrijfLog($logBestand,$huidigeGebruiker,'contributie_aangemaakt',$lidId.' · '.$jaar);coFlash('Nieuwe contributieregel aangemaakt.');}
                else coFlash('Nieuwe contributieregel kon niet worden opgeslagen.','fout');
            }
        }else coFlash('Onbekende actie.','fout');
    }finally{dataSlotDicht($slot);}coRedirect(['jaar'=>(int)($_POST['jaar']??date('Y'))]);
}

$data=contributiesLees();$jaarFilter=isset($_GET['jaar'])&&ctype_digit((string)$_GET['jaar'])?(int)$_GET['jaar']:(int)date('Y');$statusFilter=trim((string)($_GET['status']??''));$zoek=trim((string)($_GET['q']??''));
$regels=[];foreach((array)($data['regels']??[]) as $r){if(!is_array($r)||(int)($r['jaar']??0)!==$jaarFilter)continue;if($statusFilter!==''&&($r['status']??'')!==$statusFilter)continue;$lid=$lidMap[(string)($r['lid_id']??'')]??null;if($zoek!==''&&$lid){$hay=ledenVolledigeNaam($lid).' '.($lid['nummer']??'').' '.($lid['email']??'');if(stripos($hay,$zoek)===false)continue;}$regels[]=$r;}
usort($regels,static function($a,$b)use($lidMap){$la=$lidMap[(string)($a['lid_id']??'')]??[];$lb=$lidMap[(string)($b['lid_id']??'')]??[];return strcmp(ledenSorteernaam($la),ledenSorteernaam($lb));});
$types=lidmaatschapLees()['types'];$actieveLeden=array_values(array_filter((array)($ledenData['leden']??[]),static fn($l)=>is_array($l)&&empty($l['gearchiveerd_op'])));usort($actieveLeden,static fn($a,$b)=>strcmp(ledenSorteernaam($a),ledenSorteernaam($b)));
$totaalVerschuldigd=0;$totaalBetaald=0;$totaalRestant=0;foreach($regels as $r){$totaalVerschuldigd+=contributieTotaal($r);$totaalBetaald+=(float)($r['betaald_bedrag']??0);$totaalRestant+=contributieRestant($r);}
?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Contributie</title><style>body{margin:0;background:#f6f2e8;color:#26351d;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.top{padding:14px 22px;background:#fff;border-bottom:1px solid #ddd8c0}.top a{color:#2d6260;font-weight:750;text-decoration:none}.wrap{max-width:1200px;margin:30px auto;padding:0 20px 70px}.card{background:#fff;border:1px solid #ddd8c0;border-radius:14px;padding:18px;margin:14px 0}.filters,.actions{display:flex;gap:8px;flex-wrap:wrap;align-items:end}.veld label{display:block;font-weight:700;font-size:13px;margin-bottom:5px}.veld input,.veld select,.veld textarea{box-sizing:border-box;padding:8px;border:1px solid #d3ccb7;border-radius:7px;font:inherit}.veld textarea{width:100%;min-height:65px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.btn{border:1px solid #d3ccb7;background:#fff;color:#2d6260;padding:9px 12px;border-radius:8px;font:inherit;font-weight:750;cursor:pointer;text-decoration:none}.btn.primary{background:#3a7a77;color:#fff;border:0}.meta{color:#68705f;font-size:13px}.flash{padding:12px;border-radius:8px;background:#eaf6ee}.flash.fout{background:#fdeceb;color:#8b2e27}.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.stat{background:#fff;border:1px solid #ddd8c0;border-radius:12px;padding:14px}.stat strong{display:block;font-size:24px}.regel{border-top:1px solid #eee9db;padding-top:14px;margin-top:14px}.restant{font-weight:800;color:#8b2e27}.betaald{color:#23613e}@media(max-width:900px){.grid{grid-template-columns:1fr 1fr}}@media(max-width:600px){.grid,.stats{grid-template-columns:1fr}}</style></head><body><div class="top"><a href="./">← Beheer</a></div><main class="wrap"><h1>Contributie-administratie</h1><p class="meta">Financiële jaarregels staan los van persoonsgegevens. Oude geneste regels worden automatisch als migratiebron meegenomen.</p><?php if($flash):?><div class="flash <?=coEsc($flash['type']??'')?>"><?=coEsc($flash['tekst']??'')?></div><?php endif;?><section class="card"><form method="get" class="filters"><div class="veld"><label>Jaar</label><input type="number" min="2000" max="2099" name="jaar" value="<?=coEsc($jaarFilter)?>"></div><div class="veld"><label>Status</label><select name="status"><option value="">Alle</option><?php foreach(contributieStatussen() as $k=>$lab):?><option value="<?=coEsc($k)?>" <?=$statusFilter===$k?'selected':''?>><?=coEsc($lab)?></option><?php endforeach;?></select></div><div class="veld"><label>Zoeken</label><input name="q" value="<?=coEsc($zoek)?>" placeholder="Naam, nummer, e-mail"></div><button class="btn" type="submit">Filter</button></form></section><div class="stats"><div class="stat"><span>Verschuldigd</span><strong>€<?=coEsc(number_format($totaalVerschuldigd,2,',','.'))?></strong></div><div class="stat"><span>Betaald</span><strong class="betaald">€<?=coEsc(number_format($totaalBetaald,2,',','.'))?></strong></div><div class="stat"><span>Openstaand</span><strong class="restant">€<?=coEsc(number_format($totaalRestant,2,',','.'))?></strong></div></div><section class="card"><h2>Nieuwe jaarregel</h2><form method="post" class="filters"><input type="hidden" name="csrf" value="<?=coEsc($csrfToken)?>"><input type="hidden" name="actie" value="nieuw"><div class="veld"><label>Lid</label><select name="lid_id" required><option value="">Kies…</option><?php foreach($actieveLeden as $l):?><option value="<?=coEsc($l['id'])?>"><?=coEsc(ledenVolledigeNaam($l))?> (#<?=coEsc($l['nummer']??'')?>)</option><?php endforeach;?></select></div><div class="veld"><label>Jaar</label><input type="number" name="jaar" min="2000" max="2099" value="<?=coEsc($jaarFilter)?>" required></div><button class="btn primary" type="submit">Regel aanmaken</button></form><form method="post" style="margin-top:12px"><input type="hidden" name="csrf" value="<?=coEsc($csrfToken)?>"><input type="hidden" name="actie" value="migreren"><button class="btn" type="submit">Legacy contributieregels nu vastleggen</button></form></section><section class="card"><h2><?=count($regels)?> regel(s) in <?=$jaarFilter?></h2><?php if(!$regels):?><p>Geen contributieregels gevonden.</p><?php endif;?><?php foreach($regels as $r):$lid=$lidMap[(string)($r['lid_id']??'')]??null;$rest=contributieRestant($r);?><form method="post" class="regel"><input type="hidden" name="csrf" value="<?=coEsc($csrfToken)?>"><input type="hidden" name="actie" value="opslaan"><input type="hidden" name="lid_id" value="<?=coEsc($r['lid_id']??'')?>"><input type="hidden" name="jaar" value="<?=coEsc($r['jaar']??$jaarFilter)?>"><h3><?=coEsc($lid?ledenVolledigeNaam($lid):'Verwijderd/onbekend lid')?> <span class="meta">#<?=coEsc($lid['nummer']??'')?> · <?=coEsc($r['jaar']??'')?></span></h3><div class="grid"><div class="veld"><label>Lidmaatschapstype</label><select name="lidmaatschap_type"><option value="">Geen/onbekend</option><?php foreach($types as $type):?><option value="<?=coEsc($type['id'])?>" <?=($r['lidmaatschap_type']??'')===$type['id']?'selected':''?>><?=coEsc(lidmaatschapLabel($type))?></option><?php endforeach;?></select></div><div class="veld"><label>Status</label><select name="status"><?php foreach(contributieStatussen() as $k=>$lab):?><option value="<?=coEsc($k)?>" <?=($r['status']??'')===$k?'selected':''?>><?=coEsc($lab)?></option><?php endforeach;?></select></div><div class="veld"><label>Contributie verschuldigd</label><input type="number" min="0" step="0.01" name="verschuldigd_bedrag" value="<?=coEsc($r['verschuldigd_bedrag']??0)?>"></div><div class="veld"><label>Inschrijfgeld</label><input type="number" min="0" step="0.01" name="inschrijfgeld" value="<?=coEsc($r['inschrijfgeld']??0)?>"></div><div class="veld"><label>Betaald bedrag</label><input type="number" min="0" step="0.01" name="betaald_bedrag" value="<?=coEsc($r['betaald_bedrag']??0)?>"></div><div class="veld"><label>Betaald op</label><input type="date" name="betaald_op" value="<?=coEsc($r['betaald_op']??'')?>"></div><div class="veld"><label>Vrijstelling / reden</label><input name="vrijstelling_reden" maxlength="300" value="<?=coEsc($r['vrijstelling_reden']??'')?>"></div><div class="veld"><label>Restant</label><strong class="<?=$rest>0?'restant':'betaald'?>">€<?=coEsc(number_format($rest,2,',','.'))?></strong></div></div><div class="veld"><label>Notitie</label><textarea name="opmerking" maxlength="1000"><?=coEsc($r['opmerking']??'')?></textarea></div><button class="btn primary" type="submit">Opslaan</button></form><?php endforeach;?></section></main></body></html>
