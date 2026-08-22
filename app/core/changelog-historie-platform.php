<?php
return [
    [
        'datum' => '2026-08-22',
        'cat' => 'verbeterd',
        'titel' => 'Volledige pre-VPS eindacceptatie afgerond',
        'tekst' => 'Het verenigingsplatform is vóór de eerste echte VPS-installatie opnieuw volledig gecontroleerd. De bron-, functionele, technische en securityregressie is groen, de actieve DEV-securitycontrole is groen en de publieke browseracceptatie is op desktop, tablet en mobiel geslaagd. Ook de ingelogde beheer- en ledenomgeving is met tijdelijke testaccounts en een synthetisch gekoppeld lid doorlopen, inclusief persoonsgegevens, contributie, commissie, vergadering/notulen en taken. Tijdelijke testdata en authenticatiebestanden worden na de test exact hersteld. De bevroren kandidaat voor de eerste VPS-proef is commit 936cf4879f1611d94123fb3d3a0a33b831a49810.',
    ],
    [
        'datum' => '2026-08-22',
        'cat' => 'beveiliging',
        'titel' => 'Live security- en browserbevindingen opgelost',
        'tekst' => 'De eindregressie heeft meerdere concrete verbeteringen opgeleverd. PHP-runtimeinformatie via X-Powered-By is uitgeschakeld, standalone DEV-content en ontbrekende template-assets zijn veilig afgehandeld, tablet/mobile overflow is hersteld, te kleine bedieningselementen zijn vergroot en formuliersemantiek/toegankelijkheid is aangescherpt. De permanente browsertest scrollt pagina’s nu volledig zodat scrollanimaties en verborgen content daadwerkelijk worden gecontroleerd.',
    ],
    [
        'datum' => '2026-08-22',
        'cat' => 'onderhoud',
        'titel' => 'VPS-acceptatie uitgebreid met echte hersteltest',
        'tekst' => 'Fase 5.3 is nu de enige open productieacceptatiefase. Een tenantexport geldt daarbij niet als voldoende bewijs wanneer alleen de SHA-256 klopt: tijdens de eerste VPS-validatie moet de export daadwerkelijk naar een aparte wegwerp-herstelomgeving worden teruggezet en moet representatieve tenantdata na restore worden gecontroleerd. Pas na VPS-readiness, bootstrap, infrastructuur-, lifecycle-, herstel- en release/rollbackacceptatie wordt de eerste VPS productiegeschikt verklaard.',
    ],
    [
        'datum' => '2026-08-21',
        'cat' => 'beveiliging',
        'titel' => 'Productiebootstrap en control-plane verder gehard',
        'tekst' => 'Fase 5.2.1 sluit de resterende productiegrenzen rond release, first-VPS bootstrap, lifecycle en platformbeheer. Privileged childprocessen gebruiken nu één deadlock-veilige runner zonder shell en alleen absolute executables. De first-VPS flow bewijst vóór de eerste mutatie de production preflight, root-owned source-tree, exact Git repository-root, exacte geplande commit en een schone working tree. Daarnaast zijn release-state, purge/recovery, control-plane identity/auth/rate limiting en queue/resultaatmetadata fail-closed aangescherpt en door aparte regressietests afgedekt.',
    ],
    [
        'datum' => '2026-08-21',
        'cat' => 'verbeterd',
        'titel' => 'Hervatbare first-VPS productiebootstrap voorbereid',
        'tekst' => 'Fase 5.2 verbindt de immutable release, platformbeheer-DNS/TLS, control-plane en de bestaande tenantinfrastructuur tot één checkpoint-gebaseerde eerste VPS-installatie. De bootstrap installeert geen packages en schrijft niet naar DNS-providers. Platformoperator en eerste tenantbeheerder worden uitsluitend via STDIN gebootstrapt. Een onderbroken installatie kan alleen met exact hetzelfde cryptografisch gebonden plan worden hervat; PostgreSQL wordt vóór tenant-FPM activatie ingericht en de flow eindigt pas na aantoonbare platform- en tenanthealth.',
    ],
    [
        'datum' => '2026-08-21',
        'cat' => 'beveiliging',
        'titel' => 'Aparte platformbeheer-control-plane voorbereid',
        'tekst' => 'Fase 5.1 voegt een aparte superbeheer-GUI toe boven de tenant lifecycle. De webapp draait bewust zonder rootrechten en kan alleen strikt gevalideerde lifecycle-aanvragen in een queue plaatsen. Een afzonderlijke root-executor controleert operator, tenant, requestleeftijd, bevestigingen en het actuele fase-4.8-plan opnieuw voordat een vaste lifecycleactie wordt uitgevoerd. Platformoperators gebruiken een apart bcrypt-wachtwoordbestand buiten Git; tenantbeheerders krijgen geen toegang tot deze control-plane.',
    ],
];