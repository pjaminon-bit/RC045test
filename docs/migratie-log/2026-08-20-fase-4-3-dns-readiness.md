# Migratielog — fase 4.3 DNS readiness

Datum: **20-08-2026**

## Doel

De fase-4.2 Apache-artifacts mogen niet naar TLS/livegang zolang niet aantoonbaar is dat het tenantdomein exact naar de bedoelde VPS-route wijst.

## Opgeleverd

- `app/deployment/dns-contract.php`
  - tenantgebonden `dns-plan.json`;
  - direct A/AAAA-profiel;
  - CNAME-profiel met maximaal één hop;
  - exacte RRsetvergelijking;
  - canonical IP/DNS-normalisatie;
  - bronbinding aan `web-plan.json`;
  - verifier voor verse `dns-readiness.json`.
- `bin/prepare-vps-dns.php`
  - root-vrije deterministische plangenerator;
  - geen providercredentials/secrets;
  - path/symlinkgrenzen;
  - `--force` trekt bestaande readiness in wanneer het plan wijzigt.
- `bin/check-vps-dns.php`
  - live systeemresolver;
  - standaard 3 samples / 2 seconden;
  - stale A/AAAA/CNAME fail-closed;
  - mislukte check trekt oudere readiness in;
  - TOCTOU-hercontrole van het DNS-plan;
  - readiness maximaal 15 minuten geldig.
- `tests/phase43-dns-readiness.php`
  - directe en CNAME-strategie;
  - IPv4/IPv6-stale-recordscenario's;
  - CNAME-mix/keten/wrong-target;
  - planbinding, pad/symlink/secrets;
  - readiness bronhash, resolver-mode, samples en expiry.
- `docs/VPS-DNS.md`
  - operationeel contract en voorbeelden.
- CI/DEV smoke guards uitgebreid voor 4.3 tooling/test/documentatie.

## Bewuste grens

Fase 4.3 automatiseert geen writes naar een specifieke DNS-provider. De providerrecords worden pas ingesteld wanneer echte domeinen en VPS-IP's bekend zijn. Provider-onafhankelijkheid blijft behouden doordat `dns-plan.json` de gewenste eindtoestand vastlegt en de live checker de publiek zichtbare uitkomst valideert.

## Securitybeslissingen

- niet-opgegeven IP-family = moet afwezig zijn;
- extra/stale A of AAAA = niet ready;
- direct profiel + CNAME = niet ready;
- CNAME-profiel + direct owner-address = niet ready;
- CNAME-doel is literal en tenantgebonden;
- maximaal één CNAME-hop;
- readiness moet van de systeemresolver komen;
- minimaal drie opeenvolgende samples;
- readiness vervalt na 900 seconden;
- planwijziging of mislukte live check trekt oude readiness in;
- 4.4 moet readiness opnieuw cryptografisch op bronhash en inhoud valideren voordat TLS wordt geactiveerd.

## Volgende stap

**4.4 — TLS/HTTPS**: certificaatuitgifte, HTTPS/default-SNI-vhosts, complete Apache configtest en gecontroleerde activatie/reload.
