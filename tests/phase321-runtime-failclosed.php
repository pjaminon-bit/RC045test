<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function check321runtime(bool $cond,string $label):void{global$ok,$fout;if($cond){$ok++;echo"OK: $label\n";}else{$fout++;fwrite(STDERR,"FOUT: $label\n");}}
function rrmdir321runtime(string $dir):void{if(!is_dir($dir))return;foreach(scandir($dir)?:[] as $item){if($item==='.'||$item==='..')continue;$p=$dir.DIRECTORY_SEPARATOR.$item;if(is_dir($p))rrmdir321runtime($p);else@unlink($p);}@rmdir($dir);}
function run321runtime(string $envPrefix,string $runner):array{$out=[];$code=0;$cmd=$envPrefix.' '.escapeshellcmd(PHP_BINARY).' '.escapeshellarg($runner).' 2>&1';exec($cmd,$out,$code);return[$code,implode("\n",$out)];}

$tmp=sys_get_temp_dir().DIRECTORY_SEPARATOR.'rc045-phase321-runtime-'.bin2hex(random_bytes(4));
@mkdir($tmp,0750,true);
try{
    $runtimeBron=(string)file_get_contents($root.'/app/core/tenant-runtime.php');
    check321runtime(strpos($runtimeBron,'http_response_code(503)')!==false,'HTTP-configuratiefout gebruikt service-unavailable status');
    check321runtime(strpos($runtimeBron,"echo 'Deze vereniging is tijdelijk niet beschikbaar.'")!==false,'HTTP-configuratiefout toont alleen generieke melding');
    check321runtime(strpos($runtimeBron,"error_log('[platform] tenant runtime configuratiefout: '")!==false,'interne oorzaak gaat naar serverlog');

    $runner=$tmp.'/runner.php';
    file_put_contents($runner,"<?php \$cfg=require ".var_export($root.'/site-config.php',true)."; echo (string)(\$cfg['vereniging']['sleutel']??'GEEN');\n");

    [$codeLegacy,$outLegacy]=run321runtime('env -u VERENIGING_CONFIG_FILE -u VERENIGING_REQUIRE_TENANT_CONFIG',$runner);
    check321runtime($codeLegacy===0&&trim($outLegacy)==='rc045','bestaande standalone modus blijft zonder vlag compatibel');

    [$codeUit,$outUit]=run321runtime('env -u VERENIGING_CONFIG_FILE VERENIGING_REQUIRE_TENANT_CONFIG=0',$runner);
    check321runtime($codeUit===0&&trim($outUit)==='rc045','expliciet uitgeschakelde eis behoudt legacyconfig');

    [$codeVerplicht,$outVerplicht]=run321runtime('env -u VERENIGING_CONFIG_FILE VERENIGING_REQUIRE_TENANT_CONFIG=1',$runner);
    check321runtime($codeVerplicht!==0,'verplichte tenantmodus faalt zonder configbestand');
    check321runtime(strpos($outVerplicht,'Tenantconfiguratie is verplicht')!==false,'CLI-fout maakt ontbrekende tenantconfig expliciet');
    check321runtime(strpos($outVerplicht,'rc045')===false,'fail-closed fout lekt geen succesvolle RC045 fallback');

    $tenantCfg=$tmp.'/tenant.php';
    file_put_contents($tenantCfg,"<?php return ['vereniging'=>['sleutel'=>'tenant-veilig','naam'=>'Tenant Veilig']];\n");
    $envGeldig='VERENIGING_REQUIRE_TENANT_CONFIG=1 VERENIGING_CONFIG_FILE='.escapeshellarg($tenantCfg);
    [$codeGeldig,$outGeldig]=run321runtime($envGeldig,$runner);
    check321runtime($codeGeldig===0&&trim($outGeldig)==='tenant-veilig','verplichte modus laadt expliciete tenantconfig');

    [$codeOngeldig,$outOngeldig]=run321runtime('env -u VERENIGING_CONFIG_FILE VERENIGING_REQUIRE_TENANT_CONFIG=misschien',$runner);
    check321runtime($codeOngeldig!==0,'ongeldige runtimevlag faalt gesloten');
    check321runtime(strpos($outOngeldig,'ongeldige booleaanse waarde')!==false,'ongeldige vlag geeft expliciete CLI-configuratiefout');

    $ontbreekt=$tmp.'/bestaat-niet.php';
    $envOntbreekt='VERENIGING_REQUIRE_TENANT_CONFIG=1 VERENIGING_CONFIG_FILE='.escapeshellarg($ontbreekt);
    [$codeOntbreekt,$outOntbreekt]=run321runtime($envOntbreekt,$runner);
    check321runtime($codeOntbreekt!==0&&strpos($outOntbreekt,'niet leesbaar')!==false,'verplicht maar onleesbaar configpad faalt gesloten');
}finally{rrmdir321runtime($tmp);}

echo"Phase 3.2.1 runtime fail-closed: $ok OK, $fout fout(en)\n";exit($fout===0?0:1);
