# Fase 1C — centrale moduledefinities

Datum: 2026-08-18
Branch: `agent/template-foundation`

## Doel

Voorkomen dat de koppeling tussen een module, publieke pagina, publieke sectie, beheertab en beheerformulier op meerdere plekken afzonderlijk wordt onderhouden.

## Nieuwe bron van waarheid

`module-definities.php` bevat nu per module:

- leesbaar label;
- zelfstandige publieke pagina's;
- publieke links die verborgen moeten worden;
- publieke CSS-selectors voor secties/onderdelen;
- bijbehorende beheertabs;
- bijbehorende POST-formulieren;
- speciale zichtbaarheid, zoals de `nav-lid`-knop bij Aanmelden.

De eerste definities zijn:

- `fotoboek`
- `media`
- `aanmelden`
- `evenementen`
- `sponsors`

De aan/uit-status blijft bewust in `site-config.php` staan. `module-definities.php` beschrijft wat een module technisch omvat; `site-config.php` bepaalt of die module voor de vereniging actief is.

## Wijzigingen in `site.php`

`site.php` leest de centrale definities via:

- `siteModuleDefinities()`
- `siteModuleDefinitie()`
- `siteModuleVoorWaarde()`
- `siteModuleVoorPagina()`
- `siteModuleVoorBeheerTab()`
- `siteModuleVoorBeheerFormulier()`
- `siteModuleLabel()`

Publieke zichtbaarheid, beheertab-zichtbaarheid en publieke pagina-guards worden nu uit dezelfde definitie afgeleid.

## Server-side beheer-guard

Tijdens deze controle bleek dat de eerdere migratienotitie al meldde dat beheer-POST's server-side door moduleflags werden geblokkeerd, terwijl die guard nog niet daadwerkelijk in de branch stond. Dat is in deze stap gecorrigeerd.

`siteBewaakBeheerModulePost()` draait nu op `beheer.php` vóór de normale opslagafhandeling. Als bijvoorbeeld `sponsors => false` staat en iemand toch handmatig een POST met `formulier=sponsors` verstuurt:

1. wordt via de centrale definitie vastgesteld dat het formulier bij `sponsors` hoort;
2. wordt het formulier vóór de opslaglogica ongeldig gemaakt;
3. wordt de poging, als de bestaande logfunctie beschikbaar is, gelogd als `module_geblokkeerd` met alleen module- en formuliernaam;
4. POST-inhoud en persoonsgegevens worden niet in dit logdetail opgenomen.

Deze guard geldt onafhankelijk van gebruikersrechten, dus ook een master-account kan een voor de vereniging uitgeschakelde module niet muteren.

## Waarom deze structuur

Een nieuwe module hoeft voortaan niet meer in meerdere losse mappings te worden toegevoegd. De technische relaties worden op één plaats beschreven. Dat verkleint de kans dat bijvoorbeeld een beheertab verborgen is terwijl een POST-actie nog wel bereikbaar blijft.

## Volgende stap

De volgende logische stap is bepalen welke overige bestaande onderdelen echte optionele modules zijn en welke kernfunctionaliteit van ieder verenigingspakket vormen. Daarna kunnen we 1C afronden en doorgaan naar fase 1D: generieke contentpagina's in plaats van RC-specifieke vaste pagina's zoals `baanreglement.php`.
