<?php
$root = dirname(__DIR__);
$runtime = @file_get_contents($root . '/app/control-plane/control-plane-runtime.php');
$ok = 0; $fout = 0;
function cpiCheck(bool $cond, string $label): void {
    global $ok, $fout;
    if ($cond) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

cpiCheck(is_string($runtime) && $runtime !== '', 'control-plane runtime is leesbaar');
if (is_string($runtime)) {
    cpiCheck(str_contains($runtime, 'function cp51PlatformMutatieInterlock(): void'), 'server-side mutatie-interlock bestaat');
    cpiCheck(str_contains($runtime, "if (\$pct >= 97.0)"), '97%-schijfgrens wordt server-side afgedwongen');
    cpiCheck(str_contains($runtime, "'sessions_dir'"), 'sessiestore maakt deel uit van de interlock');
    cpiCheck(str_contains($runtime, "'snapshot_file'"), 'snapshotbeschikbaarheid maakt deel uit van de interlock');
    cpiCheck(str_contains($runtime, "in_array(\$actie, cp51MuterendeQueueActies(), true)) cp51PlatformMutatieInterlock();"), 'iedere muterende queuewrite passeert de interlock');
    cpiCheck(str_contains($runtime, "'schedule-create','tls-renew','onboarding-resume'"), 'nieuwe adminmutaties vallen ook onder de interlock');
    cpiCheck(!str_contains($runtime, "'admin-refresh','schedule-cancel','diagnose'\n    ];"), 'herstelacties worden niet per ongeluk als mutatie geblokkeerd');
}

echo "Control-plane interlock regression: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
