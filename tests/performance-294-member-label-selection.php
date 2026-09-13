<?php
$root=dirname(__DIR__);
require_once $root.'/app/leden/labels.php';
$ok=0;$fout=0;
function p294(bool $conditie,string $label): void{global $ok,$fout;if($conditie){$ok++;echo "OK: {$label}\n";return;}$fout++;fwrite(STDERR,"FOUT: {$label}\n");}

$set=labelsWaardenSet(['lid-a','lid-b','lid-a','',null,['ongeldig']]);
p294(isset($set['lid-a'])&&isset($set['lid-b']),'waarden-set bewaart geldige string-ID lookup');
p294(count($set)===2,'waarden-set dedupliceert en negeert lege/niet-scalare waarden');

$doc=['toewijzingen'=>['lid-a'=>['jeugd','trainer'],'lid-b'=>['trainer']]];
$sets=labelsToewijzingSets($doc);
p294(isset($sets['lid-a']['jeugd'])&&isset($sets['lid-a']['trainer']),'documenttoewijzingen worden eenmaal als sets geïndexeerd');
p294(isset($sets['lid-b']['trainer'])&&!isset($sets['lid-b']['jeugd']),'setsemantiek bewaart checked-state per lid');

$perLid=labelsSelectiesPerLid(
 ['jeugd','trainer'],
 ['jeugd'=>['lid-b','lid-a','lid-b','onbekend'],'trainer'=>['lid-a']],
 ['lid-a'=>true,'lid-b'=>true]
);
p294(($perLid['lid-a']??[])===['jeugd','trainer'],'POST-selecties behouden labelvolgorde voor lid-a');
p294(($perLid['lid-b']??[])===['jeugd'],'dubbele lidselecties worden voor lookup gededupliceerd');
p294(!isset($perLid['onbekend']),'onbekende lid-ID wordt niet overgenomen');

$bron=(string)file_get_contents($root.'/beheer/ledenlabels.php');
p294(str_contains($bron,'$toewijzingSets=labelsToewijzingSets($doc);'),'renderpad bouwt toewijzingssets eenmaal op');
p294(str_contains($bron,"isset(\$toewijzingSets[\$lidId][\$id])"),'checkbox gebruikt O(1) setlookup');
p294(str_contains($bron,'$selectiesPerLid=labelsSelectiesPerLid('),'POST-pad draait selectie eenmaal om naar lid-index');
p294(!str_contains($bron,'in_array('),'ledenlabels-beheer bevat geen lineaire in_array membershipcheck meer');

echo "Performance #294 ledenlabels-selecties: {$ok} OK, {$fout} fout(en)\n";
exit($fout===0?0:1);
