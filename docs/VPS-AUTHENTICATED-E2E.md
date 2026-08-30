# Authenticated E2E op VPS-test

Doel: beheerlogin, sessiebeveiliging, autorisatie en het gekoppelde ledenportaal automatisch bewijzen tegen `https://test.vps.holox.nl`, zonder productieachtige persoonsgegevens en zonder de oude SFTP-fixturemethode.

## Ontwerp

Tenant `test` krijgt één permanente, duidelijk gemarkeerde synthetische fixture:

- beheeraccount `vps-e2e-admin` met alle actuele beheer-capabilities;
- ledenaccount `vps-e2e-member` zonder beheer-capabilities;
- één gekoppeld synthetisch lid `E2E Testlid` met uitsluitend fictieve gegevens;
- één gedeeltelijk betaalde contributieregel;
- één `E2E Testcommissie`;
- één ledenvergadering met `E2E agendapunt` en definitieve testnotulen;
- één aan het testlid toegewezen taak.

De fixture gebruikt de echte tenantgrenzen: `/srv/verenigingen/test/private/auth/users.json` voor auth en de echte PDO/PostgreSQL private store voor domeindata. De helper schrijft niets naar de immutable release en gebruikt geen FTP/SFTP.

De identifiers zijn deterministisch per tenant. Opnieuw provisionen vervangt alleen deze fixture, roteert de sessiegeneratie van de twee E2E-accounts en laat andere accounts/leden/domeindata staan. Als een gereserveerde fixture-ID of gebruikersnaam al bij een niet-fixture account hoort, stopt de tool fail-closed.

## Eenmalig provisionen op VPS-test

Voer dit pas uit nadat de release met `bin/provision-vps-authenticated-e2e.php` actief is.

Bepaal eerst de tenant-runtimegebruiker uit het bestaande runtimeplan:

```bash
runtime_user="$(python3 - <<'PY'
import json
with open('/srv/verenigingen/test/runtime/runtime-plan.json', encoding='utf-8') as f:
    print(json.load(f)['os']['user'])
PY
)"
printf 'runtime user: %s\n' "$runtime_user"
```

Doe daarna eerst de read-only preflight:

```bash
sudo -u "$runtime_user" /usr/bin/php8.5 \
  /srv/verenigingsplatform/current/bin/provision-vps-authenticated-e2e.php \
  --config=/srv/verenigingen/test/config.php \
  --expected-tenant=test \
  --expected-site=https://test.vps.holox.nl \
  --admin-user=vps-e2e-admin \
  --member-user=vps-e2e-member \
  --check
```

Verwacht:

```text
E2E CHECK OK  tenant=test storage=pdo fixture=vps-authenticated-e2e-v1
```

Genereer vervolgens in de VPS-shell een sterk tijdelijk geheim zonder het in command history te zetten:

```bash
E2E_PASSWORD="$(python3 -c 'import secrets; print(secrets.token_urlsafe(36))')"
printf 'Bewaar dit eenmalig als GitHub secret VPS_TEST_E2E_PASSWORD: %s\n' "$E2E_PASSWORD"
```

Pas de fixture toe. Het wachtwoord loopt uitsluitend via stdin:

```bash
printf '%s\n' "$E2E_PASSWORD" | sudo -u "$runtime_user" /usr/bin/php8.5 \
  /srv/verenigingsplatform/current/bin/provision-vps-authenticated-e2e.php \
  --config=/srv/verenigingen/test/config.php \
  --expected-tenant=test \
  --expected-site=https://test.vps.holox.nl \
  --admin-user=vps-e2e-admin \
  --member-user=vps-e2e-member \
  --password-stdin \
  --apply
unset E2E_PASSWORD
```

Verwacht:

```text
E2E APPLY OK  tenant=test accounts=2 linked_member=1 fixture=vps-authenticated-e2e-v1
```

De tool maakt vóór een authwijziging een tenantlokale backup onder `private/backups/auth`. De PostgreSQL domeinwrites lopen in één transactie. Mislukt die transactie, dan wordt de oorspronkelijke authstore teruggezet.

## GitHub-inrichting

Voeg daarna voor de VPS-testomgeving de drie waarden toe:

- `VPS_TEST_ADMIN_USER` = `vps-e2e-admin`;
- `VPS_TEST_MEMBER_USER` = `vps-e2e-member`;
- `VPS_TEST_E2E_PASSWORD` = het hierboven gegenereerde wachtwoord.

Zet pas nadat de fixture live is en de drie secrets bestaan:

```text
VPS_TEST_AUTH_E2E_ENABLED=true
```

De job `live-authenticated` controleert dan op desktop, tablet en mobiel:

- beheerlogin en veilige sessiecookie;
- toegang van de E2E-admin tot leden- en gebruikersbeheer;
- logout;
- ledenlogin;
- correcte koppeling met `E2E Testlid`;
- zichtbaarheid van contributie, commissie, vergadering/notulen en taak;
- expliciete `403` wanneer het gewone testlid `/beheer/gebruikers.php` probeert te openen.

## Grenzen

- alleen tenant `test` wordt gebruikt in de huidige VPS-testprocedure;
- geen productieaccounts of echte persoonsgegevens;
- geen wachtwoord in argv, repository of workflowlogs;
- geen algemene GitHub-shell naar de VPS nodig;
- geen SFTP/FTP-fixturetransport;
- authenticated E2E blijft uit zolang `VPS_TEST_AUTH_E2E_ENABLED` niet `true` is.
