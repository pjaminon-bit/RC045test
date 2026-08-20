# Fase 3.2.1 — auth-isolatie per tenant

Datum: 2026-08-20

## Doel

Voorkomen dat meerdere verenigingen op één gedeelde codebase hetzelfde beheer-hoofdaccount, gewone beheeraccounts, auditlog of brute-force/lockoutstate delen.

## Securitygrens

Zodra een tenant een expliciete `private_root` heeft, worden authbestanden uitsluitend daar opgelost:

- `private/auth/master.php`
- `private/auth/users.json`
- `private/audit/log.json`
- `private/security/login-attempts.json`
- `private/security/.login-attempts.lock`
- `private/backups/auth/`

Voor deze tenant bestaat geen fallback naar de legacy authbestanden in de projectroot.

## Achterwaartse compatibiliteit

Een bestaande standalone installatie zonder `private_root` blijft voorlopig de huidige bestanden gebruiken:

- `beheer-config.php`
- `beheer-users.json`
- `beheer-log.json`
- `beheer-login-pogingen.json`
- `data-backups/`

Dit migratiepad is alleen voor bestaande standalone RC045/DEV en is niet de configuratie voor nieuwe VPS-tenants.

## Implementatie

- `app/auth-storage.php` toegevoegd als centrale resolver voor alle authpaden.
- `auth.php` gebruikt uitsluitend het resolvercontract voor masterconfig, users, audit en login-lockout.
- gebruikersbackups hebben een aparte tenant-lokale `authBackupMap` gekregen;
- de algemene `$dataBackupMap` is bewust niet naar deze authmap omgebogen, zodat de latere brede backupmigratie een aparte gecontroleerde stap blijft;
- auth write-directories worden met `0750` aangemaakt;
- geschreven users/audit/lockout/lockbestanden en authbackups worden naar `0640` aangescherpt;
- de provisioner maakt de benodigde `auth`, `audit`, `security` en `backups/auth` mappen;
- provisioning maakt bewust geen standaard mastercredential aan.

## Integratietest

`tests/phase321-auth-isolation.php` plaatst expres herkenbare RC045-canary authbestanden in de gedeelde projectroot en bouwt twee externe tenants.

De test bewijst dat:

- tenant A uitsluitend zijn eigen masterconfig en gebruiker ziet;
- tenant B zonder eigen masterconfig ongeconfigureerd blijft, ondanks een geldige RC045-canary master in de projectroot;
- tenant B de RC045-canary gebruiker niet ziet;
- audit- en lockoutwrites fysiek in de juiste tenant-root terechtkomen;
- gebruikersbackups onder `private/backups/auth` blijven;
- tenantwrites de gedeelde legacy canary-bestanden niet wijzigen;
- standalone legacy-authpaden compatibel blijven.

## Bewust nog niet in deze stap

- sessie aan `tenant_key` binden;
- tenant-specifieke sessienaam/cookie;
- publieke content en uploads tenant-lokaal maken;
- het algemene backupregister tenant-aware maken;
- mastercredential automatisch bootstrappen.

Deze punten blijven afzonderlijke auditbevindingen zodat wijzigingen klein en controleerbaar blijven.
