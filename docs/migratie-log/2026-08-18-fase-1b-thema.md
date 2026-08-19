# Fase 1B — configureerbaar kleurthema

## Doel

De publieke website moet de belangrijkste huisstijlkleuren uit `site-config.php` gebruiken, zonder dat een nieuwe vereniging `styles.css` hoeft te wijzigen.

## Uitgevoerd

- In `site.php` is `siteVeiligeKleur()` toegevoegd. Alleen geldige hexkleuren in formaat `#RRGGBB` worden geaccepteerd; bij ongeldige configuratie valt de code terug op de bestaande RC045-default.
- In `site.php` is `siteThemeMarkup()` toegevoegd.
- De volgende configuratievelden worden gekoppeld aan bestaande CSS-variabelen:
  - `branding.kleuren.primary` → `--teal`
  - `branding.kleuren.primary_dark` → `--teal-dark`
  - `branding.kleuren.primary_light` → `--teal-light`
  - `branding.kleuren.accent` → `--gold`
  - `branding.kleuren.accent_light` → `--gold-light`
  - `branding.kleuren.dark` → `--dark`
  - `branding.kleuren.text` → `--text`
  - `branding.kleuren.muted` → `--muted`
  - `branding.kleuren.background` → `--bg`
- De tijdelijke fase-1 outputfilter voegt vlak voor `</head>` een `<style id="site-theme">` toe. Dit komt na de bestaande stylesheet en pagina-specifieke styles en overschrijft daardoor de historische RC045-defaults in `styles.css`.
- Ook `branding.theme_color` gebruikt nu dezelfde kleurvalidatie.

## Bewuste keuze

`styles.css` blijft voorlopig zijn bestaande defaultwaarden bevatten. Dit is een veilige fallback en voorkomt tijdens fase 1 een grote stylesheet-refactor. In een latere opschoonfase kan worden besloten de defaults generieker te benoemen en/of de themalaag rechtstreeks als apart CSS-endpoint of gegenereerd stylesheet te serveren.

## Effect

Een vereniging kan nu via `site-config.php` de hoofdkleuren wijzigen en krijgt die centraal toegepast op de publieke site, voor zover de huidige interface CSS-variabelen gebruikt. Losse hardcoded kleurwaarden in pagina-specifieke CSS worden later afzonderlijk geïnventariseerd.
