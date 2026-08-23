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
$contract = bron($root . '/app/deployment/first-vps-bootstrap-contract.php');
$readiness = bron($root . '/docs/VPS-READINESS.md');
$first = bron($root . '/docs/VPS-FIRST-BOOTSTRAP.md');
$phpWorkflow = bron($root . '/.github/workflows/php85-compatibility.yml');

$checks = [
    [str_contains($runtime, "'php-version'] : '8.5'") || str_contains($runtime, ": '8.5';"), 'runtimegenerator gebruikt standaard PHP 8.5'],
    [str_contains($runtime, '--php-version=8.5') && str_contains($runtime, 'standaard 8.5'), 'runtime helptekst noemt PHP 8.5'],
    [str_contains($control, "['php-version'] ?? '8.5'"), 'control-plane generator gebruikt standaard PHP 8.5'],
    [str_contains($control, '[--php-version=8.5]'), 'control-plane helptekst noemt PHP 8.5'],
    [str_contains($bootstrap, "'php-version']??'8.5'"), 'first-VPS generator gebruikt standaard PHP 8.5'],
    [str_contains($contract, "'required_php_modules' => ['openssl','pdo_pgsql','mbstring','curl']"), 'production preflight eist alle gebruikte PHP-modules'],
    [str_contains($readiness, 'Ubuntu Server 26.04 LTS') && str_contains($readiness, 'php8.5-fpm') && str_contains($readiness, 'php8.5-pgsql') && str_contains($readiness, 'php8.5-mbstring') && str_contains($readiness, 'php8.5-curl'), 'readiness bevat volledige Ubuntu 26.04 PHP-packagebaseline'],
    [str_contains($readiness, "openssl|pdo_pgsql|mbstring|curl"), 'readiness controleert alle vereiste PHP-modules'],
    [str_contains($first, '--php-version=8.5'), 'first-VPS voorbeeld gebruikt PHP 8.5'],
    [str_contains($phpWorkflow, 'extensions: openssl, pdo_pgsql, mbstring, curl') && str_contains($phpWorkflow, 'grep -Fx mbstring') && str_contains($phpWorkflow, 'grep -Fx curl'), 'PHP 8.5 workflow bewijst volledige modulebaseline'],
    [!str_contains($readiness, '936cf4879f1611d94123fb3d3a0a33b831a49810'), 'readiness pint niet meer aan vervallen kandidaat'],
];

foreach ($checks as [$ok, $label]) {
    if ($ok) {
        echo "OK: {$label}\n";
    } else {
        $errors[] = $label;
        fwrite(STDERR, "FOUT: {$label}\n");
    }
}

$scanPaden = [
    $root . '/bin',
    $root . '/app/deployment',
    $root . '/docs',
    $root . '/tests',
    $root . '/.github/workflows',
];
$verboden = [
    '--php-version=8.3',
    'php8.3',
    'php-fpm8.3',
    '/etc/php/8.3',
    'Ubuntu 24.04',
    'ubuntu-24.04',
];

foreach ($scanPaden as $scanPad) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scanPad, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $bestand) {
        if (!$bestand->isFile()) continue;
        $ext = strtolower($bestand->getExtension());
        if (!in_array($ext, ['php','md','yml','yaml','sh'], true)) continue;
        $inhoud = @file_get_contents($bestand->getPathname());
        if (!is_string($inhoud)) continue;
        foreach ($verboden as $term) {
            if (!str_contains($inhoud, $term)) continue;
            $rel = ltrim(str_replace($root, '', $bestand->getPathname()), '/');
            $label = "verouderde VPS-baseline '{$term}' staat nog in {$rel}";
            $errors[] = $label;
            fwrite(STDERR, "FOUT: {$label}\n");
        }
    }
}

if ($errors !== []) exit(1);
echo "OK: Ubuntu 26.04 / PHP 8.5 VPS-baseline is consistent en vrij van operationele 8.3/24.04-restanten\n";
