<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function c53(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) {
        $ok++;
        echo "OK: {$label}\n";
        return;
    }
    $fout++;
    fwrite(STDERR, "FOUT: {$label}\n");
}

function r53(array $argv): array
{
    $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proces = proc_open($argv, $spec, $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($proces)) return [255, '', 'proc_open faalde'];
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    return [proc_close($proces), (string)$stdout, (string)$stderr];
}

$tmp = sys_get_temp_dir() . '/rc045-phase53-' . bin2hex(random_bytes(5));
@mkdir($tmp, 0750, true);

try {
    $basis = [
        PHP_BINARY,
        $root . '/bin/prepare-first-vps-bootstrap.php',
        '--source=' . $root,
        '--commit=' . str_repeat('a', 40),
        '--output=' . $tmp . '/bundle',
        '--platform-root=' . $tmp . '/platform',
        '--tenant-base=' . $tmp . '/tenants',
        '--platform-host=beheer.platform.example',
        '--platform-strategy=direct',
        '--platform-ipv4=203.0.113.10',
        '--tenant-key=voorbeeld',
        '--tenant-name=Voorbeeldvereniging',
        '--tenant-host=voorbeeld.platform.example',
        '--tenant-strategy=direct',
        '--tenant-ipv4=203.0.113.10',
        '--operator-user=platformadmin',
        '--modules=website',
    ];

    [$code, $stdout, $stderr] = r53(array_merge($basis, ['--dry-run']));
    $plan = json_decode($stdout, true);
    c53($code === 0 && is_array($plan), 'first-VPS dry-run zonder versie-override levert geldig plan');
    c53(($plan['platform']['php_version'] ?? '') === '8.5', 'Ubuntu 26.04 first-VPS CLI gebruikt PHP 8.5 als default');
    c53(($plan['tenant']['php_version'] ?? '') === '8.5', 'eerste tenant erft PHP 8.5 als default');
    c53(($plan['preflight']['postgresql_minimum_major'] ?? 0) === 16, 'PostgreSQL blijft capability-based 16+ en accepteert PostgreSQL 18');
    c53(($plan['preflight']['apache_minimum_version'] ?? '') === '2.4.49', 'Apache blijft capability-based en niet aan Ubuntu-packageversie vastgepind');

    [$schrijfCode, $schrijfOut, $schrijfErr] = r53($basis);
    $planPad = $tmp . '/bundle/first-vps-bootstrap-plan.json';
    c53($schrijfCode === 0 && is_file($planPad), 'PHP 8.5 first-VPS bootstrapbundle wordt werkelijk geschreven');
    $geschrevenPlan = is_file($planPad) ? json_decode((string)file_get_contents($planPad), true) : null;
    c53(is_array($geschrevenPlan) && ($geschrevenPlan['platform']['php_version'] ?? '') === '8.5', 'geschreven bootstrapbundle bindt platform aan PHP 8.5');
    c53(is_array($geschrevenPlan) && ($geschrevenPlan['tenant']['php_version'] ?? '') === '8.5', 'geschreven bootstrapbundle bindt eerste tenant aan PHP 8.5');

    [$checkCode, $checkOut, $checkErr] = r53([
        PHP_BINARY,
        $root . '/bin/apply-first-vps-bootstrap.php',
        '--plan=' . $planPad,
        '--check',
    ]);
    c53(
        $checkCode === 0 && str_contains($checkOut, 'CHECK OK phase=5.2'),
        'geschreven PHP 8.5 bootstrapbundle doorstaat de echte first-VPS --check validatieroute'
    );

    $override = array_merge($basis, ['--php-version=8.3', '--dry-run']);
    [$code83, $stdout83] = r53($override);
    $plan83 = json_decode($stdout83, true);
    c53($code83 === 0 && ($plan83['platform']['php_version'] ?? '') === '8.3', 'expliciete PHP 8.3 override blijft ondersteund');

    require_once $root . '/app/deployment/first-vps-bootstrap-contract.php';
    $directPlan = bootstrap52Plan([
        'source' => $root,
        'commit' => str_repeat('b', 40),
        'output_dir' => $tmp . '/direct-contract',
        'platform_root' => $tmp . '/direct-platform',
        'tenant_base' => $tmp . '/direct-tenants',
        'platform_host' => 'beheer-direct.platform.example',
        'platform_dns_strategy' => 'direct',
        'platform_ipv4' => '203.0.113.20',
        'tenant_key' => 'direct',
        'tenant_name' => 'Directe contracttest',
        'tenant_host' => 'direct.platform.example',
        'tenant_dns_strategy' => 'direct',
        'tenant_ipv4' => '203.0.113.20',
        'operator_user' => 'platformadmin',
        'modules' => ['website'],
    ]);
    c53(($directPlan['platform']['php_version'] ?? '') === '8.5', 'onderliggend first-VPS contract gebruikt zonder CLI eveneens PHP 8.5');
    c53(($directPlan['tenant']['php_version'] ?? '') === '8.5', 'onderliggend contract laat tenant dezelfde PHP 8.5 default erven');
    c53(runtime41PhpVersie('8.5') === true, 'runtimecontract accepteert PHP 8.5');
    c53(runtime41PhpVersie('8.3') === true, 'runtimecontract behoudt PHP 8.3 compatibiliteit');

    $prepare = (string)file_get_contents($root . '/bin/prepare-first-vps-bootstrap.php');
    $contract = (string)file_get_contents($root . '/app/deployment/first-vps-bootstrap-contract.php');
    c53(str_contains($prepare, '--php-version=8.5'), 'CLI-help documenteert PHP 8.5 als productie-default');
    c53(!str_contains($prepare, "['php-version']??'8.3'"), 'CLI bevat geen verborgen PHP 8.3 default meer');
    c53(!str_contains($contract, "['php_version'] ?? '8.3'"), 'first-VPS contract bevat geen verborgen PHP 8.3 fallback meer');
} finally {
    $wis = function (string $pad) use (&$wis): void {
        if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
        if (!is_dir($pad)) return;
        foreach (scandir($pad) ?: [] as $naam) {
            if ($naam === '.' || $naam === '..') continue;
            $wis($pad . '/' . $naam);
        }
        @rmdir($pad);
    };
    $wis($tmp);
}

echo "RESULTAAT: {$ok} ok, {$fout} fout\n";
exit($fout === 0 ? 0 : 1);
