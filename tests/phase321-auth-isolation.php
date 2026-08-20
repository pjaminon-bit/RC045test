<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function check321auth(bool $cond,string $label):void{global$ok,$fout;if($cond){$ok++;echo"OK: $label\n";}else{$fout++;fwrite(STDERR,"FOUT: $label\n");}}
function rrmdir321auth(string $dir):void{if(!is_dir($dir))return;foreach(scandir($dir)?:[] as $item){if($item==='.'||$item==='..')continue;$p=$dir.DIRECTORY_SEPARATOR.$item;if(is_dir($p))rrmdir321auth($p);else@unlink($p);}@rmdir($dir);}
function run321auth(string $config,string $runner):array{$out=[];$code=0;$cmd='VERENIGING_REQUIRE_TENANT_CONFIG=1 VERENIGING_CONFIG_FILE='.escapeshellarg($config).' '.escapeshellcmd(PHP_BINARY).' '.escapeshellarg($runner).' 2>&1';exec($cmd,$out,$code);return[$code,implode("\n",$out)];}

$tmp=sys_get_temp_dir().DIRECTORY_SEPARATOR.'rc045-phase321-auth-'.bin2hex(random_bytes(5));
$legacyMaster=$root.'/beheer-config.php';$legacyUsers=$root.'/beheer-users.json';$legacyLog=$root.'/beheer-log.json';$legacyAttempts=$root.'/beheer-login-pogingen.json';
$legacyBestanden=[$legacyMaster,$legacyUsers,$legacyLog,$legacyAttempts];
foreach($legacyBestanden as $pad)if(file_exists($pad)){fwrite(STDERR,"FOUT: test weigert bestaand server-only bestand te overschrijven: $pad\n");exit(1);}
@mkdir($tmp,0750,true);
try{
    // Canarydata in de gedeelde projectroot. Externe tenants mogen deze onder
    // geen beding als fallback gebruiken.
    file_put_contents($legacyMaster,"<?php \$BEHEER_WACHTWOORD_HASH=password_hash('RC045-CANARY-MASTER', PASSWORD_DEFAULT);\n");
    file_put_contents($legacyUsers,json_encode([['gebruikersnaam'=>'rc045-canary','hash'=>password_hash('RC045-CANARY-USER',PASSWORD_DEFAULT)]],JSON_PRETTY_PRINT));
    file_put_contents($legacyLog,json_encode([['tijd'=>date('c'),'gebruiker'=>'rc045-canary','actie'=>'CANARY']],JSON_PRETTY_PRINT));
    file_put_contents($legacyAttempts,json_encode(['user:rc045-canary'=>[time()]],JSON_PRETTY_PRINT));

    $runner=$tmp.'/runner.php';
    file_put_contents($runner,<<<'PHP'
<?php
$_SERVER['REQUEST_METHOD']='GET';
$_SERVER['SCRIPT_NAME']='/beheer/index.php';
require getenv('TEST_PROJECT_ROOT').'/auth.php';
$users=laadGebruikers($usersBestand);
schrijfLog($logBestand,'test-runner','tenant-audit','');
schrijfLoginPogingen($loginPogingenBestand,['user:test-runner'=>[time()]]);
echo "RESULT=".json_encode([
  'config_ok'=>$configOk,
  'config'=>$configPad,
  'users'=>$usersBestand,
  'audit'=>$logBestand,
  'attempts'=>$loginPogingenBestand,
  'lock'=>$loginPogingenSlotBestand,
  'backups'=>$dataBackupMap,
  'usernames'=>array_values(array_map(static fn($u)=>(string)($u['gebruikersnaam']??''),$users)),
],JSON_UNESCAPED_SLASHES)."\n";
PHP);

    $maakTenant=function(string $key,bool $metAuth)use($tmp):array{
        $tenant=$tmp.'/'.$key;$private=$tenant.'/private';
        foreach([$tenant,$private,$private.'/auth',$private.'/audit',$private.'/security',$private.'/backups/auth',$private.'/collections'] as $d)@mkdir($d,0750,true);
        $cfg=$tenant.'/config.php';
        file_put_contents($cfg,"<?php return ".var_export(['vereniging'=>['sleutel'=>$key,'naam'=>$key],'opslag'=>['private_driver'=>'json','private_root'=>$private]],true).";\n");
        if($metAuth){
            file_put_contents($private.'/auth/master.php',"<?php \$BEHEER_WACHTWOORD_HASH=password_hash('MASTER-{$key}', PASSWORD_DEFAULT);\n");
            file_put_contents($private.'/auth/users.json',json_encode([['gebruikersnaam'=>$key.'-user','hash'=>password_hash('USER-'.$key,PASSWORD_DEFAULT),'actief'=>true,'sessie_versie'=>1]],JSON_PRETTY_PRINT));
        }
        return[$cfg,$private];
    };

    [$cfgA,$privateA]=$maakTenant('tenant-a',true);
    [$cfgB,$privateB]=$maakTenant('tenant-b',false);

    putenv('TEST_PROJECT_ROOT='.$root);
    [$codeA,$outA]=run321auth($cfgA,$runner);
    preg_match('/RESULT=(\{.*\})/',$outA,$mA);$resA=json_decode($mA[1]??'',true);
    check321auth($codeA===0&&is_array($resA),'tenant A auth runtime start succesvol');
    check321auth(($resA['config_ok']??false)===true,'tenant A gebruikt eigen masterconfig');
    check321auth(($resA['users']??'')===$privateA.'/auth/users.json','tenant A userspad is tenant-lokaal');
    check321auth(($resA['audit']??'')===$privateA.'/audit/log.json','tenant A auditpad is tenant-lokaal');
    check321auth(($resA['attempts']??'')===$privateA.'/security/login-attempts.json','tenant A lockoutdata is tenant-lokaal');
    check321auth(($resA['lock']??'')===$privateA.'/security/.login-attempts.lock','tenant A lockbestand is tenant-lokaal');
    check321auth(($resA['backups']??'')===$privateA.'/backups/auth','tenant A authbackups zijn tenant-lokaal');
    check321auth(($resA['usernames']??[])===['tenant-a-user'],'tenant A ziet uitsluitend eigen gebruiker');
    check321auth(is_file($privateA.'/audit/log.json')&&is_file($privateA.'/security/login-attempts.json'),'tenant A writes landen fysiek in eigen private root');

    [$codeB,$outB]=run321auth($cfgB,$runner);
    preg_match('/RESULT=(\{.*\})/',$outB,$mB);$resB=json_decode($mB[1]??'',true);
    check321auth($codeB===0&&is_array($resB),'tenant B auth runtime start succesvol zonder masterconfig');
    check321auth(($resB['config_ok']??true)===false,'tenant B zonder eigen masterconfig blijft ongeconfigureerd');
    check321auth(($resB['usernames']??[])===[],'tenant B valt niet terug op RC045-canary gebruiker');
    check321auth(($resB['users']??'')===$privateB.'/auth/users.json','tenant B userspad blijft eigen private root');
    check321auth(is_file($privateB.'/audit/log.json')&&is_file($privateB.'/security/login-attempts.json'),'tenant B writes landen alleen in eigen private root');

    $legacyNa=json_decode((string)file_get_contents($legacyUsers),true);
    check321auth(($legacyNa[0]['gebruikersnaam']??'')==='rc045-canary','tenantwrites wijzigen gedeeld legacy usersbestand niet');
    $legacyLogNa=json_decode((string)file_get_contents($legacyLog),true);
    check321auth(count($legacyLogNa)===1&&($legacyLogNa[0]['actie']??'')==='CANARY','tenantwrites wijzigen gedeeld legacy auditlog niet');

    require_once $root.'/app/auth-storage.php';
    $legacy=authStoragePaden(['opslag'=>[]],$root);
    check321auth(($legacy['config']??'')===$root.'/beheer-config.php'&&($legacy['tenant_private']??true)===false,'standalone legacy authpad blijft compatibel');
}finally{
    foreach($legacyBestanden as $pad)@unlink($pad);
    rrmdir321auth($root.'/data-backups'); // alleen als test deze map heeft aangemaakt; CI checkout bevat hem normaal niet
    rrmdir321auth($tmp);
}

echo"Phase 3.2.1 auth isolation: $ok OK, $fout fout(en)\n";exit($fout===0?0:1);
