# VPS releases & rollback — fase 4.7

Fase 4.7 beheert de gedeelde applicatiecode op de VPS als immutable releases. Tenantconfiguratie, databases, uploads, sessies en andere mutable data blijven buiten deze releaseboom.

## Layout

```text
/srv/verenigingsplatform/
├── current -> releases/<40-hex-commit>
├── release-state.json
├── release-events.jsonl
└── releases/
    ├── <commit-a>/
    │   └── .verenigingsplatform-release.json
    └── <commit-b>/
        └── .verenigingsplatform-release.json
```

Iedere release wordt na staging root-owned en read-only gemaakt: directories `0555`, bestanden `0444`. Een bestaande `releases/<commit>` wordt nooit overschreven. Fase 4.7 verwijdert ook geen oude release automatisch; een release kan nog door `deployment.json` of rollback-state worden gerefereerd.

## Wat komt niet in een release

Het manifest sluit mutable of private paden bewust uit, waaronder `data/`, `data-backups/`, geüploade sponsor-/fotoboekbestanden, lokale siteconfiguratie, beheercredentials/logs, private ledenbestanden, SQLite-bestanden, `.git`, `.github` en `dev-build.json`.

De commit-ID is een release-identificatie. De werkelijke inhoudsintegriteit wordt bepaald door een deterministisch SHA-256-manifest over relatieve paden, bestandsgrootte en bestandshash.

## Releaseplan voorbereiden

Gebruik een schone staging checkout/artifact en schrijf het plan **buiten** die bronboom:

```bash
php bin/prepare-vps-release.php \
  --source=/opt/verenigingsplatform-staging/RC045test \
  --commit=<40-hex-git-commit> \
  --platform-root=/srv/verenigingsplatform \
  --tenant-base=/srv/verenigingen \
  --output=/opt/verenigingsplatform-staging/release-plan.json
```

Root-vrije hercontrole:

```bash
php bin/apply-vps-release.php \
  --plan=/opt/verenigingsplatform-staging/release-plan.json \
  --check
```

Iedere wijziging in de stagingbron na het maken van het plan maakt de check ongeldig.

## Eerste VPS-bootstrap

`--bootstrap` is alleen bedoeld vóórdat tenants actief zijn. Er mag nog geen `current`, `release-state.json` of gevulde tenantbasis bestaan.

```bash
sudo php bin/apply-vps-release.php \
  --plan=/opt/verenigingsplatform-staging/release-plan.json \
  --bootstrap
```

Hiermee ontstaat de eerste immutable release en de `current`-symlink. Daarna kunnen de bestaande 3.5/4.1–4.6 provisioningstappen tegen `/srv/verenigingsplatform/current` worden uitgevoerd.

## Normale deploy

```bash
sudo php bin/apply-vps-release.php \
  --plan=/opt/verenigingsplatform-staging/release-plan.json \
  --deploy
```

De volgorde is fail-closed:

1. globale release-lock nemen;
2. bronmanifest opnieuw controleren;
3. kandidaat immutable onder `releases/<commit>` stagen;
4. huidige release moet voor alle tenants gezond zijn;
5. PHP-syntax van de kandidaat controleren;
6. kandidaat als iedere echte tenant Linux-user read-only tegen config + PostgreSQL testen;
7. `apache2ctl configtest`;
8. transition-state schrijven;
9. `current` via tijdelijke symlink + `rename()` atomisch wisselen;
10. betrokken PHP-FPM services reloaden;
11. volledige 4.6 healthcheck voor iedere tenant uitvoeren;
12. pas daarna kandidaat als actief en vorige release als `previous` vastleggen.

Een kandidaatprobe schrijft geen tenantdata: hij controleert configuratie en doet alleen `SELECT 1` tegen de eigen database.

## Automatische rollback bij mislukte deploy

Faalt FPM reload of een post-switch healthcheck, dan:

- wordt `current` teruggezet naar de oorspronkelijke release;
- worden de betrokken FPM-services opnieuw geladen;
- wordt de oorspronkelijke release opnieuw via 4.6 health gecontroleerd;
- wordt de mislukte kandidaat niet als gevalideerde `previous` geregistreerd;
- wordt het resultaat in `release-events.jsonl` vastgelegd.

Als zelfs de teruggezette release niet gezond kan worden bewezen, eindigt de tool met een aparte kritieke foutstatus.

## Handmatige rollback

Rollback gebruikt uitsluitend `previous` uit de gevalideerde root-owned state. Er is bewust geen `--to=/willekeurig/pad` of webparameter.

```bash
sudo php bin/apply-vps-release.php --rollback
```

Een rollback hoeft de huidige release niet eerst gezond te verklaren; dat zou herstel van een defecte release kunnen blokkeren. De vorige release krijgt wel kandidaatpreflight, Apache configtest, FPM reload en volledige post-switch healthcontrole.

## Release-aware infrastructuurcontract

Fase 3.5 bewaart historisch het fysieke releasepad dat actief was toen `deployment.json` werd gemaakt. Vanaf 4.7 mag `/srv/verenigingsplatform/current` later naar een andere fysieke release wijzen **alleen** wanneer:

- `current` een symlink is naar een direct kind van `releases/` met een 40-hex commitnaam;
- die release een geldige `.verenigingsplatform-release.json` heeft;
- `release-state.json` exact dezelfde commit, pad en manifesthash als `active` bevat, of de release exact `from`/`to` van een lopende transition is.

Daardoor blijven de reeds geteste 4.1–4.6 infrastructuurplannen bruikbaar zonder DNS/TLS/databaseplannen bij iedere code-release kunstmatig te herschrijven. Een losse of handmatig omgezette `current` blijft fail-closed ongeldig.

## Geen GUI-actie

Release/deploy/rollback is in deze fase bewust root/operatorfunctionaliteit. Een toekomstige platform-control-plane kan deze vaste primitive aanroepen, maar een gewone verenigingsbeheerder krijgt geen directe filesystem-, systemd- of releasebevoegdheid.
