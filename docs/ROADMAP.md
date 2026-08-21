# Roadmap verenigingsplatform

Status per **21-08-2026**.

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

**Ontwikkelstatus: 4.1 t/m 4.8 code/CI/merge/DEV afgerond.** De daadwerkelijke root-toepassing en live DNS/TLS/databasehandelingen volgen pas op de echte productie-VPS.

### 4.1 — VPS runtime & Linux-isolatie
**Status: code en CI gereed; root-toepassing volgt op productie.**
Per tenant een unieke no-login Linux-user/group, eigen PHP-FPM pool/socket en private session/tmp-opslag. Gedeelde code blijft centraal read-only. 4.1.1 heraudit controleert UID/GID-collisions en actieve processen fail-closed.
Zie `docs/VPS-RUNTIME-ISOLATION.md`.

### 4.2 — Apache webserver & vhosts
**Status: code en CI gereed; productie-activatie volgt op productie.**
Exacte ServerName, neutrale default-vhost, vaste redirects zonder Host-reflectie, per-tenant FPM-routing en server-side blokkade van private/tooling/VCS-paden.
Zie `docs/VPS-WEBSERVER.md`.

### 4.3 — DNS readiness
**Status: code en CI gereed; echte providerrecords/readiness volgen op productie.**
Direct A/AAAA of één CNAME-hop, exacte RRset-match en minimaal drie consistente live resolvermetingen. Geen DNS-providercredentials of automatische providerwrites in tenantbundles.
Zie `docs/VPS-DNS.md`.

### 4.4 — TLS/HTTPS
**Status: code en CI gereed; certificaatuitgifte volgt op productie.**
Certbot webroot HTTP-01, neutrale HTTPS-catchall, exacte Host+SNI-binding, certificaat/key-validatie, HSTS en Apache configtest vóór reload/activatie.
Zie `docs/VPS-TLS.md`.

### 4.5 — PostgreSQL provisioning
**Status: code en CI gereed; echte databases volgen op productie.**
PostgreSQL 16+, één database per tenant, aparte NOLOGIN-owner, app-role gelijk aan de Linux/FPM-user en Unix-socket peer authentication zonder databasewachtwoord. 4.5.1 houdt de app-role NOLOGIN totdat HBA en least privilege aantoonbaar veilig zijn.
Zie `docs/VPS-DATABASE.md`.

### 4.6 — Monitoring & logging
**Status: code en CI gereed; installatie volgt op productie.**
Informatie-arme healthcheck, lokale Apache/FPM/PostgreSQL/TLS/app/disk-probes, privacyarme operationele logging, 14 dagen retentie en gededupliceerde alerts.
Zie `docs/VPS-MONITORING.md`.

### 4.7 — Release & rollback automation
**Status: code en CI gereed; echte releasewissels volgen op productie.**
Immutable releases per commit, inhoudsmanifest, atomische `current`-wissel, kandidaattenantpreflight, volledige health na activatie en automatische/handmatige rollback naar uitsluitend de vorige gevalideerde release.
Zie `docs/VPS-RELEASES.md`.

### 4.8 — Tenant lifecycle
**Status: code en CI gereed; lifecyclemutaties volgen op productie.**
Adopteren, uitschakelen, heractiveren, volledige export, `pending_delete`, 24 uur wachttijd, definitieve purge en crash-recovery. Lifecycle-state/audit zijn root-owned en tenantgebonden. DNS-providerrecords worden nooit automatisch verwijderd.
Zie `docs/VPS-LIFECYCLE.md`.

## Fase 5 — platformbeheer & productie-control-plane

### 5.1 — Platformbeheer / superbeheer GUI

**Status: code, CI en merge gereed; productie-installatie volgt via fase 5.2.**

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

**Status: code gereed en volledig gehard in 5.2.1; CI op de kandidaat-PR is groen. Productietoepassing blijft uitgesteld tot de echte VPS.**

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

**Status: kandidaatimplementatie afgerond; volledige regressiematrix groen.**

Deze heraudit sluit de resterende productiegrenzen rond 4.7 t/m 5.2: immutable release-metadata, purge/recovery-binding, control-plane identity/auth/rate limiting, executor-resultaatintegriteit, subprocess-deadlocks, absolute PATH-binding, production preflight en Git source/commit-binding. De volgende stap is de kandidaat-PR afronden en mergen; daarna volgt pas de echte VPS-validatie.

## Volgorde vanaf nu

De ontwikkelvolgorde is:

**5.2.1 PR afronden/merge → echte VPS-validatie met een eerste tenant → verdere platformfuncties.**

Een fase kan code/automation al gereed hebben voordat de daadwerkelijke productiehandeling op de VPS is uitgevoerd. Dat verschil blijft per fase expliciet vermeld; **“code gereed” is nooit hetzelfde als “op productie toegepast”.**
