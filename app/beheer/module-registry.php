<?php
// ============================================================
// Compatibiliteitsadapter beheerregistry
// ============================================================
// De beheerarchitectuur heeft vanaf fase 2.5 één bron van waarheid:
// app/core/platform-definities.php. Dit bestand blijft alleen bestaan voor
// helpers die beheerModuleRegistry() gebruiken; er staat geen eigen module-
// configuratie meer in.
// ============================================================

$platform = require dirname(__DIR__) . '/core/platform-definities.php';
$componenten = isset($platform['beheer']) && is_array($platform['beheer']) ? $platform['beheer'] : [];
$beheerRoot = dirname(__DIR__, 2) . '/beheer';
$registry = [];

foreach ($componenten as $sleutel => $definitie) {
    if (!is_array($definitie)) continue;
    $route = trim((string) ($definitie['route'] ?? ''));
    $registry[(string) $sleutel] = [
        'label' => (string) ($definitie['label'] ?? $sleutel),
        'status' => 'module',
        'bootstrap' => isset($definitie['bootstrap']) ? (string) $definitie['bootstrap'] : null,
        'editor' => $route === '' ? null : $beheerRoot . '/' . $route,
        'feature' => isset($definitie['feature']) ? (string) $definitie['feature'] : null,
        'capability' => isset($definitie['capability']) ? (string) $definitie['capability'] : null,
        'categorie' => (string) ($definitie['categorie'] ?? 'Overig'),
    ];
}

return $registry;
