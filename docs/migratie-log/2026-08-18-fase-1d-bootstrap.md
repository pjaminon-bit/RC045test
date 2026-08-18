# Fase 1D — contentpagina bootstrap

Datum: 2026-08-18

## Uitgevoerd

- `content-pagina.php` uitgebreid met request-detectie en een generieke `contentPaginaBootstrap()`.
- Een geregistreerde contentpagina kan nu centraal zijn definitie, databestand, hero, SEO-sleutel, beheertab en paginatype ophalen.
- `contentPaginaHeroCss()` toegevoegd. Deze zet de hero-configuratie uit `pagina-definities.php` om naar veilige CSS voor de bestaande legacy-layout.
- Alleen lokale relatieve hero-assets worden in deze fase toegestaan; externe URLs en `..`-paden worden geweigerd.
- `seo-head.php` kent nu de contentpagina-registry. Voor geregistreerde pagina's wordt de SEO-sleutel via `pagina-definities.php` bepaald.

## Effect

`ontstaan` en `baanreglement` zijn nu technisch aangesloten op dezelfde centrale pagina-identiteit, ook al renderen hun bestaande PHP-bestanden de inhoud nog met de historische layout. Daarmee kan de volgende stap de hero- en data-bootstrap in de templates vervangen zonder opnieuw een apart configuratiemodel te introduceren.

## Nog te doen

1. Hero-CSS van `ontstaan.php` en `baanreglement.php` daadwerkelijk vanuit `contentPaginaHeroCss()` laten komen.
2. Hun bestaande data-loading koppelen aan `contentPaginaBootstrap()['data']`.
3. Daarna de twee legacy-layouts terugbrengen naar herbruikbare renderers voor `verhaal` en `artikelen`.
4. Fotogrid van `ontstaan` modelleren als generiek galerijblok.
