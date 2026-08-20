# Tenant provisioning

De applicatiecode wordt gedeeld. Iedere vereniging krijgt buiten de code/documentroot een eigen tenantmap met server-only configuratie en private opslag.

## Nieuwe tenant aanmaken

Voorbeeld:

```bash
php bin/provision-tenant.php \
  --key=voorbeeldvereniging \
  --name="Voorbeeldvereniging" \
  --url=https://voorbeeldvereniging.nl \
  --root=/srv/verenigingen
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

De provisioner kopieert de applicatiecode **niet**.

## Tenant-key is permanente technische identiteit

`--key` wordt niet meer genormaliseerd of gecorrigeerd. De opgegeven waarde moet al exact canoniek zijn voordat provisioning start.

Contract:

- 3 tot en met 63 ASCII-tekens;
- alleen lowercase `a-z`, cijfers `0-9` en het koppelteken `-`;
- geen koppelteken aan begin of einde;
- geen dubbele koppeltekens (`--`);
- geen spaties, underscores, hoofdletters, Unicode of andere speciale tekens;
- `default` is gereserveerd en mag niet als tenant-key worden gebruikt.

Voorbeelden:

```text
geldig:   rc045
geldig:   test-club-2026
ongeldig: Test-Club
ongeldig: test_club
ongeldig: test--club
ongeldig: default
```

Een ongeldige key wordt fail-closed geweigerd voordat tenantmappen, configuratie of manifesten worden aangemaakt. De weergavenaam van de vereniging hoort in `--name`; de technische key is niet bedoeld als vrij tekstveld.

## Runtime koppelen

De webserver/PHP-runtime van deze vereniging krijgt minimaal:

```text
VERENIGING_REQUIRE_TENANT_CONFIG=1
VERENIGING_CONFIG_FILE=/srv/verenigingen/voorbeeldvereniging/config.php
```

`VERENIGING_REQUIRE_TENANT_CONFIG=1` is de securitygrens voor de gedeelde VPS. Als `VERENIGING_CONFIG_FILE` dan ontbreekt, relatief is, onleesbaar is of naar een niet-bestaand bestand wijst, stopt de applicatie met een configuratiefout. Er wordt **nooit** teruggevallen op RC045/defaultconfiguratie.

`config.php` bevat zelf de tenant-eigen `private_root`. `runtime.env` wordt als hulpmiddel gegenereerd en bevat de verplichte fail-closed vlag automatisch. Het bestand is niet bedoeld om via HTTP te worden aangeboden.

De bestaande losse RC045/DEV-installatie blijft voorlopig compatibel wanneer `VERENIGING_REQUIRE_TENANT_CONFIG` niet is gezet. Die compatibiliteitsmodus is niet bedoeld als configuratie voor nieuwe VPS-tenants.

## Beheer-auth per tenant

Nieuwe tenants gebruiken uitsluitend deze server-only authpaden:

```text
private/auth/master.php
private/auth/users.json
private/audit/log.json
private/security/login-attempts.json
private/security/.login-attempts.lock
private/backups/auth/
private/sessions/
```

Er is voor een tenant met `private_root` **geen fallback** naar `beheer-config.php`, `beheer-users.json`, `beheer-log.json` of `beheer-login-pogingen.json` in de gedeelde applicatieroot.

De provisioner maakt bewust géén standaard beheerderswachtwoord aan. Een nieuwe tenant blijft voor beheer ongeconfigureerd totdat `private/auth/master.php` veilig server-side is geplaatst met minimaal een `password_hash()`:

```php
<?php
$BEHEER_WACHTWOORD_HASH = '...password_hash-resultaat...';
```

Een generiek of gedeeld standaardwachtwoord tussen verenigingen is dus niet onderdeel van provisioning.

## Publieke content per tenant

Dynamische openbare JSON staat voor nieuwe tenants onder:

```text
private/public-content/
```

Daaronder vallen onder meer homepage, ontstaan, baanreglement, aanmelden/bedankt, actueel, agenda, FAQ, contact, nieuws, changelog en lidmaatschapstypen. Een ontbrekend tenantbestand valt **niet** terug op het overeenkomstige RC045-bestand onder `/data`.

De browser blijft voorlopig de bestaande URL-vorm `/data/<dataset>.json` gebruiken. Apache routeert uitsluitend de expliciet gewhiteliste datasets via `public-content.php`, dat het bestand voor de actieve tenant uit de private opslag leest. Het endpoint accepteert alleen GET/HEAD en kent geen vrij bestandspad. Bij Nginx moet de vhost dezelfde exacte `/data/<dataset>.json`-routing naar `public-content.php?key=<dataset>` configureren; een wildcard waarmee willekeurige private bestanden opvraagbaar worden is niet toegestaan.

De bestaande standalone RC045/DEV-installatie gebruikt via dezelfde resolver voorlopig de bestaande `/data`-bestanden. Dit is alleen de compatibiliteitsmodus.

Belangrijke fasegrenzen:

- `media`, `fotoboek` en fysieke uploads volgen in optie 8;
- sponsor-JSON is al tenant-lokaal, maar sponsorlogo's vallen als uploadbestand onder optie 8;
- tenant-public-content wordt in optie 7 bewust niet naar de oude gedeelde `data-backups` gekopieerd; backup/restore wordt als geheel tenant-aware in optie 9;
- RC045-specifieke hardcoded tekst/branding in de code wordt pas bij de neutrale platformdefaults aangepakt en is niet hetzelfde als een opslagfallback.

## Veilige filesystempaden

`--root` wordt als securitygrens behandeld, niet alleen als tekstuele mapnaam.

- Het pad moet absoluut zijn.
- `.` en `..` als padsegment worden geweigerd; de provisioner normaliseert traversal niet stil weg.
- Een bestaande symlink op `--root`, een ancestor, de tenantmap, een private submap of een te schrijven bestand wordt geweigerd. Ook broken symlinks vallen hieronder.
- Voor nog niet bestaande doelen wordt de langste bestaande ancestor via `realpath()` fysiek opgelost. Pas daarna worden de gevalideerde nieuwe componenten aangehangen.
- De fysieke tenantroot mag niet binnen de gedeelde applicatie/documentroot vallen.
- Directory- en filewrites voeren vlak vóór gebruik opnieuw een containment- en symlinkcontrole uit. Na directorycreatie volgt opnieuw een fysieke controle.
- `config.php`, `runtime.env` en `tenant.json` worden nooit via een symlinkdoel overschreven, ook niet met `--force`.

De provisioner is een beheertool en veronderstelt dat de filesystemhiërarchie waarin `--root` ligt niet gelijktijdig door een onbetrouwbare lokale gebruiker kan worden gewijzigd. Op de VPS hoort bijvoorbeeld `/srv/verenigingen` daarom alleen schrijfbaar te zijn voor de vertrouwde provisioning-/beheeraccount. De applicatie zelf hoeft geen schrijfrecht op de bovenliggende tenantroot te hebben buiten de expliciet benodigde tenantmappen.

## Veiligheidsregels

- `--key` moet exact aan het vaste tenant-key-contract voldoen; de provisioner normaliseert keys niet.
- `--root` moet absoluut en symlinkvrij zijn en mag geen `.`/`..`-segmenten bevatten.
- Tenantdata binnen de applicatie/documentroot wordt op het fysiek gecanonicaliseerde pad geweigerd.
- Nieuwe geprovisioneerde tenants krijgen altijd `VERENIGING_REQUIRE_TENANT_CONFIG=1` in `runtime.env`.
- Een ontbrekende tenantconfig faalt in die modus gesloten; RC045/defaultconfig wordt niet geladen.
- Authbestanden van externe tenants staan onder hun eigen `private_root`; gedeelde root-authdata wordt niet gebruikt als fallback.
- Publieke tenant-JSON staat buiten de documentroot onder `private/public-content`; ontbrekende tenantdata valt niet terug op RC045 `/data`.
- Auth- en contentmappen worden met server-only directoryrechten voorbereid; tenantbestanden worden naar `0640` aangescherpt waar deze laag ze schrijft.
- Een onbekende waarde voor `VERENIGING_REQUIRE_TENANT_CONFIG` wordt als configuratiefout geweigerd in plaats van stil als aan/uit geïnterpreteerd.
- Een bestaande afwijkende `config.php`, `runtime.env` of `tenant.json` wordt zonder `--force` niet overschreven.
- Dezelfde opdracht nogmaals uitvoeren is idempotent: identieke bestanden blijven ongewijzigd.
- `--dry-run` voert dezelfde key- en padveiligheidscontroles uit, maar maakt geen mappen of bestanden aan.
- De provisioner weigert uitvoering via HTTP en is alleen voor CLI bedoeld.

## Opties

```text
--timezone=Europe/Amsterdam
--driver=json
--driver=pdo
--force
--dry-run
```

Bij `--driver=pdo` worden nog geen databasecredentials door de CLI gevraagd of opgeslagen. Die worden in een latere deployment/provisioningfase via secrets/environment gekoppeld.

## Nog handmatig in fase 3.2

De provisioner maakt nog geen DNS-record, TLS-certificaat, Apache/Nginx-vhost, PHP-FPM pool of mastercredential aan. Hij levert wel de vaste tenantpaden en runtimeconfig waarop die automatisering in een volgende fase kan steunen.
