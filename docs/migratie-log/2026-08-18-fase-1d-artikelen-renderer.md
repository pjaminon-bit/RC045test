# Fase 1D — generieke artikelen-renderer

Datum: 2026-08-18

## Uitgevoerd

- `content-renderer.php` uitgebreid met `contentRenderArtikelen()`.
- Het paginatype `artikelen` ondersteunt nu:
  - configureerbare hero-afbeelding, positie en opacity;
  - meertalige hero-labels en hero-titels;
  - meertalige ondertitel uit het databestand;
  - introkaart met apart vet label en introtekst;
  - configureerbare artikelnummering;
  - per artikel een titelveld en inhoudsveld uit de paginaregistry;
  - alinea-indeling op basis van lege regels in de opgeslagen tekst;
  - centrale branding, talenlinks, SEO en themakleuren.
- `pagina-definities.php` uitgebreid met de meertalige hero-identiteit van `baanreglement`.
- `baanreglement` staat nu op `legacy_layout => false`.
- `baanreglement.php` teruggebracht tot een dunne route die `contentRenderArtikelen('baanreglement')` uitvoert.

## Resultaat

Zowel `ontstaan.php` als `baanreglement.php` bevatten geen unieke pagina-layout meer. Ze zijn compatibiliteitsroutes naar generieke paginatypen. De bestaande URLs blijven daardoor gelijk, terwijl nieuwe verenigingen dezelfde renderers kunnen hergebruiken met andere definities en content.

## Bewuste wijziging ten opzichte van de oude baanreglement-layout

De oude hardcoded subartikelen en bullets zijn niet meer onderdeel van de PHP-template. De beheerdata was al ingericht als één tekstvak per artikel. De generieke renderer gebruikt daarom precies die opgeslagen structuur en splitst inhoud op lege regels in losse alinea's.

## Volgende stap

Om fase 1D volledig template-waardig af te ronden ontbreekt nog een generieke manier om nieuwe contentpagina's te registreren/beheren zonder voor iedere pagina nieuwe formuliercode in `beheer.php` toe te voegen. Daarna kunnen `ontstaan` en `baanreglement` voorbeelden/configuraties zijn in plaats van speciale gevallen in de applicatie.
