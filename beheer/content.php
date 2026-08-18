<?php
// ============================================================
// Canonieke modulaire beheerroute: Contentpagina's
// ============================================================
// Fase 2 compatibiliteitsroute. De generieke editorlogica staat voorlopig
// nog in ../content-beheer.php, omdat die in fase 1 ook als helperbestand is
// ontstaan. Deze route zorgt er nu al voor dat alle zelfstandige editors
// consequent onder /beheer/ bereikbaar zijn.
//
// Zodra de helper- en renderlaag van content-beheer.php worden opgesplitst,
// verhuist de implementatie fysiek naar beheer/ en kan deze wrapper weg.
// ============================================================

require_once dirname(__DIR__) . '/auth.php';

if (!$ingelogd) {
    header('Location: ../beheer.php');
    exit;
}

// content-beheer.php voert zijn editor alleen uit wanneer het zichzelf als
// rechtstreeks aangeroepen script ziet. Voor deze canonieke route laten we
// hem daarom tijdelijk die scriptnaam zien. Dit verandert alleen de interne
// detectie; de browser blijft gewoon op /beheer/content.php staan.
$origineelScriptBestand = $_SERVER['SCRIPT_FILENAME'] ?? null;
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__) . '/content-beheer.php';

// De bestaande editor bevat nog links die relatief aan de webroot zijn
// geschreven. Omdat deze route één map dieper staat, zetten we een base-tag
// op de root van de DEV/installatie. Formulieren zonder action blijven naar
// het huidige document posten.
ob_start(static function ($html) {
    if (!is_string($html)) return $html;
    if (stripos($html, '<head>') !== false && stripos($html, '<base ') === false) {
        $html = preg_replace('~<head>~i', "<head>\n<base href=\"../\">", $html, 1) ?? $html;
    }
    return $html;
});

require dirname(__DIR__) . '/content-beheer.php';

if ($origineelScriptBestand === null) unset($_SERVER['SCRIPT_FILENAME']);
else $_SERVER['SCRIPT_FILENAME'] = $origineelScriptBestand;
