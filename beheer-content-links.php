<?php
// ============================================================
// Ingang naar de generieke contenteditor vanuit beheer.php
// ============================================================
// Wordt via paneel-hulp.php alleen in beheer.php geladen. De callback draait
// pas wanneer de volledige beheerpagina is opgebouwd; daardoor zijn de
// berekende rechten ($toegestaneTabs / $isMaster) beschikbaar zonder de
// grote beheer.php-template zelf te hoeven herschrijven.
// ============================================================

require_once __DIR__ . '/content-pagina.php';

function beheerContentLinksMarkup(): string
{
    $toegestaneTabs = isset($GLOBALS['toegestaneTabs']) && is_array($GLOBALS['toegestaneTabs'])
        ? $GLOBALS['toegestaneTabs']
        : [];
    $isMaster = !empty($GLOBALS['isMaster']);
    $ingelogd = !empty($GLOBALS['ingelogd']);

    if (!$ingelogd) return '';

    $links = [];
    foreach (contentPaginaDefinities() as $sleutel => $def) {
        if (!is_array($def)) continue;
        $tab = contentPaginaBeheerTab((string) $sleutel);
        if (!$isMaster && !in_array($tab, $toegestaneTabs, true)) continue;

        $label = trim((string) ($def['label'] ?? $sleutel));
        $type = trim((string) ($def['type'] ?? 'standaard'));
        $url = 'content-beheer.php?pagina=' . rawurlencode((string) $sleutel);
        $links[] = [
            'label' => $label !== '' ? $label : (string) $sleutel,
            'type' => $type !== '' ? $type : 'standaard',
            'url' => $url,
        ];
    }

    if (!$links) return '';

    $html = '<section id="generieke-contentpaginas" style="max-width:1180px;margin:24px auto;padding:0 24px">';
    $html .= '<div style="background:#fff;border:1px solid #ddd8c0;border-radius:14px;padding:22px">';
    $html .= '<div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-bottom:16px">';
    $html .= '<div><h2 style="margin:0 0 6px;font-size:20px">Contentpagina\'s</h2>';
    $html .= '<p style="margin:0;color:#66705e;line-height:1.5">Deze pagina\'s gebruiken de nieuwe generieke contenteditor. De bestaande beheertabs blijven tijdens de migratie nog beschikbaar als terugval.</p></div>';
    $html .= '</div><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px">';

    foreach ($links as $link) {
        $label = htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8');
        $type = htmlspecialchars($link['type'], ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8');
        $html .= '<a href="' . $url . '" style="display:block;border:1px solid #ddd8c0;border-radius:10px;padding:15px;text-decoration:none;color:#26351d;background:#faf8f2">';
        $html .= '<strong style="display:block;margin-bottom:4px">' . $label . '</strong>';
        $html .= '<span style="font-size:12px;color:#6a7560">Type: ' . $type . ' · Open generieke editor →</span>';
        $html .= '</a>';
    }

    $html .= '</div></div></section>';
    return $html;
}

function beheerContentLinksStartOutputFilter(): void
{
    static $actief = false;
    if ($actief) return;
    $actief = true;

    ob_start(function ($html) {
        $markup = beheerContentLinksMarkup();
        if ($markup === '' || stripos($html, '</body>') === false || strpos($html, 'id="generieke-contentpaginas"') !== false) {
            return $html;
        }
        return preg_replace('~</body>~i', $markup . "\n</body>", $html, 1) ?? $html;
    });
}

beheerContentLinksStartOutputFilter();
