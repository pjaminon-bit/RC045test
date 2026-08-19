# Fase 2 — Back-ups modulariseren

Datum: 2026-08-19

## Uitgevoerd

- Zelfstandige beheerroute toegevoegd: `/beheer/backups.php`.
- Historische Back-ups-tab in `beheer.php` wordt verborgen en de oude `backup_herstellen`-POST-route wordt geblokkeerd.
- Centrale lijst van automatisch geback-upte bestanden staat nu in `beheer/backup-registry.php` voor de nieuwe module.
- De bestaande back-upmap `data-backups/`, bewaartermijn van 90 dagen en maximum van 200 versies per bestand blijven behouden.
- Back-ups blijven server-only en worden niet via de publieke website aangeboden.
- De module toont per databestand de 20 nieuwste snapshots; oudere snapshots blijven volgens de bestaande bewaartermijn op de server staan.

## Herstelbeveiliging

- Herstellen is alleen mogelijk voor de master of een account met het expliciete recht `backups`.
- CSRF-controle blijft verplicht.
- De gekozen back-up moet qua bestandsnaam aantoonbaar bij het gekozen doelbestand horen.
- `basename()` en een aanvullende `realpath()`-controle beperken lezen strikt tot `data-backups/`.
- Het JSON-bestand wordt vóór herstel volledig geparseerd; beschadigde back-ups worden geweigerd.
- De gebruiker moet letterlijk `HERSTEL` typen voor iedere herstelactie.
- Het globale dataslot wordt tijdens het daadwerkelijke herstel gebruikt.
- Vóór overschrijven wordt de huidige versie automatisch opnieuw geback-upt, zodat ook een herstel zelf terug te draaien blijft.
- Gewone JSON-doelbestanden worden via een tijdelijk bestand in dezelfde map en daarna `rename()` vervangen; gebruikersdata blijft via `schrijfGebruikers()` lopen.
- Ieder succesvol herstel wordt in het activiteitenlogboek vastgelegd.

## DEV-validatie — 2026-08-19

Geslaagd:

- Back-ups opent vanuit het normale beheer-menu.
- Bestaande snapshots worden correct per databestand getoond.
- Een onschuldige testwijziging kon worden hersteld naar de vorige snapshot.
- De oorspronkelijke waarde kwam na herstel correct terug.
- Voor herstel wordt opnieuw een snapshot van de huidige versie gemaakt.
- Het logboek bevat na herstel de actie `backup_hersteld`.

Status: **DEV-gevalideerd**.
