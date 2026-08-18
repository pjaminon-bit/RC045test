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
        if (!is_array($waarde) || !array_key_exists($sleutel, $waarde)) return $standaard;
        $waarde = $waarde[$sleutel];
    }
    return $waarde;
}

function siteNaam(): string { return (string) siteConfigGet('vereniging.naam', 'Vereniging'); }
function siteVolledigeNaam(): string { return (string) siteConfigGet('vereniging.volledige_naam', siteNaam()); }
function siteSlogan(): string { return (string) siteConfigGet('vereniging.slogan', ''); }
function siteUrl(): string { return rtrim((string) siteConfigGet('vereniging.site_url', ''), '/'); }
function siteStandaardTaal(): string { return (string) siteConfigGet('vereniging.standaard_taal', 'nl'); }
function siteTalen(): array {
    $talen = siteConfigGet('vereniging.talen', ['nl' => 'nl_NL']);
    return is_array($talen) && $talen ? $talen : ['nl' => 'nl_NL'];
}
function siteAsset(string $configPad): string { return ltrim((string) siteConfigGet($configPad, ''), '/'); }
function siteAssetUrl(string $configPad): string { return siteUrl() . '/' . siteAsset($configPad); }
function siteModuleActief(string $module): bool { return siteConfigGet('modules.' . $module, false) === true; }

function siteModuleDefinities(): array
{
    static $definities = null;
    if ($definities === null) {
        $geladen = require __DIR__ . '/module-definities.php';
        $definities = is_array($geladen) ? $geladen : [];
    }
    return $definities;
}

function siteModuleDefinitie(string $module): array
{
    $definities = siteModuleDefinities();
    return isset($definities[$module]) && is_array($definities[$module]) ? $definities[$module] : [];
}

function siteModuleVoorWaarde(string $veld, string $waarde): ?string
{
    foreach (siteModuleDefinities() as $module => $definitie) {
        $waarden = $definitie[$veld] ?? [];
        if (is_array($waarden) && in_array($waarde, $waarden, true)) return $module;
    }
    return null;
}

function siteModuleVoorPagina(string $pagina): ?string
{
    return siteModuleVoorWaarde('publieke_paginas', $pagina);
}

function siteModuleVoorBeheerTab(string $tab): ?string
{
    return siteModuleVoorWaarde('beheer_tabs', $tab);
}

function siteModuleVoorBeheerFormulier(string $formulier): ?string
{
    return siteModuleVoorWaarde('beheer_formulieren', $formulier);
}

function siteModuleLabel(string $module): string
{
    $definitie = siteModuleDefinitie($module);
    return trim((string) ($definitie['label'] ?? '')) ?: 'Deze pagina';
}

function siteHuidigScript(): string
{
    return strtolower(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
}

function siteIsBeheerPagina(): bool
{
    return siteHuidigScript() === 'beheer.php';
}

function siteModulePaginaToegestaan(string $pagina): bool
{
    $module = siteModuleVoorPagina($pagina);
    return $module === null || siteModuleActief($module);
}

function siteHuidigePubliekePagina(): ?string
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if (!is_string($requestUri) || $requestUri === '') return null;

    $pad = parse_url($requestUri, PHP_URL_PATH);
    if (!is_string($pad) || $pad === '') return null;

    $bestand = strtolower(basename($pad));
    $pagina = preg_replace('/\.(?:html|php)$/', '', $bestand);
    return is_string($pagina) && $pagina !== '' ? $pagina : null;
}

function siteRenderModuleNietBeschikbaar(string $module): void
{
    if (!headers_sent()) {
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Robots-Tag: noindex, nofollow', true);
    }

    $naam = htmlspecialchars(siteNaam(), ENT_QUOTES, 'UTF-8');
    $logo = htmlspecialchars(siteAsset('branding.logo'), ENT_QUOTES, 'UTF-8');
    $primary = siteVeiligeKleur('branding.kleuren.primary', '#3A7A77');
    $dark = siteVeiligeKleur('branding.kleuren.dark', '#1E2C13');
    $bg = siteVeiligeKleur('branding.kleuren.background', '#FAF6EC');
    $text = siteVeiligeKleur('branding.kleuren.text', '#2A3818');
    $moduleNaam = htmlspecialchars(siteModuleLabel($module), ENT_QUOTES, 'UTF-8');

    echo '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<meta name="robots" content="noindex,nofollow">';
    echo '<title>Pagina niet beschikbaar – ' . $naam . '</title>';
    echo '<style>body{margin:0;font-family:Arial,sans-serif;background:' . $bg . ';color:' . $text . ';display:grid;min-height:100vh;place-items:center;padding:24px;box-sizing:border-box}.card{max-width:620px;background:#fff;border:1px solid rgba(0,0,0,.1);border-radius:16px;padding:36px;text-align:center;box-shadow:0 12px 40px rgba(0,0,0,.08)}.logo{max-height:84px;max-width:180px;margin:0 auto 20px}h1{margin:0 0 12px;color:' . $dark . ';font-size:30px}p{line-height:1.65;margin:0 0 24px}.btn{display:inline-block;background:' . $primary . ';color:#fff;text-decoration:none;padding:12px 20px;border-radius:9px;font-weight:700}</style>';
    echo '</head><body><main class="card">';
    if ($logo !== '') echo '<img class="logo" src="' . $logo . '" alt="' . $naam . '">';
    echo '<h1>Pagina niet beschikbaar</h1>';
    echo '<p>' . $moduleNaam . ' is voor deze vereniging niet ingeschakeld.</p>';
    echo '<a class="btn" href="index.html">Terug naar de homepage</a>';
    echo '</main></body></html>';
    exit;
}

function siteBewaakPubliekeModule(): void
{
    if (siteIsBeheerPagina()) return;

    $pagina = siteHuidigePubliekePagina();
    if ($pagina === null) return;

    $module = siteModuleVoorPagina($pagina);
    if ($module !== null && !siteModuleActief($module)) {
        siteRenderModuleNietBeschikbaar($module);
    }
}

function siteBewaakBeheerModulePost(): void
{
    if (!siteIsBeheerPagina() || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;

    $formulier = isset($_POST['formulier']) && is_string($_POST['formulier']) ? $_POST['formulier'] : '';
    if ($formulier === '') return;

    $module = siteModuleVoorBeheerFormulier($formulier);
    if ($module === null || siteModuleActief($module)) return;

    // Een uitgeschakelde module is ook voor een master-account niet
    // wijzigbaar. Maak het formulier onbekend vóór beheer.php zijn bestaande
    // autorisatie- en opslaglogica uitvoert.
    $_POST['formulier'] = '';
    $GLOBALS['siteModulePostGeblokkeerd'] = [
        'module' => $module,
        'formulier' => $formulier,
    ];

    // auth.php is vóór paneel-hulp.php/site.php geladen. Als logging dus al
    // beschikbaar is, registreer alleen module + formuliernaam; nooit POST-
    // inhoud of persoonsgegevens.
    if (function_exists('schrijfLog') && isset($GLOBALS['logBestand'], $GLOBALS['huidigeGebruiker'])) {
        schrijfLog(
            $GLOBALS['logBestand'],
            (string) $GLOBALS['huidigeGebruiker'],
            'module_geblokkeerd',
            $module . ':' . $formulier
        );
    }
}

function siteModuleVisibilityMarkup(): string
{
    $selectors = [];

    foreach (siteModuleDefinities() as $module => $definitie) {
        if (siteModuleActief($module)) continue;
        foreach (($definitie['publieke_selectors'] ?? []) as $selector) {
            if (is_string($selector) && $selector !== '') $selectors[] = $selector;
        }
    }

    if (!$selectors) return '';
    return '<style id="site-module-visibility">' . implode(',', array_unique($selectors)) . '{display:none!important}</style>';
}

function siteBeheerModuleVisibilityMarkup(): string
{
    $selectors = [];

    foreach (siteModuleDefinities() as $module => $definitie) {
        if (siteModuleActief($module)) continue;
        foreach (($definitie['beheer_tabs'] ?? []) as $tab) {
            if (!is_string($tab) || $tab === '') continue;
            $selectors[] = '#tab-' . $tab;
            $selectors[] = '[href="#tab-' . $tab . '"]';
            $selectors[] = '[data-tab="' . $tab . '"]';
            $selectors[] = '[data-tab-target="' . $tab . '"]';
        }
    }

    if (!$selectors) return '';
    return '<style id="site-beheer-module-visibility">' . implode(',', array_unique($selectors)) . '{display:none!important}</style>';
}

function siteVerbergUitgeschakeldeModules(string $html): string
{
    foreach (siteModuleDefinities() as $module => $definitie) {
        if (siteModuleActief($module)) continue;

        foreach (($definitie['publieke_links'] ?? []) as $link) {
            if (!is_string($link) || $link === '') continue;
            $veiligLink = preg_quote($link, '~');
            $html = preg_replace('~<li[^>]*>\s*<a[^>]+href="' . $veiligLink . '"[^>]*>.*?</a>\s*</li>~is', '', $html) ?? $html;
        }

        if (!empty($definitie['verberg_nav_lid'])) {
            $html = preg_replace('~<li[^>]*class="[^"]*nav-lid[^"]*"[^>]*>.*?</li>~is', '', $html) ?? $html;
        }
    }

    $moduleVisibility = siteModuleVisibilityMarkup();
    if ($moduleVisibility !== '' && stripos($html, '</head>') !== false && strpos($html, 'id="site-module-visibility"') === false) {
        $html = preg_replace('~</head>~i', $moduleVisibility . "\n</head>", $html, 1) ?? $html;
    }

    return $html;
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
    if ($favicon !== '') $regels[] = '<link rel="icon" type="image/x-icon" href="' . $favicon . '">';
    foreach ([16, 32, 48] as $maat) {
        $pad = siteAsset('branding.favicon_' . $maat);
        if ($pad === '') continue;
        $regels[] = '<link rel="icon" type="image/png" sizes="' . $maat . 'x' . $maat . '" href="' . htmlspecialchars($pad, ENT_QUOTES, 'UTF-8') . '">';
    }
    if ($appleTouch !== '') $regels[] = '<link rel="apple-touch-icon" sizes="180x180" href="' . $appleTouch . '">';
    if ($manifest !== '') $regels[] = '<link rel="manifest" href="' . $manifest . '">';
    if ($themeColor !== '') $regels[] = '<meta name="theme-color" content="' . $themeColor . '">';
    return implode("\n", $regels);
}

function siteHeadBranding(): void
{
    $markup = siteHeadBrandingMarkup();
    if ($markup !== '') echo $markup . "\n";
}

function siteStartTemplateOutputFilter(): void
{
    static $actief = false;
    if ($actief) return;
    $actief = true;

    ob_start(function ($html) {
        // Afgeschermd beheer krijgt alleen modulezichtbaarheid. Publieke
        // branding/thema-overrides horen niet over de beheerinterface heen.
        if (siteIsBeheerPagina()) {
            $beheerVisibility = siteBeheerModuleVisibilityMarkup();
            if ($beheerVisibility !== '' && stripos($html, '</head>') !== false && strpos($html, 'id="site-beheer-module-visibility"') === false) {
                $html = preg_replace('~</head>~i', $beheerVisibility . "\n</head>", $html, 1) ?? $html;
            }
            return $html;
        }

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

        if (stripos($html, '</head>') !== false && strpos($html, 'id="site-theme"') === false) {
            $html = preg_replace('~</head>~i', siteThemeMarkup() . "\n</head>", $html, 1) ?? $html;
        }

        $logo = htmlspecialchars(siteAsset('branding.logo'), ENT_QUOTES, 'UTF-8');
        $naam = htmlspecialchars(siteNaam(), ENT_QUOTES, 'UTF-8');
        $slogan = htmlspecialchars(siteSlogan(), ENT_QUOTES, 'UTF-8');
        if ($logo !== '') $html = str_replace('src="rc045-logo.png"', 'src="' . $logo . '"', $html);
        $html = str_replace('alt="RC045 logo"', 'alt="' . $naam . ' logo"', $html);
        $html = str_replace('alt="RC045"', 'alt="' . $naam . '"', $html);
        $html = preg_replace('~(<span\s+class="nav-logo-text">)RC045(</span>)~', '$1' . $naam . '$2', $html) ?? $html;
        $html = preg_replace('~(<span\s+class="nav-logo-sub">)Bashers of the South(</span>)~', '$1' . $slogan . '$2', $html) ?? $html;
        $html = str_replace('RC045 · Bashers of the South', $naam . ($slogan !== '' ? ' · ' . $slogan : ''), $html);

        return siteVerbergUitgeschakeldeModules($html);
    });
}

siteBewaakBeheerModulePost();
siteBewaakPubliekeModule();
siteStartTemplateOutputFilter();
