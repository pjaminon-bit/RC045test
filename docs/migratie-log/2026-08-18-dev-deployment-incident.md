# DEV deployment-incident

Datum: 2026-08-18

## Wat ging mis

De ontwikkelworkflow in `.github/workflows/deploy-dev.yml` luisterde nog uitsluitend naar pushes op `main`, terwijl de template-migratie vanaf `agent/template-foundation` werd ontwikkeld. Daardoor werden commits op die ontwikkelbranch wel in GitHub opgeslagen maar niet automatisch naar `/dev` gedeployed.

Praktijktests die zijn uitgevoerd nadat `/dev` niet meer gelijkliep met de ontwikkelbranch mogen daarom niet als validatie van de betreffende nieuwe code worden gebruikt.

## Herstel

- DEV-workflow gewijzigd naar pushes op `agent/template-foundation`.
- `workflow_dispatch` blijft beschikbaar.
- Iedere deploy maakt `dev-build.json` met omgeving, branch, volledige en korte commit-SHA, deploytijd en GitHub Actions runnummer.
- Beheer laadt een DEV build-indicator die deze informatie toont wanneer `dev-build.json` aanwezig is.
- Productie/lokale omgevingen zonder `dev-build.json` tonen geen indicator.

## Validatiestatus

Geen code uit fase 1 of fase 2 is verloren gegaan. Alleen praktijktests die op een verouderde DEV-build zijn uitgevoerd zijn ongeldig verklaard.

Opnieuw kort te controleren nadat de nieuwe workflow succesvol heeft gedeployed:

1. DEV-buildindicator toont `agent/template-foundation` en een recente commit/deploytijd.
2. 1A/1B: centrale branding/configuratie zichtbaar en site functioneel.
3. 1C: minimaal één moduleflag test (uit = publieke/beheerroute geblokkeerd, daarna terug aan).
4. 1D: Ontstaan en Baanreglement via generieke editor opslaan, publieke weergave, log en back-up.
5. Fase 2 Sponsors opnieuw praktisch valideren.
6. Fase 2 Agenda praktisch valideren.

Vanaf dit moment geldt als testregel: een DEV-test telt alleen als de buildindicator vooraf overeenkomt met de bedoelde branch/commit.
