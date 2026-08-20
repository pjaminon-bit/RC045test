# Fase 3.2.1 — runtime fail-closed

Datum: 2026-08-20

## Doel

Voorkomen dat een verkeerd geconfigureerde multi-tenant vhost of PHP-runtime zonder tenantconfig stil terugvalt op de ingebouwde RC045/defaultconfiguratie.

## Securitykeuze

Nieuwe/gedeelde VPS-tenants draaien met:

```text
VERENIGING_REQUIRE_TENANT_CONFIG=1
```

Wanneer deze vlag actief is moet `VERENIGING_CONFIG_FILE` aanwezig zijn en naar een absoluut, leesbaar bestand wijzen. Anders stopt de runtime direct met een configuratiefout.

Voor webrequests wordt zo'n fout afgehandeld als generieke HTTP 503. De browser krijgt geen intern pad, environmentvariabele of stacktrace uit deze runtimehelper te zien. De concrete oorzaak wordt wel met prefix `[platform] tenant runtime configuratiefout` naar de serverlog geschreven. CLI blijft de concrete exception krijgen voor diagnose en tests.

## Wijzigingen

- `tenantRuntimeConfigVerplicht()` toegevoegd.
- Geldige aan-waarden: `1`, `true`, `yes`, `on`.
- Geldige uit-waarden: leeg, `0`, `false`, `no`, `off`.
- Onbekende waarden falen gesloten in plaats van impliciet aan/uit te worden geïnterpreteerd.
- `tenantRuntimeExternConfigPad()` weigert een ontbrekende config wanneer de verplichte modus actief is.
- `tenantRuntimeConfiguratieFout()` centraliseert veilige foutafhandeling: HTTP 503 + generieke melding voor web, concrete exception voor CLI, details in serverlog.
- Ook ongeldige runtimepaden gebruiken deze generieke HTTP-afhandeling zodat serverpaden niet in de response hoeven te verschijnen.
- `bin/provision-tenant.php` schrijft voor iedere nieuwe tenant automatisch `VERENIGING_REQUIRE_TENANT_CONFIG=1` naar `runtime.env`.
- `tenant.json` registreert `require_tenant_config: true`.
- Provisioningdocumentatie bijgewerkt.

## Achterwaartse compatibiliteit

Zonder `VERENIGING_REQUIRE_TENANT_CONFIG` blijft de huidige standalone RC045/DEV-installatie voorlopig de bestaande defaults en `site-config.local.php` ondersteunen. Dit is een migratiecompatibiliteitsmodus en niet de bedoelde VPS-productieconfiguratie.

## Tests

`tests/phase321-runtime-failclosed.php` valideert:

- standalone RC045 blijft zonder vlag werken;
- expliciet uitgeschakelde verplichte modus blijft compatibel;
- verplichte modus zonder config faalt;
- daarbij wordt geen succesvolle RC045 fallback uitgevoerd;
- verplichte modus met geldige externe config werkt;
- ongeldige booleaanse vlag faalt gesloten;
- een niet-bestaand/onleesbaar extern configpad faalt gesloten;
- de HTTP-foutafhandeling bevat expliciet status 503, een generieke gebruikersmelding en serverlogging voor de interne oorzaak.

`tests/phase32-provisioner.php` valideert aanvullend dat geprovisioneerde tenants de fail-closed vlag zowel in `runtime.env` als in het manifest krijgen.

## Niet onderdeel van deze wijziging

Deze stap verandert nog niet de tenant-key-validatie, padcanonicalisatie/symlinkcontrole, auth-isolatie, publieke content, uploads of backups. Die blijven afzonderlijke auditbevindingen binnen fase 3.2.1.
