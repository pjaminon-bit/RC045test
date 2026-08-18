<?php
// ============================================================
// Modulaire beheerpagina: Fotoboek
// ============================================================
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/site.php';
require_once dirname(__DIR__) . '/data-slot.php';
require_once __DIR__ . '/fotoboek-lib.php';

if (!$ingelogd) { header('Location: ../beheer.php'); exit; }
if (!siteModuleActief('fotoboek')) { http_response_code(404); echo 'De fotoboekmodule is voor deze vereniging niet ingeschakeld.'; exit; }
$rechten=authRechten(['fotoboek'=>'Fotoboek'],[]);
if(!$isMaster&&!in_array('fotoboek',$rechten['toegestaneTabs']??[],true)){http_response_code(403);echo'Geen toegang tot Fotoboek.';exit;}

$root=dirname(__DIR__);
$dataPad=$root.'/data/fotoboek.json';
$tekstPad=$root.'/data/fotoboek-pagina.json';
$fotoRoot=$root.'/images/fotoboek';
$logoPad=$root.'/rc045-logo.png';
$maxVol=1600;$maxThumb=400;$maxFoto=25*1024*1024;
$tekstStandaard=['hero_sub'=>['nl'=>"Foto's van onze evenementen en banen, gerangschikt per album. Klik op een album om de foto's te bekijken.",'en'=>'Photos from our events and tracks, sorted by album. Click an album to view the photos.','de'=>'Fotos von unseren Veranstaltungen und Strecken, sortiert nach Album. Klicke auf ein Album, um die Fotos anzusehen.']];

function fbTekstLees(string $pad,array $std): array { if(!is_file($pad))return $std;$d=json_decode((string)@file_get_contents($pad),true);if(!is_array($d))return $std;$r=$std;foreach(['nl','en','de'] as $t){$v=$d['hero_sub'][$t]??'';if(is_string($v)&&trim($v)!=='')$r['hero_sub'][$t]=$v;}return $r; }
function fbAlbumIndex(array $data,string $slug): ?int { foreach($data['albums']??[] as $i=>$a)if((string)($a['slug']??'')===$slug)return (int)$i;return null; }
function fbFlash(string $tekst,string $type='ok'): void { $_SESSION['flash']['fotoboek']=['tekst'=>$tekst,'type'=>$type]; }
function fbRedirect(): void { header('Location: fotoboek.php');exit; }

$melding='';$meldingType='';
if(isset($_SESSION['flash']['fotoboek'])&&is_array($_SESSION['flash']['fotoboek'])){$melding=(string)($_SESSION['flash']['fotoboek']['tekst']??'');$meldingType=(string)($_SESSION['flash']['fotoboek']['type']??'ok');unset($_SESSION['flash']['fotoboek']);}

if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    if(!csrfOk()){$melding='Sessie verlopen. Ververs de pagina en probeer opnieuw.';$meldingType='fout';}
    else{
        $slot=dataSlotOpen();
        try{
            $actie=is_string($_POST['actie']??null)?$_POST['actie']:'';
            if($actie==='tekst_opslaan'){
                $nieuw=['hero_sub'=>[]];foreach(['nl','en','de'] as $t)$nieuw['hero_sub'][$t]=fbKort($_POST['hero_sub'][$t]??'',400);
                if(fbSchrijf($tekstPad,$nieuw)){schrijfLog($logBestand,$huidigeGebruiker,'fotoboek_tekst','ondertitel bijgewerkt via modulaire editor');fbFlash('Opgeslagen. De fotoboekpagina gebruikt meteen deze tekst.');fbRedirect();}
                $melding='Opslaan mislukt. Controleer de schrijfrechten van de data-map.';$meldingType='fout';
            }
            elseif($actie==='album_aanmaken'){
                $titel=fbKort($_POST['titel_nl']??'',60);
                if($titel===''){$melding='Vul een Nederlandse titel in.';$meldingType='fout';}
                else{
                    $data=fbLees($dataPad);$slugs=array_map(static fn($a)=>(string)($a['slug']??''),$data['albums']);$slug=fbUniekeSlug(fbMaakSlug($titel),$slugs);$map=$fotoRoot.'/'.$slug;
                    if(!is_dir($map.'/thumbs')&&!@mkdir($map.'/thumbs',0755,true)){$melding='Album-map kon niet worden aangemaakt.';$meldingType='fout';}
                    else{
                        $data['albums'][]=['slug'=>$slug,'title'=>['nl'=>$titel,'en'=>fbKort($_POST['titel_en']??'',60),'de'=>fbKort($_POST['titel_de']??'',60)],'date'=>date('Y-m-d'),'volgorde'=>count($data['albums']),'cover'=>'','verborgen'=>false,'beschrijving'=>['nl'=>'','en'=>'','de'=>''],'photos'=>[]];
                        if(fbSchrijf($dataPad,$data)){schrijfLog($logBestand,$huidigeGebruiker,'fotoboek_album_aangemaakt',$titel);fbFlash('Album “'.$titel.'” is aangemaakt.');fbRedirect();}
                        fbVerwijderMap($map);$melding='Opslaan mislukt; de nieuw aangemaakte album-map is weer opgeruimd.';$meldingType='fout';
                    }
                }
            }
            elseif($actie==='album_verwijderen'){
                $slug=fbMaakSlug($_POST['slug']??'');$data=fbLees($dataPad);$idx=fbAlbumIndex($data,$slug);
                if($idx===null){$melding='Album niet gevonden.';$meldingType='fout';}
                elseif(trim((string)($_POST['bevestiging']??''))!=='VERWIJDER'){$melding='Typ VERWIJDER om het album definitief te verwijderen.';$meldingType='fout';}
                else{
                    $album=$data['albums'][$idx];$titel=(string)($album['title']['nl']??$slug);array_splice($data['albums'],$idx,1);
                    // Eerst de administratie opslaan; pas daarna bestanden wissen.
                    if(fbSchrijf($dataPad,$data)){fbVerwijderMap($fotoRoot.'/'.$slug);schrijfLog($logBestand,$huidigeGebruiker,'fotoboek_album_verwijderd',$titel);fbFlash('Album “'.$titel.'” is verwijderd.');fbRedirect();}
                    $melding='Verwijderen afgebroken omdat de administratie niet kon worden opgeslagen. De bestanden zijn intact gebleven.';$meldingType='fout';
                }
            }
            elseif($actie==='album_opslaan'){
                $slug=fbMaakSlug($_POST['slug']??'');$data=fbLees($dataPad);$idx=fbAlbumIndex($data,$slug);
                if($idx===null){$melding='Album niet gevonden. Ververs de pagina.';$meldingType='fout';}
                else{
                    $album=$data['albums'][$idx];$albumPad=$fotoRoot.'/'.$slug;if(!is_dir($albumPad.'/thumbs'))@mkdir($albumPad.'/thumbs',0755,true);
                    $titel=fbKort($_POST['titel_nl']??'',60);if($titel!=='')$album['title']['nl']=$titel;
                    $album['title']['en']=fbKort($_POST['titel_en']??'',60);$album['title']['de']=fbKort($_POST['titel_de']??'',60);
                    $album['beschrijving']=['nl'=>fbKort($_POST['beschrijving_nl']??'',600),'en'=>fbKort($_POST['beschrijving_en']??'',600),'de'=>fbKort($_POST['beschrijving_de']??'',600)];
                    $album['verborgen']=!empty($_POST['album_verborgen']);$album['volgorde']=is_numeric($_POST['volgorde']??null)?(float)$_POST['volgorde']:(float)($album['volgorde']??$idx);
                    $datum=fbDatumIso($_POST['datum']??'');if($datum!=='')$album['date']=$datum;

                    $teVerwijderen=[];$gekozenCover=basename((string)($_POST['cover_bestand']??''));$watermerkTeller=0;
                    foreach((array)($_POST['foto']??[]) as $rij){if(!is_array($rij))continue;$bestand=basename((string)($rij['bestand']??''));if($bestand==='')continue;
                        $pi=null;foreach($album['photos'] as $i=>$p)if((string)($p['file']??'')===$bestand){$pi=$i;break;}if($pi===null)continue;
                        if(!empty($rij['verwijderen'])){$teVerwijderen[]=$album['photos'][$pi];continue;}
                        $album['photos'][$pi]['caption']=['nl'=>fbKort($rij['caption_nl']??'',150),'en'=>fbKort($rij['caption_en']??'',150),'de'=>fbKort($rij['caption_de']??'',150)];
                        if(($album['photos'][$pi]['type']??'photo')!=='video'&&!empty($rij['watermerk_toevoegen'])&&fbWatermerkBestaand($albumPad.'/'.$bestand,$logoPad)){$album['photos'][$pi]['watermerk']=true;$watermerkTeller++;}
                    }
                    if($teVerwijderen){$namen=array_map(static fn($p)=>(string)($p['file']??''),$teVerwijderen);$album['photos']=array_values(array_filter($album['photos'],static fn($p)=>!in_array((string)($p['file']??''),$namen,true)));}

                    $nieuweBestanden=[];$uploadFouten=[];$aantal=0;$watermerkAan=!empty($_POST['watermerk']);
                    $hashes=[];foreach($album['photos'] as $p)if(!empty($p['hash']))$hashes[(string)$p['hash']]=true;
                    if(isset($_FILES['nieuwe_fotos'])&&is_array($_FILES['nieuwe_fotos']['tmp_name']??null)){
                        foreach($_FILES['nieuwe_fotos']['tmp_name'] as $i=>$tmp){$err=(int)($_FILES['nieuwe_fotos']['error'][$i]??UPLOAD_ERR_NO_FILE);if($err===UPLOAD_ERR_NO_FILE)continue;$orig=(string)($_FILES['nieuwe_fotos']['name'][$i]??'foto');
                            if($err!==UPLOAD_ERR_OK){$uploadFouten[]=$orig.': uploaden mislukt.';continue;}
                            if((int)($_FILES['nieuwe_fotos']['size'][$i]??0)>$maxFoto){$uploadFouten[]=$orig.': groter dan 25 MB.';continue;}
                            $hash=@sha1_file($tmp);if($hash&&isset($hashes[$hash])){$uploadFouten[]=$orig.': staat al in dit album, overgeslagen.';continue;}
                            $basis=preg_replace('/[^a-z0-9]+/','-',strtolower(pathinfo($orig,PATHINFO_FILENAME)));$basis=trim((string)$basis,'-');if($basis==='')$basis='foto';$naam=$basis.'.jpg';$n=2;while(file_exists($albumPad.'/'.$naam))$naam=$basis.'-'.$n++.'.jpg';
                            $res=fbVerwerkFoto($tmp,$albumPad.'/'.$naam,$albumPad.'/thumbs/'.$naam,$watermerkAan,$logoPad,$maxVol,$maxThumb);
                            if(empty($res['ok'])){$uploadFouten[]=$orig.': '.($res['fout']??'verwerking mislukt.');continue;}
                            $album['photos'][]=['type'=>'photo','file'=>$naam,'width'=>(int)$res['width'],'height'=>(int)$res['height'],'caption'=>['nl'=>'','en'=>'','de'=>''],'watermerk'=>$watermerkAan,'hash'=>$hash?:null];$nieuweBestanden[]=$naam;if($hash)$hashes[$hash]=true;$aantal++;
                        }
                    }
                    if(!empty($_POST['album_watermerk_alle']))foreach($album['photos'] as &$p){if(($p['type']??'photo')==='video')continue;if(fbWatermerkBestaand($albumPad.'/'.basename((string)$p['file']),$logoPad)){$p['watermerk']=true;$watermerkTeller++;}}unset($p);

                    $fotoNamen=[];foreach($album['photos'] as $p)if(($p['type']??'photo')!=='video')$fotoNamen[]=(string)$p['file'];
                    if($gekozenCover!==''&&in_array($gekozenCover,$fotoNamen,true))$album['cover']=$gekozenCover;
                    elseif(empty($album['cover'])||!in_array((string)$album['cover'],$fotoNamen,true))$album['cover']=$fotoNamen[0]??'';
                    $data['albums'][$idx]=$album;usort($data['albums'],static fn($a,$b)=>(float)($a['volgorde']??0)<=>(float)($b['volgorde']??0));
                    if(fbSchrijf($dataPad,$data)){
                        // Pas na succesvolle metadata-write de expliciet verwijderde bestanden wissen.
                        foreach($teVerwijderen as $p)fbVerwijderBestanden($albumPad,$p);
                        $delen=['Album opgeslagen'];if($aantal)$delen[]=$aantal.' foto(\'s) toegevoegd';if($watermerkTeller)$delen[]=$watermerkTeller.' watermerk(en) verwerkt';if($uploadFouten)$delen[]='meldingen: '.implode(' | ',$uploadFouten);
                        schrijfLog($logBestand,$huidigeGebruiker,'fotoboek_album_bijgewerkt',(string)($album['title']['nl']??$slug).'; '.implode('; ',$delen));
                        fbFlash(implode('. ',$delen).'.',$uploadFouten?'waarschuwing':'ok');fbRedirect();
                    }
                    // Nieuwe bestanden hebben nog geen metadatareferentie: opruimen.
                    foreach($nieuweBestanden as $naam){@unlink($albumPad.'/'.$naam);@unlink($albumPad.'/thumbs/'.$naam);}
                    $melding='Opslaan mislukt. Nieuw geüploade bestanden zijn weer opgeruimd; bestaande bestanden zijn niet verwijderd.';$meldingType='fout';
                }
            }
        }finally{dataSlotDicht($slot);}
    }
}

$data=fbLees($dataPad);$tekst=fbTekstLees($tekstPad,$tekstStandaard);
?><!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Fotoboek beheren</title>
<style>
body{margin:0;background:#f6f2e8;color:#26351d;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.top{position:sticky;top:0;z-index:20;background:#fff;border-bottom:1px solid #ddd8c0;padding:15px 22px}.topin{max-width:1180px;margin:auto;display:flex;justify-content:space-between;gap:16px}.top a{font-weight:700;color:#2d6260;text-decoration:none}.wrap{max-width:1180px;margin:28px auto;padding:0 22px 70px}.kaart{background:#fff;border:1px solid #ddd8c0;border-radius:14px;padding:22px;margin-bottom:20px}.kaart h2{margin-top:0}.melding{padding:12px 14px;border-radius:9px;margin-bottom:18px}.melding.ok{background:#e8f5ee;color:#205b38}.melding.fout{background:#fdeceb;color:#8b2e27}.melding.waarschuwing{background:#fff4d6;color:#725019}.talen{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.grid{display:grid;grid-template-columns:1.2fr .8fr .55fr;gap:12px}.veld{margin-bottom:14px}.veld label{display:block;font-weight:700;margin-bottom:6px}.veld input,.veld textarea,.veld select{box-sizing:border-box;width:100%;border:1px solid #cfcab7;border-radius:8px;padding:10px;font:inherit}.veld textarea{min-height:85px}.btn{border:0;border-radius:9px;padding:10px 15px;font:inherit;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block}.primair{background:#3a7a77;color:#fff}.secundair{background:#fff;border:1px solid #cfcab7;color:#26351d}.gevaar{background:#a33b30;color:#fff}.albumkop{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.badge{font-size:12px;border-radius:99px;padding:4px 8px;background:#edf1e7}.fotos{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px}.foto{border:1px solid #ddd8c0;border-radius:10px;padding:10px;background:#faf9f4}.foto img{width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:7px;background:#eee}.foto .klein{font-size:12px;color:#66705e;word-break:break-all}.checks{display:flex;gap:12px;flex-wrap:wrap;font-size:13px}.checks label{display:flex;gap:5px;align-items:center}.checks input{width:auto}.acties{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.danger-zone{margin-top:20px;padding-top:18px;border-top:1px solid #edd0cc}.danger-zone input{max-width:180px}.upload-status{display:none;margin:10px 0;padding:10px;border-radius:8px;background:#edf5f4}.hint{color:#66705e;font-size:13px;line-height:1.5}@media(max-width:800px){.talen,.grid{grid-template-columns:1fr}.albumkop{display:block}.fotos{grid-template-columns:1fr}}
</style></head><body><div class="top"><div class="topin"><a href="../beheer.php">← Terug naar beheer</a><a href="../fotoboek.html" target="_blank" rel="noopener">Bekijk fotoboek ↗</a></div></div><main class="wrap"><h1>Fotoboek</h1><p>Albums, foto's, covers, bijschriften en de publieke introductietekst.</p>
<?php if($melding!==''):?><div class="melding <?=fbEsc($meldingType)?>"><?=fbEsc($melding)?></div><?php endif;?>
<section class="kaart"><h2>Tekst bovenaan fotoboek</h2><form method="post"><input type="hidden" name="csrf" value="<?=fbEsc($csrfToken)?>"><input type="hidden" name="actie" value="tekst_opslaan"><div class="talen"><?php foreach(['nl'=>'Nederlands','en'=>'Engels','de'=>'Duits'] as $t=>$lab):?><div class="veld"><label><?=$lab?></label><textarea name="hero_sub[<?=$t?>]" maxlength="400"><?=fbEsc($tekst['hero_sub'][$t]??'')?></textarea></div><?php endforeach;?></div><button class="btn primair">Tekst opslaan</button></form></section>
<section class="kaart"><h2>Nieuw album</h2><form method="post"><input type="hidden" name="csrf" value="<?=fbEsc($csrfToken)?>"><input type="hidden" name="actie" value="album_aanmaken"><div class="talen"><?php foreach(['nl'=>'Nederlands (verplicht)','en'=>'Engels','de'=>'Duits'] as $t=>$lab):?><div class="veld"><label><?=$lab?></label><input name="titel_<?=$t?>" maxlength="60" <?=$t==='nl'?'required':''?>></div><?php endforeach;?></div><button class="btn primair">Album aanmaken</button></form></section>
<?php foreach($data['albums'] as $album):$slug=(string)($album['slug']??'');$albumPad='../images/fotoboek/'.rawurlencode($slug);?><section class="kaart"><div class="albumkop"><div><h2><?=fbEsc($album['title']['nl']??$slug)?></h2><span class="badge"><?=count($album['photos']??[])?> item(s)<?=!empty($album['verborgen'])?' · verborgen':''?></span></div></div>
<form method="post" enctype="multipart/form-data" class="album-form"><input type="hidden" name="csrf" value="<?=fbEsc($csrfToken)?>"><input type="hidden" name="actie" value="album_opslaan"><input type="hidden" name="slug" value="<?=fbEsc($slug)?>"><div class="talen"><?php foreach(['nl'=>'Titel NL','en'=>'Titel EN','de'=>'Titel DE'] as $t=>$lab):?><div class="veld"><label><?=$lab?></label><input name="titel_<?=$t?>" maxlength="60" value="<?=fbEsc($album['title'][$t]??'')?>"></div><?php endforeach;?></div><div class="talen"><?php foreach(['nl'=>'Beschrijving NL','en'=>'Beschrijving EN','de'=>'Beschrijving DE'] as $t=>$lab):?><div class="veld"><label><?=$lab?></label><textarea name="beschrijving_<?=$t?>" maxlength="600"><?=fbEsc($album['beschrijving'][$t]??'')?></textarea></div><?php endforeach;?></div><div class="grid"><div class="veld"><label>Datum</label><input type="date" name="datum" value="<?=fbEsc($album['date']??'')?>"></div><div class="veld"><label>Volgorde</label><input type="number" step="1" name="volgorde" value="<?=fbEsc($album['volgorde']??0)?>"></div><div class="veld"><label>Weergave</label><label class="checks"><input type="checkbox" name="album_verborgen" value="1" <?=!empty($album['verborgen'])?'checked':''?>> Verborgen</label></div></div>
<?php if(!empty($album['photos'])):?><h3>Foto's en video's</h3><div class="fotos"><?php foreach($album['photos'] as $i=>$p):$file=basename((string)($p['file']??''));$isVideo=($p['type']??'photo')==='video';$poster=basename((string)($p['poster']??''));$preview=$isVideo?($poster?:''):$file;?><div class="foto"><?php if($preview!==''):?><img src="<?=$albumPad?>/thumbs/<?=rawurlencode($preview)?>" alt="" loading="lazy"><?php else:?><div style="aspect-ratio:4/3;display:grid;place-items:center;background:#eee;border-radius:7px">🎬 Video</div><?php endif;?><div class="klein"><?=fbEsc($file)?><?=$isVideo?' · video':''?></div><input type="hidden" name="foto[<?=$i?>][bestand]" value="<?=fbEsc($file)?>"><div class="veld"><label>Bijschrift NL</label><input name="foto[<?=$i?>][caption_nl]" maxlength="150" value="<?=fbEsc($p['caption']['nl']??'')?>"></div><div class="veld"><label>Bijschrift EN</label><input name="foto[<?=$i?>][caption_en]" maxlength="150" value="<?=fbEsc($p['caption']['en']??'')?>"></div><div class="veld"><label>Bijschrift DE</label><input name="foto[<?=$i?>][caption_de]" maxlength="150" value="<?=fbEsc($p['caption']['de']??'')?>"></div><div class="checks"><?php if(!$isVideo):?><label><input type="radio" name="cover_bestand" value="<?=fbEsc($file)?>" <?=($album['cover']??'')===$file?'checked':''?>> Cover</label><label><input type="checkbox" name="foto[<?=$i?>][watermerk_toevoegen]" value="1"> Watermerk opnieuw zetten</label><?php endif;?><label><input type="checkbox" name="foto[<?=$i?>][verwijderen]" value="1"> Verwijderen</label></div></div><?php endforeach;?></div><?php endif;?>
<div class="veld" style="margin-top:18px"><label>Nieuwe foto's toevoegen</label><input class="foto-upload" type="file" name="nieuwe_fotos[]" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" multiple><p class="hint">JPG, PNG en WEBP, maximaal 25 MB per foto. Grote selecties worden door het beheerscherm automatisch één voor één verwerkt om PHP/hostinglimieten te vermijden.</p><label class="checks"><input type="checkbox" name="watermerk" value="1" checked> Watermerk op nieuwe foto's</label><label class="checks"><input type="checkbox" name="album_watermerk_alle" value="1"> Watermerk opnieuw op alle bestaande foto's</label><div class="upload-status"></div></div><div class="acties"><button class="btn primair" type="submit">Album opslaan</button></div></form>
<div class="danger-zone"><form method="post" onsubmit="return confirm('Dit album en alle bestanden definitief verwijderen?');"><input type="hidden" name="csrf" value="<?=fbEsc($csrfToken)?>"><input type="hidden" name="actie" value="album_verwijderen"><input type="hidden" name="slug" value="<?=fbEsc($slug)?>"><div class="veld"><label>Album definitief verwijderen</label><p class="hint">Extra beveiliging: typ <strong>VERWIJDER</strong>. De administratie wordt eerst veilig opgeslagen; daarna worden pas de bestanden gewist.</p><input name="bevestiging" autocomplete="off" placeholder="VERWIJDER"></div><button class="btn gevaar">Album verwijderen</button></form></div></section><?php endforeach;?></main>
<script>
// Grote uploadsets worden één bestand per verzoek gestuurd. Zo blijft ieder
// verzoek ruim onder post_max_size en memory_limit. Alleen het eerste verzoek
// bevat ook de overige albumvelden; daarna worden die velden opnieuw uit het
// actuele formulier opgebouwd, zodat elke batch dezelfde metadata bewaart.
document.querySelectorAll('.album-form').forEach(form=>{form.addEventListener('submit',async e=>{const input=form.querySelector('.foto-upload');if(!input||input.files.length<=1)return;e.preventDefault();const files=[...input.files];const status=form.querySelector('.upload-status');status.style.display='block';const btn=form.querySelector('button[type="submit"]');btn.disabled=true;let fouten=[];
 for(let i=0;i<files.length;i++){status.textContent=`Foto ${i+1} van ${files.length} verwerken…`;const fd=new FormData(form);fd.delete('nieuwe_fotos[]');fd.append('nieuwe_fotos[]',files[i],files[i].name);try{const r=await fetch('fotoboek.php',{method:'POST',body:fd,redirect:'follow'});if(!r.ok)fouten.push(files[i].name);}catch(err){fouten.push(files[i].name);}}
 status.textContent=fouten.length?`Klaar, maar ${fouten.length} verzoek(en) mislukten. Pagina wordt vernieuwd…`:'Upload klaar. Pagina wordt vernieuwd…';location.href='fotoboek.php';});});
</script></body></html>