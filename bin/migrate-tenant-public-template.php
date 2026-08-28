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
$schrijfdata = [];

foreach ($targets as $sleutel => $nieuw) {
    $huidig = publicContentLees($sleutel);
    $legacy = $huidig !== null && tenantContentBevatLegacy($huidig);
    $status = $huidig === null ? 'ontbreekt' : ($legacy ? 'legacy' : 'tenant-eigen');

    if ($force || $huidig === null || $legacy) {
        $acties[$sleutel] = 'vervangen-' . $status;
        $schrijfdata[$sleutel] = $nieuw;
    } else {
        // Fase 5.4.1 breidt de neutrale dataset uit tot alle velden van de
        // gedeelde RC045-template. Tenant-eigen waarden winnen altijd; alleen
        // ontbrekende velden worden met veilige standaardinhoud aangevuld.
        $aangevuld = array_replace_recursive($nieuw, $huidig);
        if ($aangevuld !== $huidig) {
            $acties[$sleutel] = 'aanvullen-tenant-eigen';
            $schrijfdata[$sleutel] = $aangevuld;
        } else {
            $acties[$sleutel] = 'behouden-tenant-eigen';
        }
    }
    echo strtoupper($check ? 'check' : 'plan') . "  {$sleutel}  {$acties[$sleutel]}\n";
}

if ($check) {
    echo 'TEMPLATE CHECK OK  tenant=' . tenantRuntimeVeiligeSleutel((string) siteConfigGet('vereniging.sleutel', 'tenant')) . "\n";
    exit(0);
}

$geschreven = 0;
foreach ($targets as $sleutel => $nieuw) {
    if (!isset($schrijfdata[$sleutel])) continue;
    if (!publicContentSchrijfTenant($sleutel, $schrijfdata[$sleutel], true)) {
        fwrite(STDERR, "FOUT: dataset {$sleutel} kon niet tenantveilig worden geschreven.\n");
        exit(1);
    }
    $geschreven++;
    echo "GESCHREVEN  {$sleutel}\n";
}

echo 'TEMPLATE MIGRATION OK  tenant=' . tenantRuntimeVeiligeSleutel((string) siteConfigGet('vereniging.sleutel', 'tenant')) . ' datasets=' . $geschreven . "\n";
