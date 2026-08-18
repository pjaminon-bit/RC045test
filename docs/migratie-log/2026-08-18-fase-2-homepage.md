# Fase 2 — Homepage als modulaire contenteditor

Datum: 2026-08-18

## Uitgevoerd

- Homepage opgenomen in de centrale `pagina-definities.php` met alle bestaande beheerbare velden.
- De bestaande 14 logische Homepage-groepen zijn behouden.
- De bestaande veldlimieten zijn behouden: 100 tekens voor korte tekstvelden en 600 voor tekstblokken.
- `data/homepage.json` blijft de opslaglocatie; bestaande serverdata wordt dus hergebruikt.
- De generieke editor gebruikt dezelfde CSRF-controle, dataslot, databack-up en logging als de andere gemigreerde contentpagina's.
- Het menu-item Homepage wordt omgezet naar `/beheer/content.php?pagina=homepage`.
- De historische Homepage-tab in `beheer.php` wordt verborgen.
- Historische POST-verzoeken met `formulier=homepage` worden geblokkeerd, zodat er maar één actieve opslagroute overblijft.
- `beheer/module-registry.php` markeert Homepage nu als `module`.

## Validatie

Na deployment op `/dev`:

1. Controleer de DEV-buildbadge.
2. Open Homepage vanuit het normale beheer-menu; URL moet `/beheer/content.php?pagina=homepage` zijn.
3. Controleer of de bekende groepen en bestaande teksten geladen zijn.
4. Wijzig één onschuldig Nederlands tekstveld tijdelijk met `[TEST]` en sla op.
5. Controleer de wijziging op de DEV-homepage.
6. Zet de tekst terug en sla opnieuw op.
7. Controleer logboek en databack-up.

Pas na deze test geldt de Homepage-module als praktisch gevalideerd.
