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

- Album verwijderen: eerst `fotoboek.json` succesvol opslaan, daarna pas de fysieke albummap verwijderen. Een mislukte JSON-write kan daardoor geen administratie achterlaten die naar reeds verwijderde bestanden wijst.
- Foto verwijderen: dezelfde volgorde; eerst de nieuwe metadata opslaan, daarna pas de expliciet verwijderde foto-/thumbnailbestanden wissen.
- Nieuwe upload: wanneer afbeeldingsbestanden wel zijn aangemaakt maar de JSON-write mislukt, worden de nieuw aangemaakte bestanden weer opgeruimd.
- Albumverwijdering vereist naast een confirm-dialoog ook het letterlijk intypen van `VERWIJDER`.
- Bestandsnamen uit POST-data worden altijd via `basename()` begrensd tot de albummap.
- PNG/WEBP met transparantie worden bij JPEG-output bewust op wit afgevlakt, in plaats van een onvoorspelbare/zwarte achtergrond te kunnen krijgen.
- Fotoboek-reader meldt nu onderscheid tussen ontbrekende data, ongeldige JSON en een onbekend formaat; een oud top-level album-arrayformaat wordt read-only herkend.

## DEV-data

`data/` en `images/fotoboek/` zijn server-only en staan bewust in `.gitignore`. Een verse DEV-installatie krijgt daardoor geen bestaande productiealbums mee via GitHub Actions.

Voor validatie is een afgeschermde eenmalige hulp toegevoegd: `/beheer/fotoboek-live-naar-dev.php`.
Deze hulp:

- werkt alleen wanneer de huidige installatie fysiek in een map `/dev` staat;
- is alleen toegankelijk voor de hoofdbeheerder;
- leest alleen uit de productie-root (de oudermap van `/dev`);
- kopieert uitsluitend `data/fotoboek.json`, optioneel `data/fotoboek-pagina.json` en `images/fotoboek/` naar DEV;
- overschrijft nooit bestaande DEV-Fotoboekdata;
- vereist de expliciete bevestiging `KOPIEER`;
- wijzigt of verwijdert nooit productiegegevens.

## Bewuste keuze

Video-upload blijft uitgeschakeld, gelijk aan de bestaande situatie. Bestaande video-items blijven wel in het datamodel en de editor ondersteund.

## Nog te valideren op DEV

- LIVE-Fotoboek eenmalig veilig naar DEV seeden.
- Editor opent vanuit het normale beheer-menu.
- Bestaande albums, covers en thumbnails worden correct geladen.
- Alleen metadata opslaan zonder upload.
- Eén kleine testfoto uploaden en daarna weer verwijderen.
- Cover wijzigen.
- Eventueel een tijdelijk testalbum aanmaken en verwijderen om de nieuwe delete-flow te testen.

Status: **wacht op DEV-validatie**.
