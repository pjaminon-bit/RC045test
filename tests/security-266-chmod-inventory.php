<?php
// Tijdelijke/blijvende #266 source-contractinventaris: directe gesilencete chmod
// hoort niet verspreid in productiecode te leven zonder expliciete review.

$root = dirname(__DIR__);
$hits = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $info) {
    if (!$info->isFile() || $info->isLink() || strtolower($info->getExtension()) !== 'php') continue;
    $path = $info->getPathname();
    $rel = str_replace('\\', '/', substr($path, strlen($root) + 1));
    if (str_starts_with($rel, 'tests/') || str_starts_with($rel, 'vendor/') || str_starts_with($rel, 'node_modules/')) continue;
    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) continue;
    foreach ($lines as $i => $line) {
        if (preg_match('/@chmod\s*\(/', $line) !== 1) continue;
        $hits[] = sprintf('%s:%d: %s', $rel, $i + 1, trim($line));
    }
}

if ($hits !== []) {
    fwrite(STDERR, "Directe @chmod-calls in productiecode:\n - " . implode("\n - ", $hits) . "\n");
    exit(1);
}

echo "OK security-266: geen directe @chmod-calls buiten centrale contracts\n";
