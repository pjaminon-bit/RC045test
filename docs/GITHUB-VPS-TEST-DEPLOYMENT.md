# GitHub Actions → VPS-test (`test.vps.holox.nl`)

`RC045test` blijft de bronrepository voor de platform-/tenanttestomgeving. De oude live-DEV-koppeling naar `https://rc045.nl/dev` is niet langer onderdeel van de actuele VPS-architectuur.

## Nieuwe flow

1. Pull requests draaien bron-, PHP-, security- en regressietests op GitHub runners.
2. Een push/merge naar `main` draait `Validate RC045test` en controleert daarnaast of de bestaande testtenant op `https://test.vps.holox.nl` gezond bereikbaar is.
3. Als `VPS_TEST_DEPLOY_ENABLED=true` is ingesteld, start na een groene `Validate RC045test` de workflow `Deploy RC045test to VPS test`.
4. Die workflow mag via een restricted SSH-key uitsluitend `deploy <40-hex-commit>` aanvragen.
5. De root-owned serverwrapper haalt exact die commit uit `pjaminon-bit/RC045test`, maakt een schone root-owned stagingcheckout en gebruikt de **al actieve vertrouwde** fase-4.7 tooling om eerst `--check` en daarna `--deploy` uit te voeren.
6. Fase 4.7 voert de bestaande kandidaatpreflight, atomische releasewissel, FPM-reload, healthchecks en automatische rollback uit.
7. Na succesvolle activatie test GitHub `https://test.vps.holox.nl`, `/beheer/` en `/healthz.php`.
8. Daarna kan de volledige live browser/security-acceptatie tegen dezelfde VPS-testtenant draaien.

De workflow kopieert dus niet rechtstreeks bestanden over een actieve website en geeft GitHub geen algemene root-shell.

## Eenmalige serverinrichting

Voer dit via de VPS-console/root-shell uit. Controleer paden en inhoud vóór installatie.

### 1. Restricted deploygebruiker

```bash
sudo useradd --create-home --shell /bin/bash vst-deploy
sudo passwd -l vst-deploy
sudo install -d -m 0700 -o vst-deploy -g vst-deploy /home/vst-deploy/.ssh
```

Gebruik voor dit account geen wachtwoordlogin en geen andere SSH-keys.

### 2. Root-owned wrappers installeren

Vanuit een schone checkout van `RC045test`:

```bash
sudo install -o root -g root -m 0755 \
  ops/vps-test-deploy/verenigingsplatform-github-entry \
  /usr/local/bin/verenigingsplatform-github-entry

sudo install -o root -g root -m 0755 \
  ops/vps-test-deploy/verenigingsplatform-github-deploy \
  /usr/local/sbin/verenigingsplatform-github-deploy
```

De entrypoint accepteert uitsluitend `SSH_ORIGINAL_COMMAND` in de vorm `deploy <40-hex-commit>`. De root-wrapper valideert dezelfde commit opnieuw.

### 3. Minimale sudo-regel

Maak `/etc/sudoers.d/verenigingsplatform-github-deploy` met:

```text
vst-deploy ALL=(root) NOPASSWD: /usr/local/sbin/verenigingsplatform-github-deploy *
```

Daarna:

```bash
sudo chown root:root /etc/sudoers.d/verenigingsplatform-github-deploy
sudo chmod 0440 /etc/sudoers.d/verenigingsplatform-github-deploy
sudo visudo -cf /etc/sudoers.d/verenigingsplatform-github-deploy
```

De wildcard geeft niet vrij toegang tot andere programma's: alleen deze root-owned wrapper kan via sudo worden gestart en de wrapper accepteert exact één 40-hex commitargument. De SSH-key zelf wordt bovendien door een forced command beperkt.

### 4. Deploy-key genereren

Genereer een aparte Ed25519-key voor uitsluitend deze workflow. Deel de private key nooit via Git, chatlogs of shell history.

De publieke sleutel komt als enige regel in `/home/vst-deploy/.ssh/authorized_keys` met een forced command:

```text
restrict,command="/usr/local/bin/verenigingsplatform-github-entry" ssh-ed25519 <PUBLIEKE_SLEUTEL> github-rc045test-vps-test
```

Daarna:

```bash
sudo chown vst-deploy:vst-deploy /home/vst-deploy/.ssh/authorized_keys
sudo chmod 0600 /home/vst-deploy/.ssh/authorized_keys
```

`restrict` blokkeert onder andere port forwarding, agent forwarding, X11 forwarding en PTY. Een gebruiker van deze key krijgt geen interactieve shell.

### 5. SSH-hostkey vastpinnen

Neem de echte SSH-hostkey rechtstreeks vanaf de VPS-console, bijvoorbeeld uit `/etc/ssh/ssh_host_ed25519_key.pub`, en bouw daarmee de known-hostsregel voor de host waarmee Actions verbindt (`vps.holox.nl`). Verifieer de fingerprint buiten GitHub om.

Gebruik in CI bewust **geen `ssh-keyscan` als trust-on-first-use**. De workflow eist `StrictHostKeyChecking=yes`.

## GitHub Environment / secrets / variables

Maak bij voorkeur de GitHub Environment `vps-test` aan en plaats daarin:

### Verplicht voor deploy

- secret `VPS_TEST_DEPLOY_KEY`: private Ed25519 deploy-key;
- secret `VPS_TEST_SSH_KNOWN_HOSTS`: vooraf geverifieerde known-hostsregel(s).

Optionele repository/environment variables:

- `VPS_TEST_SSH_HOST` — standaard `vps.holox.nl`;
- `VPS_TEST_SSH_USER` — standaard `vst-deploy`;
- `VPS_TEST_SSH_PORT` — standaard `22`.

Zet pas na volledige serverinrichting:

```text
VPS_TEST_DEPLOY_ENABLED=true
```

Zolang deze variable ontbreekt of niet `true` is, wordt de privileged deployjob overgeslagen. De bronvalidatie en niet-destructieve controle van de reeds actieve `test.vps.holox.nl` blijven wel bruikbaar.

## Authenticated browser-E2E

De oude regressie maakte via SFTP tijdelijk losse auth-/ledenbestanden op `rc045.nl/dev`. Dat model hoort niet bij de VPS-tenant met PostgreSQL/private storage en is verwijderd.

Voor authenticated VPS-E2E gebruiken we uitsluitend dedicated synthetische testaccounts/data in tenant `test`. Pas wanneer die bewust zijn ingericht kunnen de volgende secrets worden toegevoegd:

- `VPS_TEST_ADMIN_USER`;
- `VPS_TEST_MEMBER_USER`;
- `VPS_TEST_E2E_PASSWORD`.

Daarna kan `VPS_TEST_AUTH_E2E_ENABLED=true` worden gezet. Tot dat moment blijft alleen die authenticated job overgeslagen; source-, live-security- en publieke browsertests blijven onafhankelijk.

## Securitygrenzen

- geen FTP/SFTP-deploy naar `rc045.nl/dev`;
- geen algemene SSH-shell voor de GitHub-key;
- geen root-private key in GitHub;
- geen wachtwoord in argv, repository of workflowlog;
- geen dynamische hosttrust via `ssh-keyscan`;
- deploy alleen na groene bronvalidatie op `main`;
- exacte 40-hex commitbinding;
- candidate checkout moet schoon zijn;
- privileged releasehandelingen gebruiken de reeds actieve vertrouwde release-tooling;
- fase-4.7 blijft verantwoordelijk voor immutable staging, atomische switch, health en rollback.
