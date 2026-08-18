# Fase 1C — configureerbare publieke modules

Datum: 2026-08-18
Branch: `agent/template-foundation`

## Doel

Publieke onderdelen per vereniging kunnen uitschakelen via `site-config.php`, zonder aparte codebase of handmatige wijzigingen in navigatie en footer.

## Eerste modules

De eerste set is bewust beperkt tot duidelijke, zelfstandige publieke modules:

- `fotoboek`
- `media`
- `aanmelden`

Deze flags bestonden al in `site-config.php` en zijn nu daadwerkelijk gekoppeld aan de gerenderde navigatie/footer.

## Technische wijzigingen

In `site.php` zijn toegevoegd:

- `siteModuleVoorPagina()` — koppelt een publieke pagina aan zijn feature flag.
- `siteModulePaginaToegestaan()` — generieke controle voor directe pagina-toegang; wordt in een volgende stap aan de pagina-entrypoints gekoppeld.
- `siteVerbergUitgeschakeldeModules()` — verwijdert navigatie- en footerlinks van uitgeschakelde modules uit de uiteindelijke HTML.

De bestaande fase-1 outputfilter roept deze modulefilter als laatste stap aan. Daardoor hoeven de grote legacy-pagina's nog niet allemaal tegelijk herschreven te worden.

## Gedrag

Als bijvoorbeeld in `site-config.php` staat:

```php
'fotoboek' => false,
```

dan verdwijnen bekende links naar `fotoboek.html` uit navigatie en footer.

Voor `aanmelden => false` worden zowel de opvallende `nav-lid`-knop als gewone footerlinks naar `aanmelden.html` verwijderd.

## Nog open

Navigatie verbergen is niet voldoende: een bezoeker kan een uitgeschakelde pagina nog rechtstreeks via de URL openen. De volgende stap binnen 1C is daarom een centrale module-guard voor `fotoboek`, `media` en `aanmelden`, met een nette 404/uitgeschakeld-respons zonder redirect-loop.

Daarna kunnen meer modules worden aangesloten, bijvoorbeeld sponsors/evenementen en later interne beheeronderdelen.

## Ontwerpbesluit

Modules worden niet gekoppeld aan aparte repositories of templates. Dezelfde applicatie blijft alle functionaliteit bevatten; per vereniging bepalen feature flags welke onderdelen actief zijn. Dit maakt updates en beveiligingsfixes centraal uitrolbaar.
