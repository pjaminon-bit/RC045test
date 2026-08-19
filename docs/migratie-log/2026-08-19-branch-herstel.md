# Branch-herstel — 19 augustus 2026

## Aanleiding

Tijdens het vervolg van de template-migratie werd per ongeluk opnieuw op de default branch `main` gewerkt. Daardoor leek onder andere `beheer.php` weer de oude monolithische beheerpagina te bevatten, terwijl de daadwerkelijke refactor al op `agent/template-foundation` stond.

## Vaststelling

- `agent/template-foundation` is de afgesproken ontwikkelbranch voor de verenigingswebsite-template.
- DEV wordt via `.github/workflows/deploy-dev.yml` uitsluitend vanaf `agent/template-foundation` gedeployed.
- De ontwikkelbranch bevat de nieuwe echte `/beheer/`-map, de opgesplitste beheeronderdelen, de centrale siteconfiguratie, content-renderers en de eerdere fase-1/fase-2 migratiestappen.
- `main` liep ver achter op de ontwikkelbranch en bevatte nog de oude grote `beheer.php`.
- Vier commits die op 19 augustus 2026 abusievelijk op `main` waren gemaakt (`site-config.php`, SEO-koppeling, voortgangs-README en uitbreiding van de configuratie) overlapten met reeds verder uitgewerkte functionaliteit op `agent/template-foundation`.

## Herstel

- `main` is teruggezet naar commit `5ed42937bc8775fdf7003b6bd51a73496b4c6a88`, het punt direct vóór de vier foutief geplaatste template-commits.
- De foutieve commits zijn niet naar de ontwikkelbranch gemerged, omdat `agent/template-foundation` al een uitgebreidere en correctere configuratiearchitectuur bevat.
- Vanaf dit punt worden nieuwe templatewijzigingen uitsluitend op `agent/template-foundation` uitgevoerd.

## Bron van waarheid

Voor de template-migratie geldt vanaf nu:

1. `agent/template-foundation` = ontwikkelbranch en bron van waarheid.
2. `/dev` = testomgeving voor deze branch.
3. `main` = niet gebruiken voor lopende templateontwikkeling.
4. Besluiten en uitgevoerde stappen worden bijgehouden in `docs/TEMPLATE-MIGRATIE.md` en `docs/migratie-log/`.

## Opmerking beheer

De actuele beheerapplicatie staat op de ontwikkelbranch in `beheer/index.php`. Het bestand `beheer.php` is daar alleen nog een backwards-compatible 308-redirect naar `/beheer/` en bevat geen beheerformulieren meer.
