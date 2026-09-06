<?php
$root = dirname(__DIR__);

$styleBlocks = [];
$styleAttributes = [];
$dynamicStyleSinks = [];

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

    if (preg_match_all('~<style\\b[^>]*>~i', $raw, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            $line = 1 + substr_count(substr($raw, 0, $match[1]), "\n");
            $styleBlocks[] = "{$rel}:{$line}";
        }
    }

    if (preg_match_all("~\\sstyle\\s*=\\s*([\"'])(.*?)\\1~is", $raw, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($matches as $match) {
            $line = 1 + substr_count(substr($raw, 0, $match[0][1]), "\n");
            $value = preg_replace('/\\s+/', ' ', trim((string)$match[2][0]));
            $styleAttributes[] = [
                'location' => "{$rel}:{$line}",
                'value' => $value,
            ];
        }
    }

    $sinkPatterns = [
        'cssText' => '~\\.cssText\\b~',
        'setAttribute(style)' => "~setAttribute\\s*\\(\\s*[\"']style[\"']~i",
        'setAttributeNS(style)' => "~setAttributeNS\\s*\\([^,]+,\\s*[\"']style[\"']~i",
        'createElement(style)' => "~createElement\\s*\\(\\s*[\"']style[\"']~i",
    ];
    foreach ($sinkPatterns as $label => $pattern) {
        if (preg_match($pattern, $raw) === 1) $dynamicStyleSinks[] = "{$rel}: {$label}";
    }
}

sort($styleBlocks);
usort($styleAttributes, static fn(array $a, array $b): int => strcmp($a['location'], $b['location']));
$dynamicStyleSinks = array_values(array_unique($dynamicStyleSinks));
sort($dynamicStyleSinks);

$uniqueValues = [];
foreach ($styleAttributes as $entry) {
    $uniqueValues[$entry['value']][] = $entry['location'];
}
ksort($uniqueValues);

echo 'CSP style inventaris: ' . count($styleBlocks) . ' <style>-blokken; ' . count($styleAttributes) . ' style-attributen; ' . count($uniqueValues) . " unieke stylewaarden.\n";
echo "STYLE_BLOCKS\n";
foreach ($styleBlocks as $entry) echo "  {$entry}\n";
echo "STYLE_ATTRIBUTES\n";
foreach ($uniqueValues as $value => $locations) {
    echo '  ' . json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    foreach ($locations as $location) echo "    {$location}\n";
}
echo "DYNAMIC_STYLE_SINKS\n";
if ($dynamicStyleSinks === []) echo "  geen cssText/setAttribute(style)/createElement(style) gevonden\n";
else foreach ($dynamicStyleSinks as $entry) echo "  {$entry}\n";

// Inventarisfase voor #192. Deze test wordt op dezelfde branch aangescherpt
// naar het definitieve fail-closed contract zodra alle actuele gevallen zijn
// gereconcilieerd en gemigreerd.
exit(0);
