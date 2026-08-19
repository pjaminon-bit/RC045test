<?php
// ============================================================
// Beheermodule: Bedankt-pagina
// ============================================================

function beheerBedanktMagOpenen(): bool
{
    if (function_exists('siteModuleActief') && !siteModuleActief('aanmelden')) return false;
    if (empty($GLOBALS['ingelogd'])) return false;
    if (!empty($GLOBALS['isMaster'])) return true;
    $tabs = isset($GLOBALS['toegestaneTabs']) && is_array($GLOBALS['toegestaneTabs']) ? $GLOBALS['toegestaneTabs'] : [];
    return in_array('bedankt', $tabs, true);
}

function beheerBedanktBewaakLegacyPost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    $formulier = isset($_POST['formulier']) && is_string($_POST['formulier']) ? $_POST['formulier'] : '';
    if ($formulier !== 'bedankt') return;

    $_POST['formulier'] = '';
    if (function_exists('schrijfLog') && isset($GLOBALS['logBestand'], $GLOBALS['huidigeGebruiker'])) {
        schrijfLog($GLOBALS['logBestand'], (string) $GLOBALS['huidigeGebruiker'], 'legacy_bedankt_geblokkeerd', 'bedankt');
    }
}

function beheerBedanktStartOutputFilter(): void
{
    ob_start(function ($html) {
        if (!is_string($html)) return $html;
        if (beheerBedanktMagOpenen()) {
            $html = preg_replace(
                '~<button\s+type="button"\s+class="menu-item"\s+data-tab="bedankt">.*?</button>~is',
                '<a class="menu-item menu-item-link" style="display:block;text-decoration:none" href="bedankt.php">* Bedankt-pagina</a>',
                $html,
                1
            ) ?? $html;
        }
        if (stripos($html, '</head>') !== false) {
            $css = '<style id="beheer-bedankt-legacy-hidden">#tab-bedankt,[href="#tab-bedankt"],[href="#bedankt"],[data-tab-target="bedankt"]{display:none!important}</style>';
            $html = preg_replace('~</head>~i', $css . "\n</head>", $html, 1) ?? $html;
        }
        return $html;
    });
}

beheerBedanktBewaakLegacyPost();
beheerBedanktStartOutputFilter();
