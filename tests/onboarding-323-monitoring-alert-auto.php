<?php
$root = dirname(__DIR__);
require_once $root . '/app/deployment/monitoring-alert.php';

$ok = 0; $fout = 0;
function t323(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) { $ok++; echo "OK: {$label}\n"; return; }
    $fout++; fwrite(STDERR, "FOUT: {$label}\n");
}
function t323Throws(callable $fn, string $needle): bool
{
    try { $fn(); }
    catch (RuntimeException $e) { return str_contains($e->getMessage(), $needle); }
    return false;
}

// Een werkelijk ontbrekende adapter is een ondersteunde installatiekeuze:
// monitoring blijft actief, maar alertdelivery wordt expliciet disabled.
t323(
    monitoring46AlertModeUitMetadata('/etc/verenigingsplatform/monitoring/alert-command', false, false, false, false, null) === 'disabled',
    'ontbrekende adapter kiest expliciet disabled'
);

// Een geldige externe adapter houdt alerting enabled.
t323(
    monitoring46AlertModeUitMetadata('/usr/local/sbin/vp-alert', true, true, false, true, ['uid'=>0, 'mode'=>0100755]) === 'enabled',
    'geldige root-owned executable adapter kiest enabled'
);

// Een geconfigureerde maar onveilige adapter is geen reden om stil uit te
// schakelen: provisioning moet fail-closed blijven.
t323(
    t323Throws(
        fn() => monitoring46AlertModeUitMetadata('/usr/local/sbin/vp-alert', true, true, false, true, ['uid'=>1000, 'mode'=>0100755]),
        'niet root-owned'
    ),
    'niet-root-owned adapter blijft fail-closed'
);
t323(
    t323Throws(
        fn() => monitoring46AlertModeUitMetadata('/usr/local/sbin/vp-alert', false, false, true, false, null),
        'symlink'
    ),
    'dangling symlink wordt niet als afwezige adapter geaccepteerd'
);
t323(
    t323Throws(
        fn() => monitoring46AlertModeUitMetadata('relatief/alert-command', false, false, false, false, null),
        'niet absoluut'
    ),
    'relatief adapterpad blijft fail-closed'
);

if ($fout > 0) {
    fwrite(STDERR, "#323 monitoring alert regressie: {$fout} fout(en), {$ok} geslaagd.\n");
    exit(1);
}
echo "#323 monitoring alert regressie geslaagd ({$ok} checks).\n";
