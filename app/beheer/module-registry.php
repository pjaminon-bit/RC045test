<?php
// ============================================================
// Beheer module-registry
// ============================================================

$beheerMap = dirname(__DIR__, 2) . '/beheer';

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
        'editor' => $beheerMap . '/content.php',
        'legacy_tabs' => ['homepage', 'ontstaan', 'baanreglement', 'aanmelden'],
        'legacy_formulieren' => ['homepage', 'ontstaan', 'baanreglement', 'aanmelden'],
    ],
    'homepage' => [
        'label' => 'Homepage',
        'status' => 'module',
        'editor' => $beheerMap . '/content.php?pagina=homepage',
    ],
    'bedankt' => [
        'label' => 'Bedankt-pagina',
        'status' => 'module',
        'bootstrap' => __DIR__ . '/modules/bedankt.php',
        'editor' => $beheerMap . '/bedankt.php',
        'legacy_tabs' => ['bedankt'],
        'legacy_formulieren' => ['bedankt'],
    ],
    'agenda' => [
        'label' => 'Agenda',
        'status' => 'module',
        'bootstrap' => __DIR__ . '/modules/agenda.php',
        'editor' => $beheerMap . '/agenda.php',
        'legacy_tabs' => ['agenda'],
        'legacy_formulieren' => ['agenda'],
    ],
    'sponsors' => [
        'label' => 'Sponsors',
        'status' => 'module',
        'bootstrap' => __DIR__ . '/modules/sponsors.php',
        'editor' => $beheerMap . '/sponsors.php',
        'legacy_tabs' => ['sponsors'],
        'legacy_formulieren' => ['sponsors'],
    ],
    'media' => [
        'label' => 'Media',
        'status' => 'module',
        'bootstrap' => __DIR__ . '/modules/media.php',
        'editor' => $beheerMap . '/media.php',
        'legacy_tabs' => ['media'],
        'legacy_formulieren' => ['media', 'media_tekst'],
    ],
    'aanmelden' => [
        'label' => 'Aanmelden',
        'status' => 'module',
        'editor' => $beheerMap . '/content.php?pagina=aanmelden',
        'legacy_tabs' => ['aanmelden'],
        'legacy_formulieren' => ['aanmelden'],
    ],
    'fotoboek' => [
        'label' => 'Fotoboek',
        'status' => 'module',
        'bootstrap' => __DIR__ . '/modules/fotoboek.php',
        'editor' => $beheerMap . '/fotoboek.php',
        'legacy_tabs' => ['fotoboek'],
        'legacy_formulieren' => ['fotoboek_tekst', 'fotoboek_album_aanmaken', 'fotoboek_album_bewerken'],
    ],
    'gebruikers' => [
        'label' => 'Gebruikers',
        'status' => 'module',
        'bootstrap' => __DIR__ . '/modules/gebruikers.php',
        'editor' => $beheerMap . '/gebruikers.php',
        'legacy_tabs' => ['gebruikers'],
        'legacy_formulieren' => ['gebruiker_toevoegen', 'gebruiker_tabs_bijwerken', 'gebruiker_verwijderen'],
    ],
    'backups' => [
        'label' => 'Back-ups',
        'status' => 'module',
        'bootstrap' => __DIR__ . '/modules/backups.php',
        'editor' => $beheerMap . '/backups.php',
        'legacy_tabs' => ['backups'],
        'legacy_formulieren' => ['backup_herstellen'],
    ],
    'logboek' => [
        'label' => 'Logboek',
        'status' => 'module',
        'bootstrap' => __DIR__ . '/modules/logboek.php',
        'editor' => $beheerMap . '/logboek.php',
        'legacy_tabs' => ['log'],
    ],
];
