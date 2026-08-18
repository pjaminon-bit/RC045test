<?php
// ============================================================
// Beheermodule: generieke contentpagina's
// ============================================================
// Verzorgt de menu-ingang naar /beheer/content.php en schakelt de oude
// Ontstaan/Baanreglement-tabs + POST-routes uit.
// ============================================================

require_once dirname(__DIR__, 2) . '/content-pagina.php';

function beheerContentLegacyTabs(): array
{
    $def = beheerModuleDefinitie('contentpaginas');
    return array_values(array_filter((array) ($def['legacy_tabs'] ?? []), 'is_string'));
}

function beheerContentLegacyFormulieren(): array
{
    $def = beheerModuleDefinitie('contentpaginas');
    return array_values(array_filter((array) ($def['legacy_formulieren'] ?? []), 'is_string'));
}

function beheerContentBewaakLegacyPost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    $formulier = isset($_POST['formulier']) && is_string($_POST['formulier']) ? $_POST['formulier'] : '';
    if ($formulier === '' || !in_array($formulier, beheerContentLegacyFormulieren(), true)) return;

    $_POST['formulier'] = '';
    if (function_exists('schrijfLog') && isset($GLOBALS['logBestand'], $GLOBALS['huidigeGebruiker'])) {
        schrijfLog(
            $GLOBALS['logBestand'],
            (string) $GLOBALS['huidigeGebruiker'],
            'legacy_content_geblokkeerd',
            $formulier
        );
    }
}

function beheerContentVervangMenuItems(string $html): string
{
    $koppelingen = [
        'ontstaan' => ['pagina' => 'ontstaan', 'label' => 'Ontstaan / geschiedenis'],
        'baanreglement' => ['pagina' => 'baanreglement', 'label' => 'Reglement'],
    ];

    foreach ($koppelingen as $tab => $info) {
        $patroon = '~<button\s+type="button"\s+class="menu-item"\s+data-tab="' . preg_quote($tab, '~') . '">.*?</button>~is';
        $link = '<a class="menu-item menu-item-link" style="display:block;text-decoration:none" href="beheer/content.php?pagina=' . rawurlencode($info['pagina']) . '">'
            . htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') . '</a>';
        $html = preg_replace($patroon, $link, $html, 1) ?? $html;
    }

    return $html;
}

function beheerContentStartOutputFilter(): void
{
    ob_start(function ($html) {
        if (!is_string($html)) return $html;

        $html = beheerContentVervangMenuItems($html);

        // Alleen de oude inhoudspanelen verbergen. De menu-items zelf zijn
        // hierboven vervangen door links naar de modulaire editor.
        $selectors = [];
        foreach (beheerContentLegacyTabs() as $tab) {
            $selectors[] = '#tab-' . $tab;
            $selectors[] = '[href="#tab-' . $tab . '"]';
            $selectors[] = '[data-tab-target="' . $tab . '"]';
        }
        if ($selectors && stripos($html, '</head>') !== false) {
            $css = '<style id="beheer-content-legacy-hidden">' . implode(',', $selectors) . '{display:none!important}</style>';
            $html = preg_replace('~</head>~i', $css . "\n</head>", $html, 1) ?? $html;
        }

        return $html;
    });
}

beheerContentBewaakLegacyPost();
beheerContentStartOutputFilter();
