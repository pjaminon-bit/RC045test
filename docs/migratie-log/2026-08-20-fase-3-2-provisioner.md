# Fase 3.2 — tenant provisioner

Datum: 2026-08-20

## Doel

Een nieuwe vereniging reproduceerbaar en veilig kunnen voorbereiden zonder applicatiecode te kopiëren.

## Opgeleverd

- `bin/provision-tenant.php` als CLI-only provisioner.
- Vereiste invoer: tenant key, naam, site-URL en absolute tenant-basisroot.
- Optionele timezone, JSON/PDO-driver, `--force` en `--dry-run`.
- Tenantmap met:
  - `config.php`
  - `runtime.env`
  - `tenant.json`
  - `private/collections/`
  - `private/backups/`
- Tenantroot binnen de gedeelde applicatie/documentroot wordt geweigerd.
- Bestaande afwijkende configuratie wordt zonder `--force` niet overschreven.
- Identieke tweede runs blijven ongewijzigd (idempotent).
- De provisioner weigert HTTP-uitvoering met 403.
- `tests/phase32-provisioner.php` valideert create, idempotency, conflict, force, dry-run en onveilige paden.
- CI voert de provisionertest uit; DEV smoke controleert dat de CLI-route via HTTP 403 geeft.
- `docs/PROVISIONING.md` beschrijft het huidige provisioningcontract.

## Niet in deze fase

- DNS/TLS-automatisering.
- Apache/Nginx/PHP-FPM configuratie genereren of activeren.
- PDO-secret provisioning.
- Publieke tenant uploads/assets koppelen.
- Eerste beheerder automatisch aanmaken.

## Volgende stap

Fase 3.3 kan het gegenereerde tenantcontract vertalen naar deploy/vhostconfiguratie en een echte testtenant naast RC045 opzetten, zonder tweede codekopie.
