# Fase 4.2 — Apache webserver & vhosts

Status per **20-08-2026**: code/CI voor Apache 2.4 gereed; artifacts blijven bewust inactief tot DNS/TLS in fase 4.3/4.4.

## Keuze: één ondersteunde productie-webserver

Vanaf fase 4.2 is **Apache HTTP Server 2.4 op Ubuntu/Debian** de canonieke VPS-stack voor het verenigingsplatform. We implementeren niet parallel ook Nginx. De gedeelde applicatie gebruikt al een uitgebreid, getest `.htaccess`-contract; twee webservers zouden dezelfde kritieke rewrite- en denyregels dubbel moeten onderhouden.

Minimumversie: **Apache 2.4.49**. Deze ondergrens is gekozen omdat `StrictHostCheck` vanaf die versie beschikbaar is.

## Waarom een expliciete catch-all nodig is

Apache gebruikt bij name-based virtual hosts de eerste vhost voor een IP/poort wanneer geen `ServerName` of `ServerAlias` overeenkomt. Daarom mag de eerste `*:80` vhost nooit een tenant zijn.

Fase 4.2 genereert één globale eerste/default vhost:

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

Apache leest wildcard-includes alfabetisch; de `000-` prefix maakt de gewenste eerste positie expliciet wanneer de site later in fase 4.4 wordt geactiveerd.

`StrictHostCheck On` is defense-in-depth. De catch-all zelf routeert nooit naar PHP/FPM en bevat geen tenantnaam, socket of alias.

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
- `Redirect` gebruikt een literal doelhost en behoudt het resterende URL-pad.

Bestandsnaam per tenant:

```text
100-vp-<tenant-key>-http.conf
```

## HTTPS-routingfragment

Fase 4.2 geeft nog geen certificaat uit. Daarom wordt de tenant-PHP-routing als apart fragment voorbereid. Fase 4.4 maakt later de volledige `*:443` vhost met exact `ServerName`, TLS-certificaat en een `Include` van dit fragment.

Conceptueel:

```apache
UseCanonicalName On
ProxyRequests Off
DocumentRoot "/srv/verenigingsplatform/current"
DirectoryIndex index.php index.html

<Directory "/">
    Options None
    AllowOverride None
    Require all denied
</Directory>

<Directory "/srv/verenigingsplatform/current">
    Options -Indexes -ExecCGI +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

<LocationMatch "^/(?:app|bin|tests|docs|\.github|\.git)(?:/|$)">
    Require all denied
</LocationMatch>

<FilesMatch "...gevoelige serverbestanden...">
    Require all denied
</FilesMatch>

<FilesMatch "\.php$">
    SetHandler "proxy:unix:/run/php/<tenant-pool>.sock|fcgi://<tenant-pool>/"
</FilesMatch>
```

De FastCGI backendnaam na `|` is bewust **tenant-uniek**. Apache documenteert dat de hostname in deze handlerconstructie kan worden gewijzigd wanneer backends onderscheiden moeten worden. Daardoor wordt niet alleen de Unix-socket maar ook de logische proxy-workeridentity aan de tenantpool gekoppeld.

Er bestaat geen generieke `ProxyPass` of `ProxyPassMatch` die de tenantbinding kan omzeilen.

## Waarom `AllowOverride All` hier bewust is

De applicatierelease bevat een vertrouwde, centraal beheerde `.htaccess` die onder andere:

- publieke content/assets rewrites uitvoert;
- vriendelijke routes verzorgt;
- aanvullende gevoelige bestanden blokkeert;
- security headers zet;
- `DirectoryIndex` en `Options` gebruikt.

De gedeelde release is vanuit fase 4.1 nooit tenant-writable. Daarom is `AllowOverride All` voor deze **centrale immutable code** acceptabel en voorkomt het dat we dezelfde applicatieregels foutgevoelig dubbel implementeren.

Fase 4.2 voegt daarnaast server-side denyregels toe voor de belangrijkste tooling/VCS/private routes. Deze beschermen dus ook wanneer `.htaccess` onverwacht niet wordt verwerkt.

## 1. Webserverbundle genereren

Voor een fase-4.1 tenant:

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

- valideert het volledige fase-4.1 runtimeplan opnieuw;
- controleert de SHA-256-binding met `deployment.json`;
- herleidt canonical host, DocumentRoot, pool en socket opnieuw;
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

`--check` vereist geen Apache en geen root. De tool:

- valideert het runtimeplan/deploymentcontract opnieuw;
- vergelijkt bron-SHA's;
- bouwt `web-plan.json` opnieuw deterministisch op;
- genereert alle drie Apache-artifacts opnieuw;
- vergelijkt ze byte-inhoudelijk.

Handmatige wijziging van plan, redirect, socket, DocumentRoot of denyregels maakt de bundle ongeldig.

## 3. Inactieve root-installatie

Pas op de toekomstige VPS:

```bash
sudo php bin/apply-vps-webserver.php \
  --plan=/srv/verenigingen/noorderhaven/webserver/web-plan.json \
  --apply
```

De root-tool ondersteunt bewust alleen de vaste Ubuntu/Debian paden:

```text
/etc/apache2/sites-available
/etc/apache2/sites-enabled
/etc/verenigingsplatform/apache/fragments
```

Hij controleert vóór installatie:

- Linux + EUID 0;
- Apacheversie minimaal 2.4.49;
- geladen modules: `alias`, `authz_core`, `dir`, `headers`, `proxy`, `proxy_fcgi`, `rewrite`;
- veilige, symlinkvrije doelmappen;
- alle bronbindings uit `web-plan.json`;
- Apache syntax van de gegenereerde artifacts via `apache2ctl -t -c 'Include ...'`.

Daarna worden de **inactieve** bestanden atomisch als `root:root 0644` geplaatst.

Een reeds actief, afwijkend sitebestand wordt nooit overschreven, ook niet met `--force`.

## 4. Wat `--apply` nadrukkelijk NIET doet

Fase 4.2:

- voert geen `a2ensite` uit;
- maakt geen symlink onder `sites-enabled`;
- schrijft geen TLS-certificaat of private key;
- voert geen `systemctl`, `service`, `reload`, `restart` of `graceful` uit;
- zet dus geen half-geconfigureerde site live.

Dit is nodig omdat DNS-readiness pas in fase 4.3 wordt vastgesteld en de volledige HTTPS-vhost/certificaten pas in fase 4.4 bestaan.

## 5. Configtest en activatie

De 4.2 root-tool syntax-test de afzonderlijke gegenereerde artifacts tegen de daadwerkelijk geladen Apache-config en modules. Dat bewijst dat de bestanden parseerbaar zijn.

De **volledige live configuratie** kan pas worden getest wanneer fase 4.4 de HTTPS-wrapper en certificaatpaden heeft toegevoegd. Vóór activatie/reload moet fase 4.4 daarom:

1. de HTTP catch-all als eerste/default site klaarzetten;
2. de tenant HTTP-vhost klaarzetten;
3. de HTTPS catch-all en tenant TLS-vhost opbouwen;
4. `apache2ctl configtest` over de complete actieve kandidaatconfig uitvoeren;
5. pas bij `Syntax OK` gecontroleerd enable/reload uitvoeren;
6. daarna host-, redirect-, TLS- en FPM-smoketests uitvoeren.

## 6. Securitygrenzen die 4.2 nu vastlegt

- onbekende HTTP hosts gaan nooit naar de eerste tenant;
- geen Host-header reflection in redirects;
- geen HTTP-verzoek gaat rechtstreeks naar PHP;
- tenant A PHP gaat uitsluitend naar tenant A socket/backend;
- tenant B socket kan niet in tenant A fragment voorkomen;
- tenantroot/private root wordt nooit geserveerd;
- shared release is de enige DocumentRoot;
- `app`, `bin`, `tests`, `docs`, `.github`, `.git` zijn server-side geblokkeerd;
- gevoelige config/data-opslagbestanden zijn server-side geblokkeerd;
- forward proxy staat uit;
- generieke ProxyPass-routes zijn verboden;
- artifacts zijn vóór DNS/TLS niet actief.

## 7. Geen secrets

`web-plan.json` en Apache-artifacts bevatten bewust geen:

- beheerwachtwoorden/hashes;
- databasecredentials/DSN;
- TLS private key;
- certificaatinhoud;
- API-tokens.

TLS-paden/secrets worden pas in fase 4.4 server-side gekoppeld.

## 8. Volgende stap

Na fase 4.2 volgt **4.3 — DNS**. Die fase bepaalt en valideert eerst waar tenantdomeinen naartoe wijzen. Pas daarna kan fase 4.4 veilig certificaten aanvragen en de in 4.2 voorbereide vhosts daadwerkelijk activeren.

## Apache-bronnen

De implementatie volgt de officiële Apache HTTP Server 2.4 documentatie voor:

- name-based virtual-host selectie en het gedrag van de eerste/default vhost;
- `StrictHostCheck` (beschikbaar vanaf 2.4.49);
- `Redirect permanent` in een dedicated HTTP-vhost;
- `mod_proxy_fcgi` `SetHandler` met Unix socket;
- `Include`/wildcards en alfabetische volgorde;
- `httpd -t` en `-c` voor syntaxtests.
