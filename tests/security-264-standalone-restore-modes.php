<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function check264(bool $cond, string $label): void
{
    global $ok, $fout;
    if ($cond) {
        $ok++;
        echo "OK: {$label}\n";
    } else {
        $fout++;
        fwrite(STDERR, "FOUT: {$label}\n");
    }
}

function rrmdir264(string $pad): void
{
    if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        rrmdir264($pad . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($pad);
}

// De productiehelper maakt de snapshot vóór de restore-write. Deze stub bootst
// bewust een permissieve legacy-copy na; de private writer moet die daarna
// fail-closed naar het centrale private modecontract terugbrengen.
function maakDataBackup($pad, $backupMap, $bewaardagen, $maxPerBestand): void
{
    if (!file_exists($pad)) return;
    if (!is_dir($backupMap) && !mkdir($backupMap, 0777, true) && !is_dir($backupMap)) return;
    $doel = rtrim($backupMap, '/\\') . DIRECTORY_SEPARATOR . '2026-09-10_120000_000000_' . basename($pad);
    if (copy($pad, $doel)) chmod($doel, 0666);
}

require_once $root . '/app/storage/private-filesystem.php';

$beheerBron = (string) file_get_contents($root . '/beheer/backups.php');
$functie = null;
if (preg_match('~function buSchrijfBestand\(string \$pad, \$data, string \$type\): bool \{.*?\n\}\n(?=function buLeesBackupData)~s', $beheerBron, $match) === 1) {
    $functie = $match[0];
    eval($functie);
}
check264(is_string($functie) && function_exists('buSchrijfBestand'), 'echte productie-implementatie buSchrijfBestand is geladen');

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rc045-264-' . bin2hex(random_bytes(5));
mkdir($tmp, 0777, true);
$dataBackupMap = $tmp . '/data-backups';
$dataBackupBewaardagen = 90;
$dataBackupMaxPerBestand = 200;
$oudUmask = umask(0000);

try {
    $privatePad = $tmp . '/leden-data.php';
    $privateOud = "<?php exit; ?>\n{\"versie\":1}";
    file_put_contents($privatePad, $privateOud);
    chmod($privatePad, 0600);

    check264(
        function_exists('buSchrijfBestand') && buSchrijfBestand($privatePad, ['versie' => 2], 'phpjson'),
        'standalone private phpjson restore slaagt onder umask 0000'
    );
    check264(privateFilesystemMode($privatePad) === 0640, 'private restore final is exact 0640');
    check264(
        str_starts_with((string) file_get_contents($privatePad), "<?php exit; ?>\n{") && str_contains((string) file_get_contents($privatePad), '"versie": 2'),
        'private restore bewaart phpjson beschermingsvoorloop en payload'
    );
    check264(glob($privatePad . '.tmp.*') === [], 'private restore laat geen centrale tempfile achter');
    check264(glob($privatePad . '.restore.*') === [], 'private restore gebruikt geen legacy restore-tempfile');

    $privateBackups = glob($dataBackupMap . '/*_' . basename($privatePad)) ?: [];
    check264(count($privateBackups) === 1, 'pre-restore backup blijft aanwezig');
    if (count($privateBackups) === 1) {
        check264((string) file_get_contents($privateBackups[0]) === $privateOud, 'pre-restore backup bevat de toestand van vóór herstel');
        check264(privateFilesystemMode($privateBackups[0]) === 0600, 'pre-restore backup is niet ruimer dan bestaand 0600 bronbestand');
    }
    check264(privateFilesystemMode($dataBackupMap) === 0750, 'private restore hardt legacy backupdirectory naar 0750');

    $private0640 = $tmp . '/taken-data.php';
    file_put_contents($private0640, "<?php exit; ?>\n{\"versie\":1}");
    chmod($private0640, 0640);
    check264(buSchrijfBestand($private0640, ['versie' => 2], 'phpjson'), 'restore van bestaand 0640 private bestand slaagt');
    $mode0640 = privateFilesystemMode($private0640);
    check264($mode0640 === 0640 && ($mode0640 & 0007) === 0, 'bestaand 0640 bestand wordt door restore niet world-readable');

    $privateBron = (string) file_get_contents($root . '/app/storage/private-filesystem.php');
    $tempCheck = strpos($privateBron, 'privateFilesystemBeveiligBestand($tmp, $mode)');
    $rename = strpos($privateBron, '@rename($tmp, $pad)');
    $finalCheck = strpos($privateBron, 'return privateFilesystemBeveiligBestand($pad, $mode);');
    check264(
        $tempCheck !== false && $rename !== false && $finalCheck !== false && $tempCheck < $rename && $rename < $finalCheck,
        'centrale writer controleert tempfile vóór rename en final file erna'
    );
    check264(!privateFilesystemBeveiligBestand($tmp . '/bestaat-niet.php', 0640), 'modefailure kan niet als succesvolle private beveiliging gelden');

    $publiekeMap = $tmp . '/data';
    mkdir($publiekeMap, 0777, true);
    $publiekPad = $publiekeMap . '/homepage.json';
    file_put_contents($publiekPad, "{\"versie\":1}");
    chmod($publiekPad, 0644);

    check264(buSchrijfBestand($publiekPad, ['versie' => 2], 'json'), 'standalone publieke json restore blijft werken onder umask 0000');
    $publiekeMode = privateFilesystemMode($publiekPad);
    check264($publiekeMode !== 0640 && $publiekeMode !== null && ($publiekeMode & 0004) !== 0, 'publieke json restore valt niet onder private 0640-beleid');
    check264(!str_starts_with((string) file_get_contents($publiekPad), '<?php exit; ?>'), 'publieke json restore krijgt geen private phpjson-voorloop');

    check264(
        str_contains($beheerBron, "if (\$type === 'phpjson')") && str_contains($beheerBron, 'privateFilesystemAtomischSchrijf($pad, $inhoud, 0640)'),
        'beheer restore routeert alleen phpjson expliciet naar centrale private writer'
    );
} finally {
    umask($oudUmask);
    rrmdir264($tmp);
}

echo "Security #264 standalone restore modes: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
