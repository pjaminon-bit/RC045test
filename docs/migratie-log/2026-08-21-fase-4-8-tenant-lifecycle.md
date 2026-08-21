# Fase 4.8 — tenant lifecycle

Datum: 21-08-2026

## Doel

Een reeds geprovisioneerde productie-tenant gecontroleerd kunnen adopteren, uitschakelen, opnieuw activeren, exporteren en uiteindelijk verwijderen zonder de isolatiecontracten uit fase 4.1–4.7 te omzeilen.

## Implementatie

- nieuw secretvrij `lifecycle-plan.json`, transitief gebonden aan het gevalideerde fase-4.6 monitoringplan en daarmee aan runtime/TLS/database;
- root-owned lifecycle-state en tenantgebonden audit buiten de documentroot;
- expliciete `--adopt-active` voor bestaande VPS-tenants; er wordt nooit stil aangenomen dat een tenant actief is;
- `--suspend` blokkeert de tenant op Apache-, PostgreSQL- en PHP-FPM-niveau en stopt de monitoringtimer;
- `--activate` herstelt uitsluitend exacte eerder gevalideerde artifacts, test databasebinding en health vóór de tenant weer als actief wordt gemarkeerd;
- `--recover` brengt een onderbroken activate/suspend fail-closed terug naar `suspended`;
- volledige root-only export van PostgreSQL plus tenantfilesystem, met manifest en SHA-256 checksums;
- verwijderen is tweestaps: eerst `pending_delete`, daarna minimaal 24 uur wachttijd en een tweede expliciete purgebevestiging;
- definitieve purge ruimt tenantgebonden Apache/FPM/PostgreSQL/HBA/Certbot/systemd/Linux-resources op, maar laat DNS-providerrecords bewust ongemoeid;
- exportpakket en tombstone blijven buiten tenantroot behouden;
- root-owned plansnapshot maakt gecontroleerd herstel mogelijk bij een crash tijdens de purge;
- gewone verenigingsbeheerders krijgen geen directe lifecycle/rootbevoegdheid; een latere platform-/superbeheer-GUI moet als aparte control-plane boven deze operatorlaag worden gebouwd.

## Veiligheidsgrenzen

- geen secrets in Git, lifecycleplan of CLI-argumenten;
- geen shell-evaluatie of `rm -rf` voor destructieve acties;
- tenantboom moet symlink-vrij zijn vóór export of dataverwijdering;
- database- en role-markers worden vóór destructieve PostgreSQL-acties opnieuw gecontroleerd;
- een geverifieerde export is verplicht vóór delete/purge;
- DNS-providerintegratie valt buiten de generieke lifecycle-engine.

## Productiestatus

De code en CI kunnen in RC045test worden afgerond voordat deze handelingen op een echte VPS worden uitgevoerd. De eerste daadwerkelijke lifecycle-adoptie volgt pas nadat de 4.1–4.7 infrastructuur op de productie-VPS is aangelegd en de tenant volledig gezond is.
