# Fase 2 — Logboek modulariseren

Datum: 2026-08-19

## Uitgevoerd

- Zelfstandige beheerroute toegevoegd: `/beheer/logboek.php`.
- Historische Logboek-tab in `beheer.php` wordt verborgen en het menu wijst naar de zelfstandige module.
- Bestaand server-only bronbestand `beheer-log.json` blijft ongewijzigd in gebruik.
- Bestaande autorisatie blijft gekoppeld aan het beheerrecht `log`; master heeft altijd toegang.
- De module is bewust alleen-lezen: geen wis-, wijzig- of resetactie toegevoegd.
- Regels worden nieuwste eerst getoond.
- Filtermogelijkheden toegevoegd voor vrije zoektekst, gebruiker, actie en periode.
- Paginering toegevoegd met 50 regels per pagina.
- CSV-export toegevoegd; de export respecteert de actieve filters.

## Bewuste keuze

Het logboek blijft auditdata. De module biedt daarom geen mogelijkheid om regels vanuit de webinterface te verwijderen of te wijzigen. De bestaande bewaartermijn van maximaal 90 dagen blijft geregeld door `schrijfLog()` in `auth.php`.

## DEV-validatie

Op 2026-08-19 gecontroleerd in de draaiende DEV-omgeving:

- Menu-item Logboek opent de nieuwe zelfstandige module.
- Bestaande logregels worden correct weergegeven.
- Zoeken en filteren werken.
- Filteren op actie werkt.
- CSV-export respecteert de actieve filters en levert de verwachte regels.

Status: **DEV-gevalideerd**.
