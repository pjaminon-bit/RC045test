<?php
$root = dirname(__DIR__);
$apply = (string)file_get_contents($root . '/bin/apply-first-vps-bootstrap.php');
$ok = 0;
$fout = 0;

function c529(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) {
        $ok++;
        echo "OK: {$label}\n";
    } else {
        $fout++;
        fwrite(STDERR, "FOUT: {$label}\n");
    }
}

$begin = strpos($apply, 'function b52TenantBase(array$p):void');
$end = $begin === false ? false : strpos($apply, 'function b52Write(', $begin);
$body = ($begin !== false && $end !== false) ? substr($apply, $begin, $end - $begin) : '';

c529($begin !== false && $end !== false, 'first-VPS bootstrap heeft een afgebakende tenantbasis-normalisatie');
c529(str_contains($body, '!@mkdir($base,0711,true)'), 'gedeelde tenantbasis wordt bij aanmaak traverse-only 0711');
c529(str_contains($body, '!@chown($base,0)') && str_contains($body, '!@chgrp($base,0)'), 'gedeelde tenantbasis blijft root:root');
c529(str_contains($body, '!@chmod($base,0711)'), 'bestaande tenantbasis wordt naar root:root 0711 genormaliseerd');
c529(str_contains($body, 'b52Meta($base,0711,true)'), 'tenantbasis 0711 wordt na mutatie fail-closed geverifieerd');
c529(!str_contains($body, '0750'), 'tenantbasis wordt niet teruggezet naar 0750 waardoor tenant-PHP-FPM traversal zou blokkeren');
c529(str_contains($apply, 'b52TenantBase($p);b52Child($current,\'apply-vps-control-plane.php\''), 'tenantbasis wordt vóór tenantprovisioning en runtimegebruik genormaliseerd');

if ($fout > 0) {
    fwrite(STDERR, "{$fout} fase-5.2.9 regressietest(s) mislukt.\n");
    exit(1);
}

echo "ALLE FASE-5.2.9 TENANT-BASE-TRAVERSAL TESTS GESLAAGD ({$ok})\n";
