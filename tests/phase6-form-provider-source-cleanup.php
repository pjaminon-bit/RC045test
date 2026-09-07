<?php
$root = dirname(__DIR__);
$needle = 'form' . 'spree';
$skipDirs = ['.git', 'node_modules', 'vendor', 'playwright-report', 'test-results'];
$textExtensions = ['php','js','json','md','yml','yaml','html','htm','css','sh','xml','txt'];
$specialNames = ['.htaccess', '.gitignore'];
$hits = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $current) use ($skipDirs): bool {
            if ($current->isDir()) {
                return !in_array($current->getFilename(), $skipDirs, true);
            }
            return true;
        }
    )
);

foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) continue;
    $name = $file->getFilename();
    $ext = strtolower($file->getExtension());
    if (!in_array($ext, $textExtensions, true) && !in_array($name, $specialNames, true)) continue;
    if ($file->getSize() > 2 * 1024 * 1024) continue;

    $content = @file_get_contents($file->getPathname());
    if (!is_string($content) || stripos($content, $needle) === false) continue;
    $hits[] = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
}

if ($hits !== []) {
    sort($hits);
    fwrite(STDERR, "FOUT: legacy externe formulierprovider staat nog in repositorybron:\n - " . implode("\n - ", $hits) . "\n");
    exit(1);
}

$aanmelden = (string) file_get_contents($root . '/aanmelden.php');
$index = (string) file_get_contents($root . '/index.php');
if (!str_contains($aanmelden, 'action="aanmelden-ontvangst.php"')) {
    fwrite(STDERR, "FOUT: aanmeldformulier post niet rechtstreeks naar lokale intake.\n");
    exit(1);
}
if (!str_contains($index, 'action="contact-ontvangst.php"')) {
    fwrite(STDERR, "FOUT: contactformulier post niet rechtstreeks naar lokale inbox.\n");
    exit(1);
}
if (substr_count($aanmelden, 'fetch(form.action') !== 1 || str_contains($aanmelden, "fetch('aanmelden-ontvangst.php'")) {
    fwrite(STDERR, "FOUT: aanmeldbrowserflow bevat niet exact één primaire POST.\n");
    exit(1);
}

echo "Phase 6 formulierprovider-broncleanup: OK\n";
