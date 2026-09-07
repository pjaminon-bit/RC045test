<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function test223Fail(string $message): never
{
    fwrite(STDERR, "FOUT: {$message}\n");
    exit(1);
}

function test223Require(bool $condition, string $message): void
{
    if (!$condition) test223Fail($message);
}

$site = file_get_contents($root . '/site-i18n.js');
test223Require(is_string($site), 'site-i18n.js kon niet worden gelezen');
foreach (['getLanguageStorageKey', 'getStoredLanguage', 'setStoredLanguage'] as $helper) {
    test223Require(str_contains($site, 'function ' . $helper . '('), "gedeelde helper {$helper} ontbreekt");
}

test223Require(str_contains($site, "return 'rc045_lang';"), 'default RC045 storagekeycompatibiliteit ontbreekt');
test223Require(str_contains($site, "tenantKey + '_lang'"), 'tenantKey-gebaseerde externe storagekey ontbreekt');
test223Require(!str_contains($site, "context.name + '_lang'"), 'displaynaam mag geen storagekey vormen');

// Deze vier inline paginascripts vormden de #223 read/write-mismatch en moeten
// allemaal uitsluitend de gedeelde write-helper gebruiken. De actieve
// taalbutton is defensief: markupdrift mag een taalwissel niet laten crashen.
$affectedWriters = [
    'media.php',
    'aanmelden.php',
    'bedankt.php',
    'fotoboek.php',
];
foreach ($affectedWriters as $bestand) {
    $bron = file_get_contents($root . '/' . $bestand);
    test223Require(is_string($bron), "{$bestand} kon niet worden gelezen");
    test223Require(str_contains($bron, 'setStoredLanguage(lang);'), "{$bestand} gebruikt de gedeelde write-helper niet");
    test223Require(!str_contains($bron, "localStorage.setItem('rc045_lang', lang)"), "{$bestand} schrijft nog rechtstreeks naar rc045_lang");
    test223Require(str_contains($bron, 'const activeBtn'), "{$bestand} selecteert de actieve taalbutton niet");
    test223Require(
        preg_match('/if\s*\(\s*activeBtn\s*\)\s*\{/', $bron) === 1,
        "{$bestand} dereferentieert de actieve taalbutton niet null-safe"
    );
}

// De homepage had vóór #223 al het correcte stabiele tenantKey-contract voor
// zowel lezen als schrijven. Houd dat gedrag intact en voorkom een displaynaam-
// of hardcoded externe key.
$homepage = file_get_contents($root . '/homepage.js');
test223Require(is_string($homepage), 'homepage.js kon niet worden gelezen');
test223Require(
    str_contains($homepage, "localStorage.setItem((tenantSiteContext ? tenantSiteContext.tenantKey : 'rc045') + '_lang', lang);"),
    'homepage.js moet het bestaande tenantKey-gebaseerde writecontract behouden'
);
test223Require(
    !str_contains($homepage, "localStorage.setItem('rc045_lang', lang)"),
    'homepage.js mag voor externe tenants niet hardcoded rc045_lang schrijven'
);

$nodeTest = __DIR__ . '/platform-223-language-storage.js';
$output = [];
$status = 0;
exec('node ' . escapeshellarg($nodeTest) . ' 2>&1', $output, $status);
if ($status !== 0) {
    test223Fail("Node-gedragstest faalde:\n" . implode("\n", $output));
}

echo implode("\n", $output) . "\n";
echo "OK: #223 source-integratie en tenantisolatie\n";
