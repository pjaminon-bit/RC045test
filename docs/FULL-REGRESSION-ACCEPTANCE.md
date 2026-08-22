# Volledige regressie- en eindacceptatie

Datum: 22 augustus 2026

## Doel

Deze acceptatieronde controleert het volledige verenigingsplatform van voor tot achter: security, functioneel, technisch en optisch. Een groene bestaande CI-run is niet als voldoende bewijs geaccepteerd. Ontbrekende testlagen zijn toegevoegd, concrete live-bevindingen zijn als productfouten behandeld en na iedere fix is de regressiematrix opnieuw uitgevoerd.

## Bevroren VPS-kandidaat

Na de productfixes, permanente testborging en authenticated E2E-uitbreiding is de definitieve pre-VPS kandidaat op `main`:

`936cf4879f1611d94123fb3d3a0a33b831a49810`

Deze commit is het uitgangspunt voor fase 5.3. Wijzig hem niet voor de eerste VPS-proef tenzij een echte blocker wordt gevonden; een wijziging vereist opnieuw de relevante acceptatiegate en een nieuw vastgelegde kandidaat-SHA.

## Definitief eindbewijs

De finale gecombineerde pre-VPS gate heeft achter elkaar bewezen:

- volledige source/functionele/technische/security-regressie: **groen**;
- actieve live DEV-security: **groen**;
- publieke Playwright-browseracceptatie: **20/20 groen** op desktop, tablet en mobiel;
- authenticated beheer- en ledenacceptatie: **groen**;
- synthetisch gekoppeld testlid met portaldata: **groen**;
- restore/cleanup van alle tijdelijk gewijzigde DEV-auth- en ledenbestanden: **groen**.

De test-only eindgate is vastgelegd bij issue #39. Een eerste publieke mobile pass had gelijktijdig `ERR_HTTP2_PROTOCOL_ERROR` op vijf sponsorafbeeldingen terwijl dezelfde assets op desktop/tablet groen waren. Exact dezelfde browserjob is zonder codewijziging herhaald en eindigde 20/20 groen. Dit is daarom als niet-reproduceerbare transportstoring geclassificeerd en niet als productregressie.

## Bron-, architectuur- en technische regressie

De permanente `tests/run-all.sh` voert alle PHP-regressietests in `tests/` uit. Daardoor kan een nieuwe test niet stil buiten de workflowlijst vallen. Onder meer zijn opnieuw bewezen:

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

## Publieke functionele en optische browseracceptatie

Playwright controleert onder meer:

- `/`
- `/ontstaan.html`
- `/baanreglement.html`
- `/aanmelden.html`
- `/beheer/`
- `/leden/`

Per hoofdroute worden desktop 1440×1000, tablet 820×1180 en mobiel 390×844 gecontroleerd op succesvolle responses, titel/taal/H1, zichtbare hoofdcontent, overflow, kapotte same-origin assets, labels, bedieningselementen, scrollanimaties, JavaScript-errors, mislukte requests, HTTP 4xx/5xx subrequests en `console.error`. Full-page screenshots worden pas gemaakt nadat de pagina echt volledig is gescrold.

Daarnaast worden zichtbare interne publiekslinks gecrawld en controleert de test de native browservalidatie en POST-semantiek van het publieke aanmeldformulier.

## Authenticated live E2E

De ontbrekende authenticated bewijslaag is op 22 augustus 2026 alsnog volledig toegevoegd en groen uitgevoerd.

De workflow gebruikt **geen vaste echte gebruikerscredentials**. Per run worden willekeurige tijdelijke accounts gemaakt; secrets worden gemaskeerd. Via de bestaande DEV-SFTP-koppeling worden de benodigde auth-/fixturebestanden eerst veiliggesteld, tijdelijk aangepast en in een `always()` cleanup exact teruggezet.

Bewezen zijn:

- beheerlogin en logout;
- beheerlogin op desktop en mobiel;
- sessiecookie met `HttpOnly`, `Secure` en `SameSite=Lax`;
- beheerder heeft toegang tot leden- en gebruikersbeheer;
- beperkt ledenaccount krijgt geen beheerrechten en ontvangt 403 op gebruikersbeheer;
- ledenlogin op desktop en mobiel;
- gekoppeld synthetisch testlid toont persoonsgegevens;
- contributiegegevens worden correct getoond;
- commissie/groepsinformatie wordt correct getoond;
- ledenvergadering, agenda en definitieve notulen worden correct getoond;
- toegewezen taak wordt correct getoond;
- ingelogde beheer- en ledenroutes zijn op desktop, tablet en mobiel vastgelegd en visueel gecontroleerd;
- alle tijdelijk gewijzigde DEV auth- en ledenbestanden zijn na afloop exact hersteld.

Issue #50 is na dit bewijs gesloten.

## Bevindingen die tijdens deze ronde zijn opgelost

De regressieronde heeft daadwerkelijk productfouten gevonden. Hersteld zijn onder andere:

- `X-Powered-By` lekte PHP-runtimeinformatie; opgelost met Apache defense-in-depth plus `expose_php = Off`;
- ontbrekende/ongeldige standalone content-overrides veroorzaakten browser-404/500-fouten; standalone DEV gebruikt nu veilig de ingebouwde defaults, externe tenants blijven fail-closed;
- template-/runtimeafbeeldingen ontbraken op DEV; DEV gebruikt nu een lokale deterministische neutrale placeholder zonder afhankelijkheid van productie-assets;
- tablet/mobile navigatie en grid-min-content konden horizontale overflow veroorzaken;
- carousel-/kopieerbediening had te kleine targets;
- `landcode` miste een toegankelijke naam;
- verplichte aanmeldvelden misten native `required`-semantiek;
- de leden-loginbutton was live te klein;
- de eerste screenshotmethodiek activeerde scrollgebonden animaties niet; de permanente browsertest scrollt nu de hele pagina en controleert expliciet op achtergebleven onzichtbare content.

## Handmatige visuele controle

De finale screenshots zijn handmatig bekeken. Publieke pagina's, beheer en ledenportaal schalen correct op desktop, tablet en mobiel. Het synthetische lid toont de verwachte portalinformatie zonder horizontale overflow of kapotte layout. DEV gebruikt bewust neutrale placeholders waar tenant-/historische foto-assets niet als broncode worden meegepackageerd; dit is zichtbaar maar geen kapotte assetstatus.

## VPS-grens

Deze regressie bewijst de code en de huidige DEV-hosting. Fase 5.3 blijft apart verantwoordelijk voor validatie van dezelfde provisioning-, security-, monitoring-, lifecycle-, export/herstel- en rollbackketen op een echte schone VPS. Een groene DEV-regressie wordt niet gelijkgesteld aan echte VPS-validatie.

Voor fase 5.3 wordt aanvullend geëist dat een succesvolle export niet alleen een geldige SHA-256 heeft, maar ook daadwerkelijk naar een wegwerp-herstelomgeving wordt teruggezet en gecontroleerd. Daarmee wordt herstelbaarheid bewezen in plaats van alleen exporteerbaarheid.
