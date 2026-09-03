<?php
$root = dirname(__DIR__);
$violations = [];
$inlineScripts = [];
$inlineStyles = [];
$styleAttributes = [];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $info) {
    if (!$info->isFile() || $info->isLink()) continue;
    $path = $info->getPathname();
    $rel = str_replace('\\', '/', substr($path, strlen($root) + 1));
    if (str_starts_with($rel, '.git/') || str_starts_with($rel, 'node_modules/') || str_starts_with($rel, 'vendor/') || str_starts_with($rel, 'tests/')) continue;
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    if (!in_array($ext, ['php', 'html'], true)) continue;
    $raw = @file_get_contents($path);
    if (!is_string($raw)) continue;

    if (preg_match_all('/<script\b([^>]*)>/i', $raw, $scripts, PREG_OFFSET_CAPTURE)) {
        foreach ($scripts[1] as $match) {
            $attrs = (string)$match[0];
            if (preg_match('/\bsrc\s*=/i', $attrs) === 1) continue;
            $line = substr_count(substr($raw, 0, (int)$match[1]), "\n") + 1;
            $inlineScripts[] = "{$rel}:{$line}";
            if (preg_match('/\bnonce\s*=\s*[\"\'][^\"\']+[\"\']/i', $attrs) !== 1) {
                $violations[] = "{$rel}:{$line}: inline <script> zonder nonce";
            }
        }
    }

    if (preg_match_all('/\son[a-z][a-z0-9_-]*\s*=\s*[\"\']/i', $raw, $handlers, PREG_OFFSET_CAPTURE)) {
        foreach ($handlers[0] as $match) {
            $line = substr_count(substr($raw, 0, (int)$match[1]), "\n") + 1;
            $violations[] = "{$rel}:{$line}: inline eventhandler";
        }
    }

    if (preg_match_all('/\b(?:href|src|action)\s*=\s*[\"\']\s*javascript:/i', $raw, $jsUrls, PREG_OFFSET_CAPTURE)) {
        foreach ($jsUrls[0] as $match) {
            $line = substr_count(substr($raw, 0, (int)$match[1]), "\n") + 1;
            $violations[] = "{$rel}:{$line}: javascript:-URL";
        }
    }

    if (preg_match_all('/<style\b/i', $raw, $styles, PREG_OFFSET_CAPTURE)) {
        foreach ($styles[0] as $match) {
            $line = substr_count(substr($raw, 0, (int)$match[1]), "\n") + 1;
            $inlineStyles[] = "{$rel}:{$line}";
        }
    }
    if (preg_match_all('/\sstyle\s*=\s*[\"\']/i', $raw, $styles, PREG_OFFSET_CAPTURE)) {
        foreach ($styles[0] as $match) {
            $line = substr_count(substr($raw, 0, (int)$match[1]), "\n") + 1;
            $styleAttributes[] = "{$rel}:{$line}";
        }
    }
}

$siteConfig = @file_get_contents($root . '/site-config.php');
if (!is_string($siteConfig)) {
    $violations[] = 'site-config.php: kon centrale CSP niet lezen';
} else {
    if (preg_match("/script-src[^;]*'unsafe-inline'/", $siteConfig) === 1) {
        $violations[] = "site-config.php: script-src bevat nog 'unsafe-inline'";
    }
    foreach (["base-uri 'self'", "object-src 'none'", "frame-ancestors 'none'", "form-action 'self'"] as $required) {
        if (!str_contains($siteConfig, $required)) $violations[] = "site-config.php: vereiste CSP-directive ontbreekt: {$required}";
    }
}

echo "CSP script broninventaris\n";
echo 'Inline scripts: ' . count($inlineScripts) . "\n";
foreach ($inlineScripts as $entry) echo "  SCRIPT {$entry}\n";
echo 'Inline <style>-blokken: ' . count($inlineStyles) . "\n";
echo 'Inline style-attributen: ' . count($styleAttributes) . "\n";

if ($violations !== []) {
    fwrite(STDERR, "CSP script regressie: " . count($violations) . " fout(en)\n");
    foreach ($violations as $entry) fwrite(STDERR, "  {$entry}\n");
    exit(1);
}

echo "CSP script regressie: OK\n";
