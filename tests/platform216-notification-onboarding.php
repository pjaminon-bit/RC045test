<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function c216o(bool $c,string $l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";return;}$fout++;fwrite(STDERR,"FOUT: {$l}\n");}
function wis216o(string $p):void{if(is_link($p)||is_file($p)){@unlink($p);return;}if(!is_dir($p))return;foreach(scandir($p)?:[] as $i){if($i==='.'||$i==='..')continue;wis216o($p.DIRECTORY_SEPARATOR.$i);}@rmdir($p);}
function run216o(string $cmd):array{$out=[];exec($cmd.' 2>&1',$out,$code);return[$code,implode("\n",$out)];}

$tmp=sys_get_temp_dir().'/issue216-onboarding-'.bin2hex(random_bytes(5));
$tenant=$tmp.'/pilot-club';$private=$tenant.'/private';@mkdir($private,0750,true);
$configPad=$tenant.'/config.php';
$config=[
    'vereniging'=>['sleutel'=>'pilot-club','naam'=>'Pilot Club','volledige_naam'=>'Pilot Club','site_url'=>'https://pilot.example.invalid','timezone'=>'Europe/Amsterdam'],
    'modules'=>['website'=>true,'aanmelden'=>true],
    'opslag'=>['private_driver'=>'json','private_root'=>$private,'pdo'=>['dsn'=>'','user'=>'','password'=>'']],
];
file_put_contents($configPad,"<?php\nreturn ".var_export($config,true).";\n");@chmod($configPad,0640);
$script=$root.'/bin/configure-tenant-notifications.php';
$base=escapeshellcmd(PHP_BINARY).' '.escapeshellarg($script)
    .' --config='.escapeshellarg($configPad)
    .' --from='.escapeshellarg('notificaties@pilot.example.invalid')
    .' --contact-to='.escapeshellarg('bestuur@pilot.example.invalid,secretaris@pilot.example.invalid')
    .' --membership-to='.escapeshellarg('leden@pilot.example.invalid')
    .' --smtp-host='.escapeshellarg('smtp.pilot.example.invalid')
    .' --smtp-port=587 --security=starttls --required';

try{
    $voor=hash_file('sha256',$configPad);[$dryCode,$dryOut]=run216o($base.' --dry-run');$na=hash_file('sha256',$configPad);
    c216o($dryCode===0&&str_contains($dryOut,'DRY-RUN'),'notification onboarding ondersteunt dry-run');
    c216o($voor===$na&&!is_dir($private.'/secrets'),'dry-run wijzigt config of private secretstructuur niet');

    [$code,$out]=run216o($base);
    c216o($code===0&&str_contains($out,'GEREED'),'notification onboarding schrijft niet-geheime transportconfig');
    c216o(is_dir($private.'/secrets'),'onboarding maakt tenantprivate secretmap aan');
    $credentials=$private.'/secrets/notifications-smtp.json';
    c216o(!file_exists($credentials)&&str_contains($out,$credentials),'onboarding maakt of toont geen credentialinhoud maar noemt alleen doelpad');

    $cfg=require$configPad;
    c216o(($cfg['notificaties']['enabled']??false)===true&&($cfg['notificaties']['required']??false)===true,'pilotconfig activeert expliciet enabled+required');
    c216o(($cfg['notificaties']['provider']??'')==='smtp'&&($cfg['notificaties']['smtp']['security']??'')==='starttls','onboarding bindt generieke SMTP-provider met TLS');
    c216o(($cfg['notificaties']['smtp']['credentials_file']??'')===$credentials,'tenantconfig verwijst alleen naar private credentialsfile');
    c216o(($cfg['notificaties']['recipients']['contact.received']??[])===['bestuur@pilot.example.invalid','secretaris@pilot.example.invalid'],'meerdere contactontvangers worden tenantgebonden opgeslagen');
    $notificatieRaw=var_export((array)($cfg['notificaties']??[]),true);
    c216o(!str_contains($notificatieRaw,'dummy-secret')&&!str_contains($notificatieRaw,"'password' =>")&&!str_contains($notificatieRaw,"'username' =>"),'notificatieconfig bevat geen SMTP-secretvelden');

    [$repeatCode,$repeatOut]=run216o($base);
    c216o($repeatCode===0&&str_contains($repeatOut,'GEREED')&&!file_exists($credentials),'herhaalde onboarding blijft secretvrij en idempotent qua contract');

    $bad=$base;
    $bad=preg_replace('/ --from=[^ ]+/', ' --from='.escapeshellarg("bad@example.invalid\nBcc:evil@example.invalid"), $bad, 1);
    [$badCode,$badOut]=run216o((string)$bad);
    c216o($badCode!==0&&str_contains($badOut,'--from is geen veilig e-mailadres'),'headerinjectie wordt vóór configwrite geweigerd');

    $checkScript=$root.'/bin/check-tenant-notifications.php';
    $env='VERENIGING_REQUIRE_TENANT_CONFIG=1 VERENIGING_CONFIG_FILE='.escapeshellarg($configPad).' VERENIGING_PRIVATE_ROOT='.escapeshellarg($private).' ';
    [$checkCode,$checkOut]=run216o($env.escapeshellcmd(PHP_BINARY).' '.escapeshellarg($checkScript));
    c216o($checkCode===1&&str_contains($checkOut,'credentials_missing'),'required tenant zonder credentials faalt readiness duidelijk');
    c216o(!str_contains($checkOut,'password')&&!str_contains($checkOut,'username'),'readinessoutput lekt geen credentialvelden');

    file_put_contents($credentials,json_encode(['username'=>'pilot-user','password'=>'dummy-test-secret'],JSON_THROW_ON_ERROR));@chmod($credentials,0600);
    [$readyCode,$readyOut]=run216o($env.escapeshellcmd(PHP_BINARY).' '.escapeshellarg($checkScript));
    c216o($readyCode===0&&str_contains($readyOut,'"config_state": "ready"'),'geldige private credentials maken readiness groen zonder netwerkcall');
    c216o(!str_contains($readyOut,'pilot-user')&&!str_contains($readyOut,'dummy-test-secret'),'readinessoutput blijft secretvrij wanneer credentials bestaan');
}finally{wis216o($tmp);}

echo"Issue #216 notification onboarding: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
