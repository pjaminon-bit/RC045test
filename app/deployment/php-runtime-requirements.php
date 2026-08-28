<?php
// Centrale PHP-runtime-eisen voor productie- en kandidaatreleases.
// Houd dit bestand secretvrij en zonder side effects zodat health- en releaseprobes
// dezelfde applicatie-eisen kunnen afdwingen.

function platformPhpRequiredExtensions(): array
{
    return ['openssl', 'pdo_pgsql', 'mbstring', 'curl', 'dom'];
}

function platformPhpAssertRequiredExtensions(): void
{
    foreach (platformPhpRequiredExtensions() as $extension) {
        if (!extension_loaded($extension)) {
            throw new RuntimeException('Vereiste PHP-extensie ontbreekt: ' . $extension);
        }
    }

    // ext-dom hoort deze klassen te leveren. Controleer ze expliciet omdat de
    // publieke tenanttemplate hier rechtstreeks van afhankelijk is.
    if (!class_exists(DOMDocument::class) || !class_exists(DOMXPath::class)) {
        throw new RuntimeException('Vereiste DOM-klassen ontbreken.');
    }
}
