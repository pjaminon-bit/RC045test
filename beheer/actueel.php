<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/app/data-slot.php';
require_once dirname(__DIR__) . '/app/beheer/editor-hulp.php';
if(!$ingelogd){header('Location: ../beheer.php');exit;}
$rechten=authRechten(['mededeling'=>'Mededeling'],[]);if(!$isMaster&&!in_array('mededeling',$rechten['toegestaneTabs']??[],true)){http_response_code(403);echo'Geen toegang tot Mededeling.';exit;}
$bestand=dirname(__DIR__).'/data/actueel.json';$data=beheerEditorLeesJson($bestand,['text'=>'','updated'=>'']);$melding='';$type='';
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
 if(!csrfOk()){$melding='Sessie verlopen. Ververs de pagina en probeer opnieuw.';$type='fout';}
 else{$tekst=beheerEditorKort($_POST['tekst']??'',500);$nieuw=['text'=>$tekst,'updated'=>date('c')];$slot=dataSlotOpen();try{$ok=beheerEditorSchrijfJson($bestand,$nieuw);}finally{dataSlotDicht($slot);}if($ok){$data=$nieuw;$melding=$tekst===''?'Opgeslagen. De mededeling is nu verborgen op de website.':'Opgeslagen. De mededeling staat op de website.';$type='ok';schrijfLog($logBestand,$huidigeGebruiker,'actueel',$tekst===''?'mededeling verborgen':'mededeling bijgewerkt');}else{$melding='Opslaan mislukt.';$type='fout';}}
}
?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Mededeling beheren</title><link rel="stylesheet" href="csp205-actueel-dde466aee5ad.css"></head><body><div class="top"><div class="topin"><a href="./">← Terug naar beheer</a><a href="../index.html" target="_blank" rel="noopener">Bekijk homepage ↗</a></div></div><main class="wrap"><h1>Mededeling</h1><p class="hint">Maximaal 500 tekens. Laat het veld leeg om de strook te verbergen.</p><?php if($melding!==''):?><div class="melding <?=beheerEditorEsc($type)?>"><?=beheerEditorEsc($melding)?></div><?php endif;?><form method="post" class="kaart"><input type="hidden" name="csrf" value="<?=beheerEditorEsc($csrfToken)?>"><textarea name="tekst" maxlength="500"><?=beheerEditorEsc($data['text']??'')?></textarea><button class="btn" type="submit">Opslaan</button></form></main></body></html>
