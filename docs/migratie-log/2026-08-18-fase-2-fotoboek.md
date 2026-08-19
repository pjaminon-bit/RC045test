# Fase 2 — Fotoboek modulariseren

Datum: 2026-08-18

## Uitgevoerd

- Zelfstandige beheerroute toegevoegd: `/beheer/fotoboek.php`.
- Historische Fotoboek-tab in `beheer.php` wordt verborgen en de oude POST-routes worden geblokkeerd.
- Bestaand datamodel `data/fotoboek.json` blijft behouden.
- Bestaande albumvelden blijven behouden: slug, NL/EN/DE-titel, datum, volgorde, cover, verborgen, beschrijving en photos.
- Bestaande foto-metadata blijft behouden: type, file, width, height, caption, watermerk en hash; bestaande video-items/posters blijven leesbaar en beheerbaar.
- Uploads blijven begrensd op 25 MB per foto en worden naar een web-JPEG (max 1600px) plus thumbnail (max 400px) verwerkt.
- EXIF-oriëntatie, watermerk, coverkeuze, captions, verborgen albums en dubbele-uploadcontrole op SHA-1 inhoud blijven behouden.
- Grote selecties worden client-side in losse verzoeken verwerkt om `post_max_size`/memory-problemen te beperken.
- Paginatekst `data/fotoboek-pagina.json` is in dezelfde editor opgenomen.

## Veiligheids-/betrouwbaarheidsoptimalisaties

- Album verwijderen: eerst `fotoboek.json` succesvol opslaan, daarna pas de fysieke albummap verwijderen.
- Foto verwijderen: dezelfde volgorde; eerst de nieuwe metadata opslaan, daarna pas de expliciet verwijderde foto-/thumbnailbestanden wissen.
- Nieuwe upload: wanneer afbeeldingsbestanden wel zijn aangemaakt maar de JSON-write mislukt, worden de nieuw aangemaakte bestanden weer opgeruimd.
- Albumverwijdering vereist naast een confirm-dialoog ook het letterlijk intypen van `VERWIJDER`.
- Bestandsnamen uit POST-data worden via `basename()` begrensd tot de albummap.
- PNG/WEBP met transparantie worden bij JPEG-output bewust op wit afgevlakt.
- Fotoboek-reader onderscheidt ontbrekende data, ongeldige JSON en onbekend formaat; een oud top-level album-arrayformaat wordt read-only herkend.
- DEV-deployment controleert vooraf alle PHP-bestanden met `php -l`.
- `.htaccess` wordt expliciet apart via SFTP geüpload, omdat de gewone `./*`-glob dotfiles niet meeneemt.

## Publieke album-URL's

Het oude hashmodel `fotoboek.html#album=<slug>` is vervangen door nette routes:

- `/fotoboek/`
- `/fotoboek/<album-slug>/`

Apache herschrijft een albumroute intern naar `fotoboek.php?album=<slug>`. Dezelfde configuratie werkt ook wanneer de installatie onder `/dev` staat. De pagina gebruikt een dynamische basis-URL zodat CSS, scripts, afbeeldingen en JSON-data onder geneste albumroutes correct blijven laden.

Bestaande hashlinks worden alleen nog als achterwaartse compatibiliteit herkend en naar de nieuwe route omgezet; nieuwe navigatie maakt er geen gebruik meer van.

## DEV-data en validatie

`data/` en `images/fotoboek/` zijn server-only en staan bewust in `.gitignore`. Voor de migratietest is de bestaande LIVE-Fotoboekdata eenmalig naar DEV gekopieerd met een tijdelijk, afgeschermd hulpprogramma. Na succesvolle validatie zijn zowel dat hulpprogramma als de tijdelijke diagnosepagina weer uit de repository verwijderd.

Praktisch gevalideerd op DEV op 2026-08-19:

- editor opent vanuit het normale beheer-menu;
- bestaande albums, covers en thumbnails worden correct geladen;
- metadata/beschrijving opslaan behoudt alle bestaande foto-items;
- testfoto uploaden werkt;
- thumbnail wordt correct aangemaakt;
- watermerk wordt correct toegepast;
- cover wijzigen werkt;
- testfoto verwijderen werkt zonder overige foto's te raken;
- nette directe albumroute `/dev/fotoboek/crawlerbaan/` werkt;
- `.htaccess` wordt aantoonbaar door de DEV-deployment meegenomen.

## Bewuste keuze

Video-upload blijft uitgeschakeld, gelijk aan de bestaande situatie. Bestaande video-items blijven wel in het datamodel en de editor ondersteund.

Status: **gevalideerd**.
