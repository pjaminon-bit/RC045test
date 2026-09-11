<?php
$root = dirname(__DIR__);
require_once $root . '/app/core/http-file-validator.php';
$ok = 0;
$fout = 0;

function check267(bool $cond, string $label): void
{
    global $ok, $fout;
    if ($cond) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

function rrmdir267(string $pad): void
{
    if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        rrmdir267($pad . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($pad);
}

function request267(string $url, string $method = 'GET', array $headers = []): array
{
    $ctx = stream_context_create(['http'=>[
        'method'=>$method, 'ignore_errors'=>true, 'timeout'=>5,
        'header'=>implode("\r\n", $headers),
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    $regels = $http_response_header ?? [];
    $status = 0; $parsed = [];
    foreach ($regels as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) { $status=(int)$m[1]; continue; }
        $pos = strpos($line, ':'); if ($pos === false) continue;
        $parsed[strtolower(trim(substr($line,0,$pos)))] = trim(substr($line,$pos+1));
    }
    return ['status'=>$status, 'body'=>$body===false?'':$body, 'headers'=>$parsed];
}

function header267(array $r, string $naam): ?string
{
    $v=$r['headers'][strtolower($naam)]??null;
    return is_string($v)?$v:null;
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rc045-security-267-' . bin2hex(random_bytes(4));
$privateRoot = $tmp . '/private';
$assetRoot = $privateRoot . '/public-assets/fotoboek/groot';
$configPad = $tmp . '/tenant-config.php';
$serverLog = $tmp . '/php-server.log';
@mkdir($assetRoot, 0750, true);
$asset = $assetRoot . '/groot.mp4';
$grootte = 64 * 1024 * 1024;
$h = @fopen($asset, 'w+b');
if (!is_resource($h) || !ftruncate($h, $grootte)) {
    if (is_resource($h)) fclose($h);
    rrmdir267($tmp);
    fwrite(STDERR, "FOUT: groot testbestand kon niet worden aangemaakt\n");
    exit(1);
}
fseek($h, 0); fwrite($h, 'A');
fseek($h, $grootte - 1); fwrite($h, 'Z');
fclose($h);

check267(filesize($asset) === $grootte, 'testasset is aantoonbaar 64 MiB groot');
$direct = fopen($asset, 'rb');
$positieVoor = ftell($direct);
$validator = httpFileValidatorVoorHandle($direct);
$positieNa = ftell($direct);
check267($positieVoor === 0 && $positieNa === 0, 'validatorberekening leest nul assetbytes en verplaatst de filepointer niet');
check267(is_array($validator) && ($validator['size'] ?? -1) === $grootte, 'validator haalt grootte uitsluitend uit handlemetadata');
check267(is_array($validator) && preg_match('/^"asset-v1-[0-9a-f]{64}"$/D', (string)($validator['etag']??'')) === 1, 'validator levert sterke versiegebonden ETag');
fclose($direct);

$gatewayBron = (string)file_get_contents($root . '/public-asset.php');
check267(str_contains($gatewayBron, 'httpFileOpenMetValidator($pad)'), 'publieke gateway gebruikt centrale O(1) handlevalidator');
check267(!str_contains($gatewayBron, 'hash_update_stream') && !str_contains($gatewayBron, 'hash_file('), 'publieke gateway bevat geen volledige filehash-operatie');

$config = [
    'vereniging'=>[
        'sleutel'=>'groot','naam'=>'Groot','volledige_naam'=>'Groot',
        'site_url'=>'http://127.0.0.1','timezone'=>'Europe/Amsterdam','standaard_taal'=>'nl',
    ],
    'opslag'=>[
        'private_driver'=>'json','private_root'=>$privateRoot,
        'pdo'=>['dsn'=>'','user'=>'','password'=>''],
    ],
];
file_put_contents($configPad, "<?php\nreturn " . var_export($config, true) . ";\n");

$socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if ($socket === false) { rrmdir267($tmp); fwrite(STDERR,"FOUT: vrije testpoort ontbreekt\n"); exit(1); }
$name=(string)stream_socket_get_name($socket,false); fclose($socket);
$colon=strrpos($name,':'); $port=$colon===false?0:(int)substr($name,$colon+1);
if($port<1){rrmdir267($tmp);fwrite(STDERR,"FOUT: ongeldige testpoort\n");exit(1);}

$env=getenv();if(!is_array($env))$env=[];
$env['VERENIGING_REQUIRE_TENANT_CONFIG']='1';
$env['VERENIGING_CONFIG_FILE']=$configPad;
$env['VERENIGING_PRIVATE_ROOT']=$privateRoot;
$desc=[0=>['pipe','r'],1=>['file',$serverLog,'a'],2=>['file',$serverLog,'a']];
$server=@proc_open([PHP_BINARY,'-d','display_errors=0','-S','127.0.0.1:'.$port,'-t',$root],$desc,$pipes,$root,$env,['bypass_shell'=>true]);
if(!is_resource($server)){rrmdir267($tmp);fwrite(STDERR,"FOUT: testserver startte niet\n");exit(1);}
if(isset($pipes[0])&&is_resource($pipes[0]))fclose($pipes[0]);

try {
    $gereed=false;
    for($i=0;$i<50;$i++){$p=@fsockopen('127.0.0.1',$port,$e1,$e2,0.1);if(is_resource($p)){fclose($p);$gereed=true;break;}usleep(50000);}
    check267($gereed,'lokale gatewaytestserver start');
    if($gereed){
        $url='http://127.0.0.1:'.$port.'/public-asset.php?'.http_build_query(['scope'=>'fotoboek','path'=>'groot/groot.mp4']);
        $head=request267($url,'HEAD');
        $etag=header267($head,'ETag');
        check267($head['status']===200&&$head['body']==='','HEAD op 64 MiB asset blijft bodyloos');
        check267(header267($head,'Content-Length')===(string)$grootte,'HEAD rapporteert volledige assetgrootte zonder bodyread');
        check267(is_string($etag)&&preg_match('/^"asset-v1-[0-9a-f]{64}"$/D',$etag)===1,'HEAD retourneert O(1) validator');

        $notModified=request267($url,'GET',['If-None-Match: '.$etag]);
        check267($notModified['status']===304&&$notModified['body']==='','304 op 64 MiB asset retourneert geen body');
        check267(header267($notModified,'ETag')===$etag,'304 behoudt dezelfde validator');

        $range=request267($url,'GET',['Range: bytes='.($grootte-1).'-'.($grootte-1)]);
        check267($range['status']===206&&$range['body']==='Z','1-byte range leest uitsluitend gevraagde laatste byte');
        check267(header267($range,'Content-Length')==='1','1-byte range heeft begrensde Content-Length');
        check267(header267($range,'Content-Range')==='bytes '.($grootte-1).'-'.($grootte-1).'/'.$grootte,'1-byte range houdt correcte Content-Range');
    }
} finally {
    @proc_terminate($server); @proc_close($server); rrmdir267($tmp);
}

if($fout>0){fwrite(STDERR,"security-267 public-asset cost: {$fout} fout(en), {$ok} checks geslaagd\n");exit(1);}
echo "security-267 public-asset cost: {$ok} checks geslaagd\n";
