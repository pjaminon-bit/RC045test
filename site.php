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

function siteAsset(string $configPad): string
{
    return ltrim((string) siteConfigGet($configPad, ''), '/');
}

function siteAssetUrl(string $configPad): string
{
    return siteUrl() . '/' . siteAsset($configPad);
}

function siteModuleActief(string $module): bool
{
    return siteConfigGet('modules.' . $module, false) === true;
}

function siteHeadBranding(): void
{
    $favicon = htmlspecialchars(siteAsset('branding.favicon'), ENT_QUOTES, 'UTF-8');
    $appleTouch = htmlspecialchars(siteAsset('branding.apple_touch_icon'), ENT_QUOTES, 'UTF-8');
    $manifest = htmlspecialchars(siteAsset('branding.manifest'), ENT_QUOTES, 'UTF-8');
    $themeColor = htmlspecialchars((string) siteConfigGet('branding.theme_color', '#1E2C13'), ENT_QUOTES, 'UTF-8');

    if ($favicon !== '') {
        echo "  <link rel=\"icon\" type=\"image/x-icon\" href=\"$favicon\">\n";
    }

    // De huidige RC045-installatie heeft daarnaast losse PNG-favicons. Deze
    // blijven tijdens fase 1 als compatibele defaults bestaan zolang ze in de
    // config zijn opgenomen; andere verenigingen hoeven ze niet te gebruiken.
    foreach ([16, 32, 48] as $maat) {
        $pad = siteAsset('branding.favicon_' . $maat);
        if ($pad === '') continue;
        $veiligPad = htmlspecialchars($pad, ENT_QUOTES, 'UTF-8');
        echo "  <link rel=\"icon\" type=\"image/png\" sizes=\"{$maat}x{$maat}\" href=\"$veiligPad\">\n";
    }

    if ($appleTouch !== '') {
        echo "  <link rel=\"apple-touch-icon\" sizes=\"180x180\" href=\"$appleTouch\">\n";
    }
    if ($manifest !== '') {
        echo "  <link rel=\"manifest\" href=\"$manifest\">\n";
    }
    if ($themeColor !== '') {
        echo "  <meta name=\"theme-color\" content=\"$themeColor\">\n";
    }
}
