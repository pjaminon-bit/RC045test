# Fase 4.2 — Apache webserver & vhosts

Status: **actueel productiecontract; fase 4.2 is op de VPS gevalideerd.** De webserverlaag gebruikt sinds hardening #226 uitsluitend de minimale `public/`-subdirectory van de actieve immutable release als DocumentRoot.

## Keuze: één ondersteunde productie-webserver

**Apache HTTP Server 2.4 op Ubuntu/Debian** is de canonieke VPS-stack. Minimumversie: **Apache 2.4.49**, onder meer vanwege `StrictHostCheck`.

De VPS-securitygrens rust niet meer op een breed toegankelijke repository-root met een dubbel onderhouden denylist. Interne applicatiecode, tooling, tests en documentatie liggen fysiek buiten de DocumentRoot. De repository-`.htaccess` blijft alleen relevant binnen de publieke `public/`-boom en voor standalone/templatecompatibiliteit.

## Public-rootarchitectuur

Voor de actieve release:

```text
/srv/verenigingsplatform/current -> releases/<40-hex-commit>
/srv/verenigingsplatform/current/public/   # enige DocumentRoot
```

Het actuele contract is:

- `app_root` is `/srv/verenigingsplatform/current`;
- `document_root` is exact `app_root/public`;
- de release-root zelf is niet webtoegankelijk en krijgt `Require all denied`;
- interne paden zoals `app/`, `bin/`, `tests/`, `docs/`, `.github/` en `.git/` liggen buiten de DocumentRoot en hoeven niet door een web-denylist te worden beschermd;
- alleen `public/index.php` is een fysiek uitvoerbaar PHP-entrypoint;
- virtuele publieke `.php`-routes worden door de publieke frontcontroller afgehandeld;
- aliases buiten `public/` en generieke `ProxyPass`/`ProxyPassMatch`-routes zijn verboden.

## Waarom een expliciete catch-all nodig is

Apache gebruikt bij name-based virtual hosts de eerste vhost voor een IP/poort wanneer geen `ServerName` of `ServerAlias` overeenkomt. Daarom mag de eerste `*:80` vhost nooit een tenant zijn.

```apache
<VirtualHost *:80>
    ServerName invalid.verenigingsplatform.invalid
    StrictHostCheck On
    ProxyRequests Off
    <Location "/">
        Require all denied
    </Location>
</VirtualHost>
```

Bestandsnaam:

```text
000-verenigingsplatform-http-catchall.conf
```

De catch-all routeert nooit naar PHP/FPM en bevat geen tenantnaam, socket of alias.

## Tenant HTTP-vhost

Iedere tenant krijgt een afzonderlijk HTTP-bestand met uitsluitend de exacte canonical host uit `deployment.json`:

```apache
<VirtualHost *:80>
    ServerName noorderhaven.example
    ProxyRequests Off
    Redirect permanent "/" "https://noorderhaven.example/"
</VirtualHost>
```

Belangrijk:

- geen `ServerAlias`;
- geen `%{HTTP_HOST}`, `$host` of andere request-afgeleide redirectdoelen;
- geen PHP-handler en geen FPM-socket op poort 80;
- `Redirect` gebruikt een literal doelhost.

## HTTPS-routingfragment

Fase 4.2 genereert het routingfragment dat fase 4.4 in de tenant-HTTPS-vhost opneemt. Conceptueel:

```apache
UseCanonicalName On
ProxyRequests Off
DocumentRoot "/srv/verenigingsplatform/current/public"
DirectoryIndex index.php

<Directory "/">
    Options None
    AllowOverride None
    Require all denied
</Directory>

<Directory "/srv/verenigingsplatform/current">
    Options None
    AllowOverride None
    Require all denied
</Directory>

<Directory "/srv/verenigingsplatform/current/public">
    Options -Indexes -ExecCGI -MultiViews +FollowSymLinks
    AllowOverride FileInfo Indexes Options
    Require all granted
</Directory>

<FilesMatch "(?i)\\.php$">
    <RequireAll>
        Require all granted
        Require not expr "-f '%{REQUEST_FILENAME}'"
    </RequireAll>
</FilesMatch>

<Files "index.php">
    AuthMerging Off
    Require all granted
    SetHandler "proxy:unix:/run/php/<tenant-pool>.sock|fcgi://<tenant-pool>/"
</Files>
```

De parent van `current` mag de release-symlink volgen, maar blijft inhoudelijk denied. De release-root zelf blijft eveneens denied. Alleen `public/` wordt aan clients aangeboden.

De FastCGI backendnaam na `|` is tenant-uniek. Daardoor zijn zowel Unix-socket als logische proxy-workeridentity aan dezelfde tenantpool gebonden.

## Waarom geen brede denylist meer

Voor #226 was de gehele release de DocumentRoot en moesten interne routes met Apache-regels en `.htaccess` worden geweigerd. Dat model is vervangen door een positieve public-rootgrens:

- interne code ligt fysiek buiten de DocumentRoot;
- release-root is fail-closed denied;
- alleen de publieke boom is granted;
- alleen `index.php` krijgt de FPM-handler.

Denyregels in `.htaccess` mogen defense-in-depth blijven voor daadwerkelijk publieke namen, maar vormen niet de primaire scheiding tussen publieke en interne repository-inhoud.

## 1. Webserverbundle genereren

```bash
php bin/prepare-vps-webserver.php \
  --runtime-plan=/srv/verenigingen/noorderhaven/runtime/runtime-plan.json
```

Standaard ontstaat:

```text
/srv/verenigingen/noorderhaven/webserver/
├── web-plan.json
├── 000-verenigingsplatform-http-catchall.conf
├── 100-vp-noorderhaven-http.conf
└── vst-noorderhaven-<hash>.https-routing.inc.conf
```

Voor alleen validatie:

```bash
php bin/prepare-vps-webserver.php \
  --runtime-plan=/srv/verenigingen/noorderhaven/runtime/runtime-plan.json \
  --dry-run
```

De generator:

- valideert het volledige runtimeplan opnieuw;
- controleert de SHA-256-binding met `deployment.json`;
- eist dat DocumentRoot exact de fysieke `app_root/public`-subdirectory is;
- herleidt canonical host, pool en socket opnieuw;
- accepteert alleen output binnen de eigen tenantroot;
- weigert symlinks en secretachtige CLI-argumenten;
- schrijft atomisch en deterministisch;
- gebruikt mode `0640` voor tenant-lokale artifacts.

## 2. Root-vrije check

```bash
php bin/apply-vps-webserver.php \
  --plan=/srv/verenigingen/noorderhaven/webserver/web-plan.json \
  --check
```

`--check` vereist geen Apache en geen root. De tool valideert bronbindings opnieuw, bouwt het plan deterministisch na en vergelijkt de gegenereerde Apache-artifacts byte-inhoudelijk.

## 3. Root-installatie

```bash
sudo php bin/apply-vps-webserver.php \
  --plan=/srv/verenigingen/noorderhaven/webserver/web-plan.json \
  --apply
```

De root-tool ondersteunt uitsluitend de vaste Ubuntu/Debian paden:

```text
/etc/apache2/sites-available
/etc/apache2/sites-enabled
/etc/verenigingsplatform/apache/fragments
```

Voor installatie controleert hij onder meer Linux/EUID 0, Apacheversie, vereiste modules, veilige doelmappen, alle planbindings en Apache-syntax. Artifacts worden atomisch als `root:root 0644` geplaatst. Een reeds actief afwijkend sitebestand wordt niet stil overschreven.

## 4. Activatiegrens

De fase-4.2 tool installeert de routingartifacts maar activeert of reloadt geen half-geconfigureerde tenant. DNS/TLS-activatie loopt via de fase-4.3/4.4 contracten en de georkestreerde bootstrap/releaseflow.

Voor een live activatie gelden altijd:

1. globale HTTP/HTTPS catch-all aanwezig;
2. exacte tenantvhosts aanwezig;
3. geldig TLS-certificaat en vaste canonical host;
4. `apache2ctl configtest` over de volledige kandidaatconfig;
5. gecontroleerde enable/reload;
6. host-, redirect-, TLS- en FPM-smoketests.

## 5. Securitygrenzen

- onbekende hosts gaan nooit naar een tenant;
- geen Host-header reflection in redirects;
- HTTP routeert niet naar PHP;
- tenant A PHP gaat uitsluitend naar tenant A socket/backend;
- tenantroot/private root wordt nooit geserveerd;
- **alleen `app_root/public` is DocumentRoot**;
- de release-root zelf is denied;
- interne repositorycode ligt buiten de DocumentRoot;
- alleen `public/index.php` is fysieke PHP/FPM-entrypoint;
- aliases buiten de public-root zijn verboden;
- generieke ProxyPass-routes zijn verboden;
- artifacts bevatten geen secrets.

## Apache-bronnen

De implementatie volgt Apache HTTP Server 2.4 voor name-based vhosts, `StrictHostCheck`, redirects, `mod_proxy_fcgi`, includes en configtests. De repositorycode in `app/deployment/webserver-contract.php` is de uitvoerbare bron van waarheid voor de exacte gegenereerde configuratie.
