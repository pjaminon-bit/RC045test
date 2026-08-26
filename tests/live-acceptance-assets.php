<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function laa(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}
$ht=(string)file_get_contents($root.'/.htaccess');
$svg=$root.'/images/template-placeholder.svg';
$png=$root.'/images/template-placeholder.png';
laa(is_file($svg)&&str_contains((string)file_get_contents($svg),'Verenigingsfoto nog niet ingesteld'),'neutrale SVG-templateplaceholder wordt meegepackageerd');
$pngInfo=is_file($png)?@getimagesize($png):false;
laa(is_array($pngInfo)&&($pngInfo['mime']??'')==='image/png','DEV sponsorplaceholder is een echte PNG');
$devSponsorPos=strpos($ht,'RewriteCond %{REQUEST_URI} ^/dev/images/sponsors/');
$devFallbackPos=strpos($ht,'RewriteCond %{REQUEST_URI} ^/dev/images/');
$genericSponsorPos=strrpos($ht,'RewriteRule ^images/sponsors/');
laa($devSponsorPos!==false&&$devFallbackPos!==false&&$genericSponsorPos!==false&&$devSponsorPos<=$devFallbackPos&&$devFallbackPos<$genericSponsorPos,'DEV sponsorredirect en algemene fallback staan vóór generieke tenant-uploadgateway');
laa(str_contains($ht,'RewriteRule ^images/sponsors/[A-Za-z0-9][A-Za-z0-9._-]{0,180}\\.(?:jpe?g|png|webp)$ images/template-placeholder.png [R=302,L,NC,NE,QSD]'),'ontbrekende DEV sponsorassets redirecten cachebuster-vrij naar één statische PNG');
laa(str_contains($ht,'RewriteCond %{REQUEST_FILENAME} !-f'),'DEV sponsorredirect activeert alleen voor fysiek ontbrekende bestanden');
laa(str_contains($ht,'RewriteRule ^images/(?!sponsors/)(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9][A-Za-z0-9._-]{0,180}\\.(?:jpe?g|png|webp|gif|svg)$ images/template-placeholder.svg [L,NC]'),'algemene ontbrekende DEV-images blijven de SVG-placeholder gebruiken');
laa(str_contains($ht,'RewriteRule ^images/sponsors/([A-Za-z0-9][A-Za-z0-9._-]{0,180})$ public-asset.php?scope=sponsors&path=$1 [L,QSA,NE]'),'niet-DEV sponsorassets behouden tenantbewuste assetgateway');
laa(!str_contains($ht,'dev_placeholder=1')&&!str_contains($ht,'images/template-placeholder.png [L,NC]')&&!str_contains($ht,'/images/$1 [R=302,L,NE,NC]'),'DEV sponsorassets gebruiken geen gatewaymarker, interne PNG-rewrite of legacy redirect');
laa(str_contains($ht,'RewriteRule ^images/(?!sponsors/|fotoboek/)')&&str_contains($ht,'images/template-placeholder.svg [L,NC]'),'algemene niet-DEV templatebeelden vallen lokaal terug zonder sponsor/fotoboek te maskeren');
laa(str_contains($ht,'(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9][A-Za-z0-9._-]{0,180}'),'assetrewrite gebruikt begrensde padsegmenten en geen vrije traversalcapture');
laa(!str_contains($ht,'RewriteRule ^images/(.+)'), 'assetfallback gebruikt geen onbeperkte path-capture');
echo"Live acceptance assets: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
