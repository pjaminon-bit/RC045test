<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function check32(bool $cond,string $label):void{global$ok,$fout;if($cond){$ok++;echo"OK: $label\n";}else{$fout++;fwrite(STDERR,"FOUT: $label\n");}}
function rrmdir32(string $dir):void{if(!is_dir($dir))return;foreach(scandir($dir)?:[] as $item){if($item==='.'||$item==='..')continue;$p=$dir.DIRECTORY_SEPARATOR.$item;if(is_dir($p))rrmdir32($p);else@unlink($p);}@rmdir($dir);}
function runKey32(string $script,string $key,string $base,bool $dryRun=true):array{$cmd=escapeshellcmd(PHP_BINARY).' '.escapeshellarg($script).' --key='.escapeshellarg($key).' --name='.escapeshellarg('Key Test').' --url='.escapeshellarg('https://key-test.example').' --root='.escapeshellarg($base);if($dryRun)$cmd.=' --dry-run';$out=[];exec($cmd.' 2>&1',$out,$code);return[$code,implode("\n",$out)];}

$tmp=sys_get_temp_dir().DIRECTORY_SEPARATOR.'rc045-phase32-'.bin2hex(random_bytes(4));
$base=$tmp.'/tenants';@mkdir($base,0750,true);
$script=$root.'/bin/provision-tenant.php';
try{
    $cmd=escapeshellcmd(PHP_BINARY).' '.escapeshellarg($script)
        .' --key=test-club --name='.escapeshellarg('Test Club')
        .' --url='.escapeshellarg('https://test.example')
        .' --root='.escapeshellarg($base);
    exec($cmd.' 2>&1',$out1,$code1);
    check32($code1===0,'provisioner maakt nieuwe tenant aan');
    $tenant=$base.'/test-club';$config=$tenant.'/config.php';$private=$tenant.'/private';
    check32(is_file($config)&&is_file($tenant.'/runtime.env')&&is_file($tenant.'/tenant.json'),'config, runtime.env en manifest bestaan');
    check32(is_dir($private.'/collections')&&is_dir($private.'/backups'),'private opslagstructuur bestaat');
    check32(is_dir($private.'/public-content'),'tenant-lokale publieke contentmap bestaat');
    check32(is_dir($private.'/auth')&&is_dir($private.'/audit')&&is_dir($private.'/security')&&is_dir($private.'/backups/auth'),'tenant-lokale auth/audit/security/backups mappen bestaan');
    check32(is_dir($private.'/sessions'),'tenant-lokale PHP sessiemap bestaat');
    check32(!is_file($private.'/auth/master.php'),'provisioner maakt bewust geen standaard mastercredential aan');
    $cfg=require$config;
    check32(($cfg['vereniging']['sleutel']??'')==='test-club','tenant key staat in config');
    check32(($cfg['opslag']['private_root']??'')===$private,'private root staat exact in config');
    $runtime=(string)file_get_contents($tenant.'/runtime.env');
    check32(strpos($runtime,"VERENIGING_REQUIRE_TENANT_CONFIG=1\n")!==false,'runtime.env verplicht expliciete tenantconfig');
    $manifest=json_decode((string)file_get_contents($tenant.'/tenant.json'),true);
    check32(is_array($manifest)&&($manifest['require_tenant_config']??false)===true,'manifest registreert fail-closed runtime-eis');

    $hashVoor=hash_file('sha256',$config);sleep(1);$out2=[];exec($cmd.' 2>&1',$out2,$code2);$hashNa=hash_file('sha256',$config);
    check32($code2===0&&$hashVoor===$hashNa,'tweede identieke run is idempotent');
    check32(strpos(implode("\n",$out2),'ONGEWIJZIGD')!==false,'idempotente run meldt ongewijzigd');

    $cmdConflict=escapeshellcmd(PHP_BINARY).' '.escapeshellarg($script)
        .' --key=test-club --name='.escapeshellarg('Andere Naam')
        .' --url='.escapeshellarg('https://test.example')
        .' --root='.escapeshellarg($base);
    $out3=[];exec($cmdConflict.' 2>&1',$out3,$code3);
    check32($code3!==0,'conflicterende bestaande tenant faalt zonder force');

    $out4=[];exec($cmdConflict.' --force 2>&1',$out4,$code4);$cfg2=require$config;
    check32($code4===0&&($cfg2['vereniging']['naam']??'')==='Andere Naam','force vervangt gecontroleerd tenantconfig');

    // Deze twee checks moeten de rootbeveiliging testen, niet eerder stranden
    // op key-validatie. Daarom gebruiken ze bewust geldige tenantkeys.
    $out5=[];exec(escapeshellcmd(PHP_BINARY).' '.escapeshellarg($script).' --key=reltest --name=x --url=https://x.example --root=relatief 2>&1',$out5,$code5);
    check32($code5!==0&&str_contains(implode("\n",$out5),'absoluut pad'),'relatieve tenantroot wordt geweigerd door rootvalidatie');

    $projectRoot=$root.'/tenant-test-verboden';
    $out6=[];exec(escapeshellcmd(PHP_BINARY).' '.escapeshellarg($script).' --key=codetest --name=x --url=https://x.example --root='.escapeshellarg($projectRoot).' 2>&1',$out6,$code6);
    check32($code6!==0&&str_contains(implode("\n",$out6),'applicatie/documentroot'),'tenantroot binnen applicatiecode wordt geweigerd door padgrens');

    $dryBase=$tmp.'/dry';$out7=[];exec(escapeshellcmd(PHP_BINARY).' '.escapeshellarg($script).' --key=dry --name=Dry --url=https://dry.example --root='.escapeshellarg($dryBase).' --dry-run 2>&1',$out7,$code7);
    check32($code7===0&&!is_dir($dryBase.'/dry'),'dry-run schrijft niets');

    // Fase 3.2.1 optie 6: technische tenantidentiteiten worden nooit stil
    // genormaliseerd. Iedere niet-canonieke invoer moet vóór filesystemwrites
    // hard worden geweigerd.
    $keyBase=$tmp.'/key-validation';@mkdir($keyBase,0750,true);
    $ongeldig=[
        'Test-Club'=>'hoofdletters',
        'test club'=>'spatie',
        'test_club'=>'underscore',
        'test@club'=>'speciaal teken',
        '-testclub'=>'koppelteken vooraan',
        'testclub-'=>'koppelteken achteraan',
        'test--club'=>'dubbel koppelteken',
        ' testclub'=>'voorliggende whitespace',
        'testclub '=>'achterliggende whitespace',
        'ab'=>'te korte key',
        str_repeat('a',64)=>'te lange key',
        'default'=>'gereserveerde fallback-key',
        'téstclub'=>'niet-ASCII teken',
    ];
    foreach($ongeldig as $key=>$reden){
        [$codeKey,$outKey]=runKey32($script,$key,$keyBase,true);
        check32($codeKey!==0,"ongeldige tenant-key wordt geweigerd: {$reden}");
    }
    check32(count(array_diff(scandir($keyBase)?:[],['.','..']))===0,'ongeldige keys maken geen tenantmappen aan');

    [$codeMin,$outMin]=runKey32($script,'abc',$keyBase,true);
    check32($codeMin===0&&str_contains($outMin,'Tenant klaar: abc'),'minimum geldige key van 3 tekens wordt geaccepteerd');
    $maxKey='a'.str_repeat('b',61).'c';
    [$codeMax,$outMax]=runKey32($script,$maxKey,$keyBase,true);
    check32(strlen($maxKey)===63&&$codeMax===0&&str_contains($outMax,'Tenant klaar: '.$maxKey),'maximum geldige key van 63 tekens wordt geaccepteerd');
    [$codeCanon,$outCanon]=runKey32($script,'test-club-2026',$keyBase,true);
    check32($codeCanon===0&&str_contains($outCanon,'Tenant klaar: test-club-2026'),'canonieke lowercase key met koppeltekens blijft geldig');
}finally{rrmdir32($tmp);}

echo"Phase 3.2 provisioner: $ok OK, $fout fout(en)\n";exit($fout===0?0:1);
