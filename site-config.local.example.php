<?php
// Voorbeeld van een server-only verenigingsconfiguratie.
// Kopieer dit bestand op de server naar site-config.local.php en pas alleen
// de waarden aan die voor die vereniging afwijken. site-config.local.php staat
// in .gitignore en hoort nooit in de gedeelde repository.

return [
    'vereniging' => [
        'naam' => 'Voorbeeldvereniging',
        'volledige_naam' => 'Voorbeeldvereniging Nederland',
        'slogan' => 'Samen actief',
        'site_url' => 'https://vereniging.example',
        'timezone' => 'Europe/Amsterdam',
        'standaard_taal' => 'nl',
    ],
    'branding' => [
        'logo' => 'vereniging-logo.png',
        'social_image' => 'vereniging-social.png',
        'theme_color' => '#245A4A',
        'kleuren' => [
            'primary' => '#357C68',
            'primary_dark' => '#245A4A',
            'primary_light' => '#E8F3EF',
            'accent' => '#D19B2A',
            'accent_light' => '#FBF3DC',
            'dark' => '#1E3028',
            'text' => '#26352F',
            'muted' => '#68756F',
            'background' => '#F7F5EF',
        ],
    ],
    'modules' => [
        'fotoboek' => true,
        'sponsors' => true,
        'media' => false,
        'aanmelden' => true,
    ],
];
