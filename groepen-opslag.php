<?php
// Private fallbackopslag voor commissies en werkgroepen.
define('GROEPEN_VOORLOOP', "<?php exit; ?>\n");
require_once __DIR__ . '/app/data-slot.php';
require_once __DIR__ . '/app/storage/legacy-private-json.php';
function groepenBestandPad(): string{return __DIR__.'/groepen-data.php';}
function groepenBackupMap(): string{return __DIR__.'/data-backups';}
function groepenLeeg(): array{return ['schema'=>1,'rollen'=>[['id'=>'trekker','naam'=>'Trekker','actief'=>true],['id'=>'voorzitter','naam'=>'Voorzitter','actief'=>true],['id'=>'secretaris','naam'=>'Secretaris','actief'=>true],['id'=>'bestuurslid','naam'=>'Verantwoordelijk bestuurslid','actief'=>true],['id'=>'lid','naam'=>'Lid','actief'=>true]],'groepen'=>[],'updated'=>''];}
function groepenLees(): array{
 $d=legacyPrivateJsonLees(groepenBestandPad(),'groepen',['groepen']);return $d===null?groepenLeeg():array_replace(groepenLeeg(),$d);
}
function groepenMaakBackup(): void{
 $pad=groepenBestandPad();if(!is_file($pad))return;$map=groepenBackupMap();if(!is_dir($map))@mkdir($map,0755,true);$stamp=(new DateTimeImmutable('now',new DateTimeZone('Europe/Amsterdam')))->format('Ymd-His-u');@copy($pad,$map.'/groepen-'.$stamp.'.php');
}
