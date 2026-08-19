<?php
// ============================================================
// Beheermodule: Logboek
// ============================================================

function beheerLogboekMagOpenen(): bool
{
    if (empty($GLOBALS['ingelogd'])) return false;
    if (!empty($GLOBALS['isMaster'])) return true;
    $tabs = isset($GLOBALS['toegestaneTabs']) && is_array($GLOBALS['toegestaneTabs']) ? $GLOBALS['toegestaneTabs'] : [];
    return in_array('log', $tabs, true);
}

function beheerLogboekStartOutputFilter(): void
{
    ob_start(function ($html) {
        if (!is_string($html)) return $html;

        if (beheerLogboekMagOpenen()) {
            // Logboek staat inmiddels als zelfstandige modulelink in het
            // beheer-menu. Markeer gemigreerde modules zichtbaar met "* ".
            $html = preg_replace(
                '~<a\s+class="menu-module-link"\s+href="logboek\.php">\s*\*?\s*Logboek\s*</a>~is',
                '<a class="menu-module-link" href="logboek.php">* Logboek</a>',
                $html,
                1
            ) ?? $html;
        }

        if (stripos($html, '</head>') !== false) {
            $css = '<style id="beheer-logboek-legacy-hidden">#tab-log,[href="#tab-log"],[href="#log"],[data-tab-target="log"]{display:none!important}</style>';
            $html = preg_replace('~</head>~i', $css . "\n</head>", $html, 1) ?? $html;
        }
        return $html;
    });
}

beheerLogboekStartOutputFilter();
