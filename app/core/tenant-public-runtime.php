<?php
// ============================================================
// Productieruntime voor externe tenantbranding
// ============================================================
// Deze laag wordt alleen voor externe tenants geregistreerd en werkt als
// laatste uitgaande HTML-filter. Doel: geen voorbeeldvereniging-identiteit,
// voorbeeldmedia, analytics of vaste huisstijl mag naar een tenant lekken.
// ============================================================

function tenantPublicRuntimeKleur(array $config, string $sleutel, string $fallback): string
{
    $waarde = strtoupper(trim((string)($config['branding']['kleuren'][$sleutel] ?? '')));
    return preg_match('/^#[0-9A-F]{6}$/D', $waarde) === 1 ? $waarde : $fallback;
}

function tenantPublicRuntimeAssetUrl(array $config, string $sleutel, string $fallback = ''): string
{
    $asset = trim((string)($config['branding'][$sleutel] ?? ''));
    if ($asset === '') $asset = $fallback;
    if ($asset === '') return '';
    if (filter_var($asset, FILTER_VALIDATE_URL) !== false) return $asset;
    $site = rtrim((string)($config['vereniging']['site_url'] ?? ''), '/');
    $asset = ltrim($asset, '/');
    return $site !== '' ? $site . '/' . $asset : '/' . $asset;
}

function tenantPublicRuntimeContact(array $config): array
{
    $privateRoot = trim((string)($config['opslag']['private_root'] ?? ''));
    if ($privateRoot === '') return [];
    $pad = rtrim($privateRoot, '/\\') . DIRECTORY_SEPARATOR . 'public-content' . DIRECTORY_SEPARATOR . 'contact.json';
    if (!is_file($pad) || !is_readable($pad) || is_link($pad)) return [];
    $raw = @file_get_contents($pad);
    if ($raw === false) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function tenantPublicRuntimeThemeMarkup(array $config): string
{
    $map = [
        '--teal' => ['primary', '#3A7A77'],
        '--teal-dark' => ['primary_dark', '#2D6260'],
        '--teal-light' => ['primary_light', '#EAF4F3'],
        '--gold' => ['accent', '#C89A1A'],
        '--gold-light' => ['accent_light', '#FBF4DF'],
        '--dark' => ['dark', '#1E2C13'],
        '--text' => ['text', '#2A3818'],
        '--muted' => ['muted', '#6A7560'],
        '--bg' => ['background', '#FAF6EC'],
        '--nav-bg' => ['nav_background', '#FFFFFF'],
        '--nav-bg-open' => ['nav_background', '#FFFFFF'],
    ];
    $regels = [];
    foreach ($map as $css => [$sleutel, $fallback]) {
        $regels[] = $css . ':' . tenantPublicRuntimeKleur($config, $sleutel, $fallback) . '!important';
    }
    $navText = tenantPublicRuntimeKleur($config, 'nav_text', '#2A3818');
    return '<style id="tenant-product-theme">:root{' . implode(';', $regels) . '}'
        . '.nav{background:var(--nav-bg)!important}.nav-links a,.nav-logo-text,.nav-logo-sub{color:' . $navText . '!important}'
        . '.nav-links .nav-lid a{color:#fff!important}'
        . '</style>';
}

function tenantPublicRuntimeHeadMarkup(array $config): string
{
    $regels = [tenantPublicRuntimeThemeMarkup($config)];
    $favicon = tenantPublicRuntimeAssetUrl($config, 'favicon');
    $logo = tenantPublicRuntimeAssetUrl($config, 'logo', 'images/template-placeholder.svg');
    $theme = tenantPublicRuntimeKleur($config, 'dark', '#1E2C13');
    if ($favicon !== '') $regels[] = '<link rel="icon" href="' . htmlspecialchars($favicon, ENT_QUOTES, 'UTF-8') . '">';
    elseif ($logo !== '') $regels[] = '<link rel="icon" href="' . htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') . '">';
    $regels[] = '<meta name="theme-color" content="' . $theme . '">';

    $naam = trim((string)($config['vereniging']['naam'] ?? 'Vereniging')) ?: 'Vereniging';
    $context = [
        'external' => true,
        'tenantKey' => (string)($config['vereniging']['sleutel'] ?? 'tenant'),
        'name' => $naam,
        'siteUrl' => (string)($config['vereniging']['site_url'] ?? ''),
    ];
    $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
    $regels[] = '<script id="vereniging-site-context">window.verenigingSiteContext=' . $json . ';</script>';
    return implode("\n", $regels);
}

function tenantPublicRuntimePlaceholderLokaleMedia(string $html, string $logoUrl): string
{
    $placeholder = 'images/template-placeholder.svg';
    $html = preg_replace_callback(
        '~(<img\b[^>]*\bsrc=["\'])([^"\']+)(["\'])~i',
        static function (array $m) use ($placeholder, $logoUrl): string {
            $src = $m[2];
            $lower = strtolower($src);
            if (str_contains($lower, 'rc045-logo')) return $m[1] . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . $m[3];
            if (preg_match('~^(?:https?:|data:|blob:|/public-asset\.php|public-asset\.php)~i', $src) === 1) return $m[0];
            if (str_starts_with($lower, 'images/') && !str_contains($lower, 'template-placeholder')) return $m[1] . $placeholder . $m[3];
            return $m[0];
        },
        $html
    ) ?? $html;

    $html = preg_replace_callback(
        '~(\bdata-bg=["\'])([^"\']+)(["\'])~i',
        static function (array $m) use ($placeholder): string {
            $src = $m[2];
            if (preg_match('~^(?:https?:|data:|blob:|/public-asset\.php|public-asset\.php)~i', $src) === 1) return $m[0];
            return $m[1] . $placeholder . $m[3];
        },
        $html
    ) ?? $html;

    $html = preg_replace("~url\((['\"]?)images/(?!template-placeholder)[^)]+\)~i", "url('images/template-placeholder.svg')", $html) ?? $html;
    return $html;
}

function tenantPublicRuntimeTransform(string $html, array $config): string
{
    if ($html === '' || (stripos($html, '<html') === false && stripos($html, '<!doctype') === false)) return $html;

    $naam = trim((string)($config['vereniging']['naam'] ?? 'Vereniging')) ?: 'Vereniging';
    $volledig = trim((string)($config['vereniging']['volledige_naam'] ?? $naam)) ?: $naam;
    $slogan = trim((string)($config['vereniging']['slogan'] ?? ''));
    $siteUrl = rtrim((string)($config['vereniging']['site_url'] ?? ''), '/');
    $betaling = is_array($config['betaling'] ?? null) ? $config['betaling'] : [];
    $iban = trim((string)($betaling['iban'] ?? '')) ?: 'Nog niet ingesteld';
    $tenaamstelling = trim((string)($betaling['tenaamstelling'] ?? '')) ?: $volledig;
    $omschrijving = trim((string)($betaling['omschrijving'] ?? '')) ?: 'Contributie {jaar} - {naam}';
    $omschrijving = str_replace('{naam}', $naam, $omschrijving);
    $contact = tenantPublicRuntimeContact($config);
    $email = filter_var((string)($contact['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? (string)$contact['email'] : '';
    $facebook = trim((string)($contact['facebook'] ?? ''));
    $straat = trim((string)($contact['adres_straat'] ?? ''));
    $plaats = trim((string)($contact['adres_postcode_plaats'] ?? ''));
    $logo = tenantPublicRuntimeAssetUrl($config, 'logo', 'images/template-placeholder.svg');

    // Oude favicon/manifest/analytics-tags verdwijnen voor externe tenants.
    $html = preg_replace('~<link\b[^>]+href=["\'][^"\']*(?:favicon|apple-touch-icon|site\.webmanifest)[^"\']*["\'][^>]*>~i', '', $html) ?? $html;
    $html = preg_replace('~<script\b[^>]*(?:data-goatcounter|src=["\'][^"\']*(?:goatcounter|gc\.zgo\.at)[^"\']*["\'])[^>]*>.*?</script>~is', '', $html) ?? $html;

    if (stripos($html, '</head>') !== false) {
        $head = tenantPublicRuntimeHeadMarkup($config);
        if (strpos($html, 'id="tenant-product-theme"') === false) {
            $html = preg_replace('~</head>~i', $head . "\n</head>", $html, 1) ?? $html;
        }
    }

    // Het publieke aanmeldformulier gebruikt voor tenants uitsluitend de eigen
    // privacyvriendelijke inbox. De historische externe mailroute is verboden.
    $html = preg_replace(
        '~(<form\b[^>]*\bid=["\']aanmeld-form["\'][^>]*\baction=["\'])[^"\']*(["\'])~i',
        '$1aanmelden-ontvangst.php$2',
        $html,
        1
    ) ?? $html;
    $html = str_replace("fetch('aanmelden-ontvangst.php', {", "if (!(window.verenigingSiteContext && window.verenigingSiteContext.external)) fetch('aanmelden-ontvangst.php', {", $html);

    $vervangingen = [
        'RC045 – Bashers of the South' => $volledig,
        'RC045 · Bashers of the South' => $volledig,
        'Bashers of the South' => $slogan !== '' ? $slogan : $naam,
        'NL51 RABO 0367 6153 63' => $iban,
        'T.n.v. RC045' => 'T.n.v. ' . $tenaamstelling,
        'In the name of RC045' => 'In the name of ' . $tenaamstelling,
        'Auf den Namen RC045' => 'Auf den Namen ' . $tenaamstelling,
        'contributie RC045 {jaar}' => $omschrijving,
        'RC045 {jaar}' => $naam . ' {jaar}',
        'bestuur@rc045.nl' => $email !== '' ? $email : 'contactgegevens volgen',
        'https://www.facebook.com/rc045/' => $facebook !== '' ? $facebook : '#contact',
        'facebook.com/rc045' => $facebook !== '' ? preg_replace('~^https?://~i', '', $facebook) : 'sociale media volgen',
        'Wijngaardsberg 26' => $straat !== '' ? $straat : 'Adres nog niet ingesteld',
        '6464 EZ Eygelshoven' => $plaats !== '' ? $plaats : 'Plaats nog niet ingesteld',
        'Kerkrade (Eygelshoven)' => $plaats !== '' ? $plaats : 'Plaats nog niet ingesteld',
        'Eygelshoven' => $plaats !== '' ? $plaats : 'de verenigingslocatie',
        'Kok Lexmond' => 'de locatiebeheerder',
        'https://rc045.nl' => $siteUrl !== '' ? $siteUrl : '/',
        'pjaminon@me.com' => '',
        'Pascal Jaminon' => 'Websitebeheer',
        'RC045' => $naam,
    ];
    $html = str_ireplace(array_keys($vervangingen), array_values($vervangingen), $html);
    $html = tenantPublicRuntimePlaceholderLokaleMedia($html, $logo);

    // Logo-alt en historische mailto-credit neutraliseren zonder een hardcoded
    // productmerk te introduceren.
    $html = preg_replace('~mailto:\?subject=Website%20[^"\']+~i', '#', $html) ?? $html;
    $html = preg_replace('~mailto:[^"\']*pjaminon[^"\']*~i', '#', $html) ?? $html;

    foreach ([
        'rc045','bashers of the south','eygelshoven','wijngaardsberg',
        'bestuur@rc045.nl','facebook.com/rc045','kok lexmond',
        'nl51 rabo 0367 6153 63','pjaminon@me.com','goatcounter.com',
    ] as $verboden) {
        if (stripos($html, $verboden) !== false) {
            error_log('[platform] tenantuitvoer geblokkeerd door legacy fingerprint: ' . $verboden);
            throw new RuntimeException('Publieke tenantuitvoer bevat niet-neutrale voorbeeldinhoud.');
        }
    }
    return $html;
}

function tenantPublicRuntimeStart(array $config, ?string $externPad): void
{
    if ($externPad === null || PHP_SAPI === 'cli') return;
    static $gestart = false;
    if ($gestart) return;
    $gestart = true;
    ob_start(static fn(string $html): string => tenantPublicRuntimeTransform($html, $config));
}
