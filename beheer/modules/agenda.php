<?php
// ============================================================
// Beheermodule: Agenda
// ============================================================
// Schakelt de historische Agenda-tab en POST-route in beheer.php uit en
// gebruikt het bestaande beheer-menu als ingang naar de modulaire editor.
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

        if (beheerAgendaMagOpenen()) {
            $html = preg_replace(
                '~<button\s+type="button"\s+class="menu-item"\s+data-tab="agenda">.*?</button>~is',
                '<a class="menu-item menu-item-link" href="beheer/agenda.php">Agenda</a>',
                $html,
                1
            ) ?? $html;
        }

        if (stripos($html, '</head>') !== false) {
            $css = '<style id="beheer-agenda-legacy-hidden">#tab-agenda,[href="#tab-agenda"],[href="#agenda"],[data-tab-target="agenda"]{display:none!important}</style>';
            $html = preg_replace('~</head>~i', $css . "\n</head>", $html, 1) ?? $html;
        }
        return $html;
    });
}

beheerAgendaBewaakLegacyPost();
beheerAgendaStartOutputFilter();
