# Fase 5.4 — publieke tenanttemplate en responsive acceptatie

## Aanleiding

De VPS-acceptatie bewees de release-, rollback- en tenantisolatie, maar de publieke testtenant renderde nog de historische RC045-homepage. De tenanttitel kwam uit de externe configuratie, terwijl vaste HTML, JavaScript-fallbacks en gedeelde afbeeldingen nog RC045-inhoud toonden.

## Oplossing

- externe tenants gebruiken een afzonderlijke server-side homepage;
- de standalone RC045-installatie behoudt de bestaande RC045-homepage;
- naam, slogan, thema, logo en taal komen uit de actieve tenantconfiguratie;
- homepage- en contactdata worden alleen gebruikt wanneer ze geen historische RC045-fingerprints bevatten;
- onveilige legacydata valt terug op neutrale tekst en wordt gelogd zonder inhoud te loggen;
- nieuwe tenants krijgen neutrale `homepage.json` en `contact.json` bij provisioning;
- bestaande tenants kunnen gecontroleerd en met tenantlokale back-up worden gemigreerd;
- de publieke contentflow is één kolom; onder 760 px worden ook navigatie, hero en acties volledig gestapeld;
- de template gebruikt geen `100vw` en begrenst alle containers en gridkinderen tegen horizontale overflow.

## Bestaande tenant controleren en migreren

```bash
php bin/migrate-tenant-public-template.php \
  --config=/srv/verenigingen/test/config.php \
  --check

php bin/migrate-tenant-public-template.php \
  --config=/srv/verenigingen/test/config.php \
  --apply
```

Veilige tenant-eigen datasets blijven bij de standaardmigratie behouden. `--force` is alleen bedoeld voor een bewust gekozen volledige reset naar neutrale startinhoud.

## Acceptatie

`tests/phase54-tenant-public-template.php` bewijst dat:

1. provisioning neutrale startdata schrijft;
2. een externe tenant server-side met de eigen naam rendert;
3. RC045-tekst en -afbeeldingen niet in de tenant-HTML voorkomen;
4. veilige tenantcontent wordt gebruikt;
5. legacy tenantdata fail-closed wordt geweigerd;
6. de migratie legacy datasets vervangt;
7. de responsive CSS éénkoloms content en overflowbegrenzing afdwingt.
