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

function siteHeadBrandingMarkup(): string
{
    $regels = [];

    $favicon = htmlspecialchars(siteAsset('branding.favicon'), ENT_QUOTES, 'UTF-8');
    $appleTouch = htmlspecialchars(siteAsset('branding.apple_touch_icon'), ENT_QUOTES, 'UTF-8');
    $manifest = htmlspecialchars(siteAsset('branding.manifest'), ENT_QUOTES, 'UTF-8');
    $themeColor = htmlspecialchars((string) siteConfigGet('branding.theme_color', '#1E2C13'), ENT_QUOTES, 'UTF-8');

    if ($favicon !== '') {
        $regels[] = '<link rel="icon" type="image/x-icon" href="' . $favicon . '">';
    }

    foreach ([16, 32, 48] as $maat) {
        $pad = siteAsset('branding.favicon_' . $maat);
        if ($pad === '') continue;
        $veiligPad = htmlspecialchars($pad, ENT_QUOTES, 'UTF-8');
        $regels[] = '<link rel="icon" type="image/png" sizes="' . $maat . 'x' . $maat . '" href="' . $veiligPad . '">';
    }

    if ($appleTouch !== '') {
        $regels[] = '<link rel="apple-touch-icon" sizes="180x180" href="' . $appleTouch . '">';
    }
    if ($manifest !== '') {
        $regels[] = '<link rel="manifest" href="' . $manifest . '">';
    }
    if ($themeColor !== '') {
        $regels[] = '<meta name="theme-color" content="' . $themeColor . '">';
    }

    return implode("\n", $regels);
}

function siteHeadBranding(): void
{
    $markup = siteHeadBrandingMarkup();
    if ($markup !== '') {
        echo $markup . "\n";
    }
}

// Tijdelijke fase-1 compatibiliteitslaag. De publieke pagina's bevatten nog
// een identiek, historisch hardcoded favicon/manifest/theme-color-blok. In
// plaats van zes grote pagina's tegelijk te herschrijven vervangen we dat
// blok server-side in de uiteindelijke HTML. Daardoor werkt de centrale
// brandingconfiguratie direct op alle pagina's. Zodra de pagina's later stuk
// voor stuk zijn opgeschoond kan deze filter zonder gedragswijziging weg.
function siteStartTemplateOutputFilter(): void
{
    static $actief = false;
    if ($actief) return;
    $actief = true;

    ob_start(function ($html) {
        $branding = siteHeadBrandingMarkup();
        if ($branding === '') return $html;

        $patroon = '~\s*<link\s+rel="icon"\s+type="image/x-icon"\s+href="favicon\.ico">\s*'
            . '(?:<link\s+rel="icon"\s+type="image/png"\s+sizes="16x16"\s+href="favicon-16x16\.png">\s*)?'
            . '(?:<link\s+rel="icon"\s+type="image/png"\s+sizes="32x32"\s+href="favicon-32x32\.png">\s*)?'
            . '(?:<link\s+rel="icon"\s+type="image/png"\s+sizes="48x48"\s+href="favicon-48x48\.png">\s*)?'
            . '<link\s+rel="apple-touch-icon"\s+sizes="180x180"\s+href="apple-touch-icon\.png">\s*'
            . '<link\s+rel="manifest"\s+href="site\.webmanifest">\s*'
            . '<meta\s+name="theme-color"\s+content="#[0-9A-Fa-f]{6}">~';

        return preg_replace($patroon, "\n" . $branding . "\n", $html) ?? $html;
    });
}

// site.php wordt door seo-head.php vóór enige HTML geladen. Daardoor kan de
// filter veilig één keer per request worden gestart zonder wijzigingen in
// iedere afzonderlijke publieke pagina.
siteStartTemplateOutputFilter();
