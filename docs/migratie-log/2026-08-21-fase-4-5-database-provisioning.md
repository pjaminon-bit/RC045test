# 21-08-2026 — fase 4.5 database provisioning

## Doel

Productieklare, tenantgebonden PDO-databaseprovisioning toevoegen zonder databasecredentials in Git/runtimebundles en zonder DDL vanuit HTTP-requests.

## Gekozen isolatiemodel

- PostgreSQL 16+ lokaal op dezelfde Linux VPS.
- Eén database per tenant.
- Eén NOLOGIN owner-role per tenant.
- PostgreSQL app-role is exact de fase-4.1 Linux/PHP-FPM user.
- Unix-socket `/var/run/postgresql` + `peer` authentication.
- Geen databasepassword of secretsbestand nodig.
- Tenant-HBA: eigen database via peer toestaan; dezelfde user voor alle andere databases expliciet rejecten.

## Nieuwe code

- `app/deployment/database-contract.php`
  - deterministische DB/owner identities;
  - validatie dat tenant expliciet `private_driver=pdo` gebruikt;
  - secretvrij databaseplan/runtime/HBA/migratie-artifacts;
  - plan- en bronhashbinding.
- `bin/prepare-vps-database.php`
  - root-vrije deterministische databasebundlegenerator;
  - geen DSN/password/user-secret CLI-opties;
  - idempotent + expliciete `--force` voor tenant-lokale bundleherstel.
- `bin/apply-vps-database.php`
  - root-only echte PostgreSQL provisioning;
  - collision/tenantmarker checks vóór objectmutaties;
  - PostgreSQL >=16 en vaste lokale socket verplicht;
  - least-privilege roles/database/schema;
  - HBA include + `pg_hba_file_rules` preflight vóór reload;
  - echte peer-connectivitycheck als tenant Linux-user;
  - cross-database rejectcheck.
- `bin/check-vps-database.php`
  - runtimecheck als tenant Linux-user;
  - PDO connect zonder password;
  - tenant/schema marker;
  - DML in rollbacktransactie;
  - DDL moet geweigerd worden.
- `app/storage/pdo-runtime.php`
  - leest vaste `<tenantroot>/database/database-runtime.json`;
  - weigert symlink/onveilige world-rechten;
  - valideert tenant, DSN, peer-contract en schema v1.
- `app/storage/private-store.php`
  - behoudt legacy expliciete PDO-config/env compatibiliteit;
  - fase-4.5 serverruntime gebruikt secretvrij peer-metadata;
  - fase-4.5 PostgreSQL valideert schema alleen en voert geen lazy DDL uit.

## Schema v1

Schema `vst`:

- `vereniging_schema_meta`
  - vaste `private_store` marker;
  - schema_version=1;
  - tenant_key.
- `vereniging_private_store`
  - tenant_key;
  - collection_key;
  - payload;
  - updated_at;
  - PK `(tenant_key, collection_key)`.

De app-role krijgt geen schema CREATE en geen tabel ownership. Alleen DML op private-store en SELECT op de marker zijn toegestaan.

## Expliciet niet gedaan

- Geen stille JSON→PDO migratie.
- Geen remote PostgreSQL/passwordmodel.
- Geen DB-password in config/env/FPM/runtimebundles.
- Geen fysieke PostgreSQL backup/PITR in deze fase.
- Geen echte root/PostgreSQL mutatie vanuit CI of DEV.

## Acceptatie

Nieuwe regressietest: `tests/phase45-database-provisioning.php`.

De test gebruikt echte bestaande provisioner/deployment/runtime code voor twee PDO-tenants en valideert daarna contract, artifacts, tenantisolatie, secretvrijheid, HBA, least privilege, runtimebinding, tamper-detectie, JSON fail-closed gedrag en statische root/runtime veiligheidsvoorwaarden.
