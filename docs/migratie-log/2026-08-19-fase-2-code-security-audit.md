# Fase 2 — code- en security-audit / afsluiting

Datum: 2026-08-19  
Repository: **`pjaminon-bit/RC045test`**  
Branch: **`main`**

> Deze audit betreft uitsluitend `RC045test`. De productie-repository `RC045` is niet gewijzigd.

## Eindoordeel

**Fase 2 kan technisch worden afgesloten.**

Na de audit zijn geen bekende kritieke of hoge kwetsbaarheden in de fase-2 beheerarchitectuur blijven staan. De tijdens de audit gevonden relevante rechten-, opslag- en hardeningproblemen zijn vóór afsluiting op `main` hersteld.

De beheerlaag heeft nu één canonieke runtime (`app/beheer/*`), alle zichtbare beheeronderdelen staan in de module-registry op `status = module`, de afgesproken `*`-markering is in DEV visueel bevestigd en gevoelige systeemfuncties gebruiken expliciete rechten.

## Scope van de audit

Gecontroleerd zijn:

- authenticatie en sessiebeveiliging;
- CSRF op muterende beheeracties;
- rechtencontrole op directe module-URL's;
- expliciete rechten voor gevoelige functies;
- feature flags en directe URL-guards;
- legacy POST-guards;
- output escaping en stored-XSS-risico's;
- JSON-opslag, centrale data-lock en automatische back-ups;
- auditlogging;
- back-upherstel en padvalidatie;
- bestandsuploads van Sponsors en Fotoboek;
- CSV-export van Logboek;
- server-only bestanden en `.htaccess`;
- dubbele/oude beheer-runtimepaden;
- publieke verwerking van beheerde Nieuws-, FAQ- en Bedankt-content;
- deployworkflow en PHP-lintconfiguratie.

## Beveiliging die al correct was

### Authenticatie en sessies

- sessiecookies zijn `Secure`, `HttpOnly` en `SameSite=Lax`;
- `session.use_strict_mode` is ingeschakeld;
- na succesvolle login wordt `session_regenerate_id(true)` uitgevoerd;
- logout is POST + CSRF en verwijdert ook de sessiecookie;
- login-lockout geldt per account én per gehashte bron-IP;
- de lockout faalt gesloten wanneer zijn opslag niet veilig gelockt kan worden;
- wachtwoorden van gewone beheeraccounts worden uitsluitend als `password_hash()` opgeslagen;
- gebruikers hebben een `sessie_versie`; rechten-, wachtwoord-, blokkeer- en verwijderacties kunnen bestaande sessies direct ongeldig maken;
- een verwijderd of geblokkeerd account verliest bij het volgende verzoek toegang.

### Rechten

- iedere zelfstandige beheereditor controleert server-side of de gebruiker de bijbehorende beheerfunctie mag openen;
- `Gebruikers` en `Back-ups` vereisten al expliciete gevoelige rechten;
- de master blijft de enige impliciete uitzondering op expliciete rechten;
- feature flags staan boven gebruikersrechten: een voor de vereniging uitgeschakelde module is ook voor de master niet te muteren.

### CSRF

Alle gecontroleerde muterende fase-2 routes vereisen het sessiegebonden CSRF-token voordat data wordt gewijzigd.

### Back-ups

De zelfstandige Back-ups-module:

- gebruikt een vaste registry van herstelbare bestanden;
- accepteert geen vrij doelpad;
- controleert de gekozen snapshot met `basename()` en `realpath()`;
- vereist letterlijk `HERSTEL`;
- parseert JSON vóór herstel;
- maakt vóór herstel opnieuw een snapshot van de huidige versie;
- gebruikt het globale dataslot;
- logt succesvolle herstelacties.

### Uploads

Sponsors:

- maximaal 1 MB;
- `getimagesize()`-controle;
- alleen PNG, JPEG en WEBP;
- servergegenereerde bestandsnaam;
- geen door de gebruiker gekozen uitvoerpad of PHP-extensie.

Fotoboek:

- albumslugs worden genormaliseerd;
- bestaande bestandsnamen uit formulieren worden met `basename()` beperkt;
- nieuwe foto's worden server-side opnieuw als JPEG opgebouwd;
- expliciet te verwijderen bestanden worden pas na een geslaagde metadata-write verwijderd;
- nieuwe bestanden zonder geslaagde metadata-write worden opgeruimd.

### Output / stored XSS

De beheereditors escapen opgeslagen tekst vóór HTML-output. De gecontroleerde publieke renderers voor Nieuws, FAQ en Bedankt plaatsen beheerbare teksten via DOM `textContent`; ingevoerde HTML wordt daardoor tekst en geen uitvoerbare markup.

## Tijdens de audit gevonden en hersteld

### 1. Logboekrecht was niet strikt genoeg — hersteld

**Classificatie vóór fix: middel.**

`Logboek` stond centraal als gevoelig recht, maar de zelfstandige route gebruikte nog `authRechten()`. Voor zeer oude accounts zonder opgeslagen `tabs` kent die functie bewust een brede compatibiliteitsfallback.

Daardoor kon zo'n account theoretisch auditgegevens openen zonder expliciet `log`-recht.

Hersteld:

- `/beheer/logboek.php` gebruikt nu `authHeeftExplicietRecht('log')`;
- de menu-module gebruikt dezelfde expliciete controle;
- zonder expliciet recht wordt de Logboek-link verwijderd en directe toegang geeft HTTP 403.

### 2. Centraal dataslot wees na mapmigratie naar verkeerde map — hersteld

**Classificatie: middel voor data-integriteit.**

`app/data-slot.php` gebruikte na de verhuizing nog `__DIR__/data-backups`, wat neerkwam op `app/data-backups`. De bedoelde server-only lockmap is `data-backups` in de projectroot.

Hersteld naar:

- projectroot `/data-backups/.data.lock`.

Daarmee gebruiken beheer, gebruikersdata en overige opslagacties opnieuw één gedeeld slot op de bedoelde locatie.

### 3. Auditlog had read-modify-write race — hersteld

**Classificatie: middel voor audit-integriteit.**

De oude `schrijfLog()` las het hele logboek vóór de `LOCK_EX` van `file_put_contents()`. Twee gelijktijdige requests konden daardoor dezelfde oude versie lezen en één auditregel verliezen.

Hersteld:

- logbestand wordt met `fopen('c+')` geopend;
- `flock(LOCK_EX)` wordt vóór lezen genomen;
- lezen, retentie, truncate, write en `fflush()` gebeuren onder hetzelfde slot;
- pas daarna wordt de lock vrijgegeven.

### 4. Gebruikersbestand niet atomisch geschreven — hersteld

**Classificatie: middel voor autorisatie-integriteit.**

`beheer-users.json` is beveiligingskritisch. Het bestand werd wel onder de globale lock en met back-up geschreven, maar de uiteindelijke write was rechtstreeks.

Hersteld:

- JSON eerst volledig encoden;
- schrijven naar tijdelijk bestand;
- daarna atomisch `rename()` naar `beheer-users.json`;
- bestaande automatische back-up blijft behouden.

### 5. Back-upnaam kon bij twee writes in dezelfde seconde botsen — hersteld

**Classificatie: laag.**

Snapshots gebruikten alleen een tijdstempel tot op seconden. Twee snelle writes naar hetzelfde bestand konden dezelfde snapshotnaam opleveren.

Hersteld:

- back-upnamen bevatten nu ook microseconden;
- bestaande glob-/retentiecode blijft compatibel.

### 6. CSV-formule-injectie in Logboek-export — hersteld

**Classificatie: middel bij openen van export in spreadsheetsoftware.**

Auditdetails kunnen beheerde tekst bevatten. Een cel die begint met bijvoorbeeld `=`, `+`, `-` of `@` kan door spreadsheetsoftware als formule worden geïnterpreteerd.

Hersteld:

- CSV-export neutraliseert formulegevoelige begintekens met een apostrof;
- HTML-weergave was al correct ge-escaped.

### 7. Interne beheerhelpers rechtstreeks bereikbaar — gehard

**Classificatie: laag / defense in depth.**

`beheer/backup-registry.php`, `beheer/gebruikers-rechten.php` en `beheer/fotoboek-lib.php` zijn include-bestanden en geen zelfstandige webpagina's.

`.htaccess` blokkeert deze paden nu rechtstreeks met HTTP 403. Server-side `require` blijft gewoon werken.

### 8. Fotoboek kon extreem grote pixelafbeeldingen decoderen — hersteld

**Classificatie: middel voor beschikbaarheid.**

Een klein gecomprimeerd beeldbestand kan extreem veel pixels bevatten en bij GD-decodering veel geheugen vragen. Alleen een limiet op bestandsgrootte is daartegen niet voldoende.

Hersteld:

- vóór `imagecreatefrom*()` wordt de resolutie gecontroleerd;
- maximaal 16.000 pixels per dimensie;
- maximaal 60 megapixel totaal;
- ongeldige of te grote afbeeldingen worden vóór volledige decodering geweigerd.

Daarnaast valideert de Fotoboek-datumfunctie nu ook ISO-datums met `checkdate()` en wordt fotoboekmetadata atomisch geschreven.

### 9. Generieke contenteditor schreef nog rechtstreeks — hersteld

Homepage, Aanmelden en overige generieke contentpagina's gebruiken nu dezelfde gedeelde atomische JSON-writer als de nieuwe fase-2 editors: back-up, tijdelijk bestand en `rename()`.

## Lage resterende technische schuld

Deze punten blokkeren fase 2 niet, maar moeten worden meegenomen in de verdere template-/tenantfase:

1. **Agenda, Sponsors en Media** hebben nog hun oorspronkelijke module-specifieke JSON-writer met `file_put_contents(..., LOCK_EX)`. Ze draaien wél onder het globale dataslot en maken vooraf een snapshot. Het risico is daardoor beperkt tot robuustheid bij een zeer ongelukkige proces-/filesystemfout; dit is geen autorisatielek. Later uniformeren op `beheerEditorSchrijfJson()`.
2. **Master-wachtwoord compatibility fallback.** `auth.php` ondersteunt nog een server-only plaintext `$BEHEER_WACHTWOORD` voor oude installaties wanneer geen geldige hash staat. Zodra iedere tenant via provisioning wordt aangemaakt moet alleen `$BEHEER_WACHTWOORD_HASH` worden toegestaan. De Git-repository bevat het echte serverconfigbestand niet, dus deze audit kan de actuele DEV-serverwaarde niet zien.
3. **Additieve SFTP-deploy.** Verwijderde files kunnen op Strato fysiek achterblijven. Gevoelige oude beheer-runtimepaden zijn daarom expliciet via `.htaccess` geblokkeerd. Op de toekomstige VPS heeft een release/deploy die ook verwijderingen synchroniseert de voorkeur.
4. **Fysieke legacycode in `beheer/index.php`.** De oude formulieren/handlers zijn runtime-dood door de moduleguards, maar staan nog deels in het grote bestand. Dat is onderhoudsschuld, geen actieve beheerroute. Verkleinen kan als aparte mechanische cleanup na fase 2.
5. **Hardcoded `rc045.nl` in de huidige HTTPS-redirect.** Voor RC045test klopt dit; voor echte multi-tenant provisioning moet de webserver-/tenantconfig dit overnemen. Dit hoort bij de VPS/tenantfase.

## Deploy-/lintstatus

De workflow `.github/workflows/deploy-dev.yml` deployt `RC045test/main` naar `/dev` en voert vóór upload `php -l` uit op alle PHP-bestanden, gevolgd door HTTP-smoketests.

De gebruikte GitHub-connector toont voor de laatste push geen afzonderlijke commit-statuscontext, waardoor in deze audit niet onafhankelijk uit de API kan worden bevestigd welke push-run op dat moment al voltooid was. Dit rapport claimt daarom niet ten onrechte een specifieke groene Actions-run. De lint- en smoketeststappen zelf staan wel als verplichte stappen in de workflow.

## Acceptatiebewijs uit DEV

In de lopende DEV-omgeving is visueel bevestigd dat alle zichtbare fase-2 beheeronderdelen de afgesproken `*`-markering tonen. Eerder zijn bovendien praktisch getest:

- Gebruikers: beperkt account, rechten wijzigen, wachtwoord wijzigen, opnieuw inloggen en verwijderen;
- Logboek: entries, filters/zoeken en CSV-export;
- Back-ups: snapshots, werkelijk herstel, herstel-snapshot en `backup_hersteld`-auditregel.

## Afsluiting

**Status fase 2: AFGESLOTEN op `main` van `pjaminon-bit/RC045test`.**

De volgende ontwikkelfase begint niet met nog meer RC045-specifieke beheerrefactors, maar met het bewijzen van de tenantarchitectuur: een tweede fictieve vereniging op dezelfde gedeelde code, met eigen configuratie, identiteit, data en uploads zonder wijzigingen in de gedeelde applicatiecode.
