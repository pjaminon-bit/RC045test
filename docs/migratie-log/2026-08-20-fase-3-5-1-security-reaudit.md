# Fase 3.5.1 — security heraudit van fase 3.x

Datum: **20-08-2026**

Aanleiding: na afronding van fase 3.5 is fase 3.1 t/m 3.5 opnieuw inhoudelijk gecontroleerd, los van het feit dat alle bestaande CI-tests groen waren. De heraudit vond geen normale cross-tenant leesroute, maar wel zeven randgevallen/hardeningspunten die vóór een echte VPS-livegang moesten worden gesloten.

## 1. Masterrotatie trok bestaande sessies niet in

Bevinding: `--rotate` verving de password hash, maar een reeds bestaande PHP-sessie hoefde het wachtwoord niet opnieuw te bewijzen.

Fix:
- de bootstrap valideert nu ook `private/sessions` als tenantgebonden symlinkvrije map;
- vóór de nieuwe masterhash wordt geplaatst worden alle bestaande reguliere sessiebestanden van die tenant verwijderd;
- onverwachte bestanden/symlinks of een mislukte unlink stoppen de rotatie fail-closed;
- de CLI meldt hoeveel sessies zijn ingetrokken;
- de tenant-cookie-namespace bevat daarnaast een SHA-256 generatie van de actieve `private/auth/master.php`;
- iedere password-hashrotatie verandert daardoor automatisch de sessiecookie-namespace, ook als precies tijdens de rotatie een login/request loopt.

Gevolg: na masterwachtwoordrotatie loggen master én gewone gebruikers opnieuw in. Het verwijderen van sessiebestanden is defense-in-depth; de mastergeneratie in de cookie-namespace sluit ook het loginrace-randgeval.

## 2. Gebruikersrestore kon een oude sessieversie terugzetten

Bevinding: een users-snapshot bevatte de historische `sessie_versie`. Een restore kon daardoor in theorie een eerder ingetrokken sessie weer dezelfde versie geven.

Fix:
- `tenantBackupLeesArray()` hardent `auth-gebruikers` snapshots vóór restore;
- per account wordt de nieuwe `sessie_versie` strikt hoger gezet dan zowel de snapshotversie als de huidige liveversie;
- ook een account dat alleen in de snapshot bestaat krijgt een hogere versie dan zijn snapshotwaarde;
- beschadigde/onveilige actuele users-opslag breekt de restore af.

Gevolg: een restore kan geen oude gebruikerssessie opnieuw geldig maken.

## 3. Host-header/canonical-host grens

Bevinding: de gedeelde `.htaccess` valideerde de Host-header syntactisch, maar reflecteerde die daarna wel in de HTTP→HTTPS redirect. Daarnaast was een catch-all/default-vhost nog geen expliciet deploymentvereiste.

Fix:
- de gedeelde `.htaccess` voert geen HTTP→HTTPS redirect meer uit;
- de huidige standalone/DEV-hosting blijft HTTPS vóór de applicatielaag afhandelen;
- `deployment.json.web` bevat nu een literal `http_redirect_target` op basis van de tenantconfig;
- `redirect_must_not_use_request_host=true`;
- `reject_unknown_hosts=true`;
- `default_vhost_must_reject=true`;
- het VPS-runbook schrijft voor dat een catch-all vhost vóór tenantvhosts staat en onbekende hosts nooit aan een tenant-FPM-pool koppelt.

## 4. Absolute-padvalidatie op POSIX

Bevinding: `tenantRuntimeIsAbsoluutPad()` behandelde een begin-backslash ook op Linux als absolute root.

Fix:
- POSIX accepteert uitsluitend paden die met `/` beginnen;
- Windows accepteert drive-root en UNC-paden;
- een losse/root-relatieve backslash is niet langer een volledige absolute tenantgrens;
- de provisioner erft dezelfde centrale correctie.

## 5. `.git` in documentroot

Bevinding: `.github` en ontwikkeltooling waren geblokkeerd, maar `.git` stond niet expliciet in de Apache deny-regel.

Fix:
- `.git` staat nu in de centrale server-only RewriteRule;
- VPS-documentatie adviseert daarnaast releases zonder VCS-metadata;
- `deployment.json.web.vcs_metadata_must_not_be_served=true` maakt dit een deploymentvereiste.

## 6. Microseconde-randgeval bij authbackups

Bevinding: twee losse `microtime(true)` calls in één berekening konden exact rond een secondegrens theoretisch een negatieve/verkeerde microsecondecomponent opleveren.

Fix:
- authbackups gebruiken één tijdmeting voor zowel seconden als microseconden;
- masterrotatiebackups gebruiken eveneens één tijdmeting en daarnaast een random suffix;
- tenantbackup-tijdstempels zijn om dezelfde reden op floor/clamp gehard.

## 7. Backup-UI noemde binding onterecht cryptografisch

Bevinding: snapshots bevatten een tenant-key/onderdeelbinding en die wordt streng gevalideerd, maar er is geen HMAC of digitale handtekening.

Fix:
- de UI zegt nu expliciet dat tenant- en onderdeelbinding vóór restore wordt gevalideerd en **geen cryptografische ondertekening** is.

## Regressietests

Nieuw:

- `tests/phase351-security-reaudit.php`
- `tests/phase351-master-session-generation.php`

Deze tests reproduceren de herauditpunten met onder meer:
- Linux backslash-schijnroot;
- echte provisioner + bootstrap;
- canary master- en usersessies vóór masterrotatie;
- wijziging van de sessiecookie-namespace bij iedere nieuwe masterhash;
- stabiliteit van die namespace zolang de masterconfig ongewijzigd blijft;
- users-snapshot met oudere sessieversies;
- statische Host-header/.git guards;
- auth/master microtime-regressie;
- correcte backup-UI terminologie;
- catch-all/canonical-host velden in het VPS-contract.

De bestaande fase-3.5 test is aangepast aan het aangescherpte webservercontract en blijft alle oorspronkelijke tenant-/FPM-/deploymentgrenzen testen.

## Afrondingscriterium

Fase 3.5.1 mag pas als afgerond worden beschouwd wanneer:

1. PHP syntaxcontrole groen is;
2. alle bestaande fase 2/3 tests groen blijven;
3. beide fase-3.5.1 regressietests 0 fouten melden;
4. de PR-run exact op de uiteindelijke head-SHA groen is;
5. pas daarna naar `main` wordt gemerged en DEV de nieuwe main-build toont.
