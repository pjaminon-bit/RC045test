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

## Nog te valideren op DEV

- Menu-item Logboek opent `/beheer/logboek.php`.
- Bestaande logregels worden correct weergegeven.
- Zoek- en filterfuncties werken.
- CSV-export levert de gefilterde regels.

Status: **wacht op DEV-validatie**.
