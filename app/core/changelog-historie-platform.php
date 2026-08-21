<?php
return [
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