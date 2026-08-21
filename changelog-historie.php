<?php
// Recente ontwikkelaarswijzigingen. De volledige bestaande historie staat
// ongewijzigd in app/core/changelog-historie-legacy.php.
$recent = [
  [
    'datum' => '2026-08-21',
    'cat' => 'verbetering',
    'titel' => 'Veilige releases en automatische rollback voorbereid',
    'tekst' => 'Fase 4.7 voegt immutable releases per commit toe met een inhoudsmanifest, een atomische current-wissel, tenantpreflight en volledige healthchecks na activatie. Als een nieuwe release na de wissel faalt wordt automatisch naar de vorige gezonde release teruggeschakeld. Handmatige rollback gebruikt alleen de eerder gevalideerde previous-release uit server-state.',
  ],
  [
    'datum' => '2026-08-21',
    'cat' => 'beveiliging',
    'titel' => 'Database-isolatie opnieuw aangescherpt na heraudit',
    'tekst' => 'De PostgreSQL tenantrole blijft voortaan NOLOGIN totdat de tenant-HBA-regels en exact least-privilege aantoonbaar actief zijn. Bij fouten valt de role weer terug naar NOLOGIN, beschermende rejectregels blijven staan en onveilige symlinks in PostgreSQL-configpaden worden geweigerd.',
  ],
  [
    'datum' => '2026-08-21',
    'cat' => 'verbetering',
    'titel' => 'PostgreSQL provisioning per vereniging toegevoegd',
    'tekst' => 'Nieuwe productie-tenants kunnen een eigen lokale PostgreSQL-database krijgen met een aparte NOLOGIN-owner en een app-role die via Unix-socket peer authentication exact aan de eigen Linux/PHP-FPM-user is gekoppeld. Databasewachtwoorden zijn daardoor niet nodig en cross-tenant databaseverkeer wordt expliciet geweigerd.',
  ],
  [
    'datum' => '2026-08-21',
    'cat' => 'onderhoud',
    'titel' => 'Monitoring en privacybewuste operationele logging voorbereid',
    'tekst' => 'Fase 4.6 voegt een informatie-arme healthcheck, lokale service/TLS/database/app-probes, 14-daagse logrotatie en gededupliceerde storingsalerts toe. Apache accesslogging bewaart bewust geen IP, requestpad/query, referrer, user-agent, cookies of Authorization.',
  ],
  [
    'datum' => '2026-08-20',
    'cat' => 'beveiliging',
    'titel' => 'TLS/HTTPS-automatisering fail-closed voorbereid',
    'tekst' => 'Per tenant zijn Certbot webroot-uitgifte, neutrale HTTP/HTTPS catch-alls, exacte Host- en SNI-binding, certificaatvalidatie, HSTS, configtest vóór reload en rollback bij mislukte eerste activatie toegevoegd. Private keys en ACME-accountgegevens komen niet in Git of tenantbundles.',
  ],
  [
    'datum' => '2026-08-20',
    'cat' => 'beveiliging',
    'titel' => 'DNS-readiness en exacte tenantrouting toegevoegd',
    'tekst' => 'DNS-plannen ondersteunen direct A/AAAA of één CNAME-hop en eisen exacte RRsets. Oude of extra IPv4/IPv6-records, gemengde CNAME/addressprofielen en verlopen readiness blokkeren de volgende TLS-stap. De productie-VPS moet minimaal drie consistente resolvermetingen zien.',
  ],
  [
    'datum' => '2026-08-20',
    'cat' => 'beveiliging',
    'titel' => 'Apache-vhosts en Linux/PHP-FPM-isolatie per tenant voorbereid',
    'tekst' => 'Iedere vereniging krijgt een deterministische Linux-user, unieke primary group, eigen PHP-FPM pool/socket en afgeschermde session/tmp-opslag. Apache gebruikt een neutrale default-vhost, exacte ServerName en vaste tenant-FPM-routing; private data, tooling en VCS-bestanden blijven buiten het HTTP-oppervlak.',
  ],
  [
    'datum' => '2026-08-20',
    'cat' => 'verbetering',
    'titel' => 'Multi-tenant provisioning en veilige eerste beheerder afgerond',
    'tekst' => 'De template kan nu aantoonbaar twee volledig gescheiden verenigingen provisionen met eigen configuratie, private opslag, auth, sessies, backups, assets en branding. Een eerste beheeraccount wordt via een aparte bootstrapstap geactiveerd zonder plaintext secret in CLI-argumenten.',
  ],
  [
    'datum' => '2026-08-20',
    'cat' => 'verbetering',
    'titel' => 'Beheerinterface en groepenmodel verder gemodulariseerd',
    'tekst' => 'Ledenlabels, groepen, commissies, werkgroepen en rollen zijn verder centraal gemaakt en de beheerinterface is technisch en visueel opgeschoond. De tenantconfig bepaalt welke modules zichtbaar en beschikbaar zijn.',
  ],
  [
    'datum' => '2026-08-19',
    'cat' => 'onderhoud',
    'titel' => 'Overbodige compatibility-loaders uit de webroot verwijderd',
    'tekst' => 'De sitekern, SEO-configuratie en gedeelde data-slothelper worden nu uitsluitend rechtstreeks vanuit de afgeschermde app-map geladen. Vijf eenregelige tussenbestanden zijn uit de codebase verwijderd. Hun webblokkades blijven bewust actief omdat de additieve SFTP-deployment eerder gedeployde bestanden niet automatisch van de server verwijdert. De publieke routes en opslaglocaties blijven ongewijzigd.',
  ],
  [
    'datum' => '2026-08-19',
    'cat' => 'onderhoud',
    'titel' => 'DEV-deployment controleert voortaan de gedeployde site zelf',
    'tekst' => 'Na de SFTP-upload voert GitHub Actions nu automatische smoke tests uit op de homepage, Beheer en Leden. Daarnaast wordt gecontroleerd dat interne app-code en dev-build.json daadwerkelijk met HTTP 403 afgeschermd blijven. De controles proberen enkele keren opnieuw zodat een korte vertraging bij Strato niet meteen een valse foutmelding veroorzaakt.',
  ],
  [
    'datum' => '2026-08-19',
    'cat' => 'beveiliging',
    'titel' => 'Interne opslag- en buildbestanden opnieuw geaudit en afgeschermd',
    'tekst' => 'De privacygevoelige opslaglaag is bewust op zijn bestaande projectroot gebleven omdat de databestanden en back-ups daarvan afhankelijk zijn. Interne root-loaders en dev-build.json zijn aanvullend rechtstreeks via HTTP geblokkeerd, terwijl een verouderde blokkade voor een inmiddels verwijderd bestand is opgeruimd.',
  ],
  [
    'datum' => '2026-08-19',
    'cat' => 'opgelost',
    'titel' => 'Beheer- en ledenmodules herkennen de nieuwe directoryroutes weer',
    'tekst' => 'Na de overgang naar /beheer/ en /leden/ controleerden enkele beveiligings- en modulelagen nog uitsluitend op beheer.php en leden.php. De contextdetectie ondersteunt nu zowel de oude compatibiliteitsingangen als de nieuwe directory-entrypoints. Daarmee werken modulebewaking, verborgen tabs en de DEV build-indicator weer via de canonieke routes.',
  ],
  [
    'datum' => '2026-08-19',
    'cat' => 'verbetering',
    'titel' => 'Gemigreerde beheeronderdelen zijn herkenbaar met een sterretje',
    'tekst' => 'Menu-items die al naar de nieuwe modulaire beheerarchitectuur zijn overgezet krijgen voortaan een sterretje. Ook zijn hun links aangepast aan de echte /beheer/-directory, zodat geen dubbele /beheer/beheer/-paden meer ontstaan.',
  ],
  [
    'datum' => '2026-08-19',
    'cat' => 'onderhoud',
    'titel' => 'Interne applicatiecode verder uit de webroot gehaald',
    'tekst' => 'De content- en templatekern is onder app/content geplaatst en technische helpers staan onder app/. De nieuwe app/core-laag verzamelt gedeelde site-, module- en paneellogica. De hele app-map is server-only, waardoor minder losse blokkades in .htaccess nodig zijn.',
  ],
  [
    'datum' => '2026-08-19',
    'cat' => 'verbetering',
    'titel' => 'Beheer en Leden gebruiken echte directory-entrypoints',
    'tekst' => '/beheer/ en /leden/ draaien nu via echte index.php-entrypoints in plaats van virtuele Apache-rewrites. De oude beheer.php- en leden.php-adressen blijven als compatibiliteitsredirect bestaan. Hierdoor werken assets en module-URL’s voorspelbaarder en is minder .htaccess-logica nodig.',
  ],
  [
    'datum' => '2026-08-19',
    'cat' => 'beveiliging',
    'titel' => 'Gebruikerssessies worden direct ingetrokken na gevoelige accountwijzigingen',
    'tekst' => 'Beheeraccounts hebben nu een sessieversie. Wachtwoord- en rechtenwijzigingen, blokkeren en verwijderen maken bestaande sessies direct ongeldig. Oude sessies kunnen een nieuwere sessieversie niet overnemen en interne sessiehelpers zijn rechtstreeks via HTTP geblokkeerd.',
  ],
  [
    'datum' => '2026-08-19',
    'cat' => 'verbetering',
    'titel' => 'Gebruikersbeheer uitgebreid met blokkeren, sessiebeheer en beveiligingsinformatie',
    'tekst' => 'Accounts kunnen tijdelijk worden geblokkeerd zonder ze te verwijderen, alle sessies kunnen gericht worden uitgelogd en recente loginproblemen zijn zichtbaar. Wachtwoordvelden hebben Nederlandse validatie, minimaal tien tekens, een oogknop en compactere accountopmaak.',
  ],
  [
    'datum' => '2026-08-19',
    'cat' => 'onderhoud',
    'titel' => 'Oude gebruikers-, logboek- en back-upcode uit beheer verwijderd',
    'tekst' => 'De zelfstandige modules Gebruikers, Logboek en Back-ups zijn de enige actieve codepaden geworden. Oude tabpanelen, POST-handlers en ongebruikte JavaScript uit het historische beheerbestand zijn verwijderd.',
  ],
  [
    'datum' => '2026-08-18',
    'cat' => 'verbetering',
    'titel' => 'Fotoboekbeheer en albumroutes verder gemoderniseerd',
    'tekst' => 'Albums worden via nette /fotoboek/<album>-routes geopend in plaats van hash-navigatie. Het fotoboekbeheer is uitgebreid en opgeschoond, inclusief albumdiagnose, filters en robuustere album- en fotoafhandeling.',
  ],
];

$legacy = require __DIR__ . '/app/core/changelog-historie-legacy.php';
return array_merge($recent, is_array($legacy) ? $legacy : []);
