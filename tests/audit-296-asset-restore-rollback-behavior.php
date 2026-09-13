<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function r296Check(bool $cond, string $label): void
{
    global $ok, $fout;
    if ($cond) {
        $ok++;
        echo "OK: {$label}\n";
        return;
    }
    $fout++;
    fwrite(STDERR, "FOUT: {$label}\n");
}

function r296Wis(string $pad): void
{
    if (is_link($pad) || is_file($pad)) {
        @unlink($pad);
        return;
    }
    if (!is_dir($pad)) return;
    foreach ((array) @scandir($pad) as $item) {
        if ($item === '.' || $item === '..') continue;
        r296Wis($pad . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($pad);
}

function r296Config(string $pad, string $private): void
{
    $config = [
        'vereniging' => [
            'sleutel' => 'rollback-test',
            'naam' => 'Rollback test',
            'volledige_naam' => 'Rollback test',
            'site_url' => 'https://rollback-test.example',
        ],
        'opslag' => [
            'private_driver' => 'json',
            'private_root' => $private,
            'backups' => [
                'bewaardagen' => 90,
                'max_per_item' => 5,
                'max_asset_snapshots' => 5,
                'max_asset_mb' => 50,
            ],
        ],
    ];
    file_put_contents($pad, "<?php\nreturn " . var_export($config, true) . ";\n");
}

function r296Run(string $worker, string $config, array $args): array
{
    $delen = [escapeshellcmd(PHP_BINARY), escapeshellarg($worker)];
    foreach ($args as $arg) $delen[] = escapeshellarg((string) $arg);
    $cmd = 'VERENIGING_REQUIRE_TENANT_CONFIG=1 VERENIGING_CONFIG_FILE=' . escapeshellarg($config) . ' ' . implode(' ', $delen);
    $regels = [];
    exec($cmd . ' 2>&1', $regels, $code);
    $raw = implode("\n", $regels);
    $laatste = $regels === [] ? '' : (string) end($regels);
    $json = json_decode($laatste, true);
    if ($code !== 0 || !is_array($json)) {
        fwrite(STDERR, "WORKER FOUT [" . implode(' ', array_map('strval', $args)) . "] code={$code}: {$raw}\n");
    }
    return [$code, is_array($json) ? $json : null, $raw];
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rc045-a296-' . bin2hex(random_bytes(4));
$private = $tmp . DIRECTORY_SEPARATOR . 'private';
$config = $tmp . DIRECTORY_SEPARATOR . 'tenant.php';
$worker = $tmp . DIRECTORY_SEPARATOR . 'worker.php';
@mkdir($private, 0750, true);
r296Config($config, $private);

$workerCode = <<<'PHP'
<?php
$root = $argv[1];
$actie = $argv[2] ?? '';

function r296Out(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

switch ($actie) {
    case 'init':
        require $root . '/app/content/public-asset-store.php';
        $map = publicAssetMaakNamespaceMap('sponsors');
        $pad = $map === null ? null : $map . DIRECTORY_SEPARATOR . 'sponsor-1.png';
        $geschreven = is_string($pad) && file_put_contents($pad, (string) ($argv[3] ?? '')) !== false;
        r296Out(['ok' => $geschreven, 'path' => $pad]);
        break;

    case 'modify':
        require $root . '/app/content/public-asset-store.php';
        $map = publicAssetNamespaceRoot('sponsors');
        $pad = $map === null ? null : $map . DIRECTORY_SEPARATOR . 'sponsor-1.png';
        $geschreven = is_string($pad) && is_dir($map) && file_put_contents($pad, (string) ($argv[3] ?? '')) !== false;
        r296Out(['ok' => $geschreven]);
        break;

    case 'snapshot':
        require $root . '/app/storage/tenant-backup-store.php';
        $pad = tenantBackupMaakAssetSnapshot('sponsors');
        r296Out(['ok' => $pad !== null, 'name' => $pad === null ? '' : basename($pad)]);
        break;

    case 'restore-modefail':
        $GLOBALS['r296_mode_fail_hits'] = 0;
        function privateFilesystemChmod(string $pad, int $mode): bool
        {
            $norm = rtrim(str_replace('\\', '/', $pad), '/');
            if (str_ends_with($norm, '/public-assets/sponsors')) {
                $GLOBALS['r296_mode_fail_hits']++;
                return false;
            }
            return @chmod($pad, $mode & 0777);
        }
        require $root . '/app/storage/tenant-backup-store.php';
        $fout = null;
        $resultaat = tenantBackupHerstelAssetSnapshot('sponsors', (string) ($argv[3] ?? ''), $fout);
        r296Out([
            'ok' => $resultaat,
            'error' => $fout,
            'mode_fail_hits' => (int) ($GLOBALS['r296_mode_fail_hits'] ?? 0),
        ]);
        break;

    case 'read':
        require $root . '/app/content/public-asset-store.php';
        $map = publicAssetNamespaceRoot('sponsors');
        $pad = $map === null ? null : $map . DIRECTORY_SEPARATOR . 'sponsor-1.png';
        r296Out(['value' => is_string($pad) && is_file($pad) ? file_get_contents($pad) : null]);
        break;

    default:
        r296Out(['error' => 'onbekende actie']);
        exit(2);
}
PHP;
file_put_contents($worker, $workerCode);

try {
    [$initCode, $init] = r296Run($worker, $config, [$root, 'init', 'SNAPSHOT-BRON']);
    r296Check($initCode === 0 && is_array($init) && ($init['ok'] ?? false) === true, 'actieve sponsorasset is voorbereid');

    [$snapCode, $snap] = r296Run($worker, $config, [$root, 'snapshot']);
    $snapshot = is_array($snap) ? (string) ($snap['name'] ?? '') : '';
    r296Check($snapCode === 0 && $snapshot !== '' && ($snap['ok'] ?? false) === true, 'assetsnapshot voor restore is gemaakt');

    [$modifyCode, $modify] = r296Run($worker, $config, [$root, 'modify', 'ACTIEF-MOET-BLIJVEN']);
    r296Check($modifyCode === 0 && is_array($modify) && ($modify['ok'] ?? false) === true, 'actieve asset wijkt na snapshot bewust af');

    [$restoreCode, $restore, $restoreRaw] = r296Run($worker, $config, [$root, 'restore-modefail', $snapshot]);
    r296Check(
        $restoreCode === 0
        && is_array($restore)
        && ($restore['ok'] ?? true) === false
        && (int) ($restore['mode_fail_hits'] ?? 0) === 1,
        'echte restore bereikt post-swap modecontrole en injecteert daar exact één failure'
    );
    r296Check(
        is_array($restore)
        && str_contains((string) ($restore['error'] ?? ''), 'veilige directorymode')
        && str_contains((string) ($restore['error'] ?? ''), 'automatisch teruggezet'),
        'restore rapporteert gecontroleerde modefailure met geslaagde automatische rollback'
    );

    [$readCode, $read] = r296Run($worker, $config, [$root, 'read']);
    r296Check(
        $readCode === 0 && is_array($read) && ($read['value'] ?? null) === 'ACTIEF-MOET-BLIJVEN',
        'post-mutation failure zet de oorspronkelijke actieve assetinhoud daadwerkelijk terug'
    );

    $assetParent = $private . DIRECTORY_SEPARATOR . 'public-assets';
    $restanten = [];
    foreach (['sponsors.restore.*', 'sponsors.before-restore.*', 'sponsors.failed-mode.*'] as $patroon) {
        $matches = glob($assetParent . DIRECTORY_SEPARATOR . $patroon);
        if (is_array($matches)) $restanten = array_merge($restanten, $matches);
    }
    r296Check($restanten === [], 'geslaagde automatische rollback laat geen restore-, parked- of failed-mode-restmappen achter');
} finally {
    r296Wis($tmp);
}

echo "Audit #296 asset restore rollback behavior: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
