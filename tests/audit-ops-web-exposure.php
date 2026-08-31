<?php
$root=dirname(__DIR__);
$release=@file_get_contents($root.'/app/deployment/release-contract.php');
$ht=@file_get_contents($root.'/.htaccess');
$web=@file_get_contents($root.'/app/deployment/webserver-contract.php');
$checks=[
 'release excludes ops'=>is_string($release)&&str_contains($release,"'.git/', '.github/', 'ops/'" )&&str_contains($release,"'.git', '.github', 'ops'"),
 'htaccess denies ops'=>is_string($ht)&&str_contains($ht,'RewriteRule ^ops(?:/|$) - [F,L,NC]'),
 'vhost denies ops'=>is_string($web)&&str_contains($web,'<LocationMatch "^/ops(?:/|$)">')&&str_contains($web,"'<LocationMatch \"^/(?:app|bin|tests|docs|\\\\.github|\\\\.git)(?:/|$)\">'"),
];
$ok=0;foreach($checks as$label=>$pass){if($pass){echo"OK: {$label}\n";$ok++;}else fwrite(STDERR,"FOUT: {$label}\n");}
echo 'Ops web exposure regression: '.$ok.'/'.count($checks)." OK\n";
exit($ok===count($checks)?0:1);
