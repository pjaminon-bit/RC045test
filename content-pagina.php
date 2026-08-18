<?php
// ============================================================
// Generieke contentpagina-hulpfuncties
// ============================================================
// Leest de centrale pagina-definities en de bijbehorende tenant-/vereniging-
// content. De bestaande losse pagina's kunnen deze laag stapsgewijs gaan
// gebruiken zonder dat hun URL of layout meteen hoeft te veranderen.
// ============================================================

function contentPaginaDefinities(): array
{
    static $definities = null;
    if ($definities === null) {
        $geladen = require __DIR__ . '/pagina-definities.php';
        $definities = is_array($geladen) ? $geladen : [];
    }
    return $definities;
}

function contentPaginaDefinitie(string $sleutel): array
{
    $alles = contentPaginaDefinities();
    return isset($alles[$sleutel]) && is_array($alles[$sleutel]) ? $alles[$sleutel] : [];
}

function contentPaginaBestaat(string $sleutel): bool
{
    return contentPaginaDefinitie($sleutel) !== [];
}

function contentPaginaDataPad(string $sleutel): ?string
{
    $def = contentPaginaDefinitie($sleutel);
    $relatief = trim((string) ($def['data_bestand'] ?? ''));
    if ($relatief === '') return null;
    return __DIR__ . '/' . ltrim($relatief, '/');
}

function contentPaginaLees(string $sleutel): array
{
    $pad = contentPaginaDataPad($sleutel);
    if ($pad === null || !is_file($pad)) return [];

    $json = @file_get_contents($pad);
    if ($json === false) return [];

    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function contentPaginaWaarde(array $data, string $veld, string $taal = 'nl', string $standaard = ''): string
{
    if (!array_key_exists($veld, $data)) return $standaard;
    $waarde = $data[$veld];

    if (is_array($waarde)) {
        if (isset($waarde[$taal]) && is_scalar($waarde[$taal])) return (string) $waarde[$taal];
        if (isset($waarde['nl']) && is_scalar($waarde['nl'])) return (string) $waarde['nl'];
        return $standaard;
    }

    return is_scalar($waarde) ? (string) $waarde : $standaard;
}

function contentPaginaHero(string $sleutel): array
{
    $def = contentPaginaDefinitie($sleutel);
    $hero = $def['hero'] ?? [];
    return is_array($hero) ? $hero : [];
}

function contentPaginaSeoSleutel(string $sleutel): string
{
    $def = contentPaginaDefinitie($sleutel);
    return trim((string) ($def['seo_sleutel'] ?? $sleutel));
}

function contentPaginaBeheerTab(string $sleutel): string
{
    $def = contentPaginaDefinitie($sleutel);
    return trim((string) ($def['beheer_tab'] ?? $sleutel));
}

function contentPaginaType(string $sleutel): string
{
    $def = contentPaginaDefinitie($sleutel);
    return trim((string) ($def['type'] ?? 'standaard'));
}
