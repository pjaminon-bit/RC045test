# Fase 2 — Sponsors als modulaire beheerfunctie

Datum: 2026-08-18

## Doel

Sponsors uit het monolithische `beheer.php` halen en via de nieuwe beheer-modulearchitectuur laten lopen, zonder het bestaande publieke sponsor-datamodel te wijzigen.

## Uitgevoerd

- `beheer/modules/sponsors.php` toegevoegd als bootstrapmodule.
- `beheer/sponsors.php` toegevoegd als zelfstandige sponsor-editor.
- `beheer/module-registry.php` zet `sponsors` nu op status `module`.
- De historische Sponsors-tab in `beheer.php` wordt verborgen.
- Een POST naar het oude formulier `formulier=sponsors` wordt vóór de legacy-opslag afgehandeld als geblokkeerde route en gelogd als `legacy_sponsors_geblokkeerd`.
- Vanuit het bestaande beheer verschijnt voor bevoegde gebruikers een nieuwe ingang **Sponsors beheren**.

## Datamodel

Het bestaande publieke formaat blijft behouden:

```json
{
  "updated": "ISO-8601 datum",
  "items": [
    {
      "name": "Sponsornaam",
      "url": "https://...",
      "logo": "sponsor-1.png",
      "width": 123,
      "height": 45
    }
  ],
  "cta": {
    "nl": "...",
    "en": "...",
    "de": "..."
  }
}
```

## Beveiliging en opslag

- Bestaande `auth.php`-sessie en rechtenmodel worden hergebruikt.
- Alleen gebruikers met recht `sponsors` of de masterbeheerder krijgen toegang.
- De feature flag `sponsors` moet actief zijn.
- CSRF-controle blijft verplicht.
- Schrijven gebeurt onder `dataSlotOpen()` / `dataSlotDicht()`.
- Voor iedere JSON-overschrijving wordt `maakDataBackup()` gebruikt.
- Succesvolle opslag wordt in het bestaande beheerlog geregistreerd.
- Sponsorlogo's accepteren alleen PNG, JPG en WEBP, maximaal 1 MB, en worden met `getimagesize()` als echte afbeelding gevalideerd.

## Legacycode

De historische Sponsors-code staat fysiek nog in `beheer.php`, maar is niet meer de actieve beheerroute. Fysieke verwijdering volgt wanneer het monolithische bestand verder wordt opgesplitst.

## DEV-validatie

Voor definitieve bevestiging op de server testen:

1. Open `/dev/beheer.php` en kies **Sponsors beheren**.
2. Wijzig tijdelijk een sponsornaam of CTA en sla op.
3. Controleer de publieke DEV-site.
4. Zet de wijziging terug.
5. Controleer logboek en `data-backups/`.
6. Optioneel: upload een klein testlogo (PNG/JPG/WEBP < 1 MB), controleer weergave en zet daarna het oorspronkelijke logo terug.
