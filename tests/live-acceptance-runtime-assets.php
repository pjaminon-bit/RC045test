<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function a(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}
$ht=(string)file_get_contents($root.'/.htaccess');
$pc=(string)file_get_contents($root.'/public-content.php');
$leden=(string)file_get_contents($root.'/leden/index.php');
a(str_contains($ht,'^/dev/images/')&&str_contains($ht,'images/template-placeholder.svg'),'DEV heeft een lokale fallback voor ontbrekende beeldassets');
a(!str_contains($ht,'RewriteRule ^images/((?:[A-Za-z0-9_-]+/)*[A-Za-z0-9][A-Za-z0-9._-]{0,180}\\.(?:jpe?g|png|webp|gif|svg))$ /images/$1'),'DEV beeldfallback is niet meer afhankelijk van productie-rootassets');
a(str_contains($pc,"standalone override is ongeldig")&&substr_count($pc,"http_response_code(204);")>=2,'ongeldige standalone override degradeert naar 204 en wordt gelogd');
a(str_contains($leden,'.wrap button{min-height:44px!important'),'leden-loginbutton heeft minimaal 44px hoogte');
echo"Live acceptance runtime/assets: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
