# Fase 5.4.1 — RC045-templatepariteit voor publieke tenants

## Aanleiding

De eerste uitwerking van fase 5.4 maakte ten onrechte een nieuwe, vereenvoudigde homepage met vier menu-items en vier secties. Dat was niet het afgesproken templatemodel. RC045 is de gedeelde visuele en structurele basis; alleen tenantidentiteit, inhoud, links en media mogen verschillen.

## Oplossing

- standalone RC045 en externe tenants lopen door dezelfde `index.php`, `styles.css`, `site-i18n.js` en `homepage.js`;
- externe tenants behouden dezelfde zeven menu-items, tien homepageonderdelen, sectievolgorde, grids en responsive breakpoints;
- een server-side tenantfilter vult naam, slogan, thema, logo, tekst, contactgegevens, links en media veilig in;
- homepage- en contactdata worden alleen gebruikt wanneer ze geen historische RC045-fingerprints bevatten;
- onveilige legacydata valt terug op neutrale tekst en wordt gelogd zonder inhoud te loggen;
- nieuwe tenants krijgen neutrale `homepage.json` en `contact.json` bij provisioning;
- bestaande tenants kunnen gecontroleerd en met tenantlokale back-up worden aangevuld;
- tenant-eigen waarden blijven behouden, terwijl nieuw vereiste templatevelden neutrale defaults krijgen;
- RC045-afbeeldingen, favicons, kaartlocatie en analytics worden niet aan een externe tenant doorgegeven.

## Bestaande tenant controleren en migreren

```bash
php bin/migrate-tenant-public-template.php \
  --config=/srv/verenigingen/test/config.php \
  --check

php bin/migrate-tenant-public-template.php \
  --config=/srv/verenigingen/test/config.php \
  --apply
```

Veilige tenant-eigen waarden blijven bij de standaardmigratie behouden. Ontbrekende velden worden aangevuld. `--force` is alleen bedoeld voor een bewust gekozen volledige reset naar neutrale startinhoud.

## Acceptatie

`tests/phase54-tenant-public-template.php` bewijst dat:

1. provisioning neutrale startdata schrijft;
2. een externe tenant dezelfde CSS en JavaScript als RC045 gebruikt;
3. menu-items en homepageonderdelen exact dezelfde volgorde behouden;
4. RC045-tekst en -afbeeldingen niet in de tenant-HTML voorkomen;
5. veilige tenantcontent wordt gebruikt en bij migratie behouden;
6. legacy tenantdata fail-closed wordt geweigerd;
7. de bestaande tablet- en mobiele breakpoints brede grids onder elkaar zetten.
