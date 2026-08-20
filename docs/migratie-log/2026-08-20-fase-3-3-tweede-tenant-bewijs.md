# Fase 3.3 — tweede tenant als platformbewijs

Datum: 20 augustus 2026

## Doel

Bewijzen dat dezelfde gedeelde RC045test-codebase twee fictieve verenigingen kan draaien zonder dat de tweede vereniging RC045-identiteit, branding, SEO, modulekeuzes, data of uploads erft.

De testverenigingen zijn bewust inhoudelijk verschillend:

- `noorderhaven` — Roeivereniging Noorderhaven;
- `duinrand` — Wandelclub Duinrand.

Er wordt geen applicatiecode naar tenantmappen gekopieerd. Beide runtimeprocessen gebruiken exact dezelfde repositorycode met alleen een eigen externe tenantconfiguratie en private root.

## Gevonden platformlekken

De bestaande provisioner schreef vóór deze fase alleen identiteit en opslagconfiguratie. Omdat `site-config.php` voor standalone RC045 veilige RC045-defaults bevat, erfde een nieuwe externe tenant daardoor impliciet:

- RC045-logo en social image;
- RC045-favicons en webmanifest;
- RC045-kleuren;
- de standaardmodulemap;
- RC045/Eygelshoven-specifieke SEO-teksten uit `app/core/site-seo.php`.

Dat gedrag is voor een gedeeld platform niet acceptabel, ook al waren data en authenticatie al fysiek gescheiden.

## Provisioningprofiel

`bin/provision-tenant.php` schrijft vanaf fase 3.3 voor iedere nieuwe tenant expliciet:

- neutrale platformbranding zonder gedeelde RC045-assets;
- alle bekende moduleflags als expliciete booleans;
- de actieve modules ook in `tenant.json`.

Nieuwe optie:

```text
--modules=website,ledenadministratie,aanmelden,...
```

Wanneer `--modules` ontbreekt blijven, voor compatibiliteit met het bestaande provisioninggedrag, alle platformmodules actief. Wanneer de optie wel wordt opgegeven geldt een allowlist van bekende modules en wordt iedere niet gekozen module expliciet `false`. De kernmodule `website` is verplicht. Onbekende modules en een profiel zonder `website` falen voordat een tenantmap wordt geschreven.

## Neutrale branding

Nieuwe VPS-tenants krijgen geen logo, social image, favicon of manifest uit de gedeelde RC045-codebase. De assetpaden zijn initieel leeg en de kleurwaarden zijn generieke platformwaarden. Een vereniging kan die waarden later uitsluitend in de eigen server-only config vervangen.

Standalone RC045 behoudt de bestaande defaults in `site-config.php` en verandert visueel niet door deze fase.

## Tenant-aware SEO

`app/content/seo-head.php` gebruikt voor standalone RC045 nog steeds `app/core/site-seo.php`.

Voor een externe tenant met `private_root` worden neutrale SEO-definities opgebouwd uit de tenantnaam. Daardoor verschijnen geen verwijzingen naar RC045, RC-auto's of Eygelshoven in een nieuwe vereniging. Een tenant kan toekomstige of handmatige maatwerk-SEO via `seo.paginas` in de eigen configuratie overschrijven.

Wanneer geen social image is geconfigureerd worden `og:image` en `twitter:image` niet meer met een onjuiste fallback-URL uitgestuurd.

## Acceptatietest

`tests/phase33-second-tenant.php` maakt twee tenants aan met verschillende namen, URL's en moduleprofielen en controleert vervolgens in afzonderlijke PHP-processen:

- dezelfde gedeelde applicatiecode wordt gebruikt;
- tenantidentiteit en private roots zijn verschillend;
- gegenereerde tenantconfig bevat geen RC045-identiteit;
- branding-assets zijn leeg en dus niet gedeeld;
- moduleprofielen verschillen aantoonbaar;
- onbekende modules en een profiel zonder `website` worden fail-closed geweigerd;
- SEO toont uitsluitend de eigen tenantnaam en geen RC045/Eygelshoven-content;
- dezelfde publieke dataset kan door beide tenants onafhankelijk worden geschreven en gelezen;
- dezelfde private collectie kan door beide tenants onafhankelijk worden geschreven en gelezen;
- publieke uploadnamespaces resolveren fysiek naar verschillende tenantroots.

De test is toegevoegd aan de verplichte GitHub Actions-platformvalidatie.

## Bewuste grens

De eerste beheerder blijft in deze stap nog bewust server-side via `private/auth/master.php` ingesteld. De provisioner maakt nog steeds geen standaardcredential aan. Automatisering van een eerste beheerder hoort als afzonderlijke securitystap te gebeuren, zodat geen plaintext wachtwoord via CLI-argumenten, Git, logs of shell history wordt geïntroduceerd.

DNS, TLS, vhost en PHP-FPM-isolatie blijven eveneens deploymenttaken voor de VPS-fase.
