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
        'legacy_tabs' => ['ontstaan', 'baanreglement'],
        'legacy_formulieren' => ['ontstaan', 'baanreglement'],
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
    'media' => ['label' => 'Media', 'status' => 'legacy'],
    'fotoboek' => ['label' => 'Fotoboek', 'status' => 'legacy'],
    'aanmelden' => ['label' => 'Aanmelden', 'status' => 'legacy'],
    'homepage' => ['label' => 'Homepage', 'status' => 'legacy'],
    'gebruikers' => ['label' => 'Gebruikers', 'status' => 'legacy'],
    'backups' => ['label' => 'Back-ups', 'status' => 'legacy'],
    'logboek' => ['label' => 'Logboek', 'status' => 'legacy'],
];
