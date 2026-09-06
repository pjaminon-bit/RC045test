<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$ok = 0;
$fout = 0;
function cspStyleOk(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) { $ok++; echo "OK: {$label}\n"; return; }
    $fout++; fwrite(STDERR, "FOUT: {$label}\n");
}
function cspStyleGitShow(string $root, string $pad): string
{
    $raw = shell_exec('git -C ' . escapeshellarg($root) . ' show ' . escapeshellarg('HEAD:' . $pad));
    if (!is_string($raw)) throw new RuntimeException('Git-bron kon niet worden gelezen: ' . $pad);
    return $raw;
}

$tracked = [];
exec('git -C ' . escapeshellarg($root) . ' ls-files', $tracked, $rc);
if ($rc !== 0 || $tracked === []) { fwrite(STDERR, "FOUT: tracked bronlijst kon niet worden gelezen.\n"); exit(1); }

$styleAttrs = [];
$blockedSinks = [];
$styleBlocks = [];
$unsafeInline = [];
foreach ($tracked as $pad) {
    $pad = str_replace('\\', '/', trim($pad));
    if ($pad === '' || str_starts_with($pad, 'tests/') || str_starts_with($pad, 'vendor/') || str_starts_with($pad, 'node_modules/')) continue;
    $ext = strtolower(pathinfo($pad, PATHINFO_EXTENSION));
    if (!in_array($ext, ['php', 'html', 'js', 'conf', 'sh', 'yml', 'yaml'], true)) continue;
    $raw = cspStyleGitShow($root, $pad);

    if (preg_match_all('~<[A-Za-z][^<>]*\\sstyle\\s*=\\s*(["\\\']).*?\\1[^<>]*>~is', $raw, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) $styleAttrs[] = $pad . ':' . (1 + substr_count(substr($raw, 0, $match[1]), "\n"));
    }
    foreach ([
        'cssText' => '~\\.cssText\\b~',
        'setAttribute(style)' => '~setAttribute\\s*\\(\\s*["\\\']style["\\\']~i',
        'setAttributeNS(style)' => '~setAttributeNS\\s*\\([^,]+,\\s*["\\\']style["\\\']~i',
        'createElement(style)' => '~createElement\\s*\\(\\s*["\\\']style["\\\']~i',
    ] as $label => $pattern) {
        if (preg_match($pattern, $raw) === 1) $blockedSinks[] = $pad . ': ' . $label;
    }
    if (preg_match_all('~<style\\b([^>]*)>.*?</style>~is', $raw, $blocks, PREG_SET_ORDER)) {
        foreach ($blocks as $block) {
            $attrs = (string)$block[1];
            $styleBlocks[] = [$pad, $attrs];
        }
    }
    if (str_contains($raw, "'unsafe-inline'")) $unsafeInline[] = $pad;
}

cspStyleOk($styleAttrs === [], 'tracked runtimebron bevat geen inline style-attributen');
if ($styleAttrs !== []) foreach ($styleAttrs as $entry) fwrite(STDERR, "  {$entry}\n");
cspStyleOk($blockedSinks === [], 'JavaScript/PHP bouwt geen CSP-geblokkeerde inline style via cssText/setAttribute/createElement');
if ($blockedSinks !== []) foreach ($blockedSinks as $entry) fwrite(STDERR, "  {$entry}\n");
cspStyleOk($unsafeInline === [], "runtime/configuratie bevat nergens 'unsafe-inline'");
if ($unsafeInline !== []) foreach ($unsafeInline as $entry) fwrite(STDERR, "  {$entry}\n");

$expected = [
    'app/content/content-pagina-runtime.php' => 1,
    'app/content/content-renderer.php' => 2,
    'app/core/site.php' => 4,
    'app/core/tenant-public-runtime.php' => 1,
    'beheer/instellingen.php' => 1,
];
$counts = [];
$unnonced = [];
foreach ($styleBlocks as [$pad, $attrs]) {
    $counts[$pad] = ($counts[$pad] ?? 0) + 1;
    if (stripos($attrs, 'nonce=') === false) $unnonced[] = $pad;
}
ksort($counts);
cspStyleOk($counts === $expected, 'alleen de negen expliciet benoemde dynamische stylecontracten blijven inline');
cspStyleOk(count($styleBlocks) === 9, 'tracked runtimebron bevat exact negen dynamische styleblokken');
cspStyleOk($unnonced === [], 'ieder resterend dynamisch styleblok is expliciet aan de response-nonce gebonden');

$siteConfig = cspStyleGitShow($root, 'site-config.php');
cspStyleOk(str_contains($siteConfig, "style-src 'self' 'nonce-" . '{$cspNonce}' . "' https://fonts.googleapis.com"), 'centrale style-src gebruikt self + response-nonce + bestaande Google Fonts origin');
cspStyleOk(str_contains($siteConfig, "style-src-attr 'none'"), 'style-attributen zijn CSP-technisch fail-closed geblokkeerd');
foreach (["base-uri 'self'", "object-src 'none'", "frame-ancestors 'none'", "script-src-attr 'none'", 'form-action {$formAction}', 'https://api.open-meteo.com', 'https://fonts.gstatic.com', 'https://www.openstreetmap.org'] as $contract) {
    cspStyleOk(str_contains($siteConfig, $contract), 'bestaande CSP-grens blijft behouden: ' . $contract);
}
cspStyleOk(!str_contains($siteConfig, "'unsafe-hashes'") && !str_contains($siteConfig, "'unsafe-eval'"), 'geen nieuwe CSP-bypass via unsafe-hashes/unsafe-eval');

$tenantRuntime = cspStyleGitShow($root, 'app/core/tenant-public-runtime.php');
cspStyleOk(str_contains($tenantRuntime, "preg_match('/^#[0-9A-F]{6}$/D'"), 'tenantkleuren blijven strikt tot #RRGGBB beperkt');
cspStyleOk(str_contains($tenantRuntime, 'siteCspHtmlWaarde(siteCspNonce())'), 'tenantbrandingstyle gebruikt de requestnonce');
$site = cspStyleGitShow($root, 'app/core/site.php');
cspStyleOk(str_contains($site, "preg_match('/^#[0-9A-Fa-f]{6}$/'"), 'sitekleuren blijven strikt tot hexkleuren beperkt');

$suspended = cspStyleGitShow($root, 'bin/apply-vps-lifecycle.php');
cspStyleOk(str_contains($suspended, 'href="/style.css"') && str_contains($suspended, "'style'=>'/var/www/verenigingsplatform-suspended/style.css'"), 'suspended placeholder gebruikt een eigen statische stylesheet');
cspStyleOk(str_contains($suspended, "style-src \\'self\\'") && !str_contains($suspended, "style-src \\'unsafe-inline\\'"), 'suspended vhost staat alleen same-origin styles toe');

$control = cspStyleGitShow($root, 'app/deployment/control-plane-contract.php');
cspStyleOk(str_contains($control, "style-src \\'self\\'") && !str_contains($control, "style-src \\'self\\' \\'unsafe-inline\\'"), 'control-plane vhost staat alleen same-origin styles toe');

$iframe = cspStyleGitShow($root, 'app/content/tenant-homepage.php');
cspStyleOk(str_contains($iframe, '/iframe-placeholder.css') && !str_contains($iframe, '<body style='), 'tenantkaart-placeholder gebruikt externe CSS in srcdoc');

$media = cspStyleGitShow($root, 'app/core/tenant-public-media.php');
cspStyleOk(!str_contains($media, "setAttribute('style'"), 'tenant media-runtime maakt geen style-attribuut');

$cpIndex = cspStyleGitShow($root, 'app/control-plane-web/index.php');
$cpOnboarding = cspStyleGitShow($root, 'app/control-plane-web/onboarding.php');
cspStyleOk(str_contains($cpIndex, 'csp-progress-<?=$ob[\'percent\']?>') && str_contains($cpOnboarding, 'csp-progress-<?=$ob[\'percent\']?>'), 'control-plane progress gebruikt begrensde stylesheetklassen');

$assets = [];
foreach ($tracked as $pad) {
    $pad = str_replace('\\', '/', trim($pad));
    if (preg_match('#(?:^|/)csp205-[a-z0-9-]+-[0-9a-f]{12}\\.css$#D', $pad) === 1) $assets[] = $pad;
}
$badHash = [];
foreach ($assets as $asset) {
    $css = cspStyleGitShow($root, $asset);
    preg_match('/-([0-9a-f]{12})\\.css$/D', $asset, $m);
    if (($m[1] ?? '') !== substr(hash('sha256', $css), 0, 12)) $badHash[] = $asset;
}
cspStyleOk(count($assets) === 43 && $badHash === [], 'alle 43 csp205-assets blijven content-gehasht en immutable');

echo "CSP style regressie: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
