<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function c119(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) {
        $ok++;
        echo "OK: {$label}\n";
        return;
    }
    $fout++;
    fwrite(STDERR, "FOUT: {$label}\n");
}

function bevatAuthRechtenCall119(string $src): bool
{
    $tokens = token_get_all($src);
    $aantal = count($tokens);
    for ($i = 0; $i < $aantal; $i++) {
        $token = $tokens[$i];
        if (!is_array($token) || $token[0] !== T_STRING || $token[1] !== 'authRechten') continue;
        for ($j = $i + 1; $j < $aantal; $j++) {
            $volgende = $tokens[$j];
            if (is_array($volgende) && in_array($volgende[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
            return $volgende === '(';
        }
    }
    return false;
}

require_once $root . '/app/auth-capabilities.php';

$platform = authPlatformDefinities();
$beheer = is_array($platform['beheer'] ?? null) ? $platform['beheer'] : [];
$capabilities = authCapabilityDefinities();

c119(authGebruikerCapabilities(['gebruikersnaam' => 'zonder-profiel']) === [], 'account zonder capabilities/tabs krijgt geen impliciete capability');
c119(authGebruikerCapabilities(['gebruikersnaam' => 'leeg', 'tabs' => []]) === [], 'expliciet lege legacy tabs blijven fail-closed');

$gedeeldeGroepenSrc = (string)file_get_contents($root . '/app/beheer/groepen-beheer.php');
c119(str_contains($gedeeldeGroepenSrc, 'authHeeftCapability($groepCapability)'), 'gedeelde groepenbeheerlaag controleert gedelegeerde capability server-side');

foreach ($beheer as $sleutel => $def) {
    if (!is_array($def)) {
        c119(false, "beheerdefinitie {$sleutel} is een array");
        continue;
    }
    $capability = trim((string)($def['capability'] ?? ''));
    c119($capability !== '' && isset($capabilities[$capability]), "beheeronderdeel {$sleutel} heeft een geldige centrale capability");
    c119(authBeheerCapability((string)$sleutel) === $capability, "beheerlookup {$sleutel} gebruikt dezelfde capability als platform-definities");

    $route = trim((string)($def['route'] ?? ''));
    if ($route === '') continue;
    $pad = (string)parse_url($route, PHP_URL_PATH);
    if ($pad === '') continue;
    $bestand = $root . '/beheer/' . ltrim($pad, '/');
    c119(is_file($bestand), "beheerroute {$sleutel} wijst naar bestaand endpoint {$pad}");
    if (!is_file($bestand)) continue;
    $src = (string)file_get_contents($bestand);

    $direct = str_contains($src, 'authHeeftCapability(')
        || str_contains($src, 'authHeeftBeheerOnderdeel(')
        || bevatAuthRechtenCall119($src)
        || $pad === 'content.php';
    $gedelegeerdGroepen = str_contains($src, "\$groepCapability='{$capability}'")
        && str_contains($src, "app/beheer/groepen-beheer.php")
        && str_contains($gedeeldeGroepenSrc, 'authHeeftCapability($groepCapability)');
    c119($direct || $gedelegeerdGroepen, "beheerendpoint {$pad} bevat of delegeert server-side autorisatie voor {$capability}");
}

$legacyVerwacht = [
    'app/content/content-beheer.php',
    'beheer/bedankt.php',
    'beheer/actueel.php',
    'beheer/agenda.php',
    'beheer/contact.php',
    'beheer/faq.php',
    'beheer/nieuws.php',
    'beheer/media.php',
    'beheer/sponsors.php',
    'beheer/fotoboek.php',
    'beheer/changelog.php',
];

$gevonden = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
    $pad = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if ($pad === 'auth.php' || str_starts_with($pad, 'tests/')) continue;
    $src = (string)file_get_contents($file->getPathname());
    if (bevatAuthRechtenCall119($src)) $gevonden[] = $pad;
}
sort($gevonden, SORT_STRING);
$legacyGesorteerd = $legacyVerwacht;
sort($legacyGesorteerd, SORT_STRING);
c119($gevonden === $legacyGesorteerd, 'repo-brede inventaris van echte authRechten-callers is expliciet en compleet: ' . implode(', ', $gevonden));

$authSrc = (string)file_get_contents($root . '/auth.php');
c119(str_contains($authSrc, "require_once __DIR__ . '/app/auth-capabilities.php';"), 'legacy authlaag laadt de centrale capabilitylaag');
c119(str_contains($authSrc, 'authBeheerCapability($tab)') && str_contains($authSrc, 'authHeeftBeheerOnderdeel($tab)'), 'authRechten routeert bekende beheer-tabs door centrale capabilitycheck');
c119(!str_contains($authSrc, "} else {\n    \$toegestaneTabs = array_keys(\$alleTabs);\n  }"), 'oude broad fallback zonder rechtenprofiel is verwijderd');

$legacyNaarCap = [
    'homepage' => 'content.homepage.manage',
    'ontstaan' => 'content.ontstaan.manage',
    'baanreglement' => 'content.reglement.manage',
    'aanmelden' => 'content.aanmelden.manage',
    'bedankt' => 'content.bedankt.manage',
    'actueel' => 'content.mededeling.manage',
    'nieuws' => 'content.nieuws.manage',
    'agenda' => 'events.agenda.manage',
    'contact' => 'content.contact.manage',
    'sponsors' => 'content.sponsors.manage',
    'faq' => 'content.faq.manage',
    'media' => 'content.media.manage',
    'fotoboek' => 'content.fotoboek.manage',
    'changelog' => 'system.changelog.manage',
    'logboek' => 'system.audit.read',
];
foreach ($legacyNaarCap as $tab => $capability) {
    c119(authBeheerCapability($tab) === $capability, "beheer-tab {$tab} gebruikt capability {$capability}");
}

$contentBeheer = (string)file_get_contents($root . '/app/content/content-beheer.php');
c119(str_contains($contentBeheer, 'authRechten([$beheerTab'), 'generieke contenteditor blijft expliciet onderdeel van de bewaakte legacy-compatibiliteitslaag');

$logboek = (string)file_get_contents($root . '/beheer/logboek.php');
c119(str_contains($logboek, "authHeeftBeheerOnderdeel('logboek')") && !str_contains($logboek, "authHeeftExplicietRecht('log')"), 'logboekendpoint gebruikt dezelfde system.audit.read capability als dashboard');

$dashboard = (string)file_get_contents($root . '/beheer/index.php');
c119(str_contains($dashboard, 'authHeeftCapability($cap'), 'dashboard controleert beheercomponenten via centrale capability');
c119(str_contains($authSrc, 'authHeeftBeheerOnderdeel($tab)'), 'directe legacy endpointcheck gebruikt hetzelfde capabilitycontract als dashboard');

echo "Security #119 legacy beheer capabilities: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
