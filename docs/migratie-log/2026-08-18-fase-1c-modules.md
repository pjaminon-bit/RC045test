# Fase 1C — configureerbare publieke modules

Datum: 2026-08-18
Branch: `agent/template-foundation`

## Doel

Publieke onderdelen per vereniging kunnen uitschakelen via `site-config.php`, zonder aparte codebase of handmatige wijzigingen in navigatie en footer.

## Gekoppelde modules

De volgende flags zijn nu daadwerkelijk gekoppeld aan de publieke site:

- `fotoboek`
- `media`
- `aanmelden`
- `evenementen`
- `sponsors`

## Technische wijzigingen

In `site.php` zijn toegevoegd / uitgebreid:

- `siteModuleVoorPagina()` — koppelt zelfstandige publieke pagina's aan hun feature flag.
- `siteModulePaginaToegestaan()` — generieke controle of een modulepagina actief is.
- `siteHuidigePubliekePagina()` — bepaalt op basis van `REQUEST_URI` welke publieke pagina wordt opgevraagd en ondersteunt zowel `.html` als `.php`.
- `siteBewaakPubliekeModule()` — centrale guard die uitgeschakelde zelfstandige modulepagina's blokkeert vóór de normale pagina wordt gerenderd.
- `siteRenderModuleNietBeschikbaar()` — rendert een eenvoudige, gebrande 404-pagina voor uitgeschakelde zelfstandige modules.
- `siteVerbergUitgeschakeldeModules()` — verwijdert links en injecteert module-afhankelijke zichtbaarheid voor onderdelen die op de homepage/footer ingebed zijn.
- `siteModuleVisibilityMarkup()` — maakt centrale CSS-selectors voor ingebedde modules die geen eigen pagina hebben.

De guard draait vóór de bestaande fase-1 outputfilter. Daardoor wordt uitgeschakelde zelfstandige functionaliteit niet eerst opgebouwd om daarna pas verborgen te worden.

## Gedrag zelfstandige modules

Als bijvoorbeeld in `site-config.php` staat:

```php
'fotoboek' => false,
```

dan:

1. verdwijnen bekende links naar `fotoboek.html` uit navigatie en footer;
2. wordt directe toegang tot zowel `fotoboek.html` als `fotoboek.php` geblokkeerd;
3. retourneert de server HTTP-status `404`;
4. krijgt de response `X-Robots-Tag: noindex, nofollow` en een `robots` meta-tag;
5. ziet de bezoeker een eenvoudige pagina in de ingestelde verenigingskleuren met een link terug naar de homepage.

Voor `aanmelden => false` worden zowel de opvallende `nav-lid`-knop als gewone footerlinks naar `aanmelden.html` verwijderd, en wordt de aanmeldpagina rechtstreeks geblokkeerd.

## Gedrag ingebedde modules

`evenementen` en `sponsors` hebben geen zelfstandige publieke pagina die geblokkeerd moet worden; ze zijn onderdelen van bestaande pagina's.

Bij:

```php
'evenementen' => false,
```

worden de homepage-sectie `#activiteiten` en bekende links naar die sectie verborgen.

Bij:

```php
'sponsors' => false,
```

wordt het sponsorgedeelte in de footer verborgen, inclusief de sponsortitel, sponsorgrid, CTA en de bekende sponsorlink in de footer.

Deze zichtbaarheid wordt centraal als CSS in de uiteindelijke HTML geïnjecteerd. Daardoor hoeven de grote legacy-pagina's tijdens fase 1 niet individueel herschreven te worden.

## Waarom 404 voor zelfstandige pagina's

Een uitgeschakelde zelfstandige module bestaat voor die vereniging functioneel niet. Een 404-respons is daarom duidelijker dan een redirect naar de homepage en voorkomt dat zoekmachines of externe links een niet-bestaande functionaliteit als geldige pagina blijven behandelen.

## Belangrijke beperking van de huidige fase-1 oplossing

Voor ingebedde modules zoals evenementen en sponsors wordt de presentatie nu centraal uitgeschakeld, maar bestaande inline JavaScript-code kan de bijbehorende databron nog wel opvragen. Dat is functioneel niet zichtbaar voor bezoekers, maar in de eindarchitectuur moeten ook data-loading en beheerfunctionaliteit modulebewust worden gemaakt.

Dat wordt in de volgende stap van 1C aangepakt: bepalen welke beheeronderdelen, endpoints en data-loaders aan iedere module gekoppeld zijn.

## Ontwerpbesluit

Modules worden niet gekoppeld aan aparte repositories of templates. Dezelfde applicatie blijft alle functionaliteit bevatten; per vereniging bepalen feature flags welke onderdelen actief zijn. Dit maakt updates en beveiligingsfixes centraal uitrolbaar.
