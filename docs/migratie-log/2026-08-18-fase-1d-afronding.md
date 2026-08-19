# Fase 1D — afronding na DEV-validatie

Datum: 2026-08-18

## Praktijktest

De generieke contenteditor is op de DEV-omgeving getest voor zowel `ontstaan` als `baanreglement`.

Bevestigd werkend:

- openen/lezen van bestaande content;
- opslaan via `content-beheer.php`;
- directe zichtbaarheid van de wijziging op de publieke contentpagina;
- registratie in het beheerlogboek;
- automatische databack-up.

Na iedere testwijziging is de tijdelijke testtekst weer verwijderd.

## Runtime-migratie afgerond

Na de succesvolle test zijn de historische beheer-ingangen voor `ontstaan` en `baanreglement` buiten gebruik gesteld:

- de oude beheertabs worden niet meer weergegeven;
- POST met `formulier=ontstaan` of `formulier=baanreglement` wordt vóór de oude `beheer.php`-opslaglogica geneutraliseerd;
- een poging via zo'n oude route wordt als `legacy_content_geblokkeerd` gelogd;
- de generieke editor is daarmee de enige actieve beheerroute voor deze contentpagina's.

## Waarom de oude PHP-code nog fysiek aanwezig is

`beheer.php` is een zeer groot monolithisch bestand. Het volledig herschrijven van dat bestand alleen om twee inmiddels onbereikbare blokken fysiek te verwijderen zou in deze fase een onnodig regressierisico opleveren.

Daarom is gekozen voor het veiliger migratieprincipe: eerst de nieuwe route testen, daarna de oude route functioneel afsluiten. De fysieke verwijdering van de dode code wordt meegenomen in fase 2, wanneer `beheer.php` toch structureel wordt opgesplitst in kleinere modules.

## Eindstatus 1D

Fase 1D is functioneel afgerond. Er zijn nu:

- een centrale contentpagina-registry;
- generieke paginatypen `verhaal` en `artikelen`;
- dunne publieke routes;
- een generieke beheereditor;
- gedeelde rechten, locking, back-up en logging;
- geen actieve pagina-specifieke beheerroute meer voor de twee gemigreerde voorbeeldpagina's.
