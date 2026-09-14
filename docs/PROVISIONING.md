# Tenant provisioning

De applicatiecode wordt gedeeld. Iedere vereniging krijgt buiten de code/documentroot een eigen tenantmap met server-only configuratie, private opslag, eigen branding/moduleprofiel en eigen beheer-authenticatie.

## 1. Nieuwe tenant aanmaken

Voorbeeld:

```bash
php bin/provision-tenant.php \
  --key=voorbeeldvereniging \
  --name="Voorbeeldvereniging" \
  --url=https://voorbeeldvereniging.nl \
  --root=/srv/verenigingen \
  --driver=pdo \
  --modules=website,ledenadministratie,aanmelden,sponsors
```

Dit maakt aan:

```text
/srv/verenigingen/voorbeeldvereniging/
├── config.php
├── runtime.env
├── tenant.json
└── private/
    ├── collections/
    ├── public-content/
    ├── auth/
    ├── audit/
    ├── security/
    ├── sessions/
    └── backups/
        └── auth/
```

De provisioner kopieert de applicatiecode **niet**. Alle verenigingen gebruiken dezelfde gedeelde codebase.

`private/public-assets/` wordt pas aangemaakt zodra een tenant de eerste publieke uploadnamespace gebruikt. Een vereniging zonder Fotoboek/Sponsors krijgt dus geen ongebruikte uploadstructuur.

## 2. Tenant-key

`--key` is de permanente technische identiteit en wordt niet stil genormaliseerd.

Contract:

- 3 t/m 63 ASCII-tekens;
- alleen lowercase `a-z`, cijfers `0-9` en `-`;
- geen koppelteken aan begin/einde;
- geen dubbele koppeltekens;
- geen spaties, underscores, hoofdletters of Unicode;
- `default` is gereserveerd.

Voorbeelden:

```text
geldig:   rc045
geldig:   test-club-2026
ongeldig: Test-Club
ongeldig: test_club
ongeldig: test--club
ongeldig: default
```

Ongeldige keys worden geweigerd voordat filesystemwrites plaatsvinden.

## 3. Moduleprofiel en branding

Met `--modules=` wordt de actieve functionaliteit per tenant gekozen. De kernmodule `website` is verplicht. Iedere bekende module wordt in `config.php` expliciet als `true` of `false` opgeslagen, zodat een tenant geen ontbrekende modulekeuzes uit RC045/defaultconfig kan erven.

Zonder `--modules` blijven voor compatibiliteit alle platformmodules actief.

Nieuwe externe tenants krijgen neutrale platformbranding. Ze erven geen RC045-logo, social image, favicons of webmanifest. Eigen branding wordt later uitsluitend in de eigen server-only tenantconfig gezet.

## 4. Runtime koppelen

De webserver/PHP-runtime van de vereniging krijgt minimaal:

```text
VERENIGING_REQUIRE_TENANT_CONFIG=1
VERENIGING_CONFIG_FILE=/srv/verenigingen/voorbeeldvereniging/config.php
```

`VERENIGING_REQUIRE_TENANT_CONFIG=1` is de harde securitygrens. Als de config ontbreekt, relatief is, onleesbaar is of niet bestaat, stopt de applicatie. Er wordt **nooit** teruggevallen op RC045/defaultconfiguratie.

`config.php` bevat de tenant-eigen `private_root`. `runtime.env` wordt als hulpmiddel gegenereerd en bevat de fail-closed runtimewaarden.

De bestaande standalone RC045/DEV-installatie blijft compatibel zonder de verplichte tenantvlag; die modus is niet bedoeld voor nieuwe VPS-tenants.

## 5. Eerste beheerder activeren

De provisioner maakt bewust geen standaard beheerderswachtwoord. Na provisioning activeer je de eerste tenantbeheerder via:

```bash
php bin/bootstrap-tenant-admin.php \
  --config=/srv/verenigingen/voorbeeldvereniging/config.php
```

Het wachtwoord wordt interactief twee keer verborgen gevraagd. Voor automation kan een veilige secretbron via STDIN worden gebruikt:

```bash
secret-tool ... | php bin/bootstrap-tenant-admin.php \
  --config=/srv/verenigingen/voorbeeldvereniging/config.php \
  --password-stdin
```

Wachtwoorden en hashes als CLI-argument worden expliciet geweigerd. Alleen een `password_hash()` wordt opgeslagen in:

```text
private/auth/master.php
```

Bestaat al een mastercredential, dan is overschrijven niet toegestaan. Een bewuste rotatie vereist `--rotate` en maakt eerst een server-only backup van de vorige hash.

Volledige security- en gebruiksdetails staan in `docs/ADMIN-BOOTSTRAP.md`.

## 6. Auth- en sessie-isolatie

Externe tenants gebruiken uitsluitend tenant-lokale authpaden:

```text
private/auth/master.php
private/auth/users.json
private/audit/log.json
private/security/login-attempts.json
private/security/.login-attempts.lock
private/backups/auth/
private/sessions/
```

Er is geen fallback naar gedeelde RC045-authdata. Ook PHP-sessionopslag en session-cookie namespace zijn tenantgebonden.

## 7. Publieke content

Dynamische openbare JSON staat voor externe tenants onder:

```text
private/public-content/
```

Onder meer homepage, ontstaan, reglement, aanmelden/bedankt, actueel, agenda, FAQ, contact, nieuws, Media, Fotoboek, changelog en lidmaatschapstypen vallen hieronder.

Een ontbrekend tenantbestand valt niet terug op RC045 `/data`. Legacy browser-URL's `/data/<dataset>.json` worden via de expliciete whitelist in `public-content.php` naar de actieve tenant gerouteerd.

Nieuwe tenants krijgen direct neutrale `homepage.json` en `contact.json`. Externe tenants gebruiken dezelfde RC045-homepagestructuur, styles, scripts, menustructuur en responsive breakpoints; een server-side filter vult uitsluitend tenantidentiteit, inhoud, links en media in. Een bestaande dataset met herkenbare RC045-inhoud wordt fail-closed genegeerd en gedeelde RC045-afbeeldingen worden nooit uitgeleverd.

Bestaande tenants controleer en migreer je met:

```bash
php bin/migrate-tenant-public-template.php --config=/srv/verenigingen/<tenant>/config.php --check
php bin/migrate-tenant-public-template.php --config=/srv/verenigingen/<tenant>/config.php --apply
```

De apply-stap gebruikt de tenantgebonden publieke contentwriter en maakt vóór iedere wijziging een tenantlokale back-up. Veilige tenant-eigen waarden blijven behouden; ontbrekende templatevelden worden neutraal aangevuld. `--force` reset bewust naar neutrale startinhoud.

Bij Nginx moet dezelfde exacte whitelist-routing worden ingericht; geef nooit vrij filesystemtoegang tot de private root.

## 8. Publieke uploads

Fotoboekbestanden en sponsorlogo's staan buiten de documentroot:

```text
private/public-assets/
├── fotoboek/
│   └── <album>/
│       ├── <bestand>
│       └── thumbs/
│           └── <bestand>
└── sponsors/
    └── <bestand>
```

De browser-URL's blijven `images/fotoboek/...` en `images/sponsors/...`. `public-asset.php` serveert alleen toegestane scopes/extensies, blokkeert traversal en symlinks en ondersteunt begrensde MP4 byte-ranges.

Een ontbrekend asset valt nooit terug op een andere tenant of op RC045.

## 9. Private data en PDO

Private JSON-collecties staan onder:

```text
private/collections/
```

Nieuwe tenants gebruiken canoniek `--driver=pdo`. De first-VPS/productiebootstrap geeft dit bovendien expliciet door en provisiont PostgreSQL vóór tenant-FPM wordt geactiveerd. `--driver=json` blijft alleen beschikbaar als bewuste standalone/legacycompatibiliteitskeuze. Een externe PDO-tenant valt nooit terug naar legacy JSON wanneer de database ontbreekt, faalt of een collectie leeg is.

Databasecredentials worden niet door de provisioner gevraagd. De canonieke VPS-stack gebruikt passwordloze Unix-socket peer authentication; expliciete DSN/user/passwordvelden blijven alleen relevant voor standalone/legacy ontwikkel- of migratiesituaties.

## 10. Backups en restore

Externe tenants gebruiken tenantgebonden backups onder hun eigen `private_root/backups`.

Data-envelopes en assetsnapshots zijn aan tenant en scope/component gebonden. Een fysiek naar een andere tenant gekopieerde snapshot wordt bij restore geweigerd. Assetrestore gebruikt staging en atomische rename.

Retentie kan via `opslag.backups` worden ingesteld en begrenst leeftijd, aantallen en totale assetbytes.

## 11. Filesystemveiligheid

De tenantroot is een securitygrens:

- `--root` moet absoluut zijn;
- `.` en `..` segmenten worden geweigerd;
- symlinks op roots, ancestors, tenantmappen, private submappen en schrijfdoelen worden geweigerd;
- nog niet bestaande doelen worden via de langste bestaande ancestor fysiek gecanonicaliseerd;
- tenantdata binnen de gedeelde applicatie/documentroot wordt geweigerd;
- gevoelige writes controleren containment en symlinks opnieuw vlak vóór gebruik;
- `config.php`, `runtime.env` en `tenant.json` worden atomisch geschreven en niet via symlinks overschreven;
- de admin-bootstrap bindt config, manifest en private root opnieuw voordat `master.php` wordt geschreven.

Op de VPS hoort `/srv/verenigingen` alleen schrijfbaar te zijn voor de vertrouwde provisioning-/beheeraccount.

## Provisioneropties

```text
--timezone=Europe/Amsterdam
--driver=pdo   # standaard/canoniek voor nieuwe tenants
--driver=json  # alleen expliciete standalone/legacycompatibiliteit
--modules=website,ledenadministratie,...
--force
--dry-run
```

`--force` geldt voor gecontroleerde vervanging van provisioningconfiguratie en **niet** voor de mastercredential. Credentialrotatie gebruikt apart `bootstrap-tenant-admin.php --rotate`.

## Productie-infrastructuurstatus

Fase 4.1 t/m 4.8, platformbeheer/control-plane en first-VPS bootstrap zijn inmiddels daadwerkelijk op de eerste Ubuntu 26.04-VPS toegepast en geaccepteerd. Fase 5.3 is op 28 augustus 2026 volledig groen afgerond, inclusief live DNS/TLS, PostgreSQL-isolatie, monitoring, lifecycle/export+restore en release/rollback.

Nieuwe VPS-tenants worden daarom niet via losse historische fasestappen als een nog te bouwen keten behandeld. Gebruik voor onboarding de actuele georkestreerde provisioning/bootstrap- en deploymentcontracten uit `docs/VPS-DEPLOYMENT.md`, `docs/VPS-FIRST-BOOTSTRAP.md` en de gespecialiseerde operationele documenten.

Historische fasenummers blijven alleen bestaan om de herkomst van een contract te duiden; zij betekenen niet dat de voorziening nog toekomstig is.
