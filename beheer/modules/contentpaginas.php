<?php
// ============================================================
// Beheermodule: generieke contentpagina's
// ============================================================
// Eerste echte fase-2 module. Verzorgt de ingang naar content-beheer.php en
// schakelt de oude Ontstaan/Baanreglement-tabs + POST-routes uit.
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

function beheerContentMarkup(): string
{
    if (empty($GLOBALS['ingelogd'])) return '';

    $toegestaneTabs = isset($GLOBALS['toegestaneTabs']) && is_array($GLOBALS['toegestaneTabs'])
        ? $GLOBALS['toegestaneTabs']
        : [];
    $isMaster = !empty($GLOBALS['isMaster']);
    $links = [];

    foreach (contentPaginaDefinities() as $sleutel => $def) {
        if (!is_array($def)) continue;
        $tab = contentPaginaBeheerTab((string) $sleutel);
        if (!$isMaster && !in_array($tab, $toegestaneTabs, true)) continue;
        $label = htmlspecialchars((string) ($def['label'] ?? $sleutel), ENT_QUOTES, 'UTF-8');
        $url = 'content-beheer.php?pagina=' . rawurlencode((string) $sleutel);
        $links[] = '<a style="display:block;padding:9px 11px;background:#eaf4f3;color:#2d6260;border-radius:8px;text-decoration:none;font-weight:700" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $label . '</a>';
    }

    if (!$links) return '';
    return '<aside id="generieke-content-snelkoppelingen" style="position:fixed;right:18px;bottom:18px;z-index:9999;width:min(320px,calc(100vw - 36px));background:#fff;border:1px solid #d9d3bd;border-radius:12px;box-shadow:0 12px 36px rgba(0,0,0,.14);padding:16px;font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif">'
        . '<div style="font-weight:800;color:#26351d;margin-bottom:6px">Contentpagina\'s</div>'
        . '<div style="font-size:12px;color:#6a7560;line-height:1.45;margin-bottom:10px">Generieke contenteditor.</div>'
        . '<div style="display:flex;flex-direction:column;gap:7px">' . implode('', $links) . '</div></aside>';
}

function beheerContentStartOutputFilter(): void
{
    ob_start(function ($html) {
        if (!is_string($html)) return $html;

        foreach (beheerContentLegacyTabs() as $tab) {
            $veilig = preg_quote($tab, '~');
            $html = preg_replace('~(<[^>]+(?:id="tab-' . $veilig . '"|data-tab="' . $veilig . '"|href="#tab-' . $veilig . '")[^>]*>)~i', '$1', $html) ?? $html;
        }

        $selectors = [];
        foreach (beheerContentLegacyTabs() as $tab) {
            $selectors[] = '#tab-' . $tab;
            $selectors[] = '[href="#tab-' . $tab . '"]';
            $selectors[] = '[data-tab="' . $tab . '"]';
            $selectors[] = '[data-tab-target="' . $tab . '"]';
        }
        if ($selectors && stripos($html, '</head>') !== false) {
            $css = '<style id="beheer-content-legacy-hidden">' . implode(',', $selectors) . '{display:none!important}</style>';
            $html = preg_replace('~</head>~i', $css . "\n</head>", $html, 1) ?? $html;
        }

        $markup = beheerContentMarkup();
        if ($markup !== '' && stripos($html, '</body>') !== false && strpos($html, 'id="generieke-content-snelkoppelingen"') === false) {
            $html = preg_replace('~</body>~i', $markup . "\n</body>", $html, 1) ?? $html;
        }
        return $html;
    });
}

beheerContentBewaakLegacyPost();
beheerContentStartOutputFilter();
