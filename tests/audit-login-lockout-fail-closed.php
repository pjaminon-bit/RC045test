<?php
$root = dirname(__DIR__);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/audit-login-lockout-fail-closed.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

ob_start();
require $root . '/auth.php';
ob_end_clean();

function allfcAssert(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, "FOUT: {$message}\n");
        exit(1);
    }
}

$tmp = sys_get_temp_dir() . '/vp-login-lockout-' . bin2hex(random_bytes(6));
if (!mkdir($tmp, 0700, true)) {
    throw new RuntimeException('Testmap kon niet worden gemaakt.');
}

// Gebruik voor alle scenario's een apart, schrijfbaar flock-bestand. Zo testen
// de storagecases de attempts-state zelf en niet per ongeluk alleen de lockfile.
$loginPogingenSlotBestand = $tmp . '/login-attempts.lock';
$limieten = ['user:audit' => 5, 'ip:' . hash('sha256', '127.0.0.1') => 20];
$venster = 15 * 60;

$missing = $tmp . '/missing.json';
allfcAssert(laadLoginPogingen($missing) === [], 'Een niet-bestaand attempts-bestand moet de enige geldige lege state zijn.');
allfcAssert(loginLockoutMinuten($missing, $limieten, $venster) === 0, 'Een nieuwe lege limiterstate moet login niet blokkeren.');
$missingJson = json_decode((string) file_get_contents($missing), true);
allfcAssert(is_array($missingJson), 'De eerste lockoutcheck moet een geldige JSON-state kunnen initialiseren.');

$valid = $tmp . '/valid.json';
allfcAssert(loginPogingRegistreren($valid, array_keys($limieten), $venster), 'Een normale mislukte poging moet duurzaam registreerbaar zijn.');
$validState = json_decode((string) file_get_contents($valid), true);
allfcAssert(is_array($validState) && count($validState['user:audit'] ?? []) === 1, 'De gebruikerslimiet moet de mislukte poging opslaan.');
allfcAssert(count($validState['ip:' . hash('sha256', '127.0.0.1')] ?? []) === 1, 'De IP-limiet moet dezelfde mislukte poging opslaan.');

$corrupt = $tmp . '/corrupt.json';
file_put_contents($corrupt, '{dit-is-geen-json');
$corruptVoor = file_get_contents($corrupt);
allfcAssert(laadLoginPogingen($corrupt) === null, 'Corrupte limiter-JSON moet een storagefout zijn, geen lege state.');
allfcAssert(loginLockoutMinuten($corrupt, $limieten, $venster) === null, 'Lockoutcheck moet fail-closed stoppen bij corrupte limiter-JSON.');
allfcAssert(file_get_contents($corrupt) === $corruptVoor, 'Een corrupte limiterstate mag niet stil worden overschreven met een lege state.');
allfcAssert(loginPogingRegistreren($corrupt, array_keys($limieten), $venster) === false, 'Pogingregistratie moet falen wanneer de bestaande limiterstate corrupt is.');
allfcAssert(file_get_contents($corrupt) === $corruptVoor, 'Mislukte registratie mag corrupte state niet resetten.');

$onleesbaar = $tmp . '/attempts-is-directory';
mkdir($onleesbaar, 0700);
allfcAssert(laadLoginPogingen($onleesbaar) === null, 'Een bestaand maar niet als bestand leesbaar attempts-pad moet als storagefout gelden.');
allfcAssert(loginLockoutMinuten($onleesbaar, $limieten, $venster) === null, 'Lockoutcheck moet fail-closed stoppen bij een onleesbaar attempts-pad.');

// Deterministische write-fout zonder afhankelijk te zijn van chmod/root:
// de parent van het gewenste attempts-bestand is bewust een regulier bestand.
$blokker = $tmp . '/parent-is-file';
file_put_contents($blokker, 'blokkeer parent directory');
$nietSchrijfbaar = $blokker . '/attempts.json';
allfcAssert(laadLoginPogingen($nietSchrijfbaar) === [], 'Een nog niet bestaand attempts-bestand begint inhoudelijk leeg.');
allfcAssert(loginLockoutMinuten($nietSchrijfbaar, $limieten, $venster) === null, 'Een mislukte prune/initialisatie-write moet de lockoutcheck fail-closed maken.');
allfcAssert(loginPogingRegistreren($nietSchrijfbaar, array_keys($limieten), $venster) === false, 'Een mislukte attempt-write moet expliciet false teruggeven.');

// Borg ook het HTTP-logincontract: een niet-persistente mislukte poging mag niet
// worden gepresenteerd alsof de brute-forcebescherming normaal functioneert.
$authBron = (string) file_get_contents($root . '/auth.php');
allfcAssert(str_contains($authBron, '$pogingOpgeslagen = loginPogingRegistreren('), 'Loginroute moet het resultaat van pogingregistratie controleren.');
allfcAssert(str_contains($authBron, "? 'Gebruikersnaam of wachtwoord onjuist.'") && str_contains($authBron, ": 'Inloggen is tijdelijk niet beschikbaar. Probeer het over een minuut opnieuw.'"), 'Loginroute moet bij niet-persistente pogingregistratie fail-closed antwoorden.');

session_write_close();
@unlink($missing);
@unlink($valid);
@unlink($corrupt);
@rmdir($onleesbaar);
@unlink($blokker);
@unlink($loginPogingenSlotBestand);
@rmdir($tmp);

echo "audit-login-lockout-fail-closed: OK\n";
