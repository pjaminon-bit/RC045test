<?php
// ============================================================
// Beheer-dashboard / module-shell
// ============================================================
// Vanaf fase 2.5 bevat deze route geen inhoudelijke formulieren meer. Alle
// beheerfuncties zijn zelfstandige modules; dit bestand verzorgt uitsluitend
// authenticatie, navigatie, feature flags en autorisatie.
// ============================================================
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/app/core/site.php';
require_once dirname(__DIR__) . '/app/auth-capabilities.php';

function beheerShellEsc($waarde): string
{
    return htmlspecialchars((string) $waarde, ENT_QUOTES, 'UTF-8');
}

function beheerShellPlatform(): array
{
    return authPlatformDefinities();
}

function beheerShellComponentActief(array $def): bool
{
    $feature = trim((string) ($def['feature'] ?? ''));
    return $feature === '' || siteModuleActief($feature);
}

function beheerShellMagOpenen(array $def): bool
{
    if (!beheerShellComponentActief($def)) return false;
    $capability = trim((string) ($def['capability'] ?? ''));
    return $capability === '' || authHeeftCapability($capability, !empty($def['gevoelig']));
}

function beheerShellRouteBestaat(string $route): bool
{
    $pad = (string) parse_url($route, PHP_URL_PATH);
    if ($pad === '') return false;
    return is_file(__DIR__ . '/' . ltrim($pad, '/'));
}

$platform = beheerShellPlatform();
$componenten = isset($platform['beheer']) && is_array($platform['beheer']) ? $platform['beheer'] : [];
$groepen = [];
if ($ingelogd) {
    foreach ($componenten as $sleutel => $def) {
        if (!is_array($def) || !beheerShellMagOpenen($def)) continue;
        $route = trim((string) ($def['route'] ?? ''));
        // Nieuwe fase-2.5 routes worden pas zichtbaar zodra het bestand in de
        // deployment aanwezig is. Zo kan de shell veilig vóór een module landen.
        if ($route === '' || !beheerShellRouteBestaat($route)) continue;
        $categorie = (string) ($def['categorie'] ?? 'Overig');
        $groepen[$categorie][(string) $sleutel] = $def;
    }
}

$build = null;
$buildPad = dirname(__DIR__) . '/dev-build.json';
if (is_file($buildPad)) {
    $ruw = @file_get_contents($buildPad);
    $gelezen = $ruw === false ? null : json_decode($ruw, true);
    if (is_array($gelezen)) $build = $gelezen;
}
?><!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= beheerShellEsc(siteVerenigingNaam()) ?> beheer</title>
<style>
:root{--bg:#f6f2e8;--card:#fff;--ink:#26351d;--muted:#68705f;--line:#ddd8c0;--primary:#3a7a77;--primary2:#2d6260;--danger:#8b2e27;--ok:#23613e}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.top{background:#fff;border-bottom:1px solid var(--line);padding:14px 22px;position:sticky;top:0;z-index:20}.topin{max-width:1180px;margin:auto;display:flex;justify-content:space-between;align-items:center;gap:18px}.brand{font-weight:850;color:var(--ink)}.top a{color:var(--primary2);text-decoration:none;font-weight:700}.user{display:flex;align-items:center;gap:12px;color:var(--muted);font-size:14px}.logout{border:0;background:none;color:var(--primary2);font:inherit;font-weight:750;cursor:pointer;padding:0}.wrap{max-width:1180px;margin:32px auto;padding:0 22px 70px}.hero{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:24px}.hero h1{margin:0 0 7px;font-size:30px}.hero p{margin:0;color:var(--muted)}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.groep{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:18px;box-shadow:0 3px 16px #2331190a}.groep h2{font-size:14px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin:0 0 12px}.links{display:grid;gap:8px}.module{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:11px 12px;border-radius:10px;background:#edf5f4;color:var(--primary2);text-decoration:none;font-weight:760;border-left:3px solid var(--primary)}.module:hover{background:#e2f0ef}.module span:first-child:before{content:'* ';}.meta{font-size:12px;font-weight:600;color:var(--muted)}.build{margin-top:22px;padding:12px 14px;border:1px dashed #c8c1a9;border-radius:10px;color:var(--muted);font-size:13px}.empty{padding:20px;background:#fff;border:1px solid var(--line);border-radius:14px}.login-wrap{max-width:520px;margin:70px auto;padding:0 20px}.kaart{background:#fff;border:1px solid var(--line);border-radius:16px;padding:24px}.kaart h1{margin-top:0}.sub{color:var(--muted)}.veld{margin:14px 0}.veld label{display:block;font-weight:700;margin-bottom:6px}.veld input{width:100%;border:1px solid #cfcab7;border-radius:9px;padding:11px;font:inherit}.kaart button{border:0;background:var(--primary);color:#fff;border-radius:9px;padding:11px 16px;font:inherit;font-weight:750;cursor:pointer}.melding.fout{background:#fdeceb;color:var(--danger);padding:11px;border-radius:8px}.hint{color:var(--muted);font-size:13px}@media(max-width:900px){.grid{grid-template-columns:1fr 1fr}}@media(max-width:620px){.grid{grid-template-columns:1fr}.hero,.topin{flex-direction:column}.top{position:static}}
</style>
</head>
<body>
<?php if (!$ingelogd): ?>
<div class="login-wrap"><?php authInlogFormulier(siteVerenigingNaam() . ' beheer'); ?></div>
<?php else: ?>
<header class="top"><div class="topin"><div><span class="brand"><?= beheerShellEsc(siteVerenigingNaam()) ?> beheer</span> · <a href="../index.html">website</a> · <a href="../leden/">mijn vereniging</a></div><div class="user"><span>Ingelogd als <strong><?= beheerShellEsc($huidigeGebruiker) ?></strong></span><form method="post"><input type="hidden" name="formulier" value="uitloggen"><input type="hidden" name="csrf" value="<?= beheerShellEsc($csrfToken) ?>"><button class="logout" type="submit">Uitloggen</button></form></div></div></header>
<main class="wrap"><div class="hero"><div><h1>Beheer</h1><p>Website, vereniging en systeembeheer vanuit één modulaire omgeving.</p></div></div>
<?php if (!$groepen): ?><div class="empty">Voor dit account zijn geen beheeronderdelen beschikbaar.</div><?php else: ?><div class="grid">
<?php foreach ($groepen as $categorie => $items): ?><section class="groep"><h2><?= beheerShellEsc($categorie) ?></h2><div class="links"><?php foreach ($items as $def): $route=(string)$def['route']; ?><a class="module" href="<?= beheerShellEsc($route) ?>"><span><?= beheerShellEsc($def['label'] ?? '') ?></span><span class="meta">open →</span></a><?php endforeach; ?></div></section><?php endforeach; ?>
</div><?php endif; ?>
<?php if ($build): ?><div class="build">DEV build: <strong><?= beheerShellEsc($build['commit_short'] ?? '') ?></strong> · branch <?= beheerShellEsc($build['branch'] ?? '') ?> · run <?= beheerShellEsc($build['run_number'] ?? '') ?> · <?= beheerShellEsc($build['deployed_at_utc'] ?? '') ?></div><?php endif; ?>
</main>
<?php endif; ?>
</body></html>
