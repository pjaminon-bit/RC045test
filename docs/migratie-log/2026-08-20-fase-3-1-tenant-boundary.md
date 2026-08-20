# Fase 3.1 — tenant boundary

Datum: 2026-08-20

## Doel

De gedeelde applicatiecode voorbereiden op meerdere verenigingen op één VPS zonder dat configuratie, JSON-data of locks tussen tenants worden gedeeld.

## Wijzigingen

- `app/core/tenant-runtime.php` toegevoegd met kleine, domeinonafhankelijke runtimehelpers.
- `VERENIGING_CONFIG_FILE` kan per vhost/process naar een absoluut server-only configbestand buiten de codebase wijzen.
- Een expliciet maar ongeldig extern configpad faalt gesloten; er wordt dan niet teruggevallen op RC045/defaultconfig.
- `site-config.local.php` blijft voor de huidige losse DEV-installatie volledig ondersteund.
- `opslag.private_root` / `VERENIGING_PRIVATE_ROOT` toegevoegd voor een absolute, tenant-eigen private opslagmap.
- JSON-private-store gebruikt bij een ingestelde private root generieke tenantcollecties onder `collections/*.json`.
- Ontbrekende tenantcollecties vallen bewust **niet** terug op legacy RC045-data.
- JSON-writes blijven atomisch via temp+rename en bewaren de vorige versie tenant-lokaal onder `backups/<collectie>/`.
- Het centrale dataslot verhuist bij een tenant-root naar `<private_root>/.data.lock`; zonder private root blijft de bestaande RC045-locatie intact.
- Rechten voor nieuw aangemaakte tenantmappen/bestanden zijn aangescherpt naar 0750/0640 waar de runtime ze zelf maakt.
- `site-config.local.example.php` documenteert beide inzetvormen.
- Nieuwe CI-test `tests/phase31-tenant-boundary.php` valideert twee fysiek gescheiden JSON-tenants, geen legacy fallback en fail-closed externe config.

## Achterwaartse compatibiliteit

De bestaande RC045/DEV-installatie heeft standaard geen `private_root` en geen `VERENIGING_CONFIG_FILE`. Daardoor blijven de huidige lokale config, JSON-fallbackpaden, backups en het bestaande lockpad actief totdat we de DEV-installatie bewust naar de nieuwe tenantlayout migreren.

## Wat dit nog niet doet

- Er is nog geen automatische tenant-provisioner.
- Publieke uploads/branding-assets zijn nog niet naar tenant-eigen roots verplaatst.
- Het bestaande beheer-backupscherm kent de nieuwe tenant-lokale JSON-backupmap nog niet.
- Domein-naar-tenant routing/vhostconfiguratie wordt nog niet automatisch aangemaakt.
- Authenticatie blijft functioneel zoals nu; verdere tenant-isolatie van gebruikers/sessies wordt in een volgende fase expliciet gecontroleerd.

## Volgende stap

Fase 3.2: een idempotente CLI-provisioner die een tenantmap, server-only config, private opslagstructuur en deployment/vhost-parameters kan genereren zonder de applicatiecode te kopiëren.
