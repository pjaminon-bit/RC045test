<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function chk257258(bool $c,string $m):void{global$ok,$fout;if($c){$ok++;echo"OK: $m\n";}else{$fout++;fwrite(STDERR,"FOUT: $m\n");}}
function rr257258(string $p):void{if(is_link($p)||is_file($p)){@chmod($p,0640);@unlink($p);return;}if(!is_dir($p))return;foreach(scandir($p)?:[] as $i){if($i==='.'||$i==='..')continue;rr257258($p.DIRECTORY_SEPARATOR.$i);}@rmdir($p);}
function cfg257258(string $p,string $private):void{$c=['vereniging'=>['sleutel'=>'integrity-test','naam'=>'Integrity Test','volledige_naam'=>'Integrity Test','site_url'=>'https://integrity.example','timezone'=>'Europe/Amsterdam'],'opslag'=>['private_driver'=>'json','private_root'=>$private,'pdo'=>['dsn'=>'','user'=>'','password'=>''],'backups'=>['bewaardagen'=>30,'max_per_item'=>5,'max_asset_snapshots'=>2,'max_asset_mb'=>20]]];file_put_contents($p,"<?php\nreturn ".var_export($c,true).";\n");}
function run257258(string $worker,string $cfg,array $args):array{$a=[escapeshellcmd(PHP_BINARY),escapeshellarg($worker)];foreach($args as $v)$a[]=escapeshellarg((string)$v);$out=[];exec('VERENIGING_REQUIRE_TENANT_CONFIG=1 VERENIGING_CONFIG_FILE='.escapeshellarg($cfg).' '.implode(' ',$a).' 2>/dev/null',$out,$code);return[$code,implode("\n",$out)];}

$tmp=sys_get_temp_dir().DIRECTORY_SEPARATOR.'rc045-257-258-'.bin2hex(random_bytes(4));$private=$tmp.'/private';$content=$private.'/public-content';@mkdir($content,0750,true);$cfg=$tmp.'/config.php';cfg257258($cfg,$private);$worker=$tmp.'/worker.php';
file_put_contents($worker,<<<'PHP'
<?php
$root=$argv[1];$actie=$argv[2]??'';$key=$argv[3]??'agenda';require_once $root.'/app/content/public-content-store.php';
function valid258(string $k,string $m):array{return match($k){'agenda'=>[['date'=>'2026-09-10','title'=>['nl'=>$m]]],'media'=>[['date'=>'2026-09-10','title'=>['nl'=>$m]]],'media-pagina','fotoboek-pagina'=>['hero_sub'=>['nl'=>$m,'en'=>'','de'=>'']],'sponsors'=>['items'=>[],'cta'=>['nl'=>$m]],'fotoboek'=>['albums'=>[],'marker'=>$m],'lidmaatschapstypen'=>['types'=>[['id'=>'test','label'=>['nl'=>$m],'actief'=>true,'jaarbedrag'=>1,'inschrijfgeld'=>0,'pro_rata'=>true]]],default=>['marker'=>$m]};}
if($actie==='result'){echo json_encode(publicContentLeesResult($key),JSON_UNESCAPED_SLASHES);exit;}
if($actie==='write'){echo publicContentSchrijfTenant($key,valid258($key,$argv[4]??'NEW'),true)?'OK':'FAIL';exit;}
if($actie==='endpoint'){$_SERVER['REQUEST_METHOD']='GET';$_GET['key']=$key;http_response_code(200);register_shutdown_function(static function(){echo'STATUS='.http_response_code();});include $root.'/public-content.php';exit;}
exit(2);
PHP);

$files=['agenda'=>'agenda.json','media'=>'media.json','media-pagina'=>'media-pagina.json','sponsors'=>'sponsors.json','fotoboek'=>'fotoboek.json','fotoboek-pagina'=>'fotoboek-pagina.json','lidmaatschapstypen'=>'lidmaatschapstypen.json'];
$valid=['agenda'=>[['date'=>'2026-01-01']],'media'=>[['date'=>'2026-01-01']],'media-pagina'=>['hero_sub'=>['nl'=>'oud']],'sponsors'=>['items'=>[],'cta'=>['nl'=>'oud']],'fotoboek'=>['albums'=>[]],'fotoboek-pagina'=>['hero_sub'=>['nl'=>'oud']],'lidmaatschapstypen'=>['types'=>[['id'=>'oud']]]];
$invalid=['agenda'=>['geen'=>'lijst'],'media'=>['geen'=>'lijst'],'media-pagina'=>['geen_hero_sub'=>[]],'sponsors'=>['cta'=>[]],'fotoboek'=>['geen_albums'=>[]],'fotoboek-pagina'=>['geen_hero_sub'=>[]],'lidmaatschapstypen'=>['types'=>'geen-lijst']];

try{
 foreach(['beheer/agenda.php'=>'function agendaSchrijf','beheer/media.php'=>'function mediaSchrijf','beheer/sponsors.php'=>'function sponsorsSchrijf','beheer/fotoboek-lib.php'=>'function fbSchrijf','app/leden/lidmaatschap.php'=>'function lidmaatschapSchrijf']as$f=>$fn){$s=(string)file_get_contents($root.'/'.$f);chk257258(str_contains($s,$fn)&&str_contains($s,'publicContentSchrijfTenant'),$f.' gebruikt centrale tenantwriter');}

 [$c,$o]=run257258($worker,$cfg,[$root,'result','agenda']);$r=json_decode($o,true);chk257258($c===0&&($r['status']??'')==='missing'&&array_key_exists('data',$r)&&$r['data']===null,'reader onderscheidt missing expliciet');
 [$c,$o]=run257258($worker,$cfg,[$root,'endpoint','agenda']);chk257258($c===0&&trim($o)==='[]STATUS=200','missing externe dataset blijft HTTP 200 empty');

 foreach($files as$key=>$name){$p=$content.'/'.$name;
  @unlink($p);[$c,$o]=run257258($worker,$cfg,[$root,'write',$key,'INIT']);chk257258($c===0&&trim($o)==='OK'&&is_file($p),$key.': missing kan worden geïnitialiseerd');

  $raw='{"broken":';file_put_contents($p,$raw);$before=hash_file('sha256',$p);[$c1,$o1]=run257258($worker,$cfg,[$root,'result',$key]);$rr=json_decode($o1,true);[$c2,$o2]=run257258($worker,$cfg,[$root,'write',$key,'NEW']);chk257258($c1===0&&($rr['status']??'')==='invalid'&&($rr['code']??'')==='ongeldige_json',$key.': corrupte JSON is invalid');chk257258($c2===0&&trim($o2)==='FAIL'&&hash_file('sha256',$p)===$before&&file_get_contents($p)===$raw,$key.': corrupte bytes blijven identiek');

  file_put_contents($p,json_encode($invalid[$key],JSON_UNESCAPED_SLASHES));$before=hash_file('sha256',$p);[$c1,$o1]=run257258($worker,$cfg,[$root,'result',$key]);$rr=json_decode($o1,true);[$c2,$o2]=run257258($worker,$cfg,[$root,'write',$key,'NEW']);chk257258($c1===0&&($rr['status']??'')==='invalid'&&($rr['code']??'')==='ongeldig_schema',$key.': ongeldig schema is invalid');chk257258($c2===0&&trim($o2)==='FAIL'&&hash_file('sha256',$p)===$before,$key.': ongeldig schema wordt niet overschreven');

  file_put_contents($p,json_encode($valid[$key],JSON_UNESCAPED_SLASHES));$before=hash_file('sha256',$p);[$c,$o]=run257258($worker,$cfg,[$root,'write',$key,'VALID']);chk257258($c===0&&trim($o)==='OK'&&hash_file('sha256',$p)!==$before,$key.': normale geldige edit blijft werken');
 }

 $p=$content.'/agenda.json';file_put_contents($p,'{broken');[$c,$o]=run257258($worker,$cfg,[$root,'endpoint','agenda']);chk257258($c===0&&str_contains($o,'STATUS=500')&&!str_contains($o,'[]STATUS=200'),'corrupte externe dataset is HTTP 500 en detecteerbaar');

 file_put_contents($p,json_encode([['date'=>'2026-01-01']]));@chmod($p,0000);[$c,$o]=run257258($worker,$cfg,[$root,'result','agenda']);@chmod($p,0640);$r=json_decode($o,true);if(function_exists('posix_geteuid')&&posix_geteuid()===0)chk257258(true,'onleesbaar-test overgeslagen onder root');else chk257258($c===0&&($r['status']??'')==='invalid'&&($r['code']??'')==='onleesbaar','bestaand onleesbaar bestand is invalid, niet missing');

 $source=(string)file_get_contents($root.'/app/content/public-content-store.php');chk257258(str_contains($source,'publicContentStructuurGeldig($sleutel, $data)'),'centrale writer valideert ook nieuw documentschema vóór mutatie');
}finally{rr257258($tmp);}
echo"Security #257/#258 public content integrity: $ok OK, $fout fout(en)\n";exit($fout===0?0:1);