# VPS deploymentcontract

Status per **20-08-2026**: fase 3.5.

Dit document legt de serverlayout vast waarmee meerdere verenigingen dezelfde applicatiecode veilig kunnen gebruiken. Fase 3.5 installeert nog geen DNS, TLS of webserverconfiguratie; zij levert wel het vaste, machineleesbare contract waarop die automation kan bouwen.

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
- readiness-vlaggen voor manifestbinding, runtimebinding en adminbootstrap.

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

Voor Apache of Nginx gelden dezelfde regels:

1. `server_name`/`ServerName` is exact `canonical_host` uit het descriptor;
2. HTTP wordt naar HTTPS op **dezelfde canonieke host** gestuurd;
3. documentroot is de gedeelde `shared_code.app_root`;
4. PHP voor die host gaat uitsluitend naar de eigen `php_fpm.socket`;
5. de tenantroot/private root wordt nooit rechtstreeks geserveerd;
6. `public-content.php` en `public-asset.php` blijven de enige gateways naar tenant-publieke data/assets;
7. server-only code en ontwikkeltooling (`app`, `bin`, `tests`, `docs`, `.github`) zijn niet via HTTP bereikbaar.

### Apache

De gedeelde `.htaccess` bevat de bestaande content-/assetrouting en deny-regels. Vanaf fase 3.5 bevat de HTTPS-fallback geen vaste `rc045.nl` meer. De uiteindelijke VPS-vhost hoort canonical-host en TLS desondanks op vhostniveau af te dwingen.

### Nginx

Nginx leest `.htaccess` niet. Een Nginx-deployment moet daarom de deny-regels en de exacte routing voor publieke content/assets expliciet overnemen. Gebruik nooit een algemene alias naar `private/`.

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

## Wat fase 3.5 nog niet doet

Nog bewust buiten deze stap:

- DNS-records aanmaken/wijzigen;
- Let's Encrypt/andere TLS-certificaten uitgeven en vernieuwen;
- Apache/Nginx-vhosts daadwerkelijk installeren/reloaden;
- Linux users/groups automatisch aanmaken en ownership toepassen;
- PDO-database en databasecredentials provisionen;
- monitoring, healthchecks, logaggregatie en tenant lifecycle (disable/remove/export) automatiseren.

Die onderdelen kunnen nu op één stabiel `deployment.json`-contract worden gebouwd zonder de applicatiecode per vereniging te forken.
