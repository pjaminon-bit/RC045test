<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$pad = $root . '/app/auth-capabilities.php';
$bron = file_get_contents($pad);
if (!is_string($bron) || $bron === '') {
    fwrite(STDERR, "FOUT: auth-capabilities.php kan niet worden gelezen.\n");
    exit(1);
}

$vereist = [
    "require_once __DIR__ . '/core/site.php';" => 'capabilitylaag laadt de gedeelde siteconfig-API',
    'function authCapabilityFeatureActief(string $capability): bool{static $cache=[];' => 'feature-resolutie is request-scoped gememoiseerd',
    '$config=siteConfig();' => 'feature-resolutie gebruikt siteConfig() in plaats van een eigen configrequire',
    'function authGebruikerRecordOpNaam(string $naam): ?array{static $cache=[];' => 'gebruikersresolutie is request-scoped gememoiseerd',
    'function authRolCapabilities(): array{static $cache=null;' => 'rolresolutie is request-scoped gememoiseerd',
];

foreach ($vereist as $naald => $uitleg) {
    if (strpos($bron, $naald) === false) {
        fwrite(STDERR, "FOUT #224: ontbreekt: {$uitleg}.\n");
        exit(1);
    }
}

if (preg_match("~authCapabilityFeatureActief\\([^}]+require\\s+[^;]*site-config\\.php~s", $bron) === 1) {
    fwrite(STDERR, "FOUT #224: authCapabilityFeatureActief() doet opnieuw een directe site-config.php require.\n");
    exit(1);
}

echo "OK #224: capability-, gebruiker- en rolresolutie gebruiken request-scoped caches.\n";
