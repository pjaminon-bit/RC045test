<?php
// ============================================================
// Centrale verenigingsconfiguratie
// ============================================================
// Alle gegevens die per vereniging kunnen verschillen horen uiteindelijk
// hier (of later in de database / tenant-configuratie) thuis. De standaard-
// waarden hieronder reproduceren bewust de huidige RC045-installatie, zodat
// het introduceren van deze configuratielaag het bestaande gedrag niet wijzigt.
// ============================================================

return [
    'vereniging' => [
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
        'apple_touch_icon' => 'apple-touch-icon.png',
        'manifest' => 'site.webmanifest',
        'theme_color' => '#1E2C13',
        'kleuren' => [
            'primary' => '#3A7A77',
            'primary_dark' => '#2D6260',
            'primary_light' => '#EAF4F3',
            'accent' => '#C89A1A',
            'accent_light' => '#FBF4DF',
            'dark' => '#1E2C13',
            'text' => '#2A3818',
            'muted' => '#6A7560',
            'background' => '#FAF6EC',
        ],
    ],

    // Hiermee kunnen we in een volgende stap onderdelen per vereniging aan-
    // of uitzetten zonder aparte codebases te maken.
    'modules' => [
        'website' => true,
        'ledenadministratie' => true,
        'evenementen' => true,
        'vergaderingen' => true,
        'taken' => true,
        'operationele_taken' => true,
        'fotoboek' => true,
        'sponsors' => true,
        'media' => true,
        'aanmelden' => true,
    ],
];
