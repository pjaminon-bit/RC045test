# Fase 2 — beheer functioneel volledig gemodulariseerd

Datum: 2026-08-19
Repository: **`pjaminon-bit/RC045test`**
Branch: **`main`**

> Dit document en alle beschreven wijzigingen betreffen uitsluitend `RC045test`. De productie-repository `RC045` is niet gewijzigd.

## Einddoel van fase 2

Het historische monolithische beheer stapsgewijs vervangen door zelfstandige beheeronderdelen zonder bestaande dataformaten, rechten, logging en back-ups te verliezen.

De functionele migratie is nu compleet: ieder zichtbaar beheeronderdeel heeft een nieuwe modulaire route of valt onder de generieke contenteditor. De oude tabpanelen in `beheer/index.php` zijn niet meer de actieve beheerroute en de bijbehorende legacy-POST-routes worden geblokkeerd.

## Canonieke runtime

Er is nog maar één beheer-bootstrapbron in de repository:

- `beheer/index.php` — centrale beheerpagina/menu;
- `app/paneel-hulp.php` — contextdetectie;
- `app/beheer/bootstrap.php` — actieve bootstrap;
- `app/beheer/module-registry.php` — centrale migratiestatus;
- `app/beheer/modules/*.php` — actieve menu-/legacyguards;
- `beheer/*.php` — zelfstandige editors.

De oude dubbele boom `beheer/bootstrap.php`, `beheer/module-registry.php` en `beheer/modules/*` is verwijderd. Omdat de SFTP-deployment additief is en eerder gedeployde bestanden op de server kan laten staan, blokkeert `.htaccess` die historische paden ook expliciet met HTTP 403.

## DEV-bron

Na de promotie van de templatecode naar `main` is ook de DEV-workflow aangepast. `/dev` wordt nu bij iedere push naar **`main` van RC045test** opgebouwd. De workflow voert vóór deployment `php -l` uit op alle PHP-bestanden en daarna smoke tests op de kernroutes en afgeschermde interne bestanden.

## Centrale module-registry

De actieve registry bevat nu alle nieuwe beheeronderdelen op `status = module`:

- Homepage / generieke contentpagina's
- Ontstaan / geschiedenis
- Baanreglement
- Aanmelden
- Bedankt-pagina
- Mededeling
- Nieuws
- Agenda
- Contact
- Sponsors
- Vragen / FAQ
- Media
- Fotoboek
- Rekentabel
- Changelog
- Gebruikers
- Back-ups
- Logboek

De afgesproken menuconventie blijft leidend: een `*` betekent dat het onderdeel naar de nieuwe modulaire implementatie is omgezet.

## Nieuwe zelfstandige editors in deze afronding

### Bedankt-pagina

- nieuwe route `/beheer/bedankt.php`;
- bestaand `data/bedankt.json` blijft het datamodel;
- IBAN en alle NL/EN/DE-teksten blijven ondersteund;
- `{jaar}` blijft dynamisch bruikbaar in betalingskenmerken;
- oude `formulier=bedankt` route wordt geblokkeerd;
- onderdeel valt samen met Aanmelden onder de feature flag `aanmelden`.

### Mededeling

- nieuwe route `/beheer/actueel.php`;
- `data/actueel.json` blijft behouden;
- maximaal 500 tekens;
- leeg opslaan verbergt de mededeling;
- `updated` blijft automatisch bijgewerkt;
- oude `formulier=actueel` route wordt geblokkeerd.

### Nieuws

- nieuwe route `/beheer/nieuws.php`;
- bestaand `data/nieuws.json` formaat blijft behouden;
- datum, NL/EN/DE titel/tekst/linktekst en optionele URL blijven ondersteund;
- URL moet `http://` of `https://` gebruiken;
- lege Nederlandse titel verwijdert het item;
- oude `formulier=nieuws` route wordt geblokkeerd.

### Contact & openingstijden

- nieuwe route `/beheer/contact.php`;
- bestaand `data/contact.json` formaat blijft behouden;
- adres, e-mail, Facebook en lidmaatschaptekst blijven ondersteund;
- openingstijden blijven per woensdag/zaterdag/zondag instelbaar;
- halfuurkeuzes van 06:00 t/m 22:00 blijven behouden;
- statussen `open`, `animo`, `animo_leden`, `leden`, `gesloten`, `onderhoud` en `weer` blijven behouden;
- tijdelijke statussen vervallen automatisch op hetzelfde moment als in de legacycode;
- oude `formulier=contact` route wordt geblokkeerd.

### Vragen / FAQ

- nieuwe route `/beheer/faq.php`;
- bestaand `data/faq.json` formaat blijft behouden;
- NL/EN/DE vragen en antwoorden blijven ondersteund;
- één leeg invoerblok wordt automatisch aangeboden voor een nieuwe vraag;
- lege Nederlandse vraag verwijdert een item;
- FAQ valt samen met de aanmeldpagina onder feature flag `aanmelden`;
- oude `formulier=faq` route wordt geblokkeerd.

### Rekentabel

- nieuwe route `/beheer/rekentabel.php`;
- bestaand `data/rekentabel.json` formaat blijft behouden;
- jaar, inschrijfkosten, jeugd-/seniorbedragen, leeftijdsgrens en optionele bedragen voor volgend jaar blijven ondersteund;
- dezelfde server-side validaties als de legacycode zijn overgenomen;
- jaarcontributies blijven op hele euro's worden opgeslagen;
- de editor toont een pro-rata controletabel;
- oude `formulier=rekentabel` route wordt geblokkeerd.

### Changelog

- nieuwe route `/beheer/changelog.php`;
- eigen regels blijven in `data/changelog.json`;
- vaste ontwikkelaarshistorie uit `changelog-historie.php` blijft zichtbaar en alleen-lezen;
- eigen regels kunnen worden toegevoegd, gewijzigd en verwijderd;
- zoeken en filteren op categorie werkt over eigen én vaste historie;
- oude changelog-POST-routes worden geblokkeerd.

## Gedeelde opslagveiligheid

Voor de nieuwe editors is `app/beheer/editor-hulp.php` toegevoegd. Deze laag centraliseert:

- HTML-escaping;
- veilige lengtebegrenzing;
- JSON-inlezen;
- automatische databack-up vóór overschrijven;
- schrijven via tijdelijk bestand + `rename()`;
- datumconversie en validatie.

Alle muterende editors gebruiken het bestaande globale dataslot en schrijven auditregels naar het bestaande logboek.

`beheer/backup-registry.php` bevat al alle databestanden van de nieuwe modules. Daardoor blijven de nieuwe editors onderdeel van het bestaande herstelmechanisme.

## Feature flags gecorrigeerd

Tijdens de eindcontrole bleek dat een uitgeschakelde module nog als nieuwe `*`-link kon verschijnen doordat de module-outputfilter ná de oude tab-verberglogica draaide. Dat is gecorrigeerd.

De menu-ingangen voor:

- Agenda (`evenementen`);
- Sponsors;
- Media;
- Fotoboek;
- Aanmelden;
- Bedankt;
- Vragen;

respecteren nu expliciet hun feature flag.

Daarnaast vormen `aanmelden`, `bedankt` en `faq` technisch één aanmeldflow. `app/core/module-definities.php` koppelt daarom publieke Aanmelden/Bedankt-routes en alle drie beheeronderdelen aan dezelfde feature flag `aanmelden`.

## Generieke contenteditor gecorrigeerd

Bij de statische eindcontrole is een padfout gevonden die tijdens de eerdere verhuizing naar `app/content/` was blijven staan. `app/content/content-beheer.php` zocht `auth.php` en `data-slot.php` ten onrechte in `app/content/`.

Dit is gecorrigeerd naar de canonieke bestanden:

- root `auth.php`;
- `app/data-slot.php`;
- `app/core/site.php`.

De generieke editor controleert nu bovendien de feature flag van de beheertab voordat een directe editor-URL wordt geopend.

## Reeds praktisch DEV-gevalideerd

Uit de eerdere migratierondes zijn al praktisch gevalideerd:

- Homepage;
- Ontstaan / geschiedenis;
- Baanreglement;
- Fotoboek;
- Gebruikers;
- Logboek;
- Back-ups.

Gebruikers is getest op beperkte rechten, rechtenwijziging, wachtwoordwijziging, opnieuw inloggen en verwijderen. De afgesproken minimale wachtwoordlengte is 10 tekens.

Logboek is getest op bestaande entries, zoeken/filteren en CSV-export.

Back-ups is getest op snapshotweergave, daadwerkelijk herstel, herstel-snapshot en `backup_hersteld` in het logboek.

## Nog één gezamenlijke DEV-acceptatieronde

De code van fase 2 is functioneel compleet, maar de nieuwe/overgezette editors moeten na deze laatste serie commits nog één keer gezamenlijk in de draaiende `/dev`-omgeving worden afgetekend.

Controleer in één ronde:

1. alle gemigreerde menu-items tonen een `*`;
2. Bedankt, Mededeling, Nieuws, Contact, Vragen, Rekentabel en Changelog openen hun zelfstandige editor;
3. Agenda, Sponsors, Media en Aanmelden openen nog steeds hun eerder gebouwde editor;
4. per editor één onschuldige wijziging opslaan en zo nodig meteen terugzetten;
5. publieke weergave controleren waar van toepassing;
6. Logboek controleren op de mutatie;
7. Back-ups controleren op een nieuwe snapshot;
8. Changelog controleren op zichtbare vaste historie en werkend zoek-/categoriefilter;
9. Aanmelden controleren op bestaande veldvalidatie en contributieberekening (een echte aanmelding hoeft niet te worden verzonden tenzij Formspree en automatische ledenopslag bewust meegetest worden).

## Fysieke legacycode in `beheer/index.php`

Veel historische formulier- en handlercode staat nog fysiek in het grote `beheer/index.php`. Die code is niet meer bereikbaar via het menu en de bijbehorende POST-routes worden door de modulaire guards geblokkeerd.

Dit is bewust nog niet massaal uit het 300kB-bestand gesneden: functionele migratie eerst, fysieke reductie daarna. Het in één grote wijziging verwijderen van honderden regels zou nu onnodig regressierisico introduceren. De volgende opschoningsstap kan daardoor puur technisch gebeuren, zonder dat er nog functionaliteit uit legacy hoeft te worden overgezet.

## Status

**Fase 2 is code-technisch/functioneel compleet op `main` van `pjaminon-bit/RC045test`.**

Openstaand vóór definitieve acceptatie: één gezamenlijke DEV-praktijktest van de laatste modulebatch. Daarna kan de fysieke legacycode uit `beheer/index.php` gecontroleerd worden verkleind en kan de template door naar de volgende fase: een tweede fictieve vereniging volledig uit configuratie/data opstarten.
