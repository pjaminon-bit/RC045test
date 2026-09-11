<?php
// Regression #266: tenant public-assets mogen permissieve umask of chmod-failure
// nooit als succesvolle publicatie accepteren.

function test266AssetsAssert(bool $ok, string $melding): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL security-266-public-assets: {$melding}\n");
        exit(1);
    }
}

function test266AssetsRm(string $pad): void
{
    if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach ((array) @scandir($pad) as $item) {
        if ($item === '.' || $item === '..') continue;
        test266AssetsRm($pad . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($pad);
}

function test266AssetsConfig(string $pad, string $privateRoot): void
{
    $config = [
        'vereniging' => [
            'sleutel' => 'security-266-assets',
            'naam' => 'Security 266 Assets',
            'volledige_naam' => 'Security 266 Assets',
            'site_url' => 'https://security-266-assets.example.invalid',
            'timezone' => 'Europe/Amsterdam',
            'standaard_taal' => 'nl',
        ],
        'opslag' => [
            'private_driver' => 'json',
            'private_root' => $privateRoot,
            'pdo' => ['dsn' => '', 'user' => '', 'password' => ''],
        ],
    ];
    test266AssetsAssert(file_put_contents($pad, "<?php\nreturn " . var_export($config, true) . ";\n") !== false, 'tenantconfig kon niet worden geschreven');
}

function test266AssetsRun(string $script, array $env): array
{
    $command = [PHP_BINARY, $script];
    $pipes = [];
    $proc = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, array_merge($_ENV, $env));
    test266AssetsAssert(is_resource($proc), 'subprocess kon niet worden gestart');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    return ['code' => $code, 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
}

$repo = dirname(__DIR__);
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rc045-security-266-assets-' . bin2hex(random_bytes(6));
$privateRoot = $tmp . DIRECTORY_SEPARATOR . 'private';
$configPad = $tmp . DIRECTORY_SEPARATOR . 'tenant.php';
test266AssetsAssert(@mkdir($privateRoot, 0750, true), 'private root kon niet worden aangemaakt');
@chmod($privateRoot, 0750);
test266AssetsConfig($configPad, $privateRoot);

try {
    $normal = $tmp . DIRECTORY_SEPARATOR . 'normal.php';
    $normalCode = <<<'PHP'
<?php
$repo = getenv('RC045_TEST_REPO');
putenv('VERENIGING_REQUIRE_TENANT_CONFIG=1');
putenv('VERENIGING_CONFIG_FILE=' . getenv('RC045_TEST_CONFIG'));
putenv('VERENIGING_PRIVATE_ROOT=' . getenv('RC045_TEST_PRIVATE'));
$oude = umask(0000);
require $repo . '/app/content/public-asset-store.php';
$sponsors = publicAssetMaakNamespaceMap('sponsors');
if (!is_string($sponsors)) { fwrite(STDERR, "namespace-failed\n"); exit(2); }
$file = $sponsors . '/mode-test.jpg';
if (file_put_contents($file, 'MODE-TEST') === false) { fwrite(STDERR, "file-write-failed\n"); exit(3); }
publicAssetBeveiligBestand($file);
clearstatcache(true, $file);
$parent = dirname($sponsors);
$out = [
    'parent_mode' => fileperms($parent) & 0777,
    'scope_mode' => fileperms($sponsors) & 0777,
    'file_mode' => fileperms($file) & 0777,
    'read' => publicAssetVeiligLeesPad('sponsors', 'mode-test.jpg'),
];
umask($oude);
echo json_encode($out, JSON_THROW_ON_ERROR);
PHP;
    test266AssetsAssert(file_put_contents($normal, $normalCode) !== false, 'normale child kon niet worden geschreven');
    $env = [
        'RC045_TEST_REPO' => $repo,
        'RC045_TEST_CONFIG' => $configPad,
        'RC045_TEST_PRIVATE' => $privateRoot,
    ];
    $normalResult = test266AssetsRun($normal, $env);
    $data = json_decode($normalResult['stdout'], true);
    test266AssetsAssert($normalResult['code'] === 0 && is_array($data), 'normale tenantassetrun faalde: ' . trim($normalResult['stderr']));
    test266AssetsAssert(($data['parent_mode'] ?? null) === 0750, 'public-assets parent is niet exact 0750 onder umask(0000)');
    test266AssetsAssert(($data['scope_mode'] ?? null) === 0750, 'assetnamespace is niet exact 0750 onder umask(0000)');
    test266AssetsAssert(($data['file_mode'] ?? null) === 0640, 'tenantasset is niet exact 0640 onder umask(0000)');
    test266AssetsAssert(($data['read'] ?? null) === $privateRoot . '/public-assets/sponsors/mode-test.jpg', 'geharde tenantasset blijft veilig leesbaar');

    // Voor de failure-run bestaan parent/scope/file al veilig. Zo testen we
    // afzonderlijk dat een geforceerde chmod-failure zowel directorygebruik als
    // bestandspublicatie blokkeert en het onbewezen bestand verwijdert.
    $scope = $privateRoot . DIRECTORY_SEPARATOR . 'public-assets' . DIRECTORY_SEPARATOR . 'sponsors';
    test266AssetsAssert(is_dir($scope), 'scope ontbreekt vóór failure-run');
    $failureFile = $scope . DIRECTORY_SEPARATOR . 'failure.jpg';
    test266AssetsAssert(file_put_contents($failureFile, 'FAILURE') !== false, 'failurefixture kon niet worden geschreven');
    @chmod($failureFile, 0640);

    $failure = $tmp . DIRECTORY_SEPARATOR . 'failure.php';
    $failureCode = <<<'PHP'
<?php
function privateFilesystemChmod(string $pad, int $mode): bool { return false; }
$repo = getenv('RC045_TEST_REPO');
putenv('VERENIGING_REQUIRE_TENANT_CONFIG=1');
putenv('VERENIGING_CONFIG_FILE=' . getenv('RC045_TEST_CONFIG'));
putenv('VERENIGING_PRIVATE_ROOT=' . getenv('RC045_TEST_PRIVATE'));
require $repo . '/app/content/public-asset-store.php';
if (publicAssetMaakNamespaceMap('sponsors') !== null) {
    fwrite(STDERR, "namespace-accepted-chmod-failure\n");
    exit(4);
}
$file = getenv('RC045_TEST_FAILURE_FILE');
$thrown = false;
try {
    publicAssetBeveiligBestand($file);
} catch (RuntimeException $e) {
    $thrown = true;
}
if (!$thrown) {
    fwrite(STDERR, "file-accepted-chmod-failure\n");
    exit(5);
}
if (file_exists($file)) {
    fwrite(STDERR, "failed-file-remained\n");
    exit(6);
}
echo "forced-failure-ok\n";
PHP;
    test266AssetsAssert(file_put_contents($failure, $failureCode) !== false, 'failure-child kon niet worden geschreven');
    $failureResult = test266AssetsRun($failure, $env + ['RC045_TEST_FAILURE_FILE' => $failureFile]);
    test266AssetsAssert($failureResult['code'] === 0 && str_contains($failureResult['stdout'], 'forced-failure-ok'), 'public-assets accepteerden chmod-failure: ' . trim($failureResult['stderr']));

    echo "OK security-266 tenant public asset modes fail-closed\n";
} finally {
    test266AssetsRm($tmp);
}
