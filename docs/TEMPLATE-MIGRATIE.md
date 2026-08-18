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
- Logo, verenigingsnaam, slogan en de belangrijkste themakleuren worden tijdens fase 1 eveneens centraal in de gerenderde publieke site toegepast.

#### Tijdelijke compatibiliteitslaag

De outputfilter is bewust tijdelijk. De grote publieke PHP-bestanden worden later individueel opgeschoond en krijgen uiteindelijk rechtstreeks de generieke helpers in hun templates. Zodra alle vaste blokken en RC045-branding uit de bron verdwenen zijn kan de migratiefilter worden verwijderd zonder zichtbare wijziging.

### 1C. Modules configureerbaar maken

Status: **afgerond**

Doel: modules per vereniging aan/uit kunnen zetten zonder aparte codebase.

#### Resultaat

- `module-definities.php` is de centrale bron van waarheid voor modulekoppelingen.
- Publieke pagina's, links en secties kunnen per module worden verborgen of geblokkeerd.
- Zelfstandige uitgeschakelde publieke modulepagina's krijgen een nette 404/noindex-respons.
- De bijbehorende tabs in `beheer.php` en `leden.php` worden verborgen.
- Bekende POST-formulieren van uitgeschakelde modules worden server-side geblokkeerd, ook voor een masteraccount.
- Geblokkeerde mutatiepogingen worden als `module_geblokkeerd` gelogd zonder POST-inhoud of persoonsgegevens.
- De interne flags `ledenadministratie`, `vergaderingen`, `taken` en `operationele_taken` zijn gekoppeld aan de betreffende onderdelen van `leden.php`.
- `evenementen` is een hybride module en stuurt zowel publieke agendaweergave als beheer- en ledenpaneelfunctionaliteit.
- `website` is geclassificeerd als kernflag; volledige tenant/site-activatie wordt later op provisioning/deploymentniveau geregeld en niet als runtime template-kill-switch.

Zie `docs/migratie-log/2026-08-18-fase-1c-afronding.md` voor de volledige classificatie en grens van deze fase.

### 1D. Generieke contentpagina's

Status: **afgerond**

Doel: RC-specifieke pagina's onder een algemeen content-/pagina-concept brengen, zodat andere verenigingen herbruikbare paginatypen kunnen gebruiken zonder per pagina een unieke PHP-template en unieke opslaglogica te bouwen.

#### Resultaat

- `pagina-definities.php` is de centrale registry voor configureerbare contentpagina's.
- `ontstaan` draait als generiek paginatype `verhaal`.
- `baanreglement` draait als generiek paginatype `artikelen`.
- Per pagina staan slug, label, SEO-sleutel, beheer-tab, databestand, hero-instellingen, veldstructuur en eventuele galerij/artikelstructuur centraal vastgelegd.
- `content-pagina.php` levert de generieke bootstrap, data-, taal-, hero-, SEO- en typehelpers.
- `content-renderer.php` rendert de gedeelde paginatypen; `ontstaan.php` en `baanreglement.php` zijn dunne routes geworden.
- `content-beheer.php` is de generieke editor. Velden en groepen worden automatisch uit `pagina-definities.php` opgebouwd.
- De editor gebruikt het bestaande rechtenmodel, CSRF, centrale data-lock, back-ups en logging.
- De generieke route is op de DEV-omgeving praktisch getest voor Ontstaan en Baanreglement: lezen, opslaan, publieke weergave, logboek en back-up zijn bevestigd.
- De historische Ontstaan/Baanreglement-ingangen in `beheer.php` zijn daarna uit runtimegebruik genomen: de tabs worden verborgen en de oude POST-formulieren worden server-side geblokkeerd.

#### Technische schuld voor fase 2

De oude Ontstaan/Baanreglement-code staat fysiek nog als onbereikbare dode code in het grote monolithische `beheer.php`. Omdat dit bestand zeer groot is, wordt die fysieke verwijdering gecombineerd met de structurele opsplitsing van `beheer.php` in fase 2. Functioneel bestaat er vanaf 1D nog maar één beheerroute: `content-beheer.php`.

Zie de bestanden onder `docs/migratie-log/2026-08-18-fase-1d-*.md` voor de technische tussenstappen en validatie.

## Eerste inventarisatie

Reeds gevonden hardcoding / technische schuld die voor fase 1 relevant is:

- Veel publieke code gebruikt functies met prefix `rc045...`; dit wordt stapsgewijs generieker gemaakt om regressies te vermijden.
- `styles.css` bevat vaste RC045-kleuren als defaults; runtime worden de belangrijkste tokens inmiddels centraal overschreven.
- Publieke pagina's bevatten in de bron nog vaste favicon- en theme-color-tags; runtime worden die inmiddels centraal vervangen.
- De repository bevat de vaste asset `rc045-logo.png`; het pad en de gerenderde branding zijn inmiddels configureerbaar.
- `auth.php` en diverse comments/labels zijn expliciet op RC045 benoemd.
- Grote bestanden zoals `beheer.php` en `leden.php` worden in fase 2 structureel opgesplitst.

## Besluiten

### 2026-08-18 — één gedeelde codebase als uitgangspunt

De beoogde architectuur is één gedeelde applicatiecodebase voor meerdere verenigingen, met per vereniging gescheiden configuratie/data/uploads en later bij voorkeur een eigen database. Er komt dus niet standaard één fork/repository per vereniging.

### 2026-08-18 — geen zichtbare RC045-wijzigingen tijdens 1A

Tijdens de introductie van de centrale configuratielaag blijven de bestaande RC045-waarden leidend. Eerst wordt hardcoding verplaatst; pas daarna voegen we instelbaarheid vanuit beheer toe.

### 2026-08-18 — compatibiliteitslaag tijdens refactor

Bestaande publieke functienamen met `rc045` worden niet in één keer hernoemd. De interne bron wordt eerst generiek gemaakt. Dit beperkt het aantal gelijktijdige wijzigingen en maakt regressies eenvoudiger te herleiden.

### 2026-08-18 — contentconfiguratie apart van applicatielogica

Verenigingsspecifieke SEO-content staat voortaan in `site-seo.php` en niet meer in `seo-head.php`. Dit patroon wordt ook gebruikt voor contentpagina's via `pagina-definities.php`.

### 2026-08-18 — tijdelijke outputfilter voor grote legacy-pagina's

Omdat meerdere publieke pagina's tientallen tot honderden kilobytes groot zijn en hetzelfde historische head-blok bevatten, wordt dit blok tijdens fase 1 server-side vervangen. Dit geeft direct centrale branding zonder een risicovolle bulk-herschrijving. De filter is nadrukkelijk een migratiehulpmiddel en geen eindarchitectuur.

### 2026-08-18 — moduleflags zijn functionaliteit, geen gebruikersrecht

Een uitgeschakelde module is voor de hele vereniging uitgeschakeld, ook voor een masteraccount. Gebruikersrechten bepalen vervolgens alleen wie toegang heeft tot modules die voor die vereniging wél actief zijn.

### 2026-08-18 — legacy contentbeheer pas na praktijktest uit runtime

De oude Ontstaan- en Baanreglement-routes zijn pas gedeactiveerd nadat de generieke editor op de DEV-omgeving succesvol is getest op opslaan, publieke weergave, logging en back-up. Fysieke verwijdering van de dode code volgt bij de opsplitsing van `beheer.php` in fase 2.
