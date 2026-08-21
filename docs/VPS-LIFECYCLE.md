# VPS tenant lifecycle — fase 4.8

Fase 4.8 beheert de levenscyclus van een reeds geprovisioneerde productie-tenant. De lifecyclelaag bouwt uitsluitend voort op een volledig gevalideerd fase-4.6 monitoringplan en is daardoor transitief gebonden aan runtime 4.1, Apache 4.2, TLS 4.4 en PostgreSQL 4.5.

## Uitgangspunten

- lifecyclemutaties zijn uitsluitend Linux-root/operatoracties;
- gewone verenigingsbeheerders krijgen nooit directe root-, Apache-, FPM-, PostgreSQL- of Certbotrechten;
- een bestaande VPS-installatie wordt éénmalig met `--adopt-active` onder lifecyclebeheer gebracht;
- per tenant kan maximaal één lifecycleactie tegelijk draaien via een exclusieve root-lock;
- lifecycle-state staat root-owned onder `/var/lib/verenigingsplatform/lifecycle`;
- lifecycle-audit staat server-side in `/var/log/verenigingsplatform/lifecycle.jsonl`;
- exports staan root-only buiten tenantroot onder `/var/backups/verenigingsplatform/tenants/<tenant>`;
- DNS-providerrecords worden nooit automatisch gewijzigd of verwijderd.

## Plan voorbereiden

```bash
php bin/prepare-vps-lifecycle.php \
  --monitoring-plan=/srv/verenigingen/club/monitoring/monitoring-plan.json
```

Dit schrijft alleen een secretvrij `lifecycle/lifecycle-plan.json`. Met `--dry-run` wordt niets geschreven. De plangenerator accepteert geen passwords, tokens, DSN's of andere secrets.

Root-vrije validatie:

```bash
php bin/apply-vps-lifecycle.php \
  --plan=/srv/verenigingen/club/lifecycle/lifecycle-plan.json \
  --check
```

## Bestaande actieve tenant adopteren

Fase 4.1–4.6 maakt bij de eerste productie-inrichting de runtime al actief. 4.8 neemt die toestand niet stil aan. De eerste lifecyclehandeling is daarom:

```bash
sudo php bin/apply-vps-lifecycle.php --plan=... --adopt-active
```

Adoptie slaagt alleen als:

- de exacte FPM-poolconfig actief is en de tenant socket bestaat;
- de exacte tenant HTTP- en HTTPS-vhosts enabled zijn;
- de PostgreSQL app-role `LOGIN` is en de tenantmarkers kloppen;
- de monitoringtimer enabled is;
- de volledige fase-4.6 healthcheck slaagt.

Pas daarna wordt `active` als root-owned lifecycle-state geregistreerd.

## Tenant uitschakelen

```bash
sudo php bin/apply-vps-lifecycle.php --plan=... --suspend
```

De veilige volgorde is:

1. de tenant HTTPS-appvhost uit Apache `sites-enabled` halen en Apache configtest/reload uitvoeren;
2. de minimale HTTP-vhost uitsluitend voor `/.well-known/acme-challenge/` actief laten, zodat Certbot ook bij langdurige suspend kan blijven vernieuwen; overige HTTP-verzoeken blijven naar de niet-actieve tenant-HTTPS-route wijzen en bereiken de applicatie niet;
3. PostgreSQL app-role `NOLOGIN` zetten en bestaande tenantsessies beëindigen;
4. monitoringtimer stoppen;
5. exacte tenant FPM-poolconfig verwijderen, FPM-config testen en FPM reloaden;
6. bewijzen dat tenant socket en tenantprocessen verdwenen zijn;
7. lifecycle-state naar `suspended` brengen.

Een fout midden in deze overgang laat een transition-state achter. `--recover` rijdt de tenant vervolgens altijd fail-closed naar `suspended`; recovery probeert nooit een half geactiveerde toestand te raden.

## Tenant opnieuw activeren

```bash
sudo php bin/apply-vps-lifecycle.php --plan=... --activate
```

Activatie mag uitsluitend vanuit `suspended` en gebruikt de gecontroleerde volgorde:

1. exacte FPM-pool uit de gevalideerde 4.1-bundle installeren, configtest en reload;
2. PostgreSQL app-role exact volgens het bestaande least-privilege/no-password contract naar `LOGIN` brengen en de 4.5 runtimecheck uitvoeren;
3. exacte tenant HTTP/HTTPS-vhosts activeren en Apache configtest/reload;
4. volledige 4.6 healthcheck uitvoeren;
5. pas daarna monitoring opnieuw activeren en lifecycle-state `active` schrijven.

Mislukt activatie na een deel van de stappen, dan probeert 4.8 automatisch terug te keren naar de veilige `suspended` toestand.

## Export

Export is alleen toegestaan wanneer de tenant stabiel `suspended` is:

```bash
sudo php bin/apply-vps-lifecycle.php --plan=... --export
```

Het exportpakket bevat:

- een PostgreSQL `pg_dump` in custom format;
- een tar.gz van de volledige tenantboom buiten de gedeelde applicatiecode;
- een exportmanifest met tenantbinding en SHA-256 checksums;
- een SHA-256 checksum over het uiteindelijke pakket.

Het pakket krijgt root-only mode `0600`. Een tenantexport bevat noodzakelijkerwijs gevoelige verenigingsdata en wachtwoordhashes; hij hoort daarom niet in Git of in een publiek/downloadbaar webpad.

## Verwijderen is tweestaps

Een tenant kan niet rechtstreeks vanuit `active` worden verwijderd.

Eerst:

1. `--suspend`;
2. `--export`;
3. controleer de gerapporteerde export-SHA;
4. zet de tenant in `pending_delete`:

```bash
sudo php bin/apply-vps-lifecycle.php \
  --plan=... \
  --delete \
  --confirm-tenant=club \
  --confirm-export-sha=<sha256>
```

`--delete` vernietigt nog geen data. Er geldt vervolgens minimaal 24 uur wachttijd. In die periode kan de aanvraag worden ingetrokken:

```bash
sudo php bin/apply-vps-lifecycle.php --plan=... --cancel-delete
```

## Definitieve purge

Na de wachttijd:

```bash
sudo php bin/apply-vps-lifecycle.php \
  --plan=... \
  --purge \
  --confirm-tenant=club \
  --confirm-export-sha=<sha256> \
  --confirm-purge=VERWIJDER-DEFINITIEF
```

De purge ruimt tenantgebonden serverresources op, waaronder:

- monitoringtimer/-unit en tenantlogrotate;
- Apache tenant-vhosts en routingfragment;
- tenant Certbot-lineage;
- PostgreSQL database, app-role, owner-role en tenant-HBA;
- tenant FPM-pool;
- tenant Linux-user/group;
- tenantroot.

Het geverifieerde exportpakket blijft behouden. Ook blijft een root-owned tombstone achter, zodat dezelfde tenant-key niet stil als een nieuwe vereniging kan worden geadopteerd. DNS-records bij de provider blijven bewust ongemoeid.

Voor de destructieve stappen wordt een root-owned plansnapshot vastgelegd en de tombstone geeft aan of de purge bezig is met `purging_infrastructure` of `data_delete`. Een proceskill of stroomuitval in een van beide fasen kan daardoor uitsluitend vanaf die exact gebonden snapshot worden hervat:

```bash
sudo php bin/apply-vps-lifecycle.php --recover-purge --tenant=club
```

`--recover-purge` kan geen nieuwe verwijdering starten. Het accepteert alleen een reeds door een geldige purge geschreven `purging_infrastructure`- of `data_delete`-tombstone waarvan de plansnapshotchecksum nog exact klopt.

## Platform-/superbeheer-GUI

De lifecycle-engine is bewust een operator/rootcontract en geen gewone verenigingsbeheerfunctie. Een toekomstige platform-/superbeheer-GUI moet boven deze laag worden geplaatst met een aparte control-plane identiteit. De web-GUI mag rootcommando's niet rechtstreeks uitvoeren en mag nooit de auth/sessies van een tenant hergebruiken voor platformbeheer.

De eerste productie-uitrol blijft daarom CLI/operatorgestuurd. De lifecycle-state en het auditformaat zijn zo opgezet dat een aparte control-plane later status en acties kan modelleren zonder de tenantapplicatie rootrechten te geven.
