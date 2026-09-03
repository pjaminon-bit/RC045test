<?php
$root = dirname(__DIR__);
require_once $root . '/app/core/csp-runtime.php';

$ok = 0;
$fout = 0;
function cspOk(bool $cond, string $label): void
{
    global $ok, $fout;
    if ($cond) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

$nonce = siteCspNonce();
cspOk(preg_match('/^[A-Za-z0-9+\/]{24}$/D', $nonce) === 1, 'CSP nonce heeft 144 bits cryptografische request-entropie');
cspOk(hash_equals($nonce, siteCspNonce()), 'CSP nonce blijft binnen één response stabiel');

$fixture = '<!doctype html><html><body>'
    . '<button onclick="setLang(\'nl\')">NL</button>'
    . '<button onclick="window.scrollTo({top:0,behavior:\'smooth\'})">Top</button>'
    . '<form onsubmit="return confirm(\'Zeker?\');"><select onchange="this.form.submit()"></select></form>'
    . '<script>window.inline=1;</script><script src="app.js"></script>'
    . '</body></html>';
$gehard = siteCspHardenHtml($fixture);
cspOk(str_contains($gehard, 'data-csp-lang="nl"'), 'taalhandler wordt inert data-attribuut');
cspOk(str_contains($gehard, 'data-csp-scroll-top="1"'), 'scrollhandler wordt inert data-attribuut');
cspOk(str_contains($gehard, 'data-csp-confirm="Zeker?"'), 'confirmhandler wordt inert data-attribuut');
cspOk(str_contains($gehard, 'data-csp-submit-form="1"'), 'autosubmit-handler wordt inert data-attribuut');
cspOk(preg_match('/\son[a-z][a-z0-9_-]*\s*=/i', $gehard) !== 1, 'ondersteunde legacy eventhandlers verdwijnen uit browseroutput');
cspOk(str_contains($gehard, '<script nonce="' . $nonce . '">window.inline=1;</script>'), 'inline script krijgt exact de response-nonce');
cspOk(str_contains($gehard, '<script src="app.js"></script>'), 'extern script behoudt bestaand src-contract');
cspOk(!str_contains($gehard, '<script nonce="' . $nonce . '" src="app.js"'), 'extern script krijgt geen nonce-bypass van originbeleid');
cspOk(str_contains($gehard, '<script nonce="' . $nonce . '" id="site-csp-event-bridge">'), 'eventbridge is aan dezelfde response-nonce gebonden');

$selectorFixture = '<!doctype html><html><body><script>const activeBtn=document.querySelector(`.lang-flag[onclick="setLang(\'${lang}\')"]`);</script></body></html>';
$selectorGehard = siteCspHardenHtml($selectorFixture);
cspOk(str_contains($selectorGehard, '[data-csp-lang="${lang}"]'), 'legacy taal-selector volgt het data-attribuutcontract');

$onbekend = '<!doctype html><html><body><button onclick="alert(1)">x</button></body></html>';
cspOk(str_contains(siteCspHardenHtml($onbekend), 'onclick="alert(1)"'), 'hardener interpreteert geen onbekende inline JavaScript-code');
cspOk(siteCspHardenHtml('{"ok":true}') === '{"ok":true}', 'niet-HTML response blijft bytegelijk');

$inlineScripts = 0;
$inlineHandlers = 0;
$inlineStyles = 0;
$styleAttributes = 0;
$dynamicScriptBuilders = [];
$dynamicStyleBuilders = [];
$bronFouten = [];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $info) {
    if (!$info->isFile() || $info->isLink()) continue;
    $path = $info->getPathname();
    $rel = str_replace('\\', '/', substr($path, strlen($root) + 1));
    if (str_starts_with($rel, '.git/') || str_starts_with($rel, 'node_modules/') || str_starts_with($rel, 'vendor/') || str_starts_with($rel, 'tests/')) continue;
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    if (!in_array($ext, ['php', 'html', 'js'], true)) continue;
    $raw = @file_get_contents($path);
    if (!is_string($raw)) continue;

    if (preg_match('/\b(?:href|src|action)\s*=\s*["\']\s*javascript:/i', $raw) === 1 || preg_match('/["\']javascript\s*:/i', $raw) === 1) {
        $bronFouten[] = "{$rel}: javascript:-URL gevonden";
    }
    if (preg_match('/setAttribute\s*\(\s*["\']on[a-z0-9_-]+["\']/i', $raw) === 1) {
        $bronFouten[] = "{$rel}: dynamisch inline eventattribuut gevonden";
    }
    if (preg_match('/createElement\s*\(\s*["\']script["\']/i', $raw) === 1) $dynamicScriptBuilders[] = $rel;
    if (preg_match('/createElement\s*\(\s*["\']style["\']/i', $raw) === 1) $dynamicStyleBuilders[] = $rel;

    if (!in_array($ext, ['php', 'html'], true) || $rel === 'app/core/csp-runtime.php') continue;

    if (preg_match_all('/<script\b([^>]*)>/i', $raw, $scripts, PREG_SET_ORDER)) {
        foreach ($scripts as $script) {
            if (preg_match('/\bsrc\s*=/i', (string)$script[1]) !== 1) $inlineScripts++;
        }
    }
    if (preg_match_all('/\son[a-z][a-z0-9_-]*\s*=\s*["\']/i', $raw, $handlers)) $inlineHandlers += count($handlers[0]);
    if (preg_match_all('/<style\b/i', $raw, $styles)) $inlineStyles += count($styles[0]);
    if (preg_match_all('/\sstyle\s*=\s*["\']/i', $raw, $styles)) $styleAttributes += count($styles[0]);

    // Elk templatefragment wordt door exact dezelfde centrale response-hardener
    // gehaald. Zo blijven ook partials zonder eigen <html>-tag onderdeel van de
    // regressie en kan een nieuw onbekend eventattribuut niet stil binnensluipen.
    $probe = '<!doctype html><html><body>' . $raw . '</body></html>';
    $hardened = siteCspHardenHtml($probe);
    if (preg_match('/\son[a-z][a-z0-9_-]*\s*=\s*["\']/i', $hardened) === 1) {
        $bronFouten[] = "{$rel}: inline eventattribuut blijft na centrale hardening aanwezig";
    }
    if (preg_match('/\b(?:href|src|action)\s*=\s*["\']\s*javascript:/i', $hardened) === 1) {
        $bronFouten[] = "{$rel}: javascript:-URL blijft na centrale hardening aanwezig";
    }
    if (preg_match_all('/<script\b([^>]*)>/i', $hardened, $scripts, PREG_SET_ORDER)) {
        foreach ($scripts as $script) {
            $attrs = (string)$script[1];
            $heeftSrc = preg_match('/\bsrc\s*=/i', $attrs) === 1;
            $heeftNonce = str_contains($attrs, 'nonce="' . $nonce . '"');
            if (!$heeftSrc && !$heeftNonce) $bronFouten[] = "{$rel}: inline script zonder actuele response-nonce";
            if ($heeftSrc && $heeftNonce) $bronFouten[] = "{$rel}: extern script kreeg onbedoeld een nonce";
        }
    }
}

cspOk($bronFouten === [], 'repo-brede browseroutput bevat geen inline-script/eventhandler bypass buiten noncecontract');
if ($bronFouten !== []) foreach ($bronFouten as $entry) fwrite(STDERR, "  {$entry}\n");

echo "CSP broninventaris: {$inlineScripts} inline scripts, {$inlineHandlers} inline eventhandlers, {$inlineStyles} <style>-blokken, {$styleAttributes} style-attributen.\n";
echo 'Dynamische script-builders: ' . count(array_unique($dynamicScriptBuilders)) . '; dynamische style-builders: ' . count(array_unique($dynamicStyleBuilders)) . ".\n";

$siteConfig = @file_get_contents($root . '/site-config.php');
if (!is_string($siteConfig)) {
    cspOk(false, 'centrale CSP is leesbaar');
} else {
    cspOk(preg_match("/script-src[^;]*'unsafe-inline'/", $siteConfig) !== 1, "script-src bevat geen brede 'unsafe-inline'");
    cspOk(str_contains($siteConfig, 'script-src \'self\' \'nonce-{$cspNonce}\''), 'script-src gebruikt uitsluitend self plus response-nonce');
    cspOk(str_contains($siteConfig, "script-src-attr 'none'"), 'inline eventattributen zijn CSP-technisch fail-closed geblokkeerd');
    cspOk(str_contains($siteConfig, "base-uri 'self'"), 'base-uri blijft strikt same-origin');
    cspOk(str_contains($siteConfig, "object-src 'none'"), 'object-src blijft none');
    cspOk(str_contains($siteConfig, "frame-ancestors 'none'"), 'frame-ancestors blijft none');
    cspOk(str_contains($siteConfig, '$formAction = "\'self\'"') && str_contains($siteConfig, 'form-action {$formAction}'), 'form-action blijft same-origin');
    foreach (['https://api.open-meteo.com', 'https://fonts.googleapis.com', 'https://fonts.gstatic.com', 'https://www.openstreetmap.org'] as $origin) {
        cspOk(str_contains($siteConfig, $origin), "bestaande CSP-originallowlist blijft behouden: {$origin}");
    }
    cspOk(str_contains($siteConfig, "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com"), "style-src blijft bewust ongewijzigd voor apart hardeningtraject");
    cspOk(!str_contains($siteConfig, "'unsafe-eval'"), "CSP voegt geen 'unsafe-eval' toe");
    cspOk(!str_contains($siteConfig, "'unsafe-hashes'"), "CSP voegt geen 'unsafe-hashes' toe");
    $cspBufferPos = strpos($siteConfig, 'siteCspRuntimeStart();');
    $tenantBufferPos = strpos($siteConfig, 'tenantPublicRuntimeStart($config, $externPad);');
    cspOk($cspBufferPos !== false && $tenantBufferPos !== false && $cspBufferPos < $tenantBufferPos, 'CSP hardener is buitenste responsebuffer en ziet definitieve tenantoutput');
}

echo "CSP script regressie: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
