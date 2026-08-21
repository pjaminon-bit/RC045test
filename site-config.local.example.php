<?php
// Voorbeeld van een server-only verenigingsconfiguratie.
//
// Bestaande losse installatie:
//   kopieer naar site-config.local.php (staat in .gitignore).
//
// Gedeelde multi-tenant codebase:
//   bewaar dit bestand buiten de code/documentroot en zet per vhost/process:
//   VERENIGING_CONFIG_FILE=/srv/verenigingen/<tenant>/config.php
//
// `private_root` hoort eveneens buiten de publieke documentroot te staan.
//
// Productie-PDO vanaf fase 4.5:
//   provision de tenant met private_driver=pdo en laat dsn/user/password hier
//   leeg. `prepare-vps-database.php` maakt vervolgens het vaste secretvrije
//   <tenantroot>/database/database-runtime.json. De canonieke lokale
//   PostgreSQL-stack gebruikt Unix-socket peer authentication en bewaart dus
//   bewust geen databasewachtwoord.
//
// De expliciete pdo dsn/user/password velden hieronder blijven alleen bestaan
// voor standalone/legacy ontwikkel- of migratiesituaties.

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
        'private_root'=>'/srv/verenigingen/voorbeeldvereniging/private',
        'pdo'=>[
            'dsn'=>'','user'=>'','password'=>'',
        ],
    ],
];
