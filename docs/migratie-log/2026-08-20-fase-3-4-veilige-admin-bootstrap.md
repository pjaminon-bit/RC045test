# Fase 3.4 — veilige eerste beheerder/bootstrap

Datum: 20 augustus 2026

## Doel

Een nieuwe geprovisioneerde vereniging veilig activeerbaar maken voor beheer zonder een standaardwachtwoord en zonder plaintext credentials in Git, tenantconfiguratie, CLI-argumenten of normale uitvoer.

## Implementatie

Nieuw CLI-commando:

```text
bin/bootstrap-tenant-admin.php
```

Het commando schrijft uitsluitend `private/auth/master.php` voor een reeds geprovisioneerde externe tenant. De opgeslagen waarde is een `password_hash(..., PASSWORD_DEFAULT)` die rechtstreeks aansluit op de bestaande masterlogin in `auth.php`.

## Secretinvoer

Twee invoerpaden zijn toegestaan:

1. interactieve Linux-TTY met uitgeschakelde terminalecho en dubbele bevestiging;
2. expliciet `--password-stdin` voor een server-side secretbron.

Wachtwoord- of hashwaarden als CLI-argument worden fail-closed geweigerd. Wachtwoorden moeten minimaal 14 tekens lang zijn, zijn maximaal 256 bytes en mogen geen nul/newline-besturingstekens bevatten. Het historische placeholderwachtwoord wordt geweigerd.

## Tenant- en padbinding

Voor een write controleert de bootstrap fysiek:

- externe config buiten de project/documentroot;
- canonieke tenant-key;
- externe private root;
- config en private root onder dezelfde tenantroot;
- hetzelfde tenant-key in `tenant.json`;
- exacte manifestbinding aan `config_file` en `private_root`;
- fail-closed `require_tenant_config=true`;
- symlinkvrije auth- en backupmappen binnen de private root.

Hiermee kan een gekopieerde tenantconfig niet worden gebruikt om een andere tenantcredential te besturen.

## Write- en rotatiegedrag

De eerste bootstrap is een create-only handeling. Als `master.php` al bestaat wordt overschrijven geweigerd.

Credentialrotatie vereist expliciet `--rotate`. Voor rotatie wordt de bestaande hash geback-upt onder `private/backups/auth/`. De rotatieretentie is maximaal 20 masterbackups en maximaal 90 dagen.

Credentialwrites gebruiken een tenant-lokale exclusieve lock, tijdelijk bestand in dezelfde authmap, `0640` rechten, een herhaalde symlinkcheck en atomische rename.

## Testdekking

`tests/phase34-admin-bootstrap.php` controleert onder meer:

- geen standaardcredential na provisioning;
- weigeren van plaintext password/hash in argv;
- fail-closed gedrag zonder TTY of `--password-stdin`;
- minimale wachtwoordlengte;
- alleen hash op disk en geen secret/hash in stdout;
- filemode `0640`;
- create-only eerste bootstrap;
- expliciete rotatie en backup van de vorige hash;
- weigeren van `--rotate` vóór eerste bootstrap;
- cross-tenant configbinding;
- manifest tenant-key binding;
- config- en master-symlinkaanvallen;
- extern symlinkdoel blijft ongemoeid;
- lock + atomische plaatsing.

De test is toegevoegd aan de verplichte GitHub Actions-platformsuite. De DEV-smoketest controleert ook dat het bootstrap-script via HTTP 403 retourneert.
