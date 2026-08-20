# 20-08-2026 — fase 4.4 TLS/HTTPS

## Aanleiding

Na fase 4.3 bestond een tenantgebonden DNS-plan en een kortlevend readinessbewijs, maar er was nog geen gecontroleerde TLS-uitgifte of veilige Apache `*:443` activatie.

## Gekozen ontwerp

- Apache 2.4 op Ubuntu/Debian blijft de canonieke webserver.
- Certbot wordt alleen als ACME-client/authenticator gebruikt via `certonly --webroot`; de Apache installer is verboden.
- ACME-accountregistratie/contactgegevens zijn VPS-/operatorverantwoordelijkheid en worden niet in tenantbundles opgenomen.
- Elke tenant krijgt een eigen webroot en eigen deterministische Certbot lineage.
- Onbekende HTTPS/SNI gebruikt een apart lokaal reject-certificaat en nooit het certificaat van een tenant.
- Tenant HTTPS controleert zowel SNI als Host voordat het 4.2 FPM-routingfragment wordt gebruikt.

## Implementatie

Nieuw:

- `app/deployment/tls-contract.php`
- `bin/prepare-vps-tls.php`
- `bin/apply-vps-tls.php`
- `tests/phase44-tls-https.php`
- `docs/VPS-TLS.md`

CI/DEV:

- fase-4.4 test toegevoegd aan de volledige platformtestmatrix;
- nieuwe `bin/`, `tests/` en `docs/VPS-TLS.md` routes worden op DEV expliciet als HTTP 403 getest.

## Activatiecontract

1. verse 4.3 readiness;
2. actieve tenant-FPM socket en exact 4.2 fragment;
3. HTTP catch-all + challenge-vhost;
4. Apache configtest + reload;
5. live DNS-hercontrole;
6. Certbot HTTP-01 webroot;
7. certificaat/key/SAN/lineage/rechten/renewalvalidatie;
8. HTTPS catch-all + tenantvhost;
9. volledige Apache configtest;
10. reload.

## Renewal

Een platformbrede Certbot deploy-hook voert eerst `apache2ctl configtest` uit en reloadt Apache alleen bij succes.

## Grenzen

De code/CI voert geen echte ACME-uitgifte, DNS-write of root-activatie uit. Die acties gebeuren pas op de echte VPS met definitieve domeinen en adressen.
