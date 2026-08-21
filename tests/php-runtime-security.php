<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function prs(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}
$ini=$root.'/.user.ini';
prs(is_file($ini),'.user.ini is onderdeel van de deploybare applicatie');
$raw=is_file($ini)?(string)file_get_contents($ini):'';
prs(preg_match('/^\s*expose_php\s*=\s*Off\s*$/mi',$raw)===1,'PHP runtimeversieheader staat expliciet uit');
$ht=(string)file_get_contents($root.'/.htaccess');
prs(str_contains($ht,'Header always unset X-Powered-By'),'Apache verwijdert X-Powered-By aanvullend defense-in-depth');
echo"PHP runtime security: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
