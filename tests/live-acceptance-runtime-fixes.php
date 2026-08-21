<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function laf3(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}

$endpoint=(string)file_get_contents($root.'/public-content.php');
laf3(str_contains($endpoint,'tenantRuntimeExternConfigPad()')&&str_contains($endpoint,'tenantRuntimeConfigVerplicht()'),'standalonecompatibiliteit wordt vóór content-store resolutie vastgesteld');
laf3(str_contains($endpoint,'publicContentLegacyRoot() . DIRECTORY_SEPARATOR . $bestand')&&str_contains($endpoint,'http_response_code(204)'),'ontbrekende standalone override levert expliciet 204 vóór store-read');
laf3(str_contains($endpoint,'catch (Throwable $e)')&&str_contains($endpoint,"http_response_code(500)"),'echte store/configuratiefouten blijven zichtbaar als serverfout');

$launcher=sys_get_temp_dir().'/rc045-live-content-'.bin2hex(random_bytes(5)).'.php';
$code="<?php\nputenv('VERENIGING_CONFIG_FILE');putenv('VERENIGING_REQUIRE_TENANT_CONFIG');putenv('VERENIGING_PRIVATE_ROOT');\n"
    .'$_SERVER[\'REQUEST_METHOD\']=\'GET\';$_GET[\'key\']=\'homepage\';http_response_code(200);register_shutdown_function(static function(){echo \'STATUS=\'.http_response_code();});include '.var_export($root.'/public-content.php',true).';';
file_put_contents($launcher,$code);
$out=[];$exit=0;exec(escapeshellcmd(PHP_BINARY).' '.escapeshellarg($launcher).' 2>&1',$out,$exit);@unlink($launcher);
laf3($exit===0&&trim(implode("\n",$out))==='STATUS=204','standalone ontbrekende homepage override eindigt werkelijk als HTTP 204');

$leden=(string)file_get_contents($root.'/leden/index.php');
laf3(str_contains($leden,'.wrap button{min-height:36px')&&str_contains($leden,'padding:8px 14px'),'leden-loginbutton heeft minimaal 36px hoogte en bruikbare padding');

$isolatie=(string)file_get_contents($root.'/tests/phase321-public-content-isolation.php');
laf3(str_contains($isolatie,"trim(\$outMissing)==='STATUS=404'"),'externe tenant zonder dataset blijft regressiematig 404/fail-closed');

echo"Live acceptance runtime fixes: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
