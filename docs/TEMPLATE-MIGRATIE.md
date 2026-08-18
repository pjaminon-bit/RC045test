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

- `site-config.php` toegevoegd als eerste centrale verenigingsconfiguratie.
- Configuratie bevat momenteel:
  - verenigingsnaam, volledige naam en slogan;
  - site-URL en tijdzone;
  - standaardtaal en beschikbare talen;
  - logo, social image, favicon, manifest en theme color;
  - eerste set themakleuren;
  - feature flags voor website, ledenadministratie, evenementen, vergaderingen, taken, operationele taken, fotoboek, sponsors, media en aanmelden.
- Huidige RC045-waarden zijn bewust als defaults opgenomen.

#### Volgende stappen

- `seo-head.php` de centrale configuratie laten gebruiken voor site-URL, talen, social image en verenigingsnaam.
- Bestaande `rc045...` functienamen voorlopig compatibel houden om een grote gelijktijdige wijziging te voorkomen; generieke aliases kunnen later worden ingevoerd.
- Daarna publieke pagina's koppelen aan centrale branding en algemene verenigingsgegevens.
- Vervolgens CSS-kleuren vanuit de configuratie/themalaag laten komen.

### 1B. Branding generiek maken

Status: **nog te doen**

Doel: logo, favicon, kleuren, naam, slogan en social-media-afbeelding per vereniging instelbaar maken zonder broncodewijzigingen.

### 1C. Modules configureerbaar maken

Status: **nog te doen**

Doel: modules per vereniging aan/uit kunnen zetten zonder aparte codebase.

### 1D. Generieke contentpagina's

Status: **nog te doen**

Doel: RC-specifieke pagina's zoals `baanreglement.php` uiteindelijk onder een algemeen content-/pagina-concept brengen, zodat andere verenigingen eigen pagina's kunnen maken zonder nieuwe PHP-bestanden.

## Eerste inventarisatie

Reeds gevonden hardcoding / technische schuld die voor fase 1 relevant is:

- `seo-head.php` bevat het vaste domein `https://rc045.nl`, het RC045-logo en RC045-specifieke SEO-titels en omschrijvingen.
- Veel publieke code gebruikt functies met prefix `rc045...`; dit wordt stapsgewijs generieker gemaakt om regressies te vermijden.
- `styles.css` bevat vaste RC045-kleuren als CSS-variabelen.
- `index.php` gebruikt vaste favicon-bestanden en een vaste `theme-color`.
- De repository bevat de vaste asset `rc045-logo.png`.
- `auth.php` en diverse comments/labels zijn expliciet op RC045 benoemd.
- Grote bestanden zoals `beheer.php` en `leden.php` worden pas in fase 2 structureel opgesplitst; fase 1 beperkt zich tot template-/configuratiescheiding.

## Besluiten

### 2026-08-18 — één gedeelde codebase als uitgangspunt

De beoogde architectuur is één gedeelde applicatiecodebase voor meerdere verenigingen, met per vereniging gescheiden configuratie/data/uploads en later bij voorkeur een eigen database. Er komt dus niet standaard één fork/repository per vereniging.

### 2026-08-18 — geen zichtbare RC045-wijzigingen tijdens 1A

Tijdens de introductie van de centrale configuratielaag blijven de bestaande RC045-waarden leidend. Eerst wordt hardcoding verplaatst; pas daarna voegen we instelbaarheid vanuit beheer toe.
