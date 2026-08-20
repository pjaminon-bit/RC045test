<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function check34(bool $cond, string $label): void
{
    global $ok, $fout;
    if ($cond) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

function rr34(string $pad): void
{
    if (is_link($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $kind = $pad . DIRECTORY_SEPARATOR . $item;
        if (is_dir($kind) && !is_link($kind)) rr34($kind); else @unlink($kind);
    }
    @rmdir($pad);
}

function run34(string $cmd): array
{
    $out = [];
    exec($cmd . ' 2>&1', $out, $code);
    return [$code, implode("\n", $out)];
}

function provision34(string $script, string $base, string $key): array
{
    $cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($script)
        . ' --key=' . escapeshellarg($key)
        . ' --name=' . escapeshellarg('Test ' . $key)
        . ' --url=' . escapeshellarg('https://' . $key . '.example')
        . ' --root=' . escapeshellarg($base);
    [$code, $out] = run34($cmd);
    if ($code !== 0) fwrite(STDERR, "PROVISION FOUT {$key}: {$out}\n");
    return [$base . '/' . $key, $code, $out];
}

function passwordFile34(string $tmp, string $naam, string $wachtwoord): string
{
    $pad = $tmp . '/' . $naam;
    file_put_contents($pad, $wachtwoord . "\n");
    @chmod($pad, 0600);
    return $pad;
}

function bootstrap34Run(string $script, string $config, ?string $stdinBestand = null, array $extra = []): array
{
    $cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($script)
        . ' --config=' . escapeshellarg($config);
    foreach ($extra as $optie) $cmd .= ' ' . $optie;
    if ($stdinBestand !== null) $cmd .= ' < ' . escapeshellarg($stdinBestand);
    return run34($cmd);
}

function masterHash34(string $pad): ?string
{
    if (!is_file($pad) || is_link($pad)) return null;
    return (static function (string $bestand): ?string {
        $BEHEER_WACHTWOORD_HASH = null;
        require $bestand;
        return is_string($BEHEER_WACHTWOORD_HASH) ? $BEHEER_WACHTWOORD_HASH : null;
    })($pad);
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vereniging-phase34-' . bin2hex(random_bytes(5));
$base = $tmp . '/tenants';
@mkdir($base, 0750, true);
$provisioner = $root . '/bin/provision-tenant.php';
$bootstrap = $root . '/bin/bootstrap-tenant-admin.php';

try {
    [$tenantA, $provA] = provision34($provisioner, $base, 'bootstrap-a');
    check34($provA === 0, 'testtenant wordt via de echte provisioner aangemaakt');
    $configA = $tenantA . '/config.php';
    $masterA = $tenantA . '/private/auth/master.php';
    check34(!file_exists($masterA), 'provisioner maakt nog steeds bewust geen standaardcredential');

    [$argCode, $argOut] = bootstrap34Run($bootstrap, $configA, null, ['--password=DIT-MAG-NOOIT-IN-ARGV']);
    check34($argCode !== 0 && str_contains($argOut, 'nooit als CLI-argument'), 'plaintext wachtwoord als CLI-argument wordt expliciet geweigerd');
    check34(!file_exists($masterA), 'geweigerde CLI-secret schrijft geen masterconfig');

    [$hashArgCode, $hashArgOut] = bootstrap34Run($bootstrap, $configA, null, ['--hash=nep']);
    check34($hashArgCode !== 0 && str_contains($hashArgOut, 'nooit als CLI-argument'), 'voorgehashte secret als CLI-argument wordt eveneens geweigerd');

    [$geenTtyCode, $geenTtyOut] = bootstrap34Run($bootstrap, $configA, '/dev/null');
    check34($geenTtyCode !== 0 && str_contains($geenTtyOut, 'Geen interactieve TTY'), 'niet-interactieve bootstrap zonder --password-stdin faalt gesloten');

    $zwak = passwordFile34($tmp, 'zwak.txt', 'te-kort');
    [$zwakCode, $zwakOut] = bootstrap34Run($bootstrap, $configA, $zwak, ['--password-stdin']);
    check34($zwakCode !== 0 && str_contains($zwakOut, 'minimaal 14'), 'te kort bootstrapwachtwoord wordt geweigerd');
    check34(!file_exists($masterA), 'zwak wachtwoord schrijft geen masterconfig');

    $wachtwoord1 = 'Correct-Horse-Battery-2026!';
    $pw1 = passwordFile34($tmp, 'pw1.txt', $wachtwoord1);
    [$eersteCode, $eersteOut] = bootstrap34Run($bootstrap, $configA, $pw1, ['--password-stdin']);
    check34($eersteCode === 0 && str_contains($eersteOut, 'Eerste tenantbeheerder geactiveerd: bootstrap-a'), 'eerste beheerder wordt succesvol geactiveerd via STDIN');
    $hash1 = masterHash34($masterA);
    check34(is_string($hash1) && password_verify($wachtwoord1, $hash1), 'master.php bevat een geldige password_hash voor het ingestelde wachtwoord');
    check34(!str_contains((string)file_get_contents($masterA), $wachtwoord1), 'master.php bevat het plaintext wachtwoord niet');
    check34(!str_contains($eersteOut, $wachtwoord1) && !str_contains($eersteOut, (string)$hash1), 'bootstrapoutput lekt wachtwoord noch hash');
    check34((fileperms($masterA) & 0777) === 0640, 'master.php krijgt server-only bestandsrechten 0640');

    [$dubbelCode, $dubbelOut] = bootstrap34Run($bootstrap, $configA, $pw1, ['--password-stdin']);
    $hashNaDubbel = masterHash34($masterA);
    check34($dubbelCode !== 0 && str_contains($dubbelOut, 'al een mastercredential'), 'tweede bootstrap zonder --rotate wordt geweigerd');
    check34($hashNaDubbel === $hash1, 'geweigerde tweede bootstrap verandert de bestaande hash niet');

    $wachtwoord2 = 'Nieuwe-Veilige-Beheerzin-2026!';
    $pw2 = passwordFile34($tmp, 'pw2.txt', $wachtwoord2);
    [$rotateCode, $rotateOut] = bootstrap34Run($bootstrap, $configA, $pw2, ['--password-stdin', '--rotate']);
    $hash2 = masterHash34($masterA);
    check34($rotateCode === 0 && str_contains($rotateOut, 'Mastercredential geroteerd: bootstrap-a'), 'bestaande credential kan alleen via expliciete --rotate worden vervangen');
    check34(is_string($hash2) && $hash2 !== $hash1 && password_verify($wachtwoord2, $hash2) && !password_verify($wachtwoord1, $hash2), 'rotatie activeert alleen het nieuwe wachtwoord');
    $backups = glob($tenantA . '/private/backups/auth/*_master.php') ?: [];
    check34(count($backups) === 1, 'rotatie maakt exact één server-only backup van de vorige masterconfig');
    $backupHash = count($backups) === 1 ? masterHash34($backups[0]) : null;
    check34(is_string($backupHash) && password_verify($wachtwoord1, $backupHash), 'rotatiebackup bevat uitsluitend de bruikbare vorige hash');
    check34(!str_contains((string)file_get_contents($backups[0] ?? $masterA), $wachtwoord1), 'rotatiebackup bevat geen plaintext secret');

    [$tenantFresh, $provFresh] = provision34($provisioner, $base, 'bootstrap-fresh');
    $configFresh = $tenantFresh . '/config.php';
    [$rotateFreshCode, $rotateFreshOut] = bootstrap34Run($bootstrap, $configFresh, $pw1, ['--password-stdin', '--rotate']);
    check34($provFresh === 0 && $rotateFreshCode !== 0 && str_contains($rotateFreshOut, 'alleen geldig'), '--rotate op een nog ongeconfigureerde tenant wordt geweigerd');

    [$tenantB, $provB] = provision34($provisioner, $base, 'bootstrap-b');
    $configB = $tenantB . '/config.php';
    $masterB = $tenantB . '/private/auth/master.php';
    file_put_contents($configB, (string)file_get_contents($configA));
    [$kopieCode, $kopieOut] = bootstrap34Run($bootstrap, $configB, $pw1, ['--password-stdin']);
    check34($provB === 0 && $kopieCode !== 0 && str_contains($kopieOut, 'niet bij dezelfde tenantroot'), 'fysiek gekopieerde config kan geen andere tenantroot besturen');
    check34(!file_exists($masterB), 'config-crossbind schrijft geen credential in slachtoffer-tenant');

    [$tenantC, $provC] = provision34($provisioner, $base, 'bootstrap-c');
    $configC = $tenantC . '/config.php';
    $manifestC = $tenantC . '/tenant.json';
    $manifestData = json_decode((string)file_get_contents($manifestC), true);
    $manifestData['tenant_key'] = 'andere-tenant';
    file_put_contents($manifestC, json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    [$manifestCode, $manifestOut] = bootstrap34Run($bootstrap, $configC, $pw1, ['--password-stdin']);
    check34($provC === 0 && $manifestCode !== 0 && str_contains($manifestOut, 'andere tenant-key'), 'gemanipuleerd tenantmanifest wordt op tenant-key binding geweigerd');
    check34(!file_exists($tenantC . '/private/auth/master.php'), 'manifestmismatch schrijft geen credential');

    [$tenantD, $provD] = provision34($provisioner, $base, 'bootstrap-d');
    $configD = $tenantD . '/config.php';
    $configLink = $tmp . '/config-link.php';
    $linkConfigOk = @symlink($configD, $configLink);
    if ($linkConfigOk) {
        [$linkCfgCode, $linkCfgOut] = bootstrap34Run($bootstrap, $configLink, $pw1, ['--password-stdin']);
        check34($linkCfgCode !== 0 && str_contains($linkCfgOut, 'symlink'), 'symlink naar tenantconfig wordt geweigerd');
    } else {
        check34(true, 'symlink naar tenantconfig niet ondersteund door testfilesystem; test overgeslagen');
    }

    $masterD = $tenantD . '/private/auth/master.php';
    $externCanary = $tmp . '/extern-master-canary.php';
    file_put_contents($externCanary, 'CANARY-ONGEWIJZIGD');
    $linkMasterOk = @symlink($externCanary, $masterD);
    if ($linkMasterOk) {
        [$linkMasterCode, $linkMasterOut] = bootstrap34Run($bootstrap, $configD, $pw1, ['--password-stdin']);
        check34($provD === 0 && $linkMasterCode !== 0 && str_contains($linkMasterOut, 'symlink'), 'symlink op masterdoel wordt vóór write geweigerd');
        check34((string)file_get_contents($externCanary) === 'CANARY-ONGEWIJZIGD', 'extern symlinkdoel blijft byte-inhoudelijk ongemoeid');
    } else {
        check34(true, 'symlink op masterdoel niet ondersteund door testfilesystem; test overgeslagen');
        check34(true, 'extern symlinkdoel niet geraakt omdat symlinktest niet ondersteund wordt');
    }

    $scriptBron = (string)file_get_contents($bootstrap);
    check34(str_contains($scriptBron, '--password-stdin') && !str_contains($scriptBron, "getopt('', ['password:"), 'bootstrapcontract heeft geen plaintext password-optie');
    check34(str_contains($scriptBron, 'password_hash($wachtwoord, PASSWORD_DEFAULT)'), 'bootstrap gebruikt PHP password_hash met PASSWORD_DEFAULT');
    check34(str_contains($scriptBron, 'flock($lock, LOCK_EX)') && str_contains($scriptBron, 'rename($tmp, $masterPad)'), 'bootstrap serializeert writes en plaatst masterconfig atomisch');
} finally {
    rr34($tmp);
}

echo "Phase 3.4 admin bootstrap: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
