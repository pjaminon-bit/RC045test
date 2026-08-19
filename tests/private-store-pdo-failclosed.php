<?php
// Integratietest in apart proces: expliciet PDO zonder DSN moet hard falen,
// nooit een lege administratie of stille JSON-write opleveren.
$root=dirname(__DIR__);$local=$root.'/site-config.local.php';
if(is_file($local)){fwrite(STDERR,"FOUT: site-config.local.php bestaat al; test weigert lokale tenantconfig te overschrijven.\n");exit(1);}
putenv('VERENIGING_DB_DSN');putenv('VERENIGING_DB_USER');putenv('VERENIGING_DB_PASSWORD');
$config="<?php\nreturn ".var_export(['vereniging'=>['sleutel'=>'phase25-failclosed'],'opslag'=>['private_driver'=>'pdo','pdo'=>['dsn'=>'','user'=>'','password'=>'']]],true).";\n";
file_put_contents($local,$config,LOCK_EX);
$geslaagd=false;
try{
    require_once $root.'/app/storage/private-store.php';
    try{privateStoreLees('probe',static fn()=>['zou'=>'niet mogen']);}
    catch(RuntimeException $e){$geslaagd=true;}
    if(!$geslaagd)throw new RuntimeException('PDO zonder DSN viel niet fail-closed uit');
    echo "OK: PDO zonder DSN faalt gesloten\n";
}finally{@unlink($local);}
