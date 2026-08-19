<?php
// ============================================================
// Beheer > Leden importeren
// ============================================================
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/app/auth-capabilities.php';
require_once dirname(__DIR__) . '/app/data-slot.php';
require_once dirname(__DIR__) . '/app/leden/service.php';

if(!$ingelogd){header('Location: ./');exit;}
if(!authHeeftCapability('members.manage')){http_response_code(403);echo'Geen toegang tot ledenimport.';exit;}

function liEsc($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function liFlash(string $t,string $type='ok'):void{$_SESSION['leden_import_flash']=['tekst'=>$t,'type'=>$type];}
function liRedirect():void{header('Location: leden-import.php');exit;}

$flash=$_SESSION['leden_import_flash']??null;unset($_SESSION['leden_import_flash']);
$preview=$_SESSION['leden_import_preview']??null;

if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    if(!csrfOk()){liFlash('Sessie verlopen. Ververs de pagina.','fout');liRedirect();}
    $actie=(string)($_POST['actie']??'');
    if($actie==='lezen'){
        if(!isset($_FILES['csv'])||!is_array($_FILES['csv'])||($_FILES['csv']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){liFlash('Kies een leesbaar CSV-bestand.','fout');liRedirect();}
        $f=$_FILES['csv'];$grootte=(int)($f['size']??0);$naam=(string)($f['name']??'');
        if($grootte<=0||$grootte>5*1024*1024){liFlash('CSV moet groter dan 0 en maximaal 5 MB zijn.','fout');liRedirect();}
        if(strtolower(pathinfo($naam,PATHINFO_EXTENSION))!=='csv'){liFlash('Alleen .csv-bestanden zijn toegestaan.','fout');liRedirect();}
        $inhoud=@file_get_contents((string)$f['tmp_name']);if($inhoud===false){liFlash('CSV kon niet worden gelezen.','fout');liRedirect();}
        $gelezen=ledenCsvLezen($inhoud);$rijen=(array)($gelezen['rijen']??[]);
        if(!$rijen){liFlash('Geen bruikbare ledenregels gevonden. Controleer de kolomkoppen.','fout');liRedirect();}
        if(count($rijen)>5000){liFlash('Import geweigerd: maximaal 5000 regels per bestand.','fout');liRedirect();}
        $data=ledenServiceLees();$resultaten=[];$nieuw=0;$bij=0;
        foreach($rijen as $rij){if(!is_array($rij))continue;$kandidaat=ledenNormaliseer($rij,null);$match=ledenZoekBestaandeMet($data,$kandidaat);$bestaand=$match['index']===null?null:$data['leden'][$match['index']];$resultaten[]=['rij'=>$rij,'match_index'=>$match['index'],'reden'=>$match['reden'],'naam'=>ledenVolledigeNaam($kandidaat),'nummer'=>$kandidaat['nummer']??0];if($match['index']===null)$nieuw++;else$bij++;}
        $_SESSION['leden_import_preview']=['bestand'=>ledenKort($naam,120),'kolommen'=>$gelezen['kolommen']??[],'resultaten'=>$resultaten,'aantal_nieuw'=>$nieuw,'aantal_bijwerken'=>$bij,'aangemaakt'=>time()];
        liFlash('CSV gelezen. Controleer de preview en bevestig daarna de import.');liRedirect();
    }
    if($actie==='annuleren'){unset($_SESSION['leden_import_preview']);liFlash('Import geannuleerd.');liRedirect();}
    if($actie==='bevestigen'){
        $preview=$_SESSION['leden_import_preview']??null;
        if(!is_array($preview)||empty($preview['resultaten'])||time()-(int)($preview['aangemaakt']??0)>3600){unset($_SESSION['leden_import_preview']);liFlash('Importpreview ontbreekt of is ouder dan één uur. Lees het CSV-bestand opnieuw.','fout');liRedirect();}
        $slot=dataSlotOpen();
        try{
            $data=ledenServiceLees();$toegevoegd=0;$bijgewerkt=0;$overgeslagen=0;$magAuth=authHeeftCapability('system.users.manage',true);
            foreach($preview['resultaten'] as $item){$rij=is_array($item['rij']??null)?$item['rij']:[];if(!$rij){$overgeslagen++;continue;}
                // Match opnieuw tegen de actuele data: de administratie kan sinds
                // de preview veranderd zijn.
                $kandidaat=ledenNormaliseer($rij,null);$match=ledenZoekBestaandeMet($data,$kandidaat);$idx=$match['index'];$bestaand=$idx===null?null:$data['leden'][$idx];
                $invoer=$rij;unset($invoer['_contributie']);
                if(!$magAuth)unset($invoer['bestuursfunctie'],$invoer['beheer_account']);
                $lid=ledenNormaliseer($invoer,$bestaand);
                $lid['commissies']=array_values(array_intersect((array)$lid['commissies'],array_keys(ledenCommissies($data))));
                if($idx===null){if((int)$lid['nummer']<=0)$lid['nummer']=ledenVolgendNummer($data);else{foreach($data['leden'] as $ander)if((int)($ander['nummer']??0)===(int)$lid['nummer']){$lid['nummer']=ledenVolgendNummer($data);break;}}if($lid['inschrijfdatum']==='')$lid['inschrijfdatum']=date('Y-m-d');$lid['bron']='csv_import';}
                foreach((array)($rij['_contributie']??[]) as $jaar=>$regel){if(!is_array($regel))continue;$lid=ledenZetContributie($lid,(int)$jaar,['status'=>ledenContributieStatusUitTekst($regel['contributiestatus']??''),'bedrag'=>$regel['contributiebedrag']??'','inschrijfgeld'=>$regel['inschrijfgeld']??'','betaald_op'=>'','opmerking'=>'Geïmporteerd uit CSV']);}
                if($idx===null){$data['leden'][]=$lid;$toegevoegd++;}else{$data['leden'][$idx]=$lid;$bijgewerkt++;}
                $data['volgnummer']=max((int)($data['volgnummer']??0),(int)$lid['nummer']);
            }
            if(!ledenServiceSchrijf($data)){liFlash('Import kon niet worden opgeslagen. De preview blijft beschikbaar.','fout');liRedirect();}
            schrijfLog($logBestand,$huidigeGebruiker,'leden_csv_import',$toegevoegd.' toegevoegd · '.$bijgewerkt.' bijgewerkt · '.$overgeslagen.' overgeslagen');
            unset($_SESSION['leden_import_preview']);liFlash('Import voltooid: '.$toegevoegd.' toegevoegd, '.$bijgewerkt.' bijgewerkt, '.$overgeslagen.' overgeslagen.');
        }finally{dataSlotDicht($slot);}liRedirect();
    }
}
$preview=$_SESSION['leden_import_preview']??null;
?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Leden importeren</title><style>body{margin:0;background:#f6f2e8;color:#26351d;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.top{padding:14px 22px;background:#fff;border-bottom:1px solid #ddd8c0}.top a{color:#2d6260;font-weight:750;text-decoration:none}.wrap{max-width:1100px;margin:30px auto;padding:0 20px 70px}.card{background:#fff;border:1px solid #ddd8c0;border-radius:14px;padding:20px;margin:14px 0}.btn{border:1px solid #d3ccb7;background:#fff;color:#2d6260;border-radius:8px;padding:9px 12px;font:inherit;font-weight:750;cursor:pointer}.btn.primary{background:#3a7a77;color:#fff;border:0}.btn.danger{background:#fff0ed;color:#8b2e27}.actions{display:flex;gap:8px;flex-wrap:wrap}.melding{padding:12px;border-radius:8px;background:#eaf6ee}.melding.fout{background:#fdeceb;color:#8b2e27}.meta{color:#68705f;font-size:13px}.tablewrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:700px}th,td{text-align:left;padding:8px;border-bottom:1px solid #eee9db}.nieuw{color:#23613e}.bij{color:#8a6513}</style></head><body><div class="top"><a href="leden.php">← Leden</a></div><main class="wrap"><h1>CSV importeren</h1><p class="meta">De import herkent bestaande leden opnieuw op e-mail, lidnummer+naam, naam+geboortedatum of een unieke niet-tegenstrijdige naam. Een preview schrijft nog niets.</p><?php if($flash):?><div class="melding <?=liEsc($flash['type']??'')?>"><?=liEsc($flash['tekst']??'')?></div><?php endif;?><?php if(!$preview):?><section class="card"><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=liEsc($csrfToken)?>"><input type="hidden" name="actie" value="lezen"><p><input type="file" name="csv" accept=".csv,text/csv" required></p><button class="btn primary" type="submit">CSV lezen en preview maken</button></form></section><?php else:?><section class="card"><h2>Preview: <?=liEsc($preview['bestand']??'')?></h2><p><strong><?=liEsc($preview['aantal_nieuw']??0)?></strong> nieuw · <strong><?=liEsc($preview['aantal_bijwerken']??0)?></strong> bestaand/bijwerken</p><div class="tablewrap"><table><thead><tr><th>Naam</th><th>Lidnummer</th><th>Actie</th><th>Herkenning</th></tr></thead><tbody><?php foreach(array_slice((array)$preview['resultaten'],0,200) as $r):$isNieuw=$r['match_index']===null;?><tr><td><?=liEsc($r['naam']??'')?></td><td><?=liEsc($r['nummer']??'')?></td><td class="<?=$isNieuw?'nieuw':'bij'?>"><?=$isNieuw?'Nieuw':'Bijwerken'?></td><td><?=liEsc($r['reden']??'')?></td></tr><?php endforeach;?></tbody></table></div><?php if(count((array)$preview['resultaten'])>200):?><p class="meta">Alleen de eerste 200 regels worden getoond; alle <?=count((array)$preview['resultaten'])?> regels worden bij bevestigen opnieuw gevalideerd.</p><?php endif;?><div class="actions"><form method="post"><input type="hidden" name="csrf" value="<?=liEsc($csrfToken)?>"><input type="hidden" name="actie" value="bevestigen"><button class="btn primary" type="submit">Import definitief uitvoeren</button></form><form method="post"><input type="hidden" name="csrf" value="<?=liEsc($csrfToken)?>"><input type="hidden" name="actie" value="annuleren"><button class="btn danger" type="submit">Annuleren</button></form></div></section><?php endif;?></main></body></html>
