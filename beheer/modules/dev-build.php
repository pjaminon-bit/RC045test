<?php
// ============================================================
// DEV build-indicator
// ============================================================
// De deploy-workflow schrijft dev-build.json alleen op de DEV-server. Als
// dat bestand ontbreekt (bijv. productie of lokale installatie), wordt niets
// getoond. Zo is direct zichtbaar welke commit daadwerkelijk wordt getest.
// ============================================================

function beheerDevBuildInfo(): array
{
    $pad = dirname(__DIR__, 2) . '/dev-build.json';
    if (!is_file($pad)) return [];
    $data = json_decode((string) @file_get_contents($pad), true);
    return is_array($data) ? $data : [];
}

function beheerDevBuildMarkup(): string
{
    $info = beheerDevBuildInfo();
    if (!$info || (($info['environment'] ?? '') !== 'dev')) return '';

    $commit = htmlspecialchars((string) ($info['commit_short'] ?? ''), ENT_QUOTES, 'UTF-8');
    $branch = htmlspecialchars((string) ($info['branch'] ?? ''), ENT_QUOTES, 'UTF-8');
    $run = htmlspecialchars((string) ($info['run_number'] ?? ''), ENT_QUOTES, 'UTF-8');
    $tijd = (string) ($info['deployed_at_utc'] ?? '');
    $tijdTekst = $tijd;
    try {
        if ($tijd !== '') {
            $dt = new DateTimeImmutable($tijd);
            $dt = $dt->setTimezone(new DateTimeZone((string) siteConfigGet('vereniging.timezone', 'Europe/Amsterdam')));
            $tijdTekst = $dt->format('d-m-Y H:i');
        }
    } catch (Throwable $e) {
        // Laat bij een ongeldige datum gewoon de ruwe waarde zien.
    }
    $tijdTekst = htmlspecialchars($tijdTekst, ENT_QUOTES, 'UTF-8');

    return '<div id="dev-build-indicator" title="Deze gegevens worden tijdens de GitHub Actions deployment geschreven" '
        . 'style="position:fixed;right:14px;bottom:12px;z-index:10000;background:#201f1b;color:#fff;padding:6px 9px;border-radius:8px;'
        . 'font:600 11px/1.3 system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;box-shadow:0 4px 14px rgba(0,0,0,.18);opacity:.88;max-width:360px">'
        . '<strong style="color:#ffd76a">DEV</strong> · <code style="color:#fff">' . $commit . '</code>'
        . ($run !== '' ? ' · #' . $run : '')
        . ' <span style="opacity:.68">· ' . $branch . ' · ' . $tijdTekst . '</span></div>';
}

function beheerDevBuildStartOutputFilter(): void
{
    if (!beheerDevBuildInfo()) return;
    ob_start(function ($html) {
        if (!is_string($html) || stripos($html, '</body>') === false) return $html;
        if (strpos($html, 'id="dev-build-indicator"') !== false) return $html;
        $markup = beheerDevBuildMarkup();
        if ($markup === '') return $html;
        return preg_replace('~</body>~i', $markup . "\n</body>", $html, 1) ?? $html;
    });
}

beheerDevBuildStartOutputFilter();
