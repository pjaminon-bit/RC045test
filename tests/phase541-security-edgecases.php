<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function c541(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) { $ok++; echo "OK: {$label}\n"; return; }
    $fout++; fwrite(STDERR, "FOUT: {$label}\n");
}

function r541(array $argv): array
{
    $spec=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
    $p=proc_open($argv,$spec,$pipes,null,null,['bypass_shell'=>true]);
    if(!is_resource($p))return[255,'','proc_open faalde'];
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);fclose($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[2]);
    return[proc_close($p),(string)$out,(string)$err];
}

function wis541(string $pad): void
{
    if(is_link($pad)||is_file($pad)){@unlink($pad);return;}
    if(!is_dir($pad))return;
    foreach(scandir($pad)?:[] as $x){if($x==='.'||$x==='..')continue;wis541($pad.DIRECTORY_SEPARATOR.$x);}
    @rmdir($pad);
}

$tmp=sys_get_temp_dir().'/rc045-phase541-'.bin2hex(random_bytes(5));
@mkdir($tmp.'/sessions',0700,true);
$users=$tmp.'/users.json';
$worker=$tmp.'/worker.php';
file_put_contents($worker, <<<'PHP'
<?php
$root=$argv[1];$sessionDir=$argv[2];$users=$argv[3];$binding=str_repeat('c',64);
session_save_path($sessionDir);session_start();
$_SESSION=['tenant_key'=>'audit-club','installation_binding'=>$binding,'csrf'=>str_repeat('b',64),'gebruiker'=>'edge','is_master'=>false,'user_session_version'=>1];
$authSiteConfig=['vereniging'=>['sleutel'=>'audit-club']];$authPaden=['tenant_private'=>true,'session_binding'=>$binding];
$csrfToken=(string)$_SESSION['csrf'];$ingelogd=true;$isMaster=false;$huidigeGebruiker='edge';$usersBestand=$users;$inlogFout='';
function laadGebruikers($pad){$x=json_decode((string)file_get_contents($pad),true);return is_array($x)?$x:[];}
require $root.'/app/auth-session-check.php';
echo json_encode(['ingelogd'=>$ingelogd,'fout'=>$inlogFout]);session_write_close();
PHP);

try {
    file_put_contents($users,json_encode([['gebruikersnaam'=>'edge','hash'=>'x','sessie_versie'=>1,'actief'=>true,'capabilities'=>['members.view']]]));
    [$c1,$o1]=r541([PHP_BINARY,$worker,$root,$tmp.'/sessions',$users]);$j1=json_decode($o1,true);
    c541($c1===0&&($j1['ingelogd']??true)===false,'capabilities-only account wordt extern fail-closed geweigerd');

    file_put_contents($users,json_encode([['gebruikersnaam'=>'edge','hash'=>'x','sessie_versie'=>1,'actief'=>true,'capabilities'=>[],'tabs'=>'leden']]));
    [$c2,$o2]=r541([PHP_BINARY,$worker,$root,$tmp.'/sessions',$users]);$j2=json_decode($o2,true);
    c541($c2===0&&($j2['ingelogd']??true)===false,'niet-array tabs-profiel wordt extern geweigerd');

    file_put_contents($users,json_encode([['gebruikersnaam'=>'edge','hash'=>'x','sessie_versie'=>1,'actief'=>true,'capabilities'=>[],'tabs'=>[]]]));
    [$c3,$o3]=r541([PHP_BINARY,$worker,$root,$tmp.'/sessions',$users]);$j3=json_decode($o3,true);
    c541($c3===0&&($j3['ingelogd']??false)===true,'expliciet leeg tabs-array blijft een geldig beperkt account');

    $endpoint=(string)file_get_contents($root.'/aanmelden-ontvangst.php');
    c541(str_contains($endpoint,'aanmeldenPogingRegistreer('),'publiek aanmeldeindpunt gebruikt de geharde limiterhelper');
    c541(!str_contains($endpoint,"__DIR__.'/aanmelden-pogingen.php'"),'publiek aanmeldeindpunt bevat geen gedeelde root-limiter meer');

    $gitignore=(string)file_get_contents($root.'/.gitignore');
    c541(str_contains($gitignore,"aanmelden-pogingen.php\n"),'legacy rate-limitstate is expliciet uitgesloten van Git');
} finally {
    wis541($tmp);
}

echo "Phase 5.4.1 security edgecases: {$ok} OK, {$fout} fout(en)\n";
exit($fout===0?0:1);