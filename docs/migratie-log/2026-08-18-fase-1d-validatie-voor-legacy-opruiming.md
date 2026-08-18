# Fase 1D — validatie vóór legacy-opruiming

Datum: 2026-08-18

## Aanleiding

Voordat de oude pagina-specifieke beheerformulieren voor `ontstaan` en `baanreglement` uit `beheer.php` worden verwijderd, is gecontroleerd of de generieke contenteditor structureel dezelfde randvoorwaarden afdekt.

## Gecontroleerd

- De generieke editor gebruikt `pagina-definities.php` als bron voor paginatype, velden, groepen, databestand en beheertab.
- `authRechten()` wordt gebruikt met de bestaande beheertab als sleutel; daardoor blijft het bestaande rechtenmodel van kracht.
- CSRF-controle loopt via `auth.php`.
- Opslaan gebruikt het centrale `dataSlotOpen()` / `dataSlotDicht()`-slot.
- Voor overschrijven wordt dezelfde back-upfunctie `maakDataBackup()` gebruikt als elders in beheer.
- Wijzigingen worden in het bestaande beheerlog geregistreerd.
- De editor leest en schrijft dezelfde JSON-bestanden als de publieke generieke renderers.
- De generieke editor staat vanuit het bestaande beheer bereikbaar via de automatisch uit `pagina-definities.php` opgebouwde snelkoppelingen.

## Bewuste beslissing

De legacy-formulieren zijn **nog niet verwijderd**. De code is structureel gecontroleerd, maar een echte ingelogde opslagactie op de Strato-testomgeving kan vanuit deze ontwikkelcontext niet worden uitgevoerd. Voor definitieve verwijdering willen we eerst één praktijktest uitvoeren voor beide pagina's:

1. open de generieke editor;
2. wijzig een onschadelijke tekst;
3. sla op;
4. controleer de publieke pagina;
5. controleer of in `data-backups/` een back-up is ontstaan;
6. controleer het beheerlog;
7. zet de tekst eventueel terug.

Na succesvolle praktijktest kunnen de oude velddefinities, formulier-HTML en opslagblokken voor `ontstaan` en `baanreglement` uit `beheer.php` worden verwijderd.

## Correctie tijdens controle

Een tijdelijk dubbel helperbestand voor de beheer-snelkoppelingen is verwijderd. De bestaande implementatie in `paneel-hulp.php` blijft de enige migratie-ingang, zodat er geen dubbele outputbuffers of twee varianten van dezelfde functionaliteit ontstaan.
