<?php
// ============================================================
// Modulair beheer-bootstrap
// ============================================================

function beheerModuleRegistry(): array
{
    static $registry = null;
    if ($registry === null) {
        $geladen = require __DIR__ . '/module-registry.php';
        $registry = is_array($geladen) ? $geladen : [];
    }
    return $registry;
}

function beheerModuleDefinitie(string $sleutel): array
{
    $registry = beheerModuleRegistry();
    return isset($registry[$sleutel]) && is_array($registry[$sleutel]) ? $registry[$sleutel] : [];
}

function beheerModuleStatus(string $sleutel): string
{
    return (string) (beheerModuleDefinitie($sleutel)['status'] ?? 'legacy');
}

function beheerModuleIsGemigreerd(string $sleutel): bool
{
    return beheerModuleStatus($sleutel) === 'module';
}

function beheerGemigreerdeModules(): array
{
    return array_filter(
        beheerModuleRegistry(),
        static fn($def) => is_array($def) && (($def['status'] ?? '') === 'module')
    );
}

function beheerBootstrapModules(): void
{
    foreach (beheerGemigreerdeModules() as $def) {
        $bestand = $def['bootstrap'] ?? null;
        if (is_string($bestand) && $bestand !== '' && is_file($bestand)) {
            require_once $bestand;
        }
    }
}

beheerBootstrapModules();
