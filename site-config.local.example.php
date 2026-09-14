<?php
// ============================================================
// STANDALONE / LEGACY configuratievoorbeeld
// ============================================================
// Dit bestand is uitsluitend bedoeld voor bestaande losse installaties,
// lokale ontwikkeling en migratiecompatibiliteit.
//
// Gebruik voor een nieuwe multi-tenant VPS-vereniging NIET dit bestand als
// provisioningtemplate. Maak nieuwe tenants aan met bin/provision-tenant.php;
// daar is PDO/PostgreSQL de canonieke standaard en wordt de server-only
// config buiten de code/documentroot gegenereerd.
//
// Bestaande losse installatie:
//   kopieer naar site-config.local.php (staat in .gitignore).
//
// `private_root` hoort ook in standalonegebruik buiten de publieke
// documentroot te staan.
//
// JSON hieronder is bewust de standalone/legacycompatibiliteitsdriver.
// Voor nieuwe VPS-tenants gebruikt de provisioner standaard private_driver=pdo.
// De canonieke VPS-stack gebruikt passwordloze PostgreSQL Unix-socket peer
// authentication; dsn/user/password worden daar niet uit dit voorbeeld gevuld.
//
// De expliciete pdo dsn/user/password velden hieronder bestaan alleen voor
// standalone/legacy ontwikkel- of migratiesituaties waar bewust een andere
// PDO-binding wordt getest.
//
// Notificatiecredentials horen altijd buiten repositorycode en webinstellingen
// onder de gekozen private_root te staan.

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
        'logo' => 'vereniging-logo.png',
        'social_image' => 'vereniging-social.png',
        'theme_color' => '#245A4A',
        'kleuren' => [
            'primary'=>'#357C68','primary_dark'=>'#245A4A','primary_light'=>'#E8F3EF',
            'accent'=>'#D19B2A','accent_light'=>'#FBF3DC','dark'=>'#1E3028',
            'text'=>'#26352F','muted'=>'#68756F','background'=>'#F7F5EF',
        ],
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
        'private_driver'=>'json', // uitsluitend standalone/legacy in dit voorbeeld
        'private_root'=>'/srv/verenigingen/voorbeeldvereniging/private',
        'pdo'=>[
            'dsn'=>'','user'=>'','password'=>'',
        ],
    ],
    'notificaties' => [
        'enabled'=>false,
        'required'=>false,
        'provider'=>'smtp',
        'from'=>'notificaties@vereniging.example',
        'recipients'=>[
            'contact.received'=>['bestuur@vereniging.example'],
            'membership.received'=>['ledenadministratie@vereniging.example'],
        ],
        'smtp'=>[
            'host'=>'smtp.vereniging.example',
            'port'=>587,
            'security'=>'starttls',
            'credentials_file'=>'/srv/verenigingen/voorbeeldvereniging/private/secrets/notifications-smtp.json',
        ],
        'stale_after_seconds'=>900,
    ],
];
