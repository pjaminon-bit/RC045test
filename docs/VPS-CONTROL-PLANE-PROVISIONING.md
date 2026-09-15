# Platformbeheer: nieuwe vereniging — fase 5.7 + 5.9

Fase 5.7 voegt aan het bestaande VPS-platformbeheer de actie **Nieuwe vereniging** toe. De browser kan de tenantbasis laten aanmaken, maar kan geen rootcommando's, serverpaden of beheerderswachtwoorden aanleveren.

Fase 5.9 bouwt hierop voort met één centrale, hervatbare onboardingroute. Die route hergebruikt de bestaande deployment-, runtime-, database-, webserver-, DNS-, TLS-, monitoring- en lifecyclecontracten en sluit sinds #319 af met een expliciete pilotacceptatie vóór de lifecycle als voltooid wordt gemarkeerd.

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

## Eerste beheerder blijft een aparte secretstap

De bestaande productiebootstrap heeft bewust een andere securitygrens voor credentials: het eerste tenantbeheerwachtwoord wordt via `bootstrap-tenant-admin.php` uit een interactieve of STDIN-secretstroom gelezen. Wachtwoorden horen niet in argv, environment, Git, logs of de control-plane queue.

Na basisprovisioning wordt daarom één server-side stap uitgevoerd:

```bash
sudo php /srv/verenigingsplatform/current/bin/bootstrap-tenant-admin.php \
  --config=/srv/verenigingen/<tenant>/config.php
```

Daarna kan de operator de onboardingwizard hervatten. De browser verstuurt alleen het publieke DNS-profiel; providercredentials en het tenantbeheerwachtwoord blijven buiten de queue.

## Centrale fase-5.9 route

De root-executor gebruikt vaste checkpoints:

`start → plans_ready → runtime_applied → database_applied → fpm_active → dns_ready → tls_active → monitoring_active → acceptance_ready → lifecycle_active → complete`

De flow hergebruikt de bestaande fase-3.5/4.x scripts. DNS is bewust het enige normale externe wachtpunt: wanneer de publieke records nog niet conform het vastgelegde profiel zijn, stopt de aanvraag veilig na voorbereiding en kan dezelfde onboarding later worden hervat.

### Pilotacceptatie vóór completion

`acceptance_ready` is een verplichte fail-closed gate. Voordat lifecycle-adoptie als voltooid wordt vastgelegd controleert de executor minimaal:

- de eerste beheerder bestaat server-side;
- `tenant.json` is geldig, aan dezelfde tenant-key en canonieke HTTPS-host gebonden en bevat de verplichte module `website`;
- de tenant heeft geldige publieke homepagecontent;
- het actuele lifecycleplan is byte-exact valide en behoudt de bestaande root-only/export-before-delete veiligheidsgrenzen;
- een verse volledige `check-vps-health.php --probe --write-status` slaagt;
- de echte canonieke HTTPS-root (`https://<tenant>/`) geeft via een lokale `--resolve`-probe HTTP 200.

Daarna schrijft de control-plane uitsluitend geschoond bewijs in de root-owned onboardingstate: tenant-key, host, timestamp, groene checkcodes, moduleprofiel en SHA-256-bindingen van tenantmanifest, monitoringplan en lifecycleplan. Er worden geen passwords, hashes uit authbestanden, PII, providerresponses of andere secrets opgenomen.

Een oudere fase-5.9 state die `lifecycle_active` of `complete` bevat maar geen geldig nieuw acceptatiebewijs heeft, geldt in het nieuwe contract niet stil als geaccepteerd.

## Backup en recovery

De pilotacceptatie valideert het bestaande fase-4.8 lifecycle/exportcontract, maar voert **geen** automatische suspend/export/restore-cyclus uit. Dat is bewust: onboarding mag geen tweede backupmechanisme introduceren en mag een nieuw geactiveerde vereniging niet verborgen tijdelijk uitschakelen.

Voor een echte pilot hoort het operationele acceptatiebewijs daarom aanvullend te bevestigen dat het bestaande backup-/export-/restorepad op TEST werkt volgens `docs/VPS-LIFECYCLE.md`, `docs/BACKUP-ATTESTATION.md` en de eerder bewezen fase-5.3 herstelprocedure. Dit blijft onderdeel van #319 voordat de pilot-readiness als geheel kan worden gesloten.

## Regressiecontract

`tests/phase57-control-plane-provisioning.php` bewaakt de basisprovisioning. `tests/phase59-control-plane-onboarding.php` bewaakt daarnaast onder meer:

- de vaste resumable checkpointvolgorde;
- de verplichte acceptance-stage vóór lifecycle-completion;
- DNS-profielvalidatie en het veilige DNS-wachtpunt;
- hergebruik van bestaande fasecontracten;
- een verse healthprobe plus echte HTTPS-root-smoke;
- tenant-/host-/module-/planbinding van het acceptatiebewijs;
- fail-closed behandeling van een legacy `complete` zonder nieuw acceptatiebewijs;
- afwezigheid van passwordbootstrap, `--force` en vrije shell/process-primitives in de orchestrator.

Voor operationele acceptatie op TEST moet een wegwerptenant de volledige route doorlopen. Alleen wanneer de acceptance-evidence aanwezig is, de lifecycle actief is en de bestaande recoveryprocedure aantoonbaar werkt, is de onboardingpilot gereed voor afronding van #319.
