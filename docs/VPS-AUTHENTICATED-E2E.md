# Authenticated E2E op VPS-test

Doel: beheerlogin, sessiebeveiliging, autorisatie en het gekoppelde ledenportaal automatisch bewijzen tegen `https://test.vps.holox.nl`, zonder productieachtige persoonsgegevens, permanente testcredentials, FTP/SFTP of een algemene CI-shell.

## Definitief ontwerp

Authenticated E2E gebruikt een tijdelijke fixture per succesvolle VPS-testdeploy:

1. `deploy-vps-test` activeert eerst de immutable release en voert de publieke smoke tests uit.
2. De verplichte vervolgjob `live-authenticated` draait daarna in dezelfde workflow en hetzelfde GitHub Environment `vps-test`.
3. GitHub genereert op de ephemeral hosted runner een cryptografisch willekeurig wachtwoord.
4. De runner verbindt via dezelfde OIDC/WIF + Tailscale-route als de VPS-deploy.
5. De bestaande `vst-deploy` SSH-key blijft `restrict` + forced-command gebruiken.
6. De forced-command gateway accepteert voor E2E uitsluitend exact `e2e check`, `e2e apply` of `e2e cleanup`.
7. De root-wrapper hardcodeert tenant `test`, `https://test.vps.holox.nl`, `vps-e2e-admin` en `vps-e2e-member`; GitHub kan geen tenant, pad, gebruiker of willekeurig shellcommando meegeven.
8. De PHP-fixturecode draait niet als root maar als de uit `runtime-plan.json` gevalideerde tenant-runtimegebruiker.
9. Het wachtwoord gaat uitsluitend via stdin naar `e2e apply`, wordt gehasht en staat nooit in argv, repository of een permanent GitHub Secret.
10. Een eventuele gemarkeerde fixture van een abrupt afgebroken vorige run wordt eerst idempotent opgeruimd.
11. Playwright voert de authenticated browseracceptatie uit.
12. Een `always()` cleanup verwijdert daarna alleen records die zowel de interne E2E-fixturemarker als tenantmarker `test` dragen.
13. Pas wanneer de volledige deployworkflow inclusief authenticated E2E groen is, kan `Full regression acceptance` als `workflow_run` doorgaan met de overige live security- en browseracceptatie.

De fixture bevat twee synthetische accounts, één synthetisch lid en gekoppelde contributie-, commissie-, vergadering/notulen- en taakdata. Alle data gebruikt uitsluitend fictieve waarden.

## Waarom authenticated E2E in de deployworkflow staat

De Tailscale Workload Identity Federation is bewust beperkt tot:

```text
pjaminon-bit/RC045test/.github/workflows/deploy-vps-test.yml@refs/heads/main
```

Daarom krijgt `full-regression.yml` geen eigen Tailscale/WIF-toegang. `live-authenticated` is een tweede job in `deploy-vps-test.yml` en gebruikt dezelfde bestaande, smalle federated identity. Zo hoeft de trust policy niet met een extra workflow te worden uitgebreid.

Dit betekent ook dat een succesvolle `Deploy RC045test to VPS test` run niet alleen bewijst dat de release is geplaatst en publiek bereikbaar is, maar ook dat de echte beheer- en ledenlogin op die release met tijdelijke testidentiteiten heeft gewerkt en de fixture daarna weer is verwijderd.

## Securitygrenzen

- Publieke TCP/22 blijft dicht; verkeer loopt via Tailscale.
- Er komt geen self-hosted GitHub runner.
- De bestaande deployment key krijgt geen algemene shell.
- De E2E-sudoersregels noemen alleen de drie exacte wrappercommando's en bevatten geen wildcard.
- De root-wrapper bepaalt zelf de actieve immutable release en accepteert geen releasepad uit GitHub.
- De wrapper accepteert uitsluitend de vaste testtenant en valideert de runtime-user als `vst` plus 16 hextekens.
- Domeinwrites lopen in één PostgreSQL-transactie.
- Voor een authwijziging wordt een tenantlokale backup gemaakt; bij een databasefout wordt de oorspronkelijke authstore teruggezet.
- Een gereserveerde fixture-ID die al door niet-fixture data wordt gebruikt, stopt fail-closed.
- Cleanup verwijdert nooit op alleen gebruikersnaam of ID: alleen expliciet gemarkeerde E2E-records van tenant `test` worden verwijderd.
- De workflow gebruikt dezelfde gepinde SSH-hosttrust en dezelfde private Tailscale-host als deployment.
- De workflowconcurrency heeft `cancel-in-progress: false`; twee deploy/E2E-runs kunnen daardoor niet tegelijk dezelfde fixture gebruiken.
- Wanneer een runner buiten GitHub om abrupt verdwijnt kan een fixture tijdelijk achterblijven. Het wachtwoord van die run bestaat daarna nergens persistent; de volgende run begint met cleanup en roteert de credentials. Cleanup is idempotent.

## Eenmalige gateway-installatie

De gateway is op 30 augustus 2026 op VPS-test geïnstalleerd vanuit de toen actieve immutable release. De installer voerde daarbij ook via de echte `vst-deploy` forced-command route een read-only check uit.

Bewezen slotregels:

```text
E2E EPHEMERAL CHECK OK  tenant=test storage=pdo fixture=vps-authenticated-e2e-v1
E2E GATEWAY INSTALL OK  backup=/root/verenigingsplatform-github-entry.pre-e2e.20260830T091817Z.29465
```

De installer zelf blijft beschikbaar voor gecontroleerde herinstallatie of herstel:

```bash
bash /srv/verenigingsplatform/current/bin/install-vps-authenticated-e2e-gateway.sh
```

Hij:

- weigert te draaien als `current` niet naar een immutable 40-hex release wijst;
- controleert de bestaande forced-command deployentry en maakt daar eerst een root-only backup van;
- installeert `/usr/local/sbin/verenigingsplatform-github-e2e`;
- voegt een apart `/etc/sudoers.d/verenigingsplatform-github-e2e` toe met drie exacte allowlistregels;
- valideert Bash en de volledige sudoersconfig;
- bewijst dat een willekeurig commando als `uname -a` nog steeds met exitcode 64 wordt geweigerd;
- voert via de echte `vst-deploy` forced-command route een read-only `e2e check` uit;
- draait de serverwijzigingen terug als een installercheck faalt voordat de installatie is gecommit.

## Geen permanente authenticated E2E-secrets

De automatische workflow heeft geen `VPS_TEST_ADMIN_USER`, `VPS_TEST_MEMBER_USER`, `VPS_TEST_E2E_PASSWORD` of aparte `VPS_TEST_AUTH_E2E_ENABLED` nodig. De gebruikersnamen zijn vaste synthetische testidentiteiten in de server-side allowlist en het wachtwoord bestaat alleen tijdens één GitHub-hosted run.

De bestaande secrets voor de private deployroute blijven ongewijzigd:

- `VPS_TEST_DEPLOY_KEY`
- `VPS_TEST_SSH_KNOWN_HOSTS`
- `TS_OAUTH_CLIENT_ID`
- `TS_AUDIENCE`

Ze blijven uitsluitend beschikbaar binnen environment `vps-test`. Eventuele oude authenticated-E2E secrets kunnen na succesvolle ingebruikname van deze automatische route uit GitHub worden verwijderd; de workflow refereert er niet meer aan.
