# Fase 4.1 — VPS runtime & Linux-isolatie

Status per **20-08-2026**: implementatie voor de VPS-runtime-laag, inclusief fase 4.1.1 re-audit hardening.

Fase 3.5/3.5.1 legt vast **welke tenant** bij welke gedeelde release, host en PHP-FPM-identiteit hoort. Fase 4.1 vertaalt dat contract naar concrete Linux- en PHP-FPM-artifacts zonder secrets in Git of in de runtimebundle te plaatsen.

## Uitgangspunt

Iedere vereniging krijgt op de VPS:

- één deterministische Linux system user;
- één unieke primary group met dezelfde naam;
- geen interactieve login (`/usr/sbin/nologin`);
- geen home directory (`/nonexistent`);
- geen supplementary groups;
- een UID en GID die niet door een andere account/groepsnaam gedeeld mogen worden;
- één eigen PHP-FPM pool;
- één eigen Unix socket;
- een tenant-private PHP session directory;
- een tenant-private upload temporary directory;
- schrijfrecht op uitsluitend de eigen `private/` boom;
- alleen lees-/traverserechten op de eigen servermetadata die de runtime nodig heeft;
- nooit ownership of schrijfrecht op de gedeelde applicatierelease.

De Linux-accountnaam blijft de deterministische identiteit die fase 3.5 al in `deployment.json` vastlegt (`vst` + 16 hextekens). Daardoor kan een tenant niet via een handmatig gekozen accountnaam naar een andere runtime worden omgebogen.

## 1. Runtimebundle genereren

Na fase 3.5:

```bash
php bin/prepare-vps-runtime.php \
  --deployment=/srv/verenigingen/noorderhaven/deployment.json \
  --php-version=8.5 \
  --web-user=www-data \
  --web-group=www-data
```

Standaard ontstaat:

```text
/srv/verenigingen/noorderhaven/runtime/
├── runtime-plan.json
└── vst-noorderhaven-<hash>.conf
```

De bundle bevat geen wachtwoorden, databasecredentials, TLS-keys of API-tokens.

Voor alleen validatie:

```bash
php bin/prepare-vps-runtime.php \
  --deployment=/srv/verenigingen/noorderhaven/deployment.json \
  --dry-run
```

Een identieke tweede generatie is idempotent. Een afwijkende bestaande bundle wordt alleen bewust met `--force` vervangen.

## 2. Wat de generator opnieuw controleert

`prepare-vps-runtime.php` vertrouwt `deployment.json` niet blind. De tool controleert opnieuw onder andere:

- schema en canonieke tenant-key;
- dat `deployment.json` direct in zijn eigen tenantroot staat;
- config, private root en tenantroot dezelfde provisioned tenant vormen;
- logische en fysieke shared app-root dezelfde release zijn;
- tenantroot en app-root niet overlappen;
- de OS-user, poolnaam en socket exact opnieuw uit de tenant-key kunnen worden berekend;
- `clear_env=true` en `one_pool_per_tenant=true`;
- het runtime-environment exact uit de drie fail-closed tenantvariabelen bestaat;
- runtime-output binnen de eigen tenantroot blijft;
- geen tenantpad via een symlink loopt.

Hierdoor is een handmatig gemanipuleerd `deployment.json` geen geldige bron voor root-automation.

## 3. Gegenereerde PHP-FPM pool

De poolconfig bevat conceptueel:

```ini
[vst-noorderhaven-...]
user = vst...
group = vst...
listen = "/run/php/vst-noorderhaven-....sock"
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

clear_env = yes
pm = ondemand
pm.max_children = 5
pm.process_idle_timeout = 10s
pm.max_requests = 500

php_admin_value[session.save_path] = "/srv/verenigingen/noorderhaven/private/sessions"
php_admin_value[upload_tmp_dir] = "/srv/verenigingen/noorderhaven/private/tmp"

env[VERENIGING_REQUIRE_TENANT_CONFIG] = "1"
env[VERENIGING_CONFIG_FILE] = "/srv/verenigingen/noorderhaven/config.php"
env[VERENIGING_PRIVATE_ROOT] = "/srv/verenigingen/noorderhaven/private"
```

De `pm.*` waarden zijn veilige startwaarden en geen tenant-securitygrens. Capaciteitstuning kan later plaatsvinden zonder de identiteit, socket of filesystemgrens te veranderen.

## 4. Bundle root-vrij controleren

Voor een rootactie wordt de bundle opnieuw gecontroleerd:

```bash
php bin/apply-vps-runtime.php \
  --plan=/srv/verenigingen/noorderhaven/runtime/runtime-plan.json \
  --check
```

`--check`:

- vereist geen root;
- verifieert de SHA-256 van de bron-`deployment.json`;
- bouwt het runtimeplan opnieuw deterministisch op;
- vergelijkt het volledige plan;
- genereert de verwachte FPM-config opnieuw en vergelijkt ook die byte-inhoudelijk.

Als `deployment.json`, `runtime-plan.json` of de poolconfig sinds generatie handmatig is aangepast, faalt de check gesloten.

## 5. Root-toepassing

Pas op de echte Linux-VPS:

```bash
sudo php bin/apply-vps-runtime.php \
  --plan=/srv/verenigingen/noorderhaven/runtime/runtime-plan.json \
  --apply \
  --fpm-pool-dir=/etc/php/8.5/fpm/pool.d
```

`--apply` vereist expliciet Linux en EUID 0.

De tool:

1. maakt de unieke system group aan als die nog niet bestaat;
2. weigert een bestaande tenantgroep met expliciete groepsleden;
3. weigert dezelfde GID onder een andere groepsnaam of als primary group van een andere account;
4. maakt de unieke system user aan met `/usr/sbin/nologin` en zonder home;
5. controleert bij bestaande accounts dat primary group, home en shell exact kloppen;
6. weigert iedere supplementary group;
7. weigert een UID die ook door een andere accountnaam wordt gebruikt;
8. controleert dat de tenant-runtimeuser **geen actieve processen** heeft voordat ownership/modes worden aangepast; bij reapply moet de tenant-FPM-pool dus eerst worden gestopt;
9. controleert de fysieke shared release voordat ownership wordt aangepast;
10. maakt de shared release **nooit** tenant-owned;
11. maakt tenantmetadata `root:<tenantgroup>` mode `0640`;
12. maakt tenantroot `root:<tenantgroup>` mode `0750`;
13. maakt `private/` tenant-owned, directories `0750`, bestanden `0640`;
14. maakt `sessions/` en `tmp/` strenger: directory `0700`, bestanden `0600`;
15. weigert symlinks in de tenantboom vóór recursieve ownershipwijzigingen;
16. plaatst de FPM poolconfig atomisch als `root:root` mode `0644`.

Een afwijkende bestaande FPM poolconfig wordt niet overschreven zonder expliciet `--force`.

### Waarom UID/GID-exclusiviteit expliciet wordt gecontroleerd

Linux filesystemrechten werken op numerieke UID/GID. Alleen een moeilijk te raden tenantgroepsnaam is daarom geen voldoende bewijs van isolatie wanneer een beheerder of externe directory vooraf al een account met dezelfde numerieke identiteit heeft. Fase 4.1.1 controleert via NSS/`getent` dat:

- de tenant-GID niet onder een andere groepsnaam bestaat;
- geen andere account de tenant-GID als primary group gebruikt;
- de tenantgroep geen expliciete leden bevat;
- de tenant-UID niet onder een andere accountnaam bestaat.

Kan de volledige NSS-database niet fail-closed worden uitgelezen, dan wordt root-toepassing geweigerd.

### Waarom een reapply een stilstaande runtime vereist

Na de eerste livegang is `private/` eigendom van de tenant-runtimeuser. Een actieve PHP-FPM worker zou tijdens een recursieve ownership/mode-run bestanden kunnen creëren of vervangen. Daarom weigert `--apply` sinds fase 4.1.1 zolang `pgrep -u <tenantuser>` actieve processen vindt. Eerst de tenantpool stoppen, daarna toepassen, config testen en pas vervolgens gecontroleerd starten/reloaden.

## 6. Shared-code grens

De apply-tool gebruikt de fysieke `shared_code.app_root_real` als controlebron. Voor ieder object in de release geldt:

- geen symlink in de immutable releaseboom;
- niet world-writable;
- niet schrijfbaar via ownership van de tenantuser;
- niet schrijfbaar via de unieke tenantgroup.

De apply-tool voert bewust **geen `chown` of `chmod` uit op shared code**. Releaseownership hoort bij de centrale release/deploymentlaag en niet bij een tenant.

## 7. Waarom PHP-FPM niet automatisch wordt herladen

De root-tool schrijft de poolconfig, maar voert geen `systemctl reload`, `service` of vergelijkbare opdracht uit. Dit is bewust fail-safe:

1. eerst de volledige PHP-FPM configuratie testen met de server/distro-specifieke testopdracht;
2. controleren dat alle tenantpools en sockets onderling geldig zijn;
3. pas daarna de juiste PHP-FPM service expliciet reloaden.

Zo kan één nieuw tenantbestand niet automatisch een bestaande productie-runtime herstarten voordat de complete configuratie is gevalideerd.

## 8. Filesystemmodel na toepassing

```text
/srv/verenigingen/noorderhaven/          root:vst... 0750
├── config.php                           root:vst... 0640
├── runtime.env                          root:vst... 0640
├── tenant.json                          root:vst... 0640
├── deployment.json                      root:vst... 0640
├── runtime/                             root:vst... 0750
│   ├── runtime-plan.json                root:vst... 0640
│   └── vst-noorderhaven-....conf        root:vst... 0640
└── private/                             vst...:vst... 0750
    ├── sessions/                        vst...:vst... 0700
    ├── tmp/                             vst...:vst... 0700
    └── overige private directories      vst...:vst... 0750
```

De geïnstalleerde FPM-config onder `/etc/.../pool.d/` is `root:root 0644` en bevat geen secrets.

## 9. Nog niet in fase 4.1

Fase 4.1 configureert geen:

- Apache/Nginx tenant-vhost — fase 4.2;
- DNS — fase 4.3;
- TLS/certificaat/renewal — fase 4.4;
- PDO databaseuser/secrets — fase 4.5;
- monitoring/logaggregatie — fase 4.6;
- centrale release/rollbackautomation — fase 4.7;
- tenant disable/export/remove lifecycle — fase 4.8.

De daadwerkelijke root-`--apply` wordt pas op de toekomstige VPS uitgevoerd. De repository en CI kunnen nu wel het volledige plan, de FPM-config en alle veiligheidsvoorwaarden deterministisch valideren.
