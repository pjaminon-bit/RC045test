# First-VPS productiebootstrap — fase 5.2

Fase 5.2 verbindt de eerder gebouwde productiecontracten tot één gecontroleerde eerste installatie van het Verenigingsplatform op een schone Debian/Ubuntu-VPS. Fase 5.2.1 heeft deze flow aanvullend gehard op bronintegriteit, preflight, subprocessen en privileged filesystemmutaties.

## Wat deze fase automatiseert

De bootstrap voert in vaste volgorde uit:

1. preflight van OS, Apache, PHP-FPM, PostgreSQL, Certbot, Fail2ban, systemd en benodigde binaries/modules;
2. brontrust controleren: root-owned, geen group/world-writable bronboom en exact manifest;
3. Git source-binding controleren: exact repository-root, exact geplande 40-hex commit en schone working tree;
4. volledige production preflight uitvoeren vóór de eerste mutatie;
5. platformstate-basis veilig voorbereiden: `/var/lib/verenigingsplatform` als root-owned `0711`, zodat publieke ACME-paden traverseerbaar zijn zonder directorylisting van private state;
6. fase 4.7 immutable release-bootstrap naar `/srv/verenigingsplatform/releases/<commit>` en atomische `current`;
7. exacte live DNS-readiness van de platformbeheer-host;
8. neutrale HTTP/HTTPS catch-alls en neutraal reject-certificaat;
9. tijdelijke HTTP-01-route voor platformbeheer;
10. eerste Certbot/ACME-account indien nog niet aanwezig en exact één platformbeheer-certificaat via webroot;
11. veilige platformoperator via bcrypt en STDIN;
12. lege tenantbasis veilig voorbereiden als root-owned `0750`, zodat de eerste control-plane snapshot op een schone VPS een geldige lege tenantroot ziet;
13. fase 5.1 control-plane installeren en activeren;
14. eerste tenant provisionen met PDO/PostgreSQL-profiel;
15. eerste tenantbeheerder via STDIN;
16. bestaande fase 3.5 en 4.1 t/m 4.5 plannen genereren en controleren;
17. tenant Linux/PHP-FPM runtime en inactieve Apache-artifacts installeren;
18. PostgreSQL provisionen terwijl de tenant-runtime nog inactief is;
19. tenant-FPM pas daarna valideren en reloaden;
20. tenant-DNS via de bestaande fase-4.3 check aantoonbaar ready maken;
21. tenant-TLS via fase 4.4 activeren;
22. monitoring via fase 4.6 activeren;
23. tenant via fase 4.8 als bestaande gezonde actieve tenant adopteren;
24. control-plane snapshot verversen en eindhealth bewijzen.

De bootstrap herschrijft de bestaande fase-4 tools niet. De bestaande scripts blijven per stap de bron van waarheid en voeren hun eigen fail-closed validatie opnieuw uit.

## Production preflight vóór mutatie

Een echte `--apply` of `--resume` doet eerst alle controles die zonder productie-mutatie mogelijk zijn. Pas daarna mag het bootstrap-lockbestand of state worden aangemaakt of aangepast.

De preflight bewijst onder meer:

- Debian/Ubuntu als ondersteund OS;
- de exact geplande PHP- en PHP-FPM-versie;
- vereiste PHP- en Apache-modules;
- Apache minimumversie en geldige `apache2ctl configtest`;
- geldige PHP-FPM configuratie;
- geldige Fail2ban configuratie;
- actieve Apache-, PHP-FPM- en PostgreSQL-services;
- werkende lokale PostgreSQL adminverbinding met exact `SELECT 1`;
- vereiste productie-directories als root-owned en niet group/world-writable;
- aanwezigheid van vaste, absolute executables voor privileged childprocessen;
- live platform-DNS volgens het exact geplande DNS-profiel.

Een fout in één van deze controles stopt de bootstrap voordat serverstate wordt gemuteerd.

## Bron- en commitbinding

De first-release bootstrap accepteert niet alleen een inhoudsmanifest. Fase 5.2.1 bindt de bron ook expliciet aan Git:

- `git rev-parse --show-toplevel` moet exact de geplande source-root teruggeven;
- `HEAD^{commit}` moet exact gelijk zijn aan de 40-hex commit uit het bootstrapplan;
- `git status --porcelain=v1 --untracked-files=all` moet leeg zijn;
- de volledige source-tree moet root-owned en niet group/world-writable zijn;
- het berekende fase-4.7 release-manifest moet exact overeenkomen met de hash in het 5.2-plan.

Daarmee kan een gewijzigde, vuile, verkeerd uitgecheckte of naar een andere commit geschoven bronboom niet stil als first-release worden geïnstalleerd.

## Gedeelde privileged subprocess-runner

Privileged productie-tools gebruiken één gedeelde subprocess-runner. Die runner:

- gebruikt geen shell-expansie;
- accepteert alleen absolute executablepaden;
- draint stdout en stderr gelijktijdig met `stream_select`, zodat grote child-output geen pipe-deadlock veroorzaakt;
- kan tegelijk STDIN schrijven en stdout/stderr blijven drainen;
- begrenst runtime en output fail-closed.

PATH-afhankelijke childtools worden vóór uitvoering naar vaste, gecontroleerde absolute binaries vertaald. Dit geldt ook voor child-executables achter bijvoorbeeld `runuser --`.

## Bewuste grenzen

De bootstrap **schrijft geen DNS-records bij een provider**. Voor zowel de platformbeheer-host als de eerste tenant moeten de A/AAAA- of CNAME-records vooraf bij de DNS-provider worden ingesteld. Het plan bevat uitsluitend de exact verwachte publieke DNS-uitkomst; providercredentials zijn verboden.

Packages worden niet automatisch met `apt` geïnstalleerd. Een ontbrekende of te oude dependency stopt de preflight vóór de productiebootstrap verdergaat. Dit voorkomt dat een generiek platformscript onverwacht systeemsoftware of repositories wijzigt.

Wachtwoorden staan nooit in het bootstrapplan, Git, argv, environment-variables of logs. Zolang operator en eerste tenantbeheerder nog niet beide veilig bestaan, worden de noodzakelijke secrets uitsluitend via `--secrets-stdin` aangeleverd. Secrets worden pas in de stage gelezen waarin ze nodig zijn en daarna direct uit de runtimevariabelen gewist.

## Plan voorbereiden

Voorbeeld met directe DNS-records naar één VPS-adres:

```bash
php bin/prepare-first-vps-bootstrap.php \
  --source=/root/staging/RC045test \
  --commit=<VOLLEDIGE_40_HEX_COMMIT> \
  --output=/root/verenigingsplatform-bootstrap \
  --platform-host=beheer.platform.example \
  --platform-strategy=direct \
  --platform-ipv4=203.0.113.10 \
  --tenant-key=voorbeeld \
  --tenant-name='Voorbeeldvereniging' \
  --tenant-host=voorbeeld.example \
  --tenant-strategy=direct \
  --tenant-ipv4=203.0.113.10 \
  --operator-user=platformadmin \
  --php-version=8.5
```

Voor een CNAME-profiel gebruik je `--platform-strategy=cname --platform-cname=vps.example.net` of dezelfde tenantopties. De opgegeven IPv4/IPv6-adressen blijven de exact verwachte terminale adressen.

Voor de eerste VPS-proef gebruiken we Ubuntu Server 26.04 LTS met PHP 8.5. Zie `docs/VPS-READINESS.md` voor de package- en servicepreflight. De exacte releasecommit wordt niet in deze generieke handleiding vastgepind; gebruik uitsluitend de 40-hex kandidaat die in GitHub issue #39 is bevroren.

Standaardpaden:

- platformroot: `/srv/verenigingsplatform`;
- tenantbasis: `/srv/verenigingen`;
- lifecycle/control-plane state: `/var/lib/verenigingsplatform`;
- platform ACME-webroot: `/var/lib/verenigingsplatform/acme/control-plane`.

De top-level statebasis `/var/lib/verenigingsplatform` is bewust `root:root 0711`: niet-root processen kunnen de map niet listen of lezen, maar Apache kan wel door het pad traverseren naar expliciet publiek gemaakte ACME challenge-directories. Private onderliggende state houdt zijn eigen strengere modes.

## Root-vrije controle

```bash
php bin/apply-first-vps-bootstrap.php \
  --plan=/root/verenigingsplatform-bootstrap/first-vps-bootstrap-plan.json \
  --check
```

Deze controle verifieert het plan, de releasebron en alle 5.2-artifacts opnieuw en muteert niets. De production-only service-, live-DNS- en root-trustchecks worden bij `--apply`/`--resume` uitgevoerd voordat de eerste productie-mutatie plaatsvindt.

## Eerste uitvoering en secrets

`--secrets-stdin` gebruikt **JSON Lines (JSONL)**: iedere nog benodigde credential staat op een eigen regel en iedere regel bevat exact één sleutel. De bootstrap leest zo'n regel pas in de stage waarin die credential werkelijk nodig is.

Voor een volledig nieuwe first-VPS apply zijn dit, in deze volgorde:

```jsonl
{"operator_password":"..."}
{"tenant_admin_password":"..."}
```

De wachtwoorden moeten 14 t/m 200 tekens lang zijn en mogen geen controletekens bevatten. Zet echte wachtwoorden niet in shell history, argv, environment-variables of Git.

Maak operationeel bij voorkeur eerst een root-only tijdelijk JSONL-bestand in `/run` met mode `0600`, controleer de rechten en start daarna pas de bootstrap. Zo kunnen verborgen invoerprompts nooit vermengd raken met gelijktijdige bootstrap-output. Een volledige apply gebruikt vervolgens bijvoorbeeld:

```bash
php bin/apply-first-vps-bootstrap.php \
  --plan=/root/verenigingsplatform-bootstrap/first-vps-bootstrap-plan.json \
  --apply \
  --secrets-stdin < /run/first-vps-secrets.jsonl
```

Verwijder het tijdelijke bestand direct na de run, ook als de bootstrap faalt.

### Secrets bij `--resume`

Lever bij resume **alleen credentials aan die na het huidige checkpoint nog ontbreken**, in de volgorde waarin de resterende stages ze lezen:

- vóór `operator_ready`: eerst één regel met alleen `operator_password`, daarna — als `tenant_admin_ready` ook nog moet volgen — één regel met alleen `tenant_admin_password`;
- vanaf `operator_ready` maar vóór `tenant_admin_ready`: alleen één regel met `tenant_admin_password`;
- vanaf `tenant_admin_ready`: geen `--secrets-stdin` meer nodig.

Een al verbruikte credential opnieuw vooraan meesturen is fout: de volgende stage verwacht exact zijn eigen sleutel en weigert een andere of gecombineerde JSON-regel fail-closed.

## Checkpoints en hervatten

De root-owned state staat in:

`/var/lib/verenigingsplatform/bootstrap/first-vps-state.json`

De state bevat geen secrets en is cryptografisch gebonden aan de SHA-256 van exact het gebruikte 5.2-plan. De globale lock staat in `/run/lock/verenigingsplatform-first-bootstrap.lock`.

Na iedere bewezen stap wordt een checkpoint geschreven. Een fout door bijvoorbeeld nog niet gepropageerde DNS stopt de bootstrap. Nadat de externe oorzaak is opgelost wordt dezelfde planfile met `--resume` hervat. Een gewijzigd plan wordt geweigerd.

Bij resume wordt een reeds geslaagde release-bootstrap niet blind opnieuw uitgevoerd. De bestaande `current`, release-marker, manifestbinding en release-state worden opnieuw fail-closed bewezen. Ook vervolgstappen controleren opnieuw dat de actieve release nog exact aan commit en manifest is gebonden.

`--status` toont alleen de server-side bootstrapstage en planbinding.

## DNS-readiness

Zowel platformbeheer als tenant gebruiken minimaal drie consistente metingen met minimaal twee seconden ertussen. Directe profielen vereisen exact de verwachte A/AAAA-set en geen CNAME. CNAME-profielen vereisen exact één hop en exact de verwachte terminale A/AAAA-set. Extra/stale adressen maken de bootstrap niet ready.

Platform-DNS wordt in de production preflight en direct vóór de eerste ACME-uitgifte live gecontroleerd. Tenant-TLS gebruikt de bestaande fase-4.3 readiness met de bestaande maximale leeftijd van vijftien minuten.

## TLS en onbekende SNI

Nog vóór het platformbeheer-certificaat wordt uitgegeven installeert 5.2 de bestaande neutrale catch-all-architectuur uit fase 4.4. De eerste/default HTTPS-vhost gebruikt dus nooit het beheer- of tenantcertificaat, maar het neutrale `invalid.verenigingsplatform.invalid` reject-certificaat.

De definitieve fase-5.1 HTTP-vhost serveert uitsluitend `/.well-known/acme-challenge/<token>` uit de platform ACME-webroot. Alle overige HTTP-paden gaan met een vaste 308 naar de canonieke HTTPS-host. De GUI zelf is daardoor nooit via HTTP beschikbaar. De globale Certbot deploy-hook voert altijd `apache2ctl configtest` uit vóór een Apache reload.

## Eerste ACME-account

Fase 4.4 eist terecht dat een Certbot-account vooraf bestaat. Op een werkelijk schone VPS mag fase 5.2 daarom éénmalig een niet-interactief ACME-account registreren als `certbot show_account` nog geen account vindt. Er wordt daarbij bewust geen contactadres in het generieke bootstrapplan of tenantautomation opgenomen. Leg operationeel vast dat Certbot zonder account-e-mail geen verval-/accountmeldingen per e-mail kan sturen; monitoring blijft daarom noodzakelijk.

## Integriteit van lifecycle en control-plane

De 5.2.1-heraudit heeft ook de onderliggende mutatieketen aangescherpt. Kritieke state-, lock-, queue-, resultaat-, audit- en exportbestanden worden na writes exact op owner/group/mode gecontroleerd. Purge en recover-purge blijven exact gebonden aan tenantroot, plansnapshot en tombstone; kritieke deletes mogen niet stil falen. Control-plane identity, bcrypt-operatorrecords en Fail2ban rate limiting worden vóór activatie fail-closed bewezen.

De first-VPS orchestration maakt de lege tenantbasis vóór de eerste control-plane `--refresh-only` snapshot. De generieke executor blijft daardoor fail-closed voor een werkelijk ontbrekende of onveilige tenantroot; alleen de first-VPS bootstrap zorgt dat de contractueel geplande basis op een schone server al veilig bestaat.

## Eindbewijs

`complete` wordt pas geschreven als:

- de tenant-healthprobe volledig slaagt;
- de control-plane snapshot opnieuw is opgebouwd;
- een lokale HTTPS-probe op de platformbeheer-host HTTP **401** teruggeeft zonder credentials — bewijs dat TLS, de juiste vhost en Basic Auth actief zijn;
- de tenant via de bestaande monitoring/healthlaag gezond is.

Daarna is de eerste VPS technisch gereed om verdere tenants via de platformbeheerlaag en de bestaande tenantcontracten te beheren.
