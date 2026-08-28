# Roadmap verenigingsplatform

Status per **28-08-2026**.

Dit document is de centrale nummering voor het platformtraject. Detailcontracten staan in de genoemde VPS-documentatie en historische beslissingen blijven in `docs/migratie-log/` bewaard.

## Fase 1 t/m 3 — applicatie en multi-tenantbasis

Afgerond:

- **Fase 1 — template-ready**
- **Fase 2 — modulair beheer**
- **Fase 3.1 — tenant boundary**
- **Fase 3.2 — tenant provisioner**
- **Fase 3.2.1 — security hardening**
- **Fase 3.3 — tweede tenantbewijs**
- **Fase 3.4 — veilige eerste beheerder**
- **Fase 3.5 — VPS deploymentcontract**
- **Fase 3.5.1 — security heraudit fixes**

De gedeelde applicatie kan daarmee meerdere verenigingen vanuit dezelfde codebase draaien met tenantgebonden configuratie, private opslag, auth/sessies, backups, assets, branding en modules.

## Fase 4 — VPS & productie-infrastructuur

**Status: 4.1 t/m 4.8 code/CI/merge én echte VPS-validatie afgerond.** De productiecontracten zijn op 28 augustus 2026 op de eerste Ubuntu 26.04-VPS toegepast en geaccepteerd, inclusief live DNS/TLS, PostgreSQL-isolatie, monitoring, lifecycle/export+restore en release/rollback.

### 4.1 — VPS runtime & Linux-isolatie
**Status: code, CI en echte VPS-validatie afgerond op 28 augustus 2026.**
Per tenant een unieke no-login Linux-user/group, eigen PHP-FPM pool/socket en private session/tmp-opslag. Gedeelde code blijft centraal read-only. 4.1.1 heraudit controleert UID/GID-collisions en actieve processen fail-closed.
Zie `docs/VPS-RUNTIME-ISOLATION.md`.

### 4.2 — Apache webserver & vhosts
**Status: code, CI en echte VPS-validatie afgerond op 28 augustus 2026.**
Exacte ServerName, neutrale default-vhost, vaste redirects zonder Host-reflectie, per-tenant FPM-routing en server-side blokkade van private/tooling/VCS-paden.
Zie `docs/VPS-WEBSERVER.md`.

### 4.3 — DNS readiness
**Status: code, CI en live DNS-validatie afgerond op 28 augustus 2026.**
Direct A/AAAA of één CNAME-hop, exacte RRset-match en minimaal drie consistente live resolvermetingen. Geen DNS-providercredentials of automatische providerwrites in tenantbundles.
Zie `docs/VPS-DNS.md`.

### 4.4 — TLS/HTTPS
**Status: code, CI en echte certificaatuitgifte/renewalvalidatie afgerond op 28 augustus 2026.**
Certbot webroot HTTP-01, neutrale HTTPS-catchall, exacte Host+SNI-binding, certificaat/key-validatie, HSTS en Apache configtest vóór reload/activatie.
Zie `docs/VPS-TLS.md`.

### 4.5 — PostgreSQL provisioning
**Status: code, CI en echte PostgreSQL-provisioning/isolatievalidatie afgerond op 28 augustus 2026.**
PostgreSQL 16+, één database per tenant, aparte NOLOGIN-owner, app-role gelijk aan de Linux/FPM-user en Unix-socket peer authentication zonder databasewachtwoord. 4.5.1 houdt de app-role NOLOGIN totdat HBA en least privilege aantoonbaar veilig zijn.
Zie `docs/VPS-DATABASE.md`.

### 4.6 — Monitoring & logging
**Status: code, CI en echte service/timer/log-validatie afgerond op 28 augustus 2026.**
Informatie-arme healthcheck, lokale Apache/FPM/PostgreSQL/TLS/app/disk-probes, privacyarme operationele logging, 14 dagen retentie en gededupliceerde alerts.
Zie `docs/VPS-MONITORING.md`.

### 4.7 — Release & rollback automation
**Status: code, CI en echte releasewissel/rollback afgerond op 28 augustus 2026.**
Immutable releases per commit, inhoudsmanifest, atomische `current`-wissel, kandidaattenantpreflight, volledige health na activatie en automatische/handmatige rollback naar uitsluitend de vorige gevalideerde release.
Zie `docs/VPS-RELEASES.md`.

### 4.8 — Tenant lifecycle
**Status: code, CI en echte lifecycle/export/restorevalidatie afgerond op 28 augustus 2026.**
Adopteren, uitschakelen, heractiveren, volledige export, `pending_delete`, 24 uur wachttijd, definitieve purge en crash-recovery. Lifecycle-state/audit zijn root-owned en tenantgebonden. DNS-providerrecords worden nooit automatisch verwijderd.
Zie `docs/VPS-LIFECYCLE.md`.

## Fase 5 — platformbeheer & productie-control-plane

### 5.1 — Platformbeheer / superbeheer GUI

**Status: code, CI, installatie en echte VPS-acceptatie afgerond op 28 augustus 2026.**

Omvat:

- aparte platformbeheer-host en Apache-vhost;
- aparte no-login Linux/PHP-FPM identity `vst-control`;
- Apache Basic Auth over TLS met bcrypt-operatorbestand buiten Git;
- CSRF-beveiligde GUI met tenantstatus, health, exportstatus en purge-wachttijd;
- gewone verenigingsbeheerders hebben geen toegang tot de control-plane;
- de webapp kan geen OS/rootprocessen starten;
- mutaties gaan uitsluitend via een strikt allowlisted JSON-queue;
- root-owned systemd `.path` unit start een aparte executor;
- requests verlopen na 15 minuten en worden volledig opnieuw gevalideerd;
- executor herverifieert het actuele fase-4.8 lifecycle-plan vóór iedere mutatie;
- destructieve bevestigingen en export-SHA worden opnieuw door 4.8 gecontroleerd;
- platformstatus toont geen tenantsecrets of private exportpaden;
- GUI-tijden worden weergegeven in `Europe/Amsterdam` als `dd-mm-jjjj HH:mm:ss`;
- definitieve HTTP-routing houdt alleen Certbot HTTP-01 bereikbaar en stuurt alle overige paden vast naar HTTPS.

Zie `docs/VPS-CONTROL-PLANE.md`.

### 5.2 — First-VPS productiebootstrap

**Status: code, CI, merge en echte first-VPS-productietoepassing afgerond en geaccepteerd op 28 augustus 2026.**

Doel: de handmatige productievoorbereiding reduceren tot één gecontroleerde, hervatbare operatorflow. De implementatie omvat:

- Debian/Ubuntu basispreflight zonder automatische package-installatie;
- volledige production preflight vóór de eerste mutatie, inclusief Apache/PHP-FPM/Fail2ban-configtests, servicechecks en PostgreSQL `SELECT 1`;
- expliciete Git source-binding: exact repository-root, exact geplande 40-hex commit en schone working tree;
- root-owned, niet group/world-writable bronboom en manifestbinding vóór first-release bootstrap;
- fase-4.7 immutable first-release bootstrap vóór iedere andere productieconfig;
- platformbeheer-DNS readiness met exacte A/AAAA of één CNAME-hop;
- neutrale HTTP/HTTPS catch-alls vóór het eerste publieke certificaat;
- eerste platformbeheer-Certbot lineage via HTTP-01 webroot;
- veilige bootstrap van de eerste platformoperator via STDIN;
- installatie/activatie van fase 5.1;
- bootstrap van de eerste tenant door de bestaande 3.2/3.4 en 4.1 → 4.8 keten;
- PostgreSQL provisioning vóór tenant-FPM activatie;
- tenant-DNS/TLS/monitoring/lifecycle uitsluitend via de reeds geteste onderliggende tools;
- root-owned checkpoints en `--resume`, cryptografisch gebonden aan exact hetzelfde 5.2-plan;
- deadlock-veilige gedeelde subprocess-runner die stdout/stderr gelijktijdig draint, shellgebruik omzeilt en alleen absolute executablepaden accepteert;
- vaste, vooraf geverifieerde absolute binaries voor privileged productiehandelingen;
- fail-closed metadata- en deletecontroles voor lifecycle, control-plane queue/resultaten en release-state;
- eindcontrole op control-plane Basic Auth en tenanthealth;
- geen DNS-providercredentials of providerwrites in de generieke codebase.

Zie `docs/VPS-FIRST-BOOTSTRAP.md`.

### 5.2.1 — Production security hardening

**Status: afgerond en gemerged naar `main` via PR #37; definitieve PR-validatie was groen.**

Deze heraudit sloot de resterende productiegrenzen rond 4.1 t/m 5.2: immutable release-metadata, purge/recovery-binding, control-plane identity/auth/rate limiting, executor-resultaatintegriteit, subprocess-deadlocks, absolute binary-binding, production preflight en Git source/commit-binding.

### Pre-VPS eindacceptatie — 22 augustus 2026

**Status: afgerond.** De applicatie- en DEV-laag is vóór fase 5.3 nog één keer van voor tot achter geregressietest.

Bewezen zijn onder meer:

- volledige bron-, functionele, technische en securityregressie;
- actieve securitycontrole op de echte DEV-host;
- publieke browseracceptatie op desktop, tablet en mobiel;
- authenticated beheer- en leden-E2E met tijdelijke willekeurige accounts;
- gekoppeld synthetisch testlid met persoonsgegevens, contributie, commissie, vergadering/notulen en taak;
- visuele screenshots van ingelogde beheer- en ledenroutes op desktop, tablet en mobiel;
- volledige restore van de tijdelijk gewijzigde DEV auth- en ledenbestanden na de tests.

De gecombineerde pre-VPS gate eindigde groen. De gekozen en bevroren VPS-kandidaat is:

`936cf4879f1611d94123fb3d3a0a33b831a49810`

Zie `docs/FULL-REGRESSION-ACCEPTANCE.md` en issue #39.

### 5.3 — Eerste echte VPS-validatie

**Status: afgerond en volledig groen op 28 augustus 2026.**

De eerste Ubuntu 26.04-VPS is met één testtenant end-to-end geaccepteerd. Bewezen zijn:

- first-VPS bootstrap, control-plane en tenantbeheer;
- publieke tenant via HTTPS, tenantbeheerlogin en informatie-arme healthcheck;
- Apache, PHP 8.5-FPM, PostgreSQL socket-only/peer-isolatie, Certbot, Fail2ban, monitoring en logrotate;
- suspend → activate → export;
- SHA-256-verificatie en daadwerkelijke restore van de tenant-export in een geïsoleerde wegwerpomgeving;
- immutable releasewissel, handmatige rollback en terugschakeling;
- ongewijzigde tenantconfiguratie en canoniek gelijke database-inhoud vóór/na de releasecyclus.

Finale release-state:

- actief: `d819446b9516bb98a580a88da448487c16383f2e`;
- previous: `7bab3d1f7e87b7d01311b41bb53e4c66dfcbb39b`;
- transition: `null`;
- gevalideerde tenants: `1`.

De optionele destructieve purge op een extra wegwerptenant is bewust niet uitgevoerd en was geen acceptatievoorwaarde. De eerste VPS is productiegeschikt verklaard voor gecontroleerde onboarding van verenigingen.

Zie `docs/migratie-log/2026-08-28-fase-5-3-vps-acceptatie.md` en issue #39.

## Niet-blokkerende technische schuld

Na fase 2 zijn nog enkele compatibiliteits-/opruimpunten bekend. Deze blokkeren fase 5.3 niet:

- fysiek runtime-dode legacyformuliercode in `beheer/index.php`;
- enkele oudere JSON-writers die nog niet op één gedeelde atomische writer zijn gestandaardiseerd;
- publieke legacy-markup die runtime door de templatefilter wordt gecorrigeerd;
- legacy standalone masterconfigcompatibiliteit buiten nieuwe VPS-tenants.

De eerder genoteerde RC045-specifieke functienamen/variabelen/comments en RC045-stijl-fallbacks zijn niet meer aanwezig in de actuele templatecode en zijn daarom uit deze schuldlijst verwijderd.

Deze resterende punten horen bij een latere mechanische opschoningsfase en mogen niet ongemerkt onderdeel worden van de eerste productiebootstrap.

## Volgorde vanaf nu

**Fase 5.3 geaccepteerd → eerste VPS productiegeschikt → gecontroleerde onboarding van verenigingen → operationeel monitoren, onderhouden en vervolgverbeteringen via afzonderlijke issues/PR's.**

Een fase kan code/automation al gereed hebben voordat de daadwerkelijke productiehandeling op de VPS is uitgevoerd. Dat verschil blijft per fase expliciet vermeld; **“code gereed” is nooit hetzelfde als “op productie toegepast/gevalideerd”.**
