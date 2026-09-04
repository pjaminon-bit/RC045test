<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function c147(bool $conditie, string $label): void
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

$gebruikersSrc = (string) file_get_contents($root . '/beheer/gebruikers.php');
$authSrc = (string) file_get_contents($root . '/auth.php');
$dashboardSrc = (string) file_get_contents($root . '/beheer/index.php');
$legacyRegressieSrc = (string) file_get_contents($root . '/tests/security-legacy-beheer-capabilities.php');

c147(
    !str_contains($gebruikersSrc, 'blijven werken via de compatibility-laag'),
    'gebruikersbeheer claimt geen brede compatibility voor oude accounts'
);
c147(
    str_contains($gebruikersSrc, 'Oude accounts met geldige bestaande rechten behouden alleen die toegekende rechten.')
        && str_contains($gebruikersSrc, 'Ontbrekende of ongeldige opgeslagen rechten geven geen extra toegang.'),
    'migratiewaarschuwing beschrijft beperkte legacyrechten en fail-closed ontbrekende rechten'
);
c147(
    str_contains($gebruikersSrc, 'Bij oude accounts worden bestaande tabrechten alleen gebruikt om dezelfde beperkte rechten over te zetten.'),
    'algemene rechtenuitleg beschrijft legacy tabs als beperkte migratiebron'
);
c147(
    str_contains($gebruikersSrc, 'authGebruikerMigreerRecord($g)')
        && str_contains($gebruikersSrc, 'name="actie" value="migreren"')
        && str_contains($gebruikersSrc, 'Accounts nu migreren'),
    'gebruikersbeheer verwijst direct naar de bestaande veilige migratieactie'
);

c147(
    authGebruikerCapabilities(['gebruikersnaam' => 'zonder-profiel']) === [],
    'ontbrekend rechtenprofiel geeft geen impliciete capabilities'
);
c147(
    authGebruikerCapabilities(['gebruikersnaam' => 'tabs-verkeerd-type', 'tabs' => 'homepage']) === [],
    'ongeldig legacy tabsprofiel faalt gesloten'
);
c147(
    authGebruikerCapabilities(['gebruikersnaam' => 'tabs-onbekend', 'tabs' => ['bestaat-niet']]) === [],
    'onbekende legacy tab geeft geen capability'
);
c147(
    authGebruikerCapabilities(['gebruikersnaam' => 'caps-verkeerd-type', 'capabilities' => 'content.homepage.manage']) === [],
    'ongeldig capabilityprofiel zonder geldig legacyprofiel faalt gesloten'
);
c147(
    authGebruikerCapabilities(['gebruikersnaam' => 'legacy-geldig', 'tabs' => ['homepage']]) === ['content.homepage.manage'],
    'geldig legacy tabrecht behoudt uitsluitend de gekoppelde centrale capability'
);
c147(
    authGebruikerCapabilities(['gebruikersnaam' => 'cap-geldig', 'capabilities' => ['content.homepage.manage']]) === ['content.homepage.manage'],
    'geldig bestaand capabilityrecht blijft werken'
);

c147(
    str_contains($authSrc, 'authBeheerCapability($tab)')
        && str_contains($authSrc, 'authHeeftBeheerOnderdeel($tab)')
        && !str_contains($authSrc, '$toegestaneTabs = array_keys($alleTabs);\n  } else {'),
    'legacy directe beheerchecks gebruiken het centrale fail-closed capabilitycontract'
);
c147(
    str_contains($dashboardSrc, 'authHeeftCapability($cap'),
    'dashboard gebruikt de centrale capabilitycontrole'
);
c147(
    str_contains($legacyRegressieSrc, 'repo-brede inventaris van echte authRechten-callers is expliciet en compleet')
        && str_contains($legacyRegressieSrc, 'directe legacy endpointcheck gebruikt hetzelfde capabilitycontract als dashboard'),
    '#119 route-inventaris en dashboard/direct-route-pariteit blijven verplichte regressies'
);

echo "Security #147 gebruikersauth-uitleg: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
