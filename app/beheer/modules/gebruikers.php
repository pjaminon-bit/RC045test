<?php
// ============================================================
// Beheermodule: Gebruikers
// ============================================================

function beheerGebruikersBewaakLegacyPost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    $formulier = isset($_POST['formulier']) && is_string($_POST['formulier']) ? $_POST['formulier'] : '';
    if (!in_array($formulier, ['gebruiker_toevoegen', 'gebruiker_tabs_bijwerken', 'gebruiker_verwijderen'], true)) return;

    $_POST['formulier'] = '';
    if (function_exists('schrijfLog') && isset($GLOBALS['logBestand'], $GLOBALS['huidigeGebruiker'])) {
        schrijfLog($GLOBALS['logBestand'], (string)$GLOBALS['huidigeGebruiker'], 'legacy_gebruikers_geblokkeerd', $formulier);
    }
}

function beheerGebruikersMagOpenen(): bool
{
    return function_exists('authHeeftExplicietRecht') && authHeeftExplicietRecht('gebruikers');
}

function beheerGebruikersStartOutputFilter(): void
{
    ob_start(function ($html) {
        if (!is_string($html)) return $html;

        if (beheerGebruikersMagOpenen()) {
            // Gebruikers staat inmiddels als zelfstandige modulelink in het
            // beheer-menu. Markeer gemigreerde modules zichtbaar met "* ".
            $html = preg_replace(
                '~<a\s+class="menu-module-link"\s+href="gebruikers\.php">\s*\*?\s*Gebruikers\s*</a>~is',
                '<a class="menu-module-link" href="gebruikers.php">* Gebruikers</a>',
                $html,
                1
            ) ?? $html;
        }

        if (stripos($html, '</head>') !== false) {
            $css = '<style id="beheer-gebruikers-legacy-hidden">#tab-gebruikers,[href="#tab-gebruikers"],[href="#gebruikers"],[data-tab-target="gebruikers"]{display:none!important}</style>';
            $html = preg_replace('~</head>~i', $css . "\n</head>", $html, 1) ?? $html;
        }
        return $html;
    });
}

beheerGebruikersBewaakLegacyPost();
beheerGebruikersStartOutputFilter();
