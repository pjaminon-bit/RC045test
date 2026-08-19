<?php
// ============================================================
// Beheermodule: Back-ups
// ============================================================

function beheerBackupsMagOpenen(): bool
{
    if (empty($GLOBALS['ingelogd'])) return false;
    if (!empty($GLOBALS['isMaster'])) return true;
    return function_exists('authHeeftExplicietRecht') && authHeeftExplicietRecht('backups');
}

function beheerBackupsBewaakLegacyPost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    $formulier = isset($_POST['formulier']) && is_string($_POST['formulier']) ? $_POST['formulier'] : '';
    if ($formulier !== 'backup_herstellen') return;
    $_POST['formulier'] = '';
    if (function_exists('schrijfLog') && isset($GLOBALS['logBestand'], $GLOBALS['huidigeGebruiker'])) {
        schrijfLog($GLOBALS['logBestand'], (string)$GLOBALS['huidigeGebruiker'], 'legacy_backup_geblokkeerd', 'backup_herstellen');
    }
}

function beheerBackupsStartOutputFilter(): void
{
    ob_start(function ($html) {
        if (!is_string($html)) return $html;
        if (beheerBackupsMagOpenen()) {
            $html = preg_replace(
                '~<button\s+type="button"\s+class="menu-item"\s+data-tab="backups">.*?</button>~is',
                '<a class="menu-item menu-item-link" style="display:block;text-decoration:none" href="backups.php">* Back-ups</a>',
                $html,
                1
            ) ?? $html;
            $html = preg_replace(
                '~(<a\s+class="menu-module-link"\s+href="backups\.php">)(?!\*\s*)(.*?</a>)~is',
                '$1* $2',
                $html,
                1
            ) ?? $html;
        }
        if (stripos($html, '</head>') !== false) {
            $css = '<style id="beheer-backups-legacy-hidden">#tab-backups,[href="#tab-backups"],[href="#backups"],[data-tab-target="backups"]{display:none!important}</style>';
            $html = preg_replace('~</head>~i', $css . "\n</head>", $html, 1) ?? $html;
        }
        return $html;
    });
}

beheerBackupsBewaakLegacyPost();
beheerBackupsStartOutputFilter();
