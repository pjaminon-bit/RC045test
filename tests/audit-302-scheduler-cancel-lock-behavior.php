<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function r302Check(bool $cond, string $label): void
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

function r302Wait(callable $cond, float $seconds, string $label): bool
{
    $deadline = microtime(true) + $seconds;
    do {
        if ($cond()) return true;
        usleep(20000);
    } while (microtime(true) < $deadline);
    fwrite(STDERR, "FOUT: timeout tijdens {$label}\n");
    return false;
}

function r302RootPrefix(): ?array
{
    if (function_exists('posix_geteuid') && posix_geteuid() === 0) return [];
    if (!is_file('/usr/bin/sudo') || !is_executable('/usr/bin/sudo')) return null;
    $proc = proc_open(['/usr/bin/sudo', '-n', '/usr/bin/true'], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    if (!is_resource($proc)) return null;
    foreach ($pipes as $p) if (is_resource($p)) fclose($p);
    return proc_close($proc) === 0 ? ['/usr/bin/sudo', '-n'] : null;
}

function r302Spawn(array $cmd, string $cwd): array
{
    $proc = proc_open($cmd, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $cwd);
    if (!is_resource($proc)) throw new RuntimeException('Proces kon niet worden gestart: ' . implode(' ', $cmd));
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    return [$proc, $pipes];
}

function r302Finish($proc, array $pipes, float $timeout = 10.0): array
{
    $done = r302Wait(static function () use ($proc): bool {
        $status = proc_get_status($proc);
        return is_array($status) && empty($status['running']);
    }, $timeout, 'procesafronding');
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);
    if (!$done && $exit === -1) $exit = 124;
    return [$exit, (string)$out, (string)$err];
}

function r302RootRun(array $prefix, array $cmd, string $cwd, float $timeout = 10.0): array
{
    [$proc, $pipes] = r302Spawn(array_merge($prefix, $cmd), $cwd);
    return r302Finish($proc, $pipes, $timeout);
}

function r302Schedule(string $id, string $tenant = 'alpha'): array
{
    return [
        'schema'=>1,
        'phase'=>'5.8-schedule',
        'schedule_id'=>$id,
        'tenant_key'=>$tenant,
        'operator'=>'owner.test',
        'action'=>'suspend',
        'execute_at_utc'=>gmdate('Y-m-d\TH:i:s\Z', time() - 10),
        'status'=>'scheduled',
        'request_id'=>null,
        'message'=>null,
    ];
}

function r302WriteJson(string $path, array $doc): void
{
    $json = json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (!is_string($json) || file_put_contents($path, $json . "\n") === false) throw new RuntimeException('Test-JSON kon niet worden geschreven: ' . $path);
}

function r302Prelock(string $path)
{
    $h = fopen($path, 'c+');
    if (!is_resource($h)) throw new RuntimeException('Testlock kon niet worden geopend.');
    chmod($path, 0666);
    if (!flock($h, LOCK_EX)) {
        fclose($h);
        throw new RuntimeException('Testlock kon niet exclusief worden bezet.');
    }
    return $h;
}

function r302Mode(string $path): ?int
{
    clearstatcache(true, $path);
    $m = @fileperms($path);
    return is_int($m) ? ($m & 0777) : null;
}

$prefix = r302RootPrefix();
if ($prefix === null) {
    if (getenv('GITHUB_ACTIONS') === 'true') {
        fwrite(STDERR, "FOUT: GitHub Actions mist passwordless sudo voor root-only schedulerlockregressie.\n");
        exit(1);
    }
    echo "SKIP: #302 vereist root of passwordless sudo om de echte root-only CLI-entrypoints te exerceren.\n";
    exit(0);
}

$tmp = sys_get_temp_dir() . '/rc045-a302-' . bin2hex(random_bytes(5));
$state = $tmp . '/state';
$schedules = $state . '/schedules';
$pending = $state . '/requests/pending';
$processing = $state . '/requests/processing';
$results = $state . '/results';
$sessions = $state . '/sessions';
$tenants = $tmp . '/tenants';
foreach ([$schedules,$pending,$processing,$results,$sessions,$tenants] as $dir) {
    if (!@mkdir($dir, 0777, true) && !is_dir($dir)) throw new RuntimeException('Tijdelijke map kon niet worden aangemaakt: ' . $dir);
}

$configPath = $tmp . '/runtime.json';
$config = [
    'schema'=>1,
    'phase'=>'5.1-runtime',
    'host'=>'audit302.example.test',
    'app_root'=>$root,
    'tenants_root'=>$tenants,
    'runtime_user'=>'root',
    'pending_dir'=>$pending,
    'processing_dir'=>$processing,
    'results_dir'=>$results,
    'sessions_dir'=>$sessions,
    'snapshot_file'=>$state . '/snapshot.json',
    'executor_lock'=>$tmp . '/executor.lock',
    'audit_file'=>$tmp . '/audit.jsonl',
    'lifecycle_apply'=>$root . '/bin/apply-vps-lifecycle.php',
];
r302WriteJson($configPath, $config);
r302WriteJson($state . '/operators.json', [
    'schema'=>1,
    'phase'=>'5.8-operators',
    'updated_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),
    'roles'=>['owner.test'=>'owner'],
]);

$idA = str_repeat('a', 32);
$idB = str_repeat('b', 32);
$idC = str_repeat('c', 32);
foreach ([$idA,$idB,$idC] as $id) r302WriteJson($schedules . '/' . $id . '.json', r302Schedule($id));

$lockA = $schedules . '/' . $idA . '.lock';
$lockC = $schedules . '/' . $idC . '.lock';
$heldA = null;
$heldC = null;
$runnerA = null;
$runnerAPipes = [];
$executor = null;
$executorPipes = [];

try {
    $heldA = r302Prelock($lockA);
    $runnerCmd = [PHP_BINARY, $root . '/bin/control-plane-scheduled-run.php', '--config=' . $configPath];
    [$runnerA, $runnerAPipes] = r302Spawn(array_merge($prefix, $runnerCmd, ['--schedule=' . $idA]), $root);

    $runnerReachedLock = r302Wait(static function () use ($lockA, $runnerA): bool {
        $status = proc_get_status($runnerA);
        return r302Mode($lockA) === 0600 && is_array($status) && !empty($status['running']);
    }, 5.0, 'scheduled runner bereikt bezette schedulelock');
    r302Check($runnerReachedLock, 'scheduled runner bereikt de echte root-only lockgrens en blijft daar geblokkeerd');

    $aBefore = json_decode((string)file_get_contents($schedules . '/' . $idA . '.json'), true);
    r302Check(is_array($aBefore) && ($aBefore['status'] ?? '') === 'scheduled', 'geblokkeerde runner muteert schedulestatus nog niet');

    [$bExit, $bOut, $bErr] = r302RootRun($prefix, array_merge($runnerCmd, ['--schedule=' . $idB]), $root, 10.0);
    $bDoc = json_decode((string)file_get_contents($schedules . '/' . $idB . '.json'), true);
    r302Check(
        $bExit === 0 && str_contains($bOut, 'SCHEDULE QUEUED') && is_array($bDoc) && ($bDoc['status'] ?? '') === 'queued',
        'andere schedule-id kan queueën terwijl eerste id gelockt is; lock is niet globaal'
    );
    if ($bExit !== 0) fwrite(STDERR, "Runner B stderr: {$bErr}\n");

    flock($heldA, LOCK_UN);
    fclose($heldA);
    $heldA = null;
    [$aExit, $aOut, $aErr] = r302Finish($runnerA, $runnerAPipes, 10.0);
    $runnerA = null;
    $runnerAPipes = [];
    $aDoc = json_decode((string)file_get_contents($schedules . '/' . $idA . '.json'), true);
    r302Check(
        $aExit === 0 && str_contains($aOut, 'SCHEDULE QUEUED') && is_array($aDoc) && ($aDoc['status'] ?? '') === 'queued',
        'scheduled runner gaat pas na expliciete lockvrijgave door en commit queued-state'
    );
    if ($aExit !== 0) fwrite(STDERR, "Runner A stderr: {$aErr}\n");

    // Verwijder de door A/B gemaakte lifecycle-requests zodat de executortest
    // uitsluitend de schedule-cancel request voor id C verwerkt.
    foreach (glob($pending . '/*.json') ?: [] as $file) @unlink($file);

    $requestId = str_repeat('d', 32);
    r302WriteJson($pending . '/' . $requestId . '.json', [
        'schema'=>1,
        'phase'=>'5.1-request',
        'request_id'=>$requestId,
        'tenant_key'=>'alpha',
        'action'=>'schedule-cancel',
        'operator'=>'owner.test',
        'requested_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),
        'confirm'=>[],
        'admin'=>['schedule_id'=>$idC],
    ]);

    $heldC = r302Prelock($lockC);
    $executorCmd = [PHP_BINARY, $root . '/bin/control-plane-executor.php', '--config=' . $configPath];
    [$executor, $executorPipes] = r302Spawn(array_merge($prefix, $executorCmd), $root);

    $executorReachedLock = r302Wait(static function () use ($lockC, $executor): bool {
        $status = proc_get_status($executor);
        return r302Mode($lockC) === 0600 && is_array($status) && !empty($status['running']);
    }, 8.0, 'cancel-executor bereikt bezette schedulelock');
    r302Check($executorReachedLock, 'schedule-cancel executor bereikt exact dezelfde root-only schedulelock en blokkeert');
    r302Check(!is_file($results . '/' . $requestId . '.json'), 'geblokkeerde cancel heeft vóór lockvrijgave nog geen resultaat gecommit');

    flock($heldC, LOCK_UN);
    fclose($heldC);
    $heldC = null;
    [$eExit, $eOut, $eErr] = r302Finish($executor, $executorPipes, 15.0);
    $executor = null;
    $executorPipes = [];
    r302Check($eExit === 0 && str_contains($eOut, 'EXECUTOR OK'), 'cancel-executor rondt na lockvrijgave zijn queuecyclus af');

    $resultPad = $results . '/' . $requestId . '.json';
    $resultRaw = '';
    if (is_file($resultPad)) {
        [$catExit, $catOut] = r302RootRun($prefix, ['/bin/cat', $resultPad], $root, 5.0);
        if ($catExit === 0) $resultRaw = $catOut;
    }
    $result = json_decode($resultRaw, true);
    r302Check(
        is_array($result) && in_array((string)($result['status'] ?? ''), ['ok','failed'], true),
        'cancel produceert pas na vrijgave een duurzaam executorresultaat'
    );
    if ($eExit !== 0) fwrite(STDERR, "Executor stderr: {$eErr}\n");

    $source = (string)file_get_contents($root . '/bin/control-plane-executor.php');
    $cancel = strpos($source, 'function cpeScheduleCancelVeilig');
    $lock = $cancel === false ? false : strpos($source, 'cpeScheduleLock($c,$id)', $cancel);
    $read = $cancel === false ? false : strpos($source, 'control58ReadSchedule($c,$id)', $cancel);
    r302Check($cancel !== false && $lock !== false && $read !== false && $lock < $read, 'cancel leest en beslist schedulestatus pas nadat dezelfde id-lock is verkregen');
} catch (Throwable $e) {
    $fout++;
    fwrite(STDERR, 'FOUT: audit #302 exception: ' . $e->getMessage() . "\n");
} finally {
    if (is_resource($heldA)) { @flock($heldA, LOCK_UN); @fclose($heldA); }
    if (is_resource($heldC)) { @flock($heldC, LOCK_UN); @fclose($heldC); }
    foreach ([[$runnerA,$runnerAPipes],[$executor,$executorPipes]] as [$proc,$pipes]) {
        if (is_resource($proc)) {
            @proc_terminate($proc);
            foreach ((array)$pipes as $p) if (is_resource($p)) @fclose($p);
            @proc_close($proc);
        }
    }
    r302RootRun($prefix, ['/bin/rm', '-rf', $tmp], $root, 5.0);
}

echo "Audit #302 scheduler/cancel lock behavior: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
