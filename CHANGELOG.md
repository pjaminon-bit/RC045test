# Changelog

Belangrijke platformwijzigingen en acceptatiemijlpalen worden hier chronologisch vastgelegd. Historische technische details blijven daarnaast beschikbaar in `docs/migratie-log/` en de fasegerichte documentatie.

## 2026-08-22 — Pre-VPS eindacceptatie afgerond

### Acceptatie

- volledige bron-, functionele, technische en securityregressie opnieuw uitgevoerd;
- actieve securityacceptatie tegen de echte DEV-host groen;
- publieke Playwright-acceptatie groen op desktop, tablet en mobiel;
- authenticated beheer- en leden-E2E toegevoegd en groen bewezen met tijdelijke willekeurige accounts;
- synthetisch gekoppeld testlid gebruikt voor persoonsgegevens, contributie, commissie, vergadering/notulen en taken;
- ingelogde beheer- en ledenroutes op desktop, tablet en mobiel visueel gecontroleerd;
- tijdelijke DEV auth- en ledenfixtures worden na iedere run exact hersteld;
- permanente regressiesuite voert alle PHP-tests in `tests/` uit zodat nieuwe tests niet ongemerkt buiten CI vallen.

### Security- en browserfixes uit de regressieronde

- PHP-runtimelek via `X-Powered-By` gesloten met `expose_php = Off` en Apache defense-in-depth;
- standalone DEV-contentfallbacks gehard zodat ontbrekende/ongeldige overrides geen browser-404/500 veroorzaken, terwijl externe tenants fail-closed blijven;
- ontbrekende DEV-template/runtimeafbeeldingen vervangen door een lokale deterministische neutrale placeholder;
- tablet/mobile overflow in navigatie en grid-layout hersteld;
- bedieningselementen met te kleine touch targets vergroot;
- `landcode` toegankelijk gelabeld;
- verplichte aanmeldvelden voorzien van native `required`-semantiek;
- leden-loginbediening op bruikbare minimale afmetingen gebracht;
- browsertest verbeterd zodat pagina's eerst volledig worden gescrold en scroll-/IntersectionObserver-content daadwerkelijk zichtbaar moet worden.

### Pre-VPS release

- definitieve VPS-kandidaat bevroren op `936cf4879f1611d94123fb3d3a0a33b831a49810`;
- issue #50 authenticated live E2E afgerond en gesloten;
- alleen fase 5.3 / issue #39 blijft als productieacceptatie open;
- fase 5.3 vereist voortaan naast export + SHA-256 ook een daadwerkelijke restore naar een wegwerp-herstelomgeving voordat de VPS productiegeschikt wordt verklaard.

## 2026-08-21/22 — Fase 5.2.1 production security hardening

- productiepreflight vóór de eerste rootmutatie aangescherpt;
- exacte Git repository-root, geplande commit en schone working tree gebonden aan first-VPS bootstrap;
- gedeelde deadlock-veilige subprocess-runner ingevoerd;
- privileged subprocessen op vooraf gecontroleerde absolute binaries vastgezet;
- runtime, webserver, database, monitoring, release, lifecycle en control-plane uitvoeringspaden gehard;
- lifecycle/control-plane/release metadata- en integriteitscontroles fail-closed gemaakt;
- PR #37 volledig groen gemerged naar `main`.

## 2026-08 — Fase 4 en 5 productieplatform

- VPS runtime- en Linux-isolatie per tenant;
- Apache/vhost-, DNS-, TLS- en PostgreSQL-provisioning;
- monitoring, logging en healthchecks;
- immutable releases met healthgestuurde rollback;
- tenant lifecycle voor adopt/suspend/activate/export/delete/purge/recovery;
- aparte platformbeheer/control-plane met root-owned executorqueue;
- hervatbare first-VPS productiebootstrap;
- code/CI-gereed status expliciet gescheiden van echte VPS-validatie.

## 2026-08 — Fase 1 t/m 3 template en multi-tenantbasis

- RC045-codebase omgebouwd naar herbruikbare verenigingstemplate;
- modulair beheer en capabilitymodel;
- tenant boundary en tenantgebonden configuratie/opslag;
- veilige provisioner en tweede-tenantisolatie;
- eerste beheerderbootstrap;
- deploymentcontract en security heraudit.
