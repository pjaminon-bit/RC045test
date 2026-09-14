# VPS deploymentcontract

Dit document is de **actuele architectuur- en navigatie-ingang** voor deployment van het multi-tenant verenigingsplatform op de VPS. De oorspronkelijke fase-4-opbouw is gerealiseerd: tenantprovisioning, Linux/PHP-FPM-isolatie, Apache-vhosts, DNS, TLS, PostgreSQL/PDO, monitoring, immutable releases/rollback, tenant lifecycle, control-plane en de GitHub→VPS-test deployflow hebben elk een eigen operationeel contract.

Historische fasenummers in specialistische documenten beschrijven de herkomst van een contract; ze betekenen niet dat de betreffende voorziening nog toekomstig is.

## Canonieke operationele documenten

| Onderwerp | Canonieke documentatie |
|---|---|
| Nieuwe tenant, configuratie- en filesystemisolatie | [PROVISIONING.md](PROVISIONING.md) |
| Eerste beheerder voor een tenant | [ADMIN-BOOTSTRAP.md](ADMIN-BOOTSTRAP.md) |
| Eerste VPS/host-bootstrap | [VPS-FIRST-BOOTSTRAP.md](VPS-FIRST-BOOTSTRAP.md) |
| VPS readiness, OS en runtimevereisten | [VPS-READINESS.md](VPS-READINESS.md) |
| Linux-user, PHP-FPM-pool en private runtime | [VPS-RUNTIME-ISOLATION.md](VPS-RUNTIME-ISOLATION.md) |
| Apache-vhosts, public-root en canonical-host routing | [VPS-WEBSERVER.md](VPS-WEBSERVER.md) |
| DNS | [VPS-DNS.md](VPS-DNS.md) |
| TLS/certificaten | [VPS-TLS.md](VPS-TLS.md) |
| PostgreSQL/PDO-tenantbinding | [VPS-DATABASE.md](VPS-DATABASE.md) |
| Immutable releases en rollback | [VPS-RELEASES.md](VPS-RELEASES.md) |
| Monitoring en healthchecks | [VPS-MONITORING.md](VPS-MONITORING.md) |
| Tenant disable/export/remove | [VPS-LIFECYCLE.md](VPS-LIFECYCLE.md) |
| GitHub Actions → VPS-test deployflow | [GITHUB-VPS-TEST-DEPLOYMENT.md](GITHUB-VPS-TEST-DEPLOYMENT.md) |
| Authenticated live E2E | [VPS-AUTHENTICATED-E2E.md](VPS-AUTHENTICATED-E2E.md) |
| Platform control-plane | [VPS-CONTROL-PLANE.md](VPS-CONTROL-PLANE.md) |
| Control-plane provisioning | [VPS-CONTROL-PLANE-PROVISIONING.md](VPS-CONTROL-PLANE-PROVISIONING.md) |
| Deployuser SSH-hardening | [VST-DEPLOY-SSHD-HARDENING.md](VST-DEPLOY-SSHD-HARDENING.md) |
| Cryptografische backupattestatie | [BACKUP-ATTESTATION.md](BACKUP-ATTESTATION.md) |
| Volledige bron/live regressie | [FULL-REGRESSION-ACCEPTANCE.md](FULL-REGRESSION-ACCEPTANCE.md) |

De [README](../README.md) is de repository-ingang. `ROADMAP.md` bevat de actuele platformstatus; operationele procedures horen in de gespecialiseerde documenten hierboven.

## Leidende VPS-architectuur

```text
/srv/verenigingsplatform/
├── current -> releases/<40-hex-commit>
└── releases/
    └── <commit>/
        ├── app/                 # server-only applicatiecode
        ├── bin/                 # server-only tooling
        ├── docs/                # niet publiek
        └── public/              # enige web-DocumentRoot
            └── index.php

/srv/verenigingen/
└── <tenant>/
    ├── config.php
    ├── runtime.env
    ├── tenant.json
    ├── deployment.json
    ├── runtime/
    └── private/
```

De harde grens is:

- gedeelde applicatiecode staat in een immutable release onder `/srv/verenigingsplatform/releases/<commit>`;
- `current` verwijst atomisch naar één fysieke release;
- **alleen `current/public` is de web-DocumentRoot**;
- de release-root en interne code blijven buiten de DocumentRoot en worden niet als webcontent gepubliceerd;
- tenantconfiguratie en mutable/private data staan onder `/srv/verenigingen/<tenant>` en nooit in de gedeelde release;
- de tenantroot/private root is nooit een documentroot of algemene statische webmap;
- iedere tenant heeft een eigen Linux-runtime-identiteit, PHP-FPM-pool en Unix-socket;
- permanente privileged host-entrypoints staan buiten de applicatierelease en voeren geen repository-/release-PHP als root uit.

## Tenantbinding en fail-closed runtime

Nieuwe VPS-tenants gebruiken een externe server-side configuratie. De runtime moet minimaal aan de eigen tenant gebonden zijn met:

```text
VERENIGING_REQUIRE_TENANT_CONFIG=1
VERENIGING_CONFIG_FILE=/srv/verenigingen/<tenant>/config.php
VERENIGING_PRIVATE_ROOT=/srv/verenigingen/<tenant>/private
```

Ontbrekende, onleesbare of inconsistente verplichte tenantconfiguratie mag niet terugvallen naar RC045/default- of standaloneconfiguratie. `deployment.json`, runtimeplan, webserverconfiguratie en databasebinding moeten dezelfde tenantidentiteit bewijzen.

De Ubuntu 26.04-productiebaseline gebruikt PHP 8.5. De volledige generatie-, controle- en applyprocedure staat in `VPS-RUNTIME-ISOLATION.md`.

## Web- en netwerkgrenzen

De canonieke VPS-stack gebruikt Apache 2.4. Voor iedere tenant gelden onder meer:

- een globale HTTP/HTTPS catch-all weigert onbekende hosts voordat een tenantvhost kan matchen;
- HTTP→HTTPS gebruikt de vaste canonieke host en spiegelt geen client-`Host` terug;
- de DocumentRoot is exact de minimale `public/`-subdirectory van de actieve immutable release;
- de applicatierelease-root zelf is geen DocumentRoot;
- interne applicatiecode ligt fysiek buiten de publieke root;
- alleen `public/index.php` is de fysieke PHP/FPM-entrypoint;
- PHP voor een tenanthost gaat uitsluitend naar de eigen PHP-FPM-socket;
- tenant-private paden worden niet rechtstreeks geserveerd;
- aliases naar bestanden buiten de minimale public-root zijn verboden.

Dit model vervangt het oudere full-release-DocumentRootmodel met een primaire denylist voor interne directories. Zie `VPS-WEBSERVER.md` voor het actuele contract.

## Database en private data

Nieuwe VPS-tenants gebruiken canoniek tenantgebonden PostgreSQL/PDO conform `VPS-DATABASE.md`. Databasecredentials horen niet in Git, `deployment.json` of de gedeelde release. Private tenantdata blijft onder de tenant-private storagegrens of in de tenantgebonden database.

Legacy PHP+JSON-opslag bestaat voor standalone/templatecompatibiliteit. Dat is geen architectuurbron voor nieuwe VPS-tenants.

## `.htaccess` en release-inhoud

De repository bevat `.htaccess` als versiebeheerd bestand voor compatibiliteit en publieke routing/defense-in-depth. Op de VPS is `.htaccess` niet de primaire grens die interne repositorycode beschermt: die code ligt buiten `public/`.

Er is geen handmatige FTP-uploadstap in de VPS-deployflow. Releases komen uitsluitend via de gecontroleerde immutable release/deployketen.

## Immutable deployment en rollback

Normale VPS-updates overschrijven de actieve website niet in-place. De releaseflow:

1. bindt de kandidaat aan een exacte Git-commit en deterministisch manifest;
2. valideert kandidaat en actuele tenants;
3. staged een root-owned, read-only release;
4. test configuratie/PHP/database/webservergrenzen;
5. wisselt `current` atomisch;
6. reloadt de betrokken PHP-FPM-services;
7. voert healthchecks uit;
8. rolt automatisch terug wanneer activatie of post-switch health niet veilig kan worden bewezen.

Mutable tenantdata, sessies, uploads, databases en tenantconfiguratie blijven buiten de releasewissel. Zie `VPS-RELEASES.md` voor het volledige contract.

## GitHub → VPS-test

Voor `RC045test` is `GITHUB-VPS-TEST-DEPLOYMENT.md` leidend. De normale keten is:

1. verplichte PR-gates op GitHub;
2. merge naar `main`;
3. post-merge validatie en lineagecontrole;
4. restricted deployverzoek voor exact één toegestane commit;
5. root-owned hostwrapper activeert via de vertrouwde release-engine de immutable kandidaat;
6. smoke en authenticated beheer/leden-E2E;
7. fixturecleanup en credentialchecks;
8. automatische post-deploy Full Regression met `source-regression`, `live-security` en `live-browser`.

De GitHub-runner krijgt geen algemene root-shell en kopieert geen losse bestanden rechtstreeks over de actieve website.

## Standalone versus VPS

**Multi-tenant VPS**
- gedeelde immutable code;
- alleen `current/public` als DocumentRoot;
- externe fail-closed tenantconfig;
- tenant-private filesystem/PDO;
- eigen Linux-user + FPM-pool;
- gecentraliseerde webserver/DNS/TLS/database/release/lifecyclecontracten;
- normale deployment via de gecontroleerde releaseketen.

**Standalone/templatecompatibiliteit**
- kan lokale configuratie en legacy PHP+JSON-opslag gebruiken;
- kan Apache `.htaccess` als aanvullende denylaag gebruiken;
- is bedoeld voor bestaande/losse installaties en migratiecompatibiliteit;
- mag niet als ontwerpbron worden gebruikt voor nieuwe VPS-tenants.

Voor nieuwe platformtenants begint de route bij `PROVISIONING.md`, gevolgd door de relevante VPS-contracten uit de tabel bovenaan.
