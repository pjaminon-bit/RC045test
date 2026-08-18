# Fase 2 — Agenda als beheermodule

Datum: 2026-08-18

## Uitgevoerd

- `beheer/agenda.php` toegevoegd als zelfstandige Agenda-editor.
- Bestaand datamodel behouden: `date`, `tag`, `time`, `title.nl/en/de`, `desc.nl/en/de`, `past`.
- De bestaande tags `leden`, `opendag` en `wedstrijd` blijven ondersteund.
- Bestaande limieten behouden: titel 80 tekens, tijd 40, omschrijving 200.
- Volgorde blijft expliciet instelbaar en wordt bij opslaan volgens dezelfde invoeglogica gesorteerd.
- CSRF, autorisatie, centraal dataslot, automatische databack-up en logboek worden gebruikt.
- `beheer/modules/agenda.php` toegevoegd om de historische Agenda-tab te verbergen, de oude POST-route te blokkeren en vanuit het hoofdbeheer naar de nieuwe editor te linken.
- `beheer/module-registry.php`: Agenda van `legacy` naar `module` gezet.

## Validatie

Net als bij Sponsors moet de module op `/dev/` praktisch worden getest: bestaande agenda laden, een herkenbare wijziging opslaan, publieke homepage controleren, wijziging terugzetten en logboek/back-up controleren.

De oude Agenda-code blijft fysiek in `beheer.php` staan als onbereikbare legacycode totdat de modulaire beheerlaag voldoende onderdelen heeft overgenomen om het monolithische bestand gecontroleerd op te schonen.
