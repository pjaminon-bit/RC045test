<?php
$root=dirname(__DIR__);
require_once $root.'/app/deployment/release-contract.php';
$standaloneHt=@file_get_contents($root.'/.htaccess');
$publicHt=@file_get_contents($root.'/public/.htaccess');
$web=@file_get_contents($root.'/app/deployment/webserver-contract.php');
$opsReal=realpath($root.'/ops');
$publicReal=realpath($root.'/public');
$opsBuitenPublic=is_string($opsReal)&&is_string($publicReal)&&!str_starts_with(rtrim(str_replace('\\','/',$opsReal),'/').'/',rtrim(str_replace('\\','/',$publicReal),'/').'/');
$checks=[
 'actuele releasepolicy sluit ops uit'=>release47GenegeerdPad('ops/vps-test-deploy/helper')===true,
 'legacy releasepolicy telt ops mee'=>release47GenegeerdPad('ops/vps-test-deploy/helper',1)===false,
 'standalone htaccess houdt ops defense-in-depth dicht'=>is_string($standaloneHt)&&str_contains($standaloneHt,'RewriteRule ^ops(?:/|$) - [F,L,NC]'),
 'ops ligt fysiek buiten minimale VPS-documentroot'=>$opsBuitenPublic,
 'public htaccess onderhoudt geen ops-denylist'=>is_string($publicHt)&&!str_contains($publicHt,'RewriteRule ^ops'),
 'VPS-vhost weigert applicatierelease en onderhoudt geen ops-padregel'=>is_string($web)&&str_contains($web,'application_release_root_denied')&&!str_contains($web,'<LocationMatch "^/ops'),
];
$ok=0;foreach($checks as$label=>$pass){if($pass){echo"OK: {$label}\n";$ok++;}else fwrite(STDERR,"FOUT: {$label}\n");}
echo 'Ops web exposure regression: '.$ok.'/'.count($checks)." OK\n";
exit($ok===count($checks)?0:1);
