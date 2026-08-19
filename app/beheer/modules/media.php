<?php
// ============================================================
// Beheermodule: Media
// ============================================================

function beheerMediaBewaakLegacyPost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    $formulier = isset($_POST['formulier']) && is_string($_POST['formulier']) ? $_POST['formulier'] : '';
    if (!in_array($formulier, ['media', 'media_tekst'], true)) return;

    $_POST['formulier'] = '';
    if (function_exists('schrijfLog') && isset($GLOBALS['logBestand'], $GLOBALS['huidigeGebruiker'])) {
        schrijfLog($GLOBALS['logBestand'], (string) $GLOBALS['huidigeGebruiker'], 'legacy_media_geblokkeerd', $formulier);
    }
}

function beheerMediaMagOpenen(): bool
{
    if (empty($GLOBALS['ingelogd'])) return false;
    if (!empty($GLOBALS['isMaster'])) return true;
    $tabs = isset($GLOBALS['toegestaneTabs']) && is_array($GLOBALS['toegestaneTabs']) ? $GLOBALS['toegestaneTabs'] : [];
    return in_array('media', $tabs, true);
}

function beheerMediaStartOutputFilter(): void
{
    ob_start(function ($html) {
        if (!is_string($html)) return $html;

        if (beheerMediaMagOpenen()) {
            $html = preg_replace(
                '~<button\s+type="button"\s+class="menu-item"\s+data-tab="media">.*?</button>~is',
                '<a class="menu-item menu-item-link" style="display:block;text-decoration:none" href="media.php">* Media</a>',
                $html,
                1
            ) ?? $html;
        }

        if (stripos($html, '</head>') !== false) {
            $css = '<style id="beheer-media-legacy-hidden">#tab-media,[href="#tab-media"],[href="#media"],[data-tab-target="media"]{display:none!important}</style>';
            $html = preg_replace('~</head>~i', $css . "\n</head>", $html, 1) ?? $html;
        }
        return $html;
    });
}

beheerMediaBewaakLegacyPost();
beheerMediaStartOutputFilter();
