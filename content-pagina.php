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

function contentPaginaHeroCss(string $sleutel): string
{
    $hero = contentPaginaHero($sleutel);
    $achtergrond = trim((string) ($hero['achtergrond'] ?? ''));
    if ($achtergrond === '') return '';

    $positie = trim((string) ($hero['positie'] ?? 'center')) ?: 'center';
    $opacity = $hero['opacity'] ?? 0.35;
    $opacity = is_numeric($opacity) ? max(0, min(1, (float) $opacity)) : 0.35;

    // Alleen lokale, relatieve assets toelaten in deze eerste templatefase.
    if (preg_match('~^(?:https?:)?//~i', $achtergrond) || str_contains($achtergrond, '..')) return '';

    $url = htmlspecialchars($achtergrond, ENT_QUOTES, 'UTF-8');
    $pos = htmlspecialchars($positie, ENT_QUOTES, 'UTF-8');
    return ".page-hero-bg{background-image:url('{$url}')!important;background-position:{$pos}!important;opacity:{$opacity}!important;}";
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

function contentPaginaSleutelVoorRequest(?string $scriptNaam = null): ?string
{
    $scriptNaam = $scriptNaam ?? (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $bestand = pathinfo(basename($scriptNaam), PATHINFO_FILENAME);
    if ($bestand === '') return null;

    foreach (contentPaginaDefinities() as $sleutel => $def) {
        $slug = trim((string) ($def['slug'] ?? $sleutel));
        if ($bestand === $slug || $bestand === $sleutel) return (string) $sleutel;
    }

    return null;
}

function contentPaginaBootstrap(?string $sleutel = null): array
{
    $sleutel = $sleutel ?? contentPaginaSleutelVoorRequest();
    if ($sleutel === null || !contentPaginaBestaat($sleutel)) return [];

    return [
        'sleutel' => $sleutel,
        'definitie' => contentPaginaDefinitie($sleutel),
        'data' => contentPaginaLees($sleutel),
        'hero' => contentPaginaHero($sleutel),
        'seo_sleutel' => contentPaginaSeoSleutel($sleutel),
        'beheer_tab' => contentPaginaBeheerTab($sleutel),
        'type' => contentPaginaType($sleutel),
    ];
}
