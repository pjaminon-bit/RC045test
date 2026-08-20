# Fase 2.5.1 — groepen, rollen en ledenlabels

Datum: 20-08-2026
Repository: `pjaminon-bit/RC045test`
Ontwikkelbranch: `agent/phase251-groups`
PR: #2

## Doel

Fase 2.5.1 breidt de verenigingsadministratie uit met organisatievormen die breed bruikbaar zijn voor clubs en verenigingen, zonder een tweede losstaand commissiesysteem te bouwen.

De kern is één generiek groepenmodel met twee zichtbare typen:

- Commissie — structurele verenigingsgroep.
- Werkgroep — tijdelijke of doelgerichte groep.

Daarnaast zijn gedeelde groepsrollen en ledenlabels/segmenten toegevoegd.

## Groepenmodel

Een groep bevat een vaste technische ID, type, naam, omschrijving, doel, status, start/einddatum en deelnemershistorie.

Statussen:

- `actief`
- `afgerond`
- `gearchiveerd`

Bij `afgerond` en `gearchiveerd` worden actieve deelnemersperioden afgesloten. Als nog geen einddatum aanwezig is, wordt de huidige datum gebruikt. Historische deelnames blijven bewaard.

Groepslidmaatschap bevat:

- vaste `lid_id`
- één of meer rollen
- `sinds`
- `tot`

Verwijderen uit een groep wist dus niet de historie; de actieve periode krijgt een einddatum. Alleen een expliciete privacy-wisactie van een gearchiveerd lid verwijdert de persoonskoppeling uit de groepshistorie.

## Commissies

`beheer/commissies.php` gebruikt voortaan dezelfde groepsservice als Werkgroepen.

Bestaande commissies uit het oude ledenbestand worden als migratiebron ingelezen. Ontbrekende legacycommissies worden aangevuld zolang ze nog niet in de nieuwe private groepenopslag staan. Daardoor is de migratievolgorde veilig: eerst een werkgroep aanmaken kan bestaande commissies niet laten verdwijnen.

Legacyrollen worden vertaald als:

- gewoon commissielid → `lid`
- `hoofd_lid_id` → `trekker`
- `bestuurslid_id` → `bestuurslid`

De beheerpagina kan deze legacygegevens gecontroleerd vastleggen in het nieuwe groepenmodel.

## Werkgroepen

Nieuwe route: `beheer/werkgroepen.php`.

Werkgroepen hebben dezelfde technische fundering als commissies, maar krijgen een eigen feature flag `werkgroepen`. Daarmee kan een tenant deze functionaliteit apart aan- of uitzetten.

## Groepsrollen

Nieuwe route: `beheer/groepsrollen.php`.

Standaardrollen:

- Trekker
- Voorzitter
- Secretaris
- Verantwoordelijk bestuurslid
- Lid

Rollen gelden voor zowel commissies als werkgroepen. Bestaande rolsleutels blijven stabiel; een rol kan worden hernoemd of gedeactiveerd, maar historische records blijven leesbaar. Nieuwe rollen kunnen per vereniging worden toegevoegd.

Capabilities:

- `committees.manage`
- `workgroups.manage`
- `groups.roles.manage`

Voor backwards compatibility impliceert bestaand commissie- of werkgroepbeheer ook `groups.roles.manage`.

## Ledenlabels / segmenten

Nieuwe route: `beheer/ledenlabels.php`.

Labels zijn bedoeld voor administratieve selecties zoals:

- jeugd
- vrijwilliger
- trainer
- wedstrijdteam
- erelid

Een label bevat een vaste technische ID, naam, beschrijving en actiefstatus. Toewijzingen worden los van het persoonsgegevensrecord opgeslagen.

Belangrijk: labels geven nooit automatisch autorisatierechten.

Capability: `member_labels.manage`.

Voor backwards compatibility impliceert bestaand `members.manage` ook labelbeheer.

## Ledenportaal

`/leden/` toont aan een gekoppeld lid uitsluitend:

- de eigen actuele commissie-/werkgroeplidmaatschappen;
- de eigen rol(len) binnen die groepen;
- het doel van de groep;
- de eigen labels.

Het portaal toont geen ledenlijst van de groep of labels van andere leden.

## Privacy en archivering

Normaal archiveren van een lid bewaart historische groepsinformatie.

De gevoelige definitieve privacy-wisactie verwijdert daarnaast:

- groepslidmaatschappen van het lid;
- ledenlabeltoewijzingen;
- de reeds in fase 2.5 gekoppelde financiële, aanmeldings-, vergadering-, taak- en evenementrelaties.

## Opslag en back-ups

Nieuwe private domeinen:

- `groepen`
- `ledenlabels`

JSON/PHP fallbackbestanden:

- `groepen-data.php`
- `ledenlabels-data.php`

Beide lopen via de centrale tenant-aware private-store/repositorylaag en zijn daardoor ook geschikt voor de PDO/PostgreSQL-backend.

Beide bestanden:

- staan in `.gitignore`;
- zijn via `.htaccess` direct over HTTP geblokkeerd;
- zitten in de centrale Back-ups-registry;
- gebruiken de bestaande atomische fallbackwriter.

## Deploydiscipline

2.5.1 is volledig gebouwd op `agent/phase251-groups`.

De branch wordt niet naar `/dev` gedeployd. PR-runs voeren alleen validatie uit. Pas na een volledig groene eindrun wordt PR #2 één keer naar `main` gemerged. De daaropvolgende ene `main`-run valideert opnieuw en mag alleen bij succes naar `/dev` deployen.

De workflow voert vóór iedere main-deploy ook de uitgebreide fase-2.5/2.5.1-testset uit.

## Geautomatiseerde controles

Nieuwe test: `tests/phase251-groups.php`.

Deze controleert onder andere:

- registratie van feature flags, routes en capabilities;
- één generiek groepenmodel voor commissie en werkgroep;
- unieke groeps-/labelstructuur;
- meerdere rollen per lid;
- historische deelnemersperioden;
- afsluiten van deelnames bij afgerond/gearchiveerd;
- migratiepad voor legacycommissies;
- privacy purge van groepen en labels;
- eigen groepen/labels in het ledenportaal;
- private repositories;
- `.gitignore` en `.htaccess`;
- Back-ups-registry;
- capability-guards.

Daarnaast blijven alle bestaande fase-2.5-, PDO- en PHP-linttests actief.

## Status

Code functioneel compleet op staging. Definitieve status wordt pas `AFGEROND` nadat de laatste PR-run groen is, PR #2 naar `main` is gemerged en de daaropvolgende main/DEV-run groen is.
