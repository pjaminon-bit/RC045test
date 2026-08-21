# First-VPS productiebootstrap — fase 5.2

Fase 5.2 verbindt de eerder gebouwde productiecontracten tot één gecontroleerde eerste installatie van het Verenigingsplatform op een schone Debian/Ubuntu-VPS.

## Wat deze fase automatiseert

De bootstrap voert in vaste volgorde uit:

1. preflight van OS, Apache, PHP-FPM, PostgreSQL, Certbot, systemd en benodigde binaries/modules;
2. fase 4.7 immutable release-bootstrap naar `/srv/verenigingsplatform/releases/<commit>` en atomische `current`;
3. exacte live DNS-readiness van de platformbeheer-host;
4. neutrale HTTP/HTTPS catch-alls en neutraal reject-certificaat;
5. tijdelijke HTTP-01-route voor platformbeheer;
6. eerste Certbot/ACME-account indien nog niet aanwezig en exact één platformbeheer-certificaat via webroot;
7. veilige platformoperator via bcrypt en STDIN;
8. fase 5.1 control-plane installeren en activeren;
9. eerste tenant provisionen met PDO/PostgreSQL-profiel;
10. eerste tenantbeheerder via STDIN;
11. bestaande fase 3.5 en 4.1 t/m 4.5 plannen genereren en controleren;
12. tenant Linux/PHP-FPM runtime en inactieve Apache-artifacts installeren;
13. PostgreSQL provisionen terwijl de tenant-runtime nog inactief is;
14. tenant-FPM pas daarna valideren en reloaden;
15. tenant-DNS via de bestaande fase-4.3 check aantoonbaar ready maken;
16. tenant-TLS via fase 4.4 activeren;
17. monitoring via fase 4.6 activeren;
18. tenant via fase 4.8 als bestaande gezonde actieve tenant adopteren;
19. control-plane snapshot verversen en eindhealth bewijzen.

De bootstrap herschrijft de bestaande fase-4 tools niet. De bestaande scripts blijven per stap de bron van waarheid en voeren hun eigen fail-closed validatie opnieuw uit.

## Bewuste grenzen

De bootstrap **schrijft geen DNS-records bij een provider**. Voor zowel de platformbeheer-host als de eerste tenant moeten de A/AAAA- of CNAME-records vooraf bij de DNS-provider worden ingesteld. Het plan bevat uitsluitend de exact verwachte publieke DNS-uitkomst; providercredentials zijn verboden.

Packages worden niet automatisch met `apt` geïnstalleerd. Een ontbrekende of te oude dependency stopt de preflight vóór de productiebootstrap verdergaat. Dit voorkomt dat een generiek platformscript onverwacht systeemsoftware of repositories wijzigt.

Wachtwoorden staan nooit in het bootstrapplan, Git, argv, environment-variables of logs. Zolang operator en eerste tenantbeheerder nog niet beide veilig bestaan, worden de noodzakelijke secrets uitsluitend via `--secrets-stdin` aangeleverd.

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
  --php-version=8.3
```

Voor een CNAME-profiel gebruik je `--platform-strategy=cname --platform-cname=vps.example.net` of dezelfde tenantopties. De opgegeven IPv4/IPv6-adressen blijven de exact verwachte terminale adressen.

Standaardpaden:

- platformroot: `/srv/verenigingsplatform`;
- tenantbasis: `/srv/verenigingen`;
- lifecycle/control-plane state: `/var/lib/verenigingsplatform`;
- platform ACME-webroot: `/var/lib/verenigingsplatform/acme/control-plane`.

## Root-vrije controle

```bash
php bin/apply-first-vps-bootstrap.php \
  --plan=/root/verenigingsplatform-bootstrap/first-vps-bootstrap-plan.json \
  --check
```

Deze controle verifieert het plan, de releasebron en alle 5.2-artifacts opnieuw en muteert niets.

## Eerste uitvoering en secrets

Gebruik een root-only secretbron die JSON via STDIN schrijft. De exacte invoer bevat de nog benodigde wachtwoorden en wordt niet als CLI-argument gebruikt.

Voor de eerste run zijn dat de platformoperator en eerste tenantbeheerder. Voorbeeld van de vorm, **niet met echte secrets in shell history opslaan**:

```json
{
  "operator_password": "...",
  "tenant_admin_password": "..."
}
```

De apply wordt uitgevoerd met `--apply --secrets-stdin`. De bootstrap wist de ontvangen strings uit zijn PHP-variabelen zodra beide bootstrapstappen voorbij zijn.

## Checkpoints en hervatten

De root-owned state staat in:

`/var/lib/verenigingsplatform/bootstrap/first-vps-state.json`

De state bevat geen secrets en is cryptografisch gebonden aan de SHA-256 van exact het gebruikte 5.2-plan. De globale lock staat in `/run/lock/verenigingsplatform-first-bootstrap.lock`.

Na iedere bewezen stap wordt een checkpoint geschreven. Een fout door bijvoorbeeld nog niet gepropageerde DNS stopt de bootstrap. Nadat de externe oorzaak is opgelost wordt dezelfde planfile met `--resume` hervat. Een gewijzigd plan wordt geweigerd.

`--status` toont alleen de server-side bootstrapstage en planbinding.

## DNS-readiness

Zowel platformbeheer als tenant gebruiken minimaal drie consistente metingen met minimaal twee seconden ertussen. Directe profielen vereisen exact de verwachte A/AAAA-set en geen CNAME. CNAME-profielen vereisen exact één hop en exact de verwachte terminale A/AAAA-set. Extra/stale adressen maken de bootstrap niet ready.

Platform-DNS wordt direct vóór de eerste ACME-uitgifte nogmaals live gecontroleerd. Tenant-TLS gebruikt de bestaande fase-4.3 readiness met de bestaande maximale leeftijd van vijftien minuten.

## TLS en onbekende SNI

Nog vóór het platformbeheer-certificaat wordt uitgegeven installeert 5.2 de bestaande neutrale catch-all-architectuur uit fase 4.4. De eerste/default HTTPS-vhost gebruikt dus nooit het beheer- of tenantcertificaat, maar het neutrale `invalid.verenigingsplatform.invalid` reject-certificaat.

De definitieve fase-5.1 HTTP-vhost serveert uitsluitend `/.well-known/acme-challenge/<token>` uit de platform ACME-webroot. Alle overige HTTP-paden gaan met een vaste 308 naar de canonieke HTTPS-host. De GUI zelf is daardoor nooit via HTTP beschikbaar. De globale Certbot deploy-hook voert altijd `apache2ctl configtest` uit vóór een Apache reload.

## Eerste ACME-account

Fase 4.4 eist terecht dat een Certbot-account vooraf bestaat. Op een werkelijk schone VPS mag fase 5.2 daarom éénmalig een niet-interactief ACME-account registreren als `certbot show_account` nog geen account vindt. Er wordt daarbij bewust geen contactadres in het generieke bootstrapplan of tenantautomation opgenomen. Leg operationeel vast dat Certbot zonder account-e-mail geen verval-/accountmeldingen per e-mail kan sturen; monitoring blijft daarom noodzakelijk.

## Eindbewijs

`complete` wordt pas geschreven als:

- de tenant-healthprobe volledig slaagt;
- de control-plane snapshot opnieuw is opgebouwd;
- een lokale HTTPS-probe op de platformbeheer-host HTTP **401** teruggeeft zonder credentials — bewijs dat TLS, de juiste vhost en Basic Auth actief zijn;
- de tenant via de bestaande monitoring/healthlaag gezond is.

Daarna is de eerste VPS technisch gereed om verdere tenants via de platformbeheerlaag en de bestaande tenantcontracten te beheren.
