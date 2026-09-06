<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$pattern = '<style\\b|[[:space:]]style=|\\.style\\.|\\.style\\[|\\.style\\.setProperty|cssText|setAttribute[^\\n]*style|createElement[^\\n]*style';
$grepCommand = 'git -C ' . escapeshellarg($root)
    . ' grep -IlE ' . escapeshellarg($pattern)
    . " HEAD -- '*.php' '*.html' '*.js'";
$grepOutput = shell_exec($grepCommand);
if ($grepOutput === null) {
    fwrite(STDERR, "Kon relevante CSP-style bronbestanden niet inventariseren.\n");
    exit(1);
}

$files = array_values(array_filter(array_map('trim', preg_split('/\\R/', $grepOutput) ?: [])));

$cssCommand = 'git -C ' . escapeshellarg($root) . " ls-files '*.css'";
$cssOutput = shell_exec($cssCommand);
if ($cssOutput === null) {
    fwrite(STDERR, "Kon CSS-bestanden niet inventariseren.\n");
    exit(1);
}
$files = array_merge($files, array_values(array_filter(array_map('trim', preg_split('/\\R/', $cssOutput) ?: []))));

$files = array_merge($files, [
    'site-config.php',
    'app/core/csp-runtime.php',
    'app/core/tenant-public-runtime.php',
    'tests/csp-script-source-regression.php',
    'tests/csp-style-source-regression.php',
    'tests/run-all.sh',
]);
$files = array_values(array_unique($files));
sort($files, SORT_STRING);

printf("CSP_SOURCE_SNAPSHOT_FILES %d\n", count($files));
foreach ($files as $path) {
    $showCommand = 'git -C ' . escapeshellarg($root) . ' show ' . escapeshellarg('HEAD:' . $path);
    $content = shell_exec($showCommand);
    if ($content === null) {
        fwrite(STDERR, "Kon HEAD-bron niet lezen: {$path}\n");
        exit(1);
    }
    echo 'CSP_SOURCE_SNAPSHOT_BEGIN ' . base64_encode($path) . "\n";
    echo base64_encode($content) . "\n";
    echo "CSP_SOURCE_SNAPSHOT_END\n";
}

exit(0);
