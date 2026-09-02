<?php
// Private fallbackopslag voor ledenlabels/segmenten.
define('LEDENLABELS_VOORLOOP', "<?php exit; ?>\n");
require_once __DIR__ . '/app/data-slot.php';
require_once __DIR__ . '/app/storage/legacy-private-json.php';
function ledenlabelsBestandPad(): string{return __DIR__.'/ledenlabels-data.php';}
function ledenlabelsBackupMap(): string{return __DIR__.'/data-backups';}
function ledenlabelsLeeg(): array{return ['schema'=>1,'labels'=>[],'toewijzingen'=>[],'updated'=>''];}
function ledenlabelsLees(): array{
 $d=legacyPrivateJsonLees(ledenlabelsBestandPad(),'ledenlabels',['labels']);return $d===null?ledenlabelsLeeg():array_replace(ledenlabelsLeeg(),$d);
}
function ledenlabelsMaakBackup(): void{
 $pad=ledenlabelsBestandPad();if(!is_file($pad))return;$map=ledenlabelsBackupMap();if(!is_dir($map))@mkdir($map,0755,true);$stamp=(new DateTimeImmutable('now',new DateTimeZone('Europe/Amsterdam')))->format('Ymd-His-u');@copy($pad,$map.'/ledenlabels-'.$stamp.'.php');
}
