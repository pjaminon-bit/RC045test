<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function check268(bool $cond, string $label): void
{
    global $ok, $fout;
    if ($cond) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

function rrmdir268(string $pad): void
{
    if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        rrmdir268($pad . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($pad);
}

function request268(string $url, string $method='GET', array $headers=[]): array
{
    $ctx=stream_context_create(['http'=>[
        'method'=>$method,'ignore_errors'=>true,'timeout'=>5,'header'=>implode("\r\n",$headers),
    ]]);
    $body=@file_get_contents($url,false,$ctx);
    $regels=$http_response_header??[];$status=0;$parsed=[];
    foreach($regels as $line){
        if(preg_match('#^HTTP/\S+\s+(\d{3})#',$line,$m)===1){$status=(int)$m[1];continue;}
        $p=strpos($line,':');if($p===false)continue;
        $parsed[strtolower(trim(substr($line,0,$p)))]=trim(substr($line,$p+1));
    }
    return ['status'=>$status,'body'=>$body===false?'':$body,'headers'=>$parsed];
}

function header268(array $r,string $naam):?string
{
    $v=$r['headers'][strtolower($naam)]??null;return is_string($v)?$v:null;
}

$tmp=sys_get_temp_dir().DIRECTORY_SEPARATOR.'rc045-functioneel-268-'.bin2hex(random_bytes(4));
$privateRoot=$tmp.'/private';
$brandingRoot=$privateRoot.'/public-assets/branding';
$configPad=$tmp.'/tenant-config.php';
$serverLog=$tmp.'/php-server.log';
@mkdir($brandingRoot,0750,true);
$pad=$brandingRoot.'/logo.jpg';
$oud='0123456789ABCDEF';
$nieuw='FEDCBA9876543210';
$mtime=1760000000;
file_put_contents($pad,$oud);touch($pad,$mtime);clearstatcache(true,$pad);

$config=[
    'vereniging'=>[
        'sleutel'=>'branding-test','naam'=>'Branding test','volledige_naam'=>'Branding test',
        'site_url'=>'http://127.0.0.1','timezone'=>'Europe/Amsterdam','standaard_taal'=>'nl',
    ],
    'opslag'=>[
        'private_driver'=>'json','private_root'=>$privateRoot,
        'pdo'=>['dsn'=>'','user'=>'','password'=>''],
    ],
];
file_put_contents($configPad,"<?php\nreturn ".var_export($config,true).";\n");

$socket=@stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);
if($socket===false){rrmdir268($tmp);fwrite(STDERR,"FOUT: vrije testpoort ontbreekt\n");exit(1);}
$name=(string)stream_socket_get_name($socket,false);fclose($socket);
$colon=strrpos($name,':');$port=$colon===false?0:(int)substr($name,$colon+1);
if($port<1){rrmdir268($tmp);fwrite(STDERR,"FOUT: ongeldige testpoort\n");exit(1);}

$env=getenv();if(!is_array($env))$env=[];
$env['VERENIGING_REQUIRE_TENANT_CONFIG']='1';
$env['VERENIGING_CONFIG_FILE']=$configPad;
$env['VERENIGING_PRIVATE_ROOT']=$privateRoot;
$desc=[0=>['pipe','r'],1=>['file',$serverLog,'a'],2=>['file',$serverLog,'a']];
$server=@proc_open([PHP_BINARY,'-d','display_errors=0','-S','127.0.0.1:'.$port,'-t',$root],$desc,$pipes,$root,$env,['bypass_shell'=>true]);
if(!is_resource($server)){rrmdir268($tmp);fwrite(STDERR,"FOUT: testserver startte niet\n");exit(1);}
if(isset($pipes[0])&&is_resource($pipes[0]))fclose($pipes[0]);

try{
    $gereed=false;
    for($i=0;$i<50;$i++){$p=@fsockopen('127.0.0.1',$port,$e1,$e2,0.1);if(is_resource($p)){fclose($p);$gereed=true;break;}usleep(50000);}
    check268($gereed,'lokale branding-gatewaytestserver start');
    if($gereed){
        $url='http://127.0.0.1:'.$port.'/branding-asset.php?'.http_build_query(['name'=>'logo.jpg']);
        $eerste=request268($url);
        $etagOud=header268($eerste,'ETag');
        check268($eerste['status']===200&&$eerste['body']===$oud,'eerste branding-GET serveert oude versie');
        check268(is_string($etagOud)&&preg_match('/^"asset-v1-[0-9a-f]{64}"$/D',$etagOud)===1,'branding gebruikt gedeelde versiegebonden validator');

        $head=request268($url,'HEAD');
        check268($head['status']===200&&$head['body']==='','branding HEAD blijft bodyloos');
        check268(header268($head,'ETag')===$etagOud,'branding HEAD gebruikt dezelfde validator');
        check268(header268($head,'Content-Length')===(string)strlen($oud),'branding HEAD behoudt Content-Length');

        $stage=$pad.'.tmp.replace';
        file_put_contents($stage,$nieuw);touch($stage,$mtime);
        check268(filesize($stage)===filesize($pad),'brandingvervanging houdt exact dezelfde grootte');
        check268(filemtime($stage)===filemtime($pad),'brandingvervanging houdt exact dezelfde mtime');
        check268(@rename($stage,$pad),'brandingvervanging wordt atomair geactiveerd');
        clearstatcache(true,$pad);

        $na=request268($url,'GET',['If-None-Match: '.$etagOud]);
        $etagNieuw=header268($na,'ETag');
        check268($na['status']===200&&$na['body']===$nieuw,'oude brandingvalidator veroorzaakt na replacement geen 304');
        check268(is_string($etagNieuw)&&is_string($etagOud)&&!hash_equals($etagNieuw,$etagOud),'same-size/same-mtime brandingreplacement krijgt nieuwe ETag');

        $notModified=request268($url,'GET',['If-None-Match: '.$etagNieuw]);
        check268($notModified['status']===304&&$notModified['body']==='','nieuwe brandingvalidator geeft alleen voor actuele versie 304');
        check268(header268($notModified,'Cache-Control')==='public, max-age=3600, stale-while-revalidate=86400','branding cachepolicy blijft gelijk');
    }
}finally{
    @proc_terminate($server);@proc_close($server);rrmdir268($tmp);
}

if($fout>0){fwrite(STDERR,"functioneel-268 branding ETag: {$fout} fout(en), {$ok} checks geslaagd\n");exit(1);}
echo "functioneel-268 branding ETag: {$ok} checks geslaagd\n";
