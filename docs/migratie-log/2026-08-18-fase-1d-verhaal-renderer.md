# Fase 1D — generieke renderer voor paginatype `verhaal`

Datum: 2026-08-18

## Uitgevoerd

- `content-renderer.php` toegevoegd met een generieke `contentRenderVerhaal()` renderer.
- De renderer gebruikt `contentPaginaBootstrap()` en daarmee centraal:
  - paginadefinitie;
  - databestand;
  - hero-configuratie;
  - SEO-sleutel;
  - actieve taal;
  - branding uit `site-config.php`.
- `pagina-definities.php` voor `ontstaan` uitgebreid met meertalige hero-label/titel en een configureerbare galerij.
- De zes bestaande historische foto's staan nu als galerijdata in de paginaregistry in plaats van hardcoded in een unieke `ontstaan.php`-layout.
- `ontstaan.php` teruggebracht tot een dunne route/wrapper die alleen de generieke renderer aanroept.
- De bestaande publieke URL blijft daardoor gelijk, terwijl de pagina niet langer een eigen unieke PHP-template nodig heeft.

## Veiligheid / validatie

- Galerijafbeeldingen accepteren in deze fase alleen lokale relatieve paden.
- Paden met `..` en externe `http(s)//`-assets worden genegeerd.
- Alle tekstwaarden worden HTML-geescaped voordat ze worden gerenderd.
- Meertalige content valt terug op Nederlands wanneer de gevraagde taal leeg is.

## Functionele verandering

De historische pagina-layout is vervangen door de generieke `verhaal`-layout. De inhoud, branding, hero en galerij blijven aanwezig, maar navigatie/footer zijn nu generiek en eenvoudiger opgebouwd. Dit is bewust de eerste echte omschakeling van legacy-pagina naar templatepagina.

## Volgende stap

- Visueel/inhoudelijk controleren of `ontstaan` nog alle gewenste elementen bevat.
- Daarna paginatype `artikelen` generiek bouwen en `baanreglement.php` op dezelfde manier reduceren tot een wrapper.
- Vervolgens de beheerzijde laten werken vanuit `pagina-definities.php`, zodat nieuwe contentpagina's zonder nieuwe hardcoded beheersectie kunnen worden toegevoegd.
