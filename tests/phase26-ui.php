<?php
$root=dirname(__DIR__);$errors=[];$ok=[];
function t26($cond,string $msg):void{global$errors,$ok;if($cond)$ok[]=$msg;else$errors[]=$msg;}
function t26txt(string $p):string{return is_file($p)?(string)file_get_contents($p):'';}
$css=t26txt($root.'/beheer/ui-2026.css');
t26($css!=='','centrale Beheer UI 2026 stylesheet bestaat');
t26(strpos($css,'--ui-space-6:32px')!==false,'centrale spacing-schaal aanwezig');
t26(strpos($css,'--ui-radius-sm:8px')!==false,'knoppen gebruiken subtiele hoekradius');
t26(strpos($css,'border-color:transparent!important')!==false,'primaire knop heeft geen donkergroene outline');
t26(strpos($css,'--ui-focus:0 0 0 3px')!==false,'toegankelijke focusring aanwezig');
t26(strpos($css,'prefers-reduced-motion:reduce')!==false,'reduced-motion wordt gerespecteerd');
foreach(['index.php','leden.php','leden-import.php','aanmeldingen.php','contributies.php','groep-relaties.php','taken.php','vergaderingen.php','evenementen.php','ledenlabels.php','groepsrollen.php','operationele-taken.php'] as $bestand){$txt=t26txt($root.'/beheer/'.$bestand);t26(strpos($txt,'ui-2026.css')!==false,$bestand.' laadt Beheer UI 2026');}
$comm=t26txt($root.'/beheer/commissies.php');$werk=t26txt($root.'/beheer/werkgroepen.php');
t26(strpos($comm,'ui-2026.css')!==false&&strpos($comm,'ob_start')!==false,'commissies injecteert UI-laag zonder groepscontroller te wijzigen');
t26(strpos($werk,'ui-2026.css')!==false&&strpos($werk,'ob_start')!==false,'werkgroepen injecteert UI-laag zonder groepscontroller te wijzigen');
$content=t26txt($root.'/beheer/content.php');
t26(strpos($content,'beheer/ui-2026.css')!==false,'generieke contenteditor laadt UI 2026');
$dashboard=t26txt($root.'/beheer/index.php');
t26(strpos($dashboard,".module span:first-child:before{content:'* '")!==false,'modulaire beheeritems behouden zichtbare stermarkering');
$aanmeld=t26txt($root.'/beheer/aanmeldingen.php');
t26(strpos($aanmeld,'class="btn primary"')!==false&&strpos($aanmeld,'class="btn danger"')!==false,'aanmeldingen gebruikt gestandaardiseerde actieknoppen');
echo 'Phase 2.6 UI checks: '.count($ok).' OK, '.count($errors)." fout(en)\n";if($errors){foreach($errors as $e)fwrite(STDERR,"FOUT: $e\n");exit(1);}foreach($ok as $m)echo "OK: $m\n";
