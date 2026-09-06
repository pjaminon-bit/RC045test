# Private-store migratie: JSON → PostgreSQL/PDO

Deze handleiding beschrijft de gecontroleerde migratie van een bestaande VPS-tenant waarvan de private store nog als JSON is geprovisioneerd naar de PostgreSQL/PDO-store.

De migratie verandert bewust **niet** de persistente tenantconfiguratie of `tenant.json` van `json` naar `pdo`. De succesvolle cutover wordt vastgelegd in een root-owned runtime-state onder de tenantroot. Daardoor blijft een mislukte migratie fail-closed op JSON en is een gecontroleerde rollback mogelijk zonder configuratiebestanden terug te schrijven.

## Randvoorwaarden

- de tenant is bestaand en als `private_driver=json` geprovisioneerd;
- de huidige release bevat de Phase-B-migratiecode;
- PostgreSQL en de vereiste PHP PDO-driver zijn beschikbaar;
- de tenant heeft een geldige, root-owned deployment/runtime-context;
- de PostgreSQL migration-target is vooraf voorbereid via het bestaande database-provisioningcontract;
- de storage-cutover wordt uitsluitend gestart via de trusted, root-owned host-engine met actie `storage-migrate`;
- voer de migratie eerst volledig uit op de VPS-testtenant en beoordeel het bewijs vóór productiegebruik.

## Wat wel en niet wordt gemigreerd

De migrator verwerkt uitsluitend collecties die door de private store worden beheerd. Per collectie worden de JSON-records canoniek geïnventariseerd en naar `vst.vereniging_private_store` geschreven.

Niet onderdeel van deze migratie zijn onder meer:

- publieke assets;
- sessiebestanden;
- releasebestanden;
- deploymentmetadata buiten het private-storecontract;
- overige losse bestanden die niet via `privateStore*()` worden beheerd.

## 1. PostgreSQL migration-target voorbereiden

Maak eerst een databaseplan voor de bestaande JSON-tenant, maar expliciet als migration-target:

```bash
php bin/prepare-vps-database.php \
  --runtime-plan=/pad/naar/runtime-plan.json \
  --migration-target
```

`--migration-target` staat databasevoorbereiding toe voor een JSON-geprovisioneerde tenant. Dit wijzigt de actieve private-store-driver niet.

Controleer het gegenereerde plan en voer het daarna uit via de bestaande trusted database-applyprocedure. Voor `bin/apply-vps-database.php` geldt het bestaande veiligheidscontract: de tenant-PHP-FPM-unit moet tijdens de database-apply gestopt zijn en de apply vereist root.

Na database-apply kan de applicatie weer worden gestart. Zolang er nog geen geldige `private-store-runtime.json` bestaat, blijft de effectieve private-store-driver JSON.

## 2. Preflight / inventory

Laat via de trusted host-engine de storageactie uitvoeren met:

- `action=storage-migrate`
- `mode=check`
- `tenant_root=/srv/verenigingen/<tenant>`

`check` is niet-mutatief. Het valideert de tenant- en databasecontext en rapporteert onder andere:

- configured driver;
- effective driver;
- private-storecollecties;
- recordaantallen;
- canonieke hashes van de JSON-bron.

Voor een nog niet gemigreerde tenant moet gelden:

- `configured_driver = json`;
- `effective_driver = json`.

Stop bij iedere preflightfout. Corrigeer geen runtime-state, proof of marker handmatig.

## 3. Cutover uitvoeren

Start via dezelfde trusted host-engine:

- `action=storage-migrate`
- `mode=apply`
- dezelfde canonieke `tenant_root`.

De coordinator voert vervolgens als één gecontroleerde procedure uit:

1. valideert tenantroot, deploymentcontext, JSON-configuratie, tenantmanifest en PostgreSQL migration-target;
2. maakt de root-owned storage-runtimecontext en migratielock fail-closed gereed;
3. activeert een root-owned migratiemarker waardoor nieuwe normale private-storewrites worden geweigerd;
4. laat reeds lopende writers afronden en neemt daarna de exclusieve migratielock;
5. maakt vóór import byte-identieke snapshots van de JSON-bron;
6. importeert alle private-storecollecties transactioneel naar PostgreSQL;
7. leest bron, snapshots en PostgreSQL-target opnieuw en vergelijkt collectiekeys, recordaantallen en canonieke payloadhashes;
8. schrijft een machineleesbaar migratieproof met bron-, snapshot- en targethashes;
9. voert een tweede onafhankelijke verify-run tegen dat proof uit;
10. activeert pas daarna atomisch de root-owned runtime-state met `effective_driver=pdo`;
11. verwijdert als laatste de migratiemarker.

Een mislukking vóór stap 10 laat JSON actief. Als de fout ná een reeds gecommitteerde import optreedt, kan de inactieve PostgreSQL-target wel data bevatten; zonder geldige runtime-state wordt die target niet als actieve private store gebruikt. Een volgende apply valideert en vult de target opnieuw via het exacte migratiecontract. De coordinator verwijdert de tijdelijke marker in zijn cleanup-pad; snapshots en bewijsmateriaal blijven beschikbaar voor onderzoek.

## 4. Na de cutover verifiëren

Voer opnieuw `storage-migrate` met `mode=check` uit.

Na een geslaagde cutover moet gelden:

- `configured_driver = json`;
- `effective_driver = pdo`.

Controleer daarnaast minimaal:

- normale applicatiesmoke;
- authenticated browser-E2E voor private functionaliteit;
- datalezen uit meerdere private-storecollecties;
- een gecontroleerde write en read-back;
- applicatie- en PHP-FPM-logs op nieuwe fouten;
- dat het migratieproof en de runtime-state intact aanwezig zijn.

De JSON-bron wordt bij de cutover niet verwijderd. Zij vormt samen met het proof het rollbackanker en mag daarom niet handmatig worden aangepast of opgeschoond zolang rollback nog nodig kan zijn.

## 5. Rollback

Rollback is bewust alleen toegestaan zolang de actuele JSON-bron én de actuele PostgreSQL-target nog exact overeenkomen met het actieve migratieproof. Hiermee wordt voorkomen dat na nieuwe PDO-writes ongemerkt naar een verouderde JSON-state wordt teruggeschakeld.

Start via de trusted host-engine:

- `action=storage-migrate`
- `mode=rollback`
- dezelfde `tenant_root`.

Normaal hoeft geen proof-pad te worden opgegeven: de coordinator gebruikt het proof waarvan de SHA-256-hash in de root-owned actieve runtime-state is vastgelegd. Als een proof expliciet wordt meegegeven, moet dit exact het proof uit die runtime-state zijn en binnen de toegestane tenant-private migratiebackupstructuur liggen.

De rollbackprocedure:

1. activeert dezelfde write barrier;
2. valideert runtime-state en proof;
3. herberekent bron-, snapshot- en targethashes;
4. weigert rollback zodra bron of target sinds de cutover is gewijzigd;
5. archiveert bij een veilige rollback de actieve runtime-state atomisch;
6. verwijdert de marker;
7. laat de effectieve driver daardoor terugvallen naar de geconfigureerde JSON-driver.

**Praktisch gevolg:** voer een rollbacktest direct na de testcutover uit, vóór nieuwe functionele PDO-writes. Zodra na de cutover legitieme PDO-writes zijn uitgevoerd, is eenvoudige rollback naar de oude JSON-state terecht niet meer veilig en wordt die geweigerd.

Voer na een rollback opnieuw `mode=check` en de normale applicatiesmoke uit. Verwacht dan `configured_driver=json` en `effective_driver=json`.

## Idempotentie en fail-closed gedrag

- `check` mag herhaald worden;
- een tweede `apply` wordt geweigerd zodra een actieve runtime-state bestaat;
- import gebruikt een exact targetcontract en de volledige target wordt na import opnieuw geverifieerd;
- een ongeldige, gewijzigde, verkeerd geownede of te ruim toegankelijke marker/runtime-state wordt geweigerd;
- een ontbrekend of gewijzigd proof wordt geweigerd;
- een mismatch tussen JSON, snapshot en PostgreSQL verhindert cutover of rollback;
- normale writes worden tijdens het migratiewindow geblokkeerd in plaats van naar een onzekere store te schrijven.

## Bewijs bewaren

Bewaar voor de audit minimaal:

- de `check`-output vóór migratie;
- de succesvolle `apply`-output;
- het proof-pad, `proof_sha256` en `target_aggregate_sha256`;
- de `check`-output na migratie;
- relevante deploy/smoke/E2E-runlinks;
- bij rollback: rollback-output en het pad van de gearchiveerde runtime-state.

Leg deze gegevens na VPS-testacceptatie vast in issue #211 en de centrale audittracker #138.
