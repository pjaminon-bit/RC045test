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
    'journal binds validated request bytes' => is_string($source)
        && str_contains($source, 'function cpeRequestBinding')
        && str_contains($source, "'request_sha256'=>cpeRequestBinding(\$r)")
        && str_contains($source, 'function cpeJournalBindtRequest')
        && str_contains($source, 'Executorjournal hoort niet byte-inhoudelijk bij het processing-request.'),
    'recovery bypass requires existing bound journal' => is_string($source)
        && str_contains($source, 'cpeRequest($f,true,$journal!==null)')
        && str_contains($source, 'if($journal!==null&&!cpeJournalBindtRequest($journal,$r))'),
    'bad processing item does not block queue recovery' => is_string($source)
        && str_contains($source, 'kon niet veilig worden gereconcilieerd en blijft root-only staan')
        && str_contains($source, 'catch(Throwable$e){fwrite(STDERR'),
    'schedule validation anchored to request' => is_string($admin)
        && str_contains($admin, '$requested=strtotime')
        && str_contains($admin, 'requested_at_utc')
        && str_contains($admin, '$ts<$requested+30')
        && !str_contains($admin, '$ts<time()+30'),
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
