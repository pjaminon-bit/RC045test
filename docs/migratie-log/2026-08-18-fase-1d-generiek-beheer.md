# Fase 1D — generieke beheerlaag voor contentpagina's

Datum: 2026-08-18

## Uitgevoerd

- `content-beheer.php` uitgebreid van helperbestand naar een zelfstandige generieke contenteditor.
- De editor leest pagina, type, velden, groepen, databestand en beheerrechten uit `pagina-definities.php`.
- `ontstaan` en `baanreglement` zijn de eerste twee pagina's die volledig via deze generieke editor kunnen worden beheerd:
  - `content-beheer.php?pagina=ontstaan`
  - `content-beheer.php?pagina=baanreglement`
- De bestaande POST-prefixes `ont` en `br` zijn als tijdelijke compatibiliteit in de registry vastgelegd.
- De editor gebruikt dezelfde authenticatie, CSRF-beveiliging, centrale dataslot-lock, automatische databack-up en logging als het bestaande beheer.
- Voor type `verhaal` worden de velden automatisch als één inhoudsgroep opgebouwd.
- Voor type `artikelen` worden Intro en de artikelgroepen automatisch uit de registry opgebouwd.
- Veldlengtes worden generiek bepaald op basis van veldtype: korte tekst of tekstblok.

## Beveiliging

- Alleen ingelogde accounts kunnen de editor openen.
- De bestaande beheerrechten op de bijbehorende `beheer_tab` worden hergebruikt.
- Elke POST vereist een geldig CSRF-token.
- Schrijven gebeurt onder `dataSlotOpen()` / `dataSlotDicht()`.
- Voor overschrijven wordt via `maakDataBackup()` eerst een back-up gemaakt.
- Succesvolle wijzigingen worden gelogd als `contentpagina`.

## Compatibiliteit

De oude formulieren in `beheer.php` bestaan voorlopig nog. Dat is bewust: het grote legacy-bestand wordt in fase 2 opgesplitst. Nieuwe contentpagina's hoeven vanaf nu echter geen eigen opslagcode meer te krijgen; de generieke editor is de nieuwe route.

## Volgende stap

- Vanuit `beheer.php` de tabs voor generieke contentpagina's laten doorlinken naar de generieke editor, waarna de oude pagina-specifieke opslag- en formulierblokken veilig kunnen verdwijnen.
- Daarna fase 1D afronden en een voorbeeld/handleiding toevoegen voor het registreren van een nieuwe contentpagina.
