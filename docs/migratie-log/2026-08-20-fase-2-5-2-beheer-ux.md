# Fase 2.5.2 — beheer-UX opschoning

Datum: 2026-08-20

## Doel
Het beheer-menu begrijpelijker maken voor verenigingen en clubs zonder bestaande routes, capabilities of dataformats te breken.

## Wijzigingen
- Hoofdmenu toont alleen primaire verenigingsmodules.
- Aanmeldingen, Leden importeren en Ledenlabels zijn secundaire acties onder Leden.
- Groepsrollen zijn secundaire actie onder Commissies en Werkgroepen.
- Operationele taken zijn secundaire actie onder Taken.
- Alle bestaande routes en capabilities blijven bestaan en rechtstreeks bruikbaar.
- Nieuwe beheerroute `beheer/groep-relaties.php` voor many-to-many koppelingen tussen Commissies/Werkgroepen en Taken, Vergaderingen en Evenementen.
- Relaties worden opgeslagen in het bestaande tenant-private groepen-document; groepen-schema is verhoogd naar 2.
- Relaties respecteren zowel het beheerrecht op het groepstype als het beheerrecht op het gekoppelde objectdomein.
- DEV-smoketest uitgebreid met `groep-relaties.php`.
- Nieuwe regressietest `tests/phase252-ux.php`.

## Deploydiscipline
Alle bouw gebeurt op `agent/phase252-beheer-ux`. Pull-request-validatie deployt niet naar DEV. Pas na volledig groene PR-validatie wordt één keer gemerged naar `main`, waarna één DEV-deploy volgt.
