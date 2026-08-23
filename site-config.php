<?php
// ============================================================
// Centrale verenigingsconfiguratie
// ============================================================
// Dit bestand bevat veilige standaardwaarden waarmee RC045 blijft werken.
// Per installatie/vereniging kan daarnaast een server-only configuratie
// worden geladen. Voor bestaande installaties blijft `site-config.local.php`
// werken. Voor de multi-tenant VPS kan VERENIGING_CONFIG_FILE naar een
// absoluut configpad buiten de gedeelde codebase wijzen.
// ============================================================
require_once __DIR__ . '/app/core/tenant-runtime.php';

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
        'logo' => 'rc045-logo.png','social_image' => 'rc045-logo.png','favicon' => 'favicon.ico','favicon_16' => 'favicon-16x16.png','favicon_32' => 'favicon-32x32.png','favicon_48' => 'favicon-48x48.png','apple_touch_icon' => 'apple-touch-icon.png','manifest' => 'site.webmanifest','theme_color' => '#1E2C13',
        'kleuren' => ['primary'=>'#3A7A77','primary_dark'=>'#2D6260','primary_light'=>'#EAF4F3','accent'=>'#C89A1A','accent_light'=>'#FBF4DF','dark'=>'#1E2C13','text'=>'#2A3818','muted'=>'#6A7560','background'=>'#FAF6EC'],
    ],
    'modules' => [
        'website'=>true,'ledenadministratie'=>true,'werkgroepen'=>true,'evenementen'=>true,'vergaderingen'=>true,'taken'=>true,'operationele_taken'=>true,'fotoboek'=>true,'sponsors'=>true,'media'=>true,'aanmelden'=>true,
    ],
    'opslag' => [
        'private_driver' => 'json',
        // Leeg houdt de bestaande RC045 JSON-fallback in de projectroot in
        // stand. Nieuwe tenants krijgen via hun externe config een absoluut,
        // server-only pad buiten de gedeelde documentroot.
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

$config['vereniging']['sleutel'] = tenantRuntimeVeiligeSleutel((string)($config['vereniging']['sleutel'] ?? 'default'));
$timezone=trim((string)($config['vereniging']['timezone']??''));if($timezone!==''&&in_array($timezone,timezone_identifiers_list(),true))date_default_timezone_set($timezone);

// Securityaudit: één afdwingbare browserpolicy voor PHP-responses. Uitvoerbare
// scripts mogen uitsluitend van de eigen origin komen. Externe tenants mogen
// bovendien nooit het historische RC045/Formspree-contactdoel gebruiken.
// De enige externe frame-origin is de read-only OpenStreetMap embed die op de
// publieke locatiekaart wordt gebruikt; frame-ancestors blijft 'none', zodat
// onze eigen pagina's zelf nergens ingebed kunnen worden.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    $formAction = $externPad !== null ? "'self'" : "'self' https://formspree.io";
    $connectSrc = $externPad !== null
        ? "'self' https://api.open-meteo.com"
        : "'self' https://api.open-meteo.com https://formspree.io";
    header(
        "Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; "
        . "form-action {$formAction}; script-src 'self' 'unsafe-inline'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data: blob: https:; "
        . "connect-src {$connectSrc}; media-src 'self' blob:; worker-src 'self' blob:; "
        . "frame-src https://www.openstreetmap.org; upgrade-insecure-requests"
    );
}

// Fase 4.6: externe VPS-tenants registreren een privacy-arme fatal logger.
// Standalone/DEV en CLI blijven volledig ongemoeid.
require_once __DIR__ . '/app/operational-log.php';
vpOps46RegisterFatalLogger($config);

return $config;
