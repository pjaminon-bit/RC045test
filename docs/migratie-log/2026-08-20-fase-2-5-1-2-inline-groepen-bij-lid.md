# Fase 2.5.1.2 — Groepsbeheer inline bij Lid bewerken

Datum: 2026-08-20

## Doel
Groepslidmaatschappen hoeven niet meer op een aparte beheerpagina te worden aangepast. Commissies, werkgroepen en groepsrollen zijn direct zichtbaar en wijzigbaar op `Beheer > Leden > Lid bewerken`.

## Wijzigingen
- Nieuwe herbruikbare helper `app/beheer/lid-groepen-inline.php`.
- `beheer/leden.php` verwerkt de actie `groepen_opslaan` en toont de groepseditor op dezelfde lidpagina.
- De groepseditor toont alleen actieve groepen waarvoor de beheerder het juiste type-recht heeft.
- Rollen kunnen per commissie/werkgroep direct bij het lid worden aangepast.
- Toevoegen/verwijderen gebruikt `groepenWerkLidBij()`, zodat andere groepsleden nooit worden gewijzigd.
- Verwijderen uit een groep sluit de historische deelname met de huidige datum.
- Gearchiveerde leden tonen groepsinformatie alleen-lezen.
- De oude route `beheer/lid-groepen.php` is een HTTP 308 compatibiliteitsredirect naar `leden.php?edit=<id>#groepen`.
- De DEV-smoketest verwacht voor die legacyroute nu 308 in plaats van een loginredirect.

## Deploydiscipline
Alle wijzigingen zijn eerst gebouwd op `agent/phase2512-inline-member-groups`. Er vindt geen DEV-deploy plaats vóór een groene PR-validatie en één gecontroleerde merge naar `main`.
