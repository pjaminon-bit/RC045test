<?php
// ============================================================
// Centrale verenigingsconfiguratie
// ============================================================
// De repository bevat een standalone compatibiliteitsprofiel. Externe tenants
// laden eerst hun server-only basisconfig en daarna uitsluitend de veilige,
// via Beheer wijzigbare whitelist uit private_root/settings/site.json.
// ============================================================
require_once __DIR__ . '/app/core/tenant-runtime.php';
require_once __DIR__ . '/app/core/tenant-settings.php';

$config = [
    'vereniging' => [
        'sleutel' => 'rc045',
        'naam' => 'RC045',
        'volledige_naam' => 'RC045 – Bashers of the South',
        'slogan' => 'Bashers of the South',
        'site_url' => 'https://rc045.nl',
        'timezone' => 'Europe/Amsterdam',
        'standaard_taal' => 'nl',
        'talen' => [
            'nl' => 'nl_NL',
            'en' => 'en_GB',
            'de' => 'de_DE',
        ],
    ],
    'branding' => [
        'logo' => 'rc045-logo.png',
        'social_image' => 'rc045-logo.png',
        'favicon' => 'favicon.ico',
        'favicon_16' => 'favicon-16x16.png',
        'favicon_32' => 'favicon-32x32.png',
        'favicon_48' => 'favicon-48x48.png',
        'apple_touch_icon' => 'apple-touch-icon.png',
        'manifest' => 'site.webmanifest',
        'theme_color' => '#1E2C13',
        'kleuren' => [
            'primary'=>'#3A7A77','primary_dark'=>'#2D6260','primary_light'=>'#EAF4F3',
            'accent'=>'#C89A1A','accent_light'=>'#FBF4DF','dark'=>'#1E2C13',
            'text'=>'#2A3818','muted'=>'#6A7560','background'=>'#FAF6EC',
            'nav_background'=>'#FFFFFF','nav_text'=>'#2A3818',
        ],
        'afbeeldingen' => ['hero'=>'','about'=>'','activity'=>'','gallery'=>''],
    ],
    'betaling' => [
        'iban' => '',
        'tenaamstelling' => '',
        'omschrijving' => 'Contributie {jaar} - {naam}',
    ],
    'privacy' => [
        'aanmeldingen_bewaardagen' => 90,
        'contactberichten_bewaardagen' => 180,
    ],
    'modules' => [
        'website'=>true,'ledenadministratie'=>true,'werkgroepen'=>true,'evenementen'=>true,
        'vergaderingen'=>true,'taken'=>true,'operationele_taken'=>true,'fotoboek'=>true,
        'sponsors'=>true,'media'=>true,'aanmelden'=>true,
    ],
    'opslag' => [
        'private_driver' => 'json',
        'private_root' => '',
        'pdo' => ['dsn'=>'','user'=>'','password'=>''],
    ],
];

$externPad = tenantRuntimeExternConfigPad();
$lokaalPad = __DIR__ . '/site-config.local.php';
$overridePad = $externPad ?? (is_file($lokaalPad) ? $lokaalPad : null);
if ($overridePad !== null) {
    $lokaal = require $overridePad;
    if (!is_array($lokaal)) {
        throw new RuntimeException('Verenigingsconfiguratie moet een array retourneren.');
    }
    $config = array_replace_recursive($config, $lokaal);
}

// Alleen externe tenants krijgen web-bewerkbare instellingen. De server-only
// config blijft bron van waarheid voor tenant-key, site-url, opslag en database.
if ($externPad !== null) {
    try {
        $bewerkbaar = tenantSettingsLees($config);
        if ($bewerkbaar !== []) $config = array_replace_recursive($config, $bewerkbaar);
    } catch (TenantSettingsStorageException $e) {
        // Publieke/read-only runtime blijft beschikbaar op de server-only
        // basisconfig, maar de requeststate onthoudt expliciet dat site.json
        // onveilig is. Beheer gebruikt die state om alle settings/brandingwrites
        // fail-closed te blokkeren totdat gecontroleerde recovery is uitgevoerd.
        tenantSettingsRuntimeLeesfoutMarkeer();
    }

    // Het standalone compatibiliteitsprofiel bevat historische RC045-assets.
    // Een nieuwe externe tenant die nog geen eigen branding heeft ingesteld
    // mag die bestanden niet erven en de outputfilter mag er evenmin een
    // fictief pad zoals Testvereniging-logo.png van maken. Alleen exact de
    // ingebouwde defaults worden geneutraliseerd; een echte tenantasset uit de
    // server-only config of settings blijft onaangeroerd. Lege branding blijft
    // bewust leeg: de presentatielaag mag desgewenst een neutrale placeholder
    // tonen, maar de tenantconfig zelf verzint geen asset-URL.
    $legacyBranding = [
        'logo' => 'rc045-logo.png',
        'social_image' => 'rc045-logo.png',
        'favicon' => 'favicon.ico',
        'favicon_16' => 'favicon-16x16.png',
        'favicon_32' => 'favicon-32x32.png',
        'favicon_48' => 'favicon-48x48.png',
        'apple_touch_icon' => 'apple-touch-icon.png',
        'manifest' => 'site.webmanifest',
    ];
    foreach ($legacyBranding as $sleutel => $legacyPad) {
        if (hash_equals($legacyPad, trim((string)($config['branding'][$sleutel] ?? '')))) {
            $config['branding'][$sleutel] = '';
        }
    }
}

$config['vereniging']['sleutel'] = tenantRuntimeVeiligeSleutel((string)($config['vereniging']['sleutel'] ?? 'default'));
$timezone = trim((string)($config['vereniging']['timezone'] ?? ''));
if ($timezone !== '' && in_array($timezone, timezone_identifiers_list(), true)) date_default_timezone_set($timezone);

// Eén afdwingbare browserpolicy. Publieke formulieren blijven volledig
// same-origin; persoonsgegevens gaan niet meer naar een externe formprovider.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    $formAction = "'self'";
    $connectSrc = "'self' https://api.open-meteo.com";
    header(
        "Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; "
        . "form-action {$formAction}; script-src 'self' 'unsafe-inline'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data: blob: https:; "
        . "connect-src {$connectSrc}; media-src 'self' blob:; worker-src 'self' blob:; "
        . "frame-src https://www.openstreetmap.org; upgrade-insecure-requests"
    );
}

require_once __DIR__ . '/app/operational-log.php';
vpOps46RegisterFatalLogger($config);

// De algemene neutralisatielaag is buitenste buffer. De mediabuffer start
// daarna en vult eerst tenant-eigen beelden in; absolute tenant-URLs worden
// vervolgens door de neutralisatielaag behouden. De contactguard start als
// laatste gedeelde buffer en forceert het historische contactformulier naar
// de eigen inbox; een later gestarte pagina-buffer kan daarna nog veilig
// tenantinhoud invullen.
require_once __DIR__ . '/app/core/tenant-public-runtime.php';
tenantPublicRuntimeStart($config, $externPad);
require_once __DIR__ . '/app/core/tenant-public-media.php';
tenantPublicMediaStart($config, $externPad);
require_once __DIR__ . '/app/core/contact-inbox-runtime.php';
contactInboxRuntimeStart();

return $config;