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
        '--dry-run',
    ];

    [$code, $stdout, $stderr] = r53($basis);
    $plan = json_decode($stdout, true);
    c53($code === 0 && is_array($plan), 'first-VPS dry-run zonder versie-override levert geldig plan');
    c53(($plan['platform']['php_version'] ?? '') === '8.5', 'Ubuntu 26.04 first-VPS CLI gebruikt PHP 8.5 als default');
    c53(($plan['tenant']['php_version'] ?? '') === '8.5', 'eerste tenant erft PHP 8.5 als default');
    c53(($plan['preflight']['postgresql_minimum_major'] ?? 0) === 16, 'PostgreSQL blijft capability-based 16+ en accepteert PostgreSQL 18');
    c53(($plan['preflight']['apache_minimum_version'] ?? '') === '2.4.49', 'Apache blijft capability-based en niet aan Ubuntu-packageversie vastgepind');

    $override = $basis;
    array_splice($override, count($override) - 1, 0, ['--php-version=8.3']);
    [$code83, $stdout83] = r53($override);
    $plan83 = json_decode($stdout83, true);
    c53($code83 === 0 && ($plan83['platform']['php_version'] ?? '') === '8.3', 'expliciete PHP 8.3 override blijft ondersteund');

    require_once $root . '/app/deployment/runtime-contract.php';
    c53(runtime41PhpVersie('8.5') === true, 'runtimecontract accepteert PHP 8.5');
    c53(runtime41PhpVersie('8.3') === true, 'runtimecontract behoudt PHP 8.3 compatibiliteit');

    $prepare = (string)file_get_contents($root . '/bin/prepare-first-vps-bootstrap.php');
    c53(str_contains($prepare, '--php-version=8.5'), 'CLI-help documenteert PHP 8.5 als productie-default');
    c53(!str_contains($prepare, "['php-version']??'8.3'"), 'CLI bevat geen verborgen PHP 8.3 default meer');
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
