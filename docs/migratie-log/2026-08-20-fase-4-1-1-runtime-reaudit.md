# Fase 4.1.1 — runtime-isolatie re-audit

Datum: **20-08-2026**

## Aanleiding

Na afronding van fase 4.1 is de Linux/PHP-FPM runtime-laag opnieuw inhoudelijk gecontroleerd vóór de start van fase 4.2. Daarbij zijn twee root-apply randgevallen gevonden die niet door de oorspronkelijke CI werden afgedekt.

## Bevinding 1 — numerieke UID/GID-collision

Het fase-4.1 model gebruikt tenantmetadata als `root:<tenantgroup> 0640/0750` en private data als `<tenantuser>:<tenantgroup>`. Linux autoriseert op numerieke UID/GID, niet op de zichtbare accountnaam. Een vooraf bestaande groep/account met dezelfde GID of UID kon daardoor in theorie de isolatiegrens verzwakken, ook als de deterministische tenantnaam zelf uniek leek.

### Fix

`apply-vps-runtime.php --apply` controleert nu fail-closed via NSS/`getent`:

- tenantgroep bevat geen expliciete groepsleden;
- tenant-GID bestaat niet onder een andere groepsnaam;
- geen andere account gebruikt de tenant-GID als primary group;
- tenant-UID bestaat niet onder een andere accountnaam;
- de volledige passwd/group enumeratie moet controleerbaar zijn, anders wordt apply geweigerd.

De GID-controle gebeurt vóór een eventueel nieuwe tenantuser wordt aangemaakt, zodat een conflict zo vroeg mogelijk zonder onnodige accountmutatie stopt.

## Bevinding 2 — reapply terwijl tenantprocessen actief zijn

Na eerste livegang is `private/` eigendom van de tenant-runtimeuser. Bij een latere recursieve ownership/mode-apply zou een nog actieve PHP-FPM worker gelijktijdig bestanden kunnen creëren of vervangen. De eerdere symlinkscan alleen sloot deze live-mutatie-race niet volledig uit.

### Fix

Vóór iedere filesystemmutatie controleert `--apply` nu `pgrep -u <tenantuser>`:

- exitstatus 1 = expliciet geen actieve processen, apply mag verder;
- actieve processen = fail-closed; eerst tenantpool stoppen;
- ontbrekende/ongeldige procescontrole = fail-closed.

Na apply blijft de bestaande operationele regel gelden: volledige FPM-config testen en pas daarna expliciet starten/reloaden.

## Testdekking

Nieuwe regressietest:

`tests/phase411-runtime-reaudit.php`

Deze bewaakt onder andere:

- volledige NSS-enumeratie;
- expliciete groepsleden geweigerd;
- duplicate GID/group-name geweigerd;
- andere primary-GID account geweigerd;
- duplicate UID geweigerd;
- actieve-processencheck;
- volgorde: identiteit + stilstand vóór filesystemmutaties;
- fail-closed gedrag van procescontrole;
- opname in CI en HTTP-afscherming op DEV.

## Resultaat

Fase 4.1 is na deze re-audit geschikt als broncontract voor fase 4.2. De echte root-apply blijft uitgesteld tot de toekomstige VPS; CI voert nooit account-, ownership- of FPM-rootmutaties uit.
