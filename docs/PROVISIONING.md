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
    └── backups/
```

De provisioner kopieert de applicatiecode **niet**.

## Runtime koppelen

De webserver/PHP-runtime van deze vereniging krijgt minimaal:

```text
VERENIGING_CONFIG_FILE=/srv/verenigingen/voorbeeldvereniging/config.php
```

`config.php` bevat zelf de tenant-eigen `private_root`. `runtime.env` wordt als hulpmiddel gegenereerd en is niet bedoeld om via HTTP te worden aangeboden.

## Veiligheidsregels

- `--root` moet absoluut zijn.
- Tenantdata binnen de applicatie/documentroot wordt geweigerd.
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

De provisioner maakt nog geen DNS-record, TLS-certificaat, Apache/Nginx-vhost of PHP-FPM pool aan. Hij levert wel de vaste tenantpaden en runtimeconfig waarop die automatisering in een volgende fase kan steunen.
