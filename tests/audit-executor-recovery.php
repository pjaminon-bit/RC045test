<?php
$source = file_get_contents(dirname(__DIR__) . '/bin/control-plane-executor.php');
$admin = file_get_contents(dirname(__DIR__) . '/app/deployment/control-plane-admin-executor.php');
$checks = [
    'journal states' => is_string($source) && str_contains($source, "'accepted','executing','effect_committed'"),
    'startup reconciliation' => is_string($source) && str_contains($source, 'function cpeReconcileProcessing'),
    'idempotent audit' => is_string($source) && str_contains($source, 'function cpeAuditEenmalig'),
    'result conflict protection' => is_string($source) && str_contains($source, 'Conflicterend bestaand executorresultaat.'),
    'root capacity guard' => is_string($source) && str_contains($source, 'function cpePlatformMutatieInterlock'),
    'no blind crash retry' => is_string($source) && str_contains($source, 'niet automatisch opnieuw uitgevoerd'),
    'schedule validation anchored to request' => is_string($admin) && str_contains($admin, "$requested=strtotime((string)($r['requested_at_utc']??''))") && !str_contains($admin, '$ts<time()+30'),
    'legacy cancel path fail closed' => is_string($admin) && str_contains($admin, 'Schedule-cancel mag uitsluitend via de gelockte root-executorroute worden uitgevoerd.'),
];
$ok = 0;
foreach ($checks as $label => $passed) {
    if ($passed) {
        echo "OK: {$label}\n";
        $ok++;
    } else {
        fwrite(STDERR, "FOUT: {$label}\n");
    }
}
echo "Executor recovery regression: {$ok}/" . count($checks) . " OK\n";
exit($ok === count($checks) ? 0 : 1);
