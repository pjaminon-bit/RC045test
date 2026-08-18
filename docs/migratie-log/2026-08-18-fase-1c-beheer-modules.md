# Fase 1C — modules in beheer

Datum: 2026-08-18
Branch: `agent/template-foundation`

## Doel

Dezelfde feature flags die publieke onderdelen uitschakelen ook laten doorwerken in `beheer.php`, zodat een vereniging geen beheerinterface ziet voor functionaliteit die zij niet gebruikt.

## Gekoppelde beheertabs

De eerste beheer-koppeling gebruikt deze mapping:

- `evenementen` → tab `agenda`
- `sponsors` → tab `sponsors`
- `media` → tab `media`
- `fotoboek` → tab `fotoboek`
- `aanmelden` → tab `aanmelden`

Als een module op `false` staat, wordt het bijbehorende tabpaneel en de bekende tabnavigatie via centrale CSS onzichtbaar gemaakt.

## Technische wijzigingen

### `site.php`

- Contextdetectie toegevoegd met `siteHuidigScript()` en `siteIsBeheerPagina()`.
- Publieke module-guard slaat `beheer.php` over.
- `siteBeheerModuleVisibilityMarkup()` toegevoegd voor beheer-specifieke modulezichtbaarheid.
- De outputfilter is nu contextbewust:
  - op `beheer.php`: alleen beheer-modulezichtbaarheid;
  - op publieke pagina's: bestaande branding, thema en publieke modulefiltering.

### `paneel-hulp.php`

`site.php` wordt alleen ingeladen als het huidige script `beheer.php` is. Daardoor krijgt `leden.php`, dat hetzelfde hulpbestand gebruikt, niet automatisch de publieke outputfilter of beheer-module-CSS.

## Waarom eerst zichtbaarheid en daarna opslagblokkade

`beheer.php` is een groot legacy-bestand met veel POST-acties. Het direct verbouwen van alle acties tegelijk zou onnodig veel regressierisico geven. Daarom doen we 1C in twee lagen:

1. beheertabs van uitgeschakelde modules verdwijnen uit de interface;
2. daarna worden de bijbehorende POST-/opslagacties server-side geblokkeerd zodat handmatig geconstrueerde requests ook niets kunnen wijzigen.

De eerste laag is nu uitgevoerd. De tweede laag is de volgende stap.

## Belangrijk

Dit is nog geen volledige autorisatiegrens. CSS-verbergen voorkomt normaal gebruik via de interface, maar een handmatig POST-verzoek kan bestaande opslagcode mogelijk nog bereiken. Dat wordt in de volgende stap centraal afgevangen.
