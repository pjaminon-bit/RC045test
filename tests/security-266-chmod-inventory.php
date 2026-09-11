<?php
// Blijvend #266 source-contract: chmod mag niet als best-effort successpad
// verspreid in productiecode terugkomen. Directe chmod is alleen toegestaan
// wanneer failure expliciet wordt afgehandeld én dezelfde implementatie
// effectieve filesystemmetadata onafhankelijk controleert.

$root = dirname(__DIR__);
$fouten = [];
$beoordeeld = 0;
$cleanupExcepties = 0;
$centraleSeams = 0;

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $info) {
    if (!$info->isFile() || $info->isLink() || strtolower($info->getExtension()) !== 'php') continue;
    $path = $info->getPathname();
    $rel = str_replace('\\', '/', substr($path, strlen($root) + 1));
    if (str_starts_with($rel, 'tests/') || str_starts_with($rel, 'vendor/') || str_starts_with($rel, 'node_modules/')) continue;

    $raw = @file_get_contents($path);
    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if (!is_string($raw) || !is_array($lines)) continue;
    if (preg_match('/(?<![A-Za-z0-9_])@?chmod\s*\(/', $raw) !== 1) continue;

    // Effective mode/owner evidence must live in the same implementation.
    // lstat/stat/fileperms are accepted primitives; a mere successful chmod
    // syscall is deliberately not considered proof of the resulting mode.
    $heeftMetadataBewijs = preg_match('/\b(?:lstat|stat|fileperms)\s*\(/', $raw) === 1;

    foreach ($lines as $i => $line) {
        if (preg_match('/(?<![A-Za-z0-9_])@?chmod\s*\(/', $line) !== 1) continue;
        $nummer = $i + 1;
        $trim = trim($line);

        // Enige centrale syscall-seam; deze is bewust overridebaar in tests.
        if ($rel === 'app/storage/private-filesystem.php' && str_contains($trim, 'return @chmod(')) {
            $centraleSeams++;
            continue;
        }

        // Uitsluitend failure-cleanup van een nog niet geactiveerde tijdelijke
        // immutable release. Dit probeert verwijderbaarheid te herstellen en
        // kan nooit een succesvolle filesystem/security-state opleveren.
        if ($rel === 'bin/apply-vps-release.php'
            && str_contains($trim, 'catch(Throwable$e)')
            && str_contains($trim, '@rmdir($tmp)')) {
            $cleanupExcepties++;
            continue;
        }

        $beoordeeld++;
        $returnGecontroleerd = preg_match('/!\s*@?chmod\s*\(/', $line) === 1
            || preg_match('/@?chmod\s*\([^;]+\)\s*===\s*false/', $line) === 1;

        if (!$returnGecontroleerd) {
            $fouten[] = sprintf('%s:%d: chmod-resultaat niet fail-closed gecontroleerd: %s', $rel, $nummer, $trim);
            continue;
        }
        if (!$heeftMetadataBewijs) {
            $fouten[] = sprintf('%s:%d: chmod wordt gecontroleerd maar effectieve metadata wordt in dit bestand niet onafhankelijk bewezen', $rel, $nummer);
        }
    }
}

if ($centraleSeams !== 1) {
    $fouten[] = 'Centrale privateFilesystemChmod syscall-seam ontbreekt of is niet uniek (' . $centraleSeams . ').';
}
if ($cleanupExcepties > 1) {
    $fouten[] = 'Meer dan één cleanup-only chmod-exceptie aangetroffen (' . $cleanupExcepties . ').';
}

if ($fouten !== []) {
    fwrite(STDERR, "Security #266 chmod-contractfouten:\n - " . implode("\n - ", $fouten) . "\n");
    exit(1);
}

echo 'OK security-266: ' . $beoordeeld . ' directe chmod-contracten fail-closed + metadata-bewijs; '
    . $centraleSeams . ' centrale seam; ' . $cleanupExcepties . " cleanup-exceptie(s)\n";
