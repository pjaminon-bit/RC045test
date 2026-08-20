# Fase 3.5 — VPS deploymentcontract

Datum: **20-08-2026**

## Doel

Na fase 3.4 kon een nieuwe vereniging veilig worden geprovisioneerd en van een eerste beheerder worden voorzien. De resterende grens was deployment: de repository had nog geen machineleesbaar contract dat één gedeelde applicatierelease veilig aan meerdere tenant-runtimes koppelt.

Fase 3.5 maakt die grens expliciet zonder al DNS, TLS of daadwerkelijke VPS-configuratie te installeren.

## Gevonden deploymentrisico's

1. De gedeelde `.htaccess` stuurde een onbeveiligd verzoek nog hardcoded door naar `https://rc045.nl`. Op een gedeelde VPS zou een andere vereniging daardoor bij een HTTP-fallback naar RC045 kunnen worden doorgestuurd.
2. De runtimevariabelen bestonden wel in `runtime.env`, maar er was nog geen laatste deploymentcheck die config, manifest, runtime.env, beheerbootstrap, host en gedeelde code-root samen valideerde.
3. Er was nog geen vaste identiteit voor een per-tenant PHP-FPM pool/socket.
4. `bin`, `tests`, `docs` en `.github` maakten nog deel uit van de gedeelde webboom. De CLI-tools weigerden HTTP zelf al, maar de webservergrens hoorde die ontwikkel-/server-only paden eerder te blokkeren.

## Nieuwe CLI

Toegevoegd:

`bin/prepare-vps-deployment.php`

Voorbeeld:

```bash
php bin/prepare-vps-deployment.php \
  --config=/srv/verenigingen/noorderhaven/config.php \
  --app-root=/srv/verenigingsplatform/current
```

De tool valideert fail-closed:

- veilige provisioned tenantconfig buiten de app-root;
- exacte `<tenant>/private` binding;
- `tenant.json` tenant-key/config/private-root/site-url binding;
- exact `runtime.env` met `VERENIGING_REQUIRE_TENANT_CONFIG=1`;
- HTTPS site-URL op de domeinroot;
- fase-3.4 mastercredential met geldige `password_hash()` en zonder plaintext compatibilitywachtwoord;
- fysieke scheiding tussen tenantroot en gedeelde code;
- symlinkvrije tenantpaden;
- geldige gedeelde platformrelease onder `--app-root`;
- output uitsluitend binnen de tenantroot.

Een logische release-symlink zoals `/srv/verenigingsplatform/current` is voor de gedeelde code bewust toegestaan. Het descriptor legt daarnaast het fysiek opgeloste releasepad vast.

## deployment.json

Per tenant ontstaat standaard:

`<tenantroot>/deployment.json`

Het contract bevat:

- schema-versie;
- tenant-key;
- canonical host en HTTPS site-URL;
- gedeelde logische en fysieke app-root/documentroot;
- tenantroot/config/private-root;
- exact fail-closed runtime-environment;
- deterministische PHP-FPM pool en Unix-socket;
- unieke aanbevolen OS-runtimeidentity;
- expliciete readiness-vlaggen.

Het bestand bevat geen authhash, wachtwoord, DSN, databasecredential, TLS-key, API-token of ander secret.

De output is deterministisch en atomisch. Een identieke tweede run blijft ongewijzigd; een afwijkend bestaand bestand vereist `--force`. `--dry-run` voert dezelfde controles uit zonder filesystemwrite.

## PHP-FPM isolation contract

Fase 3.5 legt vast dat iedere tenant later een eigen PHP-FPM pool krijgt:

- unieke poolnaam;
- unieke Unix-socket;
- unieke aanbevolen OS-runtimegebruiker;
- `clear_env=true`;
- runtime selecteert exact één tenantconfig/private root;
- gedeelde applicatiecode is read-only voor tenant-runtimes;
- private storage is alleen schrijfbaar voor de bijbehorende tenant-runtime.

OS-user/group creatie en `chown` blijven infrastructuurtaken en worden niet vanuit de applicatie-CLI met rootrechten uitgevoerd.

## Apache/websurface

`.htaccess` is aangepast:

- vaste `rc045.nl` HTTPS-redirect verwijderd;
- tenant-neutrale HTTPS-fallback gebruikt alleen een gevalideerde Host-header;
- ongeldige Host bij fallback geeft HTTP 400;
- interne env-marker is generiek `VST_HTTPS`;
- `app`, `bin`, `tests`, `docs` en `.github` worden centraal met HTTP 403 geblokkeerd.

Op de uiteindelijke VPS blijven canonical host en TLS primair verantwoordelijkheid van vhost/reverse proxy. Nginx moet de Apache deny/routingregels expliciet spiegelen omdat Nginx geen `.htaccess` leest.

## Acceptatietest

Toegevoegd:

`tests/phase35-vps-deployment.php`

De test bouwt echte fictieve tenants via de bestaande provisioner en fase-3.4 adminbootstrap en controleert onder meer:

- twee tenants delen exact dezelfde app-root;
- hosts/private roots/FPM pools/sockets/OS-identities blijven gescheiden;
- runtime-env is exact tenantgebonden;
- descriptor bevat geen secrets;
- 0640-rechten;
- idempotentie en dry-run;
- release-symlink ondersteuning;
- copied-config/cross-tenant binding wordt geweigerd;
- gemanipuleerd manifest wordt geweigerd;
- gemanipuleerd runtime.env wordt geweigerd;
- tenant zonder eerste beheerder is niet deployment-ready;
- HTTP site-url en URL-subpad worden geweigerd;
- output buiten tenantroot en symlinkoutput worden geweigerd;
- Apache-laag is niet meer RC045-hardcoded.

De test is toegevoegd aan de vaste GitHub Actions validatiesuite. De DEV-smokecheck controleert daarnaast dat de nieuwe CLI, tests en VPS-documentatie via HTTP 403 blijven.

## Bewuste fasegrens

Nog niet onderdeel van fase 3.5:

- DNS-mutaties;
- TLS-uitgifte/renewal;
- concrete Apache/Nginx-vhostbestanden installeren;
- PHP-FPM pools/users daadwerkelijk aanmaken/reloaden;
- PDO/database secrets provisionen;
- monitoring/logaggregatie;
- tenant disable/export/remove;
- volledig geautomatiseerde release/rollback.

Deze vervolgstappen kunnen nu zonder tenant-specifieke codeforks op hetzelfde machineleesbare deploymentcontract bouwen.
