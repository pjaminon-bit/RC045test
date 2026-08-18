<?php
// ============================================================
// Centrale moduledefinities voor het verenigingsplatform
// ============================================================
// Eén bron van waarheid voor de koppeling tussen feature flags, publieke
// pagina's/secties, beheertabs en POST-formulieren. De actieve status zelf
// blijft per vereniging in site-config.php staan.
// ============================================================

return [
    'fotoboek' => [
        'label' => 'Fotoboek',
        'publieke_paginas' => ['fotoboek'],
        'publieke_links' => ['fotoboek.html'],
        'beheer_tabs' => ['fotoboek'],
        'beheer_formulieren' => [
            'fotoboek_tekst',
            'fotoboek_album_aanmaken',
            'fotoboek_album_bewerken',
        ],
        'publieke_selectors' => [],
    ],
    'media' => [
        'label' => 'Media',
        'publieke_paginas' => ['media'],
        'publieke_links' => ['media.html'],
        'beheer_tabs' => ['media'],
        'beheer_formulieren' => ['media', 'media_tekst'],
        'publieke_selectors' => [],
    ],
    'aanmelden' => [
        'label' => 'Aanmelden',
        'publieke_paginas' => ['aanmelden'],
        'publieke_links' => ['aanmelden.html'],
        'beheer_tabs' => ['aanmelden'],
        'beheer_formulieren' => ['aanmelden'],
        'publieke_selectors' => [],
        'verberg_nav_lid' => true,
    ],
    'evenementen' => [
        'label' => 'Evenementen',
        'publieke_paginas' => [],
        'publieke_links' => [],
        'beheer_tabs' => ['agenda'],
        'beheer_formulieren' => ['agenda'],
        'publieke_selectors' => [
            '#activiteiten',
            'a[href="index.html#activiteiten"]',
            'a[href="#activiteiten"]',
            '#footer-link-calendar',
        ],
    ],
    'sponsors' => [
        'label' => 'Sponsors',
        'publieke_paginas' => [],
        'publieke_links' => [],
        'beheer_tabs' => ['sponsors'],
        'beheer_formulieren' => ['sponsors'],
        'publieke_selectors' => [
            '.footer-sponsors',
            '#footer-link-sponsor',
            '#sponsors-grid',
            '#footer-sponsors-title',
            '#footer-sponsors-cta',
        ],
    ],
];
