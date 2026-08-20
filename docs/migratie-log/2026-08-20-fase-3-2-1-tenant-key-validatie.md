# Fase 3.2.1 — optie 6: tenant-key validatie

Datum: 2026-08-20

## Doel

Voorkomen dat verschillende of ongeldige invoeren door stille normalisatie uiteindelijk dezelfde technische tenantidentiteit krijgen.

## Wijzigingen

- `bin/provision-tenant.php` gebruikt voor `--key` geen stille normalisatie meer.
- Tenant-key moet exact voldoen aan het provisioningcontract:
  - 3–63 ASCII-tekens;
  - alleen lowercase `a-z`, `0-9` en `-`;
  - geen koppelteken aan begin/einde;
  - geen `--`;
  - `default` is gereserveerd.
- Voor-/achterliggende whitespace, hoofdletters, underscores, spaties, Unicode en overige speciale tekens worden fail-closed geweigerd.
- Defense-in-depth controleert dat de geaccepteerde key door `tenantRuntimeVeiligeSleutel()` exact onveranderd zou blijven.
- Validatie gebeurt vóór tenantpad/config/manifest wordt opgebouwd.
- `--help` en `docs/PROVISIONING.md` beschrijven het vaste keycontract.

## Regressietests

`tests/phase32-provisioner.php` is uitgebreid met:

- afwijzing van hoofdletters, spaties, underscore en speciaal teken;
- afwijzing van leading/trailing/dubbele koppeltekens;
- afwijzing van voor-/achterliggende whitespace;
- grenswaarden voor te korte en te lange keys;
- afwijzing van `default`;
- afwijzing van niet-ASCII invoer;
- bewijs dat geweigerde keys geen tenantmappen maken;
- acceptatie van 3 en 63 tekens;
- acceptatie van een canonieke key met koppeltekens.

De bestaande root-securitytests gebruikten op twee plekken `--key=x`. Die zijn aangepast naar geldige keys, zodat ze nog steeds daadwerkelijk de root/padgrens testen en niet voortijdig groen worden door de nieuwe keyvalidatie.

## Scope

De bestaande runtime-helper `tenantRuntimeVeiligeSleutel()` blijft voor legacy/runtimecompatibiliteit bestaan. Deze fase scherpt bewust alleen de provisioninggrens voor **nieuwe** tenants aan.
