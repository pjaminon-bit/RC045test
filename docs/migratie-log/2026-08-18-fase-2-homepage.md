# Fase 2 — Homepage als modulaire contenteditor

Datum: 2026-08-18

## Uitgevoerd

- Homepage opgenomen in de centrale `pagina-definities.php` met alle bestaande beheerbare velden.
- De bestaande 14 logische Homepage-groepen zijn behouden.
- De bestaande veldlimieten zijn behouden: 100 tekens voor korte tekstvelden en 600 voor tekstblokken.
- `data/homepage.json` blijft de opslaglocatie; bestaande serverdata wordt dus hergebruikt.
- Wanneer `homepage.json` ontbreekt of velden leeg zijn, gebruikt de contentlaag de actuele standaardteksten uit `homepage.js` als fallback.
- De generieke editor gebruikt dezelfde CSRF-controle, dataslot, databack-up en logging als de andere gemigreerde contentpagina's.
- Het menu-item Homepage is omgezet naar `/beheer/content.php?pagina=homepage`.
- De historische Homepage-tab in `beheer.php` is verborgen.
- Historische POST-verzoeken met `formulier=homepage` worden geblokkeerd, zodat er maar één actieve opslagroute overblijft.
- `beheer/module-registry.php` markeert Homepage als `module`.

## Validatie

Praktisch gevalideerd op `/dev` op 2026-08-18:

- nieuwe modulaire Homepage-route opent vanuit het normale beheer-menu;
- alle Homepage-groepen worden getoond;
- standaardteksten worden correct geladen via de fallback wanneer `homepage.json` nog niet bestaat;
- tijdelijke `[TEST]`-wijziging kon worden opgeslagen en verscheen correct op de DEV-homepage;
- wijziging kon daarna weer worden teruggezet.

Status: **gevalideerd**.
