# Fase 4.4 — TLS/HTTPS

Status per **20-08-2026**: code/CI gereed; daadwerkelijke certificaatuitgifte en activatie gebeuren pas op de echte VPS.

## Doel

Fase 4.4 maakt van de in 4.2 voorbereide Apache-routing en de in 4.3 bewezen DNS-route een veilig activeerbare HTTPS-tenant.

De volgorde is bewust fail-closed:

1. verse `dns-readiness.json` valideren;
2. actieve tenant-FPM-socket en exact geïnstalleerd 4.2-routingfragment eisen;
3. alleen HTTP-01 challenge-vhost + globale HTTP catch-all activeren;
4. `apache2ctl configtest` en pas daarna reload;
5. DNS direct vóór ACME opnieuw live controleren;
6. Certbot `certonly --webroot` uitvoeren;
7. certificaat + private key inhoudelijk controleren;
8. HTTPS catch-all en tenant-vhost plaatsen;
9. volledige actieve Apache-config opnieuw testen;
10. pas daarna HTTPS reload/activatie afronden.

## Waarom Certbot webroot

De canonieke ACME-client is **Certbot**. We gebruiken alleen de authenticator:

```text
certbot certonly --webroot
```

Niet `certbot --apache`.

Hierdoor mag Certbot geen vhosts, redirects, proxyregels of tenant-FPM-routing zelfstandig wijzigen. Certbot schrijft alleen challengebestanden en beheert zijn eigen certificate lineage onder `/etc/letsencrypt`.

Het ACME-account is operator-/VPS-breed en moet vooraf geregistreerd zijn. E-mail/accountgegevens horen niet in `tls-plan.json` of tenantbundles.

## TLS-bundle genereren

Na een verse 4.3 readiness:

```bash
php bin/prepare-vps-tls.php \
  --dns-readiness=/srv/verenigingen/noorderhaven/dns/dns-readiness.json
```

Standaard ontstaat:

```text
/srv/verenigingen/noorderhaven/tls/
├── tls-plan.json
├── 000-000-verenigingsplatform-http-catchall.conf
├── 000-000-verenigingsplatform-https-catchall.conf
├── 100-vp-noorderhaven-http.conf
├── 200-vp-noorderhaven-https.conf
└── 50-verenigingsplatform-apache-reload
```

De `000-000-` prefix is bewust gekozen om vóór de gangbare Ubuntu/Debian defaults te sorteren. De root-apply controleert bovendien de werkelijke `sites-enabled` volgorde.

## HTTP-01 gedrag

Per tenant bestaat één eigen ACME-webroot:

```text
/var/lib/verenigingsplatform/acme/<tenant-key>
```

Op HTTP is alleen een geldig pad onder:

```text
/.well-known/acme-challenge/<token>
```

publiek bereikbaar. Alle andere HTTP-requests worden met 308 naar de **vaste canonical host** op HTTPS gestuurd. De request-Host wordt nooit als redirectdoel gebruikt.

Onbekende HTTP-hosts vallen in de globale catch-all en krijgen geen tenantcontent of PHP-routing.

## Certificaatlineage

Iedere tenant krijgt een deterministische Certbot-certificaatnaam:

```text
vp-<tenant>-<hash>
```

Apache wijst rechtstreeks naar:

```text
/etc/letsencrypt/live/<cert-name>/fullchain.pem
/etc/letsencrypt/live/<cert-name>/privkey.pem
```

Voor activatie controleert de root-tool:

- dat de `live` symlinks fysiek binnen de verwachte `/etc/letsencrypt/archive/<cert-name>/` lineage eindigen;
- dat certificate en private key bij elkaar horen;
- dat de SAN-set exact uit de canonical tenant-host bestaat;
- dat het certificaat geldig is en minstens zeven dagen resterende geldigheid heeft;
- dat de private key niet groep- of wereldtoegankelijk is;
- dat de Certbot renewal-config aantoonbaar `webroot` als authenticator gebruikt.

Private keys worden nooit in JSON, Git of tenantbundles gekopieerd.

## HTTPS catch-all

Een onbekende SNI/Host mag niet op het certificaat van de alfabetisch eerste tenant uitkomen. Daarom genereert de VPS één lokaal, self-signed **reject-certificaat** voor:

```text
invalid.verenigingsplatform.invalid
```

Dat certificaat/keypaar leeft uitsluitend onder:

```text
/etc/verenigingsplatform/tls/
```

De eerste/default `*:443` vhost gebruikt dit neutrale certificaat en `Require all denied`.

Het reject-certificaat is geen trust-certificaat voor bezoekers; het doel is juist dat onbekende TLS-hosts nooit tenantidentiteit of tenantcontent krijgen.

## Tenant HTTPS-vhost

Iedere tenant-vhost bevat:

- exact één `ServerName`;
- geen `ServerAlias`;
- `SSLStrictSNIVHostCheck On`;
- TLS 1.0 en 1.1 uit;
- TLS-compressie uit;
- HSTS `max-age=31536000`;
- bewust **geen** `includeSubDomains`;
- controle dat zowel `SSL_TLS_SNI` als `Host` bij dezelfde tenant horen;
- include van exact het in 4.2 geïnstalleerde tenant-routingfragment.

De dubbele Host/SNI-controle voorkomt dat een TLS-connectie voor tenant A met een HTTP Host van tenant B stil naar B wordt omgebogen.

## Root-vrije preflight

```bash
php bin/apply-vps-tls.php \
  --plan=/srv/verenigingen/noorderhaven/tls/tls-plan.json \
  --check
```

`--check`:

- vereist een nog verse 4.3 DNS-readiness;
- valideert alle bronhashes;
- regenereert het deterministische TLS-plan;
- vergelijkt alle tenant-lokale artifacts;
- voert geen root-, ACME- of Apache-write uit.

## Root-activatie op de echte VPS

```bash
sudo php bin/apply-vps-tls.php \
  --plan=/srv/verenigingen/noorderhaven/tls/tls-plan.json \
  --apply
```

Voorwaarden:

- Linux root/EUID 0;
- Apache >= 2.4.49;
- vereiste modules inclusief `ssl_module` geladen;
- Certbot >= 2.0 en bestaand ACME-account;
- fase 4.1 root-runtime daadwerkelijk toegepast en tenant-FPM socket actief;
- fase 4.2 routingfragment exact geïnstalleerd;
- fase 4.3 readiness nog vers;
- live DNS vlak vóór Certbot nog steeds exact conform plan.

Bij mislukte eerste ACME-uitgifte probeert de tool nieuw geactiveerde tenant-HTTP/catch-all links weer te verwijderen en Apache terug te laden naar de eerdere geldige config.

## Renewal

Certbot beheert de renewal-config. Fase 4.4 installeert één platformbrede deploy-hook:

```sh
#!/bin/sh
set -eu
/usr/sbin/apache2ctl configtest
/usr/bin/systemctl reload apache2
```

Certbot voert deploy-hooks alleen na een succesvolle uitgifte/renewal uit. De hook reloadt Apache dus nooit wanneer de actieve configuratie niet eerst door `configtest` komt.

## Securitygrenzen

- geen tenantcertificaat als default/catch-all certificaat;
- geen Host-headerreflectie in redirects;
- geen Certbot Apache-installer;
- geen wildcardcertificaten in dit contract;
- één certificaatlineage per canonical tenant-host;
- Host én SNI moeten bij dezelfde tenant horen;
- geen private key in tenantdata/Git;
- geen ACME-accountcontactdata in tenantbundles;
- TLS wordt niet geactiveerd zonder actieve tenant-FPM-route;
- elke reload wordt voorafgegaan door een volledige Apache configtest.

## Wat nog niet gebeurt in CI/DEV

CI kan geen echte Let's Encrypt-uitgifte of root-Apache-activatie uitvoeren. De test bewijst daarom deterministisch de contracten, generated configs, bronbindingen en de root-tool-flow. De echte `--apply` wordt pas op de productie-VPS uitgevoerd.

## Volgende stap

Na 4.4 volgt **4.5 — database provisioning**.
