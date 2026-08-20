# Fase 2.5.2.1 — technische en UI-audit

Datum: 2026-08-20

## Doel
Gerichte nacontrole van fase 2.5.2 op technische randgevallen en visuele consistentie, met nadruk op spacing, padding, afstanden en responsive gedrag.

## Technische bevindingen
- De secundaire beheeritems blijven capabilities en routes behouden; alleen de dashboardpresentatie is gegroepeerd.
- Groepsrelaties blijven tenant-private in het bestaande groepen-document en schrijven onder de bestaande dataslot-lock.
- De Relaties-contextlink werd ook getoond wanneer een gebruiker wel groepen mocht beheren maar geen enkel koppelbaar objectdomein (Taken, Vergaderingen, Evenementen) mocht beheren. Dit is gecorrigeerd.
- De directe route `groep-relaties.php` faalt nu gesloten met HTTP 403 als geen enkel koppelbaar objectrecht aanwezig is.
- De 2.5.2-regressietest controleert voortaan expliciet beide rechtenvoorwaarden en enkele belangrijke responsive/spacing-contracten.

## Layoutbevindingen
Dashboard:
- moduleafstand gestandaardiseerd op 10 px;
- dashboard-card padding naar 20 px;
- moduleklikhoogte minimaal 44 px;
- primaire module padding 12/14 px;
- subnavigatie heeft een vast vertical rhythm en 7 px interne gap;
- page gutters 22 px desktop / 16 px mobiel;
- grid gap 20 px desktop / 14 px mobiel;
- header op mobiel compacter en beter uitgelijnd.

Groepsrelaties:
- page spacing gelijkgetrokken met het dashboardritme;
- cards 20 px padding en 16 px verticale afstand;
- relatiekolommen gebruiken `minmax(0,1fr)` om overflow te voorkomen;
- checkboxregels hebben ruimere klik-/leesafstand;
- select schaalt op mobiel naar 100%;
- mobiele cards en page gutters zijn compacter zonder tegen de rand te komen.

## Bewuste keuze
Geen brede refactor naar één centrale CSS-file in deze audit. De huidige beheerpagina's gebruiken nog inline CSS. Een volledige omzetting raakt veel stabiele routes tegelijk en levert voor deze spacing-correctie meer regressierisico dan voordeel op. Een centrale beheer-UI stylesheet kan later als afzonderlijke, gecontroleerde UX-refactor worden uitgevoerd.

## Deploydiscipline
Alle wijzigingen zijn uitgevoerd op `agent/phase2521-ui-hardening`. Geen DEV-deploy vóór een volledig groene PR-validatie; daarna maximaal één merge naar `main`.
