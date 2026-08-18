<?php
// ============================================================
// Beheer module-registry
// ============================================================
// Centrale lijst van onderdelen die uit het historische beheer.php worden
// losgetrokken. Tijdens fase 2 kan ieder onderdeel afzonderlijk van
// 'legacy' naar 'module' migreren zonder de rest van beheer.php tegelijk te
// herschrijven.
// ============================================================

return [
    'contentpaginas' => [
        'label' => 'Contentpagina\'s',
        'status' => 'module',
        'bootstrap' => __DIR__ . '/modules/contentpaginas.php',
        'legacy_tabs' => ['ontstaan', 'baanreglement'],
        'legacy_formulieren' => ['ontstaan', 'baanreglement'],
    ],
    'agenda' => [
        'label' => 'Agenda',
        'status' => 'legacy',
    ],
    'sponsors' => [
        'label' => 'Sponsors',
        'status' => 'legacy',
    ],
    'media' => [
        'label' => 'Media',
        'status' => 'legacy',
    ],
    'fotoboek' => [
        'label' => 'Fotoboek',
        'status' => 'legacy',
    ],
    'aanmelden' => [
        'label' => 'Aanmelden',
        'status' => 'legacy',
    ],
    'homepage' => [
        'label' => 'Homepage',
        'status' => 'legacy',
    ],
    'gebruikers' => [
        'label' => 'Gebruikers',
        'status' => 'legacy',
    ],
    'backups' => [
        'label' => 'Back-ups',
        'status' => 'legacy',
    ],
    'logboek' => [
        'label' => 'Logboek',
        'status' => 'legacy',
    ],
];
