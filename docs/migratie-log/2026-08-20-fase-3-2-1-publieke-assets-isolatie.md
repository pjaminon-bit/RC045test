# Fase 3.2.1 — optie 8: publieke assets-isolatie

Datum: 20 augustus 2026

## Doel

Publieke bestanden die via beheer worden geüpload mogen in een gedeelde multi-tenant installatie nooit in één gezamenlijke `images/`-map terechtkomen. Een tenant mag ook bij ontbrekende eigen bestanden niet terugvallen op bestanden van RC045 of een andere vereniging.

## Nieuwe opslaggrens

Voor een extern geconfigureerde tenant met `opslag.private_root` worden publieke uploads buiten de documentroot opgeslagen onder:

```text
<private_root>/public-assets/
├── fotoboek/
│   └── <album>/
│       ├── <bestand>
│       └── thumbs/
│           └── <bestand>
└── sponsors/
    └── <logo>
```

Publieke JSON-metadata voor Media en Fotoboek valt vanaf deze stap eveneens onder de bestaande tenantcontentstore:

```text
<private_root>/public-content/media.json
<private_root>/public-content/media-pagina.json
<private_root>/public-content/fotoboek.json
<private_root>/public-content/fotoboek-pagina.json
```

De standalone RC045-installatie blijft compatibel met de bestaande `data/`, `images/fotoboek/` en `images/sponsors/` paden.

## Publiek serveren

De bestaande publieke URL's blijven behouden:

```text
/images/sponsors/<bestand>
/images/fotoboek/<album>/<bestand>
/images/fotoboek/<album>/thumbs/<bestand>
```

Apache routeert deze URL's naar `public-asset.php`. Dit endpoint:

- accepteert alleen `GET` en `HEAD`;
- kent uitsluitend de scopes `sponsors` en `fotoboek`;
- valideert albumslug, bestandsnaam en extensie strikt;
- weigert `.`/`..`, backslashes, traversal, PHP, HTML en SVG;
- resolveert bestanden met `realpath()` en controleert containment;
- weigert symlinks in het te serveren pad;
- verstuurt een vaste MIME op basis van de whitelist en `nosniff`;
- ondersteunt ETag/304;
- ondersteunt één HTTP byte-range voor MP4-video's.

Een ontbrekend bestand bij tenant B geeft 404 en veroorzaakt nooit fallback naar tenant A of de legacy RC045-map.

## Upload- en deletehardening

Fotoboek:

- JPG/PNG/WEBP blijft server-side opnieuw als JPEG gerenderd;
- maximaal 60 megapixel en maximaal 25 MB upload;
- `is_uploaded_file()` is verplicht vóór verwerking;
- tenantmappen krijgen `0750`, bestanden `0640`;
- recursief verwijderen volgt geen symlinks;
- bestanden worden pas verwijderd nadat metadata succesvol is opgeslagen;
- een externe tenant gebruikt niet het gedeelde `rc045-logo.png` als watermerk. Voorlopig wordt alleen de eigen sitehost als tekstwatermerk gebruikt.

Sponsors:

- maximaal 1 MB;
- alleen PNG/JPG/WEBP na `getimagesize()`;
- maximaal 20 megapixel / 8000 px per as;
- `is_uploaded_file()` verplicht;
- symlinkdoelen worden geweigerd;
- tenantbestanden krijgen `0640`.

## Media

`media.json` en `media-pagina.json` zijn tenant-aware gemaakt. De historische RC045-media-items blijven alleen als compatibility-fallback bestaan in standalone RC045. Een nieuwe externe tenant zonder eigen media krijgt dus een lege lijst en erft geen RC045-persberichten.

## Bewuste grenzen

Deze stap maakt nog geen tenant-aware backup/restore van publieke uploads. Er wordt juist geen kopie naar de oude gedeelde `data-backups`-structuur gemaakt. Backupregistratie, retentie en herstel volgen in optie 9.

Ook tenant-specifieke branding/defaults volgen later. Daarom gebruikt een externe tenant in deze stap bewust geen RC045-logo als fotowatermerk.

## Testdekking

`tests/phase321-public-assets-isolation.php` controleert onder meer:

- fysiek gescheiden assetroots voor tenant A en B;
- geen cross-tenant fallback;
- publieke gateway voor eigen bestanden;
- 404 voor ontbrekende of niet-whitelisted bestanden;
- MP4 byte-range gedrag;
- traversal- en symlinkblokkade;
- symlink-veilige recursieve albumverwijdering;
- fail-closed gedrag zonder `private_root`;
- tenant-aware Media/Fotoboek-metadata;
- broncontroles op Fotoboek-, Sponsor- en Mediabeheer.
