# Fase 3.2.1 — provisioner pad-hardening

Datum: 2026-08-20

## Aanleiding

De oorspronkelijke `provisionOnderProjectroot()` controleerde hoofdzakelijk de tekstuele vorm van een tenantpad. Daardoor was de veiligheidsgrens onvoldoende sterk voor een gedeelde VPS: filesystemindirectie via symlinks of ambiguë padsegmenten kon afwijken van het pad dat de provisioner dacht te controleren.

## Wijzigingen

- `--root` weigert nu expliciet `.` en `..` als padsegment.
- Nulkarakters in `--root` worden geweigerd.
- Bestaande symlinks in `--root` of een ancestor worden geweigerd, inclusief broken symlinks.
- Een nog niet bestaand doel wordt gecanonicaliseerd vanaf de langste bestaande ancestor via `realpath()`.
- De fysieke tenantroot wordt tegen de echte applicatieroot gecontroleerd.
- Een vooraf bestaande symlink op de tenantroot of een afgeleide private submap wordt geweigerd.
- Tenantdirectories worden na aanmaak opnieuw fysiek gecontroleerd.
- `config.php`, `runtime.env`, `tenant.json` en tijdelijke schrijfbestanden worden vlak vóór gebruik opnieuw op containment en symlinks gecontroleerd.
- Een symlink op een te schrijven configbestand wordt ook met `--force` geweigerd.
- Idempotentie voor bestaande gewone configbestanden blijft behouden.
- De provisioner maakt tenant- en privatemappen één niveau per stap aan nadat de basisroot is gecontroleerd.

## Testdekking

Nieuwe test: `tests/phase321-provisioner-path-security.php`.

De test dekt onder andere:

1. veilige provisioning naar een volledig nog niet bestaande externe root;
2. idempotente tweede run;
3. `..` en `.` in `--root`;
4. symlink als `--root`;
5. symlink richting de applicatieroot;
6. symlink als bestaande ancestor van een nog niet bestaand pad;
7. vooraf bestaande symlink op de tenantroot;
8. broken symlink;
9. interne symlink op `private/`;
10. symlink op `config.php` met `--force`, inclusief canarycontrole dat het externe doel niet wordt gewijzigd.

De test is toegevoegd aan de standaard PR/main validatie.

## Bewuste grens

Dit verkleint misconfiguratie- en symlinkrisico sterk, maar de PHP-filesystem-API biedt hier geen volledige `openat(..., O_NOFOLLOW)`-achtige transactieboundary over de hele provisioningboom. Daarom blijft operationeel vereist dat de bovenliggende provisioningroot op de VPS niet gelijktijdig schrijfbaar is voor onbetrouwbare lokale gebruikers. `/srv/verenigingen` hoort eigendom/rechten te hebben die alleen de vertrouwde provisioning-/beheeraccount mutaties laten uitvoeren.

## Buiten scope

- strikte validatie van de tenantkey volgt in auditoptie 6;
- publieke content/media tenant-isolatie volgt in latere auditopties;
- algemene backupmigratie volgt apart.
