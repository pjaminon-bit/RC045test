<?php
// ============================================================
// Centrale definities voor configureerbare contentpagina's
// ============================================================
// Fase 1D: losse RC045-pagina's stapsgewijs terugbrengen naar generieke
// paginatypen. De slug/URL kan gelijk blijven terwijl inhoud en layout vanuit
// deze registry en het bijbehorende databestand worden opgebouwd.
// ============================================================

return [
    'ontstaan' => [
        'slug' => 'ontstaan',
        'label' => 'Ontstaan / geschiedenis',
        'type' => 'verhaal',
        'seo_sleutel' => 'ontstaan',
        'beheer_tab' => 'ontstaan',
        'data_bestand' => 'data/ontstaan.json',
        'hero' => [
            'achtergrond' => 'images/crawlergroen.jpg',
            'positie' => 'center',
            'opacity' => 0.35,
            'label' => [
                'nl' => 'Onze geschiedenis',
                'en' => 'Our history',
                'de' => 'Unsere Geschichte',
            ],
            'titel' => [
                'nl' => 'Het ontstaan van RC045',
                'en' => 'The story of RC045',
                'de' => 'Die Entstehung von RC045',
            ],
        ],
        'velden' => [
            'hero_sub' => ['type' => 'tekst', 'label' => 'Ondertitel boven het verhaal'],
            'story_p1' => ['type' => 'blok', 'label' => 'Alinea 1'],
            'story_p2' => ['type' => 'blok', 'label' => 'Alinea 2'],
            'story_p3' => ['type' => 'blok', 'label' => 'Alinea 3'],
            'story_p4' => ['type' => 'blok', 'label' => 'Alinea 4'],
            'story_p5' => ['type' => 'blok', 'label' => 'Alinea 5'],
            'story_p6' => ['type' => 'blok', 'label' => 'Alinea 6'],
            'story_p7' => ['type' => 'blok', 'label' => 'Alinea 7'],
        ],
        'galerij' => [
            'titel' => [
                'nl' => "Foto's door de jaren heen",
                'en' => 'Photos through the years',
                'de' => 'Fotos im Laufe der Jahre',
            ],
            'afbeeldingen' => [
                ['src' => 'images/basherbaaneersteaanleg.jpg', 'alt' => 'Eerste aanleg van de RC045 baan'],
                ['src' => 'images/basherbaaneersteaanleghek.jpg', 'alt' => 'Plaatsen van het hek tijdens de eerste aanleg'],
                ['src' => 'images/basherbaaneersteaanleglucht.jpg', 'alt' => 'Luchtfoto van de eerste aanleg van de baan'],
                ['src' => 'images/basherbaaneersteaanlegmaaien.jpg', 'alt' => 'Maaien tijdens de eerste aanleg van de baan'],
                ['src' => 'images/basherbaaneersteaanlegtrack.jpg', 'alt' => 'Aanleg van het parcours'],
                ['src' => 'images/crawlerbaaneersteaanleg.jpg', 'alt' => 'Eerste aanleg van de crawlerbaan'],
            ],
        ],
        'legacy_layout' => false,
    ],

    'baanreglement' => [
        'slug' => 'baanreglement',
        'label' => 'Reglement',
        'type' => 'artikelen',
        'seo_sleutel' => 'baanreglement',
        'beheer_tab' => 'baanreglement',
        'data_bestand' => 'data/baanreglement.json',
        'hero' => [
            'achtergrond' => 'images/hero-achtergrond.jpg',
            'positie' => 'center',
            'opacity' => 0.35,
        ],
        'velden' => [
            'hero_sub' => ['type' => 'tekst', 'label' => 'Ondertitel boven de pagina'],
            'intro_bold' => ['type' => 'tekst', 'label' => 'Vet woord vooraan de introtekst'],
            'intro_text' => ['type' => 'blok', 'label' => 'Introtekst'],
        ],
        'artikelen' => [
            1 => ['titel' => 'a1_title', 'inhoud' => 'a1_body'],
            2 => ['titel' => 'a2_title', 'inhoud' => 'a2_body'],
            3 => ['titel' => 'a3_title', 'inhoud' => 'a3_body'],
            4 => ['titel' => 'a4_title', 'inhoud' => 'a4_body'],
            5 => ['titel' => 'a5_title', 'inhoud' => 'a5_body'],
            6 => ['titel' => 'a6_title', 'inhoud' => 'a6_body'],
            7 => ['titel' => 'a7_title', 'inhoud' => 'a7_body'],
            8 => ['titel' => 'a8_title', 'inhoud' => 'a8_body'],
            9 => ['titel' => 'a9_title', 'inhoud' => 'a9_body'],
            10 => ['titel' => 'a10_title', 'inhoud' => 'a10_body'],
        ],
        'legacy_layout' => true,
    ],
];
