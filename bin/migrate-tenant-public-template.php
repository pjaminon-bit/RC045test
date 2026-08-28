<?php
// Migreert een bestaande externe tenant van historische RC045-content naar
// neutrale publieke startdata. Bestaande veilige tenantcontent blijft staan.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Alleen via CLI beschikbaar.');
}

$opt = getopt('', ['config:', 'check', 'apply', 'force', 'help']);
if (isset($opt['help'])) {
    echo "Gebruik:\n";
    echo "  php bin/migrate-tenant-public-template.php --config=/absoluut/config.php --check\n";
    echo "  php bin/migrate-tenant-public-template.php --config=/absoluut/config.php --apply [--force]\n";
    exit(0);
}

$configPad = trim((string) ($opt['config'] ?? ''));
if ($configPad === '' || ($configPad[0] ?? '') !== '/' || !is_file($configPad) || !is_readable($configPad)) {
    fwrite(STDERR, "FOUT: --config moet naar een leesbaar absoluut tenantconfigbestand wijzen.\n");
    exit(2);
}
$check = isset($opt['check']);
$apply = isset($opt['apply']);
$force = isset($opt['force']);
if ($check === $apply) {
    fwrite(STDERR, "FOUT: kies exact één van --check of --apply.\n");
    exit(2);
}

putenv('VERENIGING_REQUIRE_TENANT_CONFIG=1');
putenv('VERENIGING_CONFIG_FILE=' . $configPad);

$root = dirname(__DIR__);
require_once $root . '/app/core/site.php';
require_once $root . '/app/content/public-content-store.php';
require_once $root . '/app/content/tenant-content-policy.php';

$naam = trim(siteNaam()) ?: 'Vereniging';
$targets = [
    'homepage' => tenantContentNeutraleHomepage($naam),
    'contact' => tenantContentNeutraalContact(),
];
$acties = [];

foreach ($targets as $sleutel => $nieuw) {
    $huidig = publicContentLees($sleutel);
    $status = $huidig === null ? 'ontbreekt' : (tenantContentBevatLegacy($huidig) ? 'legacy' : 'tenant-eigen');
    $moetSchrijven = $force || $huidig === null || tenantContentBevatLegacy($huidig);
    $acties[$sleutel] = $moetSchrijven ? 'vervangen-' . $status : 'behouden-tenant-eigen';
    echo strtoupper($check ? 'check' : 'plan') . "  {$sleutel}  {$acties[$sleutel]}\n";
}

if ($check) {
    echo 'TEMPLATE CHECK OK  tenant=' . tenantRuntimeVeiligeSleutel((string) siteConfigGet('vereniging.sleutel', 'tenant')) . "\n";
    exit(0);
}

$geschreven = 0;
foreach ($targets as $sleutel => $nieuw) {
    if (!str_starts_with($acties[$sleutel], 'vervangen-')) continue;
    if (!publicContentSchrijfTenant($sleutel, $nieuw, true)) {
        fwrite(STDERR, "FOUT: dataset {$sleutel} kon niet tenantveilig worden geschreven.\n");
        exit(1);
    }
    $geschreven++;
    echo "GESCHREVEN  {$sleutel}\n";
}

echo 'TEMPLATE MIGRATION OK  tenant=' . tenantRuntimeVeiligeSleutel((string) siteConfigGet('vereniging.sleutel', 'tenant')) . ' datasets=' . $geschreven . "\n";
