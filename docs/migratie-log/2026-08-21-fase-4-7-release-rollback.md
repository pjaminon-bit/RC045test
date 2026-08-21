# Migratielog — fase 4.7 release & rollback

Datum: 21-08-2026

## Doel

Gedeelde applicatiecode veilig als immutable releases kunnen activeren en bij fouten automatisch of handmatig terugrollen, zonder tenantconfiguratie, databases of TLS/DNS-contracten per code-release te herschrijven.

## Gebouwd

- `app/deployment/release-contract.php`
- `bin/prepare-vps-release.php`
- `bin/apply-vps-release.php`
- `bin/check-release-tenant.php`
- `tests/phase47-release-rollback.php`
- `docs/VPS-RELEASES.md`

## Kernbeslissingen

- release-ID is exact 40 hextekens;
- daadwerkelijke release-integriteit komt uit een SHA-256 bestandsmanifest;
- mutable/private Git-ignore-achtige paden worden niet in productie-release opgenomen;
- bestaande release directories worden nooit overschreven;
- `current` wisselt via tijdelijke symlink + atomische rename;
- current-health, kandidaat tenantprobe en Apache configtest vóór een normale deploy;
- FPM reload + volledige tenant health na de wissel;
- post-switch fout rolt current terug en bewijst daarna opnieuw de oude health;
- handmatige rollback gebruikt alleen de vorige gevalideerde release uit root-owned state;
- bootstrap bestaat apart voor de eerste VPS-codebasis vóór tenantactivatie;
- 4.1 runtimevalidatie accepteert een gewijzigde fysieke target alleen via exact gebonden fase-4.7 release-state/transition.

## Bewust niet gedaan

- geen automatische verwijdering/retentie van oude releases;
- geen releaseactie vanuit gewone verenigingsbeheer-GUI;
- geen secrets in releaseplan/state/eventlog;
- geen echte VPS-switch vanuit CI/DEV.

## Productiegrens

De code en acceptatietests kunnen in CI worden bewezen. Werkelijke root-write naar `/srv/verenigingsplatform`, FPM reloads en tenant-healthchecks vinden pas plaats op de echte VPS.
