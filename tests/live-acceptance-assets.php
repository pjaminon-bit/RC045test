<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function laa(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}
$ht=(string)file_get_contents($root.'/.htaccess');
$placeholder=$root.'/images/template-placeholder.svg';
laa(is_file($placeholder)&&str_contains((string)file_get_contents($placeholder),'Verenigingsfoto nog niet ingesteld'),'neutrale templateplaceholder wordt meegepackageerd');
$devPos=strpos($ht,'RewriteCond %{REQUEST_URI} ^/dev/images/');
$sponsorPos=strpos($ht,'RewriteRule ^images/sponsors/');
laa($devPos!==false&&$sponsorPos!==false&&$devPos<$sponsorPos,'DEV assetfallback staat vóór tenant-uploadgateway');
laa(str_contains($ht,'RewriteCond %{REQUEST_URI} ^/dev/images/')&&str_contains($ht,'RewriteRule ^images/(?!sponsors/)(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9][A-Za-z0-9._-]{0,180}\\.(?:jpe?g|png|webp|gif|svg)$ images/template-placeholder.svg [L,NC]'),'algemene ontbrekende DEV-images vallen lokaal terug, sponsorassets niet');
laa(str_contains($ht,'RewriteRule ^images/sponsors/([A-Za-z0-9][A-Za-z0-9._-]{0,180})$ public-asset.php?scope=sponsors&path=$1 [L,QSA,NE]'),'DEV en tenant sponsorassets lopen rechtstreeks via veilige assetgateway');
laa(!str_contains($ht,'/images/$1 [R=302,L,NE,NC]')&&!str_contains($ht,'^/dev/images/sponsors/'),'DEV sponsorassets gebruiken geen productie-rootredirect of speciale 302-route meer');
laa(str_contains($ht,'RewriteRule ^images/(?!sponsors/|fotoboek/)')&&str_contains($ht,'images/template-placeholder.svg [L,NC]'),'algemene niet-DEV templatebeelden vallen lokaal terug zonder sponsor/fotoboek te maskeren');
laa(str_contains($ht,'(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9][A-Za-z0-9._-]{0,180}'),'assetrewrite gebruikt begrensde padsegmenten en geen vrije traversalcapture');
laa(!str_contains($ht,'RewriteRule ^images/(.+)'), 'assetfallback gebruikt geen onbeperkte path-capture');
echo"Live acceptance assets: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
