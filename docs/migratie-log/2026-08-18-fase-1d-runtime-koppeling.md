# Fase 1D — runtime-koppeling legacy contentpagina's

Datum: 2026-08-18

## Uitgevoerd

- `content-pagina-runtime.php` toegevoegd als tijdelijke compatibiliteitslaag voor legacy contentpagina's.
- `seo-head.php` laadt deze runtime-laag na `content-pagina.php`.
- Voor geregistreerde legacy contentpagina's bepaalt `pagina-definities.php` nu tijdens rendering:
  - de hero-afbeelding;
  - hero-positie;
  - hero-opacity;
  - het JSON-data-endpoint.
- De bestaande JavaScript-renderers en Nederlandse fallbackteksten in `ontstaan.php` en `baanreglement.php` blijven intact.
- De gerenderde `<body>` krijgt tijdelijk `data-content-page` en `data-content-type`, zodat tijdens migratie zichtbaar/inspecteerbaar is welk generiek paginatype actief is.

## Waarom via een tijdelijke outputfilter?

De twee legacy-pagina's bevatten veel vaste HTML, CSS, vertalingen en JavaScript. Ze in één keer volledig herschrijven zou onnodig veel regressierisico geven. Met deze laag gebruiken ze nu al de centrale registry voor configuratie, terwijl hun bestaande rendering blijft functioneren. Zodra generieke renderers voor `verhaal` en `artikelen` klaar zijn, wordt deze laag weer verwijderd.

## Volgende stap

- Generieke renderer voor paginatype `verhaal` bouwen.
- `ontstaan` als eerste pagina op die renderer laten draaien.
- Daarna renderer voor `artikelen` bouwen en `baanreglement` migreren.
