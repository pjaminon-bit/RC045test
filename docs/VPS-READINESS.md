# Eerste productie-VPS — readiness en acceptatie

Status per **22-08-2026**.

Dit document beschrijft de stap **vóór** `apply-first-vps-bootstrap.php --apply`. Fase 5.2 installeert bewust geen OS-packages. Een nieuwe VPS moet daarom eerst aantoonbaar aan deze voorwaarden voldoen. Pas daarna mag de eerste productiebootstrap starten.

## 0. Bevroren pre-VPS kandidaat

De volledige software-/DEV-eindacceptatie is op 22 augustus 2026 afgerond. Voor de eerste VPS-proef is de gekozen releasecommit:

`936cf4879f1611d94123fb3d3a0a33b831a49810`

Deze kandidaat heeft de gecombineerde source-, security-, publieke browser- en authenticated browseracceptatie doorlopen. Gebruik voor de eerste VPS-proef exact deze commit. Als vóór de proef productcode wijzigt, moet de relevante regressie opnieuw groen zijn en wordt een nieuwe kandidaat-SHA expliciet vastgelegd.

## 1. Serverbasis

Ondersteund productieprofiel:

- Debian of Ubuntu;
- actuele beveiligingsupdates toegepast;
- correcte systeemklok/NTP;
- SSH-toegang voor de vertrouwde platformoperator;
- root/sudo beschikbaar voor de bootstrap;
- voldoende vrije schijf- en inodecapaciteit.

De bootstrap zelf voert **geen** `apt`, `apt-get` of automatische package-installatie uit.

## 2. Vereiste software vóór bootstrap

Minimaal aanwezig en uitvoerbaar:

- Git;
- Apache 2.4.49 of nieuwer;
- PHP CLI + PHP-FPM voor de gekozen platformversie, standaard 8.3;
- PHP-modules `openssl` en `pdo_pgsql`;
- PostgreSQL 16 of nieuwer;
- Certbot 2.0 of nieuwer;
- Fail2ban;
- systemd tooling;
- OpenSSL;
- curl;
- `htpasswd`;
- `getent`, `id`, `runuser`, `pgrep`;
- `tar` en `logrotate`;
- de normale Linux user/group-beheertools.

Voor privileged subprocessen accepteert de platformcode alleen vooraf gecontroleerde absolute executablepaden.

## 3. Services en configuratie vóór bootstrap

De production preflight verwacht vóór de eerste platformmutatie:

- Apache actief en `apache2ctl configtest` succesvol;
- de gekozen PHP-FPM service actief en de FPM configtest succesvol;
- PostgreSQL actief en lokaal als `postgres` exact `SELECT 1` kunnen uitvoeren;
- Fail2ban-configtest succesvol;
- de benodigde Apache-modules actief, waaronder Basic Auth, headers, proxy/proxy_fcgi, rewrite en SSL;
- de relevante `/etc`-configuratiedirectories root-owned en niet group/world-writable.

Een mislukte preflight stopt de bootstrap vóór het eerste bootstrap-lock/state-mutatiepunt.

## 4. Netwerk en firewall

Voor de eerste productievalidatie moeten minimaal bereikbaar zijn:

- TCP 22 vanaf de beheerlocatie;
- TCP 80 publiek voor Certbot HTTP-01;
- TCP 443 publiek voor HTTPS.

PostgreSQL hoeft voor het platformmodel niet publiek bereikbaar te zijn; tenantdatabase-authenticatie gebruikt lokaal Unix-socket peer authentication.

## 5. DNS vooraf kiezen en zetten

Er zijn minimaal twee verschillende publieke hostnamen nodig:

1. **platformbeheerhost**, bijvoorbeeld `beheer.example.nl`;
2. **eerste testtenant**, bijvoorbeeld `testvereniging.example.nl`.

Per host ondersteunt fase 5.2:

- directe A/AAAA-records; of
- exact één CNAME-hop naar een doel met de verwachte A/AAAA-records.

De code schrijft bewust **geen DNS-providerrecords**. De operator zet deze records vooraf bij de DNS-provider. Readiness vereist daarna meerdere consistente resolvermetingen en weigert stale/extra adressen.

## 6. Exacte productiebron voorbereiden

De source checkout op de VPS is onderdeel van de securitygrens.

Vóór bootstrap moet gelden:

- de checkout is exact de repository-root;
- Git `HEAD` is exact `936cf4879f1611d94123fb3d3a0a33b831a49810` voor deze eerste proef;
- de working tree is volledig schoon, inclusief untracked files;
- de bronboom is root-owned en nergens group/world-writable;
- het fase-4.7 inhoudsmanifest komt exact overeen met de geplande bron.

Een willekeurige gewijzigde kopie van `main` mag dus niet als productiebron worden gebruikt.

## 7. Parameters voor de eerste bootstrap vastleggen

Voor het genereren van het 5.2-plan moeten vooraf bewust worden gekozen:

- exacte releasecommit;
- platformbeheerhost;
- DNS-strategie + verwachte IP-adressen voor platformbeheer;
- eerste tenant-key;
- eerste tenantnaam;
- eerste tenanthost;
- DNS-strategie + verwachte IP-adressen voor de tenant;
- platformoperatornaam;
- PHP-versie;
- expliciet moduleprofiel van de eerste tenant;
- platformroot, standaard `/srv/verenigingsplatform`;
- tenantbasis, standaard `/srv/verenigingen`.

De eerste tenant gebruikt in de productieflow PDO/PostgreSQL en tijdzone `Europe/Amsterdam`.

## 8. Credentials

Niet in Git, planbestanden, environment of commandline zetten:

- platformoperatorwachtwoord;
- eerste tenantbeheerderwachtwoord.

Deze worden pas in hun eigen bootstrapstage via STDIN gelezen en direct daarna uit het procesgeheugen gewist voor zover PHP dat praktisch toelaat.

## 9. Root-vrije voorbereiding en controle

Voordat `sudo ... --apply` wordt gebruikt:

1. genereer de fase-5.2 bundle met `prepare-first-vps-bootstrap.php`;
2. controleer de gekozen hosts, tenant-key, modules, IP-adressen en commit in het gegenereerde plan;
3. voer `apply-first-vps-bootstrap.php --check` uit zonder rootmutaties;
4. los iedere fout op voordat productie-apply wordt gestart.

`--check` vervangt de live production preflight niet; die wordt bij `--apply`/`--resume` opnieuw uitgevoerd.

## 10. Eerste productiebootstrap

Als alle readinesspunten groen zijn:

- start `--apply --secrets-stdin`;
- onderbreek de flow niet bewust tussen checkpoints;
- gebruik na een echte onderbreking uitsluitend `--resume` met exact hetzelfde plan;
- start nooit een tweede bootstrap parallel.

De flow bouwt vervolgens in vaste volgorde:

1. immutable fase-4.7 release;
2. platform-DNS readiness;
3. neutrale HTTP/HTTPS catch-alls;
4. platformbeheer-certificaat;
5. eerste platformoperator;
6. fase-5.1 control-plane;
7. eerste tenantprovisioning en beheerder;
8. tenant runtime/web/database/FPM;
9. tenant DNS/TLS;
10. monitoring;
11. lifecycle-adoptie;
12. eind-smokes.

## 11. Acceptatie na bootstrap

De VPS is pas als **eerste productievalidatie geslaagd** te markeren wanneer minimaal is bewezen:

- platformbeheer geeft zonder credentials HTTP 401 over geldig HTTPS;
- inloggen op platformbeheer werkt met de bootstrapoperator;
- tenantpubliek en tenantbeheer werken via de eigen host;
- tenant healthprobe is groen en `/healthz.php` levert via de bedoelde route het verwachte resultaat;
- Apache/FPM/PostgreSQL/Certbot/Fail2ban/systemd zijn na bootstrap gezond;
- tenantdatabase en filesystem zijn aantoonbaar van andere tenants/platformbeheer gescheiden;
- control-plane status toont de tenant correct;
- suspend → activate werkt op de testtenant;
- volledige export werkt en checksum wordt vastgelegd;
- **restoretest:** de export wordt daadwerkelijk in een aparte wegwerp-herstelomgeving teruggezet, de SHA/integriteit wordt gecontroleerd en representatieve tenantdata wordt na restore gelezen;
- een releasewissel en rollback worden op de productieachtige VPS gecontroleerd zonder tenantdata te verliezen;
- monitoring/logrotate/healthtimer functioneren over minimaal één echte timercyclus;
- DNS-providerrecords worden bij lifecycleacties niet onverwacht gewijzigd.

Een destructieve `purge` hoeft niet op waardevolle data getest te worden. Als purge wordt beproefd, gebruik daarvoor uitsluitend een wegwerp-testtenant met vooraf geverifieerde export.

## 12. Resultaat van deze fase

Na succesvolle acceptatie kan in de roadmap voor fase 4.1 t/m 5.2 onderscheid worden gemaakt tussen:

- **code/CI/pre-VPS gereed**; en
- **op echte VPS gevalideerd**.

Pas daarna is het verstandig om reguliere verenigingen op dezelfde productie-VPS te gaan onboarden.
