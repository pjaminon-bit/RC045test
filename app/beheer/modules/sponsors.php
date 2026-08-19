<?php
// ============================================================
// Beheermodule: Sponsors
// ============================================================

function beheerSponsorsBewaakLegacyPost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    $formulier = isset($_POST['formulier']) && is_string($_POST['formulier']) ? $_POST['formulier'] : '';
    if ($formulier !== 'sponsors') return;

    $_POST['formulier'] = '';
    if (function_exists('schrijfLog') && isset($GLOBALS['logBestand'], $GLOBALS['huidigeGebruiker'])) {
        schrijfLog($GLOBALS['logBestand'], (string) $GLOBALS['huidigeGebruiker'], 'legacy_sponsors_geblokkeerd', 'sponsors');
    }
}

function beheerSponsorsMagOpenen(): bool
{
    if (empty($GLOBALS['ingelogd'])) return false;
    if (!empty($GLOBALS['isMaster'])) return true;
    $tabs = isset($GLOBALS['toegestaneTabs']) && is_array($GLOBALS['toegestaneTabs']) ? $GLOBALS['toegestaneTabs'] : [];
    return in_array('sponsors', $tabs, true);
}

function beheerSponsorsStartOutputFilter(): void
{
    ob_start(function ($html) {
        if (!is_string($html)) return $html;

        if (beheerSponsorsMagOpenen()) {
            $html = preg_replace(
                '~<button\s+type="button"\s+class="menu-item"\s+data-tab="sponsors">.*?</button>~is',
                '<a class="menu-item menu-item-link" style="display:block;text-decoration:none" href="sponsors.php">* Sponsors</a>',
                $html,
                1
            ) ?? $html;
        }

        if (stripos($html, '</head>') !== false) {
            $css = '<style id="beheer-sponsors-legacy-hidden">#tab-sponsors,[href="#tab-sponsors"],[href="#sponsors"],[data-tab-target="sponsors"]{display:none!important}</style>';
            $html = preg_replace('~</head>~i', $css . "\n</head>", $html, 1) ?? $html;
        }

        return $html;
    });
}

beheerSponsorsBewaakLegacyPost();
beheerSponsorsStartOutputFilter();
