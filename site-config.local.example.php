<?php
// Voorbeeld van een server-only verenigingsconfiguratie.
// Kopieer dit bestand op de server naar site-config.local.php en pas alleen
// afwijkende waarden aan. site-config.local.php staat in .gitignore.

return [
    'vereniging' => [
        'sleutel' => 'voorbeeldvereniging',
        'naam' => 'Voorbeeldvereniging',
        'volledige_naam' => 'Voorbeeldvereniging Nederland',
        'slogan' => 'Samen actief',
        'site_url' => 'https://vereniging.example',
        'timezone' => 'Europe/Amsterdam',
        'standaard_taal' => 'nl',
    ],
    'branding' => [
        'logo' => 'vereniging-logo.png','social_image' => 'vereniging-social.png','theme_color' => '#245A4A',
        'kleuren' => ['primary'=>'#357C68','primary_dark'=>'#245A4A','primary_light'=>'#E8F3EF','accent'=>'#D19B2A','accent_light'=>'#FBF3DC','dark'=>'#1E3028','text'=>'#26352F','muted'=>'#68756F','background'=>'#F7F5EF'],
    ],
    'modules' => [
        'ledenadministratie'=>true,
        'werkgroepen'=>true,
        'vergaderingen'=>true,
        'taken'=>true,
        'operationele_taken'=>true,
        'evenementen'=>true,
        'fotoboek'=>true,
        'sponsors'=>true,
        'media'=>false,
        'aanmelden'=>true,
    ],
    'opslag' => [
        'private_driver'=>'json',
        'pdo'=>[
            // PostgreSQL voorbeeld:
            // 'dsn' => 'pgsql:host=127.0.0.1;port=5432;dbname=verenigingen',
            'dsn'=>'','user'=>'','password'=>'',
        ],
    ],
];
