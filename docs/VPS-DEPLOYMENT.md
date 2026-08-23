# VPS deploymentcontract

Status per **20-08-2026**: fase 3.5.1 + fase 4.1 runtimevoorbereiding.

Dit document legt de serverlayout vast waarmee meerdere verenigingen dezelfde applicatiecode veilig kunnen gebruiken. Fase 3.5/3.5.1 levert het vaste, machineleesbare tenant- en hostcontract. Fase 4.1 bouwt daarop de Linux/PHP-FPM runtimebundle en root-applyprocedure. DNS, TLS en concrete webserver-vhosts volgen in fase 4.2–4.4.

De officiële vervolgnummers staan in `docs/ROADMAP.md`. De concrete 4.1 runtimeprocedure staat in `docs/VPS-RUNTIME-ISOLATION.md`.

## Doelarchitectuur

```text
/srv/verenigingsplatform/
├── releases/
│   ├── <commit-a>/              # immutable/read-only applicatierelease
│   └── <commit-b>/
└── current -> releases/<commit> # logische gedeelde app-root

/srv/verenigingen/
├── noorderhaven/
│   ├── config.php
│   ├── runtime.env
│   ├── tenant.json
│   ├── deployment.json
│   ├── runtime/
│   │   ├── runtime-plan.json
│   │   └── vst-noorderhaven-<hash>.conf
│   └── private/
│       ├── auth/
│       ├── audit/
│       ├── backups/
│       ├── collections/
│       ├── public-content/
│       ├── security/
│       ├── sessions/
│       └── tmp/
└── duinrand/
    └── ... dezelfde tenantstructuur, eigen data ...
```

De webserver gebruikt voor iedere vereniging dezelfde logische documentroot, bijvoorbeeld `/srv/verenigingsplatform/current`. De tenantroot onder `/srv/verenigingen/<key>` is **nooit** een documentroot, alias of statische webmap.

## Deploymentdescriptor maken

Nadat de tenant is geprovisioneerd en fase 3.4 de eerste beheerder heeft geactiveerd:

```bash
php bin/prepare-vps-deployment.php \
  --config=/srv/verenigingen/noorderhaven/config.php \
  --app-root=/srv/verenigingsplatform/current
```

Standaard ontstaat:

```text
/srv/verenigingen/noorderhaven/deployment.json
```

Voor alleen controle:

```bash
php bin/prepare-vps-deployment.php \
  --config=/srv/verenigingen/noorderhaven/config.php \
  --app-root=/srv/verenigingsplatform/current \
  --dry-run
```

De output is deterministisch. Een identieke tweede run verandert niets. Een afwijkend bestaand descriptor wordt alleen met bewust `--force` vervangen.

## Wat wordt vóór deployment gecontroleerd

De tool faalt gesloten wanneer één van deze grenzen niet klopt:

- tenantconfig is niet het provisioned `config.php` buiten de app-root;
- `private_root` is niet de eigen `<tenant>/private` map;
- config, `tenant.json` en `runtime.env` wijzen niet exact naar dezelfde tenant;
- `tenant.json` gebruikt niet `require_tenant_config=true`;
- `site_url` is niet canoniek HTTPS op de domeinroot;
- de eerste beheerder uit fase 3.4 ontbreekt of de masterconfig bevat geen geldige password hash;
- tenantroot en gedeelde code overlappen fysiek;
- een tenantpad loopt via een symlink;
- de opgegeven app-root bevat niet de verwachte gedeelde platformcode;
- deploymentoutput probeert buiten de tenantroot te schrijven.

Een release-symlink zoals `/srv/verenigingsplatform/current` is voor de **gedeelde code** wel toegestaan. `deployment.json` bewaart zowel dat logische pad als het fysiek opgeloste releasepad, zodat later zichtbaar is tegen welke release het contract is gecontroleerd.

## Inhoud van deployment.json

Het descriptor bevat uitsluitend niet-geheime deploymentmetadata, waaronder:

- tenant-key en canonieke host;
- logische en fysieke gedeelde app-root;
- tenantroot, configbestand en private root;
- het exacte fail-closed runtime-environment;
- een deterministische PHP-FPM poolnaam en Unix-socket;
- een unieke aanbevolen OS-runtimegebruiker;
- readiness-vlaggen voor manifestbinding, runtimebinding, adminbootstrap en canonical-hostcontract;
- webvereisten voor catch-all hostafwijzing en veilige HTTP→HTTPS-redirects.

Het descriptor bevat bewust **geen**:

- beheerderswachtwoord of wachtwoordhash;
- PDO-wachtwoord;
- DSN/databasecredentials;
- TLS private keys;
- API-tokens of andere secrets.

## Fase 4.1: Linux/PHP-FPM runtimebundle

Op basis van `deployment.json` wordt nu een tweede deterministisch contract gemaakt:

```bash
php bin/prepare-vps-runtime.php \
  --deployment=/srv/verenigingen/noorderhaven/deployment.json \
  --php-version=8.5 \
  --web-user=www-data \
  --web-group=www-data
```

Dit schrijft onder de tenantroot:

```text
runtime/runtime-plan.json
runtime/<tenant-pool>.conf
```

De generator voert geen root-acties uit. Voor roottoepassing wordt de bundle eerst opnieuw gecontroleerd:

```bash
php bin/apply-vps-runtime.php \
  --plan=/srv/verenigingen/noorderhaven/runtime/runtime-plan.json \
  --check
```

Op de echte Linux-VPS kan daarna bewust worden toegepast:

```bash
sudo php bin/apply-vps-runtime.php \
  --plan=/srv/verenigingen/noorderhaven/runtime/runtime-plan.json \
  --apply \
  --fpm-pool-dir=/etc/php/8.5/fpm/pool.d
```

De apply-tool reloadt PHP-FPM niet automatisch. Eerst moet de volledige serverconfiguratie met de distro-/versiespecifieke testopdracht worden gevalideerd; pas daarna volgt een expliciete reload.

## PHP-FPM: één pool per tenant

De securitygrens op de VPS is niet alleen de applicatiecode. Iedere tenant krijgt een eigen PHP-FPM pool met de waarden uit het gevalideerde runtimeplan.

Conceptueel:

```ini
[<deployment.php_fpm.pool>]
user = <unieke tenant-system-user>
group = <unieke tenant-primary-group>
listen = <deployment.php_fpm.socket>
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

clear_env = yes
php_admin_value[session.save_path] = "/srv/verenigingen/<tenant>/private/sessions"
php_admin_value[upload_tmp_dir] = "/srv/verenigingen/<tenant>/private/tmp"

env[VERENIGING_REQUIRE_TENANT_CONFIG] = "1"
env[VERENIGING_CONFIG_FILE] = "<tenant>/config.php"
env[VERENIGING_PRIVATE_ROOT] = "<tenant>/private"
```

Belangrijk:

- de tenant-runtimegebruiker is een system account zonder login, home of supplementary groups;
- de tenant-runtimegebruiker krijgt schrijfrecht uitsluitend op zijn eigen private root;
- andere tenant-runtimegebruikers krijgen daar geen toegang toe;
- sessies en tijdelijke uploads staan in de eigen private root;
- de gedeelde applicatierelease blijft centraal beheerd en is nooit tenant-owned;
- `clear_env=yes` voorkomt dat een pool toevallig environment van een andere deployment erft;
- databasecredentials volgen apart in fase 4.5 en komen niet in `deployment.json` of de 4.1 runtimebundle.

De precieze `pm.*` capaciteit is geen tenantidentiteitsgrens en kan later op VPS-capaciteit worden afgestemd.

## Webservercontract — fase 4.2

Voor Apache of Nginx gelden dezelfde harde regels die in fase 3.5.1 al in het deploymentcontract zijn vastgelegd en in fase 4.2 daadwerkelijk worden geautomatiseerd:

1. `server_name`/`ServerName` is exact `canonical_host` uit het descriptor;
2. de HTTP-vhost redirect naar de **vaste** `web.http_redirect_target`; een request-`Host` mag nooit in de redirect worden teruggespiegeld;
3. er bestaat vóór alle tenantvhosts een default/catch-all vhost die onbekende hosts weigert en nooit PHP naar een tenantpool stuurt;
4. documentroot is de gedeelde `shared_code.app_root`;
5. PHP voor de canonieke host gaat uitsluitend naar de eigen `php_fpm.socket`;
6. de tenantroot/private root wordt nooit rechtstreeks geserveerd;
7. `public-content.php` en `public-asset.php` blijven de enige gateways naar tenant-publieke data/assets;
8. server-only code en ontwikkeltooling (`app`, `bin`, `tests`, `docs`, `.github`) én VCS-metadata zoals `.git` zijn niet via HTTP bereikbaar.

### Waarom de gedeelde `.htaccess` niet redirect

Sinds fase 3.5.1 doet de gedeelde `.htaccess` bewust **geen** HTTP→HTTPS-redirect meer. De codebase kent op dat niveau niet betrouwbaar welke tenantvhost de request had moeten accepteren, terwijl `HTTP_HOST` clientinvoer is. De huidige standalone/DEV-hosting handelt HTTP→HTTPS vóór deze laag af; op de VPS doet de vhost/reverse proxy dit met een literal canonical host.

### Apache — vereiste vorm

De exacte syntax hangt af van de uiteindelijke VPS, maar de volgorde is essentieel. Eerst een catch-all, daarna pas tenantvhosts.

```apache
# Eerste/default HTTP-vhost: nooit naar een tenant redirecten.
<VirtualHost *:80>
    ServerName invalid.local
    <Location />
        Require all denied
    </Location>
</VirtualHost>

# Tenant HTTP-vhost: literal redirect, geen %{HTTP_HOST}.
<VirtualHost *:80>
    ServerName noorderhaven.example
    Redirect permanent / https://noorderhaven.example/
</VirtualHost>

# Tenant HTTPS-vhost.
<VirtualHost *:443>
    ServerName noorderhaven.example
    DocumentRoot /srv/verenigingsplatform/current
    # TLS-config en SetHandler/ProxyPassMatch naar exact de tenant-FPM-socket.
</VirtualHost>
```

Voor HTTPS moet eveneens een expliciete default/catch-all configuratie bestaan die een onbekende SNI/Host niet aan de eerste tenantpool koppelt. De concrete TLS-catch-all wordt in fase 4.2/4.4 uitgewerkt.

### Nginx — vereiste vorm

Nginx leest `.htaccess` niet. Een Nginx-deployment moet de deny-regels en de exacte routing voor publieke content/assets expliciet overnemen. Gebruik nooit een algemene alias naar `private/`.

Conceptueel begint de configuratie met een default server die onbekende hosts direct weigert, gevolgd door tenantservers met een literal HTTP→HTTPS redirect. Gebruik ook hier nooit `$host` als redirectdoel wanneer `canonical_host` al bekend is.

## Release-inhoud en VCS-metadata

Een live release hoort bij voorkeur uit een build/export zonder `.git` te bestaan. Defense-in-depth blokkeert de gedeelde Apache-laag `.git` daarnaast expliciet. Voor Nginx moet dezelfde deny-regel in de serverconfiguratie worden opgenomen. `deployment.json.web.vcs_metadata_must_not_be_served=true` maakt dit een deploymentvereiste.

## Filesystem ownership — fase 4.1

Het 4.1-contract maakt de bedoelde scheiding concreet:

```text
shared release             centraal platformbeheer   nooit tenant-owned/writable
tenantroot                  root:<tenantgroup>        0750
config/runtime metadata    root:<tenantgroup>        0640
runtime bundle             root:<tenantgroup>        0750/0640
tenant private directories <tenantuser>:<group>      0750
tenant private files       <tenantuser>:<group>      0640
sessions + tmp dirs        <tenantuser>:<group>      0700
sessions + tmp files       <tenantuser>:<group>      0600
```

De root-applytool weigert symlinks in de tenantboom vóór recursieve ownershipwijzigingen en controleert dat de fysieke shared release niet world-writable of via de tenantidentity schrijfbaar is. Hij wijzigt shared-code ownership/modes nooit.

## Veilige releasewissel — fase 4.7

De gedeelde code maakt later een releaseflow mogelijk zonder tenantcode te kopiëren:

1. nieuwe commit naar een nieuwe immutable map onder `releases/` plaatsen;
2. centrale tests uitvoeren;
3. tenant-deployment/runtime preflight uitvoeren tegen de nieuwe release;
4. pas na succesvolle checks `current` atomisch naar de nieuwe release laten wijzen;
5. smoke tests per tenant uitvoeren;
6. oude release tijdelijk beschikbaar houden voor snelle rollback.

Tenantdata, uploads, auth en sessies blijven bij zo'n codewissel in `/srv/verenigingen/<key>/private` staan.

## Resterende fase 4-stappen

Na de code/CI-voorbereiding van fase 4.1 volgen volgens `docs/ROADMAP.md`:

- **4.2:** concrete Apache/Nginx-vhosts en veilige reloadprocedure;
- **4.3:** DNS;
- **4.4:** TLS/certificaten en renewal;
- **4.5:** PDO/database-secret provisioning;
- **4.6:** monitoring, healthchecks en centrale logging;
- **4.7:** release- en rollbackautomation;
- **4.8:** tenant lifecycle (disable/export/remove).

De daadwerkelijke 4.1 `--apply`-handeling wordt pas op de toekomstige VPS uitgevoerd. Code/CI-gereed betekent dus nadrukkelijk niet dat er al Linux-accounts of PHP-FPM pools op een productie-VPS zijn aangemaakt.
