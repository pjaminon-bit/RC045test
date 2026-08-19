<?php
// ============================================================
// Tijdelijke runtime-koppeling voor legacy contentpagina's
// ============================================================
// Fase 1D: zolang ontstaan.php en baanreglement.php nog hun historische HTML
// en JavaScript bevatten, laat deze outputfilter de centrale paginaregistry
// al de hero en het data-endpoint bepalen. Zodra beide pagina's op generieke
// renderers draaien kan deze compatibiliteitslaag verdwijnen.
// ============================================================

function contentPaginaRuntimeStart(): void
{
    static $gestart = false;
    if ($gestart) return;

    $sleutel = contentPaginaSleutelVoorRequest();
    if ($sleutel === null || !contentPaginaBestaat($sleutel)) return;

    $bootstrap = contentPaginaBootstrap($sleutel);
    $definitie = $bootstrap['definitie'] ?? [];
    if (!is_array($definitie) || empty($definitie['legacy_layout'])) return;

    $gestart = true;

    ob_start(function ($html) use ($sleutel, $bootstrap) {
        if (!is_string($html) || $html === '') return $html;

        // Hero komt uit pagina-definities.php. Deze style wordt na de
        // bestaande pagina-CSS toegevoegd en wint daarom zonder legacy CSS te
        // hoeven verwijderen.
        $heroCss = contentPaginaHeroCss($sleutel);
        if ($heroCss !== '' && stripos($html, '</head>') !== false && strpos($html, 'id="content-page-hero"') === false) {
            $style = '<style id="content-page-hero">' . $heroCss . '</style>';
            $html = preg_replace('~</head>~i', $style . "\n</head>", $html, 1) ?? $html;
        }

        // Ook het JSON-endpoint komt uit de registry. De bestaande JS-renderer
        // en Nederlandse fallbacktekst blijven daardoor volledig intact.
        $dataBestand = trim((string) (($bootstrap['definitie']['data_bestand'] ?? '')));
        if ($dataBestand !== '') {
            $veiligPad = str_replace(['\\', "'"], ['/', "\\'"], ltrim($dataBestand, '/'));
            $html = preg_replace(
                "~fetch\\('data/" . preg_quote($sleutel, '~') . "\\.json'~",
                "fetch('" . $veiligPad . "'",
                $html
            ) ?? $html;
        }

        // Markering voor inspectie/debugging zonder visuele impact.
        if (stripos($html, '<body') !== false && strpos($html, 'data-content-page=') === false) {
            $html = preg_replace('~<body([^>]*)>~i', '<body$1 data-content-page="' . htmlspecialchars($sleutel, ENT_QUOTES, 'UTF-8') . '" data-content-type="' . htmlspecialchars((string) ($bootstrap['type'] ?? ''), ENT_QUOTES, 'UTF-8') . '">', $html, 1) ?? $html;
        }

        return $html;
    });
}

contentPaginaRuntimeStart();
