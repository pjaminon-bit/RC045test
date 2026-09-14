<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function a304Check(bool $cond, string $label): void
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

function a304Rm(string $path): void
{
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        a304Rm($path . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($path);
}

function a304WriteJson(string $path, array $data): void
{
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (!is_string($json) || file_put_contents($path, $json . "\n") === false) {
        throw new RuntimeException('Test-JSON kon niet worden geschreven: ' . $path);
    }
}

function a304Run(string $runner, string $config, string $action, string $id): array
{
    $cmd = [PHP_BINARY, $runner, $action, $id];
    $env = ['VP_CONTROL_PLANE_CONFIG' => $config];
    $proc = proc_open($cmd, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, null, $env, ['bypass_shell'=>true]);
    if (!is_resource($proc)) throw new RuntimeException('Testproces kon niet worden gestart.');
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($proc), trim((string)$out), trim((string)$err)];
}

$tmp = sys_get_temp_dir() . '/rc045-a304-' . bin2hex(random_bytes(5));
$state = $tmp . '/state';
$pending = $state . '/requests/pending';
$processing = $state . '/requests/processing';
$results = $state . '/results';
$sessions = $state . '/sessions';
$tenants = $tmp . '/tenants';
foreach ([$pending, $processing, $results, $sessions, $tenants] as $dir) {
    if (!@mkdir($dir, 0770, true) && !is_dir($dir)) {
        throw new RuntimeException('Tijdelijke testmap kon niet worden gemaakt: ' . $dir);
    }
}

$configPath = $tmp . '/runtime.json';
$snapshot = $state . '/snapshot.json';
$config = [
    'schema'=>1,
    'phase'=>'5.1-runtime',
    'host'=>'audit304.example.test',
    'app_root'=>$root,
    'tenants_root'=>$tenants,
    'runtime_user'=>get_current_user() ?: 'runner',
    'pending_dir'=>$pending,
    'processing_dir'=>$processing,
    'results_dir'=>$results,
    'sessions_dir'=>$sessions,
    'snapshot_file'=>$snapshot,
    'executor_lock'=>$tmp . '/executor.lock',
    'audit_file'=>$tmp . '/audit.jsonl',
    'lifecycle_apply'=>$root . '/bin/apply-vps-lifecycle.php',
];
a304WriteJson($configPath, $config);
a304WriteJson($snapshot, [
    'schema'=>1,
    'phase'=>'5.1-snapshot',
    'generated_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),
    'tenants'=>[],
]);

$runner = $tmp . '/runner.php';
file_put_contents($runner, "<?php\n"
    . '$root=' . var_export($root, true) . ";\n"
    . "require \$root . '/app/control-plane/control-plane-runtime.php';\n"
    . "\$action=(string)(\$argv[1]??'');\n"
    . "\$id=(string)(\$argv[2]??'');\n"
    . "try {\n"
    . "  \$written=cp51QueueSchrijf(['request_id'=>\$id,'action'=>\$action]);\n"
    . "  echo 'OK=' . \$written . \"\\n\";\n"
    . "  exit(0);\n"
    . "} catch (Throwable \$e) {\n"
    . "  fwrite(STDERR, 'ERR=' . \$e->getMessage() . \"\\n\");\n"
    . "  exit(42);\n"
    . "}\n");

try {
    $healthyId = str_repeat('1', 32);
    [$code, $out, $err] = a304Run($runner, $configPath, 'suspend', $healthyId);
    $healthyPath = $pending . '/' . $healthyId . '.json';
    $healthyDoc = is_file($healthyPath) ? json_decode((string)file_get_contents($healthyPath), true) : null;
    a304Check($code === 0 && $out === 'OK=' . $healthyId, 'gezonde muterende queuewrite passeert de echte productiegrens');
    a304Check(is_array($healthyDoc) && ($healthyDoc['action'] ?? '') === 'suspend', 'gezonde mutatie wordt daadwerkelijk als pending queue-item vastgelegd');

    $sessionId = str_repeat('2', 32);
    a304Rm($sessions);
    [$code, $out, $err] = a304Run($runner, $configPath, 'suspend', $sessionId);
    a304Check($code === 42 && str_contains($err, 'sessiestore is niet schrijfbaar'), 'ontbrekende sessiestore blokkeert muterende queuewrite fail-closed');
    a304Check(!file_exists($pending . '/' . $sessionId . '.json'), 'sessiestore-interlock laat geen queue-item achter');
    @mkdir($sessions, 0770, true);

    $snapshotId = str_repeat('3', 32);
    @unlink($snapshot);
    [$code, $out, $err] = a304Run($runner, $configPath, 'suspend', $snapshotId);
    a304Check($code === 42 && str_contains($err, 'platformstatussnapshot is niet leesbaar'), 'ontbrekende platformsnapshot blokkeert muterende queuewrite fail-closed');
    a304Check(!file_exists($pending . '/' . $snapshotId . '.json'), 'snapshot-interlock laat geen queue-item achter');
    a304WriteJson($snapshot, [
        'schema'=>1,
        'phase'=>'5.1-snapshot',
        'generated_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),
        'tenants'=>[],
    ]);

    $tenantId = str_repeat('4', 32);
    a304Rm($tenants);
    [$code, $out, $err] = a304Run($runner, $configPath, 'suspend', $tenantId);
    a304Check($code === 42 && str_contains($err, 'tenantbasis is niet veilig beschikbaar'), 'ontbrekende tenantroot blokkeert muterende queuewrite fail-closed');
    a304Check(!file_exists($pending . '/' . $tenantId . '.json'), 'tenantroot-interlock laat geen queue-item achter');

    // schedule-cancel is bewust een risicoreducerende herstelactie en staat niet
    // in cp51MuterendeQueueActies(). Bewijs dat dezelfde onbeschikbare
    // platformresources die een mutatie blokkeren deze queuewrite niet globaal
    // uitschakelen.
    a304Rm($sessions);
    @unlink($snapshot);
    $recoveryId = str_repeat('5', 32);
    [$code, $out, $err] = a304Run($runner, $configPath, 'schedule-cancel', $recoveryId);
    $recoveryPath = $pending . '/' . $recoveryId . '.json';
    $recoveryDoc = is_file($recoveryPath) ? json_decode((string)file_get_contents($recoveryPath), true) : null;
    a304Check($code === 0 && $out === 'OK=' . $recoveryId, 'risicoreducerende schedule-cancel queuewrite wordt niet door mutation interlock geblokkeerd');
    a304Check(is_array($recoveryDoc) && ($recoveryDoc['action'] ?? '') === 'schedule-cancel', 'interlock-exclusie schrijft uitsluitend het bedoelde recovery queue-item');

    $source = (string)file_get_contents($root . '/app/control-plane/control-plane-runtime.php');
    a304Check(str_contains($source, "if (\$pct >= 97.0)"), 'exacte 97%-diskdrempel blijft aanvullend als source-contract bewaakt');
} finally {
    a304Rm($tmp);
}

echo "Audit #304 control-plane interlock behavior: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
