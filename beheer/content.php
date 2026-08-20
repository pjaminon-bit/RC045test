<?php
// ============================================================
// Canonieke modulaire beheerroute: Contentpagina's
// ============================================================
// De generieke editorlogica staat in app/content/content-beheer.php.
// Deze route houdt de publieke beheer-URL consequent onder /beheer/.
// ============================================================

require_once dirname(__DIR__) . '/auth.php';

if (!$ingelogd) {
    header('Location: ../beheer.php');
    exit;
}

// De editor voert zijn zelfstandige UI alleen uit wanneer SCRIPT_FILENAME
// eindigt op content-beheer.php. Laat hem die interne implementatienaam zien;
// de browser blijft gewoon op /beheer/content.php staan.
$origineelScriptBestand = $_SERVER['SCRIPT_FILENAME'] ?? null;
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__) . '/app/content/content-beheer.php';

// De editor bevat nog links die relatief aan de webroot zijn geschreven.
// Omdat deze route één map dieper staat, zetten we een base-tag op de root
// van de huidige installatie. Formulieren zonder action posten naar de
// huidige /beheer/content.php-route. De 2026-beheerlaag wordt als laatste
// stylesheet toegevoegd zodat oudere inline editor-CSS veilig kan blijven.
ob_start(static function ($html) {
    if (!is_string($html)) return $html;
    if (stripos($html, '<head>') !== false && stripos($html, '<base ') === false) {
        $html = preg_replace('~<head>~i', "<head>\n<base href=\"../\">", $html, 1) ?? $html;
    }
    if (stripos($html, '</head>') !== false && stripos($html, 'beheer/ui-2026.css') === false) {
        $html = preg_replace('~</head>~i', '<link rel="stylesheet" href="beheer/ui-2026.css"></head>', $html, 1) ?? $html;
    }
    return $html;
});

require dirname(__DIR__) . '/app/content/content-beheer.php';

if ($origineelScriptBestand === null) unset($_SERVER['SCRIPT_FILENAME']);
else $_SERVER['SCRIPT_FILENAME'] = $origineelScriptBestand;
