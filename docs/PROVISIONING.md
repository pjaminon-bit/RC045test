# Tenant provisioning

De applicatiecode wordt gedeeld. Iedere vereniging krijgt buiten de code/documentroot een eigen tenantmap met server-only configuratie en private opslag.

## Nieuwe tenant aanmaken

Voorbeeld:

```bash
php bin/provision-tenant.php \
  --key=voorbeeldvereniging \
  --name="Voorbeeldvereniging" \
  --url=https://voorbeeldvereniging.nl \
  --root=/srv/verenigingen
```

Dit maakt aan:

```text
/srv/verenigingen/voorbeeldvereniging/
├── config.php
├── runtime.env
├── tenant.json
└── private/
    ├── collections/
    ├── auth/
    ├── audit/
    ├── security/
    └── backups/
        └── auth/
```

De provisioner kopieert de applicatiecode **niet**.

## Runtime koppelen

De webserver/PHP-runtime van deze vereniging krijgt minimaal:

```text
VERENIGING_REQUIRE_TENANT_CONFIG=1
VERENIGING_CONFIG_FILE=/srv/verenigingen/voorbeeldvereniging/config.php
```

`VERENIGING_REQUIRE_TENANT_CONFIG=1` is de securitygrens voor de gedeelde VPS. Als `VERENIGING_CONFIG_FILE` dan ontbreekt, relatief is, onleesbaar is of naar een niet-bestaand bestand wijst, stopt de applicatie met een configuratiefout. Er wordt **nooit** teruggevallen op RC045/defaultconfiguratie.

`config.php` bevat zelf de tenant-eigen `private_root`. `runtime.env` wordt als hulpmiddel gegenereerd en bevat de verplichte fail-closed vlag automatisch. Het bestand is niet bedoeld om via HTTP te worden aangeboden.

De bestaande losse RC045/DEV-installatie blijft voorlopig compatibel wanneer `VERENIGING_REQUIRE_TENANT_CONFIG` niet is gezet. Die compatibiliteitsmodus is niet bedoeld als configuratie voor nieuwe VPS-tenants.

## Beheer-auth per tenant

Nieuwe tenants gebruiken uitsluitend deze server-only authpaden:

```text
private/auth/master.php
private/auth/users.json
private/audit/log.json
private/security/login-attempts.json
private/security/.login-attempts.lock
private/backups/auth/
```

Er is voor een tenant met `private_root` **geen fallback** naar `beheer-config.php`, `beheer-users.json`, `beheer-log.json` of `beheer-login-pogingen.json` in de gedeelde applicatieroot.

De provisioner maakt bewust géén standaard beheerderswachtwoord aan. Een nieuwe tenant blijft voor beheer ongeconfigureerd totdat `private/auth/master.php` veilig server-side is geplaatst met minimaal een `password_hash()`:

```php
<?php
$BEHEER_WACHTWOORD_HASH = '...password_hash-resultaat...';
```

Een generiek of gedeeld standaardwachtwoord tussen verenigingen is dus niet onderdeel van provisioning.

## Veiligheidsregels

- `--root` moet absoluut zijn.
- Tenantdata binnen de applicatie/documentroot wordt geweigerd.
- Nieuwe geprovisioneerde tenants krijgen altijd `VERENIGING_REQUIRE_TENANT_CONFIG=1` in `runtime.env`.
- Een ontbrekende tenantconfig faalt in die modus gesloten; RC045/defaultconfig wordt niet geladen.
- Authbestanden van externe tenants staan onder hun eigen `private_root`; gedeelde root-authdata wordt niet gebruikt als fallback.
- Authmappen worden met server-only directoryrechten voorbereid; geschreven authbestanden worden naar `0640` aangescherpt.
- Een onbekende waarde voor `VERENIGING_REQUIRE_TENANT_CONFIG` wordt als configuratiefout geweigerd in plaats van stil als aan/uit geïnterpreteerd.
- Een bestaande afwijkende `config.php`, `runtime.env` of `tenant.json` wordt zonder `--force` niet overschreven.
- Dezelfde opdracht nogmaals uitvoeren is idempotent: identieke bestanden blijven ongewijzigd.
- `--dry-run` laat zien wat zou gebeuren zonder mappen of bestanden aan te maken.
- De provisioner weigert uitvoering via HTTP en is alleen voor CLI bedoeld.

## Opties

```text
--timezone=Europe/Amsterdam
--driver=json
--driver=pdo
--force
--dry-run
```

Bij `--driver=pdo` worden nog geen databasecredentials door de CLI gevraagd of opgeslagen. Die worden in een latere deployment/provisioningfase via secrets/environment gekoppeld.

## Nog handmatig in fase 3.2

De provisioner maakt nog geen DNS-record, TLS-certificaat, Apache/Nginx-vhost, PHP-FPM pool of mastercredential aan. Hij levert wel de vaste tenantpaden en runtimeconfig waarop die automatisering in een volgende fase kan steunen.
