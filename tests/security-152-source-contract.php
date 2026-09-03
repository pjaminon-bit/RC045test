<?php
$root=dirname(__DIR__);
function s152sAssert(bool $ok,string $message):void{if(!$ok){fwrite(STDERR,"FOUT: {$message}\n");exit(1);}}
$contracten=[
 'beheer/taken.php'=>['dataIntegriteitVerwijderTaak','taak_verwijderd'],
 'beheer/vergaderingen.php'=>['dataIntegriteitVerwijderVergadering','vergadering_verwijderd'],
 'beheer/evenementen.php'=>['dataIntegriteitVerwijderEvenement','evenement_verwijderd'],
];
foreach($contracten as $pad=>[$functie,$log]){
 $src=file_get_contents($root.'/'.$pad);
 s152sAssert(is_string($src),$pad.' niet leesbaar');
 s152sAssert(str_contains($src,"/app/data-integriteit.php'"),$pad.' laadt centrale integriteitsgateway niet');
 s152sAssert(str_contains($src,$functie.'('),$pad.' gebruikt centrale deletegateway niet');
 s152sAssert(str_contains($src,"'{$log}'"),$pad.' verloor bestaand auditlogevent');
}

// Repo-brede guard op de concrete oude controller-deletewriters. Een splice
// telt alleen als bypass wanneer hetzelfde bestand ook de primaire repository
// van dat #152-domein schrijft. Operationele taken gebruiken toevallig ook de
// documentsleutel `taken`, maar hebben een eigen repository en vallen niet
// onder het taak-relatiecontract van finding #152.
$verboden=[
 ["array_splice(\$data['taken']",'repoTakenSchrijf('],
 ["array_splice(\$data['vergaderingen']",'repoVergaderingenSchrijf('],
 ["array_splice(\$data['evenementen']",'repoEvenementenSchrijf('],
];
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
foreach($it as $file){
 if(!$file->isFile()||$file->getExtension()!=='php')continue;
 $pad=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
 if(str_starts_with($pad,'tests/')||str_starts_with($pad,'vendor/'))continue;
 $src=file_get_contents($file->getPathname());if(!is_string($src))continue;
 foreach($verboden as [$splice,$writer]){
  s152sAssert(!(str_contains($src,$splice)&&str_contains($src,$writer)),'directe primaire deletewriter buiten gateway gevonden in '.$pad);
 }
}

$service=file_get_contents($root.'/app/data-integriteit.php');
s152sAssert(is_string($service),'integriteitsservice niet leesbaar');
s152sAssert(str_contains($service,'privateStoreBatchTransactie('),'integriteitsservice gebruikt centrale batchtransactie niet');
s152sAssert(str_contains($service,"['vergaderingen', 'taken', 'groepen']"),'vergaderingdelete bindt niet alle drie stores');
s152sAssert(str_contains($service,'dataIntegriteitHerstelDangling'),'conservatieve repairfunctie ontbreekt');
s152sAssert(!str_contains($service,'groepenSchrijfDocument('),'repair mag groepen niet volledig normaliseren');

echo "security-152-source-contract: OK\n";
