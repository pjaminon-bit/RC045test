# Fase 1 — afronding template-ready

Datum: 2026-08-18

## Status

Fase 1 is afgerond.

## Eindresultaat

De codebase kan nu als basis voor meerdere verenigingen worden gebruikt zonder per vereniging een aparte fork te maken. De volgende onderdelen zijn tenant-/verenigingsconfigureerbaar gemaakt:

- identiteit, naam, slogan, domein, talen en tijdzone;
- logo, favicons, social image, manifest en themakleuren;
- publieke en interne modules via centrale feature flags;
- generieke contentpagina's via een paginaregistry en herbruikbare renderers;
- generiek contentbeheer met bestaand rechtenmodel, CSRF, locking, back-up en logging.

## Tenantoverride

`site-config.php` bevat veilige gedeelde defaults. Een installatie kan daarnaast `site-config.local.php` bevatten. Dit bestand:

- wordt recursief over de defaults gelegd;
- hoeft alleen afwijkende waarden te bevatten;
- staat in `.gitignore`;
- kan per vereniging op de server worden aangelegd zonder de gedeelde codebase te wijzigen.

`site-config.local.example.php` dient als voorbeeldstructuur.

## Validatie

De generieke contenteditor is praktisch getest op de DEV-omgeving voor zowel `ontstaan` als `baanreglement`. Daarbij zijn bevestigd:

1. openen/lezen van bestaande content;
2. opslaan via de generieke editor;
3. correcte publieke weergave;
4. logging;
5. automatische back-up.

## Bewust doorgeschoven naar fase 2

Fase 1 maakt de code functioneel template-ready; niet alle historische code is fysiek opgeschoond. Voor fase 2 blijven onder andere over:

- `beheer.php` en `leden.php` structureel opsplitsen;
- dode legacycode voor oude contentformulieren fysiek verwijderen;
- publieke legacy-templates direct op generieke helpers laten draaien zodat de tijdelijke outputfilter kan verdwijnen;
- resterende RC045-prefixen/namen in interne code neutraliseren;
- tenantconfiguratie later geschikt maken voor VPS-provisioning en database-backed configuratie.

## Architectuurgrens

Vanaf dit punt geldt: nieuwe verenigingen horen geen eigen repository of codefork nodig te hebben. Verenigingsverschillen horen via configuratie, modules, content en later tenantdata te worden opgelost.
