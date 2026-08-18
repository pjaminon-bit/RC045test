<?php
// ============================================================
// Beheermodule: Sponsors
// ============================================================
// Fase 2: haalt de actieve Sponsors-beheerroute uit het monolithische
// beheer.php. De oude tab blijft fysiek nog aanwezig als dode code, maar is
// niet meer zichtbaar en de oude POST-route wordt server-side geblokkeerd.
// ============================================================

function beheerSponsorsBewaakLegacyPost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    $formulier = isset($_POST['formulier']) && is_string($_POST['formulier']) ? $_POST['formulier'] : '';
    if ($formulier !== 'sponsors') return;

    $_POST['formulier'] = '';
    if (function_exists('schrijfLog') && isset($GLOBALS['logBestand'], $GLOBALS['huidigeGebruiker'])) {
        schrijfLog(
            $GLOBALS['logBestand'],
            (string) $GLOBALS['huidigeGebruiker'],
            'legacy_sponsors_geblokkeerd',
            'sponsors'
        );
    }
}

function beheerSponsorsMagOpenen(): bool
{
    if (empty($GLOBALS['ingelogd'])) return false;
    if (!empty($GLOBALS['isMaster'])) return true;
    $tabs = isset($GLOBALS['toegestaneTabs']) && is_array($GLOBALS['toegestaneTabs'])
        ? $GLOBALS['toegestaneTabs']
        : [];
    return in_array('sponsors', $tabs, true);
}

function beheerSponsorsStartOutputFilter(): void
{
    ob_start(function ($html) {
        if (!is_string($html)) return $html;

        if (stripos($html, '</head>') !== false) {
            $css = '<style id="beheer-sponsors-legacy-hidden">'
                . '#tab-sponsors,[href="#tab-sponsors"],[href="#sponsors"],'
                . '[data-tab="sponsors"],[data-tab-target="sponsors"]'
                . '{display:none!important}</style>';
            $html = preg_replace('~</head>~i', $css . "\n</head>", $html, 1) ?? $html;
        }

        if (beheerSponsorsMagOpenen() && stripos($html, '</body>') !== false && strpos($html, 'id="beheer-sponsors-module-link"') === false) {
            $link = '<a id="beheer-sponsors-module-link" href="beheer/sponsors.php" '
                . 'style="position:fixed;left:18px;bottom:18px;z-index:9999;background:#3a7a77;color:#fff;text-decoration:none;'
                . 'font:700 14px system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;padding:11px 15px;border-radius:10px;'
                . 'box-shadow:0 8px 24px rgba(0,0,0,.18)">Sponsors beheren →</a>';
            $html = preg_replace('~</body>~i', $link . "\n</body>", $html, 1) ?? $html;
        }

        return $html;
    });
}

beheerSponsorsBewaakLegacyPost();
beheerSponsorsStartOutputFilter();
