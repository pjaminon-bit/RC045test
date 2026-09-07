# Tenant-specifieke server-side notificaties

Fase 6A gebruikt een lokale transactionele outbox en een tenantgebonden SMTP-relay. Contactberichten en lidmaatschapsaanmeldingen blijven altijd in de private tenantstore; SMTP is uitsluitend een operationeel side-effect nadat de lokale write is gecommit.

## Contract

- Browserformulieren posten alleen same-origin.
- De lokale contact-/aanmeldingeninbox is de bron van waarheid.
- Een providercall vindt nooit plaats in het publieke formulierrequest.
- De outbox bewaart geen naam, e-mailadres, telefoonnummer, geboortedatum of formulierinhoud. Alleen eventtype, tenantgebonden hash, delivery-state en timestamps worden opgeslagen.
- De e-mail bevat alleen dat er een nieuw item klaarstaat plus een link naar de geauthenticeerde beheerinbox.
- SMTP draait onder de tenant-FPM-user via `ext-curl`; er is geen `exec`, sendmail, rootadapter of aparte privileged scheduler.
- TLS is verplicht. Ondersteund: `starttls` en `smtps`; certificaat- en hostverificatie kunnen niet worden uitgezet.

## Waarom SMTP als eerste adapter

Het platform beheert geen eigen MTA. Dat zou additionele hosthardening, queuebeheer, reputatie, SPF/DKIM/DMARC en deliverabilitybeheer vereisen. Het SMTP-contract werkt zowel met een eigen bestaande relay als met een transactionele mailprovider die SMTP aanbiedt, zonder codefork of leveranciersspecifieke applicatiecode.

Een leverancierspecifieke HTTP-adapter kan later naast SMTP worden toegevoegd wanneer daar een concrete operationele reden voor bestaat.

## Server-only config

Notificatieconfig hoort in het externe tenant `config.php` buiten code/documentroot. Gebruik voor een provisioned VPS-tenant:

```bash
php bin/configure-tenant-notifications.php \
  --config=/srv/verenigingen/vereniging/config.php \
  --from=notificaties@vereniging.example \
  --contact-to=bestuur@vereniging.example \
  --membership-to=ledenadministratie@vereniging.example \
  --smtp-host=smtp.provider.example \
  --smtp-port=587 \
  --security=starttls \
  --required
```

De configureer-CLI schrijft alleen niet-geheime transportmetadata en maakt `private/secrets` gereed. Hij neemt geen password/token als argument aan en maakt het credentialsbestand niet aan.

`--required` betekent dat ontbrekende/ongeldige notificatieconfig de pilot-readiness/health fail-closed maakt. Zonder `--required` blijft een enabled transport eveneens health-gecontroleerd; de vlag legt vooral expliciet vast dat notificatie onderdeel is van het pilotcontract.

## Credentials

Het door de config aangewezen bestand moet onder exact dezelfde `private_root` staan, bijvoorbeeld:

`/srv/verenigingen/vereniging/private/secrets/notifications-smtp.json`

Formaat:

```json
{
  "username": "SMTP_USERNAME",
  "password": "SMTP_PASSWORD"
}
```

Vervang bovenstaande placeholders uitsluitend op de server. Commit nooit een echt credentialsbestand. Het bestand moet een regulier bestand zijn, geen symlink, binnen de tenantprivate grens en mode `0640` of strenger hebben. De applicatie logt de inhoud nooit.

## Readiness en gecontroleerde test

Met de tenantruntime-omgeving actief:

```bash
php bin/check-tenant-notifications.php
```

De uitvoer bevat alleen tenant-key, enabled/required, configstatus en PII-vrije backlogcijfers. Exitcode `0` betekent dat de lokale configuratie/readiness geldig is.

Een expliciete providerprobe kan daarna met:

```bash
php bin/check-tenant-notifications.php --send-test
```

De testmail bevat geen formulier- of ledengegevens. Alleen de genormaliseerde deliverycode wordt getoond; ruwe providerresponses en credentials worden niet weergegeven.

## Delivery en retry

De bestaande root-owned monitoringtimer raakt `healthz.php` iedere minuut via HTTPS. Die request draait onder de tenant-FPM-user. De healthhook claimt maximaal één due outbox-item, laat het globale tenantdataslot los, voert daarna SMTP uit en schrijft vervolgens de delivery-state terug.

Backoff na mislukte afleveringen:

1. 60 seconden;
2. 5 minuten;
3. 15 minuten;
4. daarna 1 uur per poging.

Een tijdelijke providerfailure wordt als PII-vrije warning in `monitoring/operations.jsonl` vastgelegd. De primaire contact-/aanmeldingsdata blijft onaangetast. Wanneer de oudste pending notification ouder wordt dan `stale_after_seconds` (standaard 900), wordt tenant-health `503` zodat de bestaande monitoring het probleem kan escaleren.

Alleen succesvolle provideracceptatie markeert een outbox-item `delivered`. Afgeleverde outboxmetadata wordt na zeven dagen opgeruimd.

## Pilot-go-live

Voor een eerste externe pilot:

1. configureer de tenant met `bin/configure-tenant-notifications.php --required`;
2. plaats het SMTP-credentialsbestand buiten code/documentroot onder `private_root`;
3. voer `bin/check-tenant-notifications.php` uit;
4. voer één gecontroleerde `--send-test` uit;
5. controleer dat `healthz.php` gezond blijft;
6. verstuur een testcontactbericht en testaanmelding en controleer lokale inbox, PII-arme mail en lege/gezonde backlog;
7. verwijder of roteer tijdelijke providercredentials wanneer die uitsluitend voor een proef zijn aangemaakt.

Evenementdeelname, acceptatie/afwijzing richting de aanvrager, wachtwoordmails, attachments en mass mailing vallen buiten deze eerste pilotstap.
