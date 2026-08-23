# Eerste productie-VPS — readiness en acceptatie

Status per **23-08-2026**.

Dit document beschrijft de stap **vóór** `apply-first-vps-bootstrap.php --apply`. Fase 5.2 installeert bewust geen OS-packages. Een nieuwe VPS moet daarom eerst aantoonbaar aan deze voorwaarden voldoen. Pas daarna mag de eerste productiebootstrap starten.

## 0. Bevroren pre-VPS kandidaat

Gebruik voor de eerste VPS-proef uitsluitend de **exacte 40-hex releasecommit die in GitHub issue #39 (`Fase 5.3 — eerste echte VPS-validatie`) als definitieve pre-VPS kandidaat staat vastgelegd**.

Bewaar hier bewust geen gekopieerde vaste SHA: na een laatste preflight- of securitycorrectie zou zo'n documentwaarde opnieuw kunnen verouderen. Issue #39 is de enige bron van waarheid voor de te check-outen commit.

Als productcode vóór de proef wijzigt, moeten de relevante regressies opnieuw groen zijn en moet issue #39 vóór de VPS-proef naar de nieuwe 40-hex commit worden bijgewerkt.

## 1. Definitieve serverbasis

Voor de eerste productievalidatie gebruiken we:

- **Ubuntu Server 26.04 LTS (Resolute)**;
- PHP **8.5** als platform- en PHP-FPM-baseline;
- actuele beveiligingsupdates;
- correcte systeemklok/NTP;
- SSH-toegang voor de vertrouwde platformoperator;
- root/sudo beschikbaar voor de bootstrap;
- voldoende vrije schijf- en inodecapaciteit.

De bootstrap zelf voert **geen** `apt`, `apt-get` of automatische package-installatie uit.

## 2. Ubuntu 26.04 packages vóór bootstrap

Op een schone Ubuntu 26.04 VPS installeren/actualiseren we vóór de platformbootstrap minimaal:

```bash
sudo apt update
sudo apt full-upgrade -y
sudo apt install -y \
  apache2 apache2-utils \
  php8.5-cli php8.5-fpm php8.5-pgsql \
  postgresql postgresql-client \
  certbot fail2ban \
  git curl openssl logrotate \
  procps util-linux passwd tar
```

`php8.5-pgsql` levert de PostgreSQL/PDO-driver die de bootstrap als `pdo_pgsql` controleert. `apache2-utils` levert `htpasswd`. De overige packages maken de vaste binaries beschikbaar die de privileged scripts fail-closed controleren.

Controleer na installatie dat er geen uitgestelde reboot door kernel/security-updates nodig is voordat de echte bootstrap start.

## 3. Vereiste Apache-modules

De bootstrap vereist onder meer Basic Auth, headers, proxy/FastCGI, rewrite en TLS. Activeer op Ubuntu vóór de eerste production preflight:

```bash
sudo a2enmod auth_basic authn_file authz_core headers proxy proxy_fcgi rewrite ssl
sudo apache2ctl configtest
sudo systemctl restart apache2
```

De fase-4.2 webserverapply controleert aanvullend de standaard Apache-modules die voor directory/alias-routing nodig zijn.

## 4. Vereiste softwareversies

De productiepreflight vereist minimaal:

- Apache 2.4.49;
- exacte gekozen PHP CLI/PHP-FPM-versie: **8.5**;
- PHP-modules `openssl` en `pdo_pgsql`;
- PostgreSQL client/server 16 of nieuwer;
- Certbot 2.0 of nieuwer;
- Fail2ban;
- systemd tooling;
- OpenSSL en curl;
- `htpasswd`;
- `getent`, `id`, `runuser`, `pgrep`;
- `tar`, `logrotate` en de normale Linux user/group-tools.

Ubuntu 26.04 voldoet met de distributiepakketten ruimschoots aan de minimumversies; gebruik geen externe PHP/PPA uitsluitend om een andere PHP-major te installeren.

## 5. Services en configuratie vóór bootstrap

De production preflight verwacht vóór de eerste platformmutatie:

```bash
sudo systemctl enable --now apache2 php8.5-fpm postgresql fail2ban
sudo apache2ctl configtest
sudo php-fpm8.5 -t
sudo fail2ban-client -t
sudo -u postgres psql -X -v ON_ERROR_STOP=1 -Atqc 'SELECT 1;'
```

Daarnaast moeten de relevante `/etc`-configuratiedirectories root-owned en niet group/world-writable zijn.

Een mislukte preflight stopt de bootstrap vóór het eerste bootstrap-lock/state-mutatiepunt.

## 6. Snelle versiecontrole op de VPS

Voer vóór het genereren/toepassen van het bootstrapplan uit:

```bash
cat /etc/os-release
uname -a
php8.5 -v
php8.5 -m | grep -E '^(openssl|pdo_pgsql)$'
php-fpm8.5 -v
apache2ctl -v
psql --version
certbot --version
fail2ban-client --version
git --version
```

Bij PHP moet de **8.5** binary zelf worden gebruikt; vertrouw niet alleen op een eventuele generieke `/usr/bin/php` alternative.

## 7. Netwerk en firewall

Voor de eerste productievalidatie moeten minimaal bereikbaar zijn:

- TCP 22 vanaf de beheerlocatie;
- TCP 80 publiek voor Certbot HTTP-01;
- TCP 443 publiek voor HTTPS.

PostgreSQL hoeft voor het platformmodel niet publiek bereikbaar te zijn; tenantdatabase-authenticatie gebruikt lokaal Unix-socket peer authentication.

## 8. DNS vooraf kiezen en zetten

Er zijn minimaal twee verschillende publieke hostnamen nodig:

1. **platformbeheerhost**, bijvoorbeeld `beheer.example.nl`;
2. **eerste testtenant**, bijvoorbeeld `testvereniging.example.nl`.

Per host ondersteunt fase 5.2:

- directe A/AAAA-records; of
- exact één CNAME-hop naar een doel met de verwachte A/AAAA-records.

De code schrijft bewust **geen DNS-providerrecords**. De operator zet deze records vooraf bij de DNS-provider. Readiness vereist daarna meerdere consistente resolvermetingen en weigert stale/extra adressen.

## 9. Exacte productiebron voorbereiden

De source checkout op de VPS is onderdeel van de securitygrens.

Vóór bootstrap moet gelden:

- clone/check-out de release-SHA uit issue #39 als root-owned stagingbron;
- de checkout is exact de repository-root;
- Git `HEAD` is exact de 40-hex commit uit issue #39;
- de working tree is volledig schoon, inclusief untracked files;
- de volledige bronboom is root-owned en nergens group/world-writable;
- het fase-4.7 inhoudsmanifest komt exact overeen met de geplande bron.

Een willekeurige gewijzigde kopie van `main` mag niet als productiebron worden gebruikt.

## 10. Parameters voor de eerste bootstrap vastleggen

Voor het genereren van het 5.2-plan moeten vooraf bewust worden gekozen:

- exacte releasecommit uit issue #39;
- platformbeheerhost;
- DNS-strategie + verwachte IP-adressen voor platformbeheer;
- eerste tenant-key;
- eerste tenantnaam;
- eerste tenanthost;
- DNS-strategie + verwachte IP-adressen voor de tenant;
- platformoperatornaam;
- PHP-versie **8.5**;
- expliciet moduleprofiel van de eerste tenant;
- platformroot, standaard `/srv/verenigingsplatform`;
- tenantbasis, standaard `/srv/verenigingen`.

De eerste tenant gebruikt in de productieflow PDO/PostgreSQL en tijdzone `Europe/Amsterdam`.

## 11. Credentials

Niet in Git, planbestanden, environment of commandline zetten:

- platformoperatorwachtwoord;
- eerste tenantbeheerderwachtwoord.

Deze worden pas in hun eigen bootstrapstage via STDIN gelezen en direct daarna uit het procesgeheugen gewist voor zover PHP dat praktisch toelaat.

## 12. Root-vrije voorbereiding en controle

Voordat `sudo ... --apply` wordt gebruikt:

1. genereer de fase-5.2 bundle met `prepare-first-vps-bootstrap.php` en `--php-version=8.5`;
2. controleer hosts, tenant-key, modules, IP-adressen en releasecommit in het gegenereerde plan;
3. voer `apply-first-vps-bootstrap.php --check` uit;
4. los iedere fout op voordat productie-apply wordt gestart.

`--check` vervangt de live production preflight niet; die wordt bij `--apply`/`--resume` opnieuw uitgevoerd.

## 13. Eerste productiebootstrap

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

## 14. Acceptatie na bootstrap

De VPS is pas als eerste productievalidatie geslaagd wanneer minimaal is bewezen:

- platformbeheer geeft zonder credentials HTTP 401 over geldig HTTPS;
- inloggen op platformbeheer werkt met de bootstrapoperator;
- tenantpubliek en tenantbeheer werken via de eigen host;
- tenant healthprobe is groen;
- Apache/FPM/PostgreSQL/Certbot/Fail2ban/systemd zijn na bootstrap gezond;
- tenantdatabase en filesystem zijn aantoonbaar gescheiden;
- control-plane status toont de tenant correct;
- suspend → activate werkt op de testtenant;
- volledige export + checksum werkt;
- restore wordt daadwerkelijk in een aparte wegwerp-herstelomgeving getest;
- releasewissel en rollback verliezen geen tenantdata;
- monitoring/logrotate/healthtimer functioneren over minimaal één echte timercyclus;
- DNS-providerrecords worden bij lifecycleacties niet onverwacht gewijzigd.

Een destructieve `purge` hoeft niet op waardevolle data getest te worden. Gebruik daarvoor, als we hem testen, uitsluitend een wegwerp-testtenant met vooraf geverifieerde export.

## 15. Resultaat

Na succesvolle acceptatie kan fase 5.3 van **code/CI/pre-VPS gereed** naar **op echte VPS gevalideerd**. Pas daarna onboarden we reguliere verenigingen.
