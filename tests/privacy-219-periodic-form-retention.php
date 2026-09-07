<?php
$root = dirname(__DIR__);

function p219Fail(string $m): void { fwrite(STDERR, "FOUT: {$m}\n"); exit(1); }
function p219Check(bool $ok, string $m): void { if (!$ok) p219Fail($m); echo "OK: {$m}\n"; }
function p219Rm(string $pad): void {
    if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach ((array)scandir($pad) as $item) {
        if ($item === '.' || $item === '..') continue;
        p219Rm($pad . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($pad);
}
function p219Ids(array $data, string $key): array {
    return array_values(array_map(static fn($row) => is_array($row) ? (string)($row['id'] ?? '') : '', (array)($data[$key] ?? [])));
}

function p219Config(string $tmp, string $driver): array
{
    $private = $tmp . '/private';
    @mkdir($private . '/collections', 0750, true);
    $config = [
        'vereniging' => ['sleutel'=>'privacy219-'.$driver, 'timezone'=>'Europe/Amsterdam'],
        'privacy' => ['contactberichten_bewaardagen'=>30, 'aanmeldingen_bewaardagen'=>30],
        'opslag' => [
            'private_driver'=>$driver,
            'private_root'=>$private,
            'pdo'=>['dsn'=>$driver === 'pdo' ? 'sqlite:' . $tmp . '/private.sqlite' : '', 'user'=>'', 'password'=>''],
        ],
    ];
    $pad = $tmp . '/config.php';
    if (@file_put_contents($pad, "<?php\nreturn " . var_export($config, true) . ";\n", LOCK_EX) === false) p219Fail('testconfig kon niet worden geschreven');
    return [$pad, $private, $config];
}

function p219Env(string $configPad, string $private): void
{
    putenv('VERENIGING_REQUIRE_TENANT_CONFIG=1');
    putenv('VERENIGING_CONFIG_FILE=' . $configPad);
    putenv('VERENIGING_PRIVATE_ROOT=' . $private);
}

function p219Worker(string $driver): void
{
    global $root;
    if (!in_array($driver, ['json','pdo'], true)) p219Fail('ongeldige driver');
    if ($driver === 'pdo' && !extension_loaded('pdo_sqlite')) p219Fail('pdo_sqlite ontbreekt');
    $tmp = sys_get_temp_dir() . '/rc045test-privacy219-' . $driver . '-' . bin2hex(random_bytes(5));
    @mkdir($tmp, 0700, true);
    [$configPad, $private, $config] = p219Config($tmp, $driver);
    p219Env($configPad, $private);
    try {
        require_once $root . '/app/core/privacy-retention-runtime.php';
        $now = 2000000000;
        $old = gmdate('c', $now - 40 * 86400);
        $recent = gmdate('c', $now - 2 * 86400);
        p219Check(contactBerichtenSchrijf(['berichten'=>[
            ['id'=>'contact-old','status'=>'nieuw','aangemaakt'=>$old],
            ['id'=>'contact-recent','status'=>'nieuw','aangemaakt'=>$recent],
        ]]), $driver . ': contactseed opgeslagen');
        p219Check(aanmeldingenSchrijf(['aanmeldingen'=>[
            ['id'=>'membership-old','status'=>'nieuw','aangemaakt'=>$old],
            ['id'=>'membership-recent','status'=>'nieuw','aangemaakt'=>$recent],
        ]]), $driver . ': aanmeldingenseed opgeslagen');

        $eerste = privacyRetentionMaintenanceRun($config, $now);
        p219Check(($eerste['ran'] ?? false) === true, $driver . ': eerste scheduler-run voert maintenance uit');
        p219Check((int)($eerste['contact_removed'] ?? -1) === 1 && (int)($eerste['membership_removed'] ?? -1) === 1, $driver . ': verlopen formulier-PII wordt zonder beheerbezoek verwijderd');
        p219Check(p219Ids(contactBerichtenLees(), 'berichten') === ['contact-recent'], $driver . ': recente contactdata blijft behouden');
        p219Check(p219Ids(aanmeldingenLees(), 'aanmeldingen') === ['membership-recent'], $driver . ': recente aanmelddata blijft behouden');

        $tweede = privacyRetentionMaintenanceRun($config, $now + 60);
        p219Check(($tweede['ran'] ?? true) === false, $driver . ': tweede run op dezelfde lokale kalenderdag wordt overgeslagen');

        $volgendeDag = privacyRetentionMaintenanceRun($config, $now + 86400);
        p219Check(($volgendeDag['ran'] ?? false) === true, $driver . ': volgende kalenderdag voert maintenance opnieuw uit');
        $marker = privacyRetentionMarkerLees(privacyRetentionMarkerPad($config));
        p219Check(is_array($marker) && ($marker['tenant_key'] ?? '') === 'privacy219-'.$driver, $driver . ': succesvolle run schrijft tenantgebonden dagmarker');
    } finally {
        putenv('VERENIGING_REQUIRE_TENANT_CONFIG');
        putenv('VERENIGING_CONFIG_FILE');
        putenv('VERENIGING_PRIVATE_ROOT');
        p219Rm($tmp);
    }
}

function p219Probe(string $configPad, string $private, int $now): void
{
    global $root;
    p219Env($configPad, $private);
    require_once $root . '/app/core/privacy-retention-runtime.php';
    $config = require $root . '/site-config.php';
    $result = privacyRetentionMaintenanceRun($config, $now);
    echo json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";
}

if (($argv[1] ?? '') === '--worker') { p219Worker((string)($argv[2] ?? '')); exit(0); }
if (($argv[1] ?? '') === '--probe') { p219Probe((string)($argv[2] ?? ''), (string)($argv[3] ?? ''), (int)($argv[4] ?? 0)); exit(0); }

foreach (['json','pdo'] as $driver) {
    $cmd = [PHP_BINARY, __FILE__, '--worker', $driver];
    $proc = proc_open($cmd, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $root);
    if (!is_resource($proc)) p219Fail($driver . '-worker kon niet starten');
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($proc);
    if ($exit !== 0) p219Fail($driver . '-worker faalde: ' . trim((string)$err));
    echo $out;
}

// Concurrencycontract: een tweede proces moet aantoonbaar wachten op de
// tenant-private retentionlock en na vrijgave dezelfde dag als skip zien.
$tmp = sys_get_temp_dir() . '/rc045test-privacy219-lock-' . bin2hex(random_bytes(5));
@mkdir($tmp, 0700, true);
[$configPad, $private, $rawConfig] = p219Config($tmp, 'json');
p219Env($configPad, $private);
try {
    require_once $root . '/app/core/privacy-retention-runtime.php';
    $config = require $root . '/site-config.php';
    $now = 2100000000;
    $lockPad = privacyRetentionLockPad($config);
    $lock = fopen($lockPad, 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) p219Fail('concurrencytest kon retentionlock niet vasthouden');
    $proc = proc_open([PHP_BINARY, __FILE__, '--probe', $configPad, $private, (string)$now], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $root);
    if (!is_resource($proc)) p219Fail('concurrency-probe kon niet starten');
    fclose($pipes[0]);
    usleep(250000);
    $status = proc_get_status($proc);
    p219Check(!empty($status['running']), 'concurrerende scheduler-run wacht op tenant-private retentionlock');
    flock($lock, LOCK_UN); fclose($lock);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($proc);
    if ($exit !== 0) p219Fail('concurrency-probe faalde: ' . trim((string)$err));
    $result = json_decode(trim((string)$out), true);
    p219Check(is_array($result) && ($result['ran'] ?? false) === true, 'geblokkeerde scheduler-run voert na lockvrijgave één maintenance-run uit');
} finally {
    putenv('VERENIGING_REQUIRE_TENANT_CONFIG');
    putenv('VERENIGING_CONFIG_FILE');
    putenv('VERENIGING_PRIVATE_ROOT');
    p219Rm($tmp);
}

$health = (string)file_get_contents($root . '/healthz.php');
$requirePos = strpos($health, "require_once __DIR__ . '/app/core/privacy-retention-runtime.php'");
$dbPos = strpos($health, "SELECT 1");
$retPos = strpos($health, 'privacyRetentionMaintenanceRun($config)');
$notifyPos = strpos($health, 'tenantNotificationDispatchOne($config)');
p219Check($requirePos !== false && $dbPos !== false && $retPos !== false && $notifyPos !== false && $dbPos < $retPos && $retPos < $notifyPos, 'healthz koppelt dagelijkse PII-retentie na databaseprobe en vóór notificatiedispatch');

$helper = (string)file_get_contents($root . '/app/core/privacy-retention-runtime.php');
p219Check(str_contains($helper, 'flock($lock, LOCK_EX)') && strpos($helper, 'privacyRetentionMarkerSchrijf(') > strpos($helper, 'aanmeldingenOpschonenBewaartermijn()'), 'dagmarker wordt pas na beide succesvolle cleanups vastgelegd');
p219Check(!preg_match('/\b(?:naam|email|telefoon|bericht|geboortedatum)\b\s*=>/', $helper), 'retention-helper logt of bewaart geen formulier-PII');

echo "Issue #219 periodieke formulierretentie: OK\n";
