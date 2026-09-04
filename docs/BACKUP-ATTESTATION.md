# Cryptografische tenantbackup-attestatie

Status: security-hardeningcontract voor #148.

## Doel

Tenantbackups waren al fysiek aan `private_root`, tenant-key en component/scope gebonden. Die binding voorkomt normale cross-tenant restores en padmisbruik, maar schema 1 kon niet aantonen dat een bestaand snapshot na creatie ongewijzigd was gebleven.

Na activatie van dit contract worden nieuwe tenantbackups daarom cryptografisch geattesteerd vóórdat ze als bruikbare herstelbron gelden.

## Trust boundary

De tenant-webruntime krijgt **nooit** de private signing key.

- private key: `/etc/verenigingsplatform/backup-attestation/private.pem`, `root:root 0600`;
- publieke verificatiesleutel: `/etc/verenigingsplatform/backup-attestation/public.pem`, `root:root 0644`;
- root-owned attestor: `/usr/local/libexec/verenigingsplatform/backup-attestor`, `root:root 0555`;
- lokale socket: `/run/verenigingsplatform/backup-attestor.sock`.

De PHP-runtime kan uitsluitend een snapshotpad, type en componentbinding aan de lokale attestor aanbieden en het antwoord met de publieke sleutel verifiëren. De PHP-client voert geen `sudo`, shell, `exec`, `system`, `proc_open` of vergelijkbare privileged call uit.

De root-attestor:

1. leest Linux peer credentials (`SO_PEERCRED`) van de Unix-socket;
2. accepteert uitsluitend een peer-UID die exact één actuele `/srv/verenigingen/*/deployment.json` aan een deterministische tenant-FPM-user bindt;
3. valideert dat `deployment.json` root-owned en niet group/world-writeable is;
4. accepteert alleen een verse schema-2 snapshot binnen `private/backups/tenant` van diezelfde tenant;
5. valideert tenant-key, backup-key/asset-scope en het vaste snapshotpad;
6. tekent een canonieke statement met RSA/SHA-256 via een vaste `/usr/bin/openssl` invocation.

De service leest tenantdata voor attestation, maar schrijft niet in tenantstorage. De web-runtime schrijft de detached sidecar pas nadat hij het teruggestuurde signatureobject zelf met de public key heeft gevalideerd.

Dit contract beschermt tegen ontbrekende of achteraf gemanipuleerde snapshots. Het is defense-in-depth en is geen vervanging voor runtime-isolatie: een aanvaller die de tenant-runtime *actief* volledig bestuurt terwijl de attestor beschikbaar is, kan nog steeds proberen een vers snapshot ter attestatie aan te bieden. De peer-, pad-, schema-, owner- en freshnesscontroles beperken die signing-interface bewust; voorkomen van een actieve runtimecompromis blijft de taak van de bestaande tenant-/FPM-/root-isolatie.

## Wat wordt ondertekend

### Data

De detached `<snapshot>.json.sig` bevat een signature over een canonieke statement met:

- protocolversie;
- type `data`;
- tenant-key;
- backup-key;
- snapshotbestandsnaam;
- SHA-256 van de **exacte snapshotbytes**.

Omdat de exacte bytes zijn gebonden, vallen ook `schema`, `tenant_key`, `backup_key`, `created_at` en de volledige `data`-payload onder de signature.

### Assets

`attestation.json` in de snapshotmap bindt:

- protocolversie;
- type `asset`;
- tenant-key;
- asset-scope;
- snapshotmapnaam;
- SHA-256 van de exacte `manifest.json`-bytes;
- voor ieder payloadbestand: relatief pad, bytegrootte en SHA-256, deterministisch gesorteerd.

Een gewijzigd, toegevoegd, verwijderd of hernoemd assetbestand maakt de attestatie ongeldig.

## Schema en legacybeleid

De activatiestatus wordt uitsluitend bepaald door de aanwezigheid van de root-gepubliceerde publieke sleutel.

### Vóór activatie

- bestaande schema-1 data- en assetsnapshots blijven volgens het oude tenantbindingcontract functioneren;
- nieuwe snapshots blijven schema 1;
- dit maakt het mogelijk de applicatiecode eerst veilig te deployen zonder direct een root-only sleutel/servicewijziging af te dwingen.

### Na activatie

- nieuwe snapshots zijn schema 2;
- een snapshot is pas succesvol aangemaakt nadat een geldige attestatie is terugontvangen, lokaal geverifieerd en duurzaam als sidecar is opgeslagen;
- ontbrekende/onbereikbare attestor of een ongeldige signature verwijdert de zojuist gemaakte snapshot en laat de aanroep falen;
- restore accepteert uitsluitend schema 2 met geldige attestatie;
- schema-1 snapshots blijven op disk herkenbaar als **legacy / ongeauthenticeerd**, maar restore weigert ze expliciet;
- oude snapshots worden **niet achteraf ondertekend**. Back-signing zou ten onrechte historische provenance suggereren die vóór activatie nooit cryptografisch is bewezen.

De bestaande private-store `prewrite-v2` noodjournal blijft alleen vóór activatie bruikbaar. Na activatie mag een niet-geattesteerde noodjournal een mislukte centrale backup niet meer omzeilen; de write faalt dan gesloten.

## Root-only activatie

Activatie is bewust een aparte root-handeling en hoort niet in repositorygestuurde webcode of een tenant-runtimejob.

Vanaf een exact geverifieerde checkout van de gewenste release:

```bash
sudo bash ops/vps-test-deploy/install-backup-attestation
```

De installer:

- compileert/controleert de Python-attestorbron;
- maakt één RSA-3072 keypair wanneer nog geen keypair bestaat;
- roteert bestaande keymaterialen nooit stil;
- installeert private/public key met vaste rootmetadata;
- installeert de attestor buiten `current`/`releases`;
- installeert een geharde systemd-service met onder andere `NoNewPrivileges`, `ProtectSystem=strict`, `ProtectHome` en read-only `/srv/verenigingen`;
- activeert de service en controleert socket, service en geïnstalleerde bron;
- toont attestor- en public-key-SHA-256 voor live evidence.

Herverificatie zonder wijziging:

```bash
sudo bash ops/vps-test-deploy/install-backup-attestation --check
```

Na activatie moet minimaal één nieuwe data- en assetsnapshot worden gemaakt en succesvol worden gelezen/hersteld; een gecontroleerd gemanipuleerde kopie moet vóór restore worden geweigerd. Daarna wordt de normale VPS-testdeploy + authenticated/live regressieketen opnieuw uitgevoerd.

## Keyverlies en rotatie

De private key is onderdeel van de recovery trust boundary. Verwijder of roteer hem niet automatisch.

- verlies van de private key: nieuwe backups falen gesloten;
- verlies van alleen de publieke key: schema-2 restore wordt niet stil naar schema 1 teruggebracht; herstel eerst het juiste public-keymateriaal uit een vertrouwde rootbron;
- bewuste keyrotatie vereist een apart migratieplan omdat bestaande schema-2 signatures aan de huidige `key_id` zijn gebonden.

## Regressiecontract

`tests/security-backup-attestation.php` bewaakt onder meer:

- geldige data-signature;
- payload- en bindingtamper;
- verkeerde tenant en verkeerde backup-key;
- ontbrekende sidecar;
- expliciete legacy schema-1 weigering na activatie;
- volledige assetmanifestset;
- fail-closed backupcreatie wanneer signing ontbreekt;
- uitschakelen van de unauthenticated prewrite-fallback na activatie;
- geen privileged exec/private-keypad in de tenant PHP-client;
- peer-UID/deploymentbinding en schema-2-eis in de root-attestor;
- root-keymetadata en systemd-sandbox in de installer.
