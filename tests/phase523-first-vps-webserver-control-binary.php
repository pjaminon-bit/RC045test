<?php
$root = dirname(__DIR__);
require_once $root . '/app/deployment/webserver-contract.php';

$ok = 0;
$fout = 0;
function c523(bool $cond, string $label): void
{
    global $ok, $fout;
    if ($cond) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

$tmp = sys_get_temp_dir() . '/rc045-phase523-' . bin2hex(random_bytes(5));
@mkdir($tmp . '/tenant', 0750, true);
try {
    $context = [
        'tenant_root' => $tmp . '/tenant',
        'tenant_key' => 'test',
        'pool' => 'vp-test',
        'host' => 'test.example.nl',
        'document_root' => '/srv/verenigingsplatform/current',
        'document_root_real' => '/srv/verenigingsplatform/releases/' . str_repeat('a', 40),
        'runtime_plan_path' => $tmp . '/tenant/runtime/runtime-plan.json',
        'runtime_plan_sha256' => str_repeat('b', 64),
        'deployment' => [
            'path' => $tmp . '/tenant/deployment.json',
            'sha256' => str_repeat('c', 64),
        ],
        'socket' => '/run/php/vp-test.sock',
    ];

    $plan = web42Plan($context, $tmp . '/tenant/webserver');
    $binary = (string)($plan['apache']['control_binary'] ?? '');
    c523($binary === '/usr/sbin/apache2ctl', 'fase 4.2 genereert het vaste absolute Ubuntu/Debian apache2ctl-pad');
    c523(str_starts_with($binary, '/'), 'gegenereerde Apache control-binary is absoluut');

    $apply = (string)file_get_contents($root . '/bin/apply-vps-webserver.php');
    c523(
        str_contains($apply, "if(!str_starts_with(\$binary,'/')||!is_file(\$binary)||!is_executable(\$binary))"),
        'apply blijft fail-closed op niet-absolute of niet-uitvoerbare Apache control-binaries'
    );

    $contract = (string)file_get_contents($root . '/app/deployment/webserver-contract.php');
    c523(!str_contains($contract, "'control_binary' => 'apache2ctl'"), 'relatieve apache2ctl-regressie is uit het contract verwijderd');
} finally {
    if (is_dir($tmp . '/tenant/webserver')) @rmdir($tmp . '/tenant/webserver');
    if (is_dir($tmp . '/tenant')) @rmdir($tmp . '/tenant');
    if (is_dir($tmp)) @rmdir($tmp);
}

echo "Phase 5.2.3 first-VPS webserver live finding: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
