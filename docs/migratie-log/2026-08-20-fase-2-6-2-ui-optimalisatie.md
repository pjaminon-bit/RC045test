# Fase 2.6.2 — UI-optimalisatie

## Doel
Tweede visuele verfijningsronde op Beheer UI 2026. Geen nieuwe domeinfunctionaliteit; focus op dagelijkse bruikbaarheid, rust, hiërarchie en responsive gedrag.

## Dashboard
- dashboard krijgt expliciete `beheer-dashboard` scope;
- categorieën zijn geen grote geneste cards meer, maar rustige secties;
- de module zelf is de interactieve card;
- modulecards hebben subtiele border, minimale elevation en rustige hoverfeedback;
- oude zware linkeraccentlijn vervalt;
- `*`-markering blijft zichtbaar zoals afgesproken;
- subacties blijven compact en contextueel;
- header blijft op mobiel bereikbaar.

## Typografie en ritme
- lichte typografische schaal uit 2.6.1 blijft uitgangspunt;
- browser font smoothing toegevoegd;
- headline spacing en letterspacing verfijnd;
- contentgutter en verticale ruimte zijn consistenter;
- maximale contentbreedte centraal als token vastgelegd.

## Formulieren en acties
- inputs/selects hebben 42 px basishoogte;
- hover-, focus-, disabled- en placeholderstates centraal;
- pressed state voor knoppen;
- primaire, secundaire en gevaarlijke acties blijven vlak en duidelijk onderscheiden;
- sticky editoracties sluiten visueel aan op de paginaachtergrond.

## Cards, lijsten en tabellen
- cards gebruiken minder shadow en meer borderhiërarchie;
- lijstitems krijgen consistenter verticaal ritme;
- tabelkoppen compacter en rustiger;
- tabelrijen hebben subtiele hoverfeedback;
- `tablewrap` is centraal voorbereid voor brede beheerdata;
- statistiekcards (zoals contributie) hebben een duidelijkere cijferhiërarchie.

## Responsive
- desktop/tablet/mobile ritme opnieuw afgestemd;
- dashboard schakelt gecontroleerd naar één kolom;
- formulieren/toolbars krijgen op kleine schermen meer beschikbare breedte;
- tabellen binnen wrappers blijven horizontaal bruikbaar;
- reduced-motion blijft ondersteund.

## Technische scope
Gewijzigd:
- `beheer/ui-2026.css`
- `beheer/index.php` (alleen dashboard-scope class)
- `tests/phase262-ui.php`
- `.github/workflows/deploy-dev.yml`

Niet gewijzigd:
- capabilities;
- domeinlogica;
- opslag;
- routes;
- CSRF/dataslot;
- permissies.

## Test/deploy
Nieuwe regressietest `tests/phase262-ui.php` bewaakt onder andere dashboardscoping, het verdwijnen van de zware linkeraccentlijn, states, table feedback, responsive gedrag en reduced-motion.

Zoals gebruikelijk: eerst PR-validatie zonder DEV-deploy, daarna maximaal één merge naar `main` en één DEV-deploy.
