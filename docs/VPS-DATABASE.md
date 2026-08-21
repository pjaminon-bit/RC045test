# VPS database provisioning — fase 4.5

Status: **code/automation en CI-contract gereed; daadwerkelijke PostgreSQL provisioning volgt op de echte VPS.**

Fase 4.5 kiest één canoniek productiemodel voor private PDO-opslag:

- PostgreSQL **16 of nieuwer** op dezelfde Linux VPS;
- PostgreSQL draait **socket-only** met `listen_addresses=''`: geen TCP-listener voor tenantdatabases;
- **één database per tenant**;
- één aparte **NOLOGIN owner-role** per tenant;
- de applicatie-loginrole is exact dezelfde unieke Linux-user als de PHP-FPM pool uit fase 4.1;
- verbinding uitsluitend via Unix socket `/var/run/postgresql`;
- PostgreSQL **peer authentication**: de kernel OS-identiteit bepaalt of de databaseuser mag inloggen;
- er bestaat daarom bewust **geen databasewachtwoord** dat in Git, config.php, environment, deployment.json, runtimebundles of een secretsbestand moet worden opgeslagen.

## Waarom peer authentication

Alle tenant-PHP-processen draaien sinds fase 4.1 onder een unieke system user. PostgreSQL peer authentication kan bij een lokale Unix-socket die kernelidentiteit rechtstreeks controleren. De PostgreSQL app-role gebruikt daarom exact dezelfde naam als de Linux/FPM-user.

Dat levert een sterkere lokale grens op dan gedeelde wachtwoorden:

1. geen reusable databasepassword op disk;
2. geen password in process environment of PHP-FPM config;
3. een proces van tenant A kan zich niet als databaseuser van tenant B voordoen;
4. de HBA-regels staan de eigen database eerst toe en weigeren dezelfde tenantuser daarna expliciet voor alle andere databases;
5. doordat PostgreSQL geen TCP-listener heeft, kan een brede toekomstige `host ... trust`-regel geen tweede netwerkpad naar de passwordloze tenantrollen openen.

## Voorwaarden

Voor een tenant moeten vooraf aanwezig zijn:

1. provisioning met `--driver=pdo`;
2. fase 3.4 admin bootstrap;
3. `deployment.json` uit fase 3.5;
4. fase-4.1 runtimebundle;
5. de fase-4.1 Linux-user/group moet daadwerkelijk op de VPS zijn toegepast voordat `--apply` wordt gebruikt;
6. PostgreSQL moet gecontroleerd met `listen_addresses=''` zijn gestart en `/var/run/postgresql` als Unix-socket aanbieden;
7. de tenant-PHP-FPM pool moet vóór iedere database-`--apply` zijn gestopt. Fase 4.5.1 weigert actieve processen van de tenant-runtimeuser fail-closed.

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

Stop eerst de tenant-PHP-FPM pool en voer daarna uit:

```bash
sudo php bin/apply-vps-database.php \
  --database-plan=/srv/verenigingen/noorderhaven/database/database-plan.json \
  --apply
```

De apply-tool:

1. vereist Linux root;
2. controleert dat de fase-4.1 tenantuser/group werkelijk bestaat;
3. bewijst met `pgrep -u <tenantuser>` dat de tenant-runtime stil staat;
4. vereist PostgreSQL >=16, `listen_addresses=''` en socket `/var/run/postgresql`;
5. controleert vóór mutaties of deterministische role-/databasenamen niet door een ander object zijn bezet;
6. markeert platformobjecten expliciet met de tenant-key;
7. maakt de NOLOGIN owner-role en maakt/normaliseert de app-role eerst als **NOLOGIN**, zonder password en zonder superuser/CREATEDB/CREATEROLE/replication/BYPASSRLS;
8. weigert gevaarlijke privilege-, membership- of password-drift op een bestaande tenantrole;
9. installeert en valideert **vóór LOGIN** de tenant-HBA allow-own/reject-other in `/etc/verenigingsplatform/postgresql/pg_hba.d`;
10. zet de platform `include_dir` vóór de niet-platformregels in de actieve `pg_hba.conf`, valideert die met `pg_hba_file_rules` en reloadt PostgreSQL alleen na een geldige preflight;
11. maakt één database waarvan alleen de owner-role eigenaar is;
12. revoke't `PUBLIC` database- en schemarechten, normaliseert oude app-grants en past schema/migratie v1 toe als PostgreSQL-admin;
13. bewijst exact dat de app-role alleen CONNECT, schema-USAGE, SELECT op de schemamarker en SELECT/INSERT/UPDATE/DELETE op de private-store tabel heeft;
14. schakelt de app-role pas daarna als laatste databasebeveiligingsstap naar **LOGIN**;
15. test een echte peer-login als de tenant Linux-user;
16. bewijst dat dezelfde tenantuser niet naar de `postgres` database kan uitwijken;
17. zet bij iedere mislukte apply een reeds tenantgebonden app-role zo mogelijk terug naar **NOLOGIN**;
18. laat een reeds succesvol geladen beschermende tenant-HBA bij een latere provisioningfout bewust staan, zodat een fout nooit een LOGIN-role zonder cross-database reject kan achterlaten.

Afwijkende bestaande PostgreSQL-objecten worden niet met `--force` overgenomen. Een collision zonder correcte tenantmarker vereist handmatige inspectie.

### Fase 4.5.1 — security heraudit

De heraudit van 21-08-2026 sloot een provisioningrace in de oorspronkelijke 4.5-volgorde. Een PostgreSQL LOGIN-role mocht niet al bestaan voordat de tenant-HBA reject aantoonbaar actief was. De vaste volgorde is daarom nu:

**tenant-runtime stil → app-role NOLOGIN → HBA allow-own/reject-other geladen → database/schema/least-privilege → app-role LOGIN → peer/cross-database check**.

Bij een fout wordt de app-role fail-closed weer NOLOGIN gezet. Een reeds geladen tenant-HBA wordt niet teruggedraaid nadat databaseobjecten voor de tenant bestaan.

## HBA-contract

Per tenant wordt exact dit patroon gebruikt:

```text
local <eigen-database> <eigen-linux-db-user> peer
local all              <eigen-linux-db-user> reject
```

Alle bestanden in de gecontroleerde platform-include staan vóór bestaande niet-platform HBA-regels. Daardoor kan een latere brede `local all all ...` regel de tenantgrens niet omzeilen. De socket-only serverinstelling voorkomt daarnaast dat TCP/`host`-regels als alternatief authenticatiepad dienen.

PostgreSQL verwerkt HBA-regels op volgorde en gebruikt de eerste passende regel. Daarom staat de specifieke eigen-database allow vóór de expliciete reject voor alle overige databases van dezelfde tenantuser.

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