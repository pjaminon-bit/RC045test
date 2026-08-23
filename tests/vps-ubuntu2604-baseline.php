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
$deployment = bron($root . '/docs/VPS-DEPLOYMENT.md');
$runtimeDoc = bron($root . '/docs/VPS-RUNTIME-ISOLATION.md');
$controlDoc = bron($root . '/docs/VPS-CONTROL-PLANE.md');
$phase52 = bron($root . '/tests/phase52-first-vps-bootstrap.php');
$phpWorkflow = bron($root . '/.github/workflows/php85-compatibility.yml');

$checks = [
    [str_contains($runtime, "'php-version'] : '8.5'") || str_contains($runtime, ": '8.5';"), 'runtimegenerator gebruikt standaard PHP 8.5'],
    [str_contains($runtime, '--php-version=8.5') && str_contains($runtime, 'standaard 8.5'), 'runtime helptekst noemt PHP 8.5 als default'],
    [str_contains($control, "['php-version'] ?? '8.5'"), 'control-plane generator gebruikt standaard PHP 8.5'],
    [str_contains($control, '[--php-version=8.5]'), 'control-plane helptekst noemt PHP 8.5'],
    [str_contains($bootstrap, "'php-version']??'8.5'"), 'first-VPS generator gebruikt standaard PHP 8.5'],
    [str_contains($contract, "'required_php_modules' => ['openssl','pdo_pgsql','mbstring','curl']"), 'production preflight eist alle gebruikte PHP-modules'],
    [str_contains($readiness, 'Ubuntu Server 26.04 LTS') && str_contains($readiness, 'php8.5-fpm') && str_contains($readiness, 'php8.5-pgsql') && str_contains($readiness, 'php8.5-mbstring') && str_contains($readiness, 'php8.5-curl'), 'readiness bevat volledige Ubuntu 26.04 PHP-packagebaseline'],
    [str_contains($readiness, "openssl|pdo_pgsql|mbstring|curl"), 'readiness controleert alle vereiste PHP-modules'],
    [str_contains($first, '--php-version=8.5'), 'first-VPS voorbeeld gebruikt PHP 8.5'],
    [str_contains($deployment, '--php-version=8.5') && str_contains($deployment, '/etc/php/8.5/fpm/pool.d') && !str_contains($deployment, '--php-version=8.3'), 'deploymentdocumentatie gebruikt alleen PHP 8.5 als uitvoeringsvoorbeeld'],
    [str_contains($runtimeDoc, '--php-version=8.5') && str_contains($runtimeDoc, '/etc/php/8.5/fpm/pool.d') && !str_contains($runtimeDoc, '--php-version=8.3'), 'runtime-isolatiedocumentatie gebruikt alleen PHP 8.5 als uitvoeringsvoorbeeld'],
    [str_contains($controlDoc, '--php-version=8.5') && !str_contains($controlDoc, '--php-version=8.3'), 'control-plane documentatie gebruikt PHP 8.5'],
    [str_contains($phase52, '--php-version=8.5') && !str_contains($phase52, '--php-version=8.3'), 'first-VPS regressiefixture gebruikt PHP 8.5'],
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

// Scan alleen productie-instructies en defaults. tests/phase53-ubuntu2604-runtime.php
// bevat bewust een expliciete PHP 8.3 override om de eerder afgesproken legacy-
// compatibiliteit te bewaken; dat is geen first-VPS productiebaseline.
$scanBestanden = [
    $root . '/bin/prepare-vps-runtime.php',
    $root . '/bin/prepare-vps-control-plane.php',
    $root . '/bin/prepare-first-vps-bootstrap.php',
    $root . '/app/deployment/first-vps-bootstrap-contract.php',
    $root . '/docs/VPS-READINESS.md',
    $root . '/docs/VPS-FIRST-BOOTSTRAP.md',
    $root . '/docs/VPS-DEPLOYMENT.md',
    $root . '/docs/VPS-RUNTIME-ISOLATION.md',
    $root . '/docs/VPS-CONTROL-PLANE.md',
    $root . '/tests/phase52-first-vps-bootstrap.php',
    $root . '/.github/workflows/php85-compatibility.yml',
];
$verboden = [
    '--php-version=8.3',
    'php8.3',
    'php-fpm8.3',
    '/etc/php/8.3',
    'Ubuntu 24.04',
    'ubuntu-24.04',
];
foreach ($scanBestanden as $bestand) {
    $inhoud = bron($bestand);
    foreach ($verboden as $term) {
        if (!str_contains($inhoud, $term)) continue;
        $rel = ltrim(str_replace($root, '', $bestand), '/');
        $label = "verouderde productiebaseline '{$term}' staat nog in {$rel}";
        $errors[] = $label;
        fwrite(STDERR, "FOUT: {$label}\n");
    }
}

if ($errors !== []) exit(1);
echo "OK: Ubuntu 26.04 / PHP 8.5 productiebaseline is consistent; expliciete legacy-compatibiliteitstests blijven toegestaan\n";
