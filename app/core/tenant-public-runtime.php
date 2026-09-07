<?php
// ============================================================
// Productieruntime voor externe tenantbranding
// ============================================================
// Externe tenants delen de publieke templates, maar tenantidentiteit wordt
// contextueel toegepast. Tekst, URL-attributen, JSON en JavaScript krijgen
// ieder hun eigen renderpad; willekeurige broncode/identifiers worden niet
// meer via een whole-response zoek-en-vervanglaag gemuteerd.
// ============================================================
require_once __DIR__ . '/csp-runtime.php';

function tenantPublicRuntimeKleur(array $config, string $sleutel, string $fallback): string
{
    $waarde = strtoupper(trim((string)($config['branding']['kleuren'][$sleutel] ?? '')));
    return preg_match('/^#[0-9A-F]{6}$/D', $waarde) === 1 ? $waarde : $fallback;
}

function tenantPublicRuntimeHtmlWaarde(string $waarde): string
{
    return htmlspecialchars($waarde, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function tenantPublicRuntimeHttpUrl(string $waarde, string $fallback = ''): string
{
    $waarde = trim($waarde);
    if ($waarde === '') return $fallback;
    if (preg_match('/[\x00-\x1F\x7F]/', $waarde) === 1) return $fallback;
    if (filter_var($waarde, FILTER_VALIDATE_URL) === false) return $fallback;

    $schema = strtolower((string) parse_url($waarde, PHP_URL_SCHEME));
    if (!in_array($schema, ['http', 'https'], true)) return $fallback;
    if (parse_url($waarde, PHP_URL_HOST) === null) return $fallback;
    if (parse_url($waarde, PHP_URL_USER) !== null || parse_url($waarde, PHP_URL_PASS) !== null) return $fallback;
    return $waarde;
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

function tenantPublicRuntimeRenderContext(array $config): array
{
    $naam = trim((string)($config['vereniging']['naam'] ?? 'Vereniging')) ?: 'Vereniging';
    $volledig = trim((string)($config['vereniging']['volledige_naam'] ?? $naam)) ?: $naam;
    $slogan = trim((string)($config['vereniging']['slogan'] ?? ''));
    $siteUrl = tenantPublicRuntimeHttpUrl((string)($config['vereniging']['site_url'] ?? ''), '/');
    $betaling = is_array($config['betaling'] ?? null) ? $config['betaling'] : [];
    $iban = trim((string)($betaling['iban'] ?? '')) ?: 'Nog niet ingesteld';
    $tenaamstelling = trim((string)($betaling['tenaamstelling'] ?? '')) ?: $volledig;
    $omschrijving = trim((string)($betaling['omschrijving'] ?? '')) ?: 'Contributie {jaar} - {naam}';
    $omschrijving = str_replace('{naam}', $naam, $omschrijving);
    $contact = tenantPublicRuntimeContact($config);
    $email = filter_var((string)($contact['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? (string)$contact['email'] : '';
    $facebook = tenantPublicRuntimeHttpUrl((string)($contact['facebook'] ?? ''), '');
    $straat = trim((string)($contact['adres_straat'] ?? ''));
    $plaats = trim((string)($contact['adres_postcode_plaats'] ?? ''));

    return [
        'naam' => $naam,
        'volledige_naam' => $volledig,
        'slogan' => $slogan,
        'site_url' => $siteUrl,
        'email' => $email,
        'facebook' => $facebook,
        'tekst' => [
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
            'facebook.com/rc045' => $facebook !== '' ? (string) preg_replace('~^https?://~i', '', $facebook) : 'sociale media volgen',
            'Wijngaardsberg 26' => $straat !== '' ? $straat : 'Adres nog niet ingesteld',
            '6464 EZ Eygelshoven' => $plaats !== '' ? $plaats : 'Plaats nog niet ingesteld',
            'Kerkrade (Eygelshoven)' => $plaats !== '' ? $plaats : 'Plaats nog niet ingesteld',
            'Eygelshoven' => $plaats !== '' ? $plaats : 'de verenigingslocatie',
            'Kok Lexmond' => 'de locatiebeheerder',
            'https://rc045.nl' => $siteUrl,
            'pjaminon@me.com' => '',
            'Pascal Jaminon' => 'Websitebeheer',
            'RC045' => $naam,
        ],
    ];
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
    return '<style nonce="' . siteCspHtmlWaarde(siteCspNonce()) . '" id="tenant-product-theme">:root{' . implode(';', $regels) . '}'
        . '.nav{background:var(--nav-bg)!important}.nav-links a,.nav-logo-text,.nav-logo-sub{color:' . $navText . '!important}'
        . '.nav-links .nav-lid a{color:#fff!important}'
        . '</style>';
}

function tenantPublicRuntimeContextMarkup(array $config): string
{
    $naam = trim((string)($config['vereniging']['naam'] ?? 'Vereniging')) ?: 'Vereniging';
    $context = [
        'external' => true,
        'tenantKey' => (string)($config['vereniging']['sleutel'] ?? 'tenant'),
        'name' => $naam,
        'siteUrl' => (string)($config['vereniging']['site_url'] ?? ''),
    ];
    $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
    return '<script id="vereniging-site-context">window.verenigingSiteContext=' . $json . ';</script>';
}

function tenantPublicRuntimeHeadMarkup(array $config, bool $contextOpnemen = true): string
{
    $regels = [tenantPublicRuntimeThemeMarkup($config)];
    $favicon = tenantPublicRuntimeAssetUrl($config, 'favicon');
    $logo = tenantPublicRuntimeAssetUrl($config, 'logo', 'images/template-placeholder.svg');
    $theme = tenantPublicRuntimeKleur($config, 'dark', '#1E2C13');
    if ($favicon !== '') $regels[] = '<link rel="icon" href="' . htmlspecialchars($favicon, ENT_QUOTES, 'UTF-8') . '">';
    elseif ($logo !== '') $regels[] = '<link rel="icon" href="' . htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') . '">';
    $regels[] = '<meta name="theme-color" content="' . $theme . '">';
    if ($contextOpnemen) $regels[] = tenantPublicRuntimeContextMarkup($config);
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
            if (preg_match('~^(?:https?:|data:|blob:|/public-asset\.php|public-asset\.php|/branding-asset\.php|branding-asset\.php)~i', $src) === 1) return $m[0];
            if (str_starts_with($lower, 'images/') && !str_contains($lower, 'template-placeholder')) return $m[1] . $placeholder . $m[3];
            return $m[0];
        },
        $html
    ) ?? $html;

    $html = preg_replace_callback(
        '~(\bdata-bg=["\'])([^"\']+)(["\'])~i',
        static function (array $m) use ($placeholder): string {
            $src = $m[2];
            if (preg_match('~^(?:https?:|data:|blob:|/public-asset\.php|public-asset\.php|/branding-asset\.php|branding-asset\.php)~i', $src) === 1) return $m[0];
            return $m[1] . $placeholder . $m[3];
        },
        $html
    ) ?? $html;

    $html = preg_replace("~url\((['\"]?)images/(?!template-placeholder)[^)]+\)~i", "url('images/template-placeholder.svg')", $html) ?? $html;
    return $html;
}

function tenantPublicRuntimeJsWaarde(string $waarde): string
{
    $json = json_encode($waarde, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (!is_string($json) || strlen($json) < 2) return '';
    return substr($json, 1, -1);
}

function tenantPublicRuntimeJsonContext($waarde, array $tekstMap)
{
    if (is_string($waarde)) return str_replace(array_keys($tekstMap), array_values($tekstMap), $waarde);
    if (!is_array($waarde)) return $waarde;
    foreach ($waarde as $sleutel => $item) $waarde[$sleutel] = tenantPublicRuntimeJsonContext($item, $tekstMap);
    return $waarde;
}

function tenantPublicRuntimeSiteHref(string $href, array $context): string
{
    $facebook = (string)($context['facebook'] ?? '');
    $email = (string)($context['email'] ?? '');
    $siteUrl = (string)($context['site_url'] ?? '/');

    if ($href === 'https://www.facebook.com/rc045/' || $href === 'https://www.facebook.com/rc045') {
        return $facebook !== '' ? $facebook : '#contact';
    }
    if (str_starts_with(strtolower($href), 'mailto:bestuur@rc045.nl')) {
        return $email !== '' ? 'mailto:' . $email : '#contact';
    }
    if (stripos($href, 'pjaminon') !== false && str_starts_with(strtolower($href), 'mailto:')) return '#';
    if (str_starts_with($href, 'https://rc045.nl')) {
        $suffix = substr($href, strlen('https://rc045.nl'));
        return rtrim($siteUrl, '/') . '/' . ltrim($suffix, '/');
    }
    return $href;
}

function tenantPublicRuntimePasRenderContextToe(string $html, array $config): string
{
    if (!class_exists(DOMDocument::class)) {
        throw new RuntimeException('DOM-extensie ontbreekt; tenantoutput kan niet contextveilig worden gerenderd.');
    }

    $context = tenantPublicRuntimeRenderContext($config);
    $tekstMap = $context['tekst'];
    $vorige = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $geladen = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($vorige);
    if (!$geladen) throw new RuntimeException('Publieke tenant-HTML kon niet contextveilig worden verwerkt.');
    foreach ($dom->childNodes as $kind) {
        if ($kind->nodeType === XML_PI_NODE) { $dom->removeChild($kind); break; }
    }

    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//text()[not(ancestor::script) and not(ancestor::style)]') ?: [] as $node) {
        $node->nodeValue = str_replace(array_keys($tekstMap), array_values($tekstMap), (string)$node->nodeValue);
    }

    foreach ($xpath->query('//*') ?: [] as $element) {
        if (!$element instanceof DOMElement) continue;
        foreach (['title', 'alt', 'aria-label', 'placeholder', 'content', 'value'] as $attribuut) {
            if (!$element->hasAttribute($attribuut)) continue;
            $element->setAttribute($attribuut, str_replace(array_keys($tekstMap), array_values($tekstMap), $element->getAttribute($attribuut)));
        }
        if ($element->hasAttribute('href')) {
            $element->setAttribute('href', tenantPublicRuntimeSiteHref($element->getAttribute('href'), $context));
        }
    }

    foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $script) {
        if (!$script instanceof DOMElement) continue;
        $data = json_decode(trim($script->textContent), true);
        if (!is_array($data)) continue;
        $data = tenantPublicRuntimeJsonContext($data, $tekstMap);
        $script->textContent = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
    }

    // Legacy inline scripts mogen alleen zichtbare, hoofdlettergevoelige
    // voorbeeldinhoud krijgen. Technische identifiers zoals rc045_lang blijven
    // onaangeroerd; daardoor kan een displaynaam nooit meer een storage key,
    // selector of andere programmatische identifier muteren.
    $jsMap = [];
    foreach ($tekstMap as $bron => $doel) $jsMap[$bron] = tenantPublicRuntimeJsWaarde((string)$doel);
    foreach ($xpath->query('//script[not(@type="application/ld+json") and not(@id="vereniging-site-context")]') ?: [] as $script) {
        if (!$script instanceof DOMElement) continue;
        $script->textContent = str_replace(array_keys($jsMap), array_values($jsMap), $script->textContent);
    }

    // De aanmeldroute is een expliciete applicatiecontext, geen merkvervanging.
    $aanmeldForm = $dom->getElementById('aanmeld-form');
    if ($aanmeldForm instanceof DOMElement) $aanmeldForm->setAttribute('action', 'aanmelden-ontvangst.php');

    $zichtbaar = '';
    foreach ($xpath->query('//text()[not(ancestor::script) and not(ancestor::style)]') ?: [] as $node) $zichtbaar .= ' ' . $node->nodeValue;
    foreach ($xpath->query('//*[@href or @title or @alt or @aria-label or @content]') ?: [] as $element) {
        if (!$element instanceof DOMElement) continue;
        foreach (['href', 'title', 'alt', 'aria-label', 'content'] as $attribuut) {
            if ($element->hasAttribute($attribuut)) $zichtbaar .= ' ' . $element->getAttribute($attribuut);
        }
    }
    foreach ([
        'rc045','bashers of the south','eygelshoven','wijngaardsberg',
        'bestuur@rc045.nl','facebook.com/rc045','kok lexmond',
        'nl51 rabo 0367 6153 63','pjaminon@me.com','goatcounter.com',
    ] as $verboden) {
        if (stripos($zichtbaar, $verboden) !== false) {
            error_log('[platform] tenantuitvoer geblokkeerd door legacy fingerprint: ' . $verboden);
            throw new RuntimeException('Publieke tenantuitvoer bevat niet-neutrale voorbeeldinhoud.');
        }
    }

    return $dom->saveHTML() ?: '';
}

function tenantPublicRuntimeTransform(string $html, array $config): string
{
    if ($html === '' || (stripos($html, '<html') === false && stripos($html, '<!doctype') === false)) return $html;

    $logo = tenantPublicRuntimeAssetUrl($config, 'logo', 'images/template-placeholder.svg');

    // Oude favicon/manifest/analytics-tags verdwijnen voor externe tenants.
    $html = preg_replace('~<link\b[^>]+href=["\'][^"\']*(?:favicon|apple-touch-icon|site\.webmanifest)[^"\']*["\'][^>]*>~i', '', $html) ?? $html;
    $html = preg_replace('~<script\b[^>]*(?:data-goatcounter|src=["\'][^"\']*(?:goatcounter|gc\.zgo\.at)[^"\']*["\'])[^>]*>.*?</script>~is', '', $html) ?? $html;

    if (stripos($html, '</head>') !== false && strpos($html, 'id="tenant-product-theme"') === false) {
        $heeftContext = strpos($html, 'id="vereniging-site-context"') !== false;
        $head = tenantPublicRuntimeHeadMarkup($config, !$heeftContext);
        $html = preg_replace('~</head>~i', $head . "\n</head>", $html, 1) ?? $html;
    }

    // Bestaande inline aanmeldcode mag voor externe tenants niet nog een tweede
    // client-side ontvangstpad starten. Dit is een smalle legacy-compatibiliteits-
    // guard en staat los van tenantidentiteit.
    $html = str_replace(
        "fetch('aanmelden-ontvangst.php', {",
        "if (!(window.verenigingSiteContext && window.verenigingSiteContext.external)) fetch('aanmelden-ontvangst.php', {",
        $html
    );

    $html = tenantPublicRuntimePlaceholderLokaleMedia($html, $logo);
    return tenantPublicRuntimePasRenderContextToe($html, $config);
}

function tenantPublicRuntimeStart(array $config, ?string $externPad): void
{
    if ($externPad === null || PHP_SAPI === 'cli') return;
    static $gestart = false;
    if ($gestart) return;
    $gestart = true;
    ob_start(static fn(string $html): string => tenantPublicRuntimeTransform($html, $config));
}
