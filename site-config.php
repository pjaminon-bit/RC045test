<?php
// ============================================================
// Centrale verenigingsconfiguratie
// ============================================================
// Dit bestand bevat veilige standaardwaarden waarmee RC045 blijft werken.
// Per installatie/vereniging kan daarnaast een server-only bestand
// `site-config.local.php` bestaan. Dat bestand wordt recursief over deze
// defaults heen gelegd en hoeft dus alleen de afwijkende waarden te bevatten.
// Zo kan dezelfde codebase meerdere verenigingen bedienen zonder fork.
// ============================================================

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
        'pdo' => ['dsn'=>'','user'=>'','password'=>''],
    ],
];
$lokaalPad=__DIR__.'/site-config.local.php';if(is_file($lokaalPad)){$lokaal=require $lokaalPad;if(is_array($lokaal))$config=array_replace_recursive($config,$lokaal);}
$timezone=trim((string)($config['vereniging']['timezone']??''));if($timezone!==''&&in_array($timezone,timezone_identifiers_list(),true))date_default_timezone_set($timezone);
return $config;
