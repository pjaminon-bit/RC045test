# Fase 1C — afronding modulearchitectuur

Datum: 2026-08-18
Branch: `agent/template-foundation`

## Status

**Afgerond**

## Wat nu centraal geregeld is

De feature flags in `site-config.php` zijn geclassificeerd en gekoppeld aan één centrale registry in `module-definities.php`.

### Kern

- `website` — markeert de publieke website als kernfunctionaliteit. Deze flag wordt in fase 1C niet gebruikt om de complete site uit te schakelen; tenant-/site-activatie hoort later bij provisioning/deployment.

### Publieke modules

- `fotoboek`
- `media`
- `aanmelden`
- `sponsors`

Deze kunnen publieke pagina's/links/secties, beheertabs en beheer-POSTs uitschakelen.

### Hybride module

- `evenementen`

Deze flag stuurt zowel de publieke activiteitenweergave en Agenda in `beheer.php` als het evenemententabblad en evenementmutaties in `leden.php`.

### Interne modules

- `ledenadministratie` — beheert de tabbladen Leden en Commissies en bijbehorende mutaties.
- `vergaderingen` — beheert Bestuursvergadering en Ledenvergadering en bijbehorende mutaties.
- `taken` — beheert Takenlijst en bijbehorende mutaties.
- `operationele_taken` — beheert Operationele taken en bijbehorende mutaties.

De onderliggende ledenidentiteit blijft beschikbaar als `ledenadministratie` uitstaat, omdat andere modules zoals evenementen, rollen en persoonlijke informatie een gekoppeld lid nodig kunnen hebben.

## Techniek

- `module-definities.php` is de bron van waarheid voor labels, type, publieke pagina's/links/selectors, beheertabs/-formulieren en ledenpaneeltabs/-formulieren.
- `site.php` gebruikt deze registry voor de publieke website en `beheer.php`.
- `paneel-modules.php` gebruikt dezelfde registry voor `leden.php`, zonder de publieke branding/outputfilter te laden.
- `paneel-hulp.php` routeert automatisch naar de juiste modulelaag voor `beheer.php` of `leden.php`.
- Uitgeschakelde modules worden niet alleen visueel verborgen: bekende POST-formulieren worden vóór de bestaande opslaglogica geneutraliseerd en de poging wordt als `module_geblokkeerd` gelogd zonder formulierinhoud of persoonsgegevens.

## Bewuste grens van fase 1C

Feature flags bepalen nu of een functioneel onderdeel beschikbaar/beheerbaar is. Ze zijn nog geen pakket-, facturatie- of licentiesysteem. Ook laden sommige legacy-bestanden nog opslaghelpers voor modules die uitstaan; het voorkomen van onnodige includes en het verder opsplitsen van `beheer.php`/`leden.php` hoort bij fase 2.

De complete `website`-flag is bewust niet als runtime kill-switch geïmplementeerd. Een vereniging/site geheel activeren of deactiveren hoort later op tenant/provisioningniveau, niet in de template-output.

## Resultaat

Fase 1C voldoet nu aan het doel: dezelfde codebase kan per vereniging functionele modules aan- of uitzetten, waarbij publieke zichtbaarheid, paneelzichtbaarheid en server-side mutaties zoveel mogelijk vanuit dezelfde centrale definitie worden gestuurd.
