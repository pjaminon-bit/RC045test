<?php
$root = dirname(__DIR__);
$ok = 0; $fout = 0;
function check351m(bool $cond,string $label):void{global$ok,$fout;if($cond){$ok++;echo"OK: {$label}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$label}\n");}}
function rr351m(string $p):void{if(is_link($p)||is_file($p)){@unlink($p);return;}if(!is_dir($p))return;foreach(scandir($p)?:[] as $i){if($i==='.'||$i==='..')continue;rr351m($p.DIRECTORY_SEPARATOR.$i);}@rmdir($p);}
function run351m(array $args,?string $stdin=null):array{$d=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];$p=proc_open($args,$d,$pipes,null,null,['bypass_shell'=>true]);if(!is_resource($p))return[255,'proc_open'];if($stdin!==null)fwrite($pipes[0],$stdin);fclose($pipes[0]);$o=stream_get_contents($pipes[1]);fclose($pipes[1]);$e=stream_get_contents($pipes[2]);fclose($pipes[2]);return[proc_close($p),trim((string)$o."\n".(string)$e)];}

$tmp=sys_get_temp_dir().DIRECTORY_SEPARATOR.'rc045-master-generation-'.bin2hex(random_bytes(5));
$base=$tmp.'/tenants';@mkdir($base,0750,true);
try{
    [$pc]=run351m([PHP_BINARY,$root.'/bin/provision-tenant.php','--key=session-generation','--name=Session Generation','--url=https://session-generation.example','--root='.$base,'--modules=website']);
    $tenant=$base.'/session-generation';$cfgPad=$tenant.'/config.php';$private=$tenant.'/private';
    check351m($pc===0&&is_file($cfgPad),'tenant voor mastersessiegeneratie is geprovisioneerd');

    [$bc]=run351m([PHP_BINARY,$root.'/bin/bootstrap-tenant-admin.php','--config='.$cfgPad,'--password-stdin'],"Master-Generatie-Een-2026!\n");
    check351m($bc===0,'eerste masterhash is geactiveerd');

    require_once $root.'/app/auth-storage.php';
    $cfg=require $cfgPad;
    $contextVoor=authStorageSessieContext($cfg,$root,$private);
    $masterVoor=hash_file('sha256',$private.'/auth/master.php');
    check351m(is_string($contextVoor['name']??null)&&str_starts_with($contextVoor['name'],'VST'),'eerste mastergeneratie levert tenant-cookie namespace');
    check351m(($contextVoor['path']??'')===$private.'/sessions','tenant sessiecontext gebruikt uitsluitend private sessiepad');
    check351m(preg_match('/^[0-9a-f]{64}$/D',(string)($contextVoor['binding']??''))===1,'tenant sessiecontext levert cryptografische installatiebinding');

    file_put_contents($private.'/sessions/sess_canary','oude sessie');
    [$rc,$ro]=run351m([PHP_BINARY,$root.'/bin/bootstrap-tenant-admin.php','--config='.$cfgPad,'--password-stdin','--rotate'],"Master-Generatie-Twee-2026!\n");
    check351m($rc===0&&str_contains($ro,'Ingetrokken tenant-sessies: 1'),'rotatie trekt bestaande sessiebestand in');

    clearstatcache(true,$private.'/auth/master.php');
    $contextNa=authStorageSessieContext($cfg,$root,$private);
    $masterNa=hash_file('sha256',$private.'/auth/master.php');
    check351m(is_string($masterVoor)&&is_string($masterNa)&&!hash_equals($masterVoor,$masterNa),'masterconfig krijgt bij rotatie een nieuwe generatie');
    check351m(($contextVoor['name']??'')!==($contextNa['name']??''),'masterrotatie verandert sessiecookie-namespace en sluit loginrace met oude credential');
    check351m(($contextVoor['binding']??'')!==($contextNa['binding']??''),'masterrotatie verandert ook de installatiebinding');
    check351m(array_values(array_diff(scandir($private.'/sessions')?:[],['.','..']))===[],'oude tenant-sessiebestanden zijn defense-in-depth verwijderd');

    $contextNa2=authStorageSessieContext($cfg,$root,$private);
    check351m(($contextNa2['name']??'')===($contextNa['name']??'')&&($contextNa2['binding']??'')===($contextNa['binding']??''),'ongewijzigde mastergeneratie houdt sessiecontext deterministisch');
}finally{rr351m($tmp);}

echo"Phase 3.5.1 master session generation: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);