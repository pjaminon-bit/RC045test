# Fase 4.2 — Apache webserver & vhosts

Datum: **20-08-2026**

## Doel

De fase-3.5 deploymentmetadata en fase-4.1 PHP-FPM runtimebinding vertalen naar één deterministische webserverlaag zonder DNS/TLS voortijdig live te zetten.

## Keuze

De productie-VPS standaardiseert vanaf deze fase op **Apache HTTP Server 2.4 op Ubuntu/Debian**. De bestaande applicatie gebruikt een uitgebreid getest `.htaccess`-contract; parallelle Nginx-ondersteuning zou dezelfde security/rewrite-logica dubbel introduceren.

Minimum Apacheversie: **2.4.49** vanwege `StrictHostCheck`.

## Nieuwe componenten

- `app/deployment/webserver-contract.php`
- `bin/prepare-vps-webserver.php`
- `bin/apply-vps-webserver.php`
- `tests/phase42-apache-vhosts.php`
- `docs/VPS-WEBSERVER.md`

## Gegenereerde artifacts per tenant

- `web-plan.json`
- `000-verenigingsplatform-http-catchall.conf`
- `100-vp-<tenant-key>-http.conf`
- `<tenant-fpm-pool>.https-routing.inc.conf`

## Securitycontract

- eerste/default HTTP-vhost is tenant-neutraal en `Require all denied`;
- default HTTP-vhost gebruikt `StrictHostCheck On`;
- tenant HTTP-vhost heeft exact één `ServerName`, geen `ServerAlias`;
- HTTP redirectdoel is literal `https://<canonical-host>/` en gebruikt geen request Host;
- HTTP-vhost bevat geen PHP/FPM routing;
- HTTPS-routing gebruikt uitsluitend de gedeelde release als DocumentRoot;
- iedere tenant routeert PHP exact naar eigen Unix socket én eigen FastCGI backendidentity;
- geen generieke `ProxyPass`/`ProxyPassMatch`;
- tooling/VCS en gevoelige serverbestanden worden ook op serverniveau geweigerd;
- tenant private root wordt nooit DocumentRoot/Alias;
- forward proxy staat uit.

## Inactieve installatie

Omdat 4.3 nog DNS-readiness moet leveren en 4.4 TLS/certificaten toevoegt, activeert fase 4.2 niets.

`apply-vps-webserver.php --apply`:

- vereist Linux root;
- accepteert alleen vaste Ubuntu/Debian Apache-paden;
- controleert Apache >=2.4.49;
- controleert vereiste modules;
- syntax-test de gegenereerde artifacts tegen de echte Apacheconfig via `apache2ctl -t -c Include ...`;
- installeert alleen onder `sites-available` en een dedicated fragmentmap;
- schrijft root:root 0644 atomisch;
- weigert een afwijkend reeds actief sitebestand.

De tool voert bewust geen `a2ensite`, `sites-enabled` write/symlink of reload/restart uit.

## Activatiegrens

Fase 4.4 bouwt later de complete HTTPS-vhost met certificaten, voert een volledige `apache2ctl configtest` uit, activeert pas daarna de site en doet host/TLS/FPM-smoketests.

## Teststrategie

`tests/phase42-apache-vhosts.php` maakt twee echte testtenants via de bestaande provisioning/bootstrap/deployment/runtimeketen en controleert onder andere:

- catch-all volgorde en tenantneutraliteit;
- exacte hostbinding;
- literal redirects;
- cross-tenant socket/backendisolatie;
- server-side denyregels;
- bron-SHA-binding en deterministic/idempotent generatie;
- tamper/stale/symlink/outside-root/secret fail-closed gedrag;
- root/apply contract zonder activatie of reload.

De volledige bestaande platformtests blijven verplicht in dezelfde Actions-workflow.
