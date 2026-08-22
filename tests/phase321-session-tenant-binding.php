<?php
require_once dirname(__DIR__) . '/app/auth-session-tenant.php';
require_once dirname(__DIR__) . '/app/auth-storage.php';

$checks=[];
function check321session(bool $cond,string $label):void{global$checks;$checks[]=[$cond,$label];}
function rrmdir321session(string $dir):void{if(!is_dir($dir)||is_link($dir))return;foreach(scandir($dir)?:[] as $item){if($item==='.'||$item==='..')continue;$p=$dir.DIRECTORY_SEPARATOR.$item;if(is_dir($p)&&!is_link($p))rrmdir321session($p);else@unlink($p);}@rmdir($dir);}
function nieuwe321session():string{if(session_status()===PHP_SESSION_ACTIVE)session_write_close();session_id('');if(!session_start())throw new RuntimeException('Testsessie kon niet starten.');return session_id();}
function open321session(string $id):void{if(session_status()===PHP_SESSION_ACTIVE)session_write_close();session_id($id);if(!session_start())throw new RuntimeException('Bestaande testsessie kon niet openen.');}

$tmp=sys_get_temp_dir().DIRECTORY_SEPARATOR.'rc045-phase321-session-'.bin2hex(random_bytes(5));
@mkdir($tmp,0700,true);
ini_set('session.save_path',$tmp);
ini_set('session.use_cookies','0');
ini_set('session.use_only_cookies','0');
ini_set('session.use_strict_mode','1');
ini_set('session.cache_limiter','');
session_name('RC045TESTSESSION');
$bindingA=str_repeat('a',64);$bindingB=str_repeat('b',64);

try{
    $aId=nieuwe321session();
    $_SESSION=['tenant_key'=>'tenant-a','installation_binding'=>$bindingA,'csrf'=>'csrf-a','gebruiker'=>'alice','is_master'=>false,'user_session_version'=>7];
    session_write_close();
    $aBestand=$tmp.'/sess_'.$aId;$aHashVoor=is_file($aBestand)?hash_file('sha256',$aBestand):'';
    check321session($aHashVoor!=='','tenant A sessiebestand bestaat');

    open321session($aId);$csrf=(string)($_SESSION['csrf']??'');
    $okB=authSessionTenantBewaak('tenant-b',$bindingB,$csrf);$bId=session_id();
    check321session($okB===false,'tenant B weigert sessie die aan tenant A/installatie A is gebonden');
    check321session($bId!==''&&!hash_equals($aId,$bId),'mismatch krijgt een nieuw session-id');
    check321session(($_SESSION['tenant_key']??'')==='tenant-b'&&($_SESSION['installation_binding']??'')===$bindingB,'vervangsessie is aan tenant en installatie B gebonden');
    check321session(!isset($_SESSION['gebruiker'],$_SESSION['is_master'],$_SESSION['user_session_version']),'authstate van andere tenant/installatie gaat niet mee');
    check321session($csrf!==''&&hash_equals($csrf,(string)($_SESSION['csrf']??'')),'CSRF-token wordt na sessieherstart vernieuwd');
    session_write_close();

    $aHashNa=is_file($aBestand)?hash_file('sha256',$aBestand):'';
    check321session($aHashNa!==''&&hash_equals($aHashVoor,$aHashNa),'mismatchrequest wijzigt sessiebestand van tenant A niet');
    open321session($aId);
    check321session(($_SESSION['tenant_key']??'')==='tenant-a'&&($_SESSION['installation_binding']??'')===$bindingA&&($_SESSION['gebruiker']??'')==='alice','oorspronkelijke tenant/installatiesessie blijft intact');
    session_write_close();

    // Zelfde tenant maar andere installatie (bijv. rc045.nl/ versus /dev/) is
    // eveneens een harde grens.
    $prodId=nieuwe321session();
    $_SESSION=['tenant_key'=>'rc045','installation_binding'=>$bindingA,'csrf'=>'prod','gebruiker'=>'beheer-user'];
    session_write_close();open321session($prodId);$csrfProd='prod';
    $devOk=authSessionTenantBewaak('rc045',$bindingB,$csrfProd);
    check321session($devOk===false&&session_id()!==$prodId&&!isset($_SESSION['gebruiker']),'zelfde tenantkey met andere installatiebinding wordt fail-closed verworpen');
    session_write_close();

    // Een historische geauthenticeerde sessie zonder installatiebinding wordt
    // na deze hardening bewust niet gemigreerd maar ingetrokken.
    $legacyId=nieuwe321session();
    $_SESSION=['tenant_key'=>'rc045','csrf'=>'legacy','gebruiker'=>'oude-user','user_session_version'=>1];
    session_write_close();open321session($legacyId);$csrfLegacy='legacy';
    $legacyOk=authSessionTenantBewaak('rc045',$bindingA,$csrfLegacy);
    check321session($legacyOk===false&&session_id()!==$legacyId&&!isset($_SESSION['gebruiker']),'oude geauthenticeerde sessie zonder installatiebinding wordt ingetrokken');
    session_write_close();

    // Anonieme sessies hebben nog geen autorisatie en mogen in-place worden
    // gebonden; zo ontstaat geen onnodige cookie churn op het loginformulier.
    $anonId=nieuwe321session();$_SESSION=['csrf'=>'anon'];$csrfAnon='anon';
    $anonOk=authSessionTenantBewaak('tenant-d',$bindingA,$csrfAnon);
    check321session($anonOk===true&&session_id()===$anonId&&($_SESSION['tenant_key']??'')==='tenant-d'&&($_SESSION['installation_binding']??'')===$bindingA,'anonieme sessie wordt veilig in-place aan tenant en installatie gebonden');
    session_write_close();

    $malformedId=nieuwe321session();$_SESSION=['tenant_key'=>['tenant-e'],'installation_binding'=>$bindingA,'csrf'=>'x','gebruiker'=>'mallory'];$csrfMalformed='x';
    $malformedOk=authSessionTenantBewaak('tenant-e',$bindingA,$csrfMalformed);
    check321session($malformedOk===false&&session_id()!==$malformedId&&!isset($_SESSION['gebruiker']),'ongeldige tenantbinding wordt fail-closed vervangen');
    session_write_close();

    check321session(authSessionTenantSleutel(['vereniging'=>['sleutel'=>'Tenant-X']])==='tenant-x','sessiebinding gebruikt genormaliseerde tenantkey');

    // Twee standalone installaties met dezelfde tenantkey en URL krijgen door
    // hun eigen projectroot alsnog verschillende namespaces/save paths.
    $installA=$tmp.'/prod';$installB=$tmp.'/dev';@mkdir($installA,0700,true);@mkdir($installB,0700,true);
    $cfg=['vereniging'=>['sleutel'=>'rc045','site_url'=>'https://rc045.nl']];
    $contextA=authStorageSessieContext($cfg,$installA,null);$contextB=authStorageSessieContext($cfg,$installB,null);
    check321session(($contextA['name']??'')!==($contextB['name']??'')&&($contextA['path']??'')!==($contextB['path']??'')&&($contextA['binding']??'')!==($contextB['binding']??''),'standalone PROD en DEV krijgen installatie-unieke session namespace, path en binding');
}finally{
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    rrmdir321session($tmp);
}

$ok=0;$fout=0;foreach($checks as[$cond,$label]){if($cond){$ok++;echo"OK: {$label}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$label}\n");}}
echo"Phase 3.2.1 session tenant/installatie binding: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
