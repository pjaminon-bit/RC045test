<?php
$root = dirname(__DIR__);
require_once $root . '/app/auth-beheer-guard.php';

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
function p119(string $pad): string
{
    return str_replace('\\', '/', $pad);
}

$platform = authPlatformDefinities();
$beheer = (array)($platform['beheer'] ?? []);
c119($beheer !== [], 'centrale beheerdefinities zijn beschikbaar');

$platformBestanden = [];
foreach ($beheer as $sleutel => $definitie) {
    c119(is_array($definitie), "beheerdefinitie {$sleutel} is een array");
    if (!is_array($definitie)) {
        continue;
    }
    $route = (string)($definitie['route'] ?? '');
    $routePad = (string)(parse_url($route, PHP_URL_PATH) ?? '');
    $bestand = basename($routePad);
    $query = [];
    parse_str((string)(parse_url($route, PHP_URL_QUERY) ?? ''), $query);
    $contract = authBeheerRouteContract('/beheer/' . $bestand, $query);
    $capability = (string)($definitie['capability'] ?? '');
    c119(
        is_array($contract)
        && ($contract['type'] ?? '') === 'capability'
        && ($contract['sleutel'] ?? '') === (string)$sleutel
        && ($contract['capability'] ?? '') === $capability,
        "dashboardroute {$sleutel} en direct endpoint delen capability {$capability}"
    );

    $explicietVerwacht = !empty($definitie['gevoelig']);
    $checkerGezien = null;
    $mag = is_array($contract) && authBeheerEndpointMagOpenen(
        $contract,
        static function (string $gevraagd, bool $expliciet) use ($capability, $explicietVerwacht, &$checkerGezien): bool {
            $checkerGezien = [$gevraagd, $expliciet];
            return hash_equals($capability, $gevraagd);
        }
    );
    c119(
        $mag && $checkerGezien === [$capability, $explicietVerwacht],
        "direct endpoint {$sleutel} gebruikt exact dashboard-capabilitycontract"
    );
    c119(
        is_array($contract) && !authBeheerEndpointMagOpenen(
            $contract,
            static fn(string $gevraagd, bool $expliciet): bool => false
        ),
        "direct endpoint {$sleutel} weigert een account zonder vereiste capability"
    );
    $platformBestanden[$bestand] = true;
}

$special = authBeheerRouteContract('/beheer/groep-relaties.php', []);
c119(
    is_array($special) && ($special['type'] ?? '') === 'capability-groepen',
    'groep-relaties heeft een expliciet samengesteld server-side capabilitycontract'
);
c119(
    is_array($special) && !authBeheerEndpointMagOpenen(
        $special,
        static fn(string $capability, bool $expliciet): bool => false
    ),
    'groep-relaties weigert directe URL-toegang zonder capabilities'
);
c119(
    is_array($special) && authBeheerEndpointMagOpenen(
        $special,
        static fn(string $capability, bool $expliciet): bool => in_array(
            $capability,
            ['committees.manage', 'tasks.manage'],
            true
        )
    ),
    'groep-relaties vereist zowel een groepsbeheer- als een relatiedoel-capability'
);
c119(
    is_array($special) && !authBeheerEndpointMagOpenen(
        $special,
        static fn(string $capability, bool $expliciet): bool => $capability === 'committees.manage'
    ),
    'één capabilitygroep alleen is onvoldoende voor groep-relaties'
);

$dashboard = authBeheerRouteContract('/beheer/index.php', []);
c119(
    is_array($dashboard) && ($dashboard['type'] ?? '') === 'dashboard',
    'beheerindex is expliciet als dashboard geclassificeerd'
);
c119(
    authBeheerRouteContract('/leden/index.php', []) === null,
    'centrale beheerrouteguard raakt routes buiten /beheer niet'
);
$onbekend = authBeheerRouteContract('/beheer/nieuwe-onbekende-editor.php', []);
c119(
    is_array($onbekend)
    && ($onbekend['type'] ?? '') === 'onbekend'
    && !authBeheerEndpointMagOpenen($onbekend, static fn(string $c, bool $e): bool => true),
    'onbekende nieuwe beheerpagina faalt gesloten, ook met permissieve checker'
);

c119(!authBeheerRechtenprofielGeldig([]), 'account zonder rechtenvelden heeft geen geldig beheerprofiel');
c119(!authBeheerRechtenprofielGeldig(['capabilities' => 'alles']), 'malformed capabilities-profiel is ongeldig');
c119(!authBeheerRechtenprofielGeldig(['tabs' => 'alles']), 'malformed tabs-profiel is ongeldig');
c119(authBeheerRechtenprofielGeldig(['capabilities' => []]), 'expliciet leeg capabilityprofiel is geldig maar verleent niets');
c119(authBeheerRechtenprofielGeldig(['tabs' => []]), 'expliciet leeg legacy-tabprofiel is geldig maar verleent niets');

$interneBestanden = ['backup-registry.php', 'gebruikers-rechten.php', 'fotoboek-lib.php'];
$redirectAliases = ['lid-groepen.php', 'rekentabel.php'];
$verwacht = array_keys($platformBestanden);
$verwacht = array_merge($verwacht, ['index.php', 'groep-relaties.php'], $interneBestanden, $redirectAliases);
$verwacht = array_values(array_unique($verwacht));
sort($verwacht, SORT_STRING);

$werkelijk = [];
foreach (glob($root . '/beheer/*.php') ?: [] as $pad) {
    $werkelijk[] = basename($pad);
}
sort($werkelijk, SORT_STRING);
c119(
    $werkelijk === $verwacht,
    'alle direct bereikbare beheer-PHP endpoints zijn expliciet geïnventariseerd'
);
if ($werkelijk !== $verwacht) {
    fwrite(STDERR, '  verwacht: ' . implode(', ', $verwacht) . "\n");
    fwrite(STDERR, '  werkelijk: ' . implode(', ', $werkelijk) . "\n");
}

$htaccess = (string)file_get_contents($root . '/.htaccess');
foreach ($interneBestanden as $bestand) {
    c119(
        str_contains($htaccess, $bestand),
        "interne beheerhelper {$bestand} is expliciet door de webserver geblokkeerd"
    );
}

$aliasLid = (string)file_get_contents($root . '/beheer/lid-groepen.php');
c119(
    str_contains($aliasLid, "\$doel='leden.php'")
    && str_contains($aliasLid, "http_response_code(308)")
    && str_contains($aliasLid, "header('Location: '.\$doel)")
    && !str_contains($aliasLid, 'file_put_contents')
    && !str_contains($aliasLid, 'repo'),
    'lid-groepen is uitsluitend een vaste redirectalias naar beschermd ledenbeheer'
);
$aliasRekentabel = (string)file_get_contents($root . '/beheer/rekentabel.php');
c119(
    str_contains($aliasRekentabel, "header('Location: lidmaatschap.php', true, 308)")
    && !str_contains($aliasRekentabel, 'file_put_contents')
    && !str_contains($aliasRekentabel, 'repo'),
    'rekentabel is uitsluitend een vaste redirectalias naar beschermd lidmaatschapsbeheer'
);

$indirectAuth = [
    'content.php' => 'app/content/content-beheer.php',
    'commissies.php' => 'app/beheer/groepen-beheer.php',
    'werkgroepen.php' => 'app/beheer/groepen-beheer.php',
];
$bereikbareAuthRoutes = array_values(array_unique(array_merge(array_keys($platformBestanden), ['groep-relaties.php'])));
sort($bereikbareAuthRoutes, SORT_STRING);
foreach ($bereikbareAuthRoutes as $bestand) {
    $bron = (string)file_get_contents($root . '/beheer/' . $bestand);
    $heeftAuth = str_contains($bron, 'auth.php');
    if (!$heeftAuth && isset($indirectAuth[$bestand])) {
        $gedeeld = (string)file_get_contents($root . '/' . $indirectAuth[$bestand]);
        $heeftAuth = str_contains($bron, basename($indirectAuth[$bestand]))
            && str_contains($gedeeld, 'auth.php');
    }
    c119($heeftAuth, "beheerendpoint {$bestand} loopt door auth.php en de centrale routeguard");
}

$legacyVerwacht = [
    'app/content/content-beheer.php',
    'beheer/actueel.php',
    'beheer/agenda.php',
    'beheer/bedankt.php',
    'beheer/changelog.php',
    'beheer/contact.php',
    'beheer/faq.php',
    'beheer/fotoboek.php',
    'beheer/media.php',
    'beheer/nieuws.php',
    'beheer/sponsors.php',
];
sort($legacyVerwacht, SORT_STRING);
$legacyWerkelijk = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $bestand) {
    if (!$bestand->isFile() || strtolower($bestand->getExtension()) !== 'php') {
        continue;
    }
    $relatief = p119(substr($bestand->getPathname(), strlen($root) + 1));
    if ($relatief === 'auth.php' || str_starts_with($relatief, 'tests/')) {
        continue;
    }
    $bron = (string)file_get_contents($bestand->getPathname());
    if (str_contains($bron, 'authRechten(')) {
        $legacyWerkelijk[] = $relatief;
    }
}
sort($legacyWerkelijk, SORT_STRING);
c119(
    $legacyWerkelijk === $legacyVerwacht,
    'authRechten() is bevroren tot de bestaande legacy editorcallers; nieuwe callers laten regressie falen'
);
if ($legacyWerkelijk !== $legacyVerwacht) {
    fwrite(STDERR, '  legacy verwacht: ' . implode(', ', $legacyVerwacht) . "\n");
    fwrite(STDERR, '  legacy werkelijk: ' . implode(', ', $legacyWerkelijk) . "\n");
}

$legacyContracten = [
    'beheer/actueel.php' => ['actueel', 'content.mededeling.manage'],
    'beheer/agenda.php' => ['agenda', 'events.agenda.manage'],
    'beheer/bedankt.php' => ['bedankt', 'content.bedankt.manage'],
    'beheer/changelog.php' => ['changelog', 'system.changelog.manage'],
    'beheer/contact.php' => ['contact', 'content.contact.manage'],
    'beheer/faq.php' => ['faq', 'content.faq.manage'],
    'beheer/fotoboek.php' => ['fotoboek', 'content.fotoboek.manage'],
    'beheer/media.php' => ['media', 'content.media.manage'],
    'beheer/nieuws.php' => ['nieuws', 'content.nieuws.manage'],
    'beheer/sponsors.php' => ['sponsors', 'content.sponsors.manage'],
];
foreach ($legacyContracten as $pad => [$sleutel, $capability]) {
    $contract = authBeheerRouteContract('/' . $pad, []);
    c119(
        is_array($contract)
        && ($contract['sleutel'] ?? '') === $sleutel
        && ($contract['capability'] ?? '') === $capability,
        "legacy editor {$pad} wordt vóór authRechten() exact door {$capability} begrensd"
    );
}
foreach ([
    'homepage' => 'content.homepage.manage',
    'ontstaan' => 'content.ontstaan.manage',
    'baanreglement' => 'content.reglement.manage',
    'aanmelden' => 'content.aanmelden.manage',
] as $pagina => $capability) {
    $contract = authBeheerRouteContract('/beheer/content.php', ['pagina' => $pagina]);
    c119(
        is_array($contract) && ($contract['capability'] ?? '') === $capability,
        "legacy contenteditor pagina={$pagina} gebruikt exact {$capability}"
    );
}
$verkeerdeContent = authBeheerRouteContract('/beheer/content.php', ['pagina' => 'niet-bestaand']);
c119(
    is_array($verkeerdeContent)
    && ($verkeerdeContent['type'] ?? '') === 'onbekend'
    && !authBeheerEndpointMagOpenen($verkeerdeContent, static fn(string $c, bool $e): bool => true),
    'onbekende contenteditorpagina krijgt geen broad legacy fallback'
);

$sessionBron = (string)file_get_contents($root . '/app/auth-session-check.php');
c119(
    str_contains($sessionBron, "require_once __DIR__ . '/auth-beheer-guard.php'")
    && str_contains($sessionBron, 'authBeheerRechtenprofielGeldig($sessionAccount)')
    && str_contains($sessionBron, 'authBeheerEndpointHandhaaf($authBeheerContract)'),
    'sessiepoort dwingt geldig rechtenprofiel en centrale beheerendpointguard af'
);

echo "Security #119 beheer capability contract: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
