<?php
$root = dirname(__DIR__);
$errors = [];

function bron(string $pad): string {
    $raw = @file_get_contents($pad);
    if (!is_string($raw)) {
        throw new RuntimeException('Ontbrekende testbron: ' . $pad);
    }
    return $raw;
}

$runtime = bron($root . '/bin/prepare-vps-runtime.php');
$control = bron($root . '/bin/prepare-vps-control-plane.php');
$bootstrap = bron($root . '/bin/prepare-first-vps-bootstrap.php');
$readiness = bron($root . '/docs/VPS-READINESS.md');
$first = bron($root . '/docs/VPS-FIRST-BOOTSTRAP.md');

$checks = [
    [str_contains($runtime, "'php-version'] : '8.5'") || str_contains($runtime, ": '8.5';"), 'runtimegenerator gebruikt standaard PHP 8.5'],
    [str_contains($runtime, '--php-version=8.5') && str_contains($runtime, 'standaard 8.5'), 'runtime helptekst noemt PHP 8.5'],
    [str_contains($control, "['php-version'] ?? '8.5'"), 'control-plane generator gebruikt standaard PHP 8.5'],
    [str_contains($control, '[--php-version=8.5]'), 'control-plane helptekst noemt PHP 8.5'],
    [str_contains($bootstrap, "'php-version']??'8.5'"), 'first-VPS generator gebruikt standaard PHP 8.5'],
    [str_contains($readiness, 'Ubuntu Server 26.04 LTS') && str_contains($readiness, 'php8.5-fpm') && str_contains($readiness, 'php8.5-pgsql'), 'readiness bevat Ubuntu 26.04 packagebaseline'],
    [str_contains($first, '--php-version=8.5'), 'first-VPS voorbeeld gebruikt PHP 8.5'],
    [!str_contains($readiness, '936cf4879f1611d94123fb3d3a0a33b831a49810'), 'readiness pint niet meer aan vervallen kandidaat'],
    [!str_contains($first, '--php-version=8.3'), 'first-VPS handleiding bevat geen oude PHP 8.3 bootstrapoptie'],
];

foreach ($checks as [$ok, $label]) {
    if ($ok) {
        echo "OK: {$label}\n";
    } else {
        $errors[] = $label;
        fwrite(STDERR, "FOUT: {$label}\n");
    }
}

if ($errors !== []) exit(1);
echo "OK: Ubuntu 26.04 / PHP 8.5 VPS-baseline is consistent\n";
