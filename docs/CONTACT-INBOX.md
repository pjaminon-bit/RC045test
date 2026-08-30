# Contactinbox

## Doel

Het publieke contactformulier stuurt persoonsgegevens niet naar een externe formulierprovider. De browser post same-origin naar `contact-ontvangst.php`; het bericht wordt opgeslagen in de private verenigingsopslag.

Voor een externe tenant gebruikt de inbox dezelfde tenantgebonden private store als de overige domeinen: PostgreSQL wanneer `private_driver=pdo` actief is, anders de private JSON-root. De standalone compatibiliteit gebruikt `contactberichten-data.php`, dat zowel via `.htaccess` wordt geblokkeerd als door een `<?php exit; ?>`-voorloop is beschermd en door `.gitignore` nooit in Git hoort te komen.

## Opgeslagen gegevens

Een contactbericht bevat alleen wat nodig is om de vraag af te handelen: naam, e-mailadres, telefoonnummer, onderwerp, berichttekst, status en technische tijdstempels. Het IP-adres wordt niet in de inbox bewaard. Voor abusepreventie wordt uitsluitend een SHA-256-sleutel in de bestaande server-only rate-limitopslag gebruikt.

Het formulier gebruikt daarnaast een honeypot. Per afzender/IP-namespace zijn maximaal tien geaccepteerde pogingen per uur toegestaan. Een identiek bericht van dezelfde contactpersoon binnen tien minuten wordt idempotent als reeds ontvangen behandeld.

## Autorisatie

Contactberichten zijn bewust gescheiden van gewone websitecontent. Alleen accounts met de gevoelige capability `contact.messages.manage` mogen `Beheer > Contactberichten` openen. De beheeracties zijn met de bestaande CSRF-bescherming afgeschermd en worden in het operationele logboek vastgelegd.

Beheerders kunnen een nieuw bericht als afgehandeld markeren, een afgehandeld bericht heropenen en uitsluitend een afgehandeld bericht handmatig verwijderen. Interne notities worden niet aan de publieke afzender teruggestuurd.

## Bewaartermijn

`privacy.contactberichten_bewaardagen` bepaalt de maximale bewaartermijn en staat standaard op 180 dagen. De waarde wordt begrensd op 30 tot en met 730 dagen.

Open berichten rekenen vanaf `aangemaakt`; afgehandelde berichten rekenen vanaf `afgehandeld_op`. Daardoor kan een vergeten open bericht niet onbeperkt persoonsgegevens blijven bewaren. Het openen van de beheerinbox materialiseert de retentie; ook bij nieuwe publieke berichten wordt eerst verlopen data uit het document gefilterd.

## Back-up en tenantisolatie

De collectie `contactberichten` is opgenomen in de tenant-backupregistry. Daardoor volgt restore dezelfde logische private-storegrens als leden, aanmeldingen en andere tenantdata. Een externe tenant kan niet terugvallen op de standalone projectrootdata.

## Browserbeleid

De centrale CSP staat formulieracties alleen naar `'self'` toe. De gerichte outputguard `app/core/contact-inbox-runtime.php` forceert het formulier met `id="contact-form"` naar `contact-ontvangst.php`, verwijdert een historische tenant-disable en blokkeert fail-closed wanneer na transformatie toch een externe action overblijft. Andere formulieren worden niet aangepast.
