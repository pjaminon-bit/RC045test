<?php
$root = dirname(__DIR__);
$readmePad = $root . '/README.md';
$readme = file_get_contents($readmePad);
if (!is_string($readme)) {
    fwrite(STDERR, "FOUT: README.md kon niet worden gelezen.\n");
    exit(1);
}

$ok = 0;
$fout = 0;
function readme160Check(bool $conditie, string $melding): void
{
    global $ok, $fout;
    if ($conditie) {
        $ok++;
        echo "OK: {$melding}\n";
        return;
    }
    $fout++;
    fwrite(STDERR, "FOUT: {$melding}\n");
}

readme160Check(strlen($readme) > 2500, 'README is een volwaardige platformingang en niet de oude tweeregelige stub');
readme160Check(str_contains($readme, 'verenigingsplatform'), 'README benoemt het huidige verenigingsplatform');
readme160Check(str_contains($readme, 'multi-tenant'), 'README benoemt de leidende multi-tenantarchitectuur');
readme160Check(str_contains($readme, 'gedeelde codebase'), 'README maakt gedeelde applicatiecode expliciet');
readme160Check(str_contains($readme, '/srv/verenigingen/<tenant>/'), 'README onderscheidt tenant-eigen server-side data');
readme160Check(str_contains($readme, 'VERENIGING_REQUIRE_TENANT_CONFIG=1'), 'README documenteert de fail-closed tenantconfiggrens');
readme160Check(str_contains($readme, 'compatibiliteitslagen'), 'README markeert standalone/template als compatibiliteitslaag');
readme160Check(str_contains($readme, 'bash tests/run-all.sh'), 'README geeft de actuele volledige lokale regressie-entrypoint');
readme160Check(str_contains($readme, 'PHP 8.5 compatibility'), 'README benoemt de PHP 8.5 CI-gate');
readme160Check(str_contains($readme, 'Security supply-chain'), 'README benoemt de supply-chain CI-gate');
readme160Check(str_contains($readme, 'GitHub issue #138'), 'README linkt naar de centrale security/hardeningtracker');
readme160Check(str_contains($readme, 'actuele VPS deploymentcontract'), 'README verwijst nieuwe tenants naar het actuele VPS-deploymentcontract');
readme160Check(!str_contains($readme, 'auditissue #161'), 'README bevat geen tijdelijke waarschuwing meer over de inmiddels gereconcilieerde VPS-documentatie');
readme160Check(!str_contains($readme, "# rc045\nRC045 Website"), 'oude README-stub is verwijderd');

$vereisteLokaleLinks = [
    'site-config.local.example.php',
    'app/deployment/php-runtime-requirements.php',
    'package.json',
    'docs/VPS-DEPLOYMENT.md',
    'docs/PROVISIONING.md',
    'docs/ADMIN-BOOTSTRAP.md',
    'docs/GITHUB-VPS-TEST-DEPLOYMENT.md',
    'docs/VPS-CONTROL-PLANE.md',
    'docs/VPS-AUTHENTICATED-E2E.md',
    'docs/BACKUP-ATTESTATION.md',
    'docs/TEMPLATE-MIGRATIE.md',
    'tests/run-all.sh',
];
foreach ($vereisteLokaleLinks as $relatief) {
    readme160Check(str_contains($readme, '(' . $relatief . ')'), "README linkt naar {$relatief}");
    readme160Check(is_file($root . '/' . $relatief), "README-doel {$relatief} bestaat in de repository");
}

$workflowPaden = [
    '.github/workflows/deploy-dev.yml',
    '.github/workflows/full-regression.yml',
    '.github/workflows/php85-compatibility.yml',
    '.github/workflows/security-supply-chain.yml',
];
foreach ($workflowPaden as $workflow) {
    readme160Check(str_contains($readme, $workflow), "README noemt CI-workflow {$workflow}");
    readme160Check(is_file($root . '/' . $workflow), "genoemde CI-workflow {$workflow} bestaat");
}

readme160Check(
    str_contains(strtolower($readme), 'voer repository-/release-php niet als root uit'),
    'README bewaakt de root/release trust-boundary voor nieuwe maintainers'
);

printf("README platform-entrypoint #160/#161: %d OK, %d fout(en)\n", $ok, $fout);
exit($fout === 0 ? 0 : 1);
