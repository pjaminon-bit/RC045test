# Authenticated E2E op VPS-test

Doel: beheerlogin, sessiebeveiliging, autorisatie en het gekoppelde ledenportaal automatisch bewijzen tegen `https://test.vps.holox.nl`, zonder productieachtige persoonsgegevens, permanente testcredentials, FTP/SFTP of een algemene CI-shell.

## Definitief ontwerp

Authenticated E2E gebruikt een tijdelijke fixture per GitHub Actions-run:

1. GitHub genereert op de ephemeral hosted runner een cryptografisch willekeurig wachtwoord.
2. De runner verbindt via dezelfde OIDC/WIF + Tailscale-route als de VPS-deploy.
3. De bestaande `vst-deploy` SSH-key blijft `restrict` + forced-command gebruiken.
4. De forced-command gateway accepteert voor E2E uitsluitend exact `e2e check`, `e2e apply` of `e2e cleanup`.
5. De root-wrapper hardcodeert tenant `test`, `https://test.vps.holox.nl`, `vps-e2e-admin` en `vps-e2e-member`; GitHub kan geen tenant, pad, gebruiker of willekeurig shellcommando meegeven.
6. De PHP-fixturecode draait niet als root maar als de uit `runtime-plan.json` gevalideerde tenant-runtimegebruiker.
7. Het wachtwoord gaat uitsluitend via stdin naar `e2e apply`, wordt gehasht en staat nooit in argv, repository of een permanent GitHub Secret.
8. Playwright voert de authenticated browseracceptatie uit.
9. Een `always()` cleanup verwijdert daarna alleen records die zowel de interne E2E-fixturemarker als tenantmarker `test` dragen.

De fixture bevat twee synthetische accounts, één synthetisch lid en gekoppelde contributie-, commissie-, vergadering/notulen- en taakdata. Alle data gebruikt uitsluitend fictieve waarden.

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
- Wanneer een runner onverwacht wordt afgebroken kan een fixture tijdelijk achterblijven. Het wachtwoord van die run bestaat daarna nergens persistent; een volgende apply roteert de credentials en cleanup is idempotent. Verwijderde accounts maken bestaande sessies bij het volgende verzoek ongeldig via de normale auth-session-check.

## Eenmalige gateway-installatie

Eerst moet de release met de ephemeral lifecycle succesvol naar VPS-test zijn gedeployed. Authenticated E2E blijft tot dat moment uitgeschakeld.

Voer daarna éénmalig als root op `platform` uit:

```bash
bash /srv/verenigingsplatform/current/bin/install-vps-authenticated-e2e-gateway.sh
```

De installer:

- weigert te draaien als `current` niet naar een immutable 40-hex release wijst;
- controleert de bestaande forced-command deployentry en maakt daar eerst een root-only backup van;
- installeert `/usr/local/sbin/verenigingsplatform-github-e2e`;
- voegt een apart `/etc/sudoers.d/verenigingsplatform-github-e2e` toe met drie exacte allowlistregels;
- valideert Bash en de volledige sudoersconfig;
- bewijst dat een willekeurig commando als `uname -a` nog steeds met exitcode 64 wordt geweigerd;
- voert via de echte `vst-deploy` forced-command route een read-only `e2e check` uit.

Verwachte slotregels:

```text
E2E EPHEMERAL CHECK OK  tenant=test storage=pdo fixture=vps-authenticated-e2e-v1
E2E GATEWAY INSTALL OK  backup=/root/verenigingsplatform-github-entry.pre-e2e.<timestamp>
```

Pas nadat die check is bewezen, wordt in een aparte repositorywijziging `live-authenticated` omgezet van de oude secret-gebaseerde methode naar automatisch apply → browseracceptatie → always-cleanup.

## Geen permanente E2E-secrets meer

De uiteindelijke workflow heeft geen `VPS_TEST_ADMIN_USER`, `VPS_TEST_MEMBER_USER` of `VPS_TEST_E2E_PASSWORD` nodig. De gebruikersnamen zijn vaste synthetische testidentiteiten in de server-side allowlist en het wachtwoord bestaat alleen tijdens één GitHub-hosted run.

De bestaande secrets voor de private deployroute (`VPS_TEST_DEPLOY_KEY`, gepinde SSH-hosttrust en Tailscale WIF-instellingen) blijven ongewijzigd en worden alleen binnen environment `vps-test` gebruikt.
