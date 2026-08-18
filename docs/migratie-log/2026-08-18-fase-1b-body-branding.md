# Fase 1B — body-branding centraal

Datum: 2026-08-18
Branch: `agent/template-foundation`

## Uitgevoerd

- `siteSlogan()` toegevoegd aan `site.php`.
- Het configureerbare logo uit `branding.logo` wordt tijdens de fase-1 outputfilter gebruikt voor de bestaande publieke navigatie- en footerlogo's.
- Bekende `RC045` alt-teksten bij het logo worden vervangen door de configureerbare verenigingsnaam.
- `.nav-logo-text` gebruikt in de gerenderde HTML de configureerbare verenigingsnaam.
- `.nav-logo-sub` gebruikt in de gerenderde HTML de configureerbare slogan.
- De footermerkregel `RC045 · Bashers of the South` wordt opgebouwd uit configureerbare naam + slogan.

## Bewuste begrenzing

De filter vervangt alleen bekende branding-markup. Inhoudelijke teksten waarin RC045 als onderwerp voorkomt worden niet globaal vervangen. Daarmee voorkomen we dat historie, mediaberichten, SEO-content of andere clubinhoud onbedoeld verandert.

## Tijdelijk karakter

Net als de centrale head-branding is deze body-vervanging een migratiehulpmiddel. Wanneer de publieke pagina's later individueel worden opgeschoond, worden logo/naam/slogan rechtstreeks via helpers gerenderd en kan dit deel van de outputfilter verdwijnen.

## Volgende stap

De CSS-themawaarden (`--teal`, `--gold`, `--dark`, enz.) vanuit `site-config.php` genereren, zodat kleuren per vereniging configureerbaar worden zonder `styles.css` te kopiëren.
