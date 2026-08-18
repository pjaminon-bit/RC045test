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

Deze flags bestonden al in `site-config.php` en zijn nu daadwerkelijk gekoppeld aan zowel navigatie/footer als directe pagina-toegang.

## Technische wijzigingen

In `site.php` zijn toegevoegd:

- `siteModuleVoorPagina()` — koppelt een publieke pagina aan zijn feature flag.
- `siteModulePaginaToegestaan()` — generieke controle of een modulepagina actief is.
- `siteHuidigePubliekePagina()` — bepaalt op basis van `REQUEST_URI` welke publieke pagina wordt opgevraagd en ondersteunt zowel `.html` als `.php`.
- `siteBewaakPubliekeModule()` — centrale guard die uitgeschakelde modulepagina's blokkeert vóór de normale pagina wordt gerenderd.
- `siteRenderModuleNietBeschikbaar()` — rendert een eenvoudige, gebrande 404-pagina voor uitgeschakelde modules.
- `siteVerbergUitgeschakeldeModules()` — verwijdert navigatie- en footerlinks van uitgeschakelde modules uit de uiteindelijke HTML.

De guard draait vóór de bestaande fase-1 outputfilter. Daardoor wordt uitgeschakelde functionaliteit niet eerst opgebouwd om daarna pas verborgen te worden.

## Gedrag

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

## Waarom 404 en geen redirect

Een uitgeschakelde module bestaat voor die vereniging functioneel niet. Een 404-respons is daarom duidelijker dan een redirect naar de homepage en voorkomt dat zoekmachines of externe links een niet-bestaande functionaliteit als geldige pagina blijven behandelen.

## Nog open

De basis voor publieke modules staat nu. Volgende stappen binnen 1C:

- meer publieke modules koppelen waar dat functioneel logisch is, bijvoorbeeld sponsors en delen van evenementen;
- bepalen welke modules alleen publieke zichtbaarheid regelen en welke ook beheerfunctionaliteit moeten uitschakelen;
- later de modulechecks rechtstreeks in opgeschoonde templates opnemen zodat de tijdelijke outputfilter voor navigatielinks kan verdwijnen.

## Ontwerpbesluit

Modules worden niet gekoppeld aan aparte repositories of templates. Dezelfde applicatie blijft alle functionaliteit bevatten; per vereniging bepalen feature flags welke onderdelen actief zijn. Dit maakt updates en beveiligingsfixes centraal uitrolbaar.
