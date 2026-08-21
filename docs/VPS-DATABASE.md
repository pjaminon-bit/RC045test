# VPS database provisioning — fase 4.5

Status: **code/automation en CI-contract gereed; daadwerkelijke PostgreSQL provisioning volgt op de echte VPS.**

Fase 4.5 kiest één canoniek productiemodel voor private PDO-opslag:

- PostgreSQL **16 of nieuwer** op dezelfde Linux VPS;
- **één database per tenant**;
- één aparte **NOLOGIN owner-role** per tenant;
- de applicatie-loginrole is exact dezelfde unieke Linux-user als de PHP-FPM pool uit fase 4.1;
- verbinding uitsluitend via Unix socket `/var/run/postgresql`;
- PostgreSQL **peer authentication**: de kernel OS-identiteit bepaalt of de databaseuser mag inloggen;
- er bestaat daarom bewust **geen databasewachtwoord** dat in Git, config.php, environment, deployment.json, runtimebundles of een secretsbestand moet worden opgeslagen.

## Waarom peer authentication

Alle tenant-PHP-processen draaien sinds fase 4.1 onder een unieke system user. PostgreSQL peer authentication kan bij een lokale Unix-socket die kernel-identiteit rechtstreeks controleren. De PostgreSQL app-role gebruikt daarom exact dezelfde naam als de Linux/FPM-user.

Dat levert een sterkere lokale grens op dan gedeelde wachtwoorden:

1. geen reusable databasepassword op disk;
2. geen password in process environment of PHP-FPM config;
3. een proces van tenant A kan zich niet als databaseuser van tenant B voordoen;
4. de HBA-regels staan de eigen database eerst toe en weigeren dezelfde tenantuser daarna expliciet voor alle andere databases.

## Voorwaarden

Voor een tenant moeten vooraf aanwezig zijn:

1. provisioning met `--driver=pdo`;
2. fase 3.4 admin bootstrap;
3. `deployment.json` uit fase 3.5;
4. fase-4.1 runtimebundle;
5. de fase-4.1 Linux-user/group moet daadwerkelijk op de VPS zijn toegepast voordat `--apply` wordt gebruikt.

Fase 4.5 schakelt een bestaande JSON-tenant **niet automatisch** om. Een datamigratie is een afzonderlijke, expliciete operatie en mag niet verstopt zitten in infrastructuurprovisioning.

## Bundle genereren

```bash
php bin/prepare-vps-database.php \
  --runtime-plan=/srv/verenigingen/noorderhaven/runtime/runtime-plan.json
```

Dry run:

```bash
php bin/prepare-vps-database.php \
  --runtime-plan=/srv/verenigingen/noorderhaven/runtime/runtime-plan.json \
  --dry-run
```

De vaste outputmap is:

```text
/srv/verenigingen/<tenant>/database/
├── database-plan.json
├── database-runtime.json
├── 001-private-store.sql
└── 100-vp-<tenant>-peer.conf
```

`database-runtime.json` bevat alleen niet-geheime verbindingsmetadata, bijvoorbeeld database, PostgreSQL-role, Unix-socket en schemaversie. Er staat geen password, token of andere secret in.

## Root-vrije bundlecheck

```bash
php bin/apply-vps-database.php \
  --database-plan=/srv/verenigingen/noorderhaven/database/database-plan.json \
  --check
```

Deze stap controleert uitsluitend planbinding en hashes en wijzigt PostgreSQL of `/etc` niet.

## Op de echte VPS toepassen

```bash
sudo php bin/apply-vps-database.php \
  --database-plan=/srv/verenigingen/noorderhaven/database/database-plan.json \
  --apply
```

De apply-tool:

1. vereist Linux root;
2. controleert dat de fase-4.1 tenantuser/group werkelijk bestaat;
3. vereist PostgreSQL >=16 en socket `/var/run/postgresql`;
4. controleert vóór mutaties of deterministische role-/databasenamen niet door een ander object zijn bezet;
5. markeert platformobjecten expliciet met de tenant-key;
6. maakt een NOLOGIN owner-role;
7. maakt de app-role als LOGIN zonder password en zonder superuser/CREATEDB/CREATEROLE/replication/BYPASSRLS;
8. maakt één database waarvan alleen de owner-role eigenaar is;
9. revoke't `PUBLIC` database- en schemarechten;
10. past schema/migratie v1 toe als PostgreSQL-admin;
11. geeft de app-role alleen CONNECT, schema-USAGE, SELECT op de schemamarker en SELECT/INSERT/UPDATE/DELETE op de private-store tabel;
12. installeert de tenant-HBA-regels in `/etc/verenigingsplatform/postgresql/pg_hba.d`;
13. zet de platform `include_dir` vóór de generieke regels in de actieve `pg_hba.conf`;
14. valideert de actuele HBA-bestanden via `pg_hba_file_rules` vóór reload;
15. reloadt PostgreSQL alleen na geldige HBA-config;
16. test een echte peer-login als de tenant Linux-user;
17. bewijst dat dezelfde tenantuser niet naar de `postgres` database kan uitwijken.

Afwijkende bestaande PostgreSQL-objecten worden niet met `--force` overgenomen. Een collision zonder correcte tenantmarker vereist handmatige inspectie.

## HBA-contract

Per tenant wordt exact dit patroon gebruikt:

```text
local <eigen-database> <eigen-linux-db-user> peer
local all              <eigen-linux-db-user> reject
```

De platform-include staat vóór generieke bestaande HBA-regels. Daardoor kan een latere brede `local all all ...` regel de tenantgrens niet omzeilen.

## Schema v1

Schema `vst` bevat:

- `vst.vereniging_schema_meta`
  - component;
  - schema_version;
  - tenant_key;
  - applied_at.
- `vst.vereniging_private_store`
  - tenant_key;
  - collection_key;
  - payload;
  - updated_at;
  - primaire sleutel `(tenant_key, collection_key)`.

Hoewel iedere tenant al een eigen database heeft, blijft `tenant_key` ook in de tabel en schemamarker aanwezig als defense-in-depth. Een gekopieerde/verkeerd gebonden databaseverbinding faalt daardoor in de PHP-runtime gesloten.

De applicatie maakt bij de fase-4.5 PostgreSQL-runtime **geen tabellen of schema's tijdens een HTTP-request**. DDL en toekomstige migraties horen uitsluitend bij de gecontroleerde provisioning/migratielaag.

## Runtimecheck

Na succesvolle apply:

```bash
sudo -u <tenant-linux-user> php bin/check-vps-database.php \
  --database-plan=/srv/verenigingen/noorderhaven/database/database-plan.json
```

Deze check:

- verbindt zonder password via PDO PostgreSQL;
- verifieert `current_database()` en `current_user`;
- verifieert tenantmarker + schemaversie;
- test SELECT/INSERT/UPDATE/DELETE binnen een transactie die wordt teruggerold;
- probeert DDL en eist dat PostgreSQL dat weigert.

## Niet onderdeel van 4.5

- automatische migratie van bestaande JSON-data naar PostgreSQL;
- remote/managed PostgreSQL met password/certificaat-auth;
- fysieke PostgreSQL backup/restore, PITR en monitoring;
- database lifecycle bij tenant delete/export.

Die onderwerpen worden alleen toegevoegd als afzonderlijke, expliciete platformstappen; 4.5 introduceert geen verborgen destructieve migraties.
