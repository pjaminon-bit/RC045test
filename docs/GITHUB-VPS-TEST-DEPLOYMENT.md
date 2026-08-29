# GitHub Actions → VPS-test (`test.vps.holox.nl`)

`RC045test` blijft de bronrepository voor de platform-/tenanttestomgeving. De oude live-DEV-koppeling naar `https://rc045.nl/dev` is niet langer onderdeel van de actuele VPS-architectuur.

## Nieuwe flow

1. Pull requests draaien bron-, PHP-, security- en regressietests op GitHub runners.
2. Een push/merge naar `main` draait `Validate RC045test` en controleert daarnaast of de bestaande testtenant op `https://test.vps.holox.nl` gezond bereikbaar is.
3. Als `VPS_TEST_DEPLOY_ENABLED=true` is ingesteld, start na een groene `Validate RC045test` de workflow `Deploy RC045test to VPS test`.
4. De GitHub-hosted runner krijgt via GitHub OIDC/Tailscale workload identity tijdelijk de tag `tag:github-rc045test` en wordt als ephemeral node aan het tailnet toegevoegd.
5. De runner controleert eerst of VPS `platform` via het private Tailscale-adres bereikbaar is en maakt daarna pas SSH-verbinding naar dat private adres. Er is geen publieke SSH-poort nodig.
6. De restricted SSH-key mag uitsluitend `deploy <40-hex-commit>` aanvragen. `HostKeyAlias=vps.holox.nl` houdt de eerder buiten GitHub geverifieerde SSH-hostkey geldig, ook al loopt het netwerkpad via Tailscale.
7. De root-owned serverwrapper haalt exact die commit uit `pjaminon-bit/RC045test`, maakt een schone root-owned stagingcheckout en gebruikt de **al actieve vertrouwde** fase-4.7 tooling om eerst `--check` en daarna `--deploy` uit te voeren.
8. Fase 4.7 voert de bestaande kandidaatpreflight, atomische releasewissel, FPM-reload, healthchecks en automatische rollback uit.
9. Na succesvolle activatie test GitHub `https://test.vps.holox.nl`, `/beheer/` en `/healthz.php`.
10. Daarna kan de volledige live browser/security-acceptatie tegen dezelfde VPS-testtenant draaien.

De workflow kopieert dus niet rechtstreeks bestanden over een actieve website, geeft GitHub geen algemene root-shell en vereist geen SSH-poort die vanaf internet bereikbaar is.

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

Neem de echte SSH-hostkey rechtstreeks vanaf de VPS-console, bijvoorbeeld uit `/etc/ssh/ssh_host_ed25519_key.pub`, en bouw daarmee de known-hostsregel voor de logische hostnaam `vps.holox.nl`. Verifieer de fingerprint buiten GitHub om.

De workflow verbindt op netwerkniveau met het Tailscale-adres, maar gebruikt `HostKeyAlias=vps.holox.nl`. Daardoor blijft dezelfde geverifieerde hostkey afdwingbaar. Gebruik in CI bewust **geen `ssh-keyscan` als trust-on-first-use**; de workflow eist `StrictHostKeyChecking=yes`.

## Tailscale private netwerkpad

VPS `platform` moet als gewone Tailscale-node in hetzelfde tailnet staan. De huidige geaccepteerde VPS-testconfiguratie gebruikt:

```text
platform = 100.104.242.66
```

Controle:

```bash
tailscale status
tailscale ip -4
```

De publieke firewall/NAT hoeft TCP/22 niet naar de VPS door te sturen. SSH hoeft alleen lokaal op de VPS te luisteren zodat verkeer over de Tailscale-interface kan aankomen.

### Least-privilege tag en grant

Maak de tag `tag:github-rc045test` en geef die runner alleen toegang tot TCP/22 op `platform`. Tailscale Grants zijn hiervoor het aanbevolen moderne policy-model.

Een voorbeeld voor een persoonlijk tailnet is:

```json
{
  "hosts": {
    "platform-vps": "100.104.242.66"
  },
  "tagOwners": {
    "tag:github-rc045test": ["autogroup:admin"]
  },
  "grants": [
    {
      "src": ["autogroup:member"],
      "dst": ["*"],
      "ip": ["*"]
    },
    {
      "src": ["tag:github-rc045test"],
      "dst": ["platform-vps"],
      "ip": ["tcp:22"]
    }
  ]
}
```

Let op: als het tailnet nog de standaard brede grant met `"src": ["*"]` en `"dst": ["*"]` gebruikt, moet die **niet** blijven staan naast de runnergrant. Zo'n brede bronregel zou ook de getagde CI-runner toegang tot het hele tailnet geven. Pas de bestaande policy bewust aan op de eigen apparaten en controleer de policytests vóór opslaan.

## Tailscale workload identity voor GitHub Actions

Gebruik geen langlevende Tailscale authkey of OAuth client secret. Maak in de Tailscale adminconsole onder **Trust credentials** een OpenID Connect/federated identity voor GitHub Actions.

Aanbevolen binding:

- Issuer: GitHub Actions (`https://token.actions.githubusercontent.com`);
- scope: `auth_keys` met schrijfrecht;
- tag: `tag:github-rc045test`;
- beperk de identiteit tot repository `pjaminon-bit/RC045test`, environment `vps-test` en ref `refs/heads/main`;
- gebruik waar mogelijk aanvullende claimregels voor `repository`, `repository_id`, `environment` en `ref`.

GitHub kan voor nieuwere repositories een immutable OIDC-subject met repository-ID's gebruiken. Baseer daarom de Subject/claimregels op de waarden die GitHub/Tailscale voor deze repository toont en gebruik aanvullende exacte claimmatches in plaats van een onnodig brede wildcard.

Na het genereren geeft Tailscale een **Client ID** en **Audience**. Dit zijn geen private sleutels, maar we bewaren ze in de GitHub Environment als encrypted secrets zodat de workflowconfiguratie uniform blijft.

De GitHub Action gebruikt een gepinde `tailscale/github-action` v4-commit, Tailscale client `1.94.2`, `id-token: write`, `TS_OAUTH_CLIENT_ID`, `TS_AUDIENCE` en `tag:github-rc045test`. De CI-node is ephemeral en wordt na de workflow weer uit het tailnet verwijderd.

## GitHub Environment / secrets / variables

Gebruik de GitHub Environment `vps-test`.

### Verplicht voor deploy

Environment secrets:

- `VPS_TEST_DEPLOY_KEY`: private Ed25519 deploy-key;
- `VPS_TEST_SSH_KNOWN_HOSTS`: vooraf geverifieerde known-hostsregel(s);
- `TS_OAUTH_CLIENT_ID`: Tailscale federated identity Client ID;
- `TS_AUDIENCE`: Tailscale federated identity Audience.

Optionele repository/environment variables:

- `VPS_TEST_TAILSCALE_HOST` — standaard `100.104.242.66`;
- `VPS_TEST_SSH_USER` — standaard `vst-deploy`;
- `VPS_TEST_SSH_PORT` — standaard `22`.

Er is bewust geen publieke `VPS_TEST_SSH_HOST` meer nodig.

Zet pas na volledige Tailscale-policy, workload identity en GitHub-secretinrichting:

```text
VPS_TEST_DEPLOY_ENABLED=true
```

Zolang deze variable ontbreekt of niet `true` is, wordt de privileged deployjob overgeslagen. De bronvalidatie en niet-destructieve controle van de reeds actieve `test.vps.holox.nl` blijven wel bruikbaar. Handmatige privileged deployments zijn bovendien alleen vanaf `main` toegestaan.

## Authenticated browser-E2E

De oude regressie maakte via SFTP tijdelijk losse auth-/ledenbestanden op `rc045.nl/dev`. Dat model hoort niet bij de VPS-tenant met PostgreSQL/private storage en is verwijderd.

Voor authenticated VPS-E2E gebruiken we uitsluitend dedicated synthetische testaccounts/data in tenant `test`. Pas wanneer die bewust zijn ingericht kunnen de volgende secrets worden toegevoegd:

- `VPS_TEST_ADMIN_USER`;
- `VPS_TEST_MEMBER_USER`;
- `VPS_TEST_E2E_PASSWORD`.

Daarna kan `VPS_TEST_AUTH_E2E_ENABLED=true` worden gezet. Tot dat moment blijft alleen die authenticated job overgeslagen; source-, live-security- en publieke browsertests blijven onafhankelijk.

## Securitygrenzen

- geen FTP/SFTP-deploy naar `rc045.nl/dev`;
- geen publieke SSH-poort nodig voor GitHub Actions;
- GitHub → VPS SSH loopt uitsluitend over het private Tailscale-pad;
- ephemeral CI-node met `tag:github-rc045test`;
- geen langlevende Tailscale authkey of OAuth-secret in GitHub;
- workload identity wordt aan repository/environment/main gebonden;
- geen algemene SSH-shell voor de GitHub-key;
- geen root-private key in GitHub;
- geen wachtwoord in argv, repository of workflowlog;
- geen dynamische hosttrust via `ssh-keyscan`;
- deploy alleen na groene bronvalidatie op `main`;
- handmatige deploy alleen vanaf `main`;
- exacte 40-hex commitbinding;
- candidate checkout moet schoon zijn;
- privileged releasehandelingen gebruiken de reeds actieve vertrouwde release-tooling;
- fase-4.7 blijft verantwoordelijk voor immutable staging, atomische switch, health en rollback.
