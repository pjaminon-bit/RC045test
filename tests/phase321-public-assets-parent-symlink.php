<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function check321parent(bool $c,string $l):void{global$ok,$fout;if($c){$ok++;echo"OK: $l\n";}else{$fout++;fwrite(STDERR,"FOUT: $l\n");}}
function rr321parent(string $p):void{if(is_link($p)||is_file($p)){@unlink($p);return;}if(!is_dir($p))return;foreach(scandir($p)?:[]as$i){if($i==='.'||$i==='..')continue;rr321parent($p.DIRECTORY_SEPARATOR.$i);}@rmdir($p);}
$tmp=sys_get_temp_dir().DIRECTORY_SEPARATOR.'rc045-assets-parent-'.bin2hex(random_bytes(4));
$tenant=$tmp.'/tenant';$private=$tenant.'/private';$outside=$tmp.'/outside';@mkdir($private,0750,true);@mkdir($outside.'/sponsors',0750,true);file_put_contents($outside.'/sponsors/canary.jpg','BUITEN-TENANT');
$config=$tenant.'/config.php';file_put_contents($config,"<?php\nreturn ".var_export(['vereniging'=>['sleutel'=>'parent-test','naam'=>'Parent','site_url'=>'https://parent.example'],'opslag'=>['private_driver'=>'json','private_root'=>$private,'pdo'=>['dsn'=>'','user'=>'','password'=>'']]],true).";\n");
$symlink=@symlink($outside,$private.'/public-assets');
try{
 if(!$symlink){check321parent(true,'parent-symlinktest overgeslagen omdat platform geen symlink toestaat');}
 else{
  $launcher=$tmp.'/run.php';file_put_contents($launcher,"<?php\nputenv('VERENIGING_REQUIRE_TENANT_CONFIG=1');\nputenv(".var_export('VERENIGING_CONFIG_FILE='.$config,true).");\nputenv(".var_export('VERENIGING_PRIVATE_ROOT='.$private,true).");\n\$ROOT=".var_export($root,true).";\nrequire \$ROOT.'/app/content/public-asset-store.php';\necho json_encode(['map'=>publicAssetMaakNamespaceMap('sponsors'),'read'=>publicAssetVeiligLeesPad('sponsors','canary.jpg'),'safe'=>publicAssetTenantPadVeilig(publicAssetNamespaceRoot('sponsors'))]);\n");
  $out=[];exec(escapeshellcmd(PHP_BINARY).' '.escapeshellarg($launcher).' 2>&1',$out,$code);$d=json_decode(implode("\n",$out),true);
  check321parent($code===0,'parent-symlinkscenario draait gecontroleerd');
  check321parent(($d['map']??'x')===null,'namespacecreatie weigert symlink op public-assets parent');
  check321parent(($d['read']??'x')===null,'assetreader serveert geen bestand via parent-symlink');
  check321parent(array_key_exists('safe',$d)&&$d['safe']===false,'tenantpadcontrole markeert parent-symlink onveilig');
  check321parent((string)file_get_contents($outside.'/sponsors/canary.jpg')==='BUITEN-TENANT','extern symlinkdoel blijft inhoudelijk ongemoeid');
 }
}finally{rr321parent($tmp);}
echo"Phase 3.2.1 public assets parent symlink: $ok OK, $fout fout(en)\n";exit($fout===0?0:1);
