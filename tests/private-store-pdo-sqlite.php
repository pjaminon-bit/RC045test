<?php
// Integratietest in een apart PHP-proces: JSON fallback -> PDO write -> PDO read.
$root=dirname(__DIR__);$local=$root.'/site-config.local.php';$db=sys_get_temp_dir().'/rc045test-phase25-'.bin2hex(random_bytes(5)).'.sqlite';
if(is_file($local)){fwrite(STDERR,"FOUT: site-config.local.php bestaat al; test weigert lokale tenantconfig te overschrijven.\n");exit(1);}
if(!extension_loaded('pdo_sqlite')){fwrite(STDERR,"FOUT: pdo_sqlite ontbreekt; PDO-opslagtest kan niet worden uitgevoerd.\n");exit(1);}
$config="<?php\nreturn ".var_export(['vereniging'=>['sleutel'=>'phase25-sqlite'],'opslag'=>['private_driver'=>'pdo','pdo'=>['dsn'=>'sqlite:'.$db,'user'=>'','password'=>'']]],true).";\n";
file_put_contents($local,$config,LOCK_EX);
try{
    require_once $root.'/app/storage/private-store.php';
    $fallback=privateStoreLees('probe',static fn()=>['bron'=>'json-fallback','waarde'=>1]);
    if(($fallback['bron']??'')!=='json-fallback')throw new RuntimeException('lege database leverde niet de gecontroleerde fallback');
    if(!privateStoreSchrijf('probe',['bron'=>'pdo','waarde'=>2],static fn($data)=>false))throw new RuntimeException('PDO write gaf false');
    $gelezen=privateStoreLees('probe',static fn()=>['bron'=>'fout-fallback']);
    if(($gelezen['bron']??'')!=='pdo'||($gelezen['waarde']??null)!==2)throw new RuntimeException('PDO read gaf niet de geschreven data');
    $pdo=new PDO('sqlite:'.$db);$aantal=(int)$pdo->query("SELECT COUNT(*) FROM vereniging_private_store WHERE tenant_key='phase25-sqlite' AND collection_key='probe'")->fetchColumn();
    if($aantal!==1)throw new RuntimeException('tenant+collection is niet exact eenmaal opgeslagen');
    echo "OK: PDO SQLite tenant storage fallback/write/read\n";
}finally{
    @unlink($local);@unlink($db);
}
