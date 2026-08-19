<?php
// ============================================================
// Beheermodule: Gebruikers
// ============================================================
// Verbergt de historische gebruikers-tab en stuurt het menu naar de nieuwe
// zelfstandige pagina. Oude POST-routes worden geblokkeerd zodra de module
// actief is, zodat er maar één autorisatie-/opslagroute overblijft.
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
            $html = preg_replace(
                '~<button\s+type="button"\s+class="menu-item"\s+data-tab="gebruikers">.*?</button>~is',
                '<a class="menu-item menu-item-link" style="display:block;text-decoration:none" href="beheer/gebruikers.php">Gebruikers</a>',
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
