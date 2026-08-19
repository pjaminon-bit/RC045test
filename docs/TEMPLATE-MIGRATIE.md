# Template-migratie RC045test

Dit document houdt de ombouw van RC045test naar een generiek verenigingsplatform bij. Het is bedoeld als technisch logboek én als overdrachtsdocument: per stap staat wat is aangepast, waarom, welke keuzes zijn gemaakt en wat nog openstaat.

## Doel

Van RC045test een herbruikbare codebase maken waarbij verenigingsspecifieke gegevens, branding, modules en content losstaan van de gedeelde applicatielogica. RC045 blijft tijdens de migratie functioneel en visueel gelijk; de templateontwikkeling vindt uitsluitend plaats in `pjaminon-bit/RC045test`.

## Huidige werkwijze

- De gevalideerde templatecode staat vanaf 19-08-2026 op **`main` van `pjaminon-bit/RC045test`**.
- DEV wordt vanaf `RC045test/main` naar `/dev` gedeployed.
- De productie-repository **`RC045` wordt niet gebruikt voor deze templateontwikkeling**.
- Iedere DEV-deploy schrijft `dev-build.json`; in Beheer wordt commit, branch, runnummer en deploytijd zichtbaar gemaakt zodat de geteste build controleerbaar is.
- De deployworkflow voert vóór upload `php -l` uit op alle PHP-bestanden en daarna HTTP-smoketests.
- Kleine, controleerbare stappen hebben voorkeur boven een grote herschrijving.
- RC045-waarden blijven voorlopig veilige defaults zodat regressies beperkt blijven.
- Verenigingsspecifieke configuratie kan server-only in `site-config.local.php` staan en hoort niet in Git.

### Historische branchnotitie

Fase 1 en het eerste deel van fase 2 zijn ontwikkeld op `agent/template-foundation`. Na audit en herstel van de eerdere DEV-branchverwarring is die code op 19-08-2026 naar `RC045test/main` gepromoveerd. De oude branch is dus niet meer de bron voor nieuwe DEV-wijzigingen.

# Fase 1 — template-ready

Status: **AFGEROND**

Fase 1 levert één gedeelde codebasis op die per vereniging kan verschillen in identiteit, branding, modules en content zonder een aparte fork te maken. Relevante routes en editors zijn tijdens fase 2 opnieuw gebruikt en gecontroleerd.

## 1A. Centrale configuratielaag

Status: **afgerond**

- `site-config.php` bevat de gedeelde defaults voor identiteit, site-URL, tijdzone, talen, branding, themakleuren en feature flags.
- `site.php` biedt generieke helpers voor configuratie, naam, URL, talen, assets en modules.
- `seo-head.php` gebruikt centrale configuratie voor domein, talen, standaardtaal en social image.
- Verenigingsspecifieke SEO-content staat apart in `site-seo.php`.
- `site-config.local.php` is toegevoegd als concept voor server-/tenant-specifieke overrides. Het bestand wordt recursief over de defaults heen gelegd en hoeft alleen afwijkende waarden te bevatten.
- `site-config.local.php` staat in `.gitignore`; `site-config.local.example.php` documenteert de structuur.
- De configureerbare tijdzone wordt bij het laden van de siteconfig toegepast wanneer deze geldig is.

Compatibiliteitsnamen zoals `rc045Taal()`, `rc045Url()` en `rc045SeoHead()` mogen voorlopig blijven zolang hun interne bron generiek is.

## 1B. Branding generiek maken

Status: **afgerond**

- Logo, social image, faviconvarianten, apple-touch-icon, manifest en theme color zijn centraal configureerbaar.
- Verenigingsnaam en slogan worden centraal toegepast op de publieke site.
- De belangrijkste CSS-tokens voor primary, accent, tekst, muted, dark en achtergrond komen uit de configuratie.
- Kleuren worden gevalideerd en vallen bij een ongeldige waarde veilig terug op defaults.
- Historische favicon-/brandingmarkup mag nog in pagina's staan; de gerenderde HTML wordt centraal door de configuratielaag gecorrigeerd.

## 1C. Modules configureerbaar maken

Status: **afgerond**

- `module-definities.php` is de centrale bron van waarheid voor modulekoppelingen.
- Publieke pagina's, links en secties kunnen per module worden verborgen of geblokkeerd.
- Zelfstandige uitgeschakelde modulepagina's krijgen 404/noindex.
- Tabs in Beheer en Leden volgen dezelfde feature flags.
- Bekende POST-formulieren van uitgeschakelde modules worden server-side geblokkeerd, ook voor masteraccounts.
- Geblokkeerde mutatiepogingen worden gelogd zonder POST-inhoud of persoonsgegevens.
- `ledenadministratie`, `vergaderingen`, `taken`, `operationele_taken` en `evenementen` zijn aan hun relevante paneelonderdelen gekoppeld.
- `aanmelden`, `bedankt` en `faq` vormen nu technisch één aanmeldflow onder dezelfde feature flag.
- `website` blijft een kernflag; volledige tenantactivatie wordt later op provisioning/deploymentniveau geregeld.

Zie `docs/migratie-log/2026-08-18-fase-1c-afronding.md`.

## 1D. Generieke contentpagina's

Status: **afgerond**

- `pagina-definities.php` is de centrale registry voor configureerbare contentpagina's.
- `ontstaan` draait als paginatype `verhaal`.
- `baanreglement` draait als paginatype `artikelen`.
- Slug, label, SEO-sleutel, beheer-tab, databestand, hero, velden en galerij-/artikelstructuur staan centraal.
- `content-pagina.php` levert generieke bootstrap- en datahelpers.
- `content-renderer.php` rendert herbruikbare paginatypen; de oorspronkelijke pagina's zijn dunne routes.
- `content-beheer.php` is de generieke editor en gebruikt bestaand rechtenmodel, CSRF, centrale data-lock, back-ups, atomische JSON-opslag en logging.
- Directe editor-URL's respecteren de feature flag van het bijbehorende beheeronderdeel.

Zie `docs/migratie-log/2026-08-18-fase-1d-afronding.md`.

# Fase 2 — modulair beheer

Status: **AFGESLOTEN op 19-08-2026**

Doel van fase 2 was het historische monolithische beheer functioneel vervangen door zelfstandige beheeronderdelen, zonder de bestaande datamodellen, rechten, logging en back-ups kwijt te raken.

## Canonieke runtime

Er is één actieve beheerarchitectuur:

- `beheer/index.php` — centrale beheerpagina en menu;
- `app/paneel-hulp.php` — contextdetectie;
- `app/beheer/bootstrap.php` — modulebootstrap;
- `app/beheer/module-registry.php` — centrale beheerregistry;
- `app/beheer/modules/*.php` — menu-, feature- en legacyguards;
- `beheer/*.php` — zelfstandige beheereditors.

De dubbele historische boom `beheer/bootstrap.php`, `beheer/module-registry.php` en `beheer/modules/*` is uit Git verwijderd. Omdat de huidige SFTP-deploy additief is, blokkeert `.htaccess` eventuele stale exemplaren op de server met HTTP 403.

## Gemigreerde beheeronderdelen

Alle zichtbare beheeronderdelen staan op `status = module` en volgen de afgesproken menuconventie: `*` betekent gemigreerd.

- Homepage
- Ontstaan / geschiedenis
- Reglement
- Aanmelden
- Bedankt-pagina
- Mededeling
- Nieuws
- Agenda
- Contact
- Sponsors
- Vragen / FAQ
- Media
- Fotoboek
- Rekentabel
- Changelog
- Gebruikers
- Logboek
- Back-ups

De volledige `*`-markering is op de draaiende DEV-omgeving visueel bevestigd.

## Gevoelige beheerfuncties

- Gebruikers vereist expliciet `gebruikers`-recht;
- Back-ups vereist expliciet `backups`-recht;
- Logboek vereist na de security-eindaudit expliciet `log`-recht;
- masteraccounts blijven de enige impliciete uitzondering;
- rechten-, wachtwoord-, blokkade- en verwijderacties verhogen de sessieversie zodat bestaande sessies kunnen worden ingetrokken.

## Opslag en audit

- één globaal dataslot beschermt lees-wijzig-schrijfhandelingen;
- het dataslot staat canoniek in projectroot `data-backups/.data.lock`;
- nieuwe/generieke editors gebruiken atomische temp+rename-opslag waar ingevoerd;
- beveiligingskritische gebruikersdata wordt atomisch geschreven;
- automatische snapshots zijn uniek tot op microseconden;
- auditlog read-modify-write wordt volledig onder `flock` uitgevoerd;
- Logboek-CSV neutraliseert spreadsheetformule-injectie;
- Back-ups herstellen uitsluitend bekende registrybestanden en controleren snapshotpaden met `realpath()`.

## Uploadbeveiliging

- Sponsors accepteert alleen gecontroleerde PNG/JPEG/WEBP-afbeeldingen tot 1 MB en gebruikt servergegenereerde bestandsnamen.
- Fotoboek normaliseert albumslugs/bestandsnamen en bouwt nieuwe foto's opnieuw als JPEG op.
- Fotoboek weigert vóór GD-decodering afbeeldingen groter dan 60 megapixel of 16.000 pixels in een dimensie om geheugenuitputting door image bombs te beperken.

## Security-eindaudit

Fase 2 is vóór afsluiting opnieuw gecontroleerd op authenticatie, sessies, CSRF, rechten, feature flags, directe URL's, legacy POST-routes, XSS, uploads, padvalidatie, JSON-opslag, back-ups, logging, CSV-export en `.htaccess`.

Eindoordeel: **geen bekende kritieke of hoge fase-2 kwetsbaarheden blijven open.**

Volledig rapport:

`docs/migratie-log/2026-08-19-fase-2-code-security-audit.md`

Functionele afronding:

`docs/migratie-log/2026-08-19-fase-2-functioneel-compleet.md`

# Resterende technische schuld na fase 2

Deze punten blokkeren de template niet, maar horen bij verdere platformisering/opschoning:

- `beheer/index.php` bevat nog fysiek runtime-dode legacyformuliercode. De modulaire guards blokkeren die routes; fysieke reductie kan nu als mechanische cleanup plaatsvinden zonder nieuwe functionele migratie.
- Agenda, Sponsors en Media gebruiken nog hun eigen bestaande JSON-writer. Ze draaien onder het centrale dataslot en maken vooraf back-ups; later uniformeren op de gedeelde atomische writer.
- Publieke legacy-pagina's bevatten nog vaste markup die runtime door de templatefilter wordt vervangen.
- Diverse functies, variabelen, comments en data-labels gebruiken nog de prefix/naam `rc045`.
- `styles.css` bevat RC045-kleuren als fallback-defaults; runtime worden de hoofdvariabelen per tenant overschreven.
- Het server-only masterconfig ondersteunt tijdelijk nog een plaintext compatibility-variabele wanneer geen hash aanwezig is. Nieuwe tenants moeten uitsluitend met een password hash worden geprovisioned.
- De huidige `.htaccess` HTTPS-redirect bevat nog `rc045.nl`; op de toekomstige VPS moet dit tenant-/webservergestuurd worden.
- De huidige tenantoverride is bestand-gebaseerd. Een toekomstige VPS/multi-tenantlaag kan dezelfde configuratie-API uit database/provisioning voeden zonder pagina's opnieuw te herschrijven.

# Belangrijkste besluiten

## Eén gedeelde codebase

De architectuur gaat uit van één gedeelde applicatiecodebase met per vereniging gescheiden configuratie, data en uploads, en later bij voorkeur een eigen database of tenant-scope. Niet standaard één repository of fork per vereniging.

## Feature flags zijn functionaliteit

Een uitgeschakelde module is voor de hele vereniging uitgeschakeld, ook voor masteraccounts. Gebruikersrechten bepalen alleen wie een actieve module mag beheren.

## Compatibiliteit boven big-bang refactor

Legacyfunctienamen en outputfilters mogen tijdelijk blijven zolang hun interne bron generiek is en ze geen tenant-hardcoding meer afdwingen. Dode code wordt gecontroleerd verwijderd, niet in één riskante big-bang wijziging.

## Tenantconfiguratie buiten Git

`site-config.php` bevat gedeelde defaults. Afwijkingen per vereniging staan in `site-config.local.php`, dat server-only en Git-genegeerd is. Dit is de eerste praktische stap naar meerdere verenigingen op één codebasis.

# Volgende fase

De volgende mijlpaal is het bewijs dat dit werkelijk een verenigingsplatform is: **een tweede fictieve vereniging op dezelfde gedeelde code starten zonder RC045-specifieke wijzigingen in de applicatiecode**.

Daarvoor moeten minimaal worden uitgewerkt:

1. tenant-identiteit/configuratie;
2. gescheiden data-opslag;
3. gescheiden uploads/assets;
4. eerste beheerder per tenant;
5. modulekeuze per tenant;
6. provisioning/herhaalbaar installatieproces;
7. voorbereiding op een VPS-layout waarin meerdere verenigingen dezelfde applicatiecode veilig delen.
