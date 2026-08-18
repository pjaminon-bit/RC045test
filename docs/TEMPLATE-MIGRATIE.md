# Template-migratie RC045test

Dit document houdt de ombouw van RC045test naar een generiek verenigingsplatform bij. Het is bedoeld als technisch logboek én als overdrachtsdocument: per stap staat wat is aangepast, waarom, welke keuzes zijn gemaakt en wat nog openstaat.

## Doel

Van RC045test een herbruikbare codebase maken waarbij verenigingsspecifieke gegevens, branding, modules en content losstaan van de gedeelde applicatielogica. RC045 blijft tijdens de migratie functioneel en visueel gelijk; eerst verandert alleen waar configuratie vandaan komt.

## Werkwijze

- Ontwikkeling gebeurt op `agent/template-foundation` en niet rechtstreeks op `main`.
- `main` blijft voorlopig de automatisch gedeployde Strato-testsite.
- Kleine, controleerbare stappen hebben voorkeur boven een grote herschrijving.
- RC045-waarden blijven tijdens fase 1 de defaults zodat regressies beperkt blijven.
- Verenigingsspecifieke gegevens horen uiteindelijk niet meer verspreid in applicatiebestanden te staan.

## Fase 1 — template-ready

### 1A. Centrale configuratielaag

Status: **bezig**

#### Gedaan

- `site-config.php` toegevoegd als centrale verenigingsconfiguratie voor identiteit, site-URL, tijdzone, talen, branding, themakleuren en feature flags.
- `site.php` toegevoegd als generieke toegang tot configuratie.
- Helpers toegevoegd voor configuratiepaden, naam, volledige naam, URL, talen, assets en module-status.
- `seo-head.php` gebruikt nu de centrale configuratie voor site-URL, talen, standaardtaal en social image.
- `site-seo.php` toegevoegd: RC045-specifieke SEO-titels en omschrijvingen staan nu buiten de gedeelde SEO-engine.
- `seo-head.php` bevat daardoor alleen nog generieke SEO-logica plus tijdelijke compatibiliteitsnamen.
- `x-default` en de kale URL worden gebaseerd op de configureerbare standaardtaal.

#### Compatibiliteit

- `rc045Taal()`, `rc045Url()` en `rc045SeoHead()` blijven voorlopig bestaan zodat publieke pagina's niet allemaal tegelijk hoeven te veranderen.
- De meta-naam `rc045-title-*` blijft tijdelijk bestaan vanwege bestaande JavaScript-koppelingen.

#### Volgende stappen

- Algemene verenigingsgegevens in navigatie/footer/homepage centraliseren.
- Daarna CSS-kleuren vanuit de configuratie/themalaag laten komen.

### 1B. Branding generiek maken

Status: **bezig**

Doel: logo, favicon, kleuren, naam, slogan en social-media-afbeelding per vereniging instelbaar maken zonder broncodewijzigingen.

#### Gedaan

- `site-config.php` bevat logo, social image, favicon, PNG-faviconvarianten, apple-touch-icon, manifest en theme color.
- `site.php` bevat `siteHeadBranding()` en `siteHeadBrandingMarkup()` om deze head-assets centraal te renderen.
- `siteAsset()` toegevoegd naast `siteAssetUrl()`, zodat zowel relatieve webpaden als absolute social/SEO-URLs uit dezelfde configuratie kunnen komen.
- De publieke pagina's bevatten in de bron nog hun historische vaste favicon/manifest/theme-color-blok, maar `site.php` start nu vóór de HTML een server-side outputfilter die dit blok in de uiteindelijke HTML vervangt door de centrale configuratie.
- Daardoor werken favicon, PNG-favicons, apple-touch-icon, manifest en theme color nu al centraal op alle publieke pagina's die `seo-head.php` gebruiken, zonder grote pagina's tegelijk te herschrijven.

#### Tijdelijke compatibiliteitslaag

De outputfilter is bewust tijdelijk. De grote publieke PHP-bestanden worden later individueel opgeschoond en krijgen uiteindelijk rechtstreeks `siteHeadBranding()` in hun `<head>`. Zodra alle vaste blokken uit de bron verdwenen zijn kan `siteStartTemplateOutputFilter()` worden verwijderd zonder zichtbare wijziging.

#### Volgende stappen

- Logo, verenigingsnaam en slogan in navigatie/footer/homepage aan de configuratie koppelen.
- Daarna CSS-themawaarden centraal genereren.
- De historische head-blokken later fysiek uit de publieke pagina's verwijderen wanneer die pagina's toch worden opgeschoond.

### 1C. Modules configureerbaar maken

Status: **nog te doen**

Doel: modules per vereniging aan/uit kunnen zetten zonder aparte codebase.

### 1D. Generieke contentpagina's

Status: **nog te doen**

Doel: RC-specifieke pagina's zoals `baanreglement.php` uiteindelijk onder een algemeen content-/pagina-concept brengen, zodat andere verenigingen eigen pagina's kunnen maken zonder nieuwe PHP-bestanden.

## Eerste inventarisatie

Reeds gevonden hardcoding / technische schuld die voor fase 1 relevant is:

- Veel publieke code gebruikt functies met prefix `rc045...`; dit wordt stapsgewijs generieker gemaakt om regressies te vermijden.
- `styles.css` bevat vaste RC045-kleuren als CSS-variabelen.
- Publieke pagina's bevatten in de bron nog vaste favicon- en theme-color-tags; runtime worden die inmiddels centraal vervangen.
- De repository bevat de vaste asset `rc045-logo.png`; het pad is configureerbaar maar body-elementen moeten nog worden gekoppeld.
- `auth.php` en diverse comments/labels zijn expliciet op RC045 benoemd.
- Grote bestanden zoals `beheer.php` en `leden.php` worden pas in fase 2 structureel opgesplitst; fase 1 beperkt zich tot template-/configuratiescheiding.

## Besluiten

### 2026-08-18 — één gedeelde codebase als uitgangspunt

De beoogde architectuur is één gedeelde applicatiecodebase voor meerdere verenigingen, met per vereniging gescheiden configuratie/data/uploads en later bij voorkeur een eigen database. Er komt dus niet standaard één fork/repository per vereniging.

### 2026-08-18 — geen zichtbare RC045-wijzigingen tijdens 1A

Tijdens de introductie van de centrale configuratielaag blijven de bestaande RC045-waarden leidend. Eerst wordt hardcoding verplaatst; pas daarna voegen we instelbaarheid vanuit beheer toe.

### 2026-08-18 — compatibiliteitslaag tijdens refactor

Bestaande publieke functienamen met `rc045` worden niet in één keer hernoemd. De interne bron wordt eerst generiek gemaakt. Dit beperkt het aantal gelijktijdige wijzigingen en maakt regressies eenvoudiger te herleiden.

### 2026-08-18 — contentconfiguratie apart van applicatielogica

Verenigingsspecifieke SEO-content staat voortaan in `site-seo.php` en niet meer in `seo-head.php`. Dit patroon kan later ook voor andere content/configuratie worden gebruikt.

### 2026-08-18 — tijdelijke outputfilter voor grote legacy-pagina's

Omdat meerdere publieke pagina's tientallen tot honderden kilobytes groot zijn en hetzelfde historische head-blok bevatten, wordt dit blok tijdens fase 1 server-side vervangen. Dit geeft direct centrale branding zonder een risicovolle bulk-herschrijving. De filter is nadrukkelijk een migratiehulpmiddel en geen eindarchitectuur.
