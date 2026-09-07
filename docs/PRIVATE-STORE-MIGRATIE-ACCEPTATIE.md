# #211 VPS-testacceptatie — JSON → PostgreSQL/PDO

Deze procedure is uitsluitend bedoeld om de echte Phase-B-migratie één keer gecontroleerd op de VPS-testhost te bewijzen. De normale migratieprocedure voor een bestaande vereniging staat in `PRIVATE-STORE-MIGRATIE.md`.

## Veiligheidsmodel

`bin/accept-phase211-json-pdo.php` is geen algemene provisioning- of migratie-CLI. De runner:

- accepteert uitsluitend exact `--run`;
- gebruikt één vaste wegwerptenant `phase211acceptance`;
- accepteert geen operatorgekozen tenant, pad, database of SQL;
- mag uitsluitend als root vanuit een geïnstalleerde immutable host-engine draaien;
- herverifieert het host-engine manifest en eist dat host-engine en actieve release exact dezelfde commit zijn;
- weigert vóór iedere mutatie als de vaste tenantroot, Linux-identiteit, PostgreSQL-objecten of tenant-HBA al bestaan;
- maakt de fixture expliciet met `private_driver=json`;
- gebruikt voor runtimeapply een tijdelijke FPM-poolmap die niet in de actieve PHP-FPM-config wordt geladen;
- bereidt PostgreSQL uitsluitend met `--migration-target` voor;
- bewaart een root-only evidencebestand onder `/var/lib/verenigingsplatform/phase211-acceptance/`;
- verwijdert geen vooraf bestaande objecten.

## Bewijsvolgorde

De runner voert op de echte VPS uit:

1. JSON-fixture provisionen en drie deterministische private-storecollecties vastleggen;
2. deployment/runtime voorbereiden en een geïsoleerde tijdelijke Linux-runtimeidentity toepassen;
3. PostgreSQL migration-target voorbereiden en toepassen;
4. `storage-migrate check` met verwacht `configured=json` en `effective=json`;
5. bewust een afwijkende PDO-target injecteren;
6. een echte `apply` laten falen en bewijzen dat JSON actief en inhoudelijk identiek blijft en de migratiemarker is opgeruimd;
7. de afwijkende target verwijderen;
8. succesvolle `apply` naar PDO;
9. read-only applicatieprobe via `check-release-tenant.php`;
10. veilige `rollback` vóór functionele PDO-writes en bewijs dat JSON weer exact actief is;
11. een tweede, finale `apply` naar PDO;
12. opnieuw de read-only applicatieprobe;
13. finale proof-SHA en target aggregate vastleggen;
14. fixture-database, HBA, Linux-user/group, tijdelijke FPM-config en tenantboom gecontroleerd opruimen.

Cleanup verwijdert PostgreSQL-objecten alleen wanneer hun deterministische naam én tenantmarker exact bij `phase211acceptance` horen. De tenant-HBA wordt alleen verwijderd wanneer de inhoud exact overeenkomt met het gegenereerde migration-targetplan. Iedere afwijking faalt gesloten en blijft voor onderzoek staan.

## Uitvoeren

Voer de runner pas uit nadat zijn commit normaal is gemerged, gedeployed en als root-owned host-engine is geïnstalleerd. Gebruik het script uit de host-engine, nooit uit `/srv/verenigingsplatform/current` of een Git-checkout:

```bash
sudo /usr/bin/php8.5 \
  /usr/local/libexec/verenigingsplatform/host-engine/<exact-main-sha>/bin/accept-phase211-json-pdo.php \
  --run
```

Succes eindigt met:

```text
PHASE211 VPS MIGRATION ACCEPTANCE OK
```

gevolgd door één JSON-regel met minimaal:

- `engine_commit`;
- `release_commit`;
- `proof_sha256`;
- `target_aggregate_sha256`;
- `evidence_path`;
- `cleanup=ok`.

Bij iedere fout: voer de runner niet opnieuw uit voordat de gemelde cleanup- of bindingafwijking is onderzocht. Het evidencebestand blijft root-only beschikbaar voor de handover in #211 en #210.
