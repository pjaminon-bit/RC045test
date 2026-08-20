<?php
$root=dirname(__DIR__);$local=$root.'/site-config.local.php';$db=sys_get_temp_dir().'/rc045test-phase25-'.bin2hex(random_bytes(5)).'.sqlite';
if(is_file($local)){fwrite(STDERR,"FOUT: site-config.local.php bestaat al; test weigert die te overschrijven.\n");exit(1);}if(!extension_loaded('pdo_sqlite')){fwrite(STDERR,"FOUT: pdo_sqlite ontbreekt.\n");exit(1);}
$config="<?php\nreturn ".var_export(['vereniging'=>['sleutel'=>'phase25-sqlite'],'opslag'=>['private_driver'=>'pdo','pdo'=>['dsn'=>'sqlite:'.$db,'user'=>'','password'=>'']]],true).";\n";file_put_contents($local,$config,LOCK_EX);
try{
 require_once $root.'/app/storage/private-store.php';
 $fallback=privateStoreLees('probe',static fn()=>['bron'=>'json-fallback','waarde'=>1]);if(($fallback['bron']??'')!=='json-fallback')throw new RuntimeException('lege database leverde niet de fallback');
 if(!privateStoreSchrijf('probe',['bron'=>'pdo','waarde'=>2],static fn()=>false))throw new RuntimeException('PDO write gaf false');$gelezen=privateStoreLees('probe',static fn()=>['bron'=>'fout']);if(($gelezen['bron']??'')!=='pdo'||($gelezen['waarde']??null)!==2)throw new RuntimeException('PDO read fout');
 $rollback=false;try{privateStoreTransactie(function(){if(!privateStoreSchrijf('tx_a',['waarde'=>'a'],static fn()=>false))throw new RuntimeException('tx_a false');if(!privateStoreSchrijf('tx_b',['waarde'=>'b'],static fn()=>false))throw new RuntimeException('tx_b false');throw new RuntimeException('bewuste rollback');});}catch(RuntimeException $e){if($e->getMessage()==='bewuste rollback')$rollback=true;else throw$e;}if(!$rollback)throw new RuntimeException('rollback niet gezien');
 $pdo=privateStorePdo();$stmt=$pdo->prepare("SELECT COUNT(*) FROM vereniging_private_store WHERE tenant_key='phase25-sqlite' AND collection_key IN ('tx_a','tx_b')");$stmt->execute();if((int)$stmt->fetchColumn()!==0)throw new RuntimeException('rollback liet gedeeltelijke writes achter');
 privateStoreTransactie(function(){if(!privateStoreSchrijf('tx_a',['waarde'=>'a'],static fn()=>false))throw new RuntimeException('tx_a commit false');if(!privateStoreSchrijf('tx_b',['waarde'=>'b'],static fn()=>false))throw new RuntimeException('tx_b commit false');});$stmt=$pdo->prepare("SELECT COUNT(*) FROM vereniging_private_store WHERE tenant_key='phase25-sqlite' AND collection_key IN ('tx_a','tx_b')");$stmt->execute();if((int)$stmt->fetchColumn()!==2)throw new RuntimeException('commit schreef niet beide collecties');
 echo "OK: PDO SQLite fallback/write/read + transaction commit/rollback\n";
}finally{@unlink($local);@unlink($db);}
