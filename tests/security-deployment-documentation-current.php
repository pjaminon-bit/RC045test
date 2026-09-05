<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function deployment161Check(bool $conditie, string $melding): void
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

$deploymentPad = $root . '/docs/VPS-DEPLOYMENT.md';
$deployment = @file_get_contents($deploymentPad);
deployment161Check(is_string($deployment), 'actuele VPS deploymentdocumentatie is leesbaar');
$deployment = is_string($deployment) ? $deployment : '';

deployment161Check(str_contains($deployment, 'actuele architectuur- en navigatie-ingang'), 'VPS-DEPLOYMENT presenteert zichzelf als actuele architectuuringang');
deployment161Check(str_contains($deployment, 'Stand-alone') || str_contains(strtolower($deployment), 'standalone'), 'VPS-DEPLOYMENT onderscheidt standalonecompatibiliteit');
deployment161Check(str_contains($deployment, 'Multi-tenant VPS'), 'VPS-DEPLOYMENT beschrijft het leidende multi-tenant VPS-model');
deployment161Check(str_contains($deployment, 'geen handmatige FTP-upload'), 'VPS-DEPLOYMENT maakt duidelijk dat VPS geen handmatige FTP-dotfilestap heeft');
deployment161Check(str_contains($deployment, '/srv/verenigingsplatform/releases/<commit>'), 'VPS-DEPLOYMENT documenteert immutable gedeelde releases');
deployment161Check(str_contains($deployment, '/srv/verenigingen/<tenant>'), 'VPS-DEPLOYMENT documenteert tenant-eigen serverstate');

$verouderdeDeploymentTeksten = [
    'Status per **20-08-2026**: fase 3.5.1 + fase 4.1 runtimevoorbereiding',
    'DNS, TLS en concrete webserver-vhosts volgen in fase 4.2–4.4',
    'Resterende fase 4-stappen',
    'toekomstige VPS',
    'databasecredentials volgen apart in fase 4.5',
];
foreach ($verouderdeDeploymentTeksten as $tekst) {
    deployment161Check(!str_contains($deployment, $tekst), "verouderde VPS-roadmaptekst ontbreekt: {$tekst}");
}

$vereisteDocs = [
    'PROVISIONING.md',
    'VPS-FIRST-BOOTSTRAP.md',
    'VPS-READINESS.md',
    'VPS-RUNTIME-ISOLATION.md',
    'VPS-WEBSERVER.md',
    'VPS-DNS.md',
    'VPS-TLS.md',
    'VPS-DATABASE.md',
    'VPS-RELEASES.md',
    'VPS-MONITORING.md',
    'VPS-LIFECYCLE.md',
    'GITHUB-VPS-TEST-DEPLOYMENT.md',
    'VPS-AUTHENTICATED-E2E.md',
    'VPS-CONTROL-PLANE.md',
    'BACKUP-ATTESTATION.md',
    'FULL-REGRESSION-ACCEPTANCE.md',
];
foreach ($vereisteDocs as $doc) {
    deployment161Check(str_contains($deployment, '](' . $doc . ')'), "VPS-DEPLOYMENT linkt naar {$doc}");
    deployment161Check(is_file($root . '/docs/' . $doc), "gelinkt operationeel document {$doc} bestaat");
}

$legacyOpslag = [
    'leden-opslag.php',
    'vergaderingen-opslag.php',
    'taken-opslag.php',
    'operationele-taken-opslag.php',
    'evenementen-opslag.php',
];
foreach ($legacyOpslag as $bestand) {
    $inhoud = @file_get_contents($root . '/' . $bestand);
    deployment161Check(is_string($inhoud), "{$bestand} is leesbaar");
    $inhoud = is_string($inhoud) ? $inhoud : '';
    deployment161Check(str_contains($inhoud, 'STANDALONE COMPATIBILITEIT'), "{$bestand} markeert legacy opslag als standalonecompatibiliteit");
    deployment161Check(str_contains($inhoud, 'docs/VPS-DEPLOYMENT.md'), "{$bestand} verwijst naar het actuele VPS-contract");
    deployment161Check(!str_contains($inhoud, 'deploy dotfiles overslaat'), "{$bestand} bevat geen oude dotfiles-overslaanclaim");
    deployment161Check(!str_contains($inhoud, 'met de hand via FTP'), "{$bestand} instrueert geen handmatige FTP voor .htaccess");
}

deployment161Check(is_file($root . '/.htaccess'), 'repository-.htaccess bestaat als versiebeheerd releasebestand');

$readme = @file_get_contents($root . '/README.md');
deployment161Check(is_string($readme) && str_contains($readme, '(docs/VPS-DEPLOYMENT.md)'), 'README linkt naar het actuele VPS-deploymentcontract');
deployment161Check(is_string($readme) && !str_contains($readme, 'auditissue #161'), 'README bevat geen tijdelijke #161-waarschuwing meer');

printf("VPS deploymentdocumentatie #161: %d OK, %d fout(en)\n", $ok, $fout);
exit($fout === 0 ? 0 : 1);
