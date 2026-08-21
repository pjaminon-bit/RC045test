<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function check31(bool $cond,string $label): void{global$ok,$fout;if($cond){$ok++;echo"OK: $label\n";}else{$fout++;fwrite(STDERR,"FOUT: $label\n");}}
function rrmdir31(string $dir): void{if(!is_dir($dir))return;foreach(scandir($dir)?:[] as $item){if($item==='.'||$item==='..')continue;$pad=$dir.DIRECTORY_SEPARATOR.$item;if(is_dir($pad))rrmdir31($pad);else@unlink($pad);}@rmdir($dir);}

$tmp=sys_get_temp_dir().DIRECTORY_SEPARATOR.'rc045-phase31-'.bin2hex(random_bytes(4));
@mkdir($tmp,0750,true);
try{
    $runtime=file_get_contents($root.'/app/core/tenant-runtime.php')?:'';
    $siteConfig=file_get_contents($root.'/site-config.php')?:'';
    $store=file_get_contents($root.'/app/storage/private-store.php')?:'';
    $slot=file_get_contents($root.'/app/data-slot.php')?:'';
    check31(strpos($runtime,'VERENIGING_CONFIG_FILE')!==false,'runtime ondersteunt extern configpad');
    check31(strpos($runtime,'VERENIGING_PRIVATE_ROOT')!==false,'runtime ondersteunt externe private root');
    check31(strpos($siteConfig,'tenantRuntimeExternConfigPad()')!==false,'site-config gebruikt tenant runtime');
    check31(strpos($store,'privateStoreJsonRoot')!==false,'private store kent tenant-lokale JSON-root');
    check31(strpos($store,'privateStoreLegacyFallbackToegestaan')!==false,'tenantopslag heeft expliciete centrale legacy-fallbackguard');
    check31(strpos($slot,'tenantRuntimePrivateRoot')!==false,'dataslot volgt tenant private root');

    $aRoot=$tmp.'/tenant-a';$bRoot=$tmp.'/tenant-b';
    $aCfg=$tmp.'/a.php';$bCfg=$tmp.'/b.php';
    file_put_contents($aCfg,"<?php return ['vereniging'=>['sleutel'=>'tenant-a'],'opslag'=>['private_driver'=>'json','private_root'=>".var_export($aRoot,true)."]];\n");
    file_put_contents($bCfg,"<?php return ['vereniging'=>['sleutel'=>'tenant-b'],'opslag'=>['private_driver'=>'json','private_root'=>".var_export($bRoot,true)."]];\n");
    $runner=$tmp.'/runner.php';
    file_put_contents($runner,"<?php require ".var_export($root.'/app/storage/private-store.php',true)."; privateStoreSchrijf('probe',['tenant'=>privateStoreTenant()],static fn(\$d)=>false); echo json_encode(privateStoreLees('probe',static fn()=>['legacy'=>true]));\n");

    $run=function(string $cfg)use($runner):array{$cmd='VERENIGING_CONFIG_FILE='.escapeshellarg($cfg).' '.escapeshellcmd(PHP_BINARY).' '.escapeshellarg($runner);exec($cmd,$out,$code);return[$code,implode("\n",$out)];};
    [$codeA,$outA]=$run($aCfg);[$codeB,$outB]=$run($bCfg);
    check31($codeA===0&&$codeB===0,'twee tenantprocessen schrijven succesvol');
    $dataA=json_decode($outA,true);$dataB=json_decode($outB,true);
    check31(($dataA['tenant']??'')==='tenant-a','tenant A leest alleen eigen collectie');
    check31(($dataB['tenant']??'')==='tenant-b','tenant B leest alleen eigen collectie');
    check31(is_file($aRoot.'/collections/probe.json')&&is_file($bRoot.'/collections/probe.json'),'collecties staan in gescheiden private roots');
    check31(realpath($aRoot.'/collections/probe.json')!==realpath($bRoot.'/collections/probe.json'),'tenantbestanden zijn fysiek gescheiden');

    $emptyRoot=$tmp.'/empty-tenant';$emptyCfg=$tmp.'/empty.php';
    file_put_contents($emptyCfg,"<?php return ['vereniging'=>['sleutel'=>'empty'],'opslag'=>['private_driver'=>'json','private_root'=>".var_export($emptyRoot,true)."]];\n");
    $readRunner=$tmp.'/read.php';
    file_put_contents($readRunner,"<?php require ".var_export($root.'/app/storage/private-store.php',true)."; echo json_encode(privateStoreLees('ontbreekt',static fn()=>['legacy'=>'MAG-NIET']));\n");
    $cmd='VERENIGING_CONFIG_FILE='.escapeshellarg($emptyCfg).' '.escapeshellcmd(PHP_BINARY).' '.escapeshellarg($readRunner);exec($cmd,$out,$code);
    $empty=json_decode(implode("\n",$out),true);
    check31($code===0&&$empty===[],'lege tenantcollectie valt niet terug op legacy data');

    $invalidRunner=$tmp.'/invalid.php';file_put_contents($invalidRunner,"<?php require ".var_export($root.'/site-config.php',true).";\n");
    $cmd='VERENIGING_CONFIG_FILE='.escapeshellarg('relatief.php').' '.escapeshellcmd(PHP_BINARY).' '.escapeshellarg($invalidRunner).' 2>/dev/null';exec($cmd,$out,$code);
    check31($code!==0,'relatief extern configpad faalt gesloten');
}finally{rrmdir31($tmp);}

echo"Phase 3.1 tenant boundary: $ok OK, $fout fout(en)\n";exit($fout===0?0:1);