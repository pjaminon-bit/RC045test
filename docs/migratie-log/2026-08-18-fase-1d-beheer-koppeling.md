# Fase 1D — koppeling generieke editor aan beheer

Datum: 2026-08-18

## Uitgevoerd

- De generieke `content-beheer.php`-editor is vanuit het bestaande `beheer.php` bereikbaar gemaakt.
- `paneel-hulp.php` injecteert op beheerpagina's een compact blok **Contentpagina's** met links naar alle geregistreerde pagina's uit `pagina-definities.php`.
- De snelkoppelingen respecteren de bestaande beheerrechten zodra `$toegestaneTabs` beschikbaar is; mastertoegang blijft werken.
- `ontstaan` en `baanreglement` zijn daarmee vanuit het bestaande beheer rechtstreeks te openen in de nieuwe generieke editor.
- De oude pagina-specifieke tabs blijven tijdelijk bestaan als fallback. Dit voorkomt dat een fout in de nieuwe editor meteen de enige beheerroute blokkeert.

## Waarom nog niet verwijderen

`beheer.php` is nog een groot legacybestand met meerdere gekoppelde formulieren, rechten, backups en meldingen. De oude formulieren worden pas verwijderd nadat de generieke editor op de testomgeving praktisch is gecontroleerd op:

1. lezen van bestaande data;
2. opslaan van NL/EN/DE;
3. automatische backup;
4. centrale lock;
5. rechten voor gewone beheeraccounts en master;
6. correcte weergave op `ontstaan.html` en `baanreglement.html`.

## Volgende stap

Na deze controle kunnen de oude `$ontstaanVelden`, `$baanreglementVelden`, bijbehorende groepen en de twee pagina-specifieke opslagblokken uit `beheer.php` worden verwijderd. Daarna kan 1D worden afgerond en is de registry + generieke editor de enige bron voor configureerbare contentpagina's.
