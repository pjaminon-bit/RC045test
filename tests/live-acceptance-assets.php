<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function laa(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}
$ht=(string)file_get_contents($root.'/.htaccess');
$pc=(string)file_get_contents($root.'/public-content.php');
$svg=$root.'/images/template-placeholder.svg';
laa(is_file($svg)&&str_contains((string)file_get_contents($svg),'Verenigingsfoto nog niet ingesteld'),'neutrale SVG-templateplaceholder wordt meegepackageerd');
$devFallbackPos=strpos($ht,'RewriteCond %{REQUEST_URI} ^/dev/images/');
$genericSponsorPos=strrpos($ht,'RewriteRule ^images/sponsors/');
laa($devFallbackPos!==false&&$genericSponsorPos!==false&&$devFallbackPos<$genericSponsorPos,'algemene DEV imagefallback staat vóór generieke tenant-uploadgateway');
laa(!str_contains($ht,'RewriteCond %{REQUEST_URI} ^/dev/images/sponsors/'),'DEV heeft geen speciale Apache-route meer voor ontbrekende sponsoruploads');
laa(!str_contains($ht,'dev_placeholder=1')&&!str_contains($ht,'images/template-placeholder.png [R=302')&&!str_contains($ht,'images/template-placeholder.png [L,NC]'),'legacy DEV sponsorplaceholderroutes zijn verwijderd');
laa(str_contains($ht,'RewriteRule ^images/(?!sponsors/)(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9][A-Za-z0-9._-]{0,180}\\.(?:jpe?g|png|webp|gif|svg)$ images/template-placeholder.svg [L,NC]'),'algemene ontbrekende DEV-images blijven lokaal op SVG terugvallen zonder sponsors te maskeren');
laa(str_contains($ht,'RewriteRule ^images/sponsors/([A-Za-z0-9][A-Za-z0-9._-]{0,180})$ public-asset.php?scope=sponsors&path=$1 [L,QSA,NE]'),'sponsorassets behouden tenantbewuste fail-closed assetgateway');
laa(str_contains($pc,"\$sleutel === 'sponsors'")&&str_contains($pc,"preg_match('#^/dev(?:/|$)#', \$requestPad) === 1")&&str_contains($pc,"\$data['items'] = [];"),'DEV publieke content onderdrukt tenant-sponsoritems vóór browserrendering');
laa(str_contains($pc,'$externPad === null')&&str_contains($pc,'!$configVerplicht'),'DEV sponsorfilter geldt niet voor externe tenantconfiguraties');
laa(str_contains($ht,'RewriteRule ^images/(?!sponsors/|fotoboek/)')&&str_contains($ht,'images/template-placeholder.svg [L,NC]'),'algemene niet-DEV templatebeelden vallen lokaal terug zonder sponsor/fotoboek te maskeren');
laa(str_contains($ht,'(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9][A-Za-z0-9._-]{0,180}'),'assetrewrite gebruikt begrensde padsegmenten en geen vrije traversalcapture');
laa(!str_contains($ht,'RewriteRule ^images/(.+)'), 'assetfallback gebruikt geen onbeperkte path-capture');
echo"Live acceptance assets: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
