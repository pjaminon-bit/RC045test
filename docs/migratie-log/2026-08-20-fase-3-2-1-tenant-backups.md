# Fase 3.2.1 — optie 9: tenant-aware back-ups, retentie en restore

Datum: 20 augustus 2026

## Doel

Back-ups mogen in een gedeelde multi-tenant installatie nooit een tweede cross-tenant datakanaal worden. Een snapshot van tenant A mag niet door tenant B gelezen of hersteld kunnen worden, ook niet wanneer iemand het fysieke snapshotbestand handmatig in B's backupmap plaatst. Restore mag daarnaast nooit naar projectrootpaden schrijven.

## Nieuwe tenant-backuproot

Externe tenants met `opslag.private_root` gebruiken uitsluitend:

```text
<private_root>/backups/tenant/
├── records/
│   └── <backup-key>/
│       └── <timestamp>_<random>.json
└── assets/
    ├── fotoboek/
    │   └── <snapshot>/
    │       ├── manifest.json
    │       └── payload/
    └── sponsors/
        └── <snapshot>/
            ├── manifest.json
            └── payload/
```

Standalone RC045 blijft voorlopig compatibel met de bestaande `data-backups/`-structuur. Nieuwe externe tenants gebruiken die gedeelde legacy-map niet als restorebron.

## Data-envelopes

Iedere tenantsnapshot van JSON/administratieve data bevat minimaal:

- `schema`;
- `tenant_key`;
- `backup_key`;
- `created_at`;
- `data`.

Bij restore worden zowel `tenant_key` als `backup_key` opnieuw gecontroleerd. Een fysiek gekopieerd bestand van tenant A naar de correcte backupmap van tenant B blijft daardoor onbruikbaar. Hetzelfde geldt wanneer een snapshot binnen dezelfde tenant onder een ander onderdeel wordt geplaatst.

## Storage-onafhankelijke restore

Voor externe tenants bevat `beheer/backup-registry.php` geen projectrootpaden meer. De registry beschrijft logische bronnen:

- publieke contentdataset;
- private collectie;
- gebruikers;
- Fotoboek-assets;
- sponsorassets.

Restore loopt via de bijbehorende storage-API. Daardoor gebruikt JSON en PDO hetzelfde backupcontract en kan een toekomstige database-backend zonder projectroot-fallback worden hersteld.

## Automatische vorige-versie snapshots

De tenant-aware public-content writer en private store maken vóór overschrijven een snapshot van de bestaande versie. Dit geldt voor private JSON én PDO. Generieke beheereditors gebruiken dezelfde public-content writer.

Fotoboek- en sponsorassetmutaties maken maximaal één pre-write snapshot per scope per POST-request. De bijbehorende metadata wordt in hetzelfde pre-write moment als aparte tenantgebonden datasnapshot opgeslagen.

Daarnaast kan een beheerder in `Back-ups` per logisch onderdeel handmatig `Nu snapshot maken` kiezen.

## Assets

Fotoboek- en sponsorbestanden worden als volledige scopes gesnapshot. De assetmanifesten bevatten `tenant_key` en `asset_scope`. Restore controleert containment en symlinks en gebruikt staging + atomische directory-rename.

De gekozen restorebron wordt **eerst** volledig naar een veilige stagingmap gekopieerd. Pas daarna wordt de huidige assettoestand als pre-restore snapshot gemaakt. Hierdoor kan retentie de geselecteerde oude snapshot nooit tijdens een lopende restore verwijderen.

## Retentie en diskgrenzen

Standaardwaarden voor externe tenants:

- data: 90 dagen;
- maximaal 200 snapshots per dataonderdeel;
- maximaal 20 assetsnapshots per scope;
- maximaal 2048 MB totaal voor assetsnapshots.

Configuratie kan via `opslag.backups` worden aangepast:

```php
'backups' => [
    'bewaardagen' => 90,
    'max_per_item' => 200,
    'max_asset_snapshots' => 20,
    'max_asset_mb' => 2048,
],
```

Er gelden harde boven- en ondergrenzen in de runtime zodat foutieve configuratie niet tot onbeperkte groei leidt.

## Restorebeveiliging

- capability `system.backups.manage` blijft vereist;
- CSRF blijft vereist;
- beheerder moet exact `HERSTEL` typen;
- restore draait onder het bestaande dataslot;
- data-envelopes moeten bij actieve tenant én onderdeel horen;
- assetsnapshots moeten bij actieve tenant én assetscope horen;
- symlinks in backup- of restorepaden worden geweigerd;
- fysieke paden worden tegen `private_root` gecontroleerd;
- huidige toestand wordt voor restore opnieuw als snapshot bewaard voor zover er bestaande data is;
- restoreacties en handmatige snapshots worden geaudit.

## Testdekking

`tests/phase321-tenant-backups.php` controleert onder andere:

- tenantbinding van data-envelopes;
- backup-key binding;
- cross-tenant kopieeraanval A → B;
- retentie op datasnapshots;
- vorige-versie snapshot bij publieke content;
- vorige-versie snapshot bij private JSON;
- dezelfde backupflow bij PDO/SQLite;
- volledige assetrestore;
- assetsrestore bij retentielimiet van één snapshot;
- assetmanifestbinding A → B;
- logische tenantregistry zonder projectrootpaden;
- symlinkblokkade in de tenantbackupstore.

## Bewuste grens

De oude standalone RC045-backupstructuur wordt in deze stap niet verwijderd. Dat is compatibilitygedrag voor de huidige losse installatie, niet het model voor nieuwe VPS-tenants. Een latere opschoonfase kan deze legacylaag verwijderen nadat RC045 zelf als expliciete tenant is geprovisioneerd.
