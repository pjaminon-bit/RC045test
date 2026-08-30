# Changelog

Belangrijke platformwijzigingen en acceptatiemijlpalen worden hier chronologisch vastgelegd. Historische technische details blijven daarnaast beschikbaar in `docs/migratie-log/` en de fasegerichte documentatie.

## 2026-08-30 — Fase 5.9/5.9.1 VPS-test browseracceptatie groen

### Live herstel externe tenant

- PR #101 herstelde de browserruntime voor een lege externe tenant zonder terugval naar RC045/defaultdata;
- ontbrekende whitelisted publieke tenantdatasets leveren voortaan een lege HTTP 200 JSON-dataset, terwijl ongeldige of niet-whitelisted sleutels fail-closed 404 blijven;
- nieuwe externe tenants krijgen neutrale, betekenisvolle startinhoud voor `ontstaan.html` en `baanreglement.html`;
- historische RC045-brandingdefaults worden voor externe tenants geneutraliseerd zodat geen fictieve tenantasset-URL wordt geconstrueerd;
- PR #102 corrigeerde de transformatievolgorde: historische `rc045-logo.png`-referenties worden vóór merknaamvervanging naar de neutrale templateplaceholder omgezet;
- regressiedekking toegevoegd zodat `Testvereniging-logo.png` of een vergelijkbare niet-bestaande afgeleide assetnaam niet opnieuw kan ontstaan.

### VPS-test acceptatie

- actuele gedeployde `main`-baseline: `f2b6a06acf1e7e38a74291130f6f55d63953377b`;
- automatische VPS-testdeploy run `33277215732` succesvol afgerond;
- Full regression acceptance run `33277229149` succesvol afgerond;
- source-regression groen;
- live-security groen;
- publieke Playwright-browseracceptatie: **20/20 tests groen**;
- desktop, tablet en mobiel gecontroleerd voor `/`, `/ontstaan.html`, `/baanreglement.html`, `/aanmelden.html`, `/beheer/` en `/leden/`;
- geen kapotte afbeeldingen, console-errors, page-errors, mislukte same-origin requests of horizontale overflow gevonden;
- crawl van zichtbare interne publiekslinks en browservalidatie van publieke formulieren groen;
- `live-authenticated` blijft bewust uitgeschakeld/skipped totdat de dedicated VPS-testidentiteiten voor beheerder en lid worden geactiveerd.

Deze acceptatie bevestigt dat de publieke VPS-testbaseline en live security op `test.vps.holox.nl` groen zijn. Authenticated beheer- en leden-E2E is de eerstvolgende afzonderlijke acceptatiestap.

## 2026-08-28 — Fase 5.4.1 RC045-templatepariteit

- foutieve vereenvoudigde tenanthomepage met afwijkend menu en secties verwijderd;
- externe tenants teruggebracht naar dezelfde `index.php`, `styles.css`, `site-i18n.js` en `homepage.js` als RC045;
- zeven menu-items, tien homepageonderdelen, sectievolgorde en responsive breakpoints als vast templatecontract vastgelegd;
- server-side tenantfilter toegevoegd voor veilige identiteit, inhoud, links, contactgegevens en media;
- RC045-inhoud, afbeeldingen, favicons, locatiekaart en analytics voor externe tenants fail-closed geneutraliseerd;
- migratie vult ontbrekende templatevelden aan zonder tenant-eigen waarden te overschrijven;
- regressietest uitgebreid met structurele templatepariteit en mobiele gridstapeling.

## 2026-08-28 — Fase 5.3 echte VPS-validatie afgerond

### Acceptatie

- first-VPS bootstrap op Ubuntu 26.04 volledig uitgevoerd en geaccepteerd;
- platformbeheer, publieke testtenant, tenantbeheerlogin en healthcheck live groen;
- Apache, PHP 8.5-FPM, PostgreSQL socket-only/peer-isolatie, Certbot, Fail2ban, monitoringtimer en logrotate gevalideerd;
- tenant suspend/activate en volledige export succesvol uitgevoerd;
- export `20260827_145236-f2d850d7-tenant-export.tar.gz` geverifieerd met SHA-256 `f00de946cb8fa55ef36c0e557101425d48cd4ee1c9878d118eb2f4f9fbd0688e`;
- export daadwerkelijk naar een geïsoleerde wegwerp-herstelomgeving teruggezet, inhoudelijk gecontroleerd en daarna opgeruimd;
- release `d819446b9516bb98a580a88da448487c16383f2e` teruggerold naar `7bab3d1f7e87b7d01311b41bb53e4c66dfcbb39b` en daarna opnieuw succesvol geactiveerd;
- tenantconfiguratie bleef bytegelijk en de canonieke live database-inhoud was gelijk aan de gevalideerde export;
- finale release-state: active `d819446b...`, previous `7bab3d1f...`, transition `null`, één gevalideerde tenant;
- officiële healthprobe eindigde `UP` met 10 checks en Apache `Syntax OK`;
- eerste VPS productiegeschikt verklaard voor gecontroleerde onboarding.

### Live gevonden en structureel opgeloste blokkades

- PR #91 herstelde veilige traverse-rechten op de gedeelde `/etc/verenigingsplatform`-parent;
- PR #92 corrigeerde de FPM-only `session.save_path`-acceptatie zonder de tenantisolatie te verzwakken;
- beide fixes zijn in release `d819446b...` live bewezen.

De optionele destructieve purge op een extra wegwerptenant is bewust niet uitgevoerd en blokkeert de productieacceptatie niet.

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
