<?php
// ============================================================
// Centrale rechtenlijst voor het modulaire gebruikersbeheer
// ============================================================
// Sleutels blijven gelijk aan de bestaande beheer-tabs; alleen presentatie
// en groepering staat hier. 'gevoelig' markeert rechten waarmee auditdata,
// herstelacties of autorisaties zelf toegankelijk worden.
// ============================================================

return [
    'groepen' => [
        'Pagina’s' => [
            'homepage' => 'Homepage',
            'ontstaan' => 'Ontstaan',
            'baanreglement' => 'Baanreglement',
            'aanmelden' => 'Aanmelden',
            'bedankt' => 'Bedankt-pagina',
        ],
        'Content' => [
            'mededeling' => 'Openingstijden',
            'nieuws' => 'Nieuws',
            'agenda' => 'Agenda',
            'contact' => 'Contact',
            'sponsors' => 'Sponsors',
            'faq' => 'Vragen',
            'media' => 'Media',
            'fotoboek' => 'Fotoboek',
        ],
        'Contributie' => [
            'rekentabel' => 'Rekentabel',
        ],
        'Beheer' => [
            'changelog' => 'Changelog',
            'log' => 'Logboek',
            'backups' => 'Back-ups',
            'gebruikers' => 'Gebruikers',
        ],
    ],
    'gevoelig' => ['gebruikers', 'backups', 'log'],
];
