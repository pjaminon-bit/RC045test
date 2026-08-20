# Fase 3.2.1 — PDO tenant-isolatie

Datum: 2026-08-20

## Scope

Alleen auditbevinding 1 uit fase 3.2.1: voorkomen dat een extern geconfigureerde PDO-tenant bij een ontbrekende collectie terugvalt op legacy JSON/PHP-data uit de gedeelde RC045-projectroot.

## Risico vóór deze wijziging

`privateStoreLees()` gebruikte bij PDO altijd de meegegeven legacy JSON-lezer wanneer voor `(tenant_key, collection_key)` nog geen rij bestond. Voor een nieuwe externe tenant kon dat betekenen dat een lege PDO-collectie alsnog bestaande RC045-data uit een legacy bestand retourneerde.

Dit is een cross-tenant confidentiality-risico en daarom als kritieke tenant-boundary bug behandeld.

## Wijziging

- `privateStoreLegacyFallbackToegestaan()` toegevoegd.
- Legacy fallback blijft alleen toegestaan voor de bestaande standalone/migratievorm zonder `VERENIGING_CONFIG_FILE` en zonder `private_root`.
- Zodra een externe tenantconfig actief is, levert een ontbrekende PDO-collectie `[]` op.
- Ook een installatie met expliciete `private_root` mag niet terugvallen op projectrootdata.
- De bestaande PDO-write-, tenant_key- en transactielogica is niet gewijzigd.

## Bewuste compatibiliteitskeuze

De fallback is niet wereldwijd verwijderd. `tests/private-store-pdo-sqlite.php` blijft de legacy standalone fallback testen, zodat RC045 tijdens de nog lopende migratie niet onnodig wordt gebroken.

Voor externe tenants is impliciete migratie via fallback juist verboden. Eventuele datamigratie naar PDO moet later expliciet en tenantgericht gebeuren.

## Nieuwe regressietest

`tests/phase321-pdo-isolation.php` gebruikt één gedeelde SQLite-database met twee externe tenantconfigs:

1. tenant A schrijft herkenbare canary-data;
2. tenant B leest dezelfde collectie terwijl voor B nog geen rij bestaat;
3. verwacht resultaat voor B is exact een lege array;
4. een fallback-callback schrijft een marker wanneer hij ooit wordt aangeroepen; de test bewijst dat deze marker niet ontstaat;
5. een RC045-canary uit de fallback mag niet in de output voorkomen;
6. na een eigen write van tenant B blijven A en B ieder hun eigen waarde lezen.

De test draait voortaan in de normale CI-gate na controle op `pdo_sqlite`.

## Niet in deze stap

Auth, sessies, publieke content, uploads, backups, productie fail-closed config, tenant-key-validatie en provisioner-path-hardening vallen buiten deze wijziging en worden afzonderlijk afgehandeld. Dit voorkomt dat één securityfix onnodig veel systeemgedrag tegelijk verandert.
