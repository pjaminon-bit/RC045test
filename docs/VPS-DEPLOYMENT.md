# VPS deploymentcontract

Status per **20-08-2026**: fase 3.5.1.

Dit document legt de serverlayout vast waarmee meerdere verenigingen dezelfde applicatiecode veilig kunnen gebruiken. Fase 3.5/3.5.1 installeert nog geen DNS, TLS of webserverconfiguratie; zij levert wel het vaste, machineleesbare contract waarop die automation kan bouwen.

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
│   └── private/
│       ├── auth/
│       ├── audit/
│       ├── backups/
│       ├── collections/
│       ├── public-content/
│       ├── security/
│       └── sessions/
└── duinrand/
    └── ... dezelfde tenantstructuur, eigen data ...
```

De webserver gebruikt dus voor iedere vereniging dezelfde logische documentroot, bijvoorbeeld `/srv/verenigingsplatform/current`. De tenantroot onder `/srv/verenigingen/<key>` is **nooit** een documentroot, alias of statische webmap.

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

## PHP-FPM: één pool per tenant

De securitygrens op de VPS is niet alleen de applicatiecode. Iedere tenant hoort een eigen PHP-FPM pool te krijgen met de waarden uit `deployment.json`.

Conceptueel:

```ini
[<deployment.php_fpm.pool>]
user = <deployment.php_fpm.recommended_os_user>
group = <tenant-eigen groep>
listen = <deployment.php_fpm.socket>
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

clear_env = yes
env[VERENIGING_REQUIRE_TENANT_CONFIG] = 1
env[VERENIGING_CONFIG_FILE] = <deployment.runtime_env.VERENIGING_CONFIG_FILE>
env[VERENIGING_PRIVATE_ROOT] = <deployment.runtime_env.VERENIGING_PRIVATE_ROOT>
```

Belangrijk:

- de tenant-runtimegebruiker krijgt schrijfrecht op uitsluitend zijn eigen private root;
- andere tenant-runtimegebruikers krijgen daar geen toegang toe;
- de gedeelde applicatierelease is voor tenant-runtimes read-only;
- `clear_env=yes` voorkomt dat een pool toevallig environment van een andere deployment erft;
- databasecredentials worden apart via server-side secrets gekoppeld en komen niet in `deployment.json`.

De precieze `pm.*` capaciteit wordt later op VPS-capaciteit afgestemd; dat is geen tenantidentiteitsgrens.

## Webservercontract

Voor Apache of Nginx gelden dezelfde harde regels:

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
    # TLS-config en ProxyPassMatch/SetHandler naar exact de tenant-FPM-socket.
</VirtualHost>
```

Voor HTTPS moet eveneens een expliciete default/catch-all configuratie bestaan die een onbekende SNI/Host niet aan de eerste tenantpool koppelt. De concrete TLS-catch-all wordt in de VPS-automation uitgewerkt, omdat certificaatstrategie en Apache/Nginx-keuze daar onderdeel van zijn.

### Nginx — vereiste vorm

Nginx leest `.htaccess` niet. Een Nginx-deployment moet de deny-regels en de exacte routing voor publieke content/assets expliciet overnemen. Gebruik nooit een algemene alias naar `private/`.

Conceptueel begint de configuratie met een default server die onbekende hosts direct weigert, gevolgd door tenantservers met een literal HTTP→HTTPS redirect. Gebruik ook hier nooit `$host` als redirectdoel wanneer `canonical_host` al bekend is.

## Release-inhoud en VCS-metadata

Een live release hoort bij voorkeur uit een build/export zonder `.git` te bestaan. Defense-in-depth blokkeert de gedeelde Apache-laag `.git` daarnaast expliciet. Voor Nginx moet dezelfde deny-regel in de serverconfiguratie worden opgenomen. `deployment.json.web.vcs_metadata_must_not_be_served=true` maakt dit een deploymentvereiste.

## Filesystem ownership

Gewenste richting:

```text
shared release             root/platform beheer   read-only voor tenant-runtimes
tenant config/manifest     tenant runtime/admin   0640
tenant private directories tenant runtime          0750
tenant private files       tenant runtime          0640
```

De provisioningaccount mag de tenantstructuur aanmaken. Voor livegang moet ownership vervolgens aan de bedoelde tenant-runtimeidentity worden gekoppeld. Automatische `chown` is bewust nog niet in de applicatie-CLI ingebouwd: OS-accountbeheer hoort bij de VPS/deploymentlaag en vereist rootrechten.

## Veilige releasewissel

De gedeelde code maakt later een releaseflow mogelijk zonder tenantcode te kopiëren:

1. nieuwe commit naar een nieuwe immutable map onder `releases/` plaatsen;
2. centrale tests uitvoeren;
3. `prepare-vps-deployment.php --dry-run` voor tenants uitvoeren tegen de nieuwe release;
4. pas na succesvolle checks `current` atomisch naar de nieuwe release laten wijzen;
5. smoke tests per tenant uitvoeren;
6. oude release tijdelijk beschikbaar houden voor snelle rollback.

Tenantdata, uploads, auth en sessies blijven bij zo'n codewissel in `/srv/verenigingen/<key>/private` staan.

## Wat fase 3.5.1 nog niet doet

Nog bewust buiten deze stap:

- DNS-records aanmaken/wijzigen;
- Let's Encrypt/andere TLS-certificaten uitgeven en vernieuwen;
- Apache/Nginx-vhosts daadwerkelijk installeren/reloaden;
- Linux users/groups automatisch aanmaken en ownership toepassen;
- PDO-database en databasecredentials provisionen;
- monitoring, healthchecks, logaggregatie en tenant lifecycle (disable/remove/export) automatiseren.

Die onderdelen kunnen nu op één stabiel `deployment.json`-contract bouwen zonder de applicatiecode per vereniging te forken.
