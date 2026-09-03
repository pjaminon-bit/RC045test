# Changelog

Belangrijke platformwijzigingen en acceptatiemijlpalen worden hier chronologisch vastgelegd. Historische technische details blijven daarnaast beschikbaar in `docs/migratie-log/` en de fasegerichte documentatie.

## 2026-08-30 — Fase 5.13 plaintext masterfallback verwijderd

### Hash-only masterauthenticatie

- issue #62 is opgelost via PR #111;
- masterlogin accepteert uitsluitend een geldige `BEHEER_WACHTWOORD_HASH` en verifieert uitsluitend met `password_verify`;
- een niet-lege legacy `$BEHEER_WACHTWOORD` maakt de runtimeconfig fail-closed ongeldig;
- voor standalone-installaties is `bin/migrate-standalone-master-hash.php` toegevoegd als begrensde eenmalige migrator;
- de migrator accepteert geen wachtwoord op de commandline, weigert symlinks en ambigue/berekende/conditionele assignments en laat na succesvolle migratie geen plaintext rollbackbestand achter;
- regressietest `tests/phase513-standalone-master-hash.php` eindigde met **20/20 controles groen**;
- de volledige bronregressie op de PR eindigde met **79 PHP-tests groen**;
- Security supply-chain, Validate RC045test, PHP 8.5 compatibility en Full regression acceptance waren op PR #111 groen.

### Live VPS-testbewijs

- gemergede en gevalideerde `main`-release: `fa0388ccf5f5cc30a2f6e0ca3b77e68973ea13e4`;
- Validate RC045test run **#686**, `33309983232`, volledig succesvol inclusief `verify-vps-current`;
- VPS-test deploy run **#40**, `33310038992`, volledig succesvol met private Tailscale/SSH-route, immutable release-activatie en publieke smoke test;
- automatische authenticated E2E job `99253348491` volledig succesvol, inclusief ephemeral fixture-aanmaak, beheer- en ledenportaalacceptatie en cleanup;
- post-deploy Full regression acceptance **#368**, run `33310087494`, `completed/success`;
- source-regression job `99253441174`, live-security job `99253520891` en live-browser job `99253520907` alle drie groen;
- issue #62 is na deze volledige live bewijs-keten gesloten als `completed`.

Daarmee is de legacy plaintext mastercredential uit het runtime-authenticatiepad verwijderd en is de hash-only grens zowel in bronregressies als op de actuele VPS-testrelease bewezen.

## 2026-08-30 — Fase 5.10/5.11 authenticated VPS-testacceptatie groen

### Ephemeral authenticated E2E

- authenticated beheer- en ledenacceptatie op `test.vps.holox.nl` draait volledig automatisch na een succesvolle VPS-testdeploy;
- de test gebruikt uitsluitend tijdelijke synthetische identiteiten `vps-e2e-admin` en `vps-e2e-member` met een per-run cryptografisch willekeurig wachtwoord;
- er zijn geen permanente E2E-wachtwoorden of publieke SSH-routes nodig;
- de bestaande GitHub OIDC/Tailscale WIF-route en gepinde private SSH-hosttrust worden hergebruikt;
- de forced-command gateway accepteert uitsluitend `e2e check`, `e2e apply` en `e2e cleanup`;
- fixturedata wordt vóór een run idempotent opgeschoond en na de browsertest via een fail-safe cleanup verwijderd;
- de uiteindelijke succesvolle cleanup verwijderde alle **7** tijdelijke fixture-records.

### Live gevonden en opgeloste blokkades

- PR #106 herstelde E2E-markers die door bestaande domeinnormalisatie verloren gingen, zonder de productie-normalizers te versoepelen;
- PR #107 en PR #108 brachten de resterende HTTP 500 in het ledenportaal terug tot een server-side portalruntimefout tijdens `e2e apply`;
- de concrete oorzaak bleek een nieuwe PDO-tenant zonder opgeslagen evenementencollectie: de repository leverde `[]`, terwijl `evenementenGesorteerd()` een document met `evenementen: []` verwachtte;
- PR #109 normaliseert uitsluitend een werkelijk ontbrekende evenementencollectie naar het geldige lege domeindocument;
- een niet-leeg maar ongeldig evenementendocument blijft fail-closed met een runtimefout, zodat opslagcorruptie niet wordt verborgen;
- regressietest `tests/phase5114-empty-tenant-events.php` borgt leeg sorteren, eerste nummering, volgnummer-normalisatie en fail-closed gedrag bij een corrupte structuur.

### Definitieve VPS-testacceptatie

- gevalideerde applicatierelease: `aca0a1a3e082cef794c4a0fe50768b24c03f5e60`;
- deployworkflow **#33**, run `33306504848`, volledig succesvol;
- immutable release-activatie en publieke smoke test groen;
- `e2e check`, stale-fixture cleanup en `e2e apply` groen met **2 accounts** en **1 gekoppeld testlid**;
- authenticated Playwright: **6/6 tests groen** in 10,8 seconden;
- beheerlogin, autorisatie en logout gevalideerd op desktop, tablet en mobiel;
- gekoppeld ledenportaal gevalideerd op desktop, tablet en mobiel, inclusief afwezigheid van gebruikersbeheer voor het gewone testlid;
- authenticated Playwright-rapport en screenshots als workflowartifact opgeslagen;
- afsluitende ephemeral cleanup groen met **7 verwijderde records**;
- daaropvolgende Full regression acceptance **#356**, run `33306559675`, volledig succesvol;
- source-regression groen;
- live-security groen;
- publieke Playwright-browseracceptatie groen.

Daarmee is fase 5.11 technisch afgerond: dezelfde VPS-testrelease is zowel publiek als authenticated live bewezen, terwijl de tijdelijke testidentiteiten en fixturedata na iedere run volledig worden verwijderd.

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
