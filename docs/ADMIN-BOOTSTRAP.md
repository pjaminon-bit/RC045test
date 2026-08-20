# Veilige eerste tenantbeheerder

Vanaf fase 3.4 krijgt een nieuwe externe tenant zijn eerste beheercredential via `bin/bootstrap-tenant-admin.php`. Fase 3.5.1 hardent de rotatie zodat een wachtwoordwissel ook alle bestaande tenant-sessies intrekt.

De provisioner maakt bewust geen standaardwachtwoord aan. Daardoor bestaat er nooit een gedeeld wachtwoord dat bij iedere nieuwe vereniging geldig is.

## Interactief gebruik

Na provisioning:

```bash
php bin/bootstrap-tenant-admin.php \
  --config=/srv/verenigingen/voorbeeldvereniging/config.php
```

De CLI vraagt het wachtwoord twee keer. Op de Linux-VPS wordt terminalecho tijdens beide invoeren uitgeschakeld. Het wachtwoord verschijnt daardoor niet in de opdrachtregel of normale terminaloutput.

Het wachtwoord moet minimaal 14 tekens lang zijn. Er geldt bewust geen verplichte combinatie van hoofdletters/cijfers/symbolen; een lange unieke wachtwoordzin is toegestaan.

## Automatisering via STDIN

Voor deploymentautomation of een secretmanager:

```bash
secret-tool ... | php bin/bootstrap-tenant-admin.php \
  --config=/srv/verenigingen/voorbeeldvereniging/config.php \
  --password-stdin
```

`--password=...`, `--hash=...` en vergelijkbare secretargumenten worden expliciet geweigerd. Zet een echt wachtwoord dus niet in shell history, GitHub workflow-YAML, procesargumenten of een tenantconfig.

`--password-stdin` leest exact één wachtwoordregel. De bron van STDIN moet zelf veilig zijn; gebruik in productie een secretmanager, beschermde file descriptor of vergelijkbare server-side secretbron en niet `echo 'wachtwoord'` in een blijvend shellscript.

## Wat wordt opgeslagen

Alleen dit server-only bestand wordt geactiveerd:

```text
private/auth/master.php
```

Het bevat uitsluitend een resultaat van PHP `password_hash(..., PASSWORD_DEFAULT)`, nooit het ingevoerde wachtwoord. Het bestand krijgt mode `0640`.

De bestaande beheerlogin gebruikt deze hash. Voor de masterlogin blijft de gebruikersnaam leeg; het ingestelde wachtwoord geeft de bestaande masterrechten.

## Tenantbinding

De bootstrap vertrouwt niet alleen op een los pad. Voor iedere write moeten deze gegevens onderling kloppen:

- `config.php` is een regulier, symlinkvrij bestand buiten de gedeelde code/documentroot;
- de config bevat een geldige canonieke tenant-key en externe `private_root`;
- `config.php` en `private_root` liggen onder dezelfde tenantroot;
- `tenant.json` heeft dezelfde tenant-key;
- `tenant.json` bindt exact aan dezelfde `config_file` en `private_root`;
- `require_tenant_config` staat in het manifest op `true`;
- `private/auth`, `private/backups/auth` en `private/sessions` zijn bestaande symlinkvrije mappen binnen dezelfde private root.

Een gekopieerde config of gemanipuleerd manifest kan daardoor niet worden gebruikt om de mastercredential van een andere vereniging te schrijven.

## Eerste bootstrap versus rotatie

Een tenant zonder `master.php` accepteert de eerste bootstrap zonder extra vlag.

Bestaat al een credential, dan weigert dezelfde opdracht een overschrijving. Een bewuste wachtwoordwissel gebruikt:

```bash
php bin/bootstrap-tenant-admin.php \
  --config=/srv/verenigingen/voorbeeldvereniging/config.php \
  --rotate
```

Voor de vervanging wordt de vorige `master.php` server-side onder `private/backups/auth/` bewaard. De bootstrap bewaart maximaal 20 masterbackups en verwijdert backups ouder dan 90 dagen. Ook deze backups bevatten alleen hashes.

Daarna worden **alle bestaande PHP-sessiebestanden van deze tenant verwijderd voordat de nieuwe masterhash wordt geplaatst**. Daardoor kan een browser die vóór de wachtwoordrotatie als master of gewone gebruiker was ingelogd niet met die oude sessie doorgaan. Iedereen logt na een masterrotatie opnieuw in. Een fout bij het intrekken van een sessiebestand stopt de rotatie fail-closed.

`--rotate` op een tenant die nog geen mastercredential heeft wordt geweigerd. Zo blijven bootstrap en credentialrotatie twee expliciete handelingen.

## Write-hardening

De bootstrap:

- gebruikt een exclusieve tenant-lokale `flock` om gelijktijdige credentialwrites te serialiseren;
- weigert symlinks in config-, manifest-, private-, auth-, backup-, session- en masterpaden;
- maakt een secondegrens-veilige backupnaam uit één microtime-meting plus random suffix;
- valideert vóór een rotatie de volledige sessiemap en trekt daarna alle bestaande sessies in;
- schrijft eerst naar een willekeurig tijdelijk bestand in dezelfde authmap;
- zet server-only bestandsrechten;
- controleert het masterdoel opnieuw vlak vóór plaatsing;
- plaatst de nieuwe config via atomische `rename`.

De tool is alleen via CLI bruikbaar. Een HTTP-request naar `bin/bootstrap-tenant-admin.php` krijgt HTTP 403.
