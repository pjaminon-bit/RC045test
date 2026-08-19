<?php
// ============================================================
// Modulekoppeling voor afgeschermde ledenpaneel
// ============================================================

function paneelModuleConfig(): array
{
    static $config = null;
    if ($config === null) {
        $geladen = require __DIR__ . '/site-config.php';
        $config = is_array($geladen) ? $geladen : [];
    }
    return $config;
}

function paneelModuleDefinities(): array
{
    static $definities = null;
    if ($definities === null) {
        $geladen = require __DIR__ . '/module-definities.php';
        $definities = is_array($geladen) ? $geladen : [];
    }
    return $definities;
}

function paneelModuleActief(string $module): bool
{
    $config = paneelModuleConfig();
    return (($config['modules'][$module] ?? false) === true);
}

function paneelModuleVoorFormulier(string $formulier, string $veld): ?string
{
    foreach (paneelModuleDefinities() as $module => $definitie) {
        $formulieren = $definitie[$veld] ?? [];
        if (is_array($formulieren) && in_array($formulier, $formulieren, true)) return $module;
    }
    return null;
}

function paneelIsLedenPagina(): bool
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $pad = strtolower((string) (parse_url($script, PHP_URL_PATH) ?: $script));
    return basename($pad) === 'leden.php'
        || preg_match('~(?:^|/)leden/(?:index\.php)?$~', $pad) === 1;
}

function paneelBewaakLedenModulePost(): void
{
    if (!paneelIsLedenPagina()) return;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;

    $formulier = isset($_POST['formulier']) && is_string($_POST['formulier']) ? $_POST['formulier'] : '';
    if ($formulier === '') return;

    $module = paneelModuleVoorFormulier($formulier, 'leden_formulieren');
    if ($module === null || paneelModuleActief($module)) return;

    $_POST['formulier'] = '';
    $GLOBALS['paneelModulePostGeblokkeerd'] = ['module' => $module, 'formulier' => $formulier];

    if (function_exists('schrijfLog') && isset($GLOBALS['logBestand'], $GLOBALS['huidigeGebruiker'])) {
        schrijfLog(
            $GLOBALS['logBestand'],
            (string) $GLOBALS['huidigeGebruiker'],
            'module_geblokkeerd',
            $module . ':' . $formulier
        );
    }
}

function paneelLedenModuleVisibilityMarkup(): string
{
    $selectors = [];
    foreach (paneelModuleDefinities() as $module => $definitie) {
        if (paneelModuleActief($module)) continue;
        foreach (($definitie['leden_tabs'] ?? []) as $tab) {
            if (!is_string($tab) || $tab === '') continue;
            $selectors[] = '#tab-' . $tab;
            $selectors[] = '[href="#' . $tab . '"]';
            $selectors[] = '[href="#tab-' . $tab . '"]';
            $selectors[] = '[data-tab="' . $tab . '"]';
            $selectors[] = '[data-tab-target="' . $tab . '"]';
        }
    }
    if (!$selectors) return '';
    return '<style id="paneel-module-visibility">' . implode(',', array_unique($selectors)) . '{display:none!important}</style>';
}

function paneelStartLedenModuleFilter(): void
{
    if (!paneelIsLedenPagina()) return;
    static $actief = false;
    if ($actief) return;
    $actief = true;

    ob_start(function ($html) {
        $markup = paneelLedenModuleVisibilityMarkup();
        if ($markup !== '' && stripos($html, '</head>') !== false && strpos($html, 'id="paneel-module-visibility"') === false) {
            $html = preg_replace('~</head>~i', $markup . "\n</head>", $html, 1) ?? $html;
        }
        return $html;
    });
}

paneelBewaakLedenModulePost();
paneelStartLedenModuleFilter();
