# Fase 5.3 — eerste echte VPS-validatie afgerond

Datum: 28 augustus 2026  
Status: **GROEN**

## Doel

Alle code- en CI-gereed gemaakte productieonderdelen van fase 4.1 tot en met 5.2 aantoonbaar op de eerste echte VPS uitvoeren en accepteren voordat reguliere verenigingen worden onboard.

## Geaccepteerde omgeving

- OS: Ubuntu Server 26.04 LTS;
- platformbeheerhost: `vps.holox.nl`;
- eerste tenant: `test` / Testvereniging;
- tenanthost: `test.vps.holox.nl`;
- PHP: 8.5 CLI en FPM;
- PostgreSQL: socket-only met peer-authenticatie;
- finale actieve release: `d819446b9516bb98a580a88da448487c16383f2e`;
- finale previous-release: `7bab3d1f7e87b7d01311b41bb53e4c66dfcbb39b`.

## Bootstrap, control-plane en tenant

De first-VPS bootstrap is volledig uitgevoerd. Daarna zijn onder meer bewezen:

- platformbeheerhost via HTTPS;
- anonieme control-plane-toegang blijft HTTP 401;
- platformoperator kan inloggen;
- publieke tenant geeft HTTP 200;
- tenant `/healthz.php` geeft HTTP 204;
- tenantbeheer via de eigen host geeft HTTP 200;
- echte tenantbeheerlogin geeft POST 302 en daarna een ingelogde beheerpagina met HTTP 200;
- release-state is intern consistent en bevat geen openstaande transition.

## Infrastructuuracceptatie

De eindcontrole bewees:

- Apache-configuratie geldig en service actief;
- PHP 8.5-FPM-configuratie geldig, service actief en tenant-/control-plane-sockets aanwezig;
- PostgreSQL actief, `listen_addresses` leeg en geen TCP-listener op poort 5432;
- tenant peer-login naar de eigen database werkt;
- toegang van de tenantrol tot database `postgres` wordt fail-closed geweigerd;
- Certbot-lineages, SAN-hosts, webroot-renewal en deploy-hook correct;
- Fail2ban control-plane jail actief;
- officiële monitoringprobe `UP` met 10 geslaagde checks;
- healthtimer actief, enabled en daadwerkelijk uitgevoerd;
- globale en tenantspecifieke logrotateconfiguratie valide en servicecyclus succesvol;
- live tenant-DNS voldoet aan het provisioningplan.

## Lifecycle, export en restore

Op tenant `test` zijn suspend en activate succesvol uitgevoerd. Na activate gaf de healthcheck opnieuw HTTP 204.

De volledige tenant-export:

- bestand: `20260827_145236-f2d850d7-tenant-export.tar.gz`;
- SHA-256: `f00de946cb8fa55ef36c0e557101425d48cd4ee1c9878d118eb2f4f9fbd0688e`;
- inhoud: `export-manifest.json`, `database.dump` en `tenant-files.tar.gz`.

De export is niet alleen op checksum gecontroleerd, maar ook daadwerkelijk in een geïsoleerde wegwerp-herstelomgeving teruggezet. Database en tenantbestanden/configuratie zijn inhoudelijk gecontroleerd; de tijdelijke database en herstelmap zijn daarna opgeruimd.

## Release-switch, rollback en data-integriteit

De finale acceptatiecyclus bestond uit:

1. uitgangsstatus active `d819446b...`, previous `7bab3d1f...`;
2. handmatige rollback naar `7bab3d1f...`: `ROLLBACK OK`, tenant HTTP 200 en health HTTP 204;
3. release-state na rollback: active `7bab3d1f...`, previous `d819446b...`, transition `null`;
4. opnieuw voorbereiden en controleren van releaseplan `d819446b...`: `CHECK OK`, 335 bestanden;
5. terugschakelen naar `d819446b...`: `DEPLOY OK`, één tenant;
6. finale state: active `d819446b...`, previous `7bab3d1f...`, transition `null`;
7. officiële healthprobe opnieuw `UP` en Apache opnieuw `Syntax OK`.

Data-integriteit:

- tenantconfiguratie SHA-256 bleef `91d772f80e19305f3e05731791f3d507798ddcb27062a7bc6586ec5be69c2956`;
- canonieke database-SHA-256 live en uit de gevalideerde export waren beide `c8385a285f48d4c89bd63e4bb4f94d6ceb7e71e11ed7616b56e76a51e0244642`.

Voor de databasevergelijking is canonieke SQL gebruikt waarbij de willekeurige PostgreSQL `pg_dump`-restrictiesleutel buiten de hash blijft. Daardoor vergelijkt de hash de database-inhoud en niet uitvoeringsruis.

## Live bevindingen en fixes

Tijdens fase 5.3 zijn twee productieblokkades gevonden en structureel opgelost:

- PR #91: veilige traverse-rechten voor de gedeelde `/etc/verenigingsplatform`-parent, met PostgreSQL-submappen blijvend afgeschermd;
- PR #92: acceptatie van een reeds correct met FPM `php_admin_value` vastgezet `session.save_path`, zonder fail-closed tenantisolatie te verzwakken.

Beide fixes zijn opgenomen in `d819446b...`, CI-groen en live geaccepteerd.

## Bewuste uitsluiting

De optionele destructieve purge is niet uitgevoerd. Deze test was uitsluitend bedoeld voor een extra wegwerptenant, was geen acceptatievoorwaarde en blokkeert de productiegeschiktheid niet.

## Conclusie

Fase 5.3 is volledig afgerond. De productiecontracten van fase 4.1 tot en met 5.2 zijn op de eerste echte VPS gevalideerd. De VPS is productiegeschikt voor gecontroleerde onboarding van verenigingen.
