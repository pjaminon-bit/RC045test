<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function check321(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) {
        $ok++;
        echo "OK: {$label}\n";
    } else {
        $fout++;
        fwrite(STDERR, "FOUT: {$label}\n");
    }
}

function rrmdir321(string $dir): void
{
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $pad = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($pad)) rrmdir321($pad); else @unlink($pad);
    }
    @rmdir($dir);
}

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "FOUT: pdo_sqlite ontbreekt.\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rc045-phase321-' . bin2hex(random_bytes(4));
@mkdir($tmp, 0750, true);
$db = $tmp . '/shared.sqlite';
$configA = $tmp . '/tenant-a.php';
$configB = $tmp . '/tenant-b.php';
$runner = $tmp . '/runner.php';
$markerB = $tmp . '/fallback-b-called';

try {
    $basisOpslag = [
        'private_driver' => 'pdo',
        'pdo' => ['dsn' => 'sqlite:' . $db, 'user' => '', 'password' => ''],
    ];
    file_put_contents($configA, "<?php return " . var_export([
        'vereniging' => ['sleutel' => 'tenant-a'],
        'opslag' => $basisOpslag,
    ], true) . ";\n");
    file_put_contents($configB, "<?php return " . var_export([
        'vereniging' => ['sleutel' => 'tenant-b'],
        'opslag' => $basisOpslag,
    ], true) . ";\n");

    $privateStore = var_export($root . '/app/storage/private-store.php', true);
    file_put_contents($runner, <<<'PHP'
<?php
require __PRIVATE_STORE__;
$modus = $argv[1] ?? 'read';
$fallback = static function (): array {
    $marker = (string)(getenv('FALLBACK_MARKER') ?: '');
    if ($marker !== '') file_put_contents($marker, 'AANGEROEPEN');
    return ['legacy_canary' => 'RC045-MAG-NIET-LEKKEN'];
};
if ($modus === 'write') {
    $waarde = $argv[2] ?? '';
    $ok = privateStoreSchrijf('probe', ['tenant' => privateStoreTenant(), 'waarde' => $waarde], static fn(array $data): bool => false);
    echo json_encode(['ok' => $ok, 'tenant' => privateStoreTenant()]);
    exit($ok ? 0 : 2);
}
echo json_encode(privateStoreLees('probe', $fallback));
PHP
    );
    $runnerInhoud = (string)file_get_contents($runner);
    file_put_contents($runner, str_replace('__PRIVATE_STORE__', $privateStore, $runnerInhoud));

    $run = static function (string $config, string $modus, ?string $waarde = null, ?string $marker = null) use ($runner): array {
        $delen = [
            'VERENIGING_CONFIG_FILE=' . escapeshellarg($config),
        ];
        if ($marker !== null) $delen[] = 'FALLBACK_MARKER=' . escapeshellarg($marker);
        $delen[] = escapeshellcmd(PHP_BINARY);
        $delen[] = escapeshellarg($runner);
        $delen[] = escapeshellarg($modus);
        if ($waarde !== null) $delen[] = escapeshellarg($waarde);
        $uitvoer = [];
        $code = 0;
        exec(implode(' ', $delen) . ' 2>&1', $uitvoer, $code);
        return [$code, implode("\n", $uitvoer)];
    };

    [$codeAWrite, $outAWrite] = $run($configA, 'write', 'A-GEHEIM');
    $aWrite = json_decode($outAWrite, true);
    check321($codeAWrite === 0 && ($aWrite['tenant'] ?? '') === 'tenant-a', 'tenant A schrijft in gedeelde PDO-store onder eigen tenant_key');

    [$codeBRead, $outBRead] = $run($configB, 'read', null, $markerB);
    $bRead = json_decode($outBRead, true);
    check321($codeBRead === 0 && $bRead === [], 'tenant B krijgt lege collectie wanneer alleen tenant A data heeft');
    check321(!is_file($markerB), 'legacy fallback-callback wordt voor externe PDO-tenant niet aangeroepen');
    check321(strpos($outBRead, 'RC045-MAG-NIET-LEKKEN') === false, 'legacy canary lekt niet naar externe tenant');

    [$codeBWrite] = $run($configB, 'write', 'B-EIGEN');
    check321($codeBWrite === 0, 'tenant B kan daarna eigen PDO-collectie schrijven');

    [$codeARead, $outARead] = $run($configA, 'read');
    $aRead = json_decode($outARead, true);
    check321($codeARead === 0 && ($aRead['tenant'] ?? '') === 'tenant-a' && ($aRead['waarde'] ?? '') === 'A-GEHEIM', 'tenant A behoudt eigen data na write van tenant B');

    [$codeBRead2, $outBRead2] = $run($configB, 'read');
    $bRead2 = json_decode($outBRead2, true);
    check321($codeBRead2 === 0 && ($bRead2['tenant'] ?? '') === 'tenant-b' && ($bRead2['waarde'] ?? '') === 'B-EIGEN', 'tenant B leest alleen eigen PDO-data');
} finally {
    rrmdir321($tmp);
}

echo "Phase 3.2.1 PDO isolation: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
