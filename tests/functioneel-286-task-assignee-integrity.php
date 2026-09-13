<?php
$root=dirname(__DIR__);
$ok=0;$fout=0;
function f286(bool $conditie,string $label): void{global $ok,$fout;if($conditie){$ok++;echo "OK: {$label}\n";return;}$fout++;fwrite(STDERR,"FOUT: {$label}\n");}
require_once $root.'/app/leden/service.php';

$leden=[
 ['id'=>'actief_a','voornaam'=>'Actief','achternaam'=>'A','gearchiveerd_op'=>''],
 ['id'=>'actief_b','voornaam'=>'Actief','achternaam'=>'B','gearchiveerd_op'=>''],
 ['id'=>'archief','voornaam'=>'Historisch','achternaam'=>'Lid','gearchiveerd_op'=>'2026-09-13T10:00:00+02:00'],
];

$r=ledenServiceTaakToewijzingValideer('actief_a','',$leden);
f286($r['geldig']===true&&$r['id']==='actief_a'&&$r['historisch']===false,'nieuwe toewijzing aan actief lid is geldig');

$r=ledenServiceTaakToewijzingValideer('archief','',$leden);
f286($r['geldig']===false,'nieuwe toewijzing aan gearchiveerd lid wordt geweigerd');

$r=ledenServiceTaakToewijzingValideer('bestaat_niet','',$leden);
f286($r['geldig']===false,'nieuwe toewijzing aan onbekend lid wordt geweigerd');

$r=ledenServiceTaakToewijzingValideer('actief_a','actief_a',$leden);
f286($r['geldig']===true&&$r['id']==='actief_a'&&$r['historisch']===false,'ongewijzigde actieve toewijzing blijft geldig');

$r=ledenServiceTaakToewijzingValideer('archief','archief',$leden);
f286($r['geldig']===true&&$r['id']==='archief'&&$r['historisch']===true,'ongewijzigde gearchiveerde toewijzing blijft als historie geldig');

$r=ledenServiceTaakToewijzingValideer('','actief_a',$leden);
f286($r['geldig']===true&&$r['id']==='','expliciet Niemand wist actieve toewijzing');

$r=ledenServiceTaakToewijzingValideer('','archief',$leden);
f286($r['geldig']===true&&$r['id']==='','expliciet Niemand wist historische toewijzing');

$r=ledenServiceTaakToewijzingValideer('actief_b','archief',$leden);
f286($r['geldig']===true&&$r['id']==='actief_b','historische assignee kan naar actief lid worden gewijzigd');

$r=ledenServiceTaakToewijzingValideer('archief','actief_a',$leden);
f286($r['geldig']===false,'actieve assignee kan niet naar gearchiveerd lid worden gewijzigd');

$r=ledenServiceTaakToewijzingValideer(str_repeat('x',41),'',$leden);
f286($r['geldig']===false,'te lange assignee-id wordt afgewezen vóór truncatie');

$r=ledenServiceTaakToewijzingValideer('bestaat_niet','bestaat_niet',$leden);
f286($r['geldig']===false,'dangling bestaande assignee wordt niet als historie behouden');

f286(ledenServiceLidOpId($leden,'archief')['id']==='archief','centrale lidlookup vindt historische assignee');
f286(ledenServiceLidOpId($leden,'bestaat_niet')===null,'centrale lidlookup retourneert null voor onbekend lid');

$takenBron=(string)file_get_contents($root.'/beheer/taken.php');
$operationeelBron=(string)file_get_contents($root.'/beheer/operationele-taken.php');
f286(str_contains($takenBron,'ledenServiceTaakToewijzingValideer('),'reguliere taken gebruiken centrale assigneevalidatie');
f286(str_contains($operationeelBron,'ledenServiceTaakToewijzingValideer('),'operationele taken gebruiken centrale assigneevalidatie');
f286(str_contains($takenBron,'$huidigeToegewezenHistorisch'),'reguliere taakedit bewaart historische assignee in de UI');
f286(str_contains($operationeelBron,'$huidigeToegewezenHistorisch'),'operationele taakedit bewaart historische assignee in de UI');
f286(substr_count($takenBron,'(gearchiveerd)')>=1,'reguliere taakedit labelt historische assignee');
f286(substr_count($operationeelBron,'(gearchiveerd)')>=1,'operationele taakedit labelt historische assignee');
f286(!str_contains($takenBron,"array_column(\$ledenData['leden'],'id')"),'reguliere taken valideren niet langer tegen alle lid-IDs');
f286(!str_contains($operationeelBron,"\$geldige=array_column(\$actieveLeden,'id')"),'operationele taken hebben geen afwijkende lokale assigneevalidator meer');

echo "Functioneel #286 taaktoewijzingsintegriteit: {$ok} OK, {$fout} fout(en)\n";
exit($fout===0?0:1);
