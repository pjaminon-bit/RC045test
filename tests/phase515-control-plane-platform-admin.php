<?php
$root = dirname(__DIR__);
$ok = 0; $fout = 0;
function c515(bool $conditie, string $label): void {
    global $ok, $fout;
    if ($conditie) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}
function rm515(string $path): void {
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $name) {
        if ($name === '.' || $name === '..') continue;
        rm515($path . '/' . $name);
    }
    @rmdir($path);
}

$tmp = sys_get_temp_dir() . '/rc045-phase515-' . bin2hex(random_bytes(5));
$state = $tmp . '/control-plane';
$requests = $state . '/requests';
$pending = $requests . '/pending';
$processing = $requests . '/processing';
$results = $state . '/results';
$sessions = $state . '/sessions';
foreach ([$pending,$processing,$results,$sessions] as $dir) @mkdir($dir, 0770, true);
$snapshotFile = $state . '/snapshot.json';
$audit = $tmp . '/audit/control-plane.jsonl';
@mkdir(dirname($audit), 0770, true);
$config = [
    'schema'=>1,
    'phase'=>'5.1-runtime',
    'host'=>'beheer.example.test',
    'app_root'=>$root,
    'tenants_root'=>$tmp . '/tenants',
    'runtime_user'=>get_current_user() ?: 'runner',
    'pending_dir'=>$pending,
    'processing_dir'=>$processing,
    'results_dir'=>$results,
    'sessions_dir'=>$sessions,
    'snapshot_file'=>$snapshotFile,
    'executor_lock'=>$tmp . '/executor.lock',
    'audit_file'=>$audit,
    'lifecycle_apply'=>$root . '/bin/apply-vps-lifecycle.php',
];
@mkdir($config['tenants_root'], 0770, true);
$configFile = $tmp . '/runtime.json';
file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_SLASHES));
$snapshot = [
    'schema'=>1,
    'phase'=>'5.1-snapshot',
    'generated_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),
    'tenants'=>[
        ['tenant_key'=>'alpha','canonical_host'=>'alpha.example.test','status'=>'active','transition'=>null,'healthy'=>true,'updated_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'last_export'=>null,'delete_export'=>null,'purge_not_before_utc'=>null],
        ['tenant_key'=>'bravo','canonical_host'=>'bravo.example.test','status'=>'active','transition'=>null,'healthy'=>false,'updated_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'last_export'=>null,'delete_export'=>null,'purge_not_before_utc'=>null],
    ],
];
file_put_contents($snapshotFile, json_encode($snapshot, JSON_UNESCAPED_SLASHES));
putenv('VP_CONTROL_PLANE_CONFIG=' . $configFile);
$_SERVER['REMOTE_USER'] = 'operator.test';

try {
    require_once $root . '/app/control-plane/control-plane-runtime.php';
    require_once $root . '/app/control-plane/control-plane-observability.php';

    $status = cpAdminPlatformStatus($snapshot);
    c515(($status['ok'] ?? false) === true, 'platformhealth is groen wanneer queue, sessies en snapshot beschikbaar zijn');
    c515(($status['counts']['active'] ?? 0) === 2 && ($status['counts']['healthy'] ?? 0) === 1 && ($status['counts']['unhealthy'] ?? 0) === 1, 'platformhealth telt actieve en ongezonde tenants afzonderlijk');
    c515(in_array('1 actieve vereniging(en) rapporteren geen actuele gezonde status.', $status['warnings'] ?? [], true), 'ongezonde actieve tenant verschijnt als platformwaarschuwing');

    $requestId = str_repeat('a', 32);
    file_put_contents($results . '/' . $requestId . '.json', json_encode([
        'schema'=>1,'phase'=>'5.1-result','request_id'=>$requestId,'tenant_key'=>'alpha','action'=>'suspend','operator'=>'operator.test','result'=>'ok','message'=>'SUSPEND OK tenant=alpha','completed_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),
    ], JSON_UNESCAPED_SLASHES));
    $recent = cpAdminRecenteResultaten('operator.test', 8);
    c515(count($recent) === 1 && ($recent[0]['action'] ?? '') === 'suspend', 'recente beheeracties worden operatorgebonden uit veilige resultaatsbestanden gelezen');
    c515(cpAdminRecenteResultaten('andere.operator', 8) === [], 'beheeractiehistorie lekt niet naar een andere operator');

    @rename($pending, $pending . '-weg');
    $broken = cpAdminPlatformStatus($snapshot);
    c515(($broken['ok'] ?? true) === false && in_array('Aanvraagqueue is niet schrijfbaar door de control-plane runtime.', $broken['critical'] ?? [], true), 'ontbrekende/onbereikbare pending queue wordt vóór een mutatie als kritieke platformfout gemeld');
    @rename($pending . '-weg', $pending);

    $apply = (string)file_get_contents($root . '/bin/apply-vps-control-plane.php');
    $executor = (string)file_get_contents($root . '/bin/control-plane-executor.php');
    $repair = (string)file_get_contents($root . '/bin/repair-control-plane-runtime.php');
    $ui = (string)file_get_contents($root . '/app/control-plane-web/index.php');
    $obs = (string)file_get_contents($root . '/app/control-plane/control-plane-observability.php');

    $applyParent = strpos($apply, "cpaDir(dirname(\$p['runtime']['pending_dir']),0750,0,\$p['identity']['group'])");
    $applyPending = strpos($apply, "cpaDir(\$p['runtime']['pending_dir'],0730,\$p['identity']['user'],\$p['identity']['group'])");
    c515($applyParent !== false && $applyPending !== false && $applyParent < $applyPending, 'installer normaliseert requests-parent root:runtime-group 0750 vóór pending 0730');

    $executorParent = strpos($executor, "cpeDir(dirname(\$c['pending_dir']),0750,0,\$c['runtime_user'])");
    $executorPending = strpos($executor, "cpeDir(\$c['pending_dir'],0730,\$c['runtime_user'],\$c['runtime_user'])");
    c515($executorParent !== false && $executorPending !== false && $executorParent < $executorPending, 'executor bewaakt requests-parent bij iedere refresh/actie');

    c515(str_contains($repair, "cprDir(\$c['_requests_root'], 0750, 0, \$runtime, \$repair)") && str_contains($repair, "cprDir(\$c['pending_dir'], 0730, \$runtime, \$runtime, \$repair)"), 'repair-CLI herstelt parent en pending met least-privilege modes');
    c515(!str_contains($repair, '0777') && !str_contains($repair, 'shell_exec(') && !str_contains($repair, 'exec(') && !str_contains($repair, 'system('), 'runtime-repair introduceert geen world-writable of shell-executie');
    c515(str_contains($repair, 'cprSymlinkInPad') && str_contains($repair, 'Control-plane statepaden delen niet dezelfde state-root.'), 'repair-CLI valideert symlinks en vaste statepadrelaties fail-closed');

    c515(str_contains($ui, 'cpAdminPlatformStatus') && str_contains($ui, 'Mutaties geblokkeerd') && str_contains($ui, 'Recente beheeracties'), 'platformconsole toont platformhealth en actiehistorie');
    c515(str_contains($ui, 'tenant-search') && str_contains($ui, 'tenant-filter') && str_contains($ui, 'Open website'), 'platformconsole biedt zoeken, statusfilter en directe tenantlink');
    c515(!str_contains($obs, 'proc_open(') && !str_contains($obs, 'shell_exec(') && !str_contains($obs, 'system(') && !str_contains($obs, 'exec('), 'observabilitylaag blijft read-only zonder procesexecutie');
} finally {
    rm515($tmp);
}

echo "Phase 5.1.5 platform admin observability: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
