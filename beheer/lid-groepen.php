<?php
// ============================================================
// Beheer > Leden > Commissies en werkgroepen
// ============================================================
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/app/auth-capabilities.php';
require_once dirname(__DIR__) . '/app/data-slot.php';
require_once dirname(__DIR__) . '/app/leden/service.php';
require_once dirname(__DIR__) . '/app/leden/groepen.php';

if(!$ingelogd){header('Location: ./');exit;}
$magLedenBekijken=authHeeftCapability('members.view')||authHeeftCapability('members.manage');
$magCommissies=authHeeftCapability('committees.manage');
$magWerkgroepen=authHeeftCapability('workgroups.manage');
if(!$magLedenBekijken||(!$magCommissies&&!$magWerkgroepen)){http_response_code(403);echo'Geen toegang.';exit;}

function lgEsc($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function lgRedirect(string $lidId):void{header('Location: lid-groepen.php?id='.rawurlencode($lidId));exit;}
function lgVindLid(array $data,string $id):?array{foreach((array)($data['leden']??[]) as $lid)if(is_array($lid)&&($lid['id']??'')===$id)return$lid;return null;}
function lgMagGroep(array $groep,bool $magCommissies,bool $magWerkgroepen):bool{$type=(string)($groep['type']??'');return($type==='commissie'&&$magCommissies)||($type==='werkgroep'&&$magWerkgroepen);}
function lgActieveDeelname(array $groep,string $lidId):?array{foreach(groepenActieveLeden($groep) as $m)if(($m['lid_id']??'')===$lidId)return$m;return null;}

$lidId=trim((string)($_GET['id']??$_POST['id']??''));
$ledenData=ledenServiceLees();$lid=lgVindLid($ledenData,$lidId);
if(!$lid){http_response_code(404);echo'Lid niet gevonden.';exit;}
$gearchiveerd=!empty($lid['gearchiveerd_op']);
$flash=$_SESSION['lid_groepen_flash']??null;unset($_SESSION['lid_groepen_flash']);

if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    if(!csrfOk()){$_SESSION['lid_groepen_flash']=['type'=>'fout','tekst'=>'Sessie verlopen. Ververs de pagina.'];lgRedirect($lidId);}
    if($gearchiveerd){$_SESSION['lid_groepen_flash']=['type'=>'fout','tekst'=>'Groepslidmaatschappen van een gearchiveerd lid kunnen niet worden gewijzigd.'];lgRedirect($lidId);}
    $slot=dataSlotOpen();
    try{
        $doc=groepenLeesDocument();$rollenActief=groepenRolMap($doc,false);$geselecteerd=array_fill_keys(array_map('strval',(array)($_POST['groepen']??[])),true);$gewijzigd=0;
        foreach((array)($doc['groepen']??[]) as $i=>$groep){
            if(!is_array($groep)||!lgMagGroep($groep,$magCommissies,$magWerkgroepen)||($groep['status']??'actief')!=='actief')continue;
            $groepId=(string)($groep['id']??'');if($groepId==='')continue;$huidig=lgActieveDeelname($groep,$lidId);$moetActief=isset($geselecteerd[$groepId]);$gekozen=[];
            if($moetActief){
                $toegestaan=$rollenActief;foreach((array)($huidig['rollen']??[]) as $r)$toegestaan[(string)$r]=true;
                foreach((array)($_POST['rollen'][$groepId]??[]) as $rol){$rol=groepenId($rol);if($rol!==''&&isset($toegestaan[$rol]))$gekozen[]=$rol;}
                if(!$gekozen)$gekozen=isset($rollenActief['lid'])?['lid']:(array_slice(array_keys($rollenActief),0,1)?:['lid']);
            }
            $voor=json_encode($groep['leden']??[]);$doc['groepen'][$i]['leden']=groepenWerkLidBij((array)($groep['leden']??[]),$lidId,$moetActief,$gekozen);$na=json_encode($doc['groepen'][$i]['leden']);
            if($voor!==$na){$doc['groepen'][$i]['gewijzigd']=date('c');$gewijzigd++;}
        }
        if($gewijzigd===0)$_SESSION['lid_groepen_flash']=['type'=>'ok','tekst'=>'Geen wijzigingen nodig.'];
        elseif(groepenSchrijfDocument($doc)){schrijfLog($logBestand,$huidigeGebruiker,'lid_groepen_bijgewerkt',$lidId.' · '.$gewijzigd.' groep(en)');$_SESSION['lid_groepen_flash']=['type'=>'ok','tekst'=>'Commissies en werkgroepen bijgewerkt.'];}
        else $_SESSION['lid_groepen_flash']=['type'=>'fout','tekst'=>'Opslaan mislukt.'];
    }finally{dataSlotDicht($slot);}
    lgRedirect($lidId);
}

$doc=groepenLeesDocument();$rollenAlle=groepenRolMap($doc,true);$rollenActief=groepenRolMap($doc,false);$groepen=[];$historie=[];
foreach((array)($doc['groepen']??[]) as $groep){
    if(!is_array($groep)||!lgMagGroep($groep,$magCommissies,$magWerkgroepen))continue;
    if(($groep['status']??'actief')==='actief')$groepen[]=$groep;
    foreach((array)($groep['leden']??[]) as $m)if(is_array($m)&&($m['lid_id']??'')===$lidId)$historie[]=['groep'=>$groep,'deelname'=>$m];
}
usort($groepen,static fn($a,$b)=>strcmp((string)$a['type'],(string)$b['type'])?:strnatcasecmp((string)$a['naam'],(string)$b['naam']));
usort($historie,static fn($a,$b)=>strcmp((string)($b['deelname']['sinds']??''),(string)($a['deelname']['sinds']??'')));
?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Groepen van <?=lgEsc(ledenVolledigeNaam($lid))?></title><style>body{margin:0;background:#f6f2e8;color:#26351d;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.top{padding:14px 22px;background:#fff;border-bottom:1px solid #ddd8c0}.top a{color:#2d6260;font-weight:750;text-decoration:none}.wrap{max-width:1050px;margin:30px auto;padding:0 20px 70px}.card{background:#fff;border:1px solid #ddd8c0;border-radius:14px;padding:18px;margin:14px 0}.groep{border-top:1px solid #eee9db;padding:14px 0}.groep:first-child{border-top:0}.rollen{display:flex;gap:10px;flex-wrap:wrap;margin:8px 0 0 26px}.meta{color:#68705f;font-size:13px}.flash{padding:12px;border-radius:8px;background:#eaf6ee}.flash.fout{background:#fdeceb;color:#8b2e27}.notice{background:#fbf4df;border:1px solid #e4d09a;padding:12px;border-radius:9px}.btn{display:inline-block;border:1px solid #d3ccb7;background:#fff;color:#2d6260;border-radius:8px;padding:9px 12px;text-decoration:none;font:inherit;font-weight:750;cursor:pointer}.btn.primary{background:#3a7a77;color:#fff;border:0}.badge{display:inline-block;padding:2px 7px;border-radius:999px;background:#eef2ec;font-size:12px;margin-left:6px}</style></head><body><div class="top"><a href="leden.php?edit=<?=rawurlencode($lidId)?>">← Terug naar lid</a></div><main class="wrap"><h1><?=lgEsc(ledenVolledigeNaam($lid))?></h1><p class="meta">Beheer hier vanuit het lid aan welke commissies en werkgroepen deze persoon deelneemt en met welke rol(len).</p><?php if($flash):?><div class="flash <?=lgEsc($flash['type']??'')?>"><?=lgEsc($flash['tekst']??'')?></div><?php endif;?><?php if($gearchiveerd):?><p class="notice">Dit lid is gearchiveerd. De groepshistorie blijft zichtbaar maar kan niet meer worden gewijzigd.</p><?php endif;?>
<section class="card"><h2>Huidige commissies en werkgroepen</h2><?php if(!$groepen):?><p>Er zijn geen actieve groepen die je mag beheren.</p><?php else:?><form method="post"><input type="hidden" name="csrf" value="<?=lgEsc($csrfToken)?>"><input type="hidden" name="id" value="<?=lgEsc($lidId)?>"><?php foreach($groepen as $groep):$gid=(string)$groep['id'];$deelname=lgActieveDeelname($groep,$lidId);$type=(string)$groep['type'];?><div class="groep"><label><input type="checkbox" name="groepen[]" value="<?=lgEsc($gid)?>" <?=$deelname?'checked':''?> <?=$gearchiveerd?'disabled':''?>> <strong><?=lgEsc($groep['naam'])?></strong><span class="badge"><?=lgEsc(groepenTypes()[$type]??$type)?></span></label><div class="rollen"><?php foreach($rollenAlle as $rid=>$rnaam):$isActief=isset($rollenActief[$rid]);$gekozen=$deelname&&in_array($rid,(array)($deelname['rollen']??[]),true);if(!$isActief&&!$gekozen)continue;?><label><input type="checkbox" name="rollen[<?=lgEsc($gid)?>][]" value="<?=lgEsc($rid)?>" <?=$gekozen?'checked':''?> <?=(!$isActief||$gearchiveerd)?'disabled':''?>> <?=lgEsc($rnaam)?><?=$isActief?'':' (niet actief)'?></label><?php if($gekozen&&!$isActief&&!$gearchiveerd):?><input type="hidden" name="rollen[<?=lgEsc($gid)?>][]" value="<?=lgEsc($rid)?>"><?php endif;?><?php endforeach;?></div></div><?php endforeach;?><?php if(!$gearchiveerd):?><p><button class="btn primary" type="submit">Groepen opslaan</button></p><?php endif;?></form><?php endif;?></section>
<section class="card"><h2>Historie</h2><?php if(!$historie):?><p>Nog geen groepshistorie.</p><?php endif;?><?php foreach($historie as $h):$g=$h['groep'];$m=$h['deelname'];?><div class="groep"><strong><?=lgEsc($g['naam'])?></strong><span class="badge"><?=lgEsc(groepenTypes()[$g['type']??'']??($g['type']??''))?></span><br><span class="meta"><?=lgEsc(implode(', ',array_map(static fn($r)=>$rollenAlle[$r]??$r,(array)($m['rollen']??[]))))?> · <?=lgEsc(($m['sinds']??'')?:'onbekend')?> t/m <?=lgEsc(($m['tot']??'')?:'heden')?></span></div><?php endforeach;?></section></main></body></html>
