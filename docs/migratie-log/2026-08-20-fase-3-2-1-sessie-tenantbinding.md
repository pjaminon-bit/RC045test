# Fase 3.2.1 — sessies tenantgebonden

Datum: 20-08-2026

## Doel
Een beheersessie van tenant A mag nooit als geldige sessie van tenant B worden gebruikt, ook niet wanneer meerdere tenants dezelfde applicatiecode en dezelfde PHP-runtime delen.

## Wijzigingen
- Nieuwe helper `app/auth-session-tenant.php` bindt iedere beheersessie aan `tenant_key`.
- Externe tenants krijgen via `app/auth-storage.php` een eigen PHP `session.save_path` onder `<private_root>/sessions`.
- Externe tenants krijgen een tenant-specifieke sessiecookie-naam, afgeleid van tenant-private context.
- Kan PHP de tenant-eigen session save-path niet activeren, dan faalt de externe tenant gesloten.
- Bij een payload-mismatch wordt de vreemde sessie met `session_abort()` losgelaten; het oorspronkelijke sessiebestand wordt niet vernietigd of overschreven.
- De vervangsessie bevat alleen de actieve `tenant_key` en een nieuw CSRF-token.
- Een reeds geauthenticeerde externe sessie zonder `tenant_key` wordt geweigerd.
- Een anonieme externe sessie zonder `tenant_key` wordt veilig aan de huidige tenant gebonden.
- Bestaande standalone RC045-sessies zonder `tenant_key` blijven compatibel en worden in-place aan `rc045` gebonden.
- De provisioner maakt voortaan `<private_root>/sessions` met server-only directoryrechten aan.

## Security-keuze
Alleen een andere cookienaam is onvoldoende: bij PHP's file-session handler worden sessies serverside op session-id opgeslagen. Daarom is ook de serverside session directory per tenant gescheiden. De `tenant_key` in de payload is een tweede verdedigingslaag.

Bij een mismatch wordt bewust `session_abort()` gebruikt en niet `session_destroy()`. Zo kan een request aan tenant B nooit het oorspronkelijke sessiebestand van tenant A verwijderen wanneer hetzelfde session-id wordt aangeboden.

## Tests
`tests/phase321-session-tenant-binding.php` controleert met echte PHP-sessies onder andere:
- hergebruik van hetzelfde session-id bij een andere tenant;
- rotatie naar een schoon session-id;
- geen overname van gebruiker/master/sessieversie;
- oorspronkelijke sessie van tenant A blijft fysiek ongewijzigd;
- externe geauthenticeerde ongebonden sessies worden geweigerd;
- anonieme sessies kunnen veilig worden gebonden;
- bestaande standalone RC045-login blijft werken;
- beschadigde tenantbinding faalt gesloten.

De test draait als vaste CI-gate vóór merge/deploy.
