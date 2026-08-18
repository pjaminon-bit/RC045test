# Template-migratie RC045test

Dit document houdt de ombouw van RC045test naar een generiek verenigingsplatform bij. Het is bedoeld als technisch logboek én als overdrachtsdocument: per stap staat wat is aangepast, waarom, welke keuzes zijn gemaakt en wat nog openstaat.

## Doel

Van RC045test een herbruikbare codebase maken waarbij verenigingsspecifieke gegevens, branding, modules en content losstaan van de gedeelde applicatielogica. RC045 blijft tijdens de migratie functioneel en visueel gelijk; eerst verandert alleen waar configuratie vandaan komt.

## Werkwijze

- Ontwikkeling gebeurt op `agent/template-foundation` en niet rechtstreeks op `main`.
- Kleine, controleerbare stappen hebben voorkeur boven een grote herschrijving.
- RC045-waarden blijven de veilige defaults zodat regressies beperkt blijven.
- Verenigingsspecifieke configuratie kan server-only in `site-config.local.php` staan en hoort niet in Git.

# Fase 1 — template-ready

Status: **afgerond**

Fase 1 levert nu één gedeelde codebasis op die per vereniging kan verschillen in identiteit, branding, modules en content zonder een aparte fork te maken. De resterende RC045-namen en legacy-outputfilters zijn technische schuld voor fase 2, maar vormen geen blokkade meer voor templategebruik.

## 1A. Centrale configuratielaag

Status: **afgerond**

- `site-config.php` bevat de gedeelde defaults voor identiteit, site-URL, tijdzone, talen, branding, themakleuren en feature flags.
- `site.php` biedt generieke helpers voor configuratie, naam, URL, talen, assets en modules.
- `seo-head.php` gebruikt centrale configuratie voor domein, talen, standaardtaal en social image.
- Verenigingsspecifieke SEO-content staat apart in `site-seo.php`.
- `site-config.local.php` is toegevoegd als concept voor server-/tenant-specifieke overrides. Het bestand wordt recursief over de defaults heen gelegd en hoeft alleen afwijkende waarden te bevatten.
- `site-config.local.php` staat in `.gitignore`; `site-config.local.example.php` documenteert de structuur.
- De configureerbare tijdzone wordt bij het laden van de siteconfig toegepast wanneer deze geldig is.

Compatibiliteitsnamen zoals `rc045Taal()`, `rc045Url()` en `rc045SeoHead()` blijven voorlopig bestaan om grote legacy-pagina's niet onnodig tegelijk te herschrijven. Hun interne bron is wel generiek.

## 1B. Branding generiek maken

Status: **afgerond**

- Logo, social image, faviconvarianten, apple-touch-icon, manifest en theme color zijn centraal configureerbaar.
- Verenigingsnaam en slogan worden centraal toegepast op de publieke site.
- De belangrijkste CSS-tokens voor primary, accent, tekst, muted, dark en achtergrond komen uit de configuratie.
- Kleuren worden gevalideerd en vallen bij een ongeldige waarde veilig terug op defaults.
- De bestaande publieke pagina's kunnen nog historische favicon-/brandingmarkup bevatten, maar de uiteindelijke gerenderde HTML wordt centraal overschreven door de configuratielaag.

De outputfilter blijft tijdelijk bestaan als migratiehulpmiddel. In fase 2 wordt de publieke template verder opgesplitst en kan deze compatibiliteitslaag geleidelijk verdwijnen.

## 1C. Modules configureerbaar maken

Status: **afgerond**

- `module-definities.php` is de centrale bron van waarheid voor modulekoppelingen.
- Publieke pagina's, links en secties kunnen per module worden verborgen of geblokkeerd.
- Zelfstandige uitgeschakelde modulepagina's krijgen 404/noindex.
- Tabs in `beheer.php` en `leden.php` volgen dezelfde feature flags.
- Bekende POST-formulieren van uitgeschakelde modules worden server-side geblokkeerd, ook voor masteraccounts.
- Geblokkeerde mutatiepogingen worden gelogd zonder POST-inhoud of persoonsgegevens.
- `ledenadministratie`, `vergaderingen`, `taken`, `operationele_taken` en `evenementen` zijn aan hun relevante paneelonderdelen gekoppeld.
- `website` blijft een kernflag; volledige tenantactivatie wordt later op provisioning/deploymentniveau geregeld.

Zie `docs/migratie-log/2026-08-18-fase-1c-afronding.md`.

## 1D. Generieke contentpagina's

Status: **afgerond**

- `pagina-definities.php` is de centrale registry voor configureerbare contentpagina's.
- `ontstaan` draait als paginatype `verhaal`.
- `baanreglement` draait als paginatype `artikelen`.
- Slug, label, SEO-sleutel, beheer-tab, databestand, hero, velden en galerij-/artikelstructuur staan centraal.
- `content-pagina.php` levert generieke bootstrap- en datahelpers.
- `content-renderer.php` rendert herbruikbare paginatypen; de twee oorspronkelijke pagina's zijn dunne routes.
- `content-beheer.php` is de generieke editor en gebruikt bestaand rechtenmodel, CSRF, centrale data-lock, back-ups en logging.
- De generieke editor is praktisch op DEV getest voor Ontstaan en Baanreglement: lezen, opslaan, publieke weergave, logging en back-up werkten alle vier.
- De oude Ontstaan/Baanreglement-beheerroutes zijn uit runtimegebruik gehaald. Fysieke dode code in `beheer.php` wordt verwijderd bij de structurele opsplitsing in fase 2.

Zie `docs/migratie-log/2026-08-18-fase-1d-afronding.md`.

# Technische schuld voor fase 2

- Grote bestanden zoals `beheer.php` en `leden.php` zijn nog monolithisch.
- Publieke legacy-pagina's bevatten nog vaste markup die runtime door de templatefilter wordt vervangen.
- Diverse functies, variabelen, comments en data-labels gebruiken nog de prefix/naam `rc045`.
- `styles.css` bevat RC045-kleuren als fallback-defaults; runtime worden de hoofdvariabelen al per tenant overschreven.
- De huidige tenantoverride is bestand-gebaseerd. Een toekomstige VPS/multi-tenantlaag kan dezelfde configuratie-API later uit database/provisioning voeden zonder pagina's opnieuw te herschrijven.

# Belangrijkste besluiten

## Eén gedeelde codebase

De architectuur gaat uit van één gedeelde applicatiecodebase met per vereniging gescheiden configuratie, data en uploads, en later bij voorkeur een eigen database of tenant-scope. Niet standaard één repository of fork per vereniging.

## Feature flags zijn functionaliteit

Een uitgeschakelde module is voor de hele vereniging uitgeschakeld, ook voor masteraccounts. Gebruikersrechten bepalen alleen wie een actieve module mag beheren.

## Compatibiliteit boven big-bang refactor

Legacyfunctienamen en outputfilters mogen tijdelijk blijven zolang hun interne bron generiek is en ze geen tenant-hardcoding meer afdwingen. Structurele opschoning gebeurt in fase 2.

## Tenantconfiguratie buiten Git

`site-config.php` bevat gedeelde defaults. Afwijkingen per vereniging staan in `site-config.local.php`, dat server-only en Git-genegeerd is. Dit is de eerste praktische stap naar meerdere verenigingen op één codebasis.
