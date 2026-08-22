# Security supply-chain contract

Dit document borgt de afhankelijkheden en externe code die vóór fase 5.3 op een VPS mogen worden gebruikt.

## Uitgangspunten

- GitHub Actions worden op een volledige commit-SHA gepind; een losse tag zoals `@v4` is niet voldoende.
- De volledige Git-historie wordt bij iedere pull request en push naar `main` door Gitleaks gescand.
- Node-afhankelijkheden staan in `package-lock.json` en worden uitsluitend met `npm ci --ignore-scripts` geïnstalleerd.
- `npm audit --audit-level=high` blokkeert high/critical meldingen voor de Node-tooling.
- Productiecode mag geen package manager of netwerkdownload nodig hebben om te starten.
- Vendored browserbibliotheken worden als expliciete artefacten behandeld: wijziging van hun repository-blob-SHA is een supply-chain wijziging en vereist review.
- Uitvoerbare browser-JavaScript van externe origins wordt niet vertrouwd. De CSP staat `script-src` alleen voor de eigen origin toe.

## Vastgelegde browserdependencies

### PhotoSwipe

Repositorypad:

- `vendor/photoswipe/photoswipe-lightbox.esm.min.js`
- `vendor/photoswipe/photoswipe.esm.min.js`
- `vendor/photoswipe/photoswipe.css`
- `vendor/photoswipe/LICENSE`

De vendored JavaScript-header identificeert de library als **PhotoSwipe 5.4.4**. De in deze release beoordeelde Git-blob-SHA voor `photoswipe-lightbox.esm.min.js` is:

`cac7e4e0f8b8bed99b14273c544652f5c208808e`

Bij een wijziging moet minimaal worden gecontroleerd:

1. upstream project en licentie;
2. releaseversie en changelog;
3. actuele security advisories/CVE's;
4. verwachte bestandshash/blob-SHA;
5. browseracceptatie na de update.

### heic2any

Repositorypad:

- `vendor/heic2any/heic2any.min.js`

De in deze release beoordeelde Git-blob-SHA is:

`0fa6cf873a2fc161669353cc1e774266906234bc`

De minified bundle bevat in de repository geen betrouwbaar uitleesbare versiebanner. Daarom wordt hier bewust geen versie gegokt: de blob-SHA is de huidige provenance-identiteit. Een toekomstige update moet eerst de exacte upstream release vastleggen en daarna de blob-SHA in dit document vervangen.

## Testtooling

De browseracceptatie gebruikt:

- `@playwright/test` **1.62.1**;
- de bijbehorende `playwright` en `playwright-core` versies uit `package-lock.json`;
- integriteitshashes uit dezelfde lockfile.

Geen workflow mag `npm install @playwright/test@...` gebruiken als vervanging voor de lockfile.

## GitHub Actions trust

De securityscan gebruikt Gitleaks Action v3 op commit:

`e0c47f4f8be36e29cdc102c57e68cb5cbf0e8d1e`

De bestaande acties voor checkout/upload/deploy zijn eveneens commit-pinned. Een upgrade van een action is een codewijziging en moet via pull request plaatsvinden.

## SSH/SFTP host trust

Authenticated DEV-acceptatie mag geen `ssh-keyscan` gebruiken om een host direct vóór de verbinding als vertrouwd te markeren. De workflow verwacht `FTP_SSH_KNOWN_HOSTS`, gevuld met een **onafhankelijk geverifieerde** OpenSSH known-hosts regel voor de ingestelde `FTP_SERVER`.

Controleer de serverfingerprint buiten dezelfde SSH-verbinding om, bijvoorbeeld via het hostingpaneel/providerdocumentatie of een reeds vertrouwde beheerverbinding. Pas daarna mag de regel als repository secret worden opgeslagen.

## Periodieke controle

Voor iedere VPS-release geldt minimaal:

1. alle source-regressies groen;
2. Gitleaks volledige historie groen;
3. `npm audit --audit-level=high` groen;
4. geen ongecontroleerde wijziging van vendored browserblobs;
5. geen nieuwe externe `script-src` origin in CSP;
6. alle GitHub Actions commit-pinned;
7. live security- en browseracceptatie groen na deploy.

Een gevonden secret wordt niet alleen uit de actuele branch verwijderd: de credential wordt direct ingetrokken/geroteerd. Daarna wordt beoordeeld of history-rewrite nodig is.
