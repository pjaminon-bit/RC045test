<?php
// ============================================================
// Tenant runtime helpers
// ============================================================
// Deze laag kent geen domeinlogica. Hij bepaalt alleen waar een tenant zijn
// server-only configuratie en private opslag mag vinden. Daardoor kan één
// gedeelde codebase meerdere verenigingen bedienen zonder dat configuratie of
// JSON-data naast de applicatiecode hoeft te staan.
// ============================================================

function tenantRuntimeProjectRoot(): string
{
    return dirname(__DIR__, 2);
}

function tenantRuntimeVeiligeSleutel(string $waarde): string
{
    $waarde = strtolower(trim($waarde));
    $waarde = (string) preg_replace('/[^a-z0-9_-]+/', '-', $waarde);
    return trim($waarde, '-') ?: 'default';
}

function tenantRuntimeIsAbsoluutPad(string $pad): bool
{
    if ($pad === '') return false;
    if ($pad[0] === '/' || $pad[0] === '\\') return true;
    return preg_match('/^[A-Za-z]:[\\\\\/]/', $pad) === 1;
}

/**
 * Productie-/multi-tenantmodus: wanneer deze vlag aan staat MOET iedere
 * request een expliciet extern tenantconfigbestand hebben. Een onbekende of
 * verkeerd gespelde vlag faalt eveneens hard; een configuratiefout mag nooit
 * stil leiden tot de RC045/defaultconfiguratie.
 */
function tenantRuntimeConfigVerplicht(): bool
{
    $ruw = getenv('VERENIGING_REQUIRE_TENANT_CONFIG');
    if ($ruw === false) return false;

    $waarde = strtolower(trim((string) $ruw));
    if ($waarde === '' || in_array($waarde, ['0', 'false', 'no', 'off'], true)) return false;
    if (in_array($waarde, ['1', 'true', 'yes', 'on'], true)) return true;

    throw new RuntimeException('VERENIGING_REQUIRE_TENANT_CONFIG bevat een ongeldige booleaanse waarde.');
}

/**
 * Geeft het externe configbestand terug wanneer VERENIGING_CONFIG_FILE is
 * gezet. Een expliciet maar ongeldig pad faalt bewust hard: terugvallen op een
 * andere vereniging/configuratie zou in een multi-tenant omgeving onveilig zijn.
 * In verplichte tenantmodus faalt ook een ontbrekende variabele direct hard.
 */
function tenantRuntimeExternConfigPad(): ?string
{
    $pad = trim((string) (getenv('VERENIGING_CONFIG_FILE') ?: ''));
    if ($pad === '') {
        if (tenantRuntimeConfigVerplicht()) {
            throw new RuntimeException('Tenantconfiguratie is verplicht maar VERENIGING_CONFIG_FILE ontbreekt.');
        }
        return null;
    }
    if (!tenantRuntimeIsAbsoluutPad($pad)) {
        throw new RuntimeException('VERENIGING_CONFIG_FILE moet een absoluut pad zijn.');
    }
    if (!is_file($pad) || !is_readable($pad)) {
        throw new RuntimeException('Extern verenigingsconfigbestand is niet leesbaar.');
    }
    return $pad;
}

/**
 * Exacte private root van deze tenant. Deze map hoort buiten de publieke
 * documentroot te staan. De waarde kan uit config of environment komen.
 */
function tenantRuntimePrivateRoot(array $config): ?string
{
    $configPad = trim((string) ($config['opslag']['private_root'] ?? ''));
    $envPad = trim((string) (getenv('VERENIGING_PRIVATE_ROOT') ?: ''));
    $pad = $configPad !== '' ? $configPad : $envPad;
    if ($pad === '') return null;
    if (!tenantRuntimeIsAbsoluutPad($pad)) {
        throw new RuntimeException('Private tenantopslag moet een absoluut pad gebruiken.');
    }
    return rtrim($pad, '/\\');
}

function tenantRuntimeCollectieSleutel(string $collectie): string
{
    $collectie = strtolower(trim($collectie));
    $collectie = (string) preg_replace('/[^a-z0-9_-]+/', '-', $collectie);
    return trim($collectie, '-');
}

function tenantRuntimeCollectiePad(string $privateRoot, string $collectie): string
{
    $sleutel = tenantRuntimeCollectieSleutel($collectie);
    if ($sleutel === '') throw new InvalidArgumentException('Ongeldige collectie voor tenantopslag.');
    return $privateRoot . DIRECTORY_SEPARATOR . 'collections' . DIRECTORY_SEPARATOR . $sleutel . '.json';
}
