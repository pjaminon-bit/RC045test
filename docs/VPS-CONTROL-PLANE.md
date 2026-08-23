# VPS control-plane / platformbeheer — fase 5.1

Fase 5.1 zet een aparte platformbeheerlaag boven de tenant-infrastructuur uit fase 4. De control-plane is **niet** onderdeel van het gewone verenigingsbeheer en krijgt een eigen host, Apache-vhost, PHP-FPM pool, Linux identity, operatorauthenticatie en queue.

## Beveiligingsgrens

De browser voert nooit rootcommando's uit. De architectuur is bewust in twee processen gesplitst:

1. Apache authenticeert de platformoperator met Basic Auth **uitsluitend over HTTPS** en een root-owned bcrypt `htpasswd`-bestand buiten Git.
2. De PHP-webapp draait als `vst-control`, gebruikt een aparte sessiemap en CSRF-token en leest alleen een geschoonde root-generated snapshot.
3. Een mutatie schrijft uitsluitend een strikt allowlisted `5.1-request` JSON-bestand naar de pending queue. De browser kan geen shellcommando, argv, pad of willekeurige lifecycle-optie meegeven.
4. Een root-owned systemd `.path` unit start `control-plane-executor.php` zodra de pending map niet leeg is.
5. De executor neemt het verzoek atomisch over, controleert request-id, operator, tenant-key, actie, bevestigingen en maximale leeftijd van 15 minuten en valideert daarna opnieuw het actuele fase-4.8 lifecycle-plan.
6. Alleen daarna wordt de bestaande `apply-vps-lifecycle.php` met een vaste argument-array via `proc_open(..., ['bypass_shell'=>true])` gestart.
7. Resultaat, audit en nieuwe tenantstatus worden server-side geschreven; secrets of tenantdata komen niet in de GUI-snapshot.

De webapp bevat bewust geen `exec`, `system`, `shell_exec` of `proc_open`.

## Platformidentity

Vaste runtimeidentity:

- user: `vst-control`
- primary group: `vst-control`
- shell: `/usr/sbin/nologin`
- home: `/nonexistent`
- geen supplementary groups
- eigen PHP-FPM pool/socket
- eigen session directory onder `/var/lib/verenigingsplatform/control-plane`

De platformvhost gebruikt als DocumentRoot `app/control-plane-web` binnen de gedeelde release. Tenant-vhosts kunnen deze code niet bereiken omdat `/app` al server-side buiten het tenant-HTTP-oppervlak ligt.

## HTTP, HTTPS en Certbot renewal

De GUI is alleen via HTTPS beschikbaar. De definitieve HTTP-vhost heeft als enige publieke uitzondering:

`/.well-known/acme-challenge/<token>`

uit `/var/lib/verenigingsplatform/acme/control-plane`. De rest van HTTP wordt met een vaste 308 naar de canonieke beheerhost gestuurd. Hierdoor kan Certbot webroot zowel het eerste certificaat als latere renewals uitvoeren zonder de GUI ooit via HTTP te publiceren.

Fase 5.2 installeert bovendien de gedeelde deploy-hook `/etc/letsencrypt/renewal-hooks/deploy/50-verenigingsplatform-apache-reload`, die altijd eerst `apache2ctl configtest` uitvoert en alleen daarna Apache reloadt.

## GUI

De GUI toont uitsluitend operationele metadata:

- tenant-key;
- canonieke host;
- lifecycle-status;
- recente healthstatus;
- laatste exporttijd en checksum-binding;
- eventuele purge-wachttijd.

Tijden worden in de GUI weergegeven als `dd-mm-jjjj HH:mm:ss` in `Europe/Amsterdam`.

Afhankelijk van de actuele 4.8-status zijn alleen geldige acties beschikbaar:

- unmanaged → adopteren;
- active → uitschakelen;
- suspended → heractiveren, exporteren en na een geldige export delete aanvragen;
- pending_delete → delete annuleren en pas na de 24-uursgrens definitief purgen;
- onafgeronde activate/suspend transition → recovery.

Voor delete moet de operator de tenant-key exact typen. Purge vereist daarnaast exact `VERWIJDER-DEFINITIEF`. De export-SHA wordt door de control-plane uit de root-generated snapshot gebonden en door fase 4.8 nogmaals gecontroleerd.

## Voorbereiden

De bundle is secretvrij en kan zonder root worden gegenereerd:

```bash
php bin/prepare-vps-control-plane.php \
  --host=beheer.platform.example \
  --app-root=/srv/verenigingsplatform/current \
  --tenants-root=/srv/verenigingen \
  --output=/root/control-plane \
  --php-version=8.5 \
  --cert-name=verenigingsplatform-beheer
```

Controleer de bundle zonder rootmutatie:

```bash
php bin/apply-vps-control-plane.php \
  --plan=/root/control-plane/control-plane-plan.json \
  --check
```

## Operator bootstrap

Het wachtwoord mag nooit als CLI-argument worden meegegeven. De bootstrap gebruikt Apache `htpasswd` met bcrypt (`-B`) en stdin (`-i`):

```bash
printf '%s\n' 'EEN-STERK-UNIEK-WACHTWOORD' | \
  sudo php bin/bootstrap-control-plane-operator.php \
  --user=operator \
  --password-stdin
```

Minimale wachtwoordlengte is 14 tekens. Het bestand wordt root:www-data `0640` en staat buiten Git.

## TLS-voorwaarde in 5.1

Fase 5.1 zelf schrijft geen DNS-providerrecords en vraagt niet zelfstandig het eerste platformcertificaat aan. Vóór een losse `--apply` moet de genoemde Certbot-lineage al bestaan en minimaal 24 uur geldig zijn. De lineage wordt exact gecontroleerd onder `/etc/letsencrypt/archive/<cert-name>/`.

De normale eerste productie-installatie loopt daarom via **fase 5.2**, die de immutable release, platform-DNS, neutrale catch-all, ACME-account/certificaat, operator en vervolgens deze 5.1 bundle in vaste volgorde opbouwt. DNS-providerrecords blijven operator/provider-side.

## Root-installatie

Na operator- en certificaatpreflight:

```bash
sudo php bin/apply-vps-control-plane.php \
  --plan=/root/control-plane/control-plane-plan.json \
  --apply
```

De apply:

- maakt of verifieert de aparte system user/group;
- installeert de secretvrije runtimeconfig;
- maakt queue/result/session directories met gescheiden ownership;
- installeert de FPM-pool;
- valideert PHP-FPM configuratie vóór reload;
- installeert Apache en systemd artifacts;
- valideert systemd units en Apache `configtest` vóór activatie;
- activeert de platformvhost en de queue `.path` unit;
- maakt via de root-executor de eerste veilige tenant-snapshot.

Bij een fout na een nieuw aangemaakte Apache enable-link wordt die link teruggedraaid en Apache opnieuw veilig gevalideerd/herladen.

## Geen toegang vanuit DEV of tenantbeheer

De control-plane broncode staat onder `app/` en de roottools onder `bin/`; beide zijn uit het gewone HTTP-oppervlak verwijderd. CI controleert aanvullend dat de nieuwe bestanden op DEV HTTP 403 blijven geven. De platformbeheer-GUI wordt alleen via zijn **eigen VPS-vhost** bereikbaar.
