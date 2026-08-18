<?php
// ============================================================
// Generieke toegang tot de verenigingsconfiguratie
// ============================================================
// Applicatiecode gebruikt deze helpers in plaats van rechtstreeks RC045-
// specifieke waarden te bevatten. In een latere multi-tenantfase kan de
// bron van de configuratie veranderen zonder alle pagina's opnieuw aan te
// passen.
// ============================================================

function siteConfig(): array
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/site-config.php';
    }

    return $config;
}

function siteConfigGet(string $pad, $standaard = null)
{
    $waarde = siteConfig();

    foreach (explode('.', $pad) as $sleutel) {
        if (!is_array($waarde) || !array_key_exists($sleutel, $waarde)) {
            return $standaard;
        }
        $waarde = $waarde[$sleutel];
    }

    return $waarde;
}

function siteNaam(): string
{
    return (string) siteConfigGet('vereniging.naam', 'Vereniging');
}

function siteVolledigeNaam(): string
{
    return (string) siteConfigGet('vereniging.volledige_naam', siteNaam());
}

function siteUrl(): string
{
    return rtrim((string) siteConfigGet('vereniging.site_url', ''), '/');
}

function siteStandaardTaal(): string
{
    return (string) siteConfigGet('vereniging.standaard_taal', 'nl');
}

function siteTalen(): array
{
    $talen = siteConfigGet('vereniging.talen', ['nl' => 'nl_NL']);
    return is_array($talen) && $talen ? $talen : ['nl' => 'nl_NL'];
}

function siteAssetUrl(string $configPad): string
{
    $bestand = ltrim((string) siteConfigGet($configPad, ''), '/');
    return siteUrl() . '/' . $bestand;
}

function siteModuleActief(string $module): bool
{
    return siteConfigGet('modules.' . $module, false) === true;
}
