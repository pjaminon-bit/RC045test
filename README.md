# RC045test — verenigingsplatform

`RC045test` is de bronrepository voor een PHP-gebaseerd verenigingsplatform. De repository bevat nog de oorspronkelijke RC045-website en een standalone/templatecompatibiliteitslaag, maar de leidende architectuur is inmiddels **multi-tenant**: één gedeelde applicatiecodebase bedient meerdere verenigingen met per tenant geïsoleerde configuratie, authenticatie, private data en runtime-identiteit.

Deze README is de routekaart voor onderhouders en reviewers. Operationele details horen in de gekoppelde documenten en in de actuele code-/CI-contracten, niet in deze samenvatting.

## Architectuur in het kort

### Gedeelde applicatiecode

- Alle VPS-tenants gebruiken dezelfde gedeelde codebase; provisioning kopieert de applicatiecode niet per vereniging.
- Releases worden immutable per commit opgebouwd en geactiveerd via de platformreleaseflow; de actieve release is een verwijzing naar een fysieke release, niet een map waarin live bestanden worden overschreven.
- Repositorycode, tenantdata en privileged host-tooling hebben gescheiden trust boundaries. Permanente root-entrypoints horen buiten de actieve applicatierelease en mogen geen repository-/app-PHP als root uitvoeren.

### Tenantisolatie

Een externe tenant krijgt een eigen server-side root, bijvoorbeeld:

```text
/srv/verenigingen/<tenant>/
├── config.php
├── runtime.env
├── tenant.json
└── private/
```

`private/` bevat onder meer auth-, sessie-, opslag-, audit-, backup- en andere server-only data en staat buiten de publieke documentroot. Nieuwe VPS-tenants moeten fail-closed aan hun eigen configuratie worden gebonden met onder andere:

```text
VERENIGING_REQUIRE_TENANT_CONFIG=1
VERENIGING_CONFIG_FILE=/srv/verenigingen/<tenant>/config.php
```

Ontbrekende of onbruikbare verplichte tenantconfiguratie mag nooit terugvallen naar RC045/defaultinstellingen. Zie [Tenant provisioning](docs/PROVISIONING.md).

### Standalone/templatecompatibiliteit

De repository ondersteunt nog een losse/standalone configuratieroute, onder andere via [`site-config.local.example.php`](site-config.local.example.php), plus migratie- en templatecompatibiliteit voor bestaande installaties. Die paden zijn **compatibiliteitslagen** en niet de architectuurbron voor nieuwe VPS-tenants. Begin voor nieuwe tenants bij [Tenant provisioning](docs/PROVISIONING.md) en de VPS-runtime/deploymentcontracten.

## Runtime en lokale checks

De productie-runtime controleert centraal de vereiste PHP-extensies in [`app/deployment/php-runtime-requirements.php`](app/deployment/php-runtime-requirements.php): `openssl`, `pdo_pgsql`, `mbstring`, `curl` en `dom`. De actuele VPS-/compatibiliteitslijn gebruikt PHP 8.5. Browser- en JavaScripttooling vereist Node.js 20 of nieuwer; zie [`package.json`](package.json).

Voor een volledige lokale bronregressie:

```bash
npm ci
bash tests/run-all.sh
```

`tests/run-all.sh` controleert JavaScript-syntax, voert iedere PHP-regressietest in `tests/` uit en bewaakt repositorygrenzen. Voor dezelfde dekking als CI moet de PHP-CLI ook de testafhankelijke extensies bevatten, waaronder `pdo_sqlite`.

De vier verplichte PR-gates zijn:

1. **Validate RC045test** — `.github/workflows/deploy-dev.yml`;
2. **Full regression acceptance** — `.github/workflows/full-regression.yml`;
3. **PHP 8.5 compatibility** — `.github/workflows/php85-compatibility.yml`;
4. **Security supply-chain** — `.github/workflows/security-supply-chain.yml`.

Een PR alleen geldt niet als afrondbewijs voor audit- of hardeningwerk. Wanneer een wijziging de VPS-runtime raakt, horen normale deploy en relevante post-deploy live-acceptatie bij het bewijs.

## Waar vind ik wat?

| Onderwerp | Canonieke ingang |
|---|---|
| Nieuwe tenant / filesystem- en configisolatie | [docs/PROVISIONING.md](docs/PROVISIONING.md) |
| Eerste tenantbeheerder | [docs/ADMIN-BOOTSTRAP.md](docs/ADMIN-BOOTSTRAP.md) |
| GitHub Actions → VPS-test en deploygrenzen | [docs/GITHUB-VPS-TEST-DEPLOYMENT.md](docs/GITHUB-VPS-TEST-DEPLOYMENT.md) |
| Platform control-plane / beheertrust-boundary | [docs/VPS-CONTROL-PLANE.md](docs/VPS-CONTROL-PLANE.md) |
| Authenticated VPS-E2E | [docs/VPS-AUTHENTICATED-E2E.md](docs/VPS-AUTHENTICATED-E2E.md) |
| Cryptografisch geauthenticeerde tenantbackups | [docs/BACKUP-ATTESTATION.md](docs/BACKUP-ATTESTATION.md) |
| Template-/standalonemigratie | [docs/TEMPLATE-MIGRATIE.md](docs/TEMPLATE-MIGRATIE.md) |
| Volledige automatische bronregressie | [`tests/run-all.sh`](tests/run-all.sh) |
| Security-/hardeningaudit en actuele handover | [GitHub issue #138](https://github.com/pjaminon-bit/RC045test/issues/138) |

`docs/VPS-DEPLOYMENT.md` bevat nog historische gefaseerde opbouwnotities en wordt apart gereconcilieerd onder auditissue #161. Gebruik dat document zolang #161 open is niet als enige bron voor de actuele deployarchitectuur.

## Security- en onderhoudsregels

- Houd secrets, tenantconfiguratie en private data buiten Git en buiten de documentroot.
- Voer repository-/release-PHP niet als root uit; respecteer de bestaande privileged host/release trust boundary.
- Verruim tenant-, auth-, CSP-, deploy- of filesystemgrenzen niet om een test of migratie eenvoudiger te maken.
- Behandel de actuele GitHub-state en de nieuwste handovercomment in [#138](https://github.com/pjaminon-bit/RC045test/issues/138) als leidend voor de Security & Hardening Audit; oudere trackerbodytekst kan achterlopen.
- Voor nieuwe architectuurkeuzes hebben actuele runtime-, provisioning-, deployment- en regressiecontracten voorrang op legacy standalonecomments.

## Repositorydoel

De repository is daarmee tegelijk:

- de gedeelde applicatiecode voor het verenigingsplatform;
- de template-/compatibiliteitsbasis voor RC045 en bestaande standalonegebruikers;
- de bron voor tenant provisioning, runtime-, lifecycle- en control-planecontracten;
- de bron voor GitHub Actions, securityregressies en live VPS-testacceptatie.

Voor een nieuwe maintainer is de aanbevolen route: **README → provisioning/runtime → relevante operationele doc → `tests/run-all.sh` / workflows → audittracker #138**.
