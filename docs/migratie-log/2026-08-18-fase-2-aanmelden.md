# Fase 2 — Aanmelden als modulaire contenteditor

Datum: 2026-08-18

## Afbakening

Deze migratie verplaatst alleen de **CMS-laag** van de aanmeldpagina. De publieke formulierlogica wordt bewust niet gewijzigd.

Ongewijzigd gebleven in `aanmelden.php` en `aanmelden-ontvangst.php`:

- verplichte persoonsgegevens en adresvelden;
- minimaal e-mailadres of mobiel nummer;
- e-mail- en mobielvalidatie;
- landcode + mobiel samenvoegen;
- twee verplichte akkoordverklaringen;
- jeugd/senior-bepaling op basis van geboortedatum en rekentabel;
- pro-rata contributieberekening inclusief decemberlogica;
- Formspree als eerste verzendroute;
- eigen `aanmelden-ontvangst.php` als tweede opslagroute naar ledenadministratie;
- honeypot, rate-limit per gehasht IP en dubbele inzendingcontrole;
- status `nieuw` en contributieregel `open` bij automatische ontvangst.

## Gemodulariseerd

- `aanmelden` toegevoegd aan `pagina-definities.php`.
- Bestaande CMS-velden behouden: hero, contributiekaart-teksten, formulierkoppen, bevestigingsmelding en FAQ-titel.
- Bestaande veldlimieten behouden: 200 tekens voor korte velden, 500 voor tekstblokken.
- `data/aanmelden.json` blijft de opslaglocatie.
- De actuele NL/EN/DE-standaardteksten zijn als fallback in de paginadefinitie opgenomen, zodat een ontbrekend `aanmelden.json` nooit een leeg CMS oplevert.
- De generieke contentlaag ondersteunt nu per-pagina standaardwaarden.
- Het normale beheer-menu verwijst naar `/beheer/content.php?pagina=aanmelden`.
- De historische Aanmelden-tab en `formulier=aanmelden` route in `beheer.php` worden via de contentmodule geblokkeerd.

## Praktische validatie na DEV-deployment

1. Controleer de DEV-buildbadge.
2. Open Aanmelden vanuit het normale beheer-menu.
3. Controleer dat alle bestaande CMS-teksten gevuld zijn.
4. Doe één tijdelijke `[TEST]`-wijziging in bijvoorbeeld de hero-titel en controleer de publieke DEV-aanmeldpagina.
5. Zet de tekst terug.
6. Test daarna het publieke formulier zonder te verzenden: verplichte velden, e-mail/mobiel, beide akkoordvinkjes en contributieberekening moeten zich exact gedragen als vóór de migratie.
7. Een echte testaanmelding alleen uitvoeren als we bewust ook de Formspree-mail en ledenadministratie willen testen.

Status: **gebouwd, nog praktisch te valideren**.
