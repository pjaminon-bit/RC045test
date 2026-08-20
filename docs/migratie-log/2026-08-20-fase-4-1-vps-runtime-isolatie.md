# Fase 4.1 — VPS runtime & Linux-isolatie

Datum: **20-08-2026**

## Doel

Fase 4.1 vertaalt het gevalideerde `deployment.json` uit fase 3.5/3.5.1 naar een concrete, herhaalbare Linux/PHP-FPM runtimegrens per vereniging.

De kernregel blijft: **één gedeelde applicatierelease, maar iedere tenant een eigen OS-identiteit, eigen PHP-FPM pool/socket en uitsluitend schrijfrecht op de eigen private opslag.**

## Nieuwe onderdelen

### `app/deployment/runtime-contract.php`

Centrale pure contractlaag voor:

- opnieuw valideren van `deployment.json`;
- deterministisch herberekenen van OS-user, pool en socket;
- valideren van POSIX-paden en Linux accountnamen;
- opbouwen van `runtime-plan.json`;
- genereren van de tenant-PHP-FPM poolconfig;
- opnieuw valideren van een bestaande runtimebundle tegen de actuele brondeployment.

### `bin/prepare-vps-runtime.php`

Root-vrije CLI die standaard onder `<tenantroot>/runtime/` schrijft:

- `runtime-plan.json`;
- `<tenant-pool>.conf`.

Ondersteunt `--dry-run`, `--force`, expliciete PHP-versie en webserver user/group. Secretachtige CLI-argumenten worden geweigerd.

### `bin/apply-vps-runtime.php`

Heeft twee expliciete modi:

- `--check`: root-vrij; verifieert deployment-SHA, volledig runtimeplan en FPM-config opnieuw;
- `--apply`: Linux + EUID 0 vereist; maakt/verifieert system user/group, filesystemownership/modes en installeert de FPM poolconfig atomisch.

De apply-tool reloadt PHP-FPM bewust niet automatisch.

## Linux identity

Per tenant:

- system user: deterministisch uit tenant-key;
- primary group: dezelfde unieke naam;
- home: `/nonexistent`;
- shell: `/usr/sbin/nologin`;
- supplementary groups: geen.

Een bestaand account met afwijkende group/home/shell wordt fail-closed geweigerd in plaats van stil aangepast.

## Filesystemgrens

Na root-toepassing is het bedoelde model:

- tenantroot: `root:<tenantgroup> 0750`;
- config/runtime.env/tenant.json/deployment.json: `root:<tenantgroup> 0640`;
- runtimebundle: root-owned, groep-leesbaar;
- private root: `<tenantuser>:<tenantgroup>`;
- private directories: `0750`;
- private files: `0640`;
- sessions en uploadtmp: directory `0700`, bestanden `0600`.

Voor recursieve ownershipwijziging wordt iedere symlink in de tenantboom geweigerd.

## Shared-code grens

De gedeelde fysieke release wordt vóór toepassing gescand op:

- symlinks;
- world-writable objecten;
- schrijfbare objecten die eigendom zijn van de tenantuser;
- group-writable objecten gekoppeld aan de tenantgroup.

De apply-tool wijzigt shared-code ownership of modes nooit.

## PHP-FPM

De gegenereerde pool bevat:

- unieke tenantuser/group;
- unieke Unix socket;
- sockettoegang voor geconfigureerde webserver user/group;
- `clear_env=yes`;
- `pm=ondemand` met veilige startwaarden;
- tenant-private `session.save_path`;
- tenant-private `upload_tmp_dir`;
- exact de drie fail-closed `VERENIGING_*` runtimevariabelen.

Geen wachtwoord, DSN, databasecredential, TLS-key of API-token komt in de bundle.

## Testdekking

Nieuw:

`tests/phase41-vps-runtime-isolation.php`

De acceptatietest controleert onder andere:

- twee echte geprovisioneerde tenants;
- fase-3.4 bootstrap en fase-3.5 deployment als echte voorvoorwaarden;
- unieke OS identities/pools/sockets;
- nologin/no-home/no-supplementary-groups contract;
- private sessions/tmp;
- root/private ownershipmodel;
- deterministische/idempotente generatie;
- `--check` zonder root;
- tamperdetectie van FPM-config;
- stale runtimeplan na wijziging van `deployment.json`;
- gemanipuleerde OS identity in deployment;
- output buiten tenantroot;
- symlinkoutput;
- secretachtige CLI-argumenten;
- PHP-versie/accountnaam-injectie;
- expliciete root/Linux guards in apply-tool;
- geen automatische PHP-FPM reload.

## Operationele grens

De daadwerkelijke `--apply` wordt niet in GitHub Actions uitgevoerd en ook niet op DEV-SFTP. Rootacties horen uitsluitend op de toekomstige VPS plaats te vinden nadat het plan met `--check` is gevalideerd.

CI valideert wel alle gegenereerde artifacts en securitycontracten. Daarmee kan 4.1 veilig voorbereid en getest worden voordat er een productie-VPS beschikbaar is.
