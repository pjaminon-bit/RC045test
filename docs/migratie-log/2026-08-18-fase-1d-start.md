# Fase 1D — start generieke contentpagina's

Datum: 2026-08-18
Branch: `agent/template-foundation`

## Doel

RC045-specifieke losse pagina's stapsgewijs terugbrengen naar generieke paginatypen, zodat een andere vereniging eigen geschiedenis-, reglement- en later andere contentpagina's kan gebruiken zonder nieuwe PHP-code per vereniging.

## Eerste kandidaten

De eerste twee pagina's zijn bewust gekozen omdat ze inhoudelijk sterk verschillen maar allebei al deels via JSON/CMS-data worden gevoed:

- `ontstaan.php` — verhaal/geschiedenispagina;
- `baanreglement.php` — artikel-/reglementpagina.

De bestaande URLs en layouts blijven tijdens deze eerste 1D-stap ongewijzigd.

## Nieuwe centrale registry

`pagina-definities.php` toegevoegd. Per contentpagina staat daarin nu centraal:

- slug;
- beheerslabel;
- paginatype;
- SEO-sleutel;
- beheer-tab;
- databestand;
- hero-afbeelding, positie en opacity;
- inhoudsvelden;
- voor het reglement: de artikelstructuur.

De eerste generieke paginatypen zijn:

- `verhaal` — een intro/hero met meerdere tekstblokken;
- `artikelen` — een intro/hero gevolgd door genummerde artikelen met titel en inhoud.

## Generieke contentlaag

`content-pagina.php` toegevoegd met helpers voor:

- laden en opvragen van pagina-definities;
- veilig bepalen van het databestand;
- lezen van JSON-content;
- taalafhankelijke veldwaarden met NL-fallback;
- hero-instellingen;
- SEO-sleutel;
- beheer-tab;
- paginatype.

Hiermee hoeft toekomstige gedeelde renderer-/beheerlogica niet meer te weten dat een pagina specifiek `ontstaan` of `baanreglement` heet.

## Bewust nog legacy

Beide bestaande PHP-bestanden renderen hun layout nog zelf. Ook de fotogrid op `ontstaan.php` en de bestaande HTML-structuur van het baanreglement zijn nog legacy. In `pagina-definities.php` staat daarom tijdelijk `legacy_layout => true`.

Dit is bewust: eerst beschrijven we de pagina's in een generiek model, daarna sluiten we de bestaande pagina's op dat model aan. Zo veranderen we niet tegelijk data, rendering, beheer én URLs.

## Volgende stap

1. `ontstaan.php` laten bootstrappen vanuit `content-pagina.php` voor SEO-key en hero-instellingen.
2. `baanreglement.php` hetzelfde laten doen.
3. Daarna een gedeelde renderer maken voor de inhoudsblokken.
4. Vervolgens beheer.php de velddefinities uit `pagina-definities.php` laten gebruiken in plaats van eigen hardcoded veldlijsten.
5. Als beide pagina's volledig via de generieke laag lopen, kan `legacy_layout` per pagina uit.

## Einddoel 1D

Een vereniging moet uiteindelijk een contentpagina kunnen definiëren met bijvoorbeeld:

- slug `clubgeschiedenis`;
- type `verhaal`;
- eigen titel/SEO;
- eigen hero;
- eigen tekstblokken;

zonder dat daarvoor `clubgeschiedenis.php` met verenigingsspecifieke applicatielogica nodig is.
