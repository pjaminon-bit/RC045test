# Fase 2 — volledige audit vóór promotie naar `main`

Datum: 2026-08-19
Repository: **`pjaminon-bit/RC045test`**
Ontwikkelbranch: `agent/template-foundation`
Doelbranch: `main`

> Deze audit betreft uitsluitend **RC045test**. De productie-repository `RC045` wordt niet gewijzigd.

## Conclusie

De huidige template-ontwikkelbranch is de juiste en meest complete codebasis en kan als actuele staat naar `main` van **RC045test** worden gepromoveerd.

Dat betekent nadrukkelijk niet dat ieder oorspronkelijk beheeronderdeel al fysiek is gemodulariseerd. Fase 2 heeft een groot deel van het beheer uit de monoliet gehaald, maar er is nog expliciete legacy-/opschoningsschuld. Die blijft hieronder zichtbaar staan en wordt niet als afgerond gepresenteerd.

## Canonieke beheerarchitectuur

Tijdens deze audit is vastgesteld dat de actieve runtime als volgt loopt:

- `/beheer/` -> `beheer/index.php`
- `beheer/index.php` laadt `app/paneel-hulp.php`
- `app/paneel-hulp.php` laadt voor beheer `app/beheer/bootstrap.php`
- `app/beheer/bootstrap.php` gebruikt `app/beheer/module-registry.php`
- de bootstrapmodules onder `app/beheer/modules/` zijn daarmee de actieve runtimebron
- zelfstandige editors/routes staan onder `/beheer/*.php`

Er bestaat daarnaast nog een tweede bootstrap/registry/modulekopie onder `beheer/`. Die is ontstaan tijdens de gefaseerde refactor. Deze audit behandelt `app/beheer/` als de canonieke runtimebron. De dubbele boom wordt niet blind verwijderd zonder volledige referentiecontrole; dit is expliciete opschoningsschuld.

## Sterretje-conventie

Afspraak: een `*` voor een beheer-menu-item betekent dat dit onderdeel naar de nieuwe modulaire implementatie is omgezet.

Tijdens de audit bleek dat Gebruikers, Logboek en Back-ups als zelfstandige modulelinks werden gerenderd en daardoor niet meer door de oude button-regex werden geraakt. De actieve runtime-modules onder `app/beheer/modules/` zijn aangepast zodat ook deze drie links weer consequent als gemigreerd worden gemarkeerd:

- `* Gebruikers`
- `* Logboek`
- `* Back-ups`

## Module-status volgens de actieve registry

De volgende onderdelen staan op `status = module`:

- DEV build-indicator
- Contentpagina's
  - Homepage
  - Ontstaan / geschiedenis
  - Baanreglement
  - Aanmelden (CMS-laag)
- Agenda
- Sponsors
- Media
- Fotoboek
- Gebruikers
- Back-ups
- Logboek

## Praktisch gevalideerd

### Homepage

DEV-gevalideerd. De modulaire editor opent, bestaande/fallback content wordt geladen, een testwijziging kon worden opgeslagen en publiek gecontroleerd en daarna worden teruggezet.

### Ontstaan en Baanreglement

De generieke contenteditor is functioneel getest tijdens fase 1D op openen, lezen, opslaan, publieke weergave, logging en automatische databack-up. De generieke contentarchitectuur blijft de actieve route.

### Fotoboek

DEV-gevalideerd inclusief bestaande albums, metadata, testupload, thumbnail, watermerk, cover, verwijderen en nette albumroutes.

### Gebruikers

DEV-gevalideerd op 2026-08-19:

- beperkt testaccount aangemaakt;
- rechten gecontroleerd;
- extra recht toegevoegd en opnieuw gecontroleerd;
- wachtwoord gewijzigd en opnieuw ingelogd;
- testaccount verwijderd;
- auditentries aanwezig.

De afgesproken minimale wachtwoordlengte is **10 tekens**.

### Logboek

DEV-gevalideerd op 2026-08-19:

- bestaande entries zichtbaar;
- zoeken/filteren werkt;
- actie-filter werkt;
- CSV-export respecteert filters.

### Back-ups

DEV-gevalideerd op 2026-08-19:

- bestaande snapshots zichtbaar;
- onschuldige testwijziging hersteld;
- oorspronkelijke waarde kwam terug;
- vóór herstel wordt opnieuw een snapshot gemaakt;
- `backup_hersteld` verschijnt in het logboek.

## Gemigreerd maar volgens de bestaande migratielog nog niet expliciet praktisch afgetekend

Deze onderdelen staan technisch op `module`, maar hun afzonderlijke migratielog bevat nog een open praktijktest:

### Agenda

Nog expliciet af te tekenen:

- bestaande agenda laden;
- tijdelijke herkenbare wijziging opslaan;
- publieke homepage controleren;
- wijziging terugzetten;
- logboek/back-up controleren.

### Sponsors

Nog expliciet af te tekenen:

- bestaande sponsors laden;
- tijdelijke naam/CTA-wijziging;
- publieke weergave controleren;
- terugzetten;
- logboek/back-up controleren;
- optioneel uploadvalidatie van sponsorlogo.

### Media

Nog expliciet af te tekenen:

- bestaande intro/items laden;
- tijdelijke `[TEST]`-wijziging;
- publieke media-pagina controleren;
- terugzetten;
- logboek/back-up controleren.

### Aanmelden

De CMS-laag is gemigreerd; publieke formulierlogica is bewust niet herschreven. Nog expliciet af te tekenen:

- CMS-content laden en testwijziging controleren;
- publieke aanmeldpagina controleren;
- wijziging terugzetten;
- formulier-validatie en contributieberekening regressietesten.

Een echte testaanmelding is alleen nodig wanneer Formspree-mail én automatische ledenopslag bewust meegetest moeten worden.

## Nog legacy in `beheer/index.php`

Vergelijking van de centrale rechtenlijst met de module-registry toont dat de volgende beheeronderdelen nog niet als zelfstandige module zijn geregistreerd:

- Bedankt-pagina
- Openingstijden / mededeling
- Nieuws
- Contact
- Vragen / FAQ
- Rekentabel
- Changelog

Deze onderdelen blijven functioneel in de huidige beheerapplicatie, maar horen bij de resterende fase-2-opsplitsing/technische schuld.

## Overige technische schuld

- `beheer/index.php` bevat nog veel historische, inmiddels deels onbereikbare legacycode.
- Er bestaan momenteel zowel `app/beheer/*` als oude kopieën onder `beheer/bootstrap.php`, `beheer/module-registry.php` en `beheer/modules/*`.
- De actieve runtimebron is `app/beheer/*`; de dubbele boom moet later gecontroleerd worden verwijderd zodra alle referenties zijn uitgesloten.
- Diverse RC045-namen/standaardteksten blijven bewust als veilige defaults bestaan zolang tenantconfiguratie ze kan overschrijven.

## Beoordeling voor `main` van RC045test

`agent/template-foundation` is aantoonbaar de correcte actuele ontwikkelstaat en bevat alle fase-1- en fase-2-wijzigingen die op DEV zijn opgebouwd. `main` bevat sinds het gezamenlijke basiscommit alleen tijdelijke workflow-/triggerhistorie zonder netto bestandsverschillen.

Daarom is de gekozen promotiestrategie:

1. deze audit en de runtimecorrecties eerst op `agent/template-foundation` vastleggen;
2. de **boom van `agent/template-foundation`** als actuele inhoud van `main` van `pjaminon-bit/RC045test` gebruiken;
3. beide histories behouden via een mergecommit wanneer mogelijk;
4. `RC045` onder geen enkele omstandigheid wijzigen.

## Status

**Fase 2 volledig geïnventariseerd. Huidige templatecode geschikt voor promotie naar `main` van RC045test. Resterende praktijktests en legacy-onderdelen hierboven expliciet vastgelegd.**
