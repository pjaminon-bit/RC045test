# Fase 2.5.1.1 — groepsbeheer vanuit Leden

Datum: 20 augustus 2026

## Doel
Naast groepsleden beheren vanuit een commissie of werkgroep kan een beheerder nu ook vanuit een individueel lid de groepslidmaatschappen beheren.

## Uitgevoerd
- Nieuwe beheerroute `beheer/lid-groepen.php`.
- Directe ingang `Groepen` vanuit de ledenlijst en `Commissies en werkgroepen` vanuit het formulier van een bestaand lid.
- Actieve commissies en werkgroepen kunnen per lid worden aangevinkt.
- Per groepslidmaatschap kunnen één of meer groepsrollen worden gekozen.
- Rechten blijven gescheiden per groepstype: `committees.manage` en `workgroups.manage`.
- Een gebruiker ziet/wijzigt uitsluitend groepstypen waarvoor hij beheerrecht heeft.
- Gearchiveerde leden tonen alleen historie; groepslidmaatschappen kunnen daar niet meer worden gewijzigd.
- Nieuwe domeinhelper `groepenWerkLidBij()` wijzigt uitsluitend het gekozen lid en laat alle andere groepsleden ongemoeid.
- Toevoegen maakt een nieuwe deelnameperiode met `sinds`; verwijderen sluit de actieve deelname met `tot`.
- Niet-actieve historische rollen blijven bij een bestaande deelname behouden, maar zijn niet nieuw toewijsbaar.
- DEV-smoketest uitgebreid met `beheer/lid-groepen.php`.

## Testdekking
`tests/phase251-groups.php` controleert aanvullend:
- rollen wijzigen vanuit een lid;
- andere groepsleden blijven ongewijzigd;
- verwijderen sluit alleen de deelname van het gekozen lid;
- de leden-gerichte route bestaat en heeft CSRF-controle;
- commissie- en werkgroeprechten worden afzonderlijk afgedwongen;
- Beheer → Leden bevat een directe ingang naar groepsbeheer.

## Deploywerkwijze
Alle wijzigingen worden eerst op `agent/phase2511-member-groups` via een pull request gevalideerd. Er vindt geen tussentijdse `/dev`-deploy plaats. Pas na een volledig groene PR-validatie wordt één keer naar `main` gemerged en daarmee één DEV-deploy gestart.
