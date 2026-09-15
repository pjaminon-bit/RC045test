# Platformbeheer: nieuwe vereniging — fase 5.7 + 5.9

Fase 5.7 voegt aan het bestaande VPS-platformbeheer de actie **Nieuwe vereniging** toe. De browser kan de tenantbasis laten aanmaken, maar kan geen rootcommando's, serverpaden of beheerderswachtwoorden aanleveren.

Fase 5.9 bouwt hierop voort met één centrale, hervatbare onboardingroute. Die route hergebruikt de bestaande deployment-, runtime-, database-, webserver-, DNS-, TLS-, monitoring- en lifecyclecontracten. Sinds #319 betekent **infrastructuur actief** nadrukkelijk niet meer automatisch **pilot gereed**: na technische acceptatie en lifecycle-adoptie blijft een afzonderlijk, blokkerend pilot-readinesscheckpoint open.

## Wat de GUI aanmaakt

De platformoperator vult in:

- verenigingsnaam;
- permanente technische tenant-key;
- canonieke productiehost;
- actieve platformmodules.

`Website` is altijd verplicht. De overige modulekeuzes komen uit dezelfde vaste platformlijst als `bin/provision-tenant.php`.

De aanvraag wordt als een strikt `5.1-request` met actie `provision` in de bestaande control-plane queue geplaatst. De queue bevat alleen functionele metadata. Er staan geen wachtwoorden, secrets, shellcommando's, argv-fragmenten of door de browser gekozen filesystempaden in.

## Securitygrens

De bestaande fase-5.1 grens blijft intact:

1. de webapp draait als niet-root `vst-control`;
2. mutaties vereisen de bestaande operatorbinding en CSRF-controle;
3. de webapp schrijft uitsluitend een exclusief queuebestand;
4. de root-executor valideert request-schema, leeftijd, operator, tenant-key, host, naam en moduleallowlist opnieuw;
5. onbekende top-level of provisioningvelden worden geweigerd;
6. tenantroot, PDO-profiel, HTTPS-URL en provisionerscript worden server-side bepaald;
7. de executor controleert opnieuw dat tenant-key en host nog vrij zijn;
8. eerst draait een `--dry-run`; pas daarna mag de bestaande CLI-provisioner muteren;
9. de provisioner draait met dezelfde exact gepinde productie-PHP-binary als de executor;
10. CLI-output met serverpaden wordt niet teruggegeven aan de browser.

De executor blijft shellvrij en gebruikt de bestaande `process521Run()` met argument-arrays en `bypass_shell`.

## Resultaat en status

Na succesvolle basisprovisioning bestaan de normale tenantartifacts onder de server-side tenantroot, waaronder:

- `config.php`;
- `runtime.env`;
- `tenant.json`;
- private opslag;
- neutrale publieke startcontent.

Nieuwe VPS-tenants worden met `--driver=pdo` voorbereid. Zolang nog geen fase-4.8 lifecycle-plan bestaat, neemt de root-generated control-plane snapshot de tenant op met status:

`setup_required` → **Installatie afronden**

Ook een canonieke tenantdirectory met onvolledige of ongeldige provisioningmetadata wordt niet stil genegeerd, maar als **Controle nodig** (`invalid`) zichtbaar gemaakt.

Actieve tenants die al vóór fase 5.9 op de VPS gevalideerd waren en geen fase-5.9 onboardingstate hebben, blijven onder het historische `pre-5.9`-contract zichtbaar. Zij worden niet stil als nieuwe #319-pilot geherclassificeerd.

## Eerste beheerder blijft een aparte secretstap

De bestaande productiebootstrap heeft bewust een andere securitygrens voor credentials: het eerste tenantbeheerwachtwoord wordt via `bootstrap-tenant-admin.php` uit een interactieve of STDIN-secretstroom gelezen. Wachtwoorden horen niet in argv, environment, Git, logs of de control-plane queue.

Na basisprovisioning wordt daarom één server-side stap uitgevoerd:

```bash
sudo php /srv/verenigingsplatform/current/bin/bootstrap-tenant-admin.php \
  --config=/srv/verenigingen/<tenant>/config.php
```

Daarna kan de operator de onboardingwizard hervatten. In de infrastructuurfase verstuurt de browser alleen het publieke DNS-profiel; providercredentials en het tenantbeheerwachtwoord blijven buiten de queue.

## Centrale fase-5.9 route

De root-executor gebruikt vaste checkpoints:

`start → plans_ready → runtime_applied → database_applied → fpm_active → dns_ready → tls_active → monitoring_active → acceptance_ready → lifecycle_active → pilot_ready → complete`

De flow hergebruikt de bestaande fase-3.5/4.x scripts. DNS is bewust het normale externe wachtpunt tijdens de infrastructuurfase: wanneer de publieke records nog niet conform het vastgelegde profiel zijn, stopt de aanvraag veilig na voorbereiding en kan dezelfde onboarding later worden hervat.

### Technische pilotacceptatie vóór lifecycle-adoptie

`acceptance_ready` is een verplichte fail-closed gate. Voordat lifecycle-adoptie wordt uitgevoerd controleert de executor minimaal:

- de eerste beheerder bestaat server-side;
- `tenant.json` is geldig, aan dezelfde tenant-key en canonieke HTTPS-host gebonden en bevat de verplichte module `website`;
- de tenant heeft geldige publieke homepagecontent;
- het actuele lifecycleplan is byte-exact valide en behoudt de bestaande root-only/export-before-delete veiligheidsgrenzen;
- een verse volledige `check-vps-health.php --probe --write-status` slaagt;
- de echte canonieke HTTPS-root (`https://<tenant>/`) geeft via een lokale `--resolve`-probe HTTP 200.

Daarna schrijft de control-plane uitsluitend geschoond technisch bewijs in de root-owned onboardingstate: tenant-key, host, timestamp, groene checkcodes, moduleprofiel en SHA-256-bindingen van tenantmanifest, monitoringplan en lifecycleplan. Er worden geen passwords, hashes uit authbestanden, PII, providerresponses of andere secrets opgenomen.

Een oudere fase-5.9 state die `lifecycle_active`, `pilot_ready` of `complete` bevat maar geen geldig technisch acceptatiebewijs heeft, valt fail-closed terug tot vóór die grens.

## Blokkerend pilot-readinesscheckpoint

Na `lifecycle_active` stopt de eerste onboardingrun bewust. De vereniging is dan technisch actief, maar nog **niet** als pilot-gereed gemarkeerd. De snapshot en wizard tonen afzonderlijk:

- `Technische pilotacceptatie`;
- `Lifecycle actief`;
- `Content & branding gereed`;
- `Export & herstel bewezen`;
- `Pilot gereed`.

Voor `pilot_ready` en daarna `complete` gelden aanvullend alle volgende voorwaarden:

1. de tenant staat stabiel `active` en heeft geen open lifecycle-transition;
2. de homepage is aantoonbaar niet meer byte-/inhoudelijk gelijk aan de neutrale provisioninghomepage;
3. de operator bevestigt expliciet dat tenant-eigen content en branding voor de pilot zijn beoordeeld;
4. na de technische acceptatie is een nieuwe, geverifieerde lifecycle-export gemaakt;
5. exact die actuele export is volgens de bestaande fase-5.3 procedure in een geïsoleerde wegwerp-herstelomgeving teruggezet en inhoudelijk gecontroleerd;
6. de operator legt uitsluitend de SHA-256 van de herstelde database en van de herstelde tenantbestanden/configuratie vast;
7. de root-executor bindt dit bewijs aan de actuele lifecycle-export, het technische acceptatiebewijs en de huidige homepage;
8. direct vóór completion slagen opnieuw de volledige healthprobe en de canonieke HTTPS-rootprobe.

De wizard gebruikt hiervoor dezelfde bestaande actie `onboarding-resume`, maar met een strikt ander payloadschema voor een reeds actieve tenant. De browser kan in deze fase uitsluitend twee booleans en twee 64-hex SHA-256-waarden aanbieden. DNS-velden, vrije tekst, shellargumenten, secrets en backupinhoud worden in deze fase geweigerd.

Een state die al `complete` zegt maar geen geldig, tenantgebonden `5.9-pilot-readiness`-bewijs bevat, wordt bij lezen teruggebracht naar `lifecycle_active`. `complete` betekent daardoor binnen dit contract werkelijk dat ook content/branding en recovery aantoonbaar zijn afgehandeld.

## Backup en recovery

Fase 5.9 introduceert **geen tweede backupformaat en geen nieuwe restore-engine**. De bestaande lifecycle-export blijft de bron van waarheid. De daadwerkelijke restore blijft de eerder bewezen operationele fase-5.3 procedure uit `docs/VPS-READINESS.md`, `docs/VPS-LIFECYCLE.md` en `docs/migratie-log/2026-08-28-fase-5-3-vps-acceptatie.md`.

De control-plane automatiseert alleen de readinessgrens rond dat bestaande proces: hij eist een export ná technische acceptatie, bindt het operatorbewijs aan die actuele export en weigert completion zonder twee herstelhashes en een opnieuw gezonde actieve tenant.

`purge` is geen onderdeel van deze pilotacceptatie. Een wegwerpherstelomgeving wordt buiten de canonieke tenant hersteld en na bewijs veilig opgeruimd.

## Regressiecontract

`tests/phase57-control-plane-provisioning.php` bewaakt de basisprovisioning. `tests/phase59-control-plane-onboarding.php` bewaakt daarnaast onder meer:

- de vaste resumable checkpointvolgorde inclusief `pilot_ready` vóór `complete`;
- de verplichte technische acceptance-stage vóór lifecycle-adoptie;
- fail-closed downgrade van oude completionstates zonder nieuw bewijs;
- afwijzing van de neutrale provisioninghomepage als finale pilotcontent;
- strikte readinesspayload met expliciete branding-/restorebevestiging en twee herstel-SHA-256's;
- binding van readiness aan technische acceptatie en actuele lifecycle-export;
- DNS-profielvalidatie en het veilige DNS-wachtpunt;
- hergebruik van bestaande fasecontracten;
- verse healthprobe plus echte HTTPS-root-smoke zowel vóór lifecycle als vóór finale completion;
- behoud van bestaande pre-5.9 actieve tenants buiten het nieuwe contract;
- afwezigheid van passwordbootstrap, `--force` en vrije shell/process-primitives in de orchestrator.

Voor operationele acceptatie op TEST moet een nieuwe wegwerptenant de volledige route doorlopen. Alleen wanneer de nieuwe `5.9-acceptance` én `5.9-pilot-readiness` evidence aanwezig zijn en de snapshot `Pilot gereed` op groen zet, kan #319 worden gesloten.