<?php
$root=dirname(__DIR__);
$ok=0;$fout=0;
function p292(bool $conditie,string $label): void{global $ok,$fout;if($conditie){$ok++;echo "OK: {$label}\n";return;}$fout++;fwrite(STDERR,"FOUT: {$label}\n");}

$ids=['lid-a','lid-b','lid-c','lid-b'];
$set=array_fill_keys(array_map('strval',$ids),true);
p292(isset($set['lid-a'])&&isset($set['lid-b'])&&isset($set['lid-c']),'selectieset bewaart alle geldige IDs');
p292(count($set)===3,'selectieset dedupliceert alleen voor lookup zonder opslag te wijzigen');
p292(!isset($set['lid-x']),'onbekende ID is niet geselecteerd');

$eventBron=(string)file_get_contents($root.'/beheer/evenementen.php');
$groepBron=(string)file_get_contents($root.'/beheer/groep-relaties.php');

p292(str_contains($eventBron,'$deelnemerSet=array_fill_keys('),'evenementen bouwen deelnemerlookup eenmaal vóór renderen');
p292(str_contains($eventBron,"isset(\$deelnemerSet[(string)\$l['id']])"),'evenementen gebruiken O(1) deelnemerlookup per lid');
p292(!str_contains($eventBron,"in_array(\$l['id'],(array)(\$e['deelnemers']??[]),true)"),'oude lineaire deelnemercheck is verwijderd');

foreach([
 '$relTakenSet'=>'taken',
 '$relVergaderingenSet'=>'vergaderingen',
 '$relEvenementenSet'=>'evenementen',
] as $variabele=>$label){
 p292(str_contains($groepBron,$variabele.'=array_fill_keys('),'groepsrelaties bouwen '.$label.'-lookup eenmaal vóór renderen');
}
p292(str_contains($groepBron,"isset(\$relTakenSet[(string)\$x['id']])"),'taken gebruiken O(1) selectiecheck');
p292(str_contains($groepBron,"isset(\$relVergaderingenSet[(string)\$x['id']])"),'vergaderingen gebruiken O(1) selectiecheck');
p292(str_contains($groepBron,"isset(\$relEvenementenSet[(string)\$x['id']])"),'evenementenrelaties gebruiken O(1) selectiecheck');
p292(!str_contains($groepBron,"in_array(\$x['id'],\$rel['taken'],true)"),'oude lineaire takencheck is verwijderd');
p292(!str_contains($groepBron,"in_array(\$x['id'],\$rel['vergaderingen'],true)"),'oude lineaire vergaderingencheck is verwijderd');
p292(!str_contains($groepBron,"in_array(\$x['id'],\$rel['evenementen'],true)"),'oude lineaire evenementencheck is verwijderd');

echo "Performance #292 relatie-selectielookups: {$ok} OK, {$fout} fout(en)\n";
exit($fout===0?0:1);
