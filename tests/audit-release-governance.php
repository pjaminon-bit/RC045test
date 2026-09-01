<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;
function rgCheck(bool $cond, string $label): void {
    global $ok, $fout;
    if ($cond) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

$workflow = @file_get_contents($root . '/.github/workflows/deploy-vps-test.yml');
$wrapper = @file_get_contents($root . '/ops/vps-test-deploy/verenigingsplatform-github-deploy');
rgCheck(is_string($workflow) && $workflow !== '', 'deployworkflow is leesbaar');
rgCheck(is_string($wrapper) && $wrapper !== '', 'VPS deploywrapper is leesbaar');

if (is_string($workflow)) {
    rgCheck(str_contains($workflow, 'pull-requests: read'), 'deployworkflow heeft alleen leesrecht nodig voor PR-lineage');
    rgCheck(str_contains($workflow, 'Valideer gemergde PR-lineage voor iedere deploy'), 'iedere deploy passeert een expliciete lineage-gate');
    rgCheck(str_contains($workflow, '/commits/$DEPLOY_SHA/pulls'), 'lineage-gate controleert exact de te deployen SHA');
    rgCheck(str_contains($workflow, '.merged_at != null and .base.ref == "main"'), 'alleen gemergde PR naar main geldt als deploybron');
}

if (is_string($wrapper)) {
    rgCheck(str_contains($wrapper, 'ls-remote "$repo" refs/heads/main'), 'rootgateway leest de actuele remote main-tip');
    rgCheck(str_contains($wrapper, '[[ "$commit" == "$main_commit" ]]'), 'rootgateway accepteert alleen de actuele main-tip');
    rgCheck(str_contains($wrapper, 'gevraagde commit is niet de actuele main-tip'), 'afwijkende commit faalt gesloten met duidelijke fout');
    rgCheck(str_contains($wrapper, "control_plane_config='/etc/verenigingsplatform/control-plane/runtime.json'"), 'deploywrapper gebruikt de vaste control-plane runtimeconfig');
    rgCheck(
        str_contains($wrapper, "host_launcher='/usr/local/sbin/verenigingsplatform-host-php'")
        && str_contains($wrapper, '"$host_launcher" control-plane --config="$control_plane_config" --refresh-only')
        && !str_contains($wrapper, 'trusted_control_executor=')
        && !str_contains($wrapper, '$platform_root/releases/$commit/bin/control-plane-executor.php'),
        'snapshotrefresh blijft aan de root-owned host-engine gebonden'
    );
    rgCheck(str_contains($wrapper, '--refresh-only'), 'deploywrapper bouwt na deploy uitsluitend een control-plane refreshsnapshot');
    rgCheck(str_contains($wrapper, "grep -Fqx 'REFRESH OK'"), 'snapshotrefresh moet expliciet succesvol worden bevestigd');
    $refresh = strpos($wrapper, '--refresh-only');
    $deployed = strpos($wrapper, 'echo "DEPLOYED $commit"');
    rgCheck($refresh !== false && $deployed !== false && $refresh < $deployed, 'DEPLOYED wordt pas gemeld nadat de snapshotrefresh is uitgevoerd');
}

echo "Release governance regression: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
