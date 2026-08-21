# Fase 4.6 — monitoring & logging

Datum: 21-08-2026

## Uitgevoerd

- `app/deployment/monitoring-contract.php` toegevoegd: bindt monitoring exact aan runtime, TLS en database.
- `healthz.php` toegevoegd met alleen 204/503/404 en zonder foutdetails.
- `app/operational-log.php` toegevoegd met vaste contextallowlist en externe-tenantbinding.
- `bin/prepare-vps-monitoring.php` toegevoegd voor deterministische secretvrije bundles.
- `bin/check-vps-health.php` toegevoegd voor root-only service/TLS/FPM/DB/app/disk probes.
- `bin/apply-vps-monitoring.php` toegevoegd voor gecontroleerde Apache/logrotate/systemd-installatie en activatie na gezonde probe.
- Apache accesslogging bevat geen client-IP, requestpad/query, referrer, user-agent, cookies of Authorization.
- PHP-FPM servicelog blijft in journald; de fase-4.1 poolconfig wordt niet achteraf gemuteerd.
- tenant-specifieke operationele applicatielog naar `<private_root>/monitoring/operations.jsonl`.
- logretentie: 14 dagen.
- alerts via optionele root-owned adapter buiten Git, met up/down transitions en reminders maximaal eens per uur.
- Certbot `live/` symlinks worden toegestaan uitsluitend wanneer ze binnen de eigen `archive/<cert-name>/` lineage eindigen.
- systemd timer gebruikt `OnCalendar=minutely`, zodat `Persistent=true` semantisch correct is.
- acceptatietest `tests/phase46-monitoring-logging.php` toegevoegd.

## Bewuste grenzen

- geen externe monitoringprovider of credentials in Git;
- geen root/serverknoppen in de gewone verenigingsbeheer-GUI;
- werkelijke installatie/activatie volgt pas op de productie-VPS;
- control-plane/superbeheer wordt later bovenop deze operatorcontracten gebouwd.
