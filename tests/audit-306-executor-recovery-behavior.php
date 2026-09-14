<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function a306Check(bool $cond, string $label): void
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

function a306RootPrefix(): ?array
{
    if (function_exists('posix_geteuid') && posix_geteuid() === 0) return [];
    if (!is_file('/usr/bin/sudo') || !is_executable('/usr/bin/sudo')) return null;
    $proc = proc_open(['/usr/bin/sudo','-n','/usr/bin/true'], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, null, null, ['bypass_shell'=>true]);
    if (!is_resource($proc)) return null;
    foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
    return proc_close($proc) === 0 ? ['/usr/bin/sudo','-n'] : null;
}

function a306Run(array $prefix, array $cmd, string $cwd): array
{
    $proc = proc_open(array_merge($prefix, $cmd), [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $cwd, null, ['bypass_shell'=>true]);
    if (!is_resource($proc)) throw new RuntimeException('Root-testproces kon niet worden gestart.');
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($proc), (string)$out, (string)$err];
}

function a306RootRead(array $prefix, string $path, string $cwd): ?string
{
    [$code, $out] = a306Run($prefix, ['/bin/cat', $path], $cwd);
    return $code === 0 ? $out : null;
}

function a306RootExists(array $prefix, string $path, string $cwd): bool
{
    [$code] = a306Run($prefix, ['/usr/bin/test', '-e', $path], $cwd);
    return $code === 0;
}

function a306Json(array $prefix, string $path, string $cwd): ?array
{
    $raw = a306RootRead($prefix, $path, $cwd);
    if (!is_string($raw)) return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function a306WriteJson(string $path, array $data): void
{
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (!is_string($json) || file_put_contents($path, $json . "\n") === false) {
        throw new RuntimeException('Test-JSON kon niet worden geschreven: ' . $path);
    }
}

function a306Request(string $id): array
{
    return [
        'schema'=>1,
        'phase'=>'5.1-request',
        'request_id'=>$id,
        'tenant_key'=>'platform',
        'action'=>'admin-refresh',
        'operator'=>'owner.test',
        'requested_at_utc'=>gmdate('Y-m-d\TH:i:s\Z', time() - 3600),
        'confirm'=>[],
    ];
}

function a306Binding(array $request): string
{
    $json = json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) throw new RuntimeException('Requestbinding kon niet worden berekend.');
    return hash('sha256', $json);
}

function a306Journal(array $request, string $state, string $message, ?string $binding = null): array
{
    return [
        'schema'=>1,
        'phase'=>'5.8-execution-journal',
        'request_id'=>$request['request_id'],
        'request_sha256'=>$binding ?? a306Binding($request),
        'tenant_key'=>$request['tenant_key'],
        'action'=>$request['action'],
        'operator'=>$request['operator'],
        'state'=>$state,
        'updated_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),
        'message'=>$message,
    ];
}

function a306AuditCount(string $raw, string $id): int
{
    $count = 0;
    foreach (preg_split('/\R/', trim($raw)) ?: [] as $line) {
        if ($line === '') continue;
        $row = json_decode($line, true);
        if (is_array($row) && hash_equals($id, (string)($row['request_id'] ?? ''))) $count++;
    }
    return $count;
}

$prefix = a306RootPrefix();
if ($prefix === null) {
    if (getenv('GITHUB_ACTIONS') === 'true') {
        fwrite(STDERR, "FOUT: GitHub Actions mist passwordless sudo voor executor recovery-regressie.\n");
        exit(1);
    }
    echo "SKIP: #306 vereist root of passwordless sudo om de echte root-only executor te starten.\n";
    exit(0);
}

$tmp = sys_get_temp_dir() . '/rc045-a306-' . bin2hex(random_bytes(5));
$state = $tmp . '/state';
$pending = $state . '/requests/pending';
$processing = $state . '/requests/processing';
$results = $state . '/results';
$sessions = $state . '/sessions';
$tenants = $tmp . '/tenants';
foreach ([$pending,$processing,$results,$sessions,$tenants] as $dir) {
    if (!@mkdir($dir, 0770, true) && !is_dir($dir)) throw new RuntimeException('Tijdelijke testmap kon niet worden gemaakt: ' . $dir);
}

$configPath = $tmp . '/runtime.json';
$auditPath = $tmp . '/audit/control-plane.jsonl';
@mkdir(dirname($auditPath), 0770, true);
$config = [
    'schema'=>1,
    'phase'=>'5.1-runtime',
    'host'=>'audit306.example.test',
    'app_root'=>$root,
    'tenants_root'=>$tenants,
    'runtime_user'=>'root',
    'pending_dir'=>$pending,
    'processing_dir'=>$processing,
    'results_dir'=>$results,
    'sessions_dir'=>$sessions,
    'snapshot_file'=>$state . '/snapshot.json',
    'executor_lock'=>$tmp . '/executor.lock',
    'audit_file'=>$auditPath,
    'lifecycle_apply'=>$root . '/bin/apply-vps-lifecycle.php',
];
a306WriteJson($configPath, $config);

// Sorteer mismatch eerst: een onveilig item mag een later geldig item niet blokkeren.
$idMismatch = str_repeat('1', 32);
$idEffect = str_repeat('2', 32);
$idExecuting = str_repeat('3', 32);
$idExisting = str_repeat('4', 32);
$idAccepted = str_repeat('5', 32);

$requests = [];
foreach ([$idMismatch,$idEffect,$idExecuting,$idExisting,$idAccepted] as $id) {
    $requests[$id] = a306Request($id);
    a306WriteJson($processing . '/' . $id . '.json', $requests[$id]);
}

a306WriteJson($processing . '/' . $idMismatch . '.journal.json', a306Journal(
    $requests[$idMismatch],
    'effect_committed',
    'MISMATCH-MAG-NOOIT-WORDEN-VERTROUWD',
    str_repeat('0', 64)
));
a306WriteJson($processing . '/' . $idEffect . '.journal.json', a306Journal($requests[$idEffect], 'effect_committed', 'EFFECT-COMMITTED-CANARY'));
a306WriteJson($processing . '/' . $idExecuting . '.journal.json', a306Journal($requests[$idExecuting], 'executing', 'EXECUTING-CANARY'));
a306WriteJson($processing . '/' . $idExisting . '.journal.json', a306Journal($requests[$idExisting], 'accepted', 'ACCEPTED-MET-BESTAAND-RESULT'));
a306WriteJson($processing . '/' . $idAccepted . '.journal.json', a306Journal($requests[$idAccepted], 'accepted', 'ACCEPTED-CANARY'));

a306WriteJson($results . '/' . $idExisting . '.json', [
    'schema'=>1,
    'phase'=>'5.1-result',
    'request_id'=>$idExisting,
    'tenant_key'=>'platform',
    'action'=>'admin-refresh',
    'operator'=>'owner.test',
    'result'=>'ok',
    'message'=>'PREEXISTING-RESULT-CANARY',
    'completed_at_utc'=>gmdate('Y-m-d\TH:i:s\Z', time() - 120),
]);
file_put_contents($auditPath, json_encode([
    'timestamp_utc'=>gmdate('Y-m-d\TH:i:s\Z', time() - 120),
    'request_id'=>$idExisting,
    'operator'=>'owner.test',
    'tenant_key'=>'platform',
    'action'=>'admin-refresh',
    'result'=>'ok',
    'message'=>'PREEXISTING-RESULT-CANARY',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

try {
    $executor = [PHP_BINARY, $root . '/bin/control-plane-executor.php', '--config=' . $configPath];
    [$exit, $out, $err] = a306Run($prefix, $executor, $root);
    a306Check($exit === 0 && str_contains($out, 'EXECUTOR OK'), 'echte root-executor rondt startup-reconciliation af');
    a306Check(str_contains($err, 'kon niet veilig worden gereconcilieerd') && str_contains($err, 'byte-inhoudelijk'), 'journal/request mismatch wordt tijdens echte startup fail-closed gemeld');

    $effect = a306Json($prefix, $results . '/' . $idEffect . '.json', $root);
    a306Check(is_array($effect) && ($effect['result'] ?? '') === 'ok' && ($effect['message'] ?? '') === 'EFFECT-COMMITTED-CANARY', 'effect_committed wordt zonder heruitvoering naar duurzaam ok-result gereconcilieerd');
    a306Check(!a306RootExists($prefix, $processing . '/' . $idEffect . '.json', $root) && !a306RootExists($prefix, $processing . '/' . $idEffect . '.journal.json', $root), 'effect_committed processing- en journalstate wordt na duurzaam herstel opgeruimd');

    $executing = a306Json($prefix, $results . '/' . $idExecuting . '.json', $root);
    a306Check(is_array($executing) && ($executing['result'] ?? '') === 'failed' && str_contains((string)($executing['message'] ?? ''), 'Uitkomst onbekend'), 'executing crashstate wordt als unknown-outcome failed geclassificeerd');
    a306Check(str_contains((string)($executing['message'] ?? ''), 'niet automatisch opnieuw uitgevoerd'), 'executing crashstate bewijst expliciet geen blinde retry');

    $existing = a306Json($prefix, $results . '/' . $idExisting . '.json', $root);
    a306Check(is_array($existing) && ($existing['result'] ?? '') === 'ok' && ($existing['message'] ?? '') === 'PREEXISTING-RESULT-CANARY', 'bestaand definitief result blijft byte-semantisch leidend bij reconciliation');
    a306Check(!a306RootExists($prefix, $processing . '/' . $idExisting . '.json', $root), 'processingstate met bestaand result wordt na reconciliation opgeruimd');

    $accepted = a306Json($prefix, $results . '/' . $idAccepted . '.json', $root);
    a306Check(is_array($accepted) && ($accepted['result'] ?? '') === 'failed' && str_contains((string)($accepted['message'] ?? ''), 'vóór aantoonbare uitvoering'), 'accepted crashstate wordt niet automatisch hervat maar duurzaam failed gemaakt');

    a306Check(!a306RootExists($prefix, $results . '/' . $idMismatch . '.json', $root), 'mismatch-item produceert geen vertrouwenswaardig result');
    a306Check(a306RootExists($prefix, $processing . '/' . $idMismatch . '.json', $root) && a306RootExists($prefix, $processing . '/' . $idMismatch . '.journal.json', $root), 'mismatch recoverymateriaal blijft root-only beschikbaar voor operatorherstel');
    a306Check(is_array($effect) && is_array($executing) && is_array($accepted), 'eerste corrupte processing-item blokkeert latere geldige reconciliation niet');

    $auditRaw = a306RootRead($prefix, $auditPath, $root) ?? '';
    a306Check(a306AuditCount($auditRaw, $idEffect) === 1, 'effect_committed krijgt exact één auditregel');
    a306Check(a306AuditCount($auditRaw, $idExecuting) === 1, 'executing recovery krijgt exact één auditregel');
    a306Check(a306AuditCount($auditRaw, $idExisting) === 1, 'bestaand result dupliceert reeds aanwezige auditregel niet');
    a306Check(a306AuditCount($auditRaw, $idAccepted) === 1, 'accepted recovery krijgt exact één auditregel');
    a306Check(a306AuditCount($auditRaw, $idMismatch) === 0, 'ongebonden journal krijgt geen auditbewijs alsof recovery geslaagd was');

    // Tweede executorstart: alleen het mismatch-item resteert. Reconciliation moet
    // idempotent blijven en mag geen afgeronde auditregels dupliceren.
    [$exit2, $out2, $err2] = a306Run($prefix, $executor, $root);
    a306Check($exit2 === 0 && str_contains($out2, 'EXECUTOR OK') && str_contains($err2, 'kon niet veilig worden gereconcilieerd'), 'tweede executorstart blijft beschikbaar ondanks bewust achtergehouden mismatch-item');
    $auditRaw2 = a306RootRead($prefix, $auditPath, $root) ?? '';
    a306Check(
        a306AuditCount($auditRaw2, $idEffect) === 1
        && a306AuditCount($auditRaw2, $idExecuting) === 1
        && a306AuditCount($auditRaw2, $idExisting) === 1
        && a306AuditCount($auditRaw2, $idAccepted) === 1,
        'tweede executorstart houdt afgeronde recovery-audit idempotent'
    );
} finally {
    a306Run($prefix, ['/bin/rm','-rf',$tmp], $root);
}

echo "Audit #306 executor recovery behavior: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
