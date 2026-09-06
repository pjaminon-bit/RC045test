<?php
$root = dirname(__DIR__);

$tracked = [];
exec('cd ' . escapeshellarg($root) . ' && git ls-files -z', $trackedOut, $trackedRc);
if ($trackedRc === 0) {
    $joined = implode("\n", $trackedOut);
    foreach (explode("\0", $joined) as $item) {
        $item = trim($item);
        if ($item !== '') $tracked[str_replace('\\', '/', $item)] = true;
    }
}

$styleBlocks = [];
$styleAttributes = [];
$dynamicStyleSinks = [];
$relevantFiles = [];
$gitChanged = [];
exec('cd ' . escapeshellarg($root) . ' && git status --porcelain=v1 --untracked-files=all', $gitChanged);

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
    $origin = isset($tracked[$rel]) ? 'TRACKED' : 'GENERATED';
    $relevant = false;

    if (preg_match_all('~<style\\b[^>]*>~i', $raw, $matches, PREG_OFFSET_CAPTURE)) {
        $relevant = true;
        foreach ($matches[0] as $match) {
            $line = 1 + substr_count(substr($raw, 0, $match[1]), "\n");
            $styleBlocks[] = "{$origin} {$rel}:{$line}";
        }
    }

    if (preg_match_all("~\\sstyle\\s*=\\s*([\"'])(.*?)\\1~is", $raw, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        $relevant = true;
        foreach ($matches as $match) {
            $line = 1 + substr_count(substr($raw, 0, $match[0][1]), "\n");
            $value = preg_replace('/\\s+/', ' ', trim((string)$match[2][0]));
            $styleAttributes[] = [
                'location' => "{$origin} {$rel}:{$line}",
                'value' => $value,
            ];
        }
    }

    $sinkPatterns = [
        'cssText' => '~\\.cssText\\b~',
        'setAttribute(style)' => "~setAttribute\\s*\\(\\s*[\"']style[\"']~i",
        'setAttributeNS(style)' => "~setAttributeNS\\s*\\([^,]+,\\s*[\"']style[\"']~i",
        'createElement(style)' => "~createElement\\s*\\(\\s*[\"']style[\"']~i",
        'style.property assignment' => '~\\.style\\.[A-Za-z_$][A-Za-z0-9_$]*\\s*=~',
        'style[index] assignment' => '~\\.style\\s*\\[[^]]+\\]\\s*=~',
        'style.setProperty' => '~\\.style\\.setProperty\\s*\\(~',
    ];
    foreach ($sinkPatterns as $label => $pattern) {
        if (preg_match_all($pattern, $raw, $matches, PREG_OFFSET_CAPTURE)) {
            if (in_array($label, ['cssText', 'setAttribute(style)', 'setAttributeNS(style)', 'createElement(style)'], true)) $relevant = true;
            foreach ($matches[0] as $match) {
                $line = 1 + substr_count(substr($raw, 0, $match[1]), "\n");
                $dynamicStyleSinks[] = "{$origin} {$rel}:{$line}: {$label}";
            }
        }
    }

    if ($relevant) $relevantFiles[$rel] = $raw;
}

sort($styleBlocks);
usort($styleAttributes, static fn(array $a, array $b): int => strcmp($a['location'], $b['location']));
$dynamicStyleSinks = array_values(array_unique($dynamicStyleSinks));
sort($dynamicStyleSinks);
ksort($relevantFiles);

$uniqueValues = [];
foreach ($styleAttributes as $entry) {
    $uniqueValues[$entry['value']][] = $entry['location'];
}
ksort($uniqueValues);

echo 'CSP style inventaris: ' . count($styleBlocks) . ' <style>-blokken; ' . count($styleAttributes) . ' style-attributen; ' . count($uniqueValues) . " unieke stylewaarden.\n";
echo "GIT_STATUS_NA_EERDERE_TESTS\n";
foreach ($gitChanged as $entry) echo "  {$entry}\n";
echo "STYLE_BLOCKS\n";
foreach ($styleBlocks as $entry) echo "  {$entry}\n";
echo "STYLE_ATTRIBUTES\n";
foreach ($uniqueValues as $value => $locations) {
    echo '  ' . json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    foreach ($locations as $location) echo "    {$location}\n";
}
echo "DYNAMIC_STYLE_SINKS\n";
if ($dynamicStyleSinks === []) echo "  geen inline-style JavaScript-sinks gevonden\n";
else foreach ($dynamicStyleSinks as $entry) echo "  {$entry}\n";

echo "CSP192_SOURCE_DUMP_BEGIN\n";
foreach ($relevantFiles as $rel => $raw) {
    echo 'FILE ' . base64_encode($rel) . ' ' . base64_encode($raw) . "\n";
}
echo "CSP192_SOURCE_DUMP_END\n";

exit(0);