# Fase 4.3 — DNS planning & readiness

Status per **20-08-2026**: code/CI gereed; echte DNS-records worden pas ingesteld zodra het VPS-adres en de tenantdomeinen definitief zijn.

## Doel

Fase 4.3 vormt de harde grens tussen de in 4.2 voorbereide, nog inactieve Apache-configuratie en de TLS/HTTPS-activatie van fase 4.4.

Een certificaat mag pas worden aangevraagd wanneer aantoonbaar is dat de exacte canonical host van de tenant via DNS naar de bedoelde VPS-route wijst. Een oud A- of AAAA-record naar een andere server telt daarom als fout, ook wanneer één correct record al aanwezig is.

## Ondersteunde strategieën

### 1. `direct`

De canonical host heeft rechtstreeks één of meer A- en/of AAAA-records.

Voorbeeldcontract:

```text
noorderhaven.example.  A     203.0.113.10
noorderhaven.example.  AAAA  2001:db8::10
```

Het plan verwacht exact deze RRsets:

- alle opgegeven IPv4-adressen moeten aanwezig zijn;
- alle opgegeven IPv6-adressen moeten aanwezig zijn;
- niet-opgegeven of oude A/AAAA-adressen zijn verboden;
- CNAME op de owner is verboden.

### 2. `cname`

De canonical host heeft exact één CNAME naar een vast doel. Dat CNAME-doel moet zelf rechtstreeks naar de verwachte VPS-adressen resolven.

Voorbeeldcontract:

```text
www.noorderhaven.example.  CNAME  vps.example.net.
vps.example.net.           A      203.0.113.10
vps.example.net.           AAAA   2001:db8::10
```

Fase 4.3 staat bewust maar **één CNAME-hop** toe. Extra ketens maken de route minder expliciet en worden fail-closed geweigerd.

Voor een apex/rootdomein is `direct` de veilige standaard. Provider-specifieke ALIAS/ANAME-flattening valt niet onder het huidige contract; als een provider dat intern gebruikt, moet de publiek zichtbare DNS-uitkomst alsnog als het gekozen fase-4.3 profiel valideerbaar zijn.

> De adressen in deze documentatie zijn gereserveerde documentatie-adressen en geen productie-IP's.

## 1. DNS-plan genereren

Direct:

```bash
php bin/prepare-vps-dns.php \
  --web-plan=/srv/verenigingen/noorderhaven/webserver/web-plan.json \
  --strategy=direct \
  --ipv4=<VPS-IPV4> \
  --ipv6=<VPS-IPV6>
```

Alleen IPv4 is ook toegestaan wanneer de tenant bewust geen IPv6 publiceert:

```bash
php bin/prepare-vps-dns.php \
  --web-plan=/srv/verenigingen/noorderhaven/webserver/web-plan.json \
  --strategy=direct \
  --ipv4=<VPS-IPV4>
```

In dat geval is een publiek AAAA-record juist **niet** toegestaan; een vergeten oud IPv6-record kan anders verkeer naar een verkeerde server sturen.

CNAME:

```bash
php bin/prepare-vps-dns.php \
  --web-plan=/srv/verenigingen/noorderhaven/webserver/web-plan.json \
  --strategy=cname \
  --cname=vps.example.net \
  --ipv4=<VPS-IPV4> \
  --ipv6=<VPS-IPV6>
```

Standaard ontstaat:

```text
/srv/verenigingen/noorderhaven/dns/
└── dns-plan.json
```

Het plan:

- bindt byte-exact aan `web-plan.json` uit fase 4.2;
- bindt daarmee transitief aan runtime/deployment/tenantconfig;
- bevat geen DNS-providercredentials of andere secrets;
- wordt `0640` opgeslagen;
- kan alleen binnen de eigen tenantroot worden geschreven;
- weigert symlink-output;
- is deterministisch en idempotent.

`--force` is nodig wanneer het bedoelde DNS-profiel wijzigt. Zodra een plan werkelijk verandert, wordt een eventueel oud `dns-readiness.json` automatisch verwijderd.

## 2. Records bij de DNS-provider instellen

Fase 4.3 schrijft bewust **niet automatisch** naar Cloudflare, Route53, TransIP, Strato of een andere DNS-provider. Providercredentials horen niet in deze generieke tenantbundles.

De operator stelt de records bij de gekozen provider in volgens `dns-plan.json`.

Later kan tenant-lifecycle automation een provideradapter krijgen, maar ook dan blijft `dns-plan.json` het onafhankelijke gewenste-eindtoestandcontract.

## 3. Live readiness controleren

Na de DNS-wijziging, op de toekomstige VPS:

```bash
php bin/check-vps-dns.php \
  --plan=/srv/verenigingen/noorderhaven/dns/dns-plan.json
```

Standaard voert de checker drie live queries uit met twee seconden ertussen via de **geconfigureerde systeemresolver van de VPS**.

Readiness vereist:

- exact de verwachte A/AAAA/CNAME-RRsets;
- geen extra/stale IPv4;
- geen extra/stale IPv6;
- geen gemengd CNAME + address-profiel;
- bij CNAME exact het bedoelde doel;
- bij CNAME exact de verwachte terminale VPS-adressen;
- minimaal 3 succesvolle samples;
- minimaal 2 seconden tussen de samples.

Bij een mismatch:

- exit de checker fail-closed;
- een bestaand ouder readinessbestand wordt ingetrokken;
- fase 4.4 blijft geblokkeerd.

Voor alleen een diagnostische live check zonder statuswrite:

```bash
php bin/check-vps-dns.php --plan=... --no-write
```

`--no-write` mag ook met minder samples worden gebruikt; zo'n run kan nooit als TLS-readinessbewijs dienen.

## 4. Readinessbewijs

Een volledig geslaagde standaardcheck schrijft:

```text
/srv/verenigingen/noorderhaven/dns/dns-readiness.json
```

Het bestand bevat onder andere:

- tenant-key en canonical host;
- DNS-strategie;
- SHA-256 van `dns-plan.json` en `web-plan.json`;
- `resolver_mode: system`;
- aantal samples en interval;
- laatste geobserveerde RRsets;
- UTC-controle- en vervaltijd;
- `ready: true`.

Het bewijs is maximaal **900 seconden / 15 minuten** geldig.

`dns43ReadinessLeesEnValideer()` controleert opnieuw:

1. server-only, symlinkvrij tenantpad;
2. actuele `dns-plan.json`;
3. actuele 4.2-bronhash;
4. tenant/host/strategie-binding;
5. live-system-resolvermarkering;
6. minimaal 3 samples met minimaal 2 seconden interval;
7. inhoudelijke RRset-match;
8. exacte 15-minutengeldigheid;
9. dat het bewijs nog niet verlopen is.

Fase 4.4 moet deze verifier gebruiken vóór certificaatuitgifte of HTTPS-activatie.

## 5. Wat propagation hier betekent

DNS-propagatie wereldwijd is niet atomisch en kan niet door één enkele server volledig worden bewezen. Fase 4.3 claimt daarom bewust geen wereldwijde consensus.

Het readinessbewijs betekent concreet:

> de resolver die de productie-VPS op dit moment gebruikt ziet herhaaldelijk exact de bedoelde DNS-route.

Dat is de relevante minimale preflight vóór ACME/TLS op die VPS. Monitoring via andere resolvers/locaties kan later in fase 4.6 worden toegevoegd zonder het fase-4.3 contract te verzwakken.

## 6. Geen activatie in 4.3

Fase 4.3:

- activeert geen Apache-site;
- reloadt Apache of PHP-FPM niet;
- vraagt geen certificaat aan;
- schrijft geen TLS private key;
- wijzigt geen DNS-provideraccount;
- bevat geen provider/API-secrets.

De 4.2 webserverartifacts blijven dus inactief.

## 7. Volgende stap

Na een vers en geldig `dns-readiness.json` volgt **fase 4.4 — TLS/HTTPS**.

Fase 4.4 bouwt de volledige HTTPS-vhost, koppelt certificaatpaden server-side, voegt een veilige `*:443` default/catch-all toe, voert een complete Apache `configtest` uit en activeert/reloadt pas wanneer DNS, TLS en tenant-FPM-routing als één geheel kloppen.
