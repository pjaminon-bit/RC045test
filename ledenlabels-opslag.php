<?php
// Private fallbackopslag voor ledenlabels/segmenten.
define('LEDENLABELS_VOORLOOP', "<?php exit; ?>\n");
require_once __DIR__ . '/app/data-slot.php';
function ledenlabelsBestandPad(): string{return __DIR__.'/ledenlabels-data.php';}
function ledenlabelsBackupMap(): string{return __DIR__.'/data-backups';}
function ledenlabelsLeeg(): array{return ['schema'=>1,'labels'=>[],'toewijzingen'=>[],'updated'=>''];}
function ledenlabelsLees(): array{
 $pad=ledenlabelsBestandPad();if(!is_file($pad))return ledenlabelsLeeg();$ruw=@file_get_contents($pad);if($ruw===false)return ledenlabelsLeeg();$p=strpos($ruw,'{');if($p===false)return ledenlabelsLeeg();$d=json_decode(substr($ruw,$p),true);return is_array($d)?array_replace(ledenlabelsLeeg(),$d):ledenlabelsLeeg();
}
function ledenlabelsMaakBackup(): void{
 $pad=ledenlabelsBestandPad();if(!is_file($pad))return;$map=ledenlabelsBackupMap();if(!is_dir($map))@mkdir($map,0755,true);$stamp=(new DateTimeImmutable('now',new DateTimeZone('Europe/Amsterdam')))->format('Ymd-His-u');@copy($pad,$map.'/ledenlabels-'.$stamp.'.php');
}
