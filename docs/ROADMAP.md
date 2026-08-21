# Roadmap verenigingsplatform

Status per **21-08-2026**.

Dit document is vanaf fase 4 de centrale nummering voor de resterende platform- en VPS-stappen. Historische migratiedocumenten blijven hun toenmalige stand beschrijven.

## Afgerond

- **Fase 1 — template-ready:** afgerond.
- **Fase 2 — modulair beheer:** afgerond.
- **Fase 3.1 — tenant boundary:** afgerond.
- **Fase 3.2 — tenant provisioner:** afgerond.
- **Fase 3.2.1 — security hardening:** afgerond.
- **Fase 3.3 — tweede tenantbewijs:** afgerond.
- **Fase 3.4 — veilige eerste beheerder:** afgerond.
- **Fase 3.5 — VPS deploymentcontract:** afgerond.
- **Fase 3.5.1 — security heraudit fixes:** afgerond.

## Fase 4 — VPS & productie-infrastructuur

### 4.1 — VPS runtime & Linux-isolatie

**Status: code en CI gereed; root-toepassing volgt op de echte VPS.**

Omvat:

- deterministische system user + unieke primary group per tenant;
- geen login/home/supplementary groups;
- UID/GID zijn exclusief voor die tenantidentity;
- per-tenant PHP-FPM pool en Unix socket;
- tenant-private PHP sessions en upload tmp;
- ownership- en modecontract voor tenantmetadata en private opslag;
- shared release blijft centraal beheerd en nooit tenant-writable;
- root-vrije `--check` en aparte Linux-root `--apply`;
- herhaalde `--apply` vereist een stilstaande tenant-runtime;
- geen automatische PHP-FPM reload voordat de complete serverconfig is getest.

**4.1.1 — runtime-isolatie re-audit:** afgerond; bestaande groeps-/UID/GID-collisions en live-process races worden fail-closed geweigerd vóór filesystemmutaties.

Zie `docs/VPS-RUNTIME-ISOLATION.md`.

### 4.2 — Webserver & vhosts

**Status: code en CI gereed; artifacts blijven inactief tot DNS/TLS in 4.3/4.4.**

Canonieke productie-stack: **Apache HTTP Server 2.4 op Ubuntu/Debian**, minimaal 2.4.49.

Omvat:

- deterministische Apache tenant-vhosts uit het fase-4.1 runtimeplan;
- eerste/default `000-` HTTP catch-all met `StrictHostCheck On` en `Require all denied`;
- literal canonical HTTP→HTTPS redirect zonder request-Hostreflectie;
- exacte `ServerName` zonder `ServerAlias`;
- iedere host uitsluitend naar eigen PHP-FPM Unix socket én unieke FastCGI backendidentity;
- DocumentRoot uitsluitend de gedeelde release;
- server-side denyregels voor private/tooling/VCS-paden naast de vertrouwde gedeelde `.htaccess`;
- root-vrije bundlecheck;
- root-only installatie naar `sites-available` + dedicated fragmentmap;
- Apache-versie/module/syntaxpreflight;
- geen `a2ensite`, geen `sites-enabled` write en geen reload/restart in fase 4.2;
- volledige TLS-vhost/configtest/activatie volgt pas in 4.4.

Zie `docs/VPS-WEBSERVER.md`.

### 4.3 — DNS

**Status: code en CI gereed; echte providerrecords/readiness volgen zodra VPS-IP's en tenantdomeinen definitief zijn.**

Omvat:

- tenantgebonden `dns-plan.json` uit het gevalideerde fase-4.2 web-plan;
- expliciete `direct` A/AAAA- of één-hop `cname`-strategie;
- exacte RRset-match: extra/stale IPv4 of IPv6 faalt gesloten;
- geen gemengd CNAME + owner-addressprofiel;
- CNAME-doel en terminale VPS-adressen exact vastgelegd;
- live readiness via de systeemresolver van de VPS;
- minimaal 3 succesvolle samples met minimaal 2 seconden interval;
- readiness maximaal 15 minuten geldig en gebonden aan DNS-/web-planhash;
- planwijziging of mislukte live check trekt een oude readiness in;
- geen DNS-providercredentials of automatische providerwrite in de generieke tenantbundles;
- fase 4.4 blijft contractueel geblokkeerd zonder verse geldige readiness.

Zie `docs/VPS-DNS.md`.

### 4.4 — TLS/HTTPS

**Status: code en CI gereed; daadwerkelijke certificaatuitgifte en HTTPS-activatie volgen op de echte VPS na 4.1/4.2 root-toepassing en verse 4.3 DNS-readiness.**

Omvat:

- tenantgebonden `tls-plan.json` uit verse fase-4.3 readiness;
- Certbot `certonly --webroot` met vooraf geregistreerd operator-ACME-account;
- aparte per-tenant ACME webroot; Certbot mag Apache-config niet autonoom herschrijven;
- HTTP-vhost serveert uitsluitend `/.well-known/acme-challenge/` en redirect overige requests naar de vaste canonical HTTPS-host;
- aparte eerste/default HTTP- en HTTPS-catch-all vóór tenantvhosts;
- neutraal lokaal reject-certificaat voor onbekende HTTPS/SNI in plaats van een tenantcertificaat;
- TLS 1.0/1.1 en TLS-compressie uit; HSTS één jaar zonder `includeSubDomains`;
- iedere tenant-HTTPS-vhost controleert zowel `SSL_TLS_SNI` als `Host`;
- certificaatvalidatie op exacte SAN, geldigheid, private-key match, lineage-containment en keyrechten;
- actieve fase-4.1 FPM-socket en exact geïnstalleerd 4.2 routingfragment verplicht vóór HTTPS;
- live DNS-hercontrole direct vóór ACME-uitgifte;
- volledige Apache `configtest` vóór elke reload en vóór definitieve HTTPS-activatie;
- Certbot renewal deploy-hook: eerst `apache2ctl configtest`, alleen daarna `systemctl reload apache2`;
- mislukte eerste ACME-uitgifte rolt nieuw geactiveerde tenant-HTTP-route zo mogelijk terug;
- geen ACME-accountmail, certificaatprivate key of andere secrets in tenantbundles/Git.

**Heraudit 21-08-2026:** opnieuw gecontroleerd tegen actuele Apache/Certbot-contracten; geen nieuwe blocker of codewijziging nodig.

Zie `docs/VPS-TLS.md`.

### 4.5 — Database provisioning

**Status: code en CI gereed; daadwerkelijke PostgreSQL provisioning volgt op de echte VPS.**

Canoniek productiemodel: **lokale PostgreSQL 16+ met één database per tenant en Unix-socket peer authentication.**

Omvat:

- tenantgebonden `database-plan.json` uit het gevalideerde fase-4.1 runtimeplan;
- iedere PDO-tenant moet vooraf expliciet met `--driver=pdo` zijn geprovisioneerd; JSON-tenants worden nooit stil omgeschakeld;
- één unieke database per tenant;
- aparte NOLOGIN owner-role; de app-role is exact de unieke Linux/PHP-FPM user uit fase 4.1;
- geen databasepassword: kernel peer identity via `/var/run/postgresql` is de enige app-authenticatie;
- exacte HBA-allow voor eigen database gevolgd door reject voor iedere andere database voor dezelfde tenantuser;
- platform-HBA include staat vóór generieke bestaande regels en wordt via `pg_hba_file_rules` gevalideerd vóór reload;
- `PUBLIC` krijgt geen database- of schemarechten;
- app-role krijgt alleen CONNECT, schema-USAGE, SELECT op schema-meta en SELECT/INSERT/UPDATE/DELETE op private-store;
- schema `vst` versie 1 met tenantmarker als defense-in-depth;
- DDL/schema-migratie uitsluitend via provisioning, nooit automatisch vanuit een HTTP-request;
- vaste secretvrije `database-runtime.json` buiten de documentroot;
- root-vrije bundlecheck en aparte Linux-root `--apply`;
- post-apply runtimecheck draait als de echte tenant Linux-user, test DML rollback-safe en eist DDL-weigering;
- geen DB-secrets in Git, `config.php`, `deployment.json`, environment of runtimebundles.

**4.5.1 — database security heraudit:** afgerond en CI-groen. De root-apply vereist een stilstaande tenant-runtime, houdt de app-role `NOLOGIN` totdat tenant-HBA en least privilege aantoonbaar actief zijn, zet de role bij fouten opnieuw `NOLOGIN`, laat beschermende HBA-rejects na latere provisioningfouten staan en weigert symlinks in alle root-HBA/configpaden.

Zie `docs/VPS-DATABASE.md`.

### 4.6 — Monitoring & logging

**Status: code en CI gereed; productie-installatie volgt op de echte VPS.**

Omvat:

- informatie-arme publieke healthcheck: 204 gezond, 503 ongezond, 404 op standalone/DEV;
- lokale root-only minuutprobe voor Apache, PHP-FPM, PostgreSQL, TLS, FPM-socket, database-peerbinding, app-health en schijfruimte;
- Certbot-lineagecontrole die geldige `live/` symlinks uitsluitend binnen de eigen `archive/<cert-name>/` accepteert;
- privacybewust Apache accesslog zonder IP, URI/query, referrer, user-agent, cookies of Authorization;
- PHP-FPM servicelog via systemd-journal zonder de fase-4.1 poolconfig achteraf te muteren;
- tenantgebonden operationele app-log met vaste contextallowlist;
- 14-daagse logrotatie;
- root-only statusbestanden buiten documentroot;
- optionele alert-adapter buiten Git, met up/down-transities en maximaal één reminder per uur;
- systemd minuut-timer wordt pas enabled nadat Apache/systemd/logrotate-validatie en een eerste volledige healthprobe slagen.

Zie `docs/VPS-MONITORING.md`.

### 4.7 — Release & rollback automation

**Status: code en CI gereed; echte releasewissels volgen op de productie-VPS.**

Omvat:

- immutable `releases/<40-hex-commit>` met root-owned `0555/0444` filesystemrechten;
- deterministisch SHA-256 inhoudsmanifest; commit-ID is identificatie en de inhoudshash is de werkelijke integriteitsbinding;
- mutable/private tenantdata, lokale secrets, `.git`, `.github` en DEV-buildmetadata worden niet in een productie-release opgenomen;
- bestaande release directories worden nooit overschreven en niet automatisch verwijderd;
- expliciete eerste `--bootstrap` vóór tenantactivatie;
- normale deploy vereist gezonde huidige tenants, PHP-syntax, read-only kandidaattenantprobes en Apache configtest vóór de wissel;
- atomische `current`-wissel via tijdelijke symlink + filesystem-rename;
- transition-state voorkomt dat de bestaande 4.1–4.6 planbinding tijdens de korte wissel onterecht open of willekeurig wordt;
- PHP-FPM reload na de codewissel;
- volledige 4.6 tenant-health na de switch;
- mislukte post-switch deploy zet `current` automatisch terug en bewijst de oude health opnieuw;
- handmatige rollback gebruikt uitsluitend de vorige gevalideerde release uit root-owned state en mag juist plaatsvinden wanneer de huidige release ongezond is;
- release-events worden server-side geaudit zonder secrets;
- gewone verenigingsbeheerders krijgen geen directe release/rootbevoegdheid.

Zie `docs/VPS-RELEASES.md`.

### 4.8 — Tenant lifecycle

**Status: code en CI gereed; daadwerkelijke lifecyclemutaties volgen op de echte productie-VPS.**

Omvat:

- een bestaande actieve tenant wordt alleen na volledige runtime/database/Apache/monitoring-health expliciet met `--adopt-active` onder lifecyclebeheer gebracht;
- per tenant kan maximaal één lifecycleactie tegelijk lopen via een root-owned lock;
- gecontroleerd uitschakelen blokkeert app-HTTPS, PostgreSQL-login/sessies, monitoring en PHP-FPM; alleen de minimale HTTP-01 route blijft bereikbaar voor certificaatvernieuwing;
- heractiveren bouwt fail-closed op via FPM → database → Apache/HTTPS → volledige healthcheck → monitoring;
- export is alleen vanuit stabiel `suspended` toegestaan en bevat PostgreSQL-dump, volledige tenantboom en SHA-256 manifest/checksum in root-only opslag;
- verwijderen is tweestaps: eerst `pending_delete` na geverifieerde export, daarna minimaal 24 uur wachttijd en een tweede expliciete purgebevestiging;
- een pending delete kan binnen de wachttijd worden ingetrokken;
- definitieve purge ruimt tenantgebonden Apache/FPM/PostgreSQL/HBA/Certbot/systemd/Linux-resources en tenantdata op, maar bewaart export en tombstone;
- onderbroken infrastructuur- of datapurges hebben een expliciet recoverypad dat uitsluitend de reeds gestarte purge mag hervatten;
- lifecycle-state en audit zijn tenantgebonden en root-owned buiten documentroot;
- DNS-providerrecords worden nooit automatisch verwijderd;
- een latere platform-/superbeheer-GUI komt als aparte control-plane boven deze operator-engine en krijgt geen directe rootuitvoering vanuit het gewone verenigingsbeheer.

Zie `docs/VPS-LIFECYCLE.md`.

## Volgorde

De standaardvolgorde is:

**4.1 → 4.2 → 4.3 → 4.4 → 4.5 → 4.6 → 4.7 → 4.8**

Een fase kan code/automation al gereed hebben voordat de daadwerkelijke productiehandeling op de VPS kan worden uitgevoerd. Dat verschil wordt per fase expliciet als status vermeld; “code gereed” wordt nooit gelijkgesteld aan “op productie toegepast”.
