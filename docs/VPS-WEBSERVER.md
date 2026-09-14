# VPS webserver & vhosts — fase 4.2

Dit document beschrijft het actuele Apache 2.4-webservercontract voor de multi-tenant VPS. Fase 4.2 is de historische herkomst van het contract; de webserverlaag is inmiddels operationeel toegepast en gevalideerd. De actuele projectstatus staat in `ROADMAP.md`.

## Canonieke webgrens

Sinds #226 is de primaire securitygrens een minimale publieke subdirectory. De volledige immutable applicatierelease is nadrukkelijk geen DocumentRoot.

```text
/srv/verenigingsplatform/
├── current -> releases/<40-hex-commit>
└── releases/<40-hex-commit>/
    ├── app/                 # buiten de DocumentRoot
    ├── bin/                 # buiten de DocumentRoot
    ├── docs/                # buiten de DocumentRoot
    ├── tests/               # buiten de DocumentRoot
    └── public/              # enige DocumentRoot
        └── index.php        # enige fysieke PHP-entrypoint
```

Voor iedere tenant gelden daarom deze invarianten:

- `DocumentRoot` is exact `/srv/verenigingsplatform/current/public`;
- `shared_code.document_root` moet exact `app_root/public` zijn;
- de release-root zelf blijft denied en wordt niet als webroot gebruikt;
- interne applicatiecode ligt fysiek buiten de DocumentRoot;
- aliases van buiten `public/` naar de webroot zijn verboden;
- alleen `public/index.php` krijgt de tenantgebonden PHP-FPM-handler;
- virtuele publieke routes worden via de frontcontroller afgehandeld;
- fysiek bestaande andere PHP-bestanden zijn geen publieke uitvoerbare entrypoints.

De productie-implementatie en deterministische generator staan in `app/deployment/webserver-contract.php`. Dat codecontract is normatief wanneer een voorbeeld in documentatie ooit afwijkt.

## Eén ondersteunde productie-webserver

De canonieke VPS-stack gebruikt Apache HTTP Server 2.4 op Ubuntu/Debian. Minimumversie is 2.4.49 wegens `StrictHostCheck`.

Elke omgeving gebruikt:

- een globale HTTP/HTTPS catch-all die onbekende hosts weigert;
- een tenant HTTP-vhost met alleen de exacte canonical host en een vaste HTTPS-redirect;
- een tenant HTTPS-vhost die uitsluitend de eigen FPM-pool/socket gebruikt;
- geen `ServerAlias` voor tenanthosts;
- geen request-afgeleide redirecthost;
- geen generieke proxyroute die de tenantbinding kan omzeilen.

## Waarom de oude denylistarchitectuur niet meer geldt

Het pre-#226 model publiceerde de hele release en moest daarna interne paden zoals `app/`, `bin/`, `tests/`, `docs/` en VCS/CI-metadata afzonderlijk weigeren. Dat was foutgevoelig: iedere nieuwe interne directory moest ook in een denylist terechtkomen.

Het huidige model publiceert alleen `public/`. Interne code is daardoor standaard onbereikbaar omdat die buiten de DocumentRoot ligt. Eventuele publieke routingregels en `.htaccess` zijn defense-in-depth binnen `public/`, niet de primaire grens voor repository-internals.

Daarom zijn de volgende oude instructies geen onderdeel meer van het VPS-contract:

- de volledige `/srv/verenigingsplatform/current` als DocumentRoot;
- `AllowOverride All` op de hele release;
- een brede onderhouden denylist als primaire bescherming van interne directories;
- generieke FPM-uitvoering voor ieder fysiek PHP-bestand.

Binnen de minimale publieke directory staat Apache alleen de overrideklassen toe die publieke routing nodig heeft: `FileInfo Indexes Options`.

## Bundle genereren

Voor een tenant met een gevalideerd fase-4.1 runtimeplan:

```bash
php bin/prepare-vps-webserver.php \
  --runtime-plan=/srv/verenigingen/noorderhaven/runtime/runtime-plan.json
```

De bundle komt onder de tenantroot en bevat het plan, globale HTTP-catch-all, tenant HTTP-vhost en het HTTPS-routingfragment.

Voor alleen validatie:

```bash
php bin/prepare-vps-webserver.php \
  --runtime-plan=/srv/verenigingen/noorderhaven/runtime/runtime-plan.json \
  --dry-run
```

De generator valideert opnieuw:

- runtimeplan en SHA-binding met `deployment.json`;
- canonical host en tenantidentiteit;
- tenantpool en Unix-socket;
- `web.serve_only_minimal_public_root=true`;
- `web.application_code_outside_document_root=true`;
- exact `app_root/public` als logische en fysieke DocumentRoot;
- outputcontainment en symlinkgrenzen.

## Root-vrije check

```bash
php bin/apply-vps-webserver.php \
  --plan=/srv/verenigingen/noorderhaven/webserver/web-plan.json \
  --check
```

`--check` vereist geen root. De tool valideert bronbindings opnieuw, reconstrueert plan en artifacts deterministisch en vergelijkt ze byte-inhoudelijk. Handmatige wijziging van DocumentRoot, host, socket of public-rootgrenzen maakt de bundle ongeldig.

## Root-toepassing

Op de VPS wordt dezelfde bundle gecontroleerd toegepast met `apply-vps-webserver.php --apply`. De tool accepteert alleen de vaste Ubuntu/Debian Apache-doelpaden, controleert Linux/root, minimumversie, vereiste modules en veilige doelpaden, en syntax-test de kandidaatconfig vóór plaatsing.

Een afwijkende reeds actieve siteconfiguratie wordt niet stil overschreven.

## Activatie en TLS

De HTTP-catch-all en tenantredirect worden samen met de fase-4.4 TLS-wrapper en certificaatbinding gevalideerd voordat een tenantvhost actief wordt. Voor iedere enable/reload geldt:

1. globale catch-alls staan klaar;
2. tenant HTTP/TLS-vhosts zijn exact aan de canonical host gebonden;
3. de complete Apache-configtest is groen;
4. daarna volgt gecontroleerde enable/reload;
5. host-, redirect-, TLS- en FPM-smokes bewijzen de actieve configuratie.

## Securityinvarianten

- onbekende hosts bereiken geen tenant;
- redirects reflecteren geen client-`Host`;
- HTTP routeert niet rechtstreeks naar PHP;
- tenant A PHP gebruikt alleen tenant A FPM-socket/backend;
- alleen `app_root/public` is DocumentRoot;
- release-root en interne code blijven buiten de publieke root;
- alleen `public/index.php` is fysieke PHP-entrypoint;
- tenantroot/private root wordt nooit gepubliceerd;
- aliases buiten de public-root zijn verboden;
- directory indexes en CGI zijn uit;
- forward proxy en generieke proxy-routes zijn verboden;
- gegenereerde webserverartifacts bevatten geen secrets.

## Standalone versus VPS

Een standalone Apache-installatie kan de repository-`.htaccess` als compatibiliteits- en defense-in-depthlaag gebruiken. Dat is niet het architectuurmodel voor nieuwe VPS-tenants. Nieuwe VPS-tenants gebruiken de minimale `public/` DocumentRoot en de tenantgebonden Apache/FPM-runtime.

Zie `VPS-DEPLOYMENT.md`, `VPS-TLS.md` en `VPS-RELEASES.md` voor de samenhang met deployment, certificaten en immutable releases.
