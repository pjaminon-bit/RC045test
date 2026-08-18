<?php
// ============================================================
// Beheermodule: Agenda
// ============================================================
// Schakelt de historische Agenda-tab en POST-route in beheer.php uit en
// biedt de ingang naar de zelfstandige modulaire agenda-editor.
// ============================================================

function beheerAgendaBewaakLegacyPost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    $formulier = isset($_POST['formulier']) && is_string($_POST['formulier']) ? $_POST['formulier'] : '';
    if ($formulier !== 'agenda') return;

    $_POST['formulier'] = '';
    if (function_exists('schrijfLog') && isset($GLOBALS['logBestand'], $GLOBALS['huidigeGebruiker'])) {
        schrijfLog($GLOBALS['logBestand'], (string) $GLOBALS['huidigeGebruiker'], 'legacy_agenda_geblokkeerd', 'agenda');
    }
}

function beheerAgendaMagOpenen(): bool
{
    if (empty($GLOBALS['ingelogd'])) return false;
    if (!empty($GLOBALS['isMaster'])) return true;
    $tabs = isset($GLOBALS['toegestaneTabs']) && is_array($GLOBALS['toegestaneTabs']) ? $GLOBALS['toegestaneTabs'] : [];
    return in_array('agenda', $tabs, true);
}

function beheerAgendaStartOutputFilter(): void
{
    ob_start(function ($html) {
        if (!is_string($html)) return $html;

        if (stripos($html, '</head>') !== false) {
            $css = '<style id="beheer-agenda-legacy-hidden">#tab-agenda,[href="#tab-agenda"],[href="#agenda"],[data-tab="agenda"],[data-tab-target="agenda"]{display:none!important}</style>';
            $html = preg_replace('~</head>~i', $css . "\n</head>", $html, 1) ?? $html;
        }

        if (beheerAgendaMagOpenen() && stripos($html, '</body>') !== false && strpos($html, 'id="beheer-agenda-module-link"') === false) {
            $link = '<a id="beheer-agenda-module-link" href="beheer/agenda.php" style="position:fixed;left:18px;bottom:72px;z-index:9999;background:#3a7a77;color:#fff;text-decoration:none;font:700 14px system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;padding:11px 15px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.18)">Agenda beheren →</a>';
            $html = preg_replace('~</body>~i', $link . "\n</body>", $html, 1) ?? $html;
        }
        return $html;
    });
}

beheerAgendaBewaakLegacyPost();
beheerAgendaStartOutputFilter();
