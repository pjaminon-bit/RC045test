# VPS monitoring & logging — fase 4.6

Status: code/CI-contract voor productie-VPS. Werkelijke installatie volgt pas op de VPS nadat 4.1 t/m 4.5 zijn toegepast.

## Doel

Fase 4.6 bewaakt iedere tenant zonder secrets of onnodige persoonsgegevens in logs te verzamelen. Het monitoringplan is byte-exact gebonden aan het actuele runtime-, TLS- en databaseplan.

## Publieke healthcheck

`/healthz.php` is bewust informatie-arm:

- externe productie-tenant gezond: HTTP 204;
- externe productie-tenant ongezond: HTTP 503;
- standalone/DEV: HTTP 404;
- geen tenantnaam, databasegegevens of foutdetails in de response.

De endpoint controleert alleen de applicatielaag/private root en de eigen PDO-verbinding. De bredere VPS-controle gebeurt uitsluitend lokaal als root.

## Interne healthprobe

`bin/check-vps-health.php --probe` controleert onder andere:

- Apache, PHP-FPM en PostgreSQL actief;
- volledige Apache `configtest`;
- eigen FPM Unix-socket aanwezig;
- Certbot `live/` certificaat en key volgen alleen symlinks naar de eigen `archive/<cert-name>/` lineage;
- certificaat blijft minimaal twee weken geldig;
- peer-login naar exact de eigen PostgreSQL database/role;
- lokale HTTPS healthcheck via `curl --resolve ... 127.0.0.1`;
- minimaal 10% én 512 MiB vrije ruimte op het tenantfilesystem.

De root-only status komt in `/var/lib/verenigingsplatform/monitoring/<tenant>-health.json`.

## Logging

Apache krijgt één platformbreed privacybewust accesslogformaat. Het bevat alleen tijd, vhost, HTTP-methode, status, responsegrootte en duur. Client-IP, URI/pad, querystring, referrer, user-agent, cookies en Authorization worden niet vastgelegd.

PHP-FPM servicestoringen blijven in het systemd-journal van `php<versie>-fpm.service`. Fase 4.6 muteert bewust niet achteraf de byte-exacte fase-4.1 poolconfig. Tenant-specifieke PHP-fatals en operationele applicatie-events gaan naar `<private_root>/monitoring/operations.jsonl` met een vaste allowlist aan contextvelden.

Logs roteren dagelijks en worden 14 dagen bewaard.

## Alerts

De generieke code bevat geen mail-, Slack-, webhook- of andere credentials. Een operator kan buiten Git een root-owned executable plaatsen op:

`/etc/verenigingsplatform/monitoring/alert-command`

De checker stuurt een kleine geschoonde JSON-payload via stdin. Er komt direct een alert bij `up -> down`, een herstelmelding bij `down -> up`, en tijdens dezelfde storing maximaal één reminder per uur.

Ontbreekt de adapter, dan blijft monitoring werken maar vindt geen externe alertdelivery plaats. Bestaat de adapter wel maar is hij niet root-owned, executable of is hij group/world-writable, dan faalt de alertactie gesloten.

## Genereren

```bash
php bin/prepare-vps-monitoring.php \
  --tls-plan=/srv/verenigingen/<tenant>/tls/tls-plan.json \
  --database-plan=/srv/verenigingen/<tenant>/database/database-plan.json
```

Gebruik `--dry-run` om alleen het plan te tonen. Een afwijkend bestaand artifact vereist expliciet `--force`.

## Controleren

```bash
php bin/apply-vps-monitoring.php \
  --monitoring-plan=/srv/verenigingen/<tenant>/monitoring/monitoring-plan.json \
  --check
```

Dit is root-vrij en wijzigt niets.

## Toepassen op de VPS

```bash
sudo php bin/apply-vps-monitoring.php \
  --monitoring-plan=/srv/verenigingen/<tenant>/monitoring/monitoring-plan.json \
  --apply
```

De apply installeert de Apache loggingconfig, logrotate-regels en systemd healthservice/timer. Voor activatie worden Apache, systemd-units en logrotate gevalideerd. Daarna wordt Apache gecontroleerd herladen, moet één volledige healthprobe slagen en pas dan wordt de minuut-timer enabled.

## Bewuste grenzen

Fase 4.6 installeert geen extern monitoringproduct en bewaart geen alertcredentials. Multi-location uptimecontrole kan later bovenop deze lokale betrouwbare statuslaag worden gebouwd. De gewone verenigingsbeheer-GUI krijgt geen root/serveracties; platformbeheer/control-plane volgt pas in een latere fase.
