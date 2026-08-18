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
    'devbuild' => [
        'label' => 'DEV build-indicator',
        'status' => 'module',
        'bootstrap' => __DIR__ . '/modules/dev-build.php',
    ],
    'contentpaginas' => [
        'label' => 'Contentpagina\'s',
        'status' => 'module',
        'bootstrap' => __DIR__ . '/modules/contentpaginas.php',
        'editor' => __DIR__ . '/content.php',
        'legacy_tabs' => ['homepage', 'ontstaan', 'baanreglement', 'aanmelden'],
        'legacy_formulieren' => ['homepage', 'ontstaan', 'baanreglement', 'aanmelden'],
    ],
    'homepage' => [
        'label' => 'Homepage',
        'status' => 'module',
        'editor' => __DIR__ . '/content.php?pagina=homepage',
    ],
    'agenda' => [
        'label' => 'Agenda',
        'status' => 'module',
        'bootstrap' => __DIR__ . '/modules/agenda.php',
        'editor' => __DIR__ . '/agenda.php',
        'legacy_tabs' => ['agenda'],
        'legacy_formulieren' => ['agenda'],
    ],
    'sponsors' => [
        'label' => 'Sponsors',
        'status' => 'module',
        'bootstrap' => __DIR__ . '/modules/sponsors.php',
        'editor' => __DIR__ . '/sponsors.php',
        'legacy_tabs' => ['sponsors'],
        'legacy_formulieren' => ['sponsors'],
    ],
    'media' => [
        'label' => 'Media',
        'status' => 'module',
        'bootstrap' => __DIR__ . '/modules/media.php',
        'editor' => __DIR__ . '/media.php',
        'legacy_tabs' => ['media'],
        'legacy_formulieren' => ['media', 'media_tekst'],
    ],
    'aanmelden' => [
        'label' => 'Aanmelden',
        'status' => 'module',
        'editor' => __DIR__ . '/content.php?pagina=aanmelden',
        'legacy_tabs' => ['aanmelden'],
        'legacy_formulieren' => ['aanmelden'],
    ],
    'fotoboek' => ['label' => 'Fotoboek', 'status' => 'legacy'],
    'gebruikers' => ['label' => 'Gebruikers', 'status' => 'legacy'],
    'backups' => ['label' => 'Back-ups', 'status' => 'legacy'],
    'logboek' => ['label' => 'Logboek', 'status' => 'legacy'],
];
