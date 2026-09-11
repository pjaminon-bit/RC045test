<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;
function check259(bool $cond, string $label): void { global $ok,$fout; if($cond){$ok++;echo "OK: {$label}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$label}\n");} }
function rrmdir259(string $pad): void { if(is_link($pad)||is_file($pad)){@unlink($pad);return;} if(!is_dir($pad))return; foreach(scandir($pad)?:[] as $item){if($item==='.'||$item==='..')continue;rrmdir259($pad.DIRECTORY_SEPARATOR.$item);}@rmdir($pad); }
require_once $root . '/app/storage/private-filesystem.php';
$tmp=sys_get_temp_dir().DIRECTORY_SEPARATOR.'rc045-259-'.bin2hex(random_bytes(5));
$oudUmask=umask(0000);
try {
    $pad=$tmp.'/private/leden-data.php';
    check259(privateFilesystemAtomischSchrijf($pad,"<?php exit; ?>\n{}",0640),'private atomic writer slaagt onder umask 0000');
    check259(privateFilesystemMode(dirname($pad))===0750,'private directory is 0750');
    check259(privateFilesystemMode($pad)===0640,'private final is 0640');
    check259(glob($pad.'.tmp.*')===[],'tempfile is na rename opgeruimd');

    chmod($pad,0600);
    check259(privateFilesystemAtomischSchrijf($pad,"<?php exit; ?>\n{\"v\":2}",0640),'rewrite bestaand 0600 bestand slaagt');
    $mode=privateFilesystemMode($pad);
    check259($mode!==null&&($mode&0007)===0,'rewrite maakt bestand niet world-readable');

    $backupMap=dirname($pad).'/data-backups';
    mkdir($backupMap,0777,true);
    $backup=$backupMap.'/20260910_leden-data.php';
    file_put_contents($backup,'backup');
    chmod($backup,0666);
    check259(privateFilesystemAtomischSchrijf($pad,"<?php exit; ?>\n{\"v\":3}",0640),'write hardt legacy backup vóór mutatie');
    check259(privateFilesystemMode($backupMap)===0750,'legacy backupdirectory is 0750');
    check259(privateFilesystemMode($backup)===0640,'legacy backup is maximaal 0640');

    $bron0600=$tmp.'/private/source-0600.json';file_put_contents($bron0600,'{}');chmod($bron0600,0600);
    $kopie=$tmp.'/kopie/backup-source-0600.json';
    check259(privateFilesystemKopieerBackup($bron0600,$kopie),'centrale backupcopy slaagt');
    check259(privateFilesystemMode($kopie)===0600,'backupcopy is niet ruimer dan 0600 bron');

    $repoBron=(string)file_get_contents($root.'/app/storage/domein-repositories.php');
    $aanmeldBron=(string)file_get_contents($root.'/aanmeldingen-opslag.php');
    $publicBron=(string)file_get_contents($root.'/app/content/public-content-store.php');
    check259(str_contains($repoBron,'privateFilesystemAtomischSchrijf($pad, $voorloop . $json, 0640)'),'repoPhpJsonSchrijf gebruikt centrale private writer');
    check259(str_contains($aanmeldBron,'privateFilesystemAtomischSchrijf($pad,AANMELDINGEN_VOORLOOP.$json,0640)'),'aanmeldingenJsonSchrijf gebruikt centrale private writer');
    check259(
        str_contains($publicBron,'privateFilesystemBeveiligMap($map, true)')
        && str_contains($publicBron,'privateFilesystemAtomischSchrijf($pad, $json, 0640)'),
        'tenant publieke contentstore gebruikt centraal fail-closed modecontract'
    );
} finally { umask($oudUmask); rrmdir259($tmp); }
echo "Security #259 private file modes: {$ok} OK, {$fout} fout(en)\n";
exit($fout===0?0:1);
