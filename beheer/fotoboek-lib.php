<?php
// ============================================================
// Modulaire Fotoboek-hulpfuncties
// ============================================================

function fbEsc($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function fbKort($v,int $max): string { $t=trim(is_scalar($v)?(string)$v:''); return function_exists('mb_substr')?mb_substr($t,0,$max,'UTF-8'):substr($t,0,$max); }
function fbDatumIso($v): string {
    $v=trim((string)$v);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$v,$m) && checkdate((int)$m[2],(int)$m[3],(int)$m[1])) return $v;
    if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/',$v,$m) && checkdate((int)$m[2],(int)$m[1],(int)$m[3])) return sprintf('%04d-%02d-%02d',(int)$m[3],(int)$m[2],(int)$m[1]);
    return '';
}
function fbMaakSlug($tekst): string {
    $tekst=trim((string)$tekst);
    if(function_exists('iconv')){ $v=@iconv('UTF-8','ASCII//TRANSLIT',$tekst); if($v!==false)$tekst=$v; }
    $tekst=strtolower($tekst); $tekst=preg_replace('/[^a-z0-9]+/','-',$tekst); $tekst=trim((string)$tekst,'-');
    return $tekst===''?'album':$tekst;
}
function fbUniekeSlug(string $basis,array $slugs): string { $s=$basis;$i=2;while(in_array($s,$slugs,true)){$s=$basis.'-'.$i++;}return $s; }
function fbLees(string $pad): array {
    $GLOBALS['fbLeesStatus']=['ok'=>false,'code'=>'onbekend','pad'=>$pad,'melding'=>''];
    if(!is_file($pad)) {
        $GLOBALS['fbLeesStatus']=['ok'=>false,'code'=>'ontbreekt','pad'=>$pad,'melding'=>'fotoboek.json bestaat niet op deze installatie.'];
        return ['albums'=>[]];
    }
    $ruw=@file_get_contents($pad);
    if($ruw===false){
        $GLOBALS['fbLeesStatus']=['ok'=>false,'code'=>'onleesbaar','pad'=>$pad,'melding'=>'fotoboek.json bestaat wel, maar kon niet worden gelezen.'];
        return ['albums'=>[]];
    }
    $d=json_decode($ruw,true);
    if(json_last_error()!==JSON_ERROR_NONE||!is_array($d)){
        $GLOBALS['fbLeesStatus']=['ok'=>false,'code'=>'ongeldige_json','pad'=>$pad,'melding'=>'fotoboek.json bevat ongeldige JSON: '.json_last_error_msg()];
        return ['albums'=>[]];
    }
    // Compatibiliteit: een zeer oude versie kan rechtstreeks een lijst albums
    // bevatten in plaats van {"albums": [...]}. Alleen herkennen wanneer het
    // echt een numerieke lijst is; andere structuren worden niet gegokt.
    $isLijst=function_exists('array_is_list')?array_is_list($d):(array_keys($d)===range(0,count($d)-1));
    if(!isset($d['albums'])&&$isLijst){$d=['albums'=>$d];$formaat='legacy_lijst';}
    else $formaat='object';
    if(!isset($d['albums'])||!is_array($d['albums'])){
        $GLOBALS['fbLeesStatus']=['ok'=>false,'code'=>'onbekend_formaat','pad'=>$pad,'melding'=>'fotoboek.json is leesbaar, maar bevat geen geldige albums-lijst.'];
        return ['albums'=>[]];
    }
    foreach($d['albums'] as $i=>&$a){
        if(!is_array($a)){$a=[];}
        if(!isset($a['volgorde']))$a['volgorde']=$i;
        if(!isset($a['title'])||!is_array($a['title']))$a['title']=['nl'=>'','en'=>'','de'=>''];
        foreach(['nl','en','de'] as $t) if(!isset($a['title'][$t]))$a['title'][$t]='';
        if(!isset($a['beschrijving'])||!is_array($a['beschrijving']))$a['beschrijving']=['nl'=>'','en'=>'','de'=>''];
        foreach(['nl','en','de'] as $t) if(!isset($a['beschrijving'][$t]))$a['beschrijving'][$t]='';
        if(!isset($a['photos'])||!is_array($a['photos']))$a['photos']=[];
        foreach($a['photos'] as &$p){
            if(!is_array($p))$p=[];
            if(!isset($p['type']))$p['type']='photo';
            if(!isset($p['caption'])||!is_array($p['caption']))$p['caption']=['nl'=>'','en'=>'','de'=>''];
            foreach(['nl','en','de'] as $t) if(!isset($p['caption'][$t]))$p['caption'][$t]='';
            if(($p['type']??'photo')!=='video' && !isset($p['watermerk']))$p['watermerk']=false;
        }
        unset($p);
    }
    unset($a);
    usort($d['albums'],static fn($a,$b)=>(float)($a['volgorde']??0)<=>(float)($b['volgorde']??0));
    $GLOBALS['fbLeesStatus']=['ok'=>true,'code'=>$formaat,'pad'=>$pad,'melding'=>count($d['albums']).' album(s) gelezen.'];
    return $d;
}
function fbSchrijf(string $pad,array $data): bool {
    global $dataBackupMap,$dataBackupBewaardagen,$dataBackupMaxPerBestand;
    if(function_exists('maakDataBackup'))maakDataBackup($pad,$dataBackupMap,$dataBackupBewaardagen,$dataBackupMaxPerBestand);
    if(!is_dir(dirname($pad))&&!@mkdir(dirname($pad),0755,true))return false;
    $j=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    if($j===false)return false;
    try{$suffix=bin2hex(random_bytes(4));}catch(Throwable $e){$suffix=str_replace('.','',(string)microtime(true));}
    $tmp=$pad.'.tmp.'.$suffix;
    if(file_put_contents($tmp,$j,LOCK_EX)===false)return false;
    if(!@rename($tmp,$pad)){@unlink($tmp);return false;}
    return true;
}
function fbSchaalAf($bron,int $b,int $h,int $max){
    if($b<1||$h<1)return false;
    $factor=$b>$max?$max/$b:1;
    $nb=max(1,(int)round($b*$factor));$nh=max(1,(int)round($h*$factor));
    $nieuw=imagecreatetruecolor($nb,$nh);
    $wit=imagecolorallocate($nieuw,255,255,255);imagefill($nieuw,0,0,$wit);
    imagecopyresampled($nieuw,$bron,0,0,0,0,$nb,$nh,$b,$h);
    return $nieuw;
}
function fbWatermerk($img,string $logoPad,string $tekst='rc045.nl'): void {
    $b=imagesx($img);$h=imagesy($img);$lh=(int)max(18,min(36,round($h*.035)));$pad=(int)max(7,round($lh*.45));
    $logo=null;
    if(is_file($logoPad)&&($lb=@imagecreatefrompng($logoPad))){$ow=imagesx($lb);$oh=imagesy($lb);if($oh>0){$lw=(int)round($lh*($ow/$oh));$logo=imagecreatetruecolor($lw,$lh);imagealphablending($logo,false);imagesavealpha($logo,true);$tr=imagecolorallocatealpha($logo,0,0,0,127);imagefill($logo,0,0,$tr);imagealphablending($logo,true);imagecopyresampled($logo,$lb,0,0,0,0,$lw,$lh,$ow,$oh);}imagedestroy($lb);}
    $font=3;$tw=imagefontwidth($font)*strlen($tekst);$th=imagefontheight($font);$lw=$logo?imagesx($logo):0;$gap=$logo?(int)round($pad*.6):0;$vw=$lw+$gap+$tw+$pad*2;$vh=max($lh,$th)+$pad;
    $x2=$b-$pad;$y2=$h-$pad;$x1=(int)round($x2-$vw);$y1=(int)round($y2-$vh);imagealphablending($img,true);$vlak=imagecolorallocatealpha($img,20,24,15,55);imagefilledrectangle($img,$x1,$y1,$x2,$y2,$vlak);$my=(int)round(($y1+$y2)/2);$x=$x1+$pad;
    if($logo){imagecopy($img,$logo,$x,$my-(int)round(imagesy($logo)/2),0,0,imagesx($logo),imagesy($logo));$x+=imagesx($logo)+$gap;imagedestroy($logo);} $wit=imagecolorallocate($img,255,255,255);imagestring($img,$font,$x,$my-(int)round($th/2),$tekst,$wit);
}
function fbVerwerkFoto(string $tmp,string $vol,string $thumb,bool $watermerk,string $logo,int $maxVol,int $maxThumb): array {
    $info=@getimagesize($tmp); if($info===false)return ['ok'=>false,'fout'=>'bestand is geen geldige afbeelding.'];
    $pixelBreedte=(int)($info[0]??0);$pixelHoogte=(int)($info[1]??0);
    if($pixelBreedte<1||$pixelHoogte<1||$pixelBreedte>16000||$pixelHoogte>16000||($pixelBreedte*$pixelHoogte)>60000000){
        return ['ok'=>false,'fout'=>'afbeelding heeft te veel pixels voor veilige verwerking (maximaal 60 megapixel).'];
    }
    $bron=false;
    if($info[2]===IMAGETYPE_JPEG)$bron=@imagecreatefromjpeg($tmp);
    elseif($info[2]===IMAGETYPE_PNG)$bron=@imagecreatefrompng($tmp);
    elseif($info[2]===IMAGETYPE_WEBP&&function_exists('imagecreatefromwebp'))$bron=@imagecreatefromwebp($tmp);
    if(!$bron)return ['ok'=>false,'fout'=>'alleen JPG, PNG of WEBP toegestaan, of bestand kon niet worden geopend.'];
    if($info[2]===IMAGETYPE_JPEG&&function_exists('exif_read_data')){ $ex=@exif_read_data($tmp);$o=(int)($ex['Orientation']??0);$hoek=$o===3?180:($o===6?-90:($o===8?90:0));if($hoek){$r=imagerotate($bron,$hoek,0);if($r){imagedestroy($bron);$bron=$r;}} }
    $b=imagesx($bron);$h=imagesy($bron);$full=fbSchaalAf($bron,$b,$h,$maxVol);if(!$full){imagedestroy($bron);return ['ok'=>false,'fout'=>'afbeelding kon niet worden verkleind.'];}
    if($watermerk)fbWatermerk($full,$logo);
    $okFull=@imagejpeg($full,$vol,82);$ow=imagesx($full);$oh=imagesy($full);imagedestroy($full);
    $th=fbSchaalAf($bron,$b,$h,$maxThumb);$okThumb=$th?@imagejpeg($th,$thumb,78):false;if($th)imagedestroy($th);imagedestroy($bron);
    if(!$okFull||!$okThumb){@unlink($vol);@unlink($thumb);return ['ok'=>false,'fout'=>'afbeelding kon niet op de server worden opgeslagen.'];}
    return ['ok'=>true,'width'=>$ow,'height'=>$oh];
}
function fbWatermerkBestaand(string $pad,string $logo): bool { $info=@getimagesize($pad);if($info===false||$info[2]!==IMAGETYPE_JPEG)return false;$img=@imagecreatefromjpeg($pad);if(!$img)return false;fbWatermerk($img,$logo);$ok=@imagejpeg($img,$pad,82);imagedestroy($img);return (bool)$ok; }
function fbVerwijderBestanden(string $albumPad,array $foto): void {
    $file=basename((string)($foto['file']??''));if($file!==''){@unlink($albumPad.'/'.$file);@unlink($albumPad.'/thumbs/'.$file);}
    $poster=basename((string)($foto['poster']??''));if($poster!==''){@unlink($albumPad.'/'.$poster);@unlink($albumPad.'/thumbs/'.$poster);}
}
function fbVerwijderMap(string $pad): void { if(!is_dir($pad))return;foreach((array)@scandir($pad) as $i){if($i==='.'||$i==='..')continue;$p=$pad.'/'.$i;if(is_dir($p))fbVerwijderMap($p);else @unlink($p);}@rmdir($pad); }
