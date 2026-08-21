# Migratielog — fase 5.2 first-VPS productiebootstrap

Datum: 21-08-2026

## Doel

De reeds geteste productiecontracten uit fase 3.2 t/m 5.1 samenbrengen tot één fail-closed eerste installatie van een schone productie-VPS, zonder de onderliggende contracten te dupliceren of providercredentials/secrets in Git op te nemen.

## Besluiten

- De eerste gedeelde applicatie wordt altijd via fase 4.7 als immutable release geplaatst voordat `current` door andere plannen wordt gebruikt.
- De canonieke tenantbasis voor de productiebootstrap is `/srv/verenigingen`.
- Platformbeheer gebruikt een eigen fase-5.2 DNS/ACME-bootstrap; tenant fase-4.3/4.4 plannen worden niet kunstmatig voor de platformhost hergebruikt.
- Platformbeheer mag nooit de default TLS-identiteit worden. De neutrale fase-4.4 HTTP/HTTPS catch-alls en het reject-certificaat worden daarom vóór het eerste beheer-certificaat geïnstalleerd.
- De definitieve 5.1 HTTP-vhost is ACME-aware gemaakt: alleen HTTP-01 is publiek, alle overige paden krijgen een vaste canonieke 308 naar HTTPS.
- De globale Certbot deploy-hook voert een Apache configtest uit vóór reload en wordt al tijdens de platformbootstrap geïnstalleerd.
- Een eerste Certbot-account mag alleen op de schone VPS worden geregistreerd als er nog geen account bestaat. Er wordt geen account-e-mail of andere contactdata in plan/Git opgeslagen.
- DNS-providerwrites blijven buiten de generieke automation. De bootstrap bewijst alleen de live DNS-uitkomst.
- De bootstrap installeert geen packages. Ontbrekende systeemdependencies blokkeren vooraf.
- Operator- en tenantbeheerwachtwoord worden uitsluitend via STDIN aan de bestaande bootstraptools doorgegeven.
- Iedere duurzame stap schrijft een root-owned checkpoint dat aan de SHA-256 van exact het 5.2-plan is gebonden.
- Hervatten gebeurt uitsluitend op hetzelfde plan. Een half afgemaakte installatie wordt niet door een nieuw/gewijzigd plan overgenomen.
- PostgreSQL wordt geprovisioneerd terwijl de tenant-PHP-FPM runtime nog niet actief is; FPM wordt pas daarna getest en herladen.
- Lifecycle-adoptie vindt pas plaats nadat tenant TLS, monitoring en health aantoonbaar actief zijn.

## Nieuwe bestanden

- `app/deployment/first-vps-bootstrap-contract.php`
- `bin/prepare-first-vps-bootstrap.php`
- `bin/apply-first-vps-bootstrap.php`
- `tests/phase52-first-vps-bootstrap.php`
- `docs/VPS-FIRST-BOOTSTRAP.md`
- dit migratielog

## Aangepaste integratie

- fase-5.1 control-plane HTTP-routing ondersteunt permanente Certbot webroot-renewal;
- CI draait een aparte fase-5.2 acceptatietest en houdt de nieuwe servertools/documentatie uit het DEV HTTP-oppervlak;
- centrale roadmap en zichtbare platform-changelog worden bijgewerkt.

## Productiestatus

Deze fase bouwt en test de bootstrapautomation. Er worden vanuit GitHub/DEV geen productie-VPS-mutaties uitgevoerd. De echte VPS-validatie volgt pas wanneer een productie-VPS en de operator-side DNS-records beschikbaar zijn.
