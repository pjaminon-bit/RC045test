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

function siteSlogan(): string
{
    return (string) siteConfigGet('vereniging.slogan', '');
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

function siteVeiligeKleur(string $configPad, string $standaard): string
{
    $kleur = trim((string) siteConfigGet($configPad, $standaard));
    return preg_match('/^#[0-9A-Fa-f]{6}$/', $kleur) ? $kleur : $standaard;
}

function siteThemeMarkup(): string
{
    $mapping = [
        '--teal' => ['branding.kleuren.primary', '#3A7A77'],
        '--teal-dark' => ['branding.kleuren.primary_dark', '#2D6260'],
        '--teal-light' => ['branding.kleuren.primary_light', '#EAF4F3'],
        '--gold' => ['branding.kleuren.accent', '#C89A1A'],
        '--gold-light' => ['branding.kleuren.accent_light', '#FBF4DF'],
        '--dark' => ['branding.kleuren.dark', '#1E2C13'],
        '--text' => ['branding.kleuren.text', '#2A3818'],
        '--muted' => ['branding.kleuren.muted', '#6A7560'],
        '--bg' => ['branding.kleuren.background', '#FAF6EC'],
    ];

    $regels = [];
    foreach ($mapping as $cssVariabele => [$configPad, $standaard]) {
        $regels[] = '    ' . $cssVariabele . ': ' . siteVeiligeKleur($configPad, $standaard) . ';';
    }

    return "<style id=\"site-theme\">\n  :root {\n" . implode("\n", $regels) . "\n  }\n</style>";
}

function siteHeadBrandingMarkup(): string
{
    $regels = [];

    $favicon = htmlspecialchars(siteAsset('branding.favicon'), ENT_QUOTES, 'UTF-8');
    $appleTouch = htmlspecialchars(siteAsset('branding.apple_touch_icon'), ENT_QUOTES, 'UTF-8');
    $manifest = htmlspecialchars(siteAsset('branding.manifest'), ENT_QUOTES, 'UTF-8');
    $themeColor = htmlspecialchars(siteVeiligeKleur('branding.theme_color', '#1E2C13'), ENT_QUOTES, 'UTF-8');

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

function siteStartTemplateOutputFilter(): void
{
    static $actief = false;
    if ($actief) return;
    $actief = true;

    ob_start(function ($html) {
        $branding = siteHeadBrandingMarkup();

        if ($branding !== '') {
            $patroon = '~\s*<link\s+rel="icon"\s+type="image/x-icon"\s+href="favicon\.ico">\s*'
                . '(?:<link\s+rel="icon"\s+type="image/png"\s+sizes="16x16"\s+href="favicon-16x16\.png">\s*)?'
                . '(?:<link\s+rel="icon"\s+type="image/png"\s+sizes="32x32"\s+href="favicon-32x32\.png">\s*)?'
                . '(?:<link\s+rel="icon"\s+type="image/png"\s+sizes="48x48"\s+href="favicon-48x48\.png">\s*)?'
                . '<link\s+rel="apple-touch-icon"\s+sizes="180x180"\s+href="apple-touch-icon\.png">\s*'
                . '<link\s+rel="manifest"\s+href="site\.webmanifest">\s*'
                . '<meta\s+name="theme-color"\s+content="#[0-9A-Fa-f]{6}">~';

            $html = preg_replace($patroon, "\n" . $branding . "\n", $html) ?? $html;
        }

        // Voeg de configureerbare kleurvariabelen als laatste styleblok in de
        // head toe. Daardoor overschrijven ze de historische defaults in
        // styles.css zonder dat die stylesheet tijdens fase 1 herschreven hoeft
        // te worden. Alleen geldige #RRGGBB-waarden worden toegelaten.
        if (stripos($html, '</head>') !== false && strpos($html, 'id="site-theme"') === false) {
            $html = preg_replace('~</head>~i', siteThemeMarkup() . "\n</head>", $html, 1) ?? $html;
        }

        // Alleen bekende merk-markup vervangen. Inhoudelijke RC045-teksten
        // blijven onaangeroerd; dit centraliseert identiteit zonder content
        // per ongeluk generiek te maken.
        $logo = htmlspecialchars(siteAsset('branding.logo'), ENT_QUOTES, 'UTF-8');
        $naam = htmlspecialchars(siteNaam(), ENT_QUOTES, 'UTF-8');
        $slogan = htmlspecialchars(siteSlogan(), ENT_QUOTES, 'UTF-8');

        if ($logo !== '') {
            $html = str_replace('src="rc045-logo.png"', 'src="' . $logo . '"', $html);
        }
        $html = str_replace('alt="RC045 logo"', 'alt="' . $naam . ' logo"', $html);
        $html = str_replace('alt="RC045"', 'alt="' . $naam . '"', $html);
        $html = preg_replace('~(<span\s+class="nav-logo-text">)RC045(</span>)~', '$1' . $naam . '$2', $html) ?? $html;
        $html = preg_replace('~(<span\s+class="nav-logo-sub">)Bashers of the South(</span>)~', '$1' . $slogan . '$2', $html) ?? $html;

        $footerMerk = $naam . ($slogan !== '' ? ' · ' . $slogan : '');
        $html = str_replace('RC045 · Bashers of the South', $footerMerk, $html);

        return $html;
    });
}

siteStartTemplateOutputFilter();
