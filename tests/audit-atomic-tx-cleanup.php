<?php
require_once dirname(__DIR__) . '/app/core/atomic-file-transaction.php';
$ok=0;$fail=0;
function atc(bool $c,string $m):void{global$ok,$fail;if($c){echo"OK: $m\n";$ok++;}else{fwrite(STDERR,"FOUT: $m\n");$fail++;}}
$base=sys_get_temp_dir().'/vp-atomic-test-'.bin2hex(random_bytes(5));mkdir($base,0700,true);$file=$base.'/data.txt';file_put_contents($file,'oud');
try{
    $tx=atomicFileTxBegin([$file]);file_put_contents($file,'nieuw');atc(atomicFileTxCommit($tx),'commit cleanup slaagt');atc(($tx['committed']??false)===true&&($tx['closed']??false)===true,'commit en cleanup hebben aparte state');atc(file_get_contents($file)==='nieuw','commit behoudt nieuwe inhoud');
    file_put_contents($file,'oud2');$tx=atomicFileTxBegin([$file]);file_put_contents($file,'kapot');atc(atomicFileTxRollback($tx),'rollback slaagt');atc(($tx['rolled_back']??false)===true&&($tx['closed']??false)===true,'rollback en cleanup hebben aparte state');atc(file_get_contents($file)==='oud2','rollback herstelt oude inhoud');
    $source=file_get_contents(dirname(__DIR__).'/app/core/atomic-file-transaction.php');atc(str_contains($source,"if (!empty(\$tx['committed']) || !empty(\$tx['rolled_back']))"),'exceptionroute herhaalt geen businessactie na cleanupfout');
}finally{atomicFileTxVerwijder($base);}
echo"Atomic tx cleanup regression: $ok OK, $fail fout(en)\n";exit($fail===0?0:1);
