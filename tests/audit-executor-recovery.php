<?php
$source = file_get_contents(dirname(__DIR__) . '/bin/control-plane-executor.php');
$checks = [
    'journal states' => "'accepted','executing','effect_committed'",
    'startup reconciliation' => 'function cpeReconcileProcessing',
    'idempotent audit' => 'function cpeAuditEenmalig',
    'result conflict protection' => 'Conflicterend bestaand executorresultaat.',
    'root capacity guard' => 'function cpePlatformMutatieInterlock',
    'no blind crash retry' => 'niet automatisch opnieuw uitgevoerd',
];
$ok = 0;
foreach ($checks as $label => $needle) {
    if (is_string($source) && str_contains($source, $needle)) {
        echo "OK: {$label}\n";
        $ok++;
    } else {
        fwrite(STDERR, "FOUT: {$label}\n");
    }
}
echo "Executor recovery regression: {$ok}/" . count($checks) . " OK\n";
exit($ok === count($checks) ? 0 : 1);
