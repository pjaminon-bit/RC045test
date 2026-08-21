# 21-08-2026 — fase 5.1 platformbeheer/control-plane

## Doel

Een aparte, veilige platform-/superbeheerlaag boven fase 4 bouwen zonder rootrechten aan het gewone verenigingsbeheer of aan een PHP-webrequest te geven.

## Toegevoegd

- `app/deployment/control-plane-contract.php`
- `app/control-plane/control-plane-runtime.php`
- `app/control-plane-web/index.php`
- `bin/prepare-vps-control-plane.php`
- `bin/apply-vps-control-plane.php`
- `bin/bootstrap-control-plane-operator.php`
- `bin/control-plane-executor.php`
- `tests/phase51-control-plane.php`
- `docs/VPS-CONTROL-PLANE.md`

## Securitymodel

- aparte Linux/FPM identity `vst-control` zonder login/home/supplementary groups;
- aparte Apache-vhost en TLS-host voor platformbeheer;
- Apache Basic Auth met bcrypt-htpasswd buiten Git;
- CSRF-sessie in een eigen serverdirectory;
- webapp leest alleen een geschoonde root-generated tenantsnapshot;
- webapp kan geen processen starten en schrijft uitsluitend allowlisted JSON-requests;
- root-owned systemd `.path` unit start de executor op een niet-lege queue;
- executor accepteert alleen kortlevende requests (maximaal 15 minuten), vaste lifecycleacties en exacte destructieve bevestigingen;
- executor valideert ieder 4.8 lifecycle-plan opnieuw vóór uitvoering;
- lifecycle wordt met argument-arrays en `bypass_shell` uitgevoerd, nooit via shellstrings;
- tenantsecrets en exportpaden komen niet in de GUI-snapshot;
- DNS-providerrecords blijven buiten automatische lifecycleverwijdering.

## GUI

De platformbeheer-GUI toont tenant-key, host, lifecycle-status, health, laatste export en purge-wachttijd. Beschikbare knoppen volgen strikt de actuele 4.8-status. Tijden worden weergegeven als `dd-mm-jjjj HH:mm:ss` in `Europe/Amsterdam`.

## Productiegrens

Fase 5.1 installeert nog niet zelfstandig de eerste platform-DNS/Certbot-lineage. Een bestaand geldig platformcertificaat en minstens één gebootstrapte operator zijn preconditions voor root-apply. De first-VPS bootstrap inclusief platformhost/DNS/TLS wordt fase 5.2.
