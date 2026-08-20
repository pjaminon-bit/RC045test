# Fase 3.2.1 — optie 7: publieke content-isolatie

Datum: 2026-08-20

## Doel

Voorkomen dat een externe tenant publieke JSON-content leest of overschrijft uit de gedeelde RC045 `/data`-map wanneer meerdere verenigingen dezelfde applicatiecode gebruiken.

## Wijzigingen

- Centrale `app/content/public-content-store.php` toegevoegd met expliciete dataset-whitelist.
- Externe/private-root tenants gebruiken `<private_root>/public-content/*.json`.
- Ontbrekende tenantbestanden vallen niet terug op RC045 `/data`.
- Externe tenant zonder `private_root` faalt gesloten.
- `public-content.php` toegevoegd als publiek read-only GET/HEAD-endpoint; willekeurige bestandspaden/traversal zijn niet mogelijk.
- Bestaande `/data/<dataset>.json`-URL's voor gewhiteliste datasets worden via `.htaccess` naar het endpoint geleid. Standalone RC045 blijft via dezelfde resolver compatibel.
- Generieke contentpagina's (`homepage`, `ontstaan`, `baanreglement`, `aanmelden`) gebruiken de tenant-contentresolver.
- Gedeelde beheereditor remapt bekende legacy `/data/*.json`-paden naar tenantopslag.
- Agenda, Bedankt, Sponsors en Lidmaatschapstypen zijn expliciet tenant-aware gemaakt omdat zij eigen lees-/schrijflogica hadden.
- `lidmaatschapstypen.json` is meegenomen omdat de publieke aanmeldflow deze rechtstreeks gebruikt voor tarieven/typekeuze.
- Tenantwrites naar publieke content worden niet meer in de oude gedeelde `data-backups` gekopieerd; tenant-aware backup/restore volgt in optie 9.
- Provisioner maakt voortaan `private/public-content/` met server-only directoryrechten aan.
- Door deze laag geschreven tenantbestanden worden naar `0640` aangescherpt.

## Tests

Nieuwe `tests/phase321-public-content-isolation.php` controleert onder meer:

- expliciete whitelist en afwijzing van traversal/onbekende keys;
- standalone RC045-compatibiliteit;
- fysiek gescheiden homepage-canaries voor tenant A en B;
- geen fallback naar het bestaande RC045 `lidmaatschapstypen.json` wanneer tenant B dit bestand mist;
- legacy beheerpad `/data/contact.json` schrijft uitsluitend naar B's private public-content-map;
- lidmaatschapstypen schrijven/lezen uitsluitend tenant-lokaal;
- bestaande RC045-bestanden blijven hashmatig ongewijzigd;
- publiek endpoint serveert de actieve tenant, geeft 404 bij ontbrekende data en weigert traversal;
- externe tenant zonder `private_root` faalt gesloten.

De bestaande provisionertest controleert daarnaast dat `private/public-content/` daadwerkelijk wordt aangemaakt. De CI-workflow voert de nieuwe test uit en de DEV-smoke controleert zowel de legacy `/data/lidmaatschapstypen.json`-route als het nieuwe endpoint en een ongeldige endpoint-key.

## Bewuste fasegrenzen

- Media, fotoboek en fysieke uploads blijven voor optie 8.
- Sponsor-JSON is al tenant-lokaal; sponsorlogo's blijven tot optie 8 op hun bestaande uploadpad.
- Het algemene backup-/restorebeheer blijft optie 9 en is nog niet tenant-aware.
- RC045-specifieke hardcoded fallbacktekst/branding in templates en JavaScript wordt pas bij de neutrale platformdefaults aangepakt; optie 7 voorkomt opslagfallbacks, niet alle RC045-tekst in de gedeelde code.
