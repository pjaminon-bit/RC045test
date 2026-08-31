<?php
$root=dirname(__DIR__);
require_once $root.'/app/deployment/release-contract.php';
$ht=@file_get_contents($root.'/.htaccess');
$web=@file_get_contents($root.'/app/deployment/webserver-contract.php');
$checks=[
 'actuele releasepolicy sluit ops uit'=>release47GenegeerdPad('ops/vps-test-deploy/helper')===true,
 'legacy releasepolicy telt ops mee'=>release47GenegeerdPad('ops/vps-test-deploy/helper',1)===false,
 'htaccess denies ops'=>is_string($ht)&&str_contains($ht,'RewriteRule ^ops(?:/|$) - [F,L,NC]'),
 'vhost denies ops'=>is_string($web)&&str_contains($web,'<LocationMatch "^/ops(?:/|$)">')&&str_contains($web,"'<LocationMatch \"^/(?:app|bin|tests|docs|\\\\.github|\\\\.git)(?:/|$)\">'"),
];
$ok=0;foreach($checks as$label=>$pass){if($pass){echo"OK: {$label}\n";$ok++;}else fwrite(STDERR,"FOUT: {$label}\n");}
echo 'Ops web exposure regression: '.$ok.'/'.count($checks)." OK\n";
exit($ok===count($checks)?0:1);
