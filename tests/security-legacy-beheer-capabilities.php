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

require_once $root . '/app/auth-capabilities.php';

$platform = authPlatformDefinities();
$beheer = is_array($platform['beheer'] ?? null) ? $platform['beheer'] : [];
$capabilities = authCapabilityDefinities();

c119(authGebruikerCapabilities(['gebruikersnaam' => 'zonder-profiel']) === [], 'account zonder capabilities/tabs krijgt geen impliciete capability');
c119(authGebruikerCapabilities(['gebruikersnaam' => 'leeg', 'tabs' => []]) === [], 'expliciet lege legacy tabs blijven fail-closed');

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
    $heeftServerAuth = str_contains($src, 'authHeeftCapability(')
        || str_contains($src, 'authHeeftBeheerOnderdeel(')
        || str_contains($src, 'authRechten(')
        || $pad === 'content.php';
    c119($heeftServerAuth, "beheerendpoint {$pad} bevat server-side autorisatie");
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
    if (str_contains($src, 'authRechten(')) $gevonden[] = $pad;
}
sort($gevonden, SORT_STRING);
$legacyGesorteerd = $legacyVerwacht;
sort($legacyGesorteerd, SORT_STRING);
c119($gevonden === $legacyGesorteerd, 'repo-brede inventaris van authRechten-callers is expliciet en compleet: ' . implode(', ', $gevonden));

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
];
foreach ($legacyNaarCap as $tab => $capability) {
    c119(authBeheerCapability($tab) === $capability, "legacy beheer-tab {$tab} gebruikt capability {$capability}");
}

$contentBeheer = (string)file_get_contents($root . '/app/content/content-beheer.php');
c119(str_contains($contentBeheer, 'authRechten([$beheerTab'), 'generieke contenteditor blijft expliciet onderdeel van de bewaakte legacy-compatibiliteitslaag');

$dashboard = (string)file_get_contents($root . '/beheer/index.php');
c119(str_contains($dashboard, 'authHeeftCapability($cap'), 'dashboard controleert beheercomponenten via centrale capability');
c119(str_contains($authSrc, 'authHeeftBeheerOnderdeel($tab)'), 'directe legacy endpointcheck gebruikt hetzelfde capabilitycontract als dashboard');

echo "Security #119 legacy beheer capabilities: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
