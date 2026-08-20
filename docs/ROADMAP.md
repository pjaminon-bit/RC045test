# Roadmap verenigingsplatform

Status per **20-08-2026**.

Dit document is vanaf fase 4 de centrale nummering voor de resterende platform- en VPS-stappen. Historische migratiedocumenten blijven hun toenmalige stand beschrijven.

## Afgerond

- **Fase 1 — template-ready:** afgerond.
- **Fase 2 — modulair beheer:** afgerond.
- **Fase 3.1 — tenant boundary:** afgerond.
- **Fase 3.2 — tenant provisioner:** afgerond.
- **Fase 3.2.1 — security hardening:** afgerond.
- **Fase 3.3 — tweede tenantbewijs:** afgerond.
- **Fase 3.4 — veilige eerste beheerder:** afgerond.
- **Fase 3.5 — VPS deploymentcontract:** afgerond.
- **Fase 3.5.1 — security heraudit fixes:** afgerond.

## Fase 4 — VPS & productie-infrastructuur

### 4.1 — VPS runtime & Linux-isolatie

**Status: code en CI gereed; root-toepassing volgt op de echte VPS.**

Omvat:

- deterministische system user + unieke primary group per tenant;
- geen login/home/supplementary groups;
- per-tenant PHP-FPM pool en Unix socket;
- tenant-private PHP sessions en upload tmp;
- ownership- en modecontract voor tenantmetadata en private opslag;
- shared release blijft centraal beheerd en nooit tenant-writable;
- root-vrije `--check` en aparte Linux-root `--apply`;
- geen automatische PHP-FPM reload voordat de complete serverconfig is getest.

Zie `docs/VPS-RUNTIME-ISOLATION.md`.

### 4.2 — Webserver & vhosts

**Status: gepland.**

Omvat:

- Apache/Nginx tenant-vhosts;
- literal canonical HTTP→HTTPS redirect;
- default/catch-all voor onbekende hosts;
- iedere host uitsluitend naar zijn eigen PHP-FPM socket;
- documentroot uitsluitend de gedeelde release;
- server-side denyregels voor private/tooling/VCS-paden;
- configtest vóór reload.

### 4.3 — DNS

**Status: gepland.**

Omvat:

- tenantdomeinen naar de VPS laten wijzen;
- gewenste A/AAAA/CNAME-strategie vastleggen;
- DNS-readiness en propagation-controle vóór TLS/livegang.

### 4.4 — TLS/HTTPS

**Status: gepland.**

Omvat:

- certificaatuitgifte;
- veilige renewal;
- HTTPS/canonical-host enforcement;
- TLS default/catch-all gedrag voor onbekende SNI/hosts.

### 4.5 — Database provisioning

**Status: gepland.**

Omvat:

- PDO-database/databaseuser per gekozen isolatiemodel;
- server-only databasecredentials;
- schema/migraties;
- geen secrets in Git, deployment.json of runtimebundles;
- tenantbinding en fail-closed connectivity checks.

### 4.6 — Monitoring & logging

**Status: gepland.**

Omvat:

- healthchecks;
- uptime/error monitoring;
- PHP-FPM/webserver/app logging;
- tenantidentiteit in operationele logs zonder secrets/persoonsdata te lekken;
- alerts voor relevante storingen.

### 4.7 — Release & rollback automation

**Status: gepland.**

Omvat:

- immutable `releases/<commit>`;
- atomische `current`-wissel;
- preflight voor tenants;
- config/runtime/vhost checks;
- smoke tests na release;
- snelle rollback naar vorige gevalideerde release.

### 4.8 — Tenant lifecycle

**Status: gepland.**

Omvat:

- tenant activeren;
- gecontroleerd uitschakelen;
- exporteren;
- verwijderen met expliciete veiligheidsstappen;
- lifecycle-acties tenantgebonden auditen.

## Volgorde

De standaardvolgorde is:

**4.1 → 4.2 → 4.3 → 4.4 → 4.5 → 4.6 → 4.7 → 4.8**

Een fase kan code/automation al gereed hebben voordat de daadwerkelijke productiehandeling op de VPS kan worden uitgevoerd. Dat verschil wordt per fase expliciet als status vermeld; “code gereed” wordt nooit gelijkgesteld aan “op productie toegepast”.
