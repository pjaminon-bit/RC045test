<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function checkTB(bool $cond,string $label):void{global$ok,$fout;if($cond){$ok++;echo"OK: $label\n";}else{$fout++;fwrite(STDERR,"FOUT: $label\n");}}
function rrTB(string $dir):void{if(is_link($dir)){@unlink($dir);return;}if(!is_dir($dir))return;foreach(scandir($dir)?:[] as $i){if($i==='.'||$i==='..')continue;$p=$dir.DIRECTORY_SEPARATOR.$i;if(is_dir($p)&&!is_link($p))rrTB($p);else@unlink($p);}@rmdir($dir);}
function cpTB(string $src,string $dst):bool{if(is_dir($src)&&!is_link($src)){if(!is_dir($dst)&&!mkdir($dst,0750,true))return false;foreach(scandir($src)?:[] as $i){if($i==='.'||$i==='..')continue;if(!cpTB($src.DIRECTORY_SEPARATOR.$i,$dst.DIRECTORY_SEPARATOR.$i))return false;}return true;}return is_file($src)&&!is_link($src)&&copy($src,$dst);}
function configTB(string $pad,string $key,string $private,string $driver='json',string $dsn=''):void{$cfg=['vereniging'=>['sleutel'=>$key,'naam'=>strtoupper($key),'volledige_naam'=>strtoupper($key),'site_url'=>'https://'.$key.'.example'],'opslag'=>['private_driver'=>$driver,'private_root'=>$private,'pdo'=>['dsn'=>$dsn,'user'=>'','password'=>''],'backups'=>['bewaardagen'=>90,'max_per_item'=>2,'max_asset_snapshots'=>1,'max_asset_mb'=>50]]];file_put_contents($pad,"<?php\nreturn ".var_export($cfg,true).";\n");}
function runTB(string $worker,string $config,array $args):array{$parts=[escapeshellcmd(PHP_BINARY),escapeshellarg($worker)];foreach($args as $a)$parts[]=escapeshellarg((string)$a);$cmd='VERENIGING_REQUIRE_TENANT_CONFIG=1 VERENIGING_CONFIG_FILE='.escapeshellarg($config).' '.implode(' ',$parts);$out=[];exec($cmd.' 2>&1',$out,$code);$raw=implode("\n",$out);$json=json_decode($raw,true);if($code!==0||!is_array($json))fwrite(STDERR,"WORKER FOUT [".implode(' ',array_map('strval',$args))."] code={$code}: {$raw}\n");return[$code,is_array($json)?$json:null,$raw];}

$tmp=sys_get_temp_dir().DIRECTORY_SEPARATOR.'rc045-tbackup-'.bin2hex(random_bytes(4));mkdir($tmp,0750,true);
$aPrivate=$tmp.'/a/private';$bPrivate=$tmp.'/b/private';$pPrivate=$tmp.'/pdo/private';mkdir($aPrivate,0750,true);mkdir($bPrivate,0750,true);mkdir($pPrivate,0750,true);
$aCfg=$tmp.'/a.php';$bCfg=$tmp.'/b.php';$pCfg=$tmp.'/pdo.php';configTB($aCfg,'tenant-a',$aPrivate);configTB($bCfg,'tenant-b',$bPrivate);configTB($pCfg,'tenant-pdo',$pPrivate,'pdo','sqlite:'.$tmp.'/pdo.sqlite');
$worker=$tmp.'/worker.php';
$workerCode=<<<'PHP'
<?php
$root=$argv[1];$actie=$argv[2]??'';
function out($v){echo json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
switch($actie){
case 'backup':require $root.'/app/storage/tenant-backup-store.php';$p=tenantBackupMaakArray($argv[3],['value'=>$argv[4]]);out(['ok'=>$p!==null,'name'=>$p?basename($p):'']);break;
case 'list':require $root.'/app/storage/tenant-backup-store.php';$l=tenantBackupDataLijst($argv[3]);out(['count'=>count($l),'names'=>array_map('basename',$l)]);break;
case 'read':require $root.'/app/storage/tenant-backup-store.php';$f=null;$d=tenantBackupLeesArray($argv[3],$argv[4],$f);out(['data'=>$d,'error'=>$f]);break;
case 'private-write':require $root.'/app/storage/private-store.php';$r=privateStoreSchrijf($argv[3],['value'=>$argv[4]],static fn($d)=>false);out(['ok'=>$r]);break;
case 'public-write':require $root.'/app/content/public-content-store.php';$r=publicContentSchrijfTenant($argv[3],['value'=>$argv[4]],($argv[5]??'1')==='1');out(['ok'=>$r]);break;
case 'public-read':require $root.'/app/content/public-content-store.php';out(['data'=>publicContentLees($argv[3])]);break;
case 'asset-init':require $root.'/app/content/public-asset-store.php';$scope=$argv[3];$v=$argv[4];$r=publicAssetMaakNamespaceMap($scope);if($scope==='sponsors'){$p=$r.'/sponsor-1.png';}else{$m=$r.'/album';if(!is_dir($m))mkdir($m,0750,true);$p=$m.'/foto.jpg';}file_put_contents($p,$v);out(['ok'=>is_file($p),'path'=>$p]);break;
case 'asset-modify':require $root.'/app/content/public-asset-store.php';$scope=$argv[3];$r=publicAssetNamespaceRoot($scope);$p=$scope==='sponsors'?$r.'/sponsor-1.png':$r.'/album/foto.jpg';file_put_contents($p,$argv[4]);out(['ok'=>true]);break;
case 'asset-snapshot':require $root.'/app/storage/tenant-backup-store.php';$p=tenantBackupMaakAssetSnapshot($argv[3]);out(['ok'=>$p!==null,'name'=>$p?basename($p):'']);break;
case 'asset-list':require $root.'/app/storage/tenant-backup-store.php';$l=tenantBackupAssetLijst($argv[3]);out(['count'=>count($l),'names'=>array_map('basename',$l)]);break;
case 'asset-validate':require $root.'/app/storage/tenant-backup-store.php';$f=null;$p=tenantBackupLeesAssetSnapshot($argv[3],$argv[4],$f);out(['ok'=>$p!==null,'error'=>$f]);break;
case 'asset-restore':require $root.'/app/storage/tenant-backup-store.php';$f=null;$r=tenantBackupHerstelAssetSnapshot($argv[3],$argv[4],$f);out(['ok'=>$r,'error'=>$f]);break;
case 'asset-read':require $root.'/app/content/public-asset-store.php';$scope=$argv[3];$r=publicAssetNamespaceRoot($scope);$p=$scope==='sponsors'?$r.'/sponsor-1.png':$r.'/album/foto.jpg';out(['value'=>is_file($p)?file_get_contents($p):null]);break;
case 'registry':require $root.'/app/storage/tenant-backup-store.php';$r=require $root.'/beheer/backup-registry.php';$pads=0;foreach($r as $i)if(array_key_exists('pad',$i))$pads++;out(['count'=>count($r),'pads'=>$pads,'assets'=>isset($r['assets_fotoboek'],$r['assets_sponsors']),'private'=>isset($r['private_leden']),'public'=>isset($r['public_contact'])]);break;
default:out(['error'=>'actie']);exit(2);
}
PHP;
file_put_contents($worker,$workerCode);
try{
    [$c1,$r1]=runTB($worker,$aCfg,[$root,'backup','public-contact','A1']);
    [$c2,$r2]=runTB($worker,$aCfg,[$root,'backup','public-contact','A2']);
    [$c3,$r3]=runTB($worker,$aCfg,[$root,'backup','public-contact','A3']);
    checkTB($c1===0&&$c2===0&&$c3===0&&!empty($r1['name']),'tenant A kan tenantgebonden datasnapshots maken');
    [$cl,$rl]=runTB($worker,$aCfg,[$root,'list','public-contact']);
    checkTB($cl===0&&($rl['count']??0)===2,'datasnapshotretentie begrenst onderdeel op max_per_item');

    if(is_array($r3)&&!empty($r3['name'])){
        $aFile=$aPrivate.'/backups/tenant/records/public-contact/'.$r3['name'];
        $bMap=$bPrivate.'/backups/tenant/records/public-contact';mkdir($bMap,0750,true);copy($aFile,$bMap.'/'.$r3['name']);
        [$cb,$rb]=runTB($worker,$bCfg,[$root,'read','public-contact',$r3['name']]);
        checkTB($cb===0&&($rb['data']??null)===null&&str_contains((string)($rb['error']??''),'hoort niet'),'fysiek gekopieerde A-databackup wordt door B tenantbinding geweigerd');

        $verkeerd=$aPrivate.'/backups/tenant/records/public-homepage';mkdir($verkeerd,0750,true);copy($aFile,$verkeerd.'/'.$r3['name']);
        [$ck,$rk]=runTB($worker,$aCfg,[$root,'read','public-homepage',$r3['name']]);
        checkTB($ck===0&&($rk['data']??null)===null&&str_contains((string)($rk['error']??''),'hoort niet'),'datasnapshot kan niet onder ander onderdeel worden hersteld');
    }else{
        checkTB(false,'fysiek gekopieerde A-databackup wordt door B tenantbinding geweigerd');
        checkTB(false,'datasnapshot kan niet onder ander onderdeel worden hersteld');
    }

    [$pw1,$pr1]=runTB($worker,$aCfg,[$root,'public-write','contact','oud','0']);
    [$pw2,$pr2]=runTB($worker,$aCfg,[$root,'public-write','contact','nieuw','1']);
    [$pl,$plr]=runTB($worker,$aCfg,[$root,'list','public-contact']);
    $publicBackup=is_array($plr)?($plr['names'][0]??''):'';
    [$pread,$preadr]=$publicBackup!==''?runTB($worker,$aCfg,[$root,'read','public-contact',$publicBackup]):[1,null,'geen backup'];
    checkTB($pw1===0&&$pw2===0&&is_array($preadr)&&($preadr['data']['value']??'')==='oud','publieke tenantwrite bewaart vorige versie');

    [$j1]=runTB($worker,$aCfg,[$root,'private-write','leden','een']);
    [$j2]=runTB($worker,$aCfg,[$root,'private-write','leden','twee']);
    [$jl,$jlr]=runTB($worker,$aCfg,[$root,'list','private-leden']);
    $jn=is_array($jlr)?($jlr['names'][0]??''):'';[$jr,$jrr]=$jn!==''?runTB($worker,$aCfg,[$root,'read','private-leden',$jn]):[1,null,'geen backup'];
    checkTB($j1===0&&$j2===0&&is_array($jrr)&&($jrr['data']['value']??'')==='een','JSON private-store bewaart vorige tenantversie centraal');

    if(extension_loaded('pdo_sqlite')){
        [$d1]=runTB($worker,$pCfg,[$root,'private-write','leden','pdo-een']);
        [$d2]=runTB($worker,$pCfg,[$root,'private-write','leden','pdo-twee']);
        [$dl,$dlr]=runTB($worker,$pCfg,[$root,'list','private-leden']);$dn=is_array($dlr)?($dlr['names'][0]??''):'';
        [$dr,$drr]=$dn!==''?runTB($worker,$pCfg,[$root,'read','private-leden',$dn]):[1,null,'geen backup'];
        checkTB($d1===0&&$d2===0&&is_array($drr)&&($drr['data']['value']??'')==='pdo-een','PDO private-store maakt dezelfde tenantgebonden vorige-versie snapshot');
    }else checkTB(true,'PDO-backuptest overgeslagen: pdo_sqlite niet geladen');

    [$ai,$air]=runTB($worker,$aCfg,[$root,'asset-init','sponsors','OUD']);
    [$as,$asr]=runTB($worker,$aCfg,[$root,'asset-snapshot','sponsors']);$assetName=is_array($asr)?($asr['name']??''):'';
    [$am]=runTB($worker,$aCfg,[$root,'asset-modify','sponsors','NIEUW']);
    [$ar,$arr]=$assetName!==''?runTB($worker,$aCfg,[$root,'asset-restore','sponsors',$assetName]):[1,null,'geen snapshot'];
    [$av,$avr]=runTB($worker,$aCfg,[$root,'asset-read','sponsors']);
    checkTB($ai===0&&$as===0&&$am===0&&$ar===0&&is_array($arr)&&($arr['ok']??false)===true&&is_array($avr)&&($avr['value']??'')==='OUD','assetsnapshot herstelt atomisch ook bij retentielimiet van één snapshot');
    [$asl,$aslr]=runTB($worker,$aCfg,[$root,'asset-list','sponsors']);
    checkTB(is_array($aslr)&&($aslr['count']??99)<=1,'assetsnapshotretentie begrenst scope op max_asset_snapshots');

    [$as2,$as2r]=runTB($worker,$aCfg,[$root,'asset-snapshot','sponsors']);$assetA=is_array($as2r)?($as2r['name']??''):'';
    $srcA=$aPrivate.'/backups/tenant/assets/sponsors/'.$assetA;$dstB=$bPrivate.'/backups/tenant/assets/sponsors/'.$assetA;
    checkTB($assetA!==''&&cpTB($srcA,$dstB),'asset-canary snapshot fysiek van A naar B gekopieerd');
    [$ab,$abr]=$assetA!==''?runTB($worker,$bCfg,[$root,'asset-validate','sponsors',$assetA]):[1,null,'geen snapshot'];
    checkTB($ab===0&&is_array($abr)&&($abr['ok']??true)===false&&str_contains((string)($abr['error']??''),'hoort niet'),'B weigert A-assetsnapshot op tenantmanifest');

    [$rg,$rgr]=runTB($worker,$aCfg,[$root,'registry']);
    checkTB($rg===0&&is_array($rgr)&&($rgr['pads']??99)===0&&!empty($rgr['assets'])&&!empty($rgr['private'])&&!empty($rgr['public']),'externe backupregistry is logisch en bevat geen projectrootpaden');

    $outside=$tmp.'/outside';mkdir($outside,0750,true);$bad=$aPrivate.'/backups/tenant/records/symlink-test';@mkdir(dirname($bad),0750,true);
    if(@symlink($outside,$bad)){
        [$sy,$syr]=runTB($worker,$aCfg,[$root,'backup','symlink-test','X']);
        checkTB($sy===0&&is_array($syr)&&empty($syr['ok']),'symlink in tenantbackup-pad wordt geweigerd');
    }else checkTB(true,'symlink-backuptest overgeslagen op dit platform');
}finally{rrTB($tmp);}

echo"Phase 3.2.1 tenant backups: $ok OK, $fout fout(en)\n";exit($fout===0?0:1);
