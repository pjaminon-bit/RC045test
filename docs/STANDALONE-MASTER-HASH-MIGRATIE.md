# Standalone mastercredential migreren naar hash-only

Vanaf fase 5.13 accepteert de authenticatieruntime voor **alle** installaties uitsluitend een geldige `password_hash` als mastercredential. De historische standalonevariabele `$BEHEER_WACHTWOORD` wordt niet meer gebruikt om in te loggen. Een niet-lege plaintextvariabele maakt de masterconfig fail-closed ongeldig.

Externe tenants gebruikten al uitsluitend een gehashte mastercredential onder hun private tenantroot. Deze procedure is daarom alleen bedoeld voor een oudere standalone-installatie met een server-only `beheer-config.php`.

## Veilige volgorde

Voer de migratie uit **voordat** de hash-only webrelease wordt geactiveerd. Gebruik de migrator uit de nieuwe release/source tree tegen het bestaande server-only configuratiebestand.

Controleer eerst zonder mutatie:

```bash
php bin/migrate-standalone-master-hash.php \
  --config=/absoluut/pad/naar/beheer-config.php \
  --check
```

Mogelijke uitkomsten:

- `STANDALONE MASTER CHECK OK  status=hash-only` — de config is al gereed;
- `STANDALONE MASTER CHECK MIGRATION_REQUIRED ...` met exitcode `2` — veilige automatische migratie is mogelijk;
- iedere andere fout — stop en beoordeel de config handmatig. De migrator herschrijft geen conditionele, berekende, geneste of meervoudige assignments.

Voer alleen na een verwachte `MIGRATION_REQUIRED` de migratie uit:

```bash
php bin/migrate-standalone-master-hash.php \
  --config=/absoluut/pad/naar/beheer-config.php \
  --apply
```

De migrator leest het bestaande plaintextsecret uitsluitend uit het reeds server-only configuratiebestand. Het wachtwoord wordt niet als CLI-argument gevraagd, niet naar stdout/stderr geschreven en niet naar een nieuw backupbestand gekopieerd.

Bij plaintext-only configuratie maakt PHP met `PASSWORD_DEFAULT` een nieuwe hash. Als al een geldige `$BEHEER_WACHTWOORD_HASH` aanwezig is, blijft die hash bytegelijk behouden en wordt alleen de oude plaintextassignment verwijderd.

De kandidaatconfig wordt vóór plaatsing gevalideerd en daarna via een tijdelijk bestand atomisch op de plaats van `beheer-config.php` gezet. De uiteindelijke config krijgt mode `0640`. Bij een fout vóór de atomische rename blijft het oorspronkelijke bestand ongemoeid. Na een succesvolle rename schrijft de tool het oude plaintextsecret nooit terug naar disk.

## Nacontrole

Voer na `--apply` opnieuw `--check` uit. Alleen `status=hash-only` met exitcode `0` is een geldige eindtoestand. Activeer daarna de nieuwe webrelease en controleer de masterlogin.

De actieve configuratie moet functioneel neer komen op:

```php
<?php
$BEHEER_WACHTWOORD_HASH = '<geldige password_hash>';
```

Er mag geen niet-lege `$BEHEER_WACHTWOORD` meer aanwezig zijn. Deel of commit de werkelijke hash niet; `beheer-config.php` blijft server-only en staat in `.gitignore`.

## Fail-closed gedrag

De migrator weigert onder andere symlinks, relatieve configpaden, onbekende CLI-opties, ongeldige bestaande hashes, placeholdercredentials en PHP-constructies die niet als één eenvoudige top-level stringassignment kunnen worden bewezen. De webauthenticatie zelf accepteert na deze fase geen plaintext fallback meer, ook niet wanneer een geldige hash daarnaast aanwezig is.
