<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function c55(bool $conditie, string $label): void
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

require_once $root . '/app/deployment/php-runtime-requirements.php';

$required = platformPhpRequiredExtensions();
c55(in_array('dom', $required, true), 'DOM is een expliciete productie-runtime-eis');
c55(in_array('pdo_pgsql', $required, true), 'PostgreSQL PDO blijft een productie-runtime-eis');
c55(count($required) === count(array_unique($required)), 'runtime-extensielijst bevat geen duplicaten');

$checker = (string)file_get_contents($root . '/bin/check-release-tenant.php');
c55(
    str_contains($checker, "php-runtime-requirements.php") && str_contains($checker, 'platformPhpAssertRequiredExtensions()'),
    'kandidaatrelease controleert runtime-eisen vóór activatie'
);

$health = (string)file_get_contents($root . '/healthz.php');
c55(
    str_contains($health, "php-runtime-requirements.php") && str_contains($health, 'platformPhpAssertRequiredExtensions()'),
    'health endpoint neemt runtime-eisen mee in 204/503 oordeel'
);

$helper = (string)file_get_contents($root . '/app/deployment/php-runtime-requirements.php');
c55(
    str_contains($helper, 'DOMDocument::class') && str_contains($helper, 'DOMXPath::class'),
    'DOMDocument en DOMXPath worden expliciet bewezen'
);

echo "PHP runtime guard: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
