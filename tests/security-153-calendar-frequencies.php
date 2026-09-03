<?php
$root=dirname(__DIR__);
require_once $root.'/app/storage/domein-repositories.php';
$errors=[];$ok=[];
function s153(bool $cond,string $msg):void{global$errors,$ok;if($cond)$ok[]=$msg;else$errors[]=$msg;}
function s153eq($actual,$expected,string $msg):void{s153($actual===$expected,$msg.' ['.var_export($actual,true).']');}

// Vaste datumstappen en gewone kalenderdatums.
s153eq(otaakVolgendeUitvoering('dagelijks','2026-01-31'),'2026-02-01','dagelijks +1 dag');
s153eq(otaakVolgendeUitvoering('wekelijks','2026-03-25'),'2026-04-01','wekelijks +7 dagen');
s153eq(otaakVolgendeUitvoering('maandelijks','2026-01-15',15),'2026-02-15','maandelijks behoudt gewone dag');
s153eq(otaakVolgendeUitvoering('maandelijks','2025-12-31',31),'2026-01-31','maand over jaargrens');
s153eq(otaakVolgendeUitvoering('naar_behoefte','2026-01-31'),'','naar behoefte blijft zonder volgende datum');

// Maandultimo mag alleen in de korte doelmaand clippen; het anker blijft staan.
s153eq(otaakVolgendeUitvoering('maandelijks','2024-01-31',31),'2024-02-29','31 januari naar schrikkel-februari');
s153eq(otaakVolgendeUitvoering('maandelijks','2023-01-31',31),'2023-02-28','31 januari naar gewone februari');
s153eq(otaakVolgendeUitvoering('maandelijks','2024-01-30',30),'2024-02-29','30 januari naar laatste februaridag');
s153eq(otaakVolgendeUitvoering('maandelijks','2024-02-29',30),'2024-03-30','30-daganker herstelt na februari');
$t=['frequentie'=>'maandelijks','geschiedenis'=>[],'laatst_uitgevoerd'=>'','volgende_uitvoering'=>''];
foreach ([['2024-01-31','2024-02-29'],['2024-02-29','2024-03-31'],['2024-03-31','2024-04-30'],['2024-04-30','2024-05-31']] as [$uitgevoerd,$volgende]){
    $t=otaakMarkeerUitgevoerd($t,'tester',$uitgevoerd);
    s153eq($t['volgende_uitvoering']??null,$volgende,'opeenvolgende maandultimo '.$uitgevoerd);
}
s153eq($t['kalender_anker_dag']??null,31,'maandultimo houdt expliciet 31-daganker');

// Kwartaal, halfjaar en jaar gebruiken echte kalendermaanden en herstellen ankers.
s153eq(otaakVolgendeUitvoering('per_kwartaal','2024-01-31',31),'2024-04-30','kwartaal +3 kalendermaanden');
s153eq(otaakVolgendeUitvoering('per_kwartaal','2024-04-30',31),'2024-07-31','kwartaal herstelt 31-daganker');
s153eq(otaakVolgendeUitvoering('halfjaarlijks','2023-08-31',31),'2024-02-29','halfjaar +6 kalendermaanden');
s153eq(otaakVolgendeUitvoering('halfjaarlijks','2024-02-29',31),'2024-08-31','halfjaar herstelt 31-daganker');
s153eq(otaakVolgendeUitvoering('jaarlijks','2024-02-29',29),'2025-02-28','29 februari clipt jaarlijks');
s153eq(otaakVolgendeUitvoering('jaarlijks','2027-02-28',29),'2028-02-29','29-februarianker keert terug in schrikkeljaar');

// Bestaande records krijgen geen migratiewrite; bij de eerstvolgende uitvoering
// wordt conservatief de vorige echte uitvoerdag als anker overgenomen.
$legacy=['frequentie'=>'maandelijks','geschiedenis'=>[['datum'=>'2024-01-31','door'=>'legacy']],'laatst_uitgevoerd'=>'2024-01-31','volgende_uitvoering'=>'2024-03-01'];
$oudeHistorie=$legacy['geschiedenis'];
$legacy=otaakMarkeerUitgevoerd($legacy,'tester','2024-02-29');
s153eq($legacy['kalender_anker_dag']??null,31,'legacy record krijgt gecontroleerd anker uit vorige uitvoering');
s153eq($legacy['volgende_uitvoering']??null,'2024-03-31','legacy record drijft daarna niet verder');
s153(($legacy['geschiedenis'][1]??null)===$oudeHistorie[0],'historische uitvoering blijft inhoudelijk intact');
$jaar=['frequentie'=>'jaarlijks','geschiedenis'=>[],'laatst_uitgevoerd'=>'2024-02-29','volgende_uitvoering'=>'2025-03-01'];
foreach ([['2025-02-28','2026-02-28'],['2026-02-28','2027-02-28'],['2027-02-28','2028-02-29']] as [$uitgevoerd,$volgende]){
    $jaar=otaakMarkeerUitgevoerd($jaar,'tester',$uitgevoerd);
    s153eq($jaar['volgende_uitvoering']??null,$volgende,'opeenvolgende jaarcyclus '.$uitgevoerd);
}
s153eq($jaar['kalender_anker_dag']??null,29,'legacy schrikkeldaganker blijft 29');

// Date-only contract is niet afhankelijk van locale timezone/DST.
$tz=date_default_timezone_get();date_default_timezone_set('Europe/Amsterdam');$ams=otaakVolgendeUitvoering('wekelijks','2026-03-25');date_default_timezone_set('America/New_York');$ny=otaakVolgendeUitvoering('wekelijks','2026-03-25');date_default_timezone_set($tz);
s153eq($ams,'2026-04-01','Europese DST-overgang verandert datumcontract niet');
s153eq($ny,$ams,'server-timezone verandert datumcontract niet');

// Ongeldige waarden moeten sluiten, niet naar maandelijks degraderen.
$thrown=false;try{otaakVolgendeUitvoering('onbekend','2026-01-01');}catch(InvalidArgumentException $e){$thrown=true;}s153($thrown,'onbekende frequentie faalt gesloten bij planning');
$ongeldig=['frequentie'=>'onbekend','geschiedenis'=>[],'laatst_uitgevoerd'=>'','volgende_uitvoering'=>''];$voor=$ongeldig;$thrown=false;try{otaakMarkeerUitgevoerd($ongeldig,'tester','2026-01-01');}catch(InvalidArgumentException $e){$thrown=true;}s153($thrown&&$ongeldig===$voor,'ongeldige frequentie registreert geen uitvoering');
$thrown=false;try{otaakNormaliseer(['frequentie'=>'onbekend']);}catch(InvalidArgumentException $e){$thrown=true;}s153($thrown,'ongeldige invoer wordt niet stil maandelijks');
s153eq(otaakNormaliseer([])['frequentie']??null,'maandelijks','interne default voor nieuw record blijft compatibel');

// Gewone edits wijzigen historie/planning niet; expliciete frequentiewijziging
// reset alleen nieuwe ankermetadata en laat de al opgeslagen volgende datum staan.
$b=['id'=>'otaak_b','nummer'=>7,'omschrijving'=>'B','toelichting'=>'x','frequentie'=>'maandelijks','zichtbaarheid'=>'leden','toegewezen_aan'=>'lid_x','actief'=>true,'laatst_uitgevoerd'=>'2024-01-31','laatst_uitgevoerd_door'=>'tester','volgende_uitvoering'=>'2024-02-29','kalender_anker_dag'=>31,'geschiedenis'=>[['datum'=>'2024-01-31','door'=>'tester']],'aangemaakt'=>'2024-01-01T00:00:00+00:00','aangemaakt_door'=>'tester'];
$edit=otaakNormaliseer(['omschrijving'=>'B2','frequentie'=>'maandelijks','actief'=>true],$b);
s153eq($edit['volgende_uitvoering']??null,$b['volgende_uitvoering'],'gewone edit herschrijft volgende datum niet');s153eq($edit['geschiedenis']??null,$b['geschiedenis'],'gewone edit herschrijft historie niet');s153eq($edit['kalender_anker_dag']??null,31,'gewone edit bewaart anker');
$wissel=otaakNormaliseer(['frequentie'=>'per_kwartaal','actief'=>true],$b);s153(!array_key_exists('kalender_anker_dag',$wissel),'expliciete frequentiewijziging reset anker');s153eq($wissel['volgende_uitvoering']??null,$b['volgende_uitvoering'],'frequentiewijziging herschrijft bestaande volgende datum niet');

// JSON fallback bewaart de nieuwe ankermetadata zonder apart schema.
$tmp=sys_get_temp_dir().'/rc045test-153-json-'.bin2hex(random_bytes(4)).'.php';try{$payload=['volgnummer'=>1,'taken'=>[['id'=>'otaak_json','frequentie'=>'maandelijks','kalender_anker_dag'=>31,'volgende_uitvoering'=>'2024-03-31']]];s153(repoPhpJsonSchrijf($tmp,OTAKEN_VOORLOOP,$payload,null,false),'JSON fallback write slaagt');$raw=(string)file_get_contents($tmp);$round=json_decode(substr($raw,strlen(OTAKEN_VOORLOOP)),true);s153(is_array($round)&&($round['taken'][0]['kalender_anker_dag']??null)===31&&($round['taken'][0]['volgende_uitvoering']??'')==='2024-03-31','JSON roundtrip bewaart anker en datum');}finally{@unlink($tmp);}

// Source-/UI-contract: kalenderfrequenties mogen niet terugvallen op vaste dagen.
$bron=(string)file_get_contents($root.'/operationele-taken-opslag.php');s153(strpos($bron,'otaakFrequentieDagen')===false,'oude vaste-dagenhelper is verwijderd');foreach(["'maandelijks'   => 30","'per_kwartaal'  => 91","'halfjaarlijks' => 182","'jaarlijks'     => 365"] as $verboden)s153(strpos($bron,$verboden)===false,'verboden oude dagensemantiek afwezig: '.$verboden);s153(strpos($bron,"'maandelijks'   => 1")!==false&&strpos($bron,"'per_kwartaal'  => 3")!==false&&strpos($bron,"'halfjaarlijks' => 6")!==false&&strpos($bron,"'jaarlijks'     => 12")!==false,'1/3/6/12 kalendermaanden liggen vast');s153(strpos($bron,'kalender_anker_dag')!==false&&strpos($bron,'checkdate(')!==false,'anker en geldige-doelmaandcontract liggen vast');
$beheer=(string)file_get_contents($root.'/beheer/operationele-taken.php');s153(strpos($beheer,'$_POST[\'frequentie\']??\'\'')!==false,'beheer heeft geen stille maandelijks-fallback voor ontbrekende frequentie');s153(strpos($beheer,'catch(InvalidArgumentException')!==false||strpos($beheer,'catch (InvalidArgumentException')!==false,'beheer vangt ongeldige planning gecontroleerd af');s153(strpos($beheer,'Ongeldige frequentie — kies opnieuw')!==false,'beheer maakt corrupte opgeslagen frequentie zichtbaar en herstelbaar');
$portaal=(string)file_get_contents($root.'/leden/index.php');s153(strpos($portaal,'otaakVolgendeUitvoering(')===false,'ledenweergave interpreteert kalenderfrequentie niet zelfstandig');

echo 'Security #153 calendar frequency checks: '.count($ok).' OK, '.count($errors)." fout(en)\n";if($errors){foreach($errors as $e)fwrite(STDERR,"FOUT: $e\n");exit(1);}foreach($ok as $m)echo "OK: $m\n";
