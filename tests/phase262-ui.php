<?php
$root=dirname(__DIR__);$errors=[];$ok=[];
function t262($cond,string $msg):void{global$errors,$ok;if($cond)$ok[]=$msg;else$errors[]=$msg;}
function t262txt(string $p):string{return is_file($p)?(string)file_get_contents($p):'';}
$css=t262txt($root.'/beheer/ui-2026.css');
$dash=t262txt($root.'/beheer/index.php');
t262(strpos($dash,'class="beheer-dashboard"')!==false,'dashboard heeft expliciete UI-scope');
t262(strpos($css,'--ui-shadow-hover:')!==false,'interactieve elevation token aanwezig');
t262(strpos($css,'.beheer-dashboard .groep{background:transparent!important;border:0!important')!==false,'dashboardcategorieen zijn geen geneste zware cards meer');
t262(strpos($css,'.moduleblok:hover{')!==false&&strpos($css,'translateY(-1px)')!==false,'modulecards hebben subtiele hoverfeedback');
t262(strpos($css,'.moduleblok{')!==false&&strpos($css,'border-left:1px solid var(--ui-line)!important')!==false,'oude zware groene linkeraccent is verwijderd');
t262(strpos($css,'.btn:active')!==false,'knoppen hebben duidelijke pressed state');
t262(strpos($css,'button:disabled')!==false,'disabled state is centraal gedefinieerd');
t262(strpos($css,'input::placeholder')!==false,'placeholderstijl is centraal gedefinieerd');
t262(strpos($css,'tbody tr:hover')!==false,'tabellen hebben rustige rijfeedback');
t262(strpos($css,'.tablewrap')!==false,'brede tabellen hebben responsieve wrapperstijl');
t262(strpos($css,'.stat strong')!==false,'dashboardachtige statistiekhierarchie aanwezig');
t262(strpos($css,'.acties{background:rgba')!==false,'sticky editoracties sluiten aan op UI 2026');
t262(strpos($css,'-webkit-font-smoothing:antialiased')!==false,'typografie is browsermatig verfijnd');
t262(strpos($css,'@media(max-width:620px)')!==false,'compact mobiel breakpoint aanwezig');
t262(strpos($css,'.beheer-dashboard .top{position:sticky!important}')!==false,'dashboardnavigatie blijft mobiel bereikbaar');
t262(strpos($css,'@media(prefers-reduced-motion:reduce)')!==false,'motion preference blijft gerespecteerd');
echo 'Phase 2.6.2 UI checks: '.count($ok).' OK, '.count($errors)." fout(en)\n";
if($errors){foreach($errors as $e)fwrite(STDERR,"FOUT: $e\n");exit(1);}foreach($ok as $m)echo "OK: $m\n";
