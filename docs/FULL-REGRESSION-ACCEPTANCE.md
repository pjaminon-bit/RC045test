# Volledige regressie- en eindacceptatie

Datum: 22 augustus 2026

## Doel

Deze acceptatieronde is uitgevoerd naar aanleiding van de eis om het volledige verenigingsplatform van voor tot achter opnieuw te controleren: security, functioneel, technisch en optisch. Een groene bestaande CI-run is daarbij niet als voldoende bewijs geaccepteerd. Ontbrekende testlagen zijn toegevoegd, concrete live-bevindingen zijn als productfouten behandeld en na iedere fix is de totale regressiematrix opnieuw uitgevoerd.

## Geteste productiebron

De finale productfixes zijn gemerged naar `main` in commit:

`c52222f26195df54f43e619255c2671577991778`

De tijdelijke finale acceptatiebranch wijzigde daarna uitsluitend het testharnas, niet de productcode. De strengste eindmeting gebruikte test-head:

`053548d3fe5d1866248f10c44a361d35420f5fdf`

## Eindbewijs

- Validate and deploy RC045test #499: **success**
- Full regression acceptance #45: **success**
- Final live DEV acceptance #3: **success**
- Live securityjob: **success**
- Live Playwright browseracceptatie: **20/20 tests geslaagd**
- 18 full-page screenshots: zes hoofdroute-types op desktop, tablet en mobiel
- Screenshots na volledige scroll handmatig gecontroleerd zodat scroll-/IntersectionObserver-animaties daadwerkelijk geactiveerd waren

## Bron-, architectuur- en technische regressie

De permanente `tests/run-all.sh` voert alle PHP-regressietests in `tests/` uit. Daardoor kan een nieuwe test niet meer stil buiten de expliciete workflowlijst vallen. Onder meer zijn opnieuw bewezen:

- tenantgrenzen en tenantconfiguratie;
- filesystem- en PDO-opslagisolatie;
- auth-, sessie- en master-sessionbinding;
- capability/autorisatielogica;
- ledenadministratie, groepen, labels en domeinintegriteit;
- provisioning en tweede-tenantisolatie;
- runtime/FPM-isolatie;
- Apache/vhostconfiguratie;
- DNS-readiness;
- TLS/HTTPS;
- PostgreSQL-provisioning;
- monitoring en logging;
- immutable releases en rollback;
- lifecycle suspend/activate/export/delete/purge/recovery;
- control-plane identity, authenticatie, rate limiting en executorresultaten;
- first-VPS bootstrap en production-hardening;
- subprocess/PATH/deadlock-hardening;
- statische source-securityregels;
- volledige PHP- en JavaScript-syntaxcontrole.

## Actieve live securityacceptatie op DEV

De live test tegen `https://rc045.nl/dev` bewijst onder meer:

- HSTS aanwezig;
- `X-Content-Type-Options: nosniff` aanwezig;
- Referrer-Policy aanwezig;
- clickjackingbescherming aanwezig;
- geen `X-Powered-By` PHP-runtimelek;
- TRACE geblokkeerd;
- server-only `tests/`, `app/deployment/`, `bin/` en `dev-build.json` niet publiek uitleesbaar;
- traversal- en dubbel-gecodeerde traversalrequests op publieke content/assets fail-closed;
- externe URL-injectie in assetgateway geweigerd;
- XSS-canary niet als actieve scriptmarkup gereflecteerd;
- geen CRLF-headerinjectie via query-invoer;
- cookies van beheer- en ledenlogin op beveiligingsattributen gecontroleerd.

## Functionele en optische browseracceptatie

Playwright controleert de volgende routes:

- `/`
- `/ontstaan.html`
- `/baanreglement.html`
- `/aanmelden.html`
- `/beheer/`
- `/leden/`

Iedere route wordt gecontroleerd op:

- desktop 1440×1000;
- tablet 820×1180;
- mobiel 390×844;
- succesvolle hoofdresponse;
- correcte documenttitel, taal en H1;
- voldoende zichtbare hoofdcontent;
- geen horizontale overflow;
- geen kapotte same-origin afbeeldingen;
- geen ongelabelde zichtbare formuliervelden;
- geen extreem kleine niet-inline bedieningselementen;
- geen inhoud die na een volledige echte scroll door scrollanimaties onzichtbaar blijft;
- geen JavaScript page errors;
- geen mislukte same-origin requests;
- geen same-origin HTTP 4xx/5xx subrequests;
- geen `console.error`;
- full-page screenshot na het activeren van scrollgebonden content.

Daarnaast worden zichtbare interne publiekslinks gecrawld en controleert de test de native browservalidatie en POST-semantiek van het publieke aanmeldformulier.

## Bevindingen die tijdens deze ronde zijn opgelost

De regressieronde heeft daadwerkelijk productfouten gevonden en niet alleen bestaande groene tests herhaald. Hersteld zijn onder andere:

- `X-Powered-By` lekte PHP-runtimeinformatie; opgelost met Apache defense-in-depth plus `expose_php = Off`;
- ontbrekende/ongeldige standalone content-overrides veroorzaakten browser-404/500-fouten; standalone DEV gebruikt nu veilig de ingebouwde defaults, externe tenants blijven fail-closed;
- template-/runtimeafbeeldingen ontbraken op DEV; DEV gebruikt nu een lokale deterministische neutrale placeholder zonder afhankelijkheid van productie-assets;
- tablet/mobile navigatie en grid-min-content konden horizontale overflow veroorzaken;
- carousel-/kopieerbediening had te kleine targets;
- `landcode` miste een toegankelijke naam;
- verplichte aanmeldvelden misten native `required`-semantiek;
- de leden-loginbutton was live te klein;
- de eerste screenshotmethodiek activeerde scrollgebonden animaties niet, waardoor grote lege vlakken zichtbaar waren; de permanente browsertest scrollt nu de hele pagina en controleert expliciet op achtergebleven onzichtbare content.

## Handmatige visuele controle

De finale, na scroll gemaakte screenshots zijn handmatig bekeken. De homepage is op desktop, tablet en mobiel volledig gevuld zonder de eerdere lege animatievlakken. Het aanmeldformulier is bruikbaar op mobiel, de beheerlogin schaalt correct en de ledenlogin past zonder horizontale overflow. DEV gebruikt bewust neutrale placeholders waar tenant-/historische foto-assets niet als broncode worden meegepackageerd; dit is zichtbaar maar geen kapotte assetstatus.

## Nog niet als live authenticated E2E bewezen

De bron/functionele suites testen de achterliggende beheer-, autorisatie-, leden- en domeinlogica uitgebreid. De live browserlaag heeft echter **geen geldige DEV-beheerder- of ledencredentials** in de repository of het testharnas en heeft daarom alleen de niet-ingelogde beheer- en ledenloginpagina's optisch/live getest.

Daarom wordt niet beweerd dat iedere ingelogde beheerpagina en iedere ledenportaalactie al als echte browsergebruiker op DEV is doorgeklikt. Voor een werkelijk volledige authenticated live E2E-laag is een expliciet tijdelijk DEV-testaccount of een veilig Actions-secret met testcredentials nodig. Dit is de enige bekende ontbrekende bewijslaag binnen de huidige DEV-acceptatie; het is geen bekende productfout.

## VPS-grens

Deze regressie bewijst code en de huidige DEV-hosting. Fase 5.3 blijft apart verantwoordelijk voor validatie van dezelfde provisioning-, security-, monitoring-, lifecycle- en rollbackketen op een echte schone VPS. Een groene DEV-regressie wordt niet gelijkgesteld aan echte VPS-validatie.
