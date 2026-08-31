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
}

echo "Release governance regression: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
