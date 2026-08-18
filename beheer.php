<?php
// ============================================================
// RC045 beheerpagina
// Inhoudelijke onderdelen, elk met een eigen formulier en eigen JSON-bestand
// in data/, die door de website worden uitgelezen:
//   - Afwijkende openingstijden -> data/actueel.json
//   - Agenda (kaarten)     -> data/agenda.json
//   - Veelgestelde vragen -> data/faq.json
//   - Sponsors (logo's)   -> data/sponsors.json, bestanden in images/sponsors/
//   - Fotoboek (albums)   -> data/fotoboek.json, bestanden in images/fotoboek/<slug>/
//
// Inloggen zelf staat niet meer in dit bestand maar in auth.php: sessie,
// CSRF, gebruikers, logboek, lockout en het rechtenmodel. Zie de toelichting
// bovenin dat bestand, ook voor welke server-only bestanden erbij horen en
// waarom die in .htaccess afgeschermd moeten zijn.
// ============================================================

// Inloggen, sessie, CSRF, het logboek, de lockout en het rechtenmodel staan
// in auth.php, zodat een tweede afgeschermde pagina (leden.php) precies
// hetzelfde gebruikt in plaats van een eigen inlogsysteem ernaast. Dit moet
// vóór elke uitvoer gebeuren: auth.php stuurt headers en start de sessie.
// Levert onder meer $configOk, $ingelogd, $huidigeGebruiker, $isMaster,
// $csrfToken, $inlogFout, $melding/$meldingType, $usersBestand, $logBestand
// en de back-upinstellingen die schrijfJson() hieronder gebruikt.
require_once __DIR__ . '/auth.php';

$dataMap      = __DIR__ . '/data';

// Ledenadministratie. De opslag en de hulpfuncties staan in een apart
// bestand, omdat aanmelden-ontvangst.php ze ook nodig heeft. Zie de
// toelichting bovenin dat bestand voor waar het ledenbestand staat en
// waarom het niet in data/ hoort. leden-opslag.php wordt al door auth.php
// ingesloten (de rechten hangen aan de bestuursfunctie), maar staat hier
// bewust ook: deze pagina gebruikt het rechtstreeks.
require_once __DIR__ . '/leden-opslag.php';
require_once __DIR__ . '/vergaderingen-opslag.php';
require_once __DIR__ . '/taken-opslag.php';
require_once __DIR__ . '/operationele-taken-opslag.php';
require_once __DIR__ . '/evenementen-opslag.php';
require_once __DIR__ . '/paneel-hulp.php';

// De instellingen voor de lockout bij mislukte inlogpogingen en voor de
// automatische back-up van databestanden ($dataBackupMap en de twee
// bewaargrenzen, gebruikt door schrijfJson() en maakDataBackup()) staan in
// auth.php, omdat de inlogafhandeling ze daar zelf nodig heeft.

$actueelBestand = $dataMap . '/actueel.json';
$agendaBestand  = $dataMap . '/agenda.json';
$faqBestand     = $dataMap . '/faq.json';
$sponsorBestand = $dataMap . '/sponsors.json';
$sponsorMap     = __DIR__ . '/images/sponsors';
$fotoboekBestand = $dataMap . '/fotoboek.json';
$fotoboekMap     = __DIR__ . '/images/fotoboek';
$logoPad         = __DIR__ . '/rc045-logo.png';
$contactBestand  = $dataMap . '/contact.json';
$mediaBestand    = $dataMap . '/media.json';
$nieuwsBestand    = $dataMap . '/nieuws.json';
$rekentabelBestand = $dataMap . '/rekentabel.json';
$homepageBestand   = $dataMap . '/homepage.json';
$ontstaanBestand   = $dataMap . '/ontstaan.json';
$baanreglementBestand = $dataMap . '/baanreglement.json';
$bedanktBestand    = $dataMap . '/bedankt.json';
$aanmeldenBestand  = $dataMap . '/aanmelden.json';
$mediaTekstBestand = $dataMap . '/media-pagina.json';
$fotoboekTekstBestand = $dataMap . '/fotoboek-pagina.json';

// Changelog. Twee bronnen die in het tabblad samengevoegd worden:
// - changelog-historie.php: de vaste regels die bij de code horen, staan in
//   de repo en komen met elke deploy mee. Niet te bewerken in beheer.
// - data/changelog.json: de regels die het bestuur zelf toevoegt.
$changelogBestand = $dataMap . '/changelog.json';
$changelogVastPad = __DIR__ . '/changelog-historie.php';

// Alle bestanden die automatisch back-upt worden (zie maakDataBackup()),
// gebruikt door het tabblad "Back-ups" en de backup_herstellen-actie
// verderop. "schrijffunctie" bepaalt via welke functie een herstelde back-up
// weer wordt weggeschreven: gebruikers.json gaat via schrijfGebruikers, de
// rest via het generieke schrijfJson.
$dataBackupBestanden = [
  'homepage'   => ['label' => 'Homepage teksten', 'pad' => $homepageBestand, 'schrijffunctie' => 'schrijfJson'],
  'ontstaan'   => ['label' => 'Ontstaan (geschiedenis)', 'pad' => $ontstaanBestand, 'schrijffunctie' => 'schrijfJson'],
  'baanreglement' => ['label' => 'Baanreglement', 'pad' => $baanreglementBestand, 'schrijffunctie' => 'schrijfJson'],
  'bedankt'    => ['label' => 'Bedankt-pagina (betaalgegevens)', 'pad' => $bedanktBestand, 'schrijffunctie' => 'schrijfJson'],
  'aanmelden'  => ['label' => 'Aanmelden (pagina)', 'pad' => $aanmeldenBestand, 'schrijffunctie' => 'schrijfJson'],
  'media_pagina' => ['label' => 'Media (paginatekst)', 'pad' => $mediaTekstBestand, 'schrijffunctie' => 'schrijfJson'],
  'fotoboek_pagina' => ['label' => 'Fotoboek (paginatekst)', 'pad' => $fotoboekTekstBestand, 'schrijffunctie' => 'schrijfJson'],
  'mededeling' => ['label' => 'Openingstijden', 'pad' => $actueelBestand, 'schrijffunctie' => 'schrijfJson'],
  'agenda'     => ['label' => 'Agenda', 'pad' => $agendaBestand, 'schrijffunctie' => 'schrijfJson'],
  'faq'        => ['label' => 'Vragen (FAQ)', 'pad' => $faqBestand, 'schrijffunctie' => 'schrijfJson'],
  'sponsors'   => ['label' => 'Sponsors', 'pad' => $sponsorBestand, 'schrijffunctie' => 'schrijfJson'],
  'contact'    => ['label' => 'Contact', 'pad' => $contactBestand, 'schrijffunctie' => 'schrijfJson'],
  'media'      => ['label' => 'Media', 'pad' => $mediaBestand, 'schrijffunctie' => 'schrijfJson'],
  'fotoboek'   => ['label' => 'Fotoboek', 'pad' => $fotoboekBestand, 'schrijffunctie' => 'schrijfJson'],
  'nieuws'     => ['label' => 'Nieuws', 'pad' => $nieuwsBestand, 'schrijffunctie' => 'schrijfJson'],
  'rekentabel' => ['label' => 'Rekentabel contributie', 'pad' => $rekentabelBestand, 'schrijffunctie' => 'schrijfJson'],
  'changelog'  => ['label' => 'Changelog (eigen regels)', 'pad' => $changelogBestand, 'schrijffunctie' => 'schrijfJson'],
  'gebruikers' => ['label' => 'Gebruikers', 'pad' => $usersBestand, 'schrijffunctie' => 'schrijfGebruikers'],
];

// Formaten voor het fotoboek: volledige (web) versie max 1600px breed,
// thumbnail voor de albumgrid max 400px breed. Alleen verkleinen, nooit
// vergroten. Watermerk wordt alleen op de volledige versie gezet.
$fotoboekMaxVolledig = 1600;
$fotoboekMaxThumb    = 400;

// Max bestandsgrootte per FOTO-upload (video heeft zijn eigen limiet, zie
// $fotoboekMaxVideoBytes hieronder). Moderne telefooncamera's halen soms
// 15-25 MB per foto, vandaar deze ruimere marge. Let op: upload_max_filesize
// in .user.ini moet minstens even hoog staan, anders knipt PHP de upload al
// eerder, stilletjes, af.
$fotoboekMaxFotoBytes = 25 * 1024 * 1024;

// Video's (mp4) worden zonder verkleinen/watermerk opgeslagen: GD kan geen
// video verwerken en er is geen ffmpeg op de gedeelde hosting om automatisch
// een thumbnail te trekken. De browser maakt daarom zelf een thumbnail (een
// frame uit de video via canvas) en stuurt die als los plaatje mee; die wordt
// hier net als een gewone foto verkleind naar een volledige en thumb-versie.
// Let op: dit limiet werkt alleen als upload_max_filesize/post_max_size in de
// PHP-instellingen van Strato dit ook toestaan; anders wordt de upload al
// eerder, stilletjes, door de server zelf afgekapt.
$fotoboekMaxVideoBytes = 80 * 1024 * 1024;

// Tijdelijk uit: terwijl we grote foto-uploads (98+ bestanden) stabiel
// krijgen, staat video-upload even helemaal uit (verbergen in het
// bestandskiezer-filter, weigeren op de server als iemand het toch stuurt).
// Op false zetten om video weer aan te zetten; de rest van de video-code
// (opslag, thumbnail, weergave) blijft intact staan.
$fotoboekVideoAan = false;

// Rekentabel contributie: bedragen komen uit data/rekentabel.json (te
// bewerken via het tabblad "Rekentabel"), zie $rekentabelStandaard en
// rekentabelProRata() verderop. $inschrijfkosten/$tabelJeugd/$tabelSenior
// worden pas na het inlezen van die data definitief gezet (zie "Huidige
// inhoud inlezen" verderop). $maandNamen/$huidigeMaand blijven wel hier,
// die zijn puur kalenderdata en niet afhankelijk van de ingevoerde bedragen.

$huidigeMaand = (int) date('n');

// Agenda tags: sleutel => label voor dit formulier. Dezelfde sleutel
// bepaalt op de website automatisch de kleur en de vertaling.
$agendaTags = ['leden' => 'Ledenevenement', 'opendag' => 'Open dag', 'wedstrijd' => 'Wedstrijd'];

// Standaardinhoud voor de agenda, alleen gebruikt zolang data/agenda.json
// nog niet bestaat. Dit zijn de vier evenementen die nu al op de site staan,
// inclusief de originele Engelse en Duitse vertaling, zodat het formulier
// meteen goed gevuld is en opslaan geen zichtbare wijziging geeft.
$agendaStandaard = [
  [
    'date' => '2026-07-19', 'tag' => 'leden', 'time' => '10:00 - 15:00',
    'title' => ['nl' => 'RC045 Clubkampioenschappen (voor leden)', 'en' => 'RC045 Club Championships (members only)', 'de' => 'RC045 Vereinsmeisterschaften (nur für Mitglieder)'],
    'desc'  => ['nl' => 'Een besloten evenement alleen voor leden, de baan is gesloten voor gasten.', 'en' => 'A closed event for members only, the track is closed to guests.', 'de' => 'Eine geschlossene Veranstaltung nur für Mitglieder, die Strecke ist für Gäste geschlossen.'],
  ],
  [
    'date' => '2026-08-23', 'tag' => 'leden', 'time' => '10:00 - 17:00',
    'title' => ['nl' => 'ZomerBBQ met F1 Zandvoort', 'en' => 'Summer BBQ with F1 Zandvoort', 'de' => 'Sommer-BBQ mit F1 Zandvoort'],
    'desc'  => ['nl' => 'Exclusief voor leden. Gezellige BBQ terwijl we de Formule 1 in Zandvoort volgen.', 'en' => 'Exclusive for members. A relaxed BBQ while we watch the Formula 1 race in Zandvoort.', 'de' => 'Exklusiv für Mitglieder. Ein gemütliches BBQ, während wir die Formel 1 in Zandvoort verfolgen.'],
  ],
  [
    'date' => '2026-10-31', 'tag' => 'leden', 'time' => '10:00 - 15:00',
    'title' => ['nl' => 'Onderhoudsdag + Halloweenevent', 'en' => 'Maintenance day + Halloween event', 'de' => 'Wartungstag + Halloween-Event'],
    'desc'  => ['nl' => 'Onderhoud aan de baan gecombineerd met een gezellig Halloween-evenement, exclusief voor leden.', 'en' => 'Track maintenance combined with a fun Halloween event, exclusively for members.', 'de' => 'Streckenwartung kombiniert mit einem gemütlichen Halloween-Event, exklusiv für Mitglieder.'],
  ],
  [
    'date' => '2026-12-13', 'tag' => 'leden', 'time' => '10:00 - 15:00',
    'title' => ['nl' => 'Snert/Kerst-rit', 'en' => 'Pea Soup & Christmas Ride', 'de' => 'Erbsensuppe & Weihnachtsfahrt'],
    'desc'  => ['nl' => 'Gezellige winterrit voor leden, afgesloten met warme snert en kerstsfeer.', 'en' => 'A cosy winter ride for members, finished off with warm pea soup and Christmas cheer.', 'de' => 'Eine gemütliche Winterfahrt für Mitglieder, abgeschlossen mit warmer Erbsensuppe und Weihnachtsstimmung.'],
  ],
];

// Standaardinhoud voor de FAQ, alleen gebruikt zolang data/faq.json nog niet
// bestaat. Dit zijn de vijf vragen die nu al op aanmelden.html staan, inclusief
// de originele Engelse en Duitse vertaling, zodat het formulier meteen goed
// gevuld is en opslaan geen zichtbare wijziging geeft.
$faqStandaard = [
  [
    'q' => ['nl' => 'Wanneer ben ik officieel lid?', 'en' => 'When am I officially a member?', 'de' => 'Wann bin ich offiziell Mitglied?'],
    'a' => [
      'nl' => 'Je bent officieel lid zodra je aanmelding is bevestigd door het bestuur én de contributie is ontvangen op onze bankrekening. Je ontvangt dan een bevestiging per e-mail of via de WhatsApp groep.',
      'en' => 'You are officially a member once your registration has been confirmed by the board and the membership fee has been received in our bank account. You will then receive a confirmation by email or via the WhatsApp group.',
      'de' => 'Du bist offiziell Mitglied, sobald deine Anmeldung vom Vorstand bestätigt wurde und der Mitgliedsbeitrag auf unserem Konto eingegangen ist. Du erhältst dann eine Bestätigung per E-Mail oder über die WhatsApp-Gruppe.',
    ],
  ],
  [
    'q' => ['nl' => 'Hoe bereken ik mijn contributie?', 'en' => 'How is my membership fee calculated?', 'de' => 'Wie wird mein Mitgliedsbeitrag berechnet?'],
    'a' => [
      'nl' => 'De contributie wordt berekend op basis van de maand waarin je je aanmeldt. Je betaalt voor de resterende maanden van het jaar. De exacte berekening zie je automatisch zodra je je geboortedatum invult.',
      'en' => 'The fee is calculated based on the month you register. You pay for the remaining months of the year. The exact amount is shown automatically once you enter your date of birth.',
      'de' => 'Der Beitrag wird anhand des Monats berechnet, in dem du dich anmeldest. Du zahlst für die verbleibenden Monate des Jahres. Den genauen Betrag siehst du automatisch, sobald du dein Geburtsdatum eingibst.',
    ],
  ],
  [
    'q' => ['nl' => 'Wat als ik later in het jaar lid word?', 'en' => 'What if I join later in the year?', 'de' => 'Was ist, wenn ich erst später im Jahr beitrete?'],
    'a' => [
      'nl' => 'Dan betaal je een pro-rata bedrag voor de resterende maanden. Schrijf je in december in? Dan betaal je alleen de eenmalige inschrijfkosten van €10; de volledige contributie voor het volgende jaar hoeft dan nog niet te worden overgemaakt.',
      'en' => 'You pay a pro-rata amount for the remaining months. Joining in December? Then you only pay the one-time registration fee of €10; the full membership fee for the following year does not need to be transferred yet.',
      'de' => 'Du zahlst dann einen anteiligen Betrag für die verbleibenden Monate. Wenn du im Dezember beitrittst, zahlst du nur die einmalige Anmeldegebühr von €10; der volle Mitgliedsbeitrag für das nächste Jahr muss dann noch nicht überwiesen werden.',
    ],
  ],
  [
    'q' => ['nl' => 'Moet ik elk jaar opnieuw betalen?', 'en' => 'Do I need to pay every year?', 'de' => 'Muss ich jedes Jahr erneut zahlen?'],
    'a' => [
      'nl' => 'Ja, de contributie wordt jaarlijks geïnd. Je ontvangt hierover tijdig bericht via de WhatsApp groep of nieuwsbrief.',
      'en' => 'Yes, membership fees are collected annually. You will be notified in time via the WhatsApp group or newsletter.',
      'de' => 'Ja, der Mitgliedsbeitrag wird jährlich erhoben. Du wirst rechtzeitig über die WhatsApp-Gruppe oder den Newsletter informiert.',
    ],
  ],
  [
    'q' => ['nl' => 'Kan ik eerst komen kijken voor ik lid word?', 'en' => 'Can I come and have a look before joining?', 'de' => 'Kann ich erst vorbeischauen, bevor ich Mitglied werde?'],
    'a' => [
      'nl' => 'Ja, je kunt altijd eerst als gastrijder langskomen. Volwassenen betalen €10, jeugd t/m 15 jaar betaalt €5 per dag. Meld je bij aankomst bij een bestuurslid.',
      'en' => 'Yes, you can always come as a guest rider first. Adults pay €10, youth up to 15 years pay €5 per day. Check in with a board member on arrival.',
      'de' => 'Ja, du kannst jederzeit als Gastfahrer vorbeikommen. Erwachsene zahlen €10, Jugendliche bis 15 Jahre zahlen €5 pro Tag. Melde dich bei einem Vorstandsmitglied.',
    ],
  ],
];

// Standaardinhoud voor de sponsors, alleen gebruikt zolang data/sponsors.json
// nog niet bestaat. Dit zijn de vijf sponsors die nu al op de site staan.
$sponsorStandaard = [
  ['name' => 'Traxxas', 'url' => '', 'logo' => 'traxxas.png'],
  ['name' => 'Kok Lexmond', 'url' => '', 'logo' => 'kok-lexmond.png'],
  ['name' => 'Toemen', 'url' => '', 'logo' => 'toemen.png'],
  ['name' => 'Shamrock', 'url' => '', 'logo' => 'shamrock.png'],
  ['name' => 'Rothy', 'url' => '', 'logo' => 'rothy.png'],
];

// Standaardtekst voor de "sponsor worden"-oproep onderaan elke pagina, onder
// de sponsorlogo's. Het woord "contactformulier" (of de vertaling ervan) wordt
// op de website automatisch een link naar het contactformulier op de homepage;
// dat gebeurt puur op basis van dat woord in de tekst, dus het moet er letterlijk
// in blijven staan.
$sponsorCtaStandaard = [
  'nl' => 'Sponsor worden? Neem contact op via het contactformulier.',
  'en' => 'Want to become a sponsor? Get in touch via the contact form.',
  'de' => 'Sponsor werden? Kontaktieren Sie uns über das Kontaktformular.',
];

// Standaardinhoud voor de contactgegevens, alleen gebruikt zolang data/contact.json
// nog niet bestaat. Dit zijn de gegevens die nu al (verspreid over meerdere
// pagina's) hardcoded op de site staan, zodat opslaan zonder wijzigingen geen
// zichtbaar verschil geeft.
$contactStandaard = [
  'adres_straat' => 'Wijngaardsberg 26',
  'adres_postcode_plaats' => '6464 EZ Eygelshoven',
  'openingstijden' => [
    // 'status' bepaalt of de dag normaal open is, alleen voor leden open is,
    // alleen bij voldoende animo doorgaat of dicht is, en met welke melding.
    // 'status_tot' is het moment waarop een tijdelijke melding vanzelf vervalt:
    // na afloop van de betreffende dag. De van/tot blijven altijd bewaard.
    'woensdag' => ['van' => '19:00', 'tot' => '22:00', 'status' => 'animo', 'status_tot' => ''],
    'zaterdag' => ['van' => '10:00', 'tot' => '15:00', 'status' => 'open', 'status_tot' => ''],
    'zondag' => ['van' => '10:00', 'tot' => '15:00', 'status' => 'open', 'status_tot' => ''],
  ],
  'lidmaatschap_vanaf' => 'Vanaf €50/jaar',
  'email' => 'bestuur@rc045.nl',
  'facebook' => 'https://www.facebook.com/rc045/',
];

// Standaardinhoud voor de mediaberichten op media.html, alleen gebruikt zolang
// data/media.json nog niet bestaat. Dit zijn de zeven items die nu al op de
// site staan, inclusief de originele Engelse en Duitse vertaling. 'date' is
// steeds de canonieke datum (jjjj-mm-dd); de website zet dat zelf om naar de
// juiste datumnotatie per taal, dus hier hoeft maar één datum ingevuld te worden.
$mediaStandaard = [
  [
    'date' => '2020-11-08', 'bron' => 'Omroep Landgraaf', 'icoon' => '📺',
    'title' => ['nl' => 'Interview Omroep Landgraaf', 'en' => 'Interview Omroep Landgraaf', 'de' => 'Interview Omroep Landgraaf'],
    'desc'  => ['nl' => 'Omroep Landgraaf bracht een interview over onze RC-autoclub, nog voordat we een eigen baan hadden.', 'en' => 'Omroep Landgraaf aired an interview about our RC car club, before we had our own track.', 'de' => 'Omroep Landgraaf brachte ein Interview über unseren RC-Auto-Verein, noch bevor wir eine eigene Strecke hatten.'],
    'link' => 'https://www.facebook.com/OmroepLandgraaf/videos/3636393673049251/',
    'linktekst' => ['nl' => 'Bekijk op Facebook →', 'en' => 'Watch on Facebook →', 'de' => 'Auf Facebook ansehen →'],
  ],
  [
    'date' => '2020-12-06', 'bron' => 'Omroep Landgraaf', 'icoon' => '📺',
    'title' => ['nl' => 'Interview Omroep Landgraaf: Caravanrace', 'en' => 'Interview Omroep Landgraaf: Caravanrace', 'de' => 'Interview Omroep Landgraaf: Caravanrace'],
    'desc'  => ['nl' => 'Een tweede interview met Omroep Landgraaf, ditmaal over de Caravanrace die onze leden organiseerden.', 'en' => 'A second interview with Omroep Landgraaf, this time about the Caravanrace organised by our members.', 'de' => 'Ein zweites Interview mit Omroep Landgraaf, diesmal über das Caravanrennen, das unsere Mitglieder organisierten.'],
    'link' => 'https://www.facebook.com/OmroepLandgraaf/videos/739268850304412/',
    'linktekst' => ['nl' => 'Bekijk op Facebook →', 'en' => 'Watch on Facebook →', 'de' => 'Auf Facebook ansehen →'],
  ],
  [
    'date' => '2021-04-25', 'bron' => 'Omroep Landgraaf', 'icoon' => '📰',
    'title' => ['nl' => 'Artikel Omroep Landgraaf', 'en' => 'Article Omroep Landgraaf', 'de' => 'Artikel Omroep Landgraaf'],
    'desc'  => ['nl' => 'Omroep Landgraaf schreef een artikel over RC045 en de groeiende populariteit van onze hobby in de regio.', 'en' => 'Omroep Landgraaf wrote an article about RC045 and the growing popularity of our hobby in the region.', 'de' => 'Omroep Landgraaf schrieb einen Artikel über RC045 und die wachsende Beliebtheit unseres Hobbys in der Region.'],
    'link' => 'https://www.facebook.com/OmroepLandgraaf/posts/4235498243148100',
    'linktekst' => ['nl' => 'Lees het artikel →', 'en' => 'Read the article →', 'de' => 'Artikel lesen →'],
  ],
  [
    'date' => '2021-04-29', 'bron' => 'ZO-NWS', 'icoon' => '📰',
    'title' => ['nl' => 'Artikel ZO-NWS: Club RC-auto\'s groeit uit jasje', 'en' => 'Article ZO-NWS: RC car club outgrows itself', 'de' => 'Artikel ZO-NWS: RC-Auto-Club wächst aus den Nähten'],
    'desc'  => ['nl' => 'ZO-NWS berichtte over de snelle groei van RC045 en de zoektocht naar een eigen locatie voor onze baan.', 'en' => 'ZO-NWS reported on the rapid growth of RC045 and the search for a dedicated location for our track.', 'de' => 'ZO-NWS berichtete über das schnelle Wachstum von RC045 und die Suche nach einem eigenen Gelände für unsere Strecke.'],
    'link' => 'https://www.zo-nws.nl/video-club-rc-autos-groeit-uit-jasje',
    'linktekst' => ['nl' => 'Lees het artikel →', 'en' => 'Read the article →', 'de' => 'Artikel lesen →'],
  ],
  [
    'date' => '2021-05-04', 'bron' => 'L1mburg', 'icoon' => '📺',
    'title' => ['nl' => 'Route Regio: op zoek naar een nieuwe locatie', 'en' => 'Route Regio: looking for a new location', 'de' => 'Route Regio: auf der Suche nach einem neuen Gelände'],
    'desc'  => ['nl' => 'L1 volgde RC045 in de zoektocht naar een nieuwe locatie voor de club.', 'en' => "L1 followed RC045's search for a new location for the club.", 'de' => 'L1 begleitete RC045 bei der Suche nach einem neuen Gelände für den Verein.'],
    'link' => 'https://www.l1.nl/nieuws/2535856/route-regio-rc-045-is-op-zoek-naar-een-nieuwe-locatie',
    'linktekst' => ['nl' => 'Bekijk de reportage →', 'en' => 'Watch the report →', 'de' => 'Reportage ansehen →'],
  ],
  [
    'date' => '2022-11-20', 'bron' => 'Omroep Landgraaf', 'icoon' => '📺',
    'title' => ['nl' => 'Interview Omroep Landgraaf: opening nieuwe locatie', 'en' => 'Omroep Landgraaf interview: new location opening', 'de' => 'Interview Omroep Landgraaf: Eröffnung des neuen Geländes'],
    'desc'  => ['nl' => 'Omroep Landgraaf was aanwezig bij de opening van onze nieuwe baan aan de Wijngaardsberg in Kerkrade, nadat we waren verhuisd vanaf het veldje bij sporthal Strijthagen.', 'en' => 'Omroep Landgraaf attended the opening of our new track at the Wijngaardsberg in Kerkrade, after our move from the field near sports hall Strijthagen.', 'de' => 'Omroep Landgraaf war bei der Eröffnung unserer neuen Bahn am Wijngaardsberg in Kerkrade dabei, nachdem wir vom Feld bei der Sporthalle Strijthagen umgezogen waren.'],
    'link' => 'https://www.facebook.com/watch/?v=828115825105155',
    'linktekst' => ['nl' => 'Bekijk op Facebook →', 'en' => 'Watch on Facebook →', 'de' => 'Auf Facebook ansehen →'],
  ],
  [
    'date' => '2024-02-13', 'bron' => 'L1mburg', 'icoon' => '📺',
    'title' => ['nl' => 'Route Regio: eindelijk een eigen terrein', 'en' => 'Route Regio: finally our own site', 'de' => 'Route Regio: endlich ein eigenes Gelände'],
    'desc'  => ['nl' => 'Ruim twee jaar later keerde L1 terug: RC045 had eindelijk een eigen terrein gevonden.', 'en' => 'Over two years later, L1 returned: RC045 had finally found its own site.', 'de' => 'Über zwei Jahre später kehrte L1 zurück: RC045 hatte endlich ein eigenes Gelände gefunden.'],
    'link' => 'https://www.l1.nl/nieuws/2542087/route-regio-rc045-heeft-eindelijk-een-eigen-terrein',
    'linktekst' => ['nl' => 'Bekijk de reportage →', 'en' => 'Watch the report →', 'de' => 'Reportage ansehen →'],
  ],
];

// Standaardinhoud voor het nieuwsblok op de homepage, alleen gebruikt zolang
// data/nieuws.json nog niet bestaat. Leeg: het nieuwsblok toont zichzelf
// pas zodra er via het tabblad "Nieuws" een eerste item is toegevoegd.
$nieuwsStandaard = [];

// Standaardinhoud voor de rekentabel contributie, alleen gebruikt zolang
// data/rekentabel.json nog niet bestaat. Zelfde bedragen als voorheen
// hardcoded in beheer.php en aanmelden.html stonden, zodat er bij de
// allereerste keer laden niets verandert aan wat bezoekers zien.


// Standaardinhoud voor de homepage-teksten, alleen gebruikt zolang
// data/homepage.json nog niet bestaat. Zelfde tekst als voorheen hardcoded
// in index.html stond (NL/EN/DE), zodat er bij de allereerste keer laden
// niets verandert aan wat bezoekers zien totdat iemand dit tabblad
// gebruikt.
$homepageStandaard = [
  'hero_intro' => [
    'nl' => "Wij zijn een gezellige vereniging uit het zuiden van Limburg voor liefhebbers van elektrisch aangedreven, radiografisch bestuurbare auto's. Voor beginners én ervaren hobbyisten. Jong én oud.",
    'en' => "We are a friendly club from the south of Limburg for enthusiasts of electrically powered, radio-controlled cars. For beginners and experienced hobbyists alike. Young and old.",
    'de' => "Wir sind ein freundlicher Verein aus dem Süden von Limburg für Liebhaber von elektrisch angetriebenen, ferngesteuerten Autos. Für Anfänger und erfahrene Hobbyisten. Jung und Alt.",
  ],
  'about_p1' => [
    'nl' => "RC045 is een actieve vereniging voor liefhebbers van radiografisch bestuurbare auto's. We rijden met elektrische RC-auto's in alle schalen. Of je nu net begint of al jaren rijdt: bij ons ben je welkom.",
    'en' => "RC045 is an active club for enthusiasts of radio-controlled cars. We drive electric RC cars in all scales. Whether you're just starting out or have been racing for years: you're welcome here.",
    'de' => "RC045 ist ein aktiver Verein für Liebhaber von ferngesteuerten Autos. Wir fahren elektrische RC-Autos in allen Maßstäben. Ob Anfänger oder Erfahrener, bei uns bist du willkommen.",
  ],
  'about_p2' => [
    'nl' => "We beschikken over een eigen baan in Eygelshoven, op het terrein van Kok Lexmond. Naast de basher baan hebben we ook een enorm crawler-parcours en een jump-track.",
    'en' => "We have our own track in Eygelshoven, on the grounds of Kok Lexmond. Besides the basher track, we also have a huge crawler course and a jump track.",
    'de' => "Wir haben eine eigene Strecke in Eygelshoven auf dem Gelände von Kok Lexmond. Neben der Basher-Strecke gibt es auch einen riesigen Crawler-Parcours und eine Sprungstrecke.",
  ],
  'feat1_title' => ['nl' => 'Alleen elektrisch', 'en' => 'Electric only', 'de' => 'Nur elektrisch'],
  'feat1_text' => [
    'nl' => "Nitro en benzine zijn niet toegestaan. Alle electrische auto's zijn welkom!",
    'en' => "Nitro and petrol are not allowed. All electric cars are welcome!",
    'de' => "Nitro und Benzin sind nicht erlaubt. Alle elektrischen Autos sind willkommen!",
  ],
  'feat2_title' => ['nl' => 'Crawler-baan', 'en' => 'Crawler track', 'de' => 'Crawler-Strecke'],
  'feat2_text' => [
    'nl' => "Speciaal terrein voor crawlers en uitdagende obstakels, we breiden ons parcours regelmatig uit",
    'en' => "Dedicated terrain for crawlers and challenging obstacles, we regularly expand the course.",
    'de' => "Spezielles Gelände für Crawler und anspruchsvolle Hindernisse, wir erweitern den Parcours regelmäßig.",
  ],
  'feat3_title' => ['nl' => 'Jump-track', 'en' => 'Jump track', 'de' => 'Sprungstrecke'],
  'feat3_text' => [
    'nl' => "Volle gas over de schans! Voor wie van actie houdt",
    'en' => "Full throttle over the ramp! For those who love action.",
    'de' => "Vollgas über die Rampe! Für alle, die Action lieben.",
  ],
  'feat4_title' => ['nl' => 'Voor iedereen', 'en' => 'For everyone', 'de' => 'Für alle'],
  'feat4_text' => [
    'nl' => "Vanaf 4 jaar is iedereen welkom!",
    'en' => "From age 4, everyone is welcome!",
    'de' => "Ab 4 Jahren ist jeder willkommen!",
  ],
  'track_p1' => [
    'nl' => "Ons terrein bevindt zich op het perceel van Kok Lexmond in Eygelshoven (Kerkrade). We beschikken over meerdere banen: een race-circuit, een crawler-parcours, en een jump-track voor de echte thrill-seekers.",
    'en' => "Our grounds are located on the Kok Lexmond site in Eygelshoven (Kerkrade). We have multiple tracks: a race circuit, a crawler course, and a jump track for the real thrill-seekers.",
    'de' => "Unser Gelände befindet sich auf dem Kok Lexmond Grundstück in Eygelshoven (Kerkrade). Wir haben mehrere Strecken: einen Rennkurs, einen Crawler-Parcours und eine Sprungstrecke für echte Adrenalin-Junkies.",
  ],
  'track_p2' => [
    'nl' => "Volg bij aankomst de pijlen met het RC045-logo en je ziet ons vanzelf. Er is voldoende gratis parkeergelegenheid.",
    'en' => "Follow the RC045 arrows on arrival and you'll find us easily. There is plenty of free parking.",
    'de' => "Folge beim Ankommen den RC045-Schildern und du findest uns sofort. Es gibt ausreichend kostenlose Parkplätze.",
  ],
  'track_f1' => ['nl' => "Race-circuit voor buggy's, truggies en meer", 'en' => "Race circuit for buggies, truggies and more", 'de' => "Rennstrecke für Buggys, Truggies und mehr"],
  'track_f2' => ['nl' => "Off-road crawler-parcours", 'en' => "Off-road crawler course", 'de' => "Offroad-Crawler-Parcours"],
  'track_f3' => ['nl' => "Jump-track met schans", 'en' => "Jump track with ramp", 'de' => "Sprungstrecke mit Rampe"],
  'track_f4' => ['nl' => "Kantine & werkruimte aanwezig", 'en' => "Canteen & workshop available", 'de' => "Kantine & Werkraum vorhanden"],
  'track_f5' => ['nl' => "Voldoende parkeerruimte", 'en' => "Ample parking", 'de' => "Ausreichend Parkplätze"],
  'pricing_title' => [
    'nl' => "Lid worden of een keer komen kijken?",
    'en' => "Become a member or come and have a look?",
    'de' => "Mitglied werden oder einfach vorbeischauen?",
  ],
  'pricing_sub' => [
    'nl' => "Je kunt altijd eerst als gast komen rijden om te ervaren of het iets voor jou is. Daarna kun je eventueel lid worden en volop genieten van onze banen.",
    'en' => "You can always come as a guest first to see if it suits you. After that, you can become a member and enjoy our tracks to the fullest.",
    'de' => "Du kannst zunächst als Gast fahren, um zu sehen, ob es dir gefällt. Danach kannst du Mitglied werden und unsere Strecken in vollen Zügen genießen.",
  ],
  'guest_title' => [
    'nl' => "Kom eens gastrijden!",
    'en' => "Come for a guest ride!",
    'de' => "Komm mal als Gast fahren!",
  ],
  'guest_text' => [
    'nl' => "Rij een hele dag mee op onze baan zonder lidmaatschap. Check onze openingstijden en kom gewoon langs, meld je wel even bij een (bestuurs)lid als je er bent!",
    'en' => "Ride all day on our track without a membership. Check our opening hours and just show up, and check in with a club member when you arrive!",
    'de' => "Fahre einen ganzen Tag auf unserer Strecke ohne Mitgliedschaft. Schau einfach vorbei, melde dich beim Ankommen kurz bei einem Vereinsmitglied!",
  ],
  'guest_note' => [
    'nl' => "Begeleiding door ouder/verzorger verplicht voor -16 jaar. Tijdens besloten- of ledenevenementen is gastrijden niet mogelijk.",
    'en' => "Supervision by a parent or guardian required for under 16. Not available during private or members-only events.",
    'de' => "Begleitung durch Elternteil oder Erziehungsberechtigte für unter 16 Jahre erforderlich. Nicht möglich bei geschlossenen Veranstaltungen.",
  ],
  'member_title' => [
    'nl' => "Word lid van RC045",
    'en' => "Become a member of RC045",
    'de' => "Werde Mitglied bei RC045",
  ],
  'member_text' => [
    'nl' => "Onbeperkt rijden op alle banen, toegang tot de groepsapp, kennis delen met medehobbyisten en altijd iemand om je mee te helpen.",
    'en' => "Unlimited riding on all tracks, access to the group app, sharing knowledge with fellow hobbyists, and always someone to help you out.",
    'de' => "Unbegrenztes Fahren auf allen Strecken, Zugang zur Gruppen-App, Wissensaustausch mit Gleichgesinnten und immer jemand zum Helfen.",
  ],
  'member_note' => [
    'nl' => "Contributie pro-rata: je betaalt alleen voor de resterende maanden van het jaar.",
    'en' => "Pro-rata membership: you only pay for the remaining months of the year.",
    'de' => "Anteilige Mitgliedschaft: Du zahlst nur für die verbleibenden Monate des Jahres.",
  ],
  'hero_btn_member' => [
    'nl' => "Lid worden!",
    'en' => "Become a member!",
    'de' => "Mitglied werden!",
  ],
  'hero_btn_more' => [
    'nl' => "Meer over ons",
    'en' => "More about us",
    'de' => "Mehr über uns",
  ],
  'update_label' => [
    'nl' => "📣 Actueel:",
    'en' => "📣 Update:",
    'de' => "📣 Aktuell:",
  ],
  'info_hours' => [
    'nl' => "Openingstijden",
    'en' => "Opening hours",
    'de' => "Öffnungszeiten",
  ],
  'info_location' => [
    'nl' => "Locatie",
    'en' => "Location",
    'de' => "Standort",
  ],
  'info_membership' => [
    'nl' => "Lidmaatschap",
    'en' => "Membership",
    'de' => "Mitgliedschaft",
  ],
  'info_weather' => [
    'nl' => "Weer in Eygelshoven",
    'en' => "Weather in Eygelshoven",
    'de' => "Wetter in Eygelshoven",
  ],
  'about_label' => [
    'nl' => "Wie zijn wij",
    'en' => "Who we are",
    'de' => "Wer wir sind",
  ],
  'about_title' => [
    'nl' => "Dé RC-vereniging van Zuid-Limburg",
    'en' => "The RC club of South Limburg",
    'de' => "Der RC-Verein in Südlimburg",
  ],
  'about_medialink' => [
    'nl' => "RC045 in de media →",
    'en' => "RC045 in the media →",
    'de' => "RC045 in den Medien →",
  ],
  'about_storylink' => [
    'nl' => "Lees het ontstaansverhaal →",
    'en' => "Read our story →",
    'de' => "Unsere Geschichte →",
  ],
  'about_photos_title' => [
    'nl' => "Crawlerparcours",
    'en' => "Crawler course",
    'de' => "Crawler-Parcours",
  ],
  'track_label' => [
    'nl' => "Onze locatie",
    'en' => "Our location",
    'de' => "Unser Standort",
  ],
  'track_title' => [
    'nl' => "De baan in Eygelshoven",
    'en' => "The track in Eygelshoven",
    'de' => "Die Strecke in Eygelshoven",
  ],
  'hours_title' => [
    'nl' => "🕐 Openingstijden",
    'en' => "🕐 Opening hours",
    'de' => "🕐 Öffnungszeiten",
  ],
  'hours_sat' => [
    'nl' => "Zaterdag",
    'en' => "Saturday",
    'de' => "Samstag",
  ],
  'hours_sun' => [
    'nl' => "Zondag",
    'en' => "Sunday",
    'de' => "Sonntag",
  ],
  'hours_wed' => [
    'nl' => "Woensdag",
    'en' => "Wednesday",
    'de' => "Mittwoch",
  ],
  'hours_weather' => [
    'nl' => "Bij slecht weer kunnen we besluiten eerder te sluiten of helemaal niet open te gaan.",
    'en' => "In bad weather we may decide to close early or not open at all.",
    'de' => "Bei schlechtem Wetter können wir früher schließen oder gar nicht öffnen.",
  ],
  'hours_note_attention' => [
    'nl' => "Let op:",
    'en' => "Please note:",
    'de' => "Hinweis:",
  ],
  'hours_note_text' => [
    'nl' => "We zijn de eerste zaterdag of zondag van de maand gesloten wegens onderhoud.",
    'en' => "We are closed the first Saturday or Sunday of the month for maintenance.",
    'de' => "Wir sind am ersten Samstag oder Sonntag des Monats wegen Wartungsarbeiten geschlossen.",
  ],
  'rules_label' => [
    'nl' => "Reglement",
    'en' => "Rules",
    'de' => "Reglement",
  ],
  'rules_title' => [
    'nl' => "Veiligheid staat voorop",
    'en' => "Safety comes first",
    'de' => "Sicherheit geht vor",
  ],
  'rules_sub' => [
    'nl' => "We hebben duidelijke regels zodat iedereen veilig en met plezier kan rijden. Hieronder lees je de belangrijkste punten.",
    'en' => "We have clear rules so everyone can ride safely and have fun. Below you can read the most important points.",
    'de' => "Wir haben klare Regeln, damit alle sicher und mit Freude fahren können. Im Folgenden liest du die wichtigsten Punkte.",
  ],
  'rules_link' => [
    'nl' => "Volledig (statutair) baanreglement lezen →",
    'en' => "Read the full (statutory) track regulations →",
    'de' => "Vollständiges (satzungsgemäßes) Streckenreglement lesen →",
  ],
  'rule1_title' => [
    'nl' => "Alleen elektrisch",
    'en' => "Electric only",
    'de' => "Nur elektrisch",
  ],
  'rule1_text' => [
    'nl' => "Nitro en benzine zijn niet toegestaan op ons terrein. Alleen elektrisch aangedreven voertuigen zijn welkom.",
    'en' => "Nitro and petrol are not permitted on our grounds. Only electrically powered vehicles are welcome.",
    'de' => "Nitro und Benzin sind auf unserem Gelände nicht erlaubt. Nur elektrisch angetriebene Fahrzeuge sind willkommen.",
  ],
  'rule2_title' => [
    'nl' => "Veiligheid baan",
    'en' => "Track safety",
    'de' => "Streckensicherheit",
  ],
  'rule2_text' => [
    'nl' => "Alleen rijders mogen zich op het rijderspodium begeven. Kijken doe je achter het hek. De baanmeester (oranje hesje) bepaalt of er gereden mag worden.",
    'en' => "Only riders are allowed on the driver's platform. Spectators watch from behind the fence. The track marshal (orange vest) decides whether riding is permitted.",
    'de' => "Nur Fahrer dürfen das Fahrerpodium betreten. Zuschauer bleiben hinter dem Zaun. Der Streckenmarschall (orangene Weste) entscheidet, ob gefahren werden darf.",
  ],
  'rule3_title' => [
    'nl' => "Gastrijders",
    'en' => "Guest riders",
    'de' => "Gastfahrer",
  ],
  'rule3_text' => [
    'nl' => "Aanmelden bij een bestuurslid verplicht. Onder 16 jaar altijd begeleid door ouder/verzorger.",
    'en' => "Check-in with a board member is mandatory. Under 16 must always be accompanied by a parent or guardian.",
    'de' => "Anmeldung bei einem Vorstandsmitglied erforderlich. Unter 16 Jahren immer mit Elternteil oder Erziehungsberechtigtem.",
  ],
  'rule4_title' => [
    'nl' => "Laden van accu's",
    'en' => "Charging batteries",
    'de' => "Akkus laden",
  ],
  'rule4_text' => [
    'nl' => "Accu's laden we alleen buiten, bij de daarvoor bestemde laadplek te herkennen aan het laadpaal-bord. Defecte accu's mag je niet weggooien in onze emmers, neem ze mee naar huis en voer ze zelf af.",
    'en' => "Batteries are only charged outside, at the designated charging area marked with the charging point sign. Do not throw defective batteries in our bins, take them home and dispose of them yourself.",
    'de' => "Akkus werden nur draußen geladen, an dem dafür vorgesehenen Ladeplatz, erkennbar am Ladesäulen-Schild. Defekte Akkus nicht in unsere Eimer werfen, nimm sie mit nach Hause und entsorge sie selbst.",
  ],
  'rule5_title' => [
    'nl' => "Opgeruimd staat netjes",
    'en' => "Tidy up after yourself",
    'de' => "Aufgeräumt ist besser",
  ],
  'rule5_text' => [
    'nl' => "Ieder lid ruimt mee op. Afval scheiden we in de daarvoor aangewezen bakken. De kantine laten we schoon achter.",
    'en' => "Every member helps clean up. We separate waste in the designated bins. We leave the canteen as we found it.",
    'de' => "Jedes Mitglied räumt mit auf. Müll trennen wir in die vorgesehenen Behälter. Wir hinterlassen die Kantine sauber.",
  ],
  'rule6_title' => [
    'nl' => "Geen alcohol of drugs",
    'en' => "No alcohol or drugs",
    'de' => "Kein Alkohol oder Drogen",
  ],
  'rule6_text' => [
    'nl' => "Alcoholhoudende dranken en verdovende middelen zijn ten allen tijden verboden op het gehele terrein.",
    'en' => "Alcoholic beverages and narcotics are strictly prohibited at all times on the entire premises.",
    'de' => "Alkoholische Getränke und Betäubungsmittel sind zu jeder Zeit auf dem gesamten Gelände verboten.",
  ],
  'rule7_title' => [
    'nl' => "We rijden nooit op het asfalt",
    'en' => "We never ride on the asphalt",
    'de' => "Wir fahren nie auf dem Asphalt",
  ],
  'rule7_text' => [
    'nl' => "Het is verboden om te rijden op het asfalt. Van de kantine naar het rijderspodium rijd je stapvoets.",
    'en' => "It is forbidden to ride on the asphalt. From the canteen to the driver's platform, you ride at walking pace.",
    'de' => "Es ist verboten, auf dem Asphalt zu fahren. Von der Kantine zum Fahrerpodium fährst du Schrittgeschwindigkeit.",
  ],
  'nieuws_label' => [
    'nl' => "Nieuws",
    'en' => "News",
    'de' => "Neuigkeiten",
  ],
  'nieuws_title' => [
    'nl' => "Laatste updates",
    'en' => "Latest updates",
    'de' => "Letzte Updates",
  ],
  'nieuws_sub' => [
    'nl' => "Het laatste nieuws van RC045.",
    'en' => "The latest news from RC045.",
    'de' => "Die neuesten Nachrichten von RC045.",
  ],
  'agenda_label' => [
    'nl' => "Agenda",
    'en' => "Events",
    'de' => "Veranstaltungen",
  ],
  'agenda_title' => [
    'nl' => "Activiteiten",
    'en' => "Activities",
    'de' => "Aktivitäten",
  ],
  'agenda_sub' => [
    'nl' => "Kijk hier wat er op de planning staat bij RC045. Check onze Facebook-pagina voor de meest actuele informatie.",
    'en' => "Check what is planned at RC045. Follow our Facebook page for the most up-to-date information.",
    'de' => "Schau hier, was bei RC045 geplant ist. Folge unserer Facebook-Seite für die aktuellsten Informationen.",
  ],
  'loc_label' => [
    'nl' => "Bezoek ons",
    'en' => "Visit us",
    'de' => "Besuche uns",
  ],
  'loc_title' => [
    'nl' => "Hoe vind je ons?",
    'en' => "How to find us?",
    'de' => "Wie findest du uns?",
  ],
  'addr_title' => [
    'nl' => "Adres",
    'en' => "Address",
    'de' => "Adresse",
  ],
  'addr_text' => [
    'nl' => "Onze baan ligt op het terrein van Kok Lexmond, bij aankomst volg je de pijlen RC045.",
    'en' => "Our track is on the Kok Lexmond site, follow the RC045 arrows on arrival.",
    'de' => "Unsere Strecke liegt auf dem Gelände von Kok Lexmond, folge beim Ankommen den RC045-Schildern.",
  ],
  'addr_route' => [
    'nl' => "Routebeschrijving openen →",
    'en' => "Open directions →",
    'de' => "Route öffnen →",
  ],
  'instagram_soon' => [
    'nl' => "Binnenkort beschikbaar",
    'en' => "Coming soon",
    'de' => "Bald verfügbar",
  ],
  'contact_label' => [
    'nl' => "Contact",
    'en' => "Contact",
    'de' => "Kontakt",
  ],
  'contact_title' => [
    'nl' => "Heb je een vraag?",
    'en' => "Got a question?",
    'de' => "Hast du eine Frage?",
  ],
  'contact_text' => [
    'nl' => "Wil je meer weten over een lidmaatschap, gastrijden, eens komen kijken, of heb je gewoon een vraag? Stuur ons een bericht en we reageren zo snel mogelijk.",
    'en' => "Want to know more about membership, guest riding, or just have a question? Send us a message and we'll get back to you as soon as possible.",
    'de' => "Möchtest du mehr über die Mitgliedschaft, Gastfahren oder hast du einfach eine Frage? Schick uns eine Nachricht und wir antworten so schnell wie möglich.",
  ],
];

// Later (na de eerste versie hierboven) toegevoegde CMS-velden: het
// lidmaatschapsblok-detail, het navigatiemenu, de footer en het
// contactformulier. Overgenomen uit index.html/site-i18n.js, op dezelfde
// manier als hierboven. Als een sleutel hierboven al bestaat, wint die
// versie: de += operator laat bestaande sleutels links onaangeroerd.
$homepageStandaard += json_decode('{"hero_intro": {"nl": "Wij zijn een gezellige vereniging uit het zuiden van Limburg voor liefhebbers van elektrisch aangedreven, radiografisch bestuurbare auto\'s. Voor beginners én ervaren hobbyisten. Jong én oud.", "en": "We are a friendly club from the south of Limburg for enthusiasts of electrically powered, radio-controlled cars. For beginners and experienced hobbyists alike. Young and old.", "de": "Wir sind ein freundlicher Verein aus dem Süden von Limburg für Liebhaber von elektrisch angetriebenen, ferngesteuerten Autos. Für Anfänger und erfahrene Hobbyisten. Jung und Alt."}, "hero_btn_member": {"nl": "Lid worden!", "en": "Become a member!", "de": "Mitglied werden!"}, "hero_btn_more": {"nl": "Meer over ons", "en": "More about us", "de": "Mehr über uns"}, "update_label": {"nl": "📣 Actueel:", "en": "📣 Update:", "de": "📣 Aktuell:"}, "info_hours": {"nl": "Openingstijden", "en": "Opening hours", "de": "Öffnungszeiten"}, "info_location": {"nl": "Locatie", "en": "Location", "de": "Standort"}, "info_membership": {"nl": "Lidmaatschap", "en": "Membership", "de": "Mitgliedschaft"}, "info_weather": {"nl": "Weer in Eygelshoven", "en": "Weather in Eygelshoven", "de": "Wetter in Eygelshoven"}, "about_label": {"nl": "Wie zijn wij", "en": "Who we are", "de": "Wer wir sind"}, "about_title": {"nl": "Dé RC-vereniging van Zuid-Limburg", "en": "The RC club of South Limburg", "de": "Der RC-Verein in Südlimburg"}, "about_medialink": {"nl": "RC045 in de media →", "en": "RC045 in the media →", "de": "RC045 in den Medien →"}, "about_storylink": {"nl": "Lees het ontstaansverhaal →", "en": "Read our story →", "de": "Unsere Geschichte →"}, "about_photos_title": {"nl": "Crawlerparcours", "en": "Crawler course", "de": "Crawler-Parcours"}, "track_label": {"nl": "Onze locatie", "en": "Our location", "de": "Unser Standort"}, "track_title": {"nl": "De baan in Eygelshoven", "en": "The track in Eygelshoven", "de": "Die Strecke in Eygelshoven"}, "hours_title": {"nl": "🕐 Openingstijden", "en": "🕐 Opening hours", "de": "🕐 Öffnungszeiten"}, "hours_sat": {"nl": "Zaterdag", "en": "Saturday", "de": "Samstag"}, "hours_sun": {"nl": "Zondag", "en": "Sunday", "de": "Sonntag"}, "hours_wed": {"nl": "Woensdag", "en": "Wednesday", "de": "Mittwoch"}, "hours_weather": {"nl": "Bij slecht weer kunnen we besluiten eerder te sluiten of helemaal niet open te gaan.", "en": "In bad weather we may decide to close early or not open at all.", "de": "Bei schlechtem Wetter können wir früher schließen oder gar nicht öffnen."}, "hours_note_attention": {"nl": "Let op:", "en": "Please note:", "de": "Hinweis:"}, "hours_note_text": {"nl": "We zijn de eerste zaterdag of zondag van de maand gesloten wegens onderhoud.", "en": "We are closed the first Saturday or Sunday of the month for maintenance.", "de": "Wir sind am ersten Samstag oder Sonntag des Monats wegen Wartungsarbeiten geschlossen."}, "rules_label": {"nl": "Reglement", "en": "Rules", "de": "Reglement"}, "rules_title": {"nl": "Veiligheid staat voorop", "en": "Safety comes first", "de": "Sicherheit geht vor"}, "rules_sub": {"nl": "We hebben duidelijke regels zodat iedereen veilig en met plezier kan rijden. Hieronder lees je de belangrijkste punten.", "en": "We have clear rules so everyone can ride safely and have fun. Below you can read the most important points.", "de": "Wir haben klare Regeln, damit alle sicher und mit Freude fahren können. Im Folgenden liest du die wichtigsten Punkte."}, "rules_link": {"nl": "Volledig (statutair) baanreglement lezen →", "en": "Read the full (statutory) track regulations →", "de": "Vollständiges (satzungsgemäßes) Streckenreglement lesen →"}, "rule1_title": {"nl": "Alleen elektrisch", "en": "Electric only", "de": "Nur elektrisch"}, "rule1_text": {"nl": "Nitro en benzine zijn niet toegestaan op ons terrein. Alleen elektrisch aangedreven voertuigen zijn welkom.", "en": "Nitro and petrol are not permitted on our grounds. Only electrically powered vehicles are welcome.", "de": "Nitro und Benzin sind auf unserem Gelände nicht erlaubt. Nur elektrisch angetriebene Fahrzeuge sind willkommen."}, "rule2_title": {"nl": "Veiligheid baan", "en": "Track safety", "de": "Streckensicherheit"}, "rule2_text": {"nl": "Alleen rijders mogen zich op het rijderspodium begeven. Kijken doe je achter het hek. De baanmeester (oranje hesje) bepaalt of er gereden mag worden.", "en": "Only riders are allowed on the driver\'s platform. Spectators watch from behind the fence. The track marshal (orange vest) decides whether riding is permitted.", "de": "Nur Fahrer dürfen das Fahrerpodium betreten. Zuschauer bleiben hinter dem Zaun. Der Streckenmarschall (orangene Weste) entscheidet, ob gefahren werden darf."}, "rule3_title": {"nl": "Gastrijders", "en": "Guest riders", "de": "Gastfahrer"}, "rule3_text": {"nl": "Aanmelden bij een bestuurslid verplicht. Onder 16 jaar altijd begeleid door ouder/verzorger.", "en": "Check-in with a board member is mandatory. Under 16 must always be accompanied by a parent or guardian.", "de": "Anmeldung bei einem Vorstandsmitglied erforderlich. Unter 16 Jahren immer mit Elternteil oder Erziehungsberechtigtem."}, "rule4_title": {"nl": "Laden van accu\'s", "en": "Charging batteries", "de": "Akkus laden"}, "rule4_text": {"nl": "Accu\'s laden we alleen buiten, bij de daarvoor bestemde laadplek te herkennen aan het laadpaal-bord. Defecte accu\'s mag je niet weggooien in onze emmers, neem ze mee naar huis en voer ze zelf af.", "en": "Batteries are only charged outside, at the designated charging area marked with the charging point sign. Do not throw defective batteries in our bins, take them home and dispose of them yourself.", "de": "Akkus werden nur draußen geladen, an dem dafür vorgesehenen Ladeplatz, erkennbar am Ladesäulen-Schild. Defekte Akkus nicht in unsere Eimer werfen, nimm sie mit nach Hause und entsorge sie selbst."}, "rule5_title": {"nl": "Opgeruimd staat netjes", "en": "Tidy up after yourself", "de": "Aufgeräumt ist besser"}, "rule5_text": {"nl": "Ieder lid ruimt mee op. Afval scheiden we in de daarvoor aangewezen bakken. De kantine laten we schoon achter.", "en": "Every member helps clean up. We separate waste in the designated bins. We leave the canteen as we found it.", "de": "Jedes Mitglied räumt mit auf. Müll trennen wir in die vorgesehenen Behälter. Wir hinterlassen die Kantine sauber."}, "rule6_title": {"nl": "Geen alcohol of drugs", "en": "No alcohol or drugs", "de": "Kein Alkohol oder Drogen"}, "rule6_text": {"nl": "Alcoholhoudende dranken en verdovende middelen zijn ten allen tijden verboden op het gehele terrein.", "en": "Alcoholic beverages and narcotics are strictly prohibited at all times on the entire premises.", "de": "Alkoholische Getränke und Betäubungsmittel sind zu jeder Zeit auf dem gesamten Gelände verboten."}, "rule7_title": {"nl": "We rijden nooit op het asfalt", "en": "We never ride on the asphalt", "de": "Wir fahren nie auf dem Asphalt"}, "rule7_text": {"nl": "Het is verboden om te rijden op het asfalt. Van de kantine naar het rijderspodium rijd je stapvoets.", "en": "It is forbidden to ride on the asphalt. From the canteen to the driver\'s platform, you ride at walking pace.", "de": "Es ist verboten, auf dem Asphalt zu fahren. Von der Kantine zum Fahrerpodium fährst du Schrittgeschwindigkeit."}, "nieuws_label": {"nl": "Nieuws", "en": "News", "de": "Neuigkeiten"}, "nieuws_title": {"nl": "Laatste updates", "en": "Latest updates", "de": "Letzte Updates"}, "nieuws_sub": {"nl": "Het laatste nieuws van RC045.", "en": "The latest news from RC045.", "de": "Die neuesten Nachrichten von RC045."}, "agenda_label": {"nl": "Agenda", "en": "Events", "de": "Veranstaltungen"}, "agenda_title": {"nl": "Activiteiten", "en": "Activities", "de": "Aktivitäten"}, "agenda_sub": {"nl": "Kijk hier wat er op de planning staat bij RC045. Check onze Facebook-pagina voor de meest actuele informatie.", "en": "Check what is planned at RC045. Follow our Facebook page for the most up-to-date information.", "de": "Schau hier, was bei RC045 geplant ist. Folge unserer Facebook-Seite für die aktuellsten Informationen."}, "loc_label": {"nl": "Bezoek ons", "en": "Visit us", "de": "Besuche uns"}, "loc_title": {"nl": "Hoe vind je ons?", "en": "How to find us?", "de": "Wie findest du uns?"}, "addr_title": {"nl": "Adres", "en": "Address", "de": "Adresse"}, "addr_text": {"nl": "Onze baan ligt op het terrein van Kok Lexmond, bij aankomst volg je de pijlen RC045.", "en": "Our track is on the Kok Lexmond site, follow the RC045 arrows on arrival.", "de": "Unsere Strecke liegt auf dem Gelände von Kok Lexmond, folge beim Ankommen den RC045-Schildern."}, "addr_route": {"nl": "Routebeschrijving openen →", "en": "Open directions →", "de": "Route öffnen →"}, "instagram_soon": {"nl": "Binnenkort beschikbaar", "en": "Coming soon", "de": "Bald verfügbar"}, "contact_label": {"nl": "Contact", "en": "Contact", "de": "Kontakt"}, "contact_title": {"nl": "Heb je een vraag?", "en": "Got a question?", "de": "Hast du eine Frage?"}, "contact_text": {"nl": "Wil je meer weten over een lidmaatschap, gastrijden, eens komen kijken, of heb je gewoon een vraag? Stuur ons een bericht en we reageren zo snel mogelijk.", "en": "Want to know more about membership, guest riding, or just have a question? Send us a message and we\'ll get back to you as soon as possible.", "de": "Möchtest du mehr über die Mitgliedschaft, Gastfahren oder hast du einfach eine Frage? Schick uns eine Nachricht und wir antworten so schnell wie möglich."}, "about_p1": {"nl": "RC045 is een actieve vereniging voor liefhebbers van radiografisch bestuurbare auto\'s. We rijden met elektrische RC-auto\'s in alle schalen. Of je nu net begint of al jaren rijdt: bij ons ben je welkom.", "en": "RC045 is an active club for enthusiasts of radio-controlled cars. We drive electric RC cars in all scales. Whether you\'re just starting out or have been racing for years: you\'re welcome here.", "de": "RC045 ist ein aktiver Verein für Liebhaber von ferngesteuerten Autos. Wir fahren elektrische RC-Autos in allen Maßstäben. Ob Anfänger oder Erfahrener, bei uns bist du willkommen."}, "about_p2": {"nl": "We beschikken over een eigen baan in Eygelshoven, op het terrein van Kok Lexmond. Naast de basher baan hebben we ook een enorm crawler-parcours en een jump-track.", "en": "We have our own track in Eygelshoven, on the grounds of Kok Lexmond. Besides the basher track, we also have a huge crawler course and a jump track.", "de": "Wir haben eine eigene Strecke in Eygelshoven auf dem Gelände von Kok Lexmond. Neben der Basher-Strecke gibt es auch einen riesigen Crawler-Parcours und eine Sprungstrecke."}, "feat1_title": {"nl": "Alleen elektrisch", "en": "Electric only", "de": "Nur elektrisch"}, "feat1_text": {"nl": "Nitro en benzine zijn niet toegestaan. Alle electrische auto\'s zijn welkom!", "en": "Nitro and petrol are not allowed. All electric cars are welcome!", "de": "Nitro und Benzin sind nicht erlaubt. Alle elektrischen Autos sind willkommen!"}, "feat2_title": {"nl": "Crawler-baan", "en": "Crawler track", "de": "Crawler-Strecke"}, "feat2_text": {"nl": "Speciaal terrein voor crawlers en uitdagende obstakels, we breiden ons parcours regelmatig uit", "en": "Dedicated terrain for crawlers and challenging obstacles, we regularly expand the course.", "de": "Spezielles Gelände für Crawler und anspruchsvolle Hindernisse, wir erweitern den Parcours regelmäßig."}, "feat3_title": {"nl": "Jump-track", "en": "Jump track", "de": "Sprungstrecke"}, "feat3_text": {"nl": "Volle gas over de schans! Voor wie van actie houdt", "en": "Full throttle over the ramp! For those who love action.", "de": "Vollgas über die Rampe! Für alle, die Action lieben."}, "feat4_title": {"nl": "Voor iedereen", "en": "For everyone", "de": "Für alle"}, "feat4_text": {"nl": "Vanaf 4 jaar is iedereen welkom!", "en": "From age 4, everyone is welcome!", "de": "Ab 4 Jahren ist jeder willkommen!"}, "track_p1": {"nl": "Ons terrein bevindt zich op het perceel van Kok Lexmond in Eygelshoven (Kerkrade). We beschikken over meerdere banen: een race-circuit, een crawler-parcours, en een jump-track voor de echte thrill-seekers.", "en": "Our grounds are located on the Kok Lexmond site in Eygelshoven (Kerkrade). We have multiple tracks: a race circuit, a crawler course, and a jump track for the real thrill-seekers.", "de": "Unser Gelände befindet sich auf dem Kok Lexmond Grundstück in Eygelshoven (Kerkrade). Wir haben mehrere Strecken: einen Rennkurs, einen Crawler-Parcours und eine Sprungstrecke für echte Adrenalin-Junkies."}, "track_p2": {"nl": "Volg bij aankomst de pijlen met het RC045-logo en je ziet ons vanzelf. Er is voldoende gratis parkeergelegenheid.", "en": "Follow the RC045 arrows on arrival and you\'ll find us easily. There is plenty of free parking.", "de": "Folge beim Ankommen den RC045-Schildern und du findest uns sofort. Es gibt ausreichend kostenlose Parkplätze."}, "track_f1": {"nl": "Race-circuit voor buggy\'s, truggies en meer", "en": "Race circuit for buggies, truggies and more", "de": "Rennstrecke für Buggys, Truggies und mehr"}, "track_f2": {"nl": "Off-road crawler-parcours", "en": "Off-road crawler course", "de": "Offroad-Crawler-Parcours"}, "track_f3": {"nl": "Jump-track met schans", "en": "Jump track with ramp", "de": "Sprungstrecke mit Rampe"}, "track_f4": {"nl": "Kantine & werkruimte aanwezig", "en": "Canteen & workshop available", "de": "Kantine & Werkraum vorhanden"}, "track_f5": {"nl": "Voldoende parkeerruimte", "en": "Ample parking", "de": "Ausreichend Parkplätze"}, "pricing_title": {"nl": "Lid worden of een keer komen kijken?", "en": "Become a member or come and have a look?", "de": "Mitglied werden oder einfach vorbeischauen?"}, "pricing_sub": {"nl": "Je kunt altijd eerst als gast komen rijden om te ervaren of het iets voor jou is. Daarna kun je eventueel lid worden en volop genieten van onze banen.", "en": "You can always come as a guest first to see if it suits you. After that, you can become a member and enjoy our tracks to the fullest.", "de": "Du kannst zunächst als Gast fahren, um zu sehen, ob es dir gefällt. Danach kannst du Mitglied werden und unsere Strecken in vollen Zügen genießen."}, "guest_tag": {"nl": "Gastrijden", "en": "Guest riding", "de": "Gastfahren"}, "guest_title": {"nl": "Kom eens gastrijden!", "en": "Come for a guest ride!", "de": "Komm mal als Gast fahren!"}, "guest_text": {"nl": "Rij een hele dag mee op onze baan zonder lidmaatschap. Check onze openingstijden en kom gewoon langs, meld je wel even bij een (bestuurs)lid als je er bent!", "en": "Ride all day on our track without a membership. Check our opening hours and just show up, and check in with a club member when you arrive!", "de": "Fahre einen ganzen Tag auf unserer Strecke ohne Mitgliedschaft. Schau einfach vorbei, melde dich beim Ankommen kurz bei einem Vereinsmitglied!"}, "guest_adult": {"nl": "Volwassene (16+)", "en": "Adult (16+)", "de": "Erwachsener (16+)"}, "guest_youth": {"nl": "Jeugd (t/m 15 jaar)", "en": "Youth (up to 15)", "de": "Jugend (bis 15 Jahre)"}, "guest_group": {"nl": "Groepen krijgen korting!", "en": "Groups get a discount!", "de": "Gruppen bekommen Rabatt!"}, "guest_btn": {"nl": "Stuur ons een berichtje →", "en": "Send us a message →", "de": "Schick uns eine Nachricht →"}, "guest_note": {"nl": "Begeleiding door ouder/verzorger verplicht voor -16 jaar. Tijdens besloten- of ledenevenementen is gastrijden niet mogelijk.\\nKom je met 4 of meer personen? Meld je dan van te voren via het contactformulier of bestuur@rc045.nl", "en": "Supervision by a parent or guardian required for under 16. Not available during private or members-only events.\\nComing with 4 or more people? Please let us know in advance via the contact form or bestuur@rc045.nl", "de": "Begleitung durch Elternteil oder Erziehungsberechtigte für unter 16 Jahre erforderlich. Nicht möglich bei geschlossenen Veranstaltungen.\\nKommst du mit 4 oder mehr Personen? Melde dich dann immer vorher über das Kontaktformular oder bestuur@rc045.nl"}, "member_tag": {"nl": "Lidmaatschap", "en": "Membership", "de": "Mitgliedschaft"}, "member_title": {"nl": "Word lid van RC045", "en": "Become a member of RC045", "de": "Werde Mitglied bei RC045"}, "member_text": {"nl": "Onbeperkt rijden op alle banen, toegang tot de groepsapp, kennis delen met medehobbyisten en altijd iemand om je mee te helpen.", "en": "Unlimited riding on all tracks, access to the group app, sharing knowledge with fellow hobbyists, and always someone to help you out.", "de": "Unbegrenztes Fahren auf allen Strecken, Zugang zur Gruppen-App, Wissensaustausch mit Gleichgesinnten und immer jemand zum Helfen."}, "member_youth": {"nl": "Jeugdlid (t/m 15 jaar)", "en": "Youth member (up to 15)", "de": "Jugendmitglied (bis 15 Jahre)"}, "member_senior": {"nl": "Seniorlid (16+)", "en": "Senior member (16+)", "de": "Seniorenmitglied (16+)"}, "member_fee": {"nl": "Eenmalige inschrijfkosten", "en": "One-time registration fee", "de": "Einmalige Anmeldegebühr"}, "member_btn": {"nl": "Ik wil graag lid worden!", "en": "I would like to become a member!", "de": "Ich möchte gerne Mitglied werden!"}, "member_note": {"nl": "Contributie pro-rata: je betaalt alleen voor de resterende maanden van het jaar.", "en": "Pro-rata membership: you only pay for the remaining months of the year.", "de": "Anteilige Mitgliedschaft: Du zahlst nur für die verbleibenden Monate des Jahres."}, "nav_about": {"nl": "Over ons", "en": "About us", "de": "Über uns"}, "nav_membership": {"nl": "Lidmaatschap", "en": "Membership", "de": "Mitgliedschaft"}, "nav_track": {"nl": "De baan", "en": "The track", "de": "Die Strecke"}, "nav_location": {"nl": "Locatie", "en": "Location", "de": "Standort"}, "nav_photobook": {"nl": "Fotoboek", "en": "Photo book", "de": "Fotobuch"}, "nav_contact": {"nl": "Contact", "en": "Contact", "de": "Kontakt"}, "nav_join": {"nl": "Lid worden", "en": "Become a member", "de": "Mitglied werden"}, "footer_brand": {"nl": "Een gezellige vereniging voor liefhebbers van elektrisch aangedreven RC-auto\'s in de regio Zuid-Limburg. Voor beginners én ervaren rijders.", "en": "A friendly club for enthusiasts of electrically powered RC cars in the South Limburg region. For beginners and experienced riders alike.", "de": "Ein freundlicher Verein für Liebhaber von elektrisch angetriebenen RC-Autos in der Region Südlimburg. Für Anfänger und erfahrene Fahrer."}, "footer_nav": {"nl": "Navigatie", "en": "Navigation", "de": "Navigation"}, "footer_origin": {"nl": "Het ontstaan", "en": "Our history", "de": "Unsere Geschichte"}, "footer_media": {"nl": "Media", "en": "Media", "de": "Medien"}, "footer_photobook": {"nl": "Fotoboek", "en": "Photo book", "de": "Fotobuch"}, "footer_calendar": {"nl": "Activiteitenkalender", "en": "Events calendar", "de": "Veranstaltungskalender"}, "footer_join": {"nl": "Meedoen", "en": "Get involved", "de": "Mitmachen"}, "footer_become": {"nl": "Lid worden", "en": "Become a member", "de": "Mitglied werden"}, "footer_rules": {"nl": "Baanreglement", "en": "Track regulations", "de": "Streckenreglement"}, "footer_sponsor": {"nl": "Sponsoring", "en": "Sponsorship", "de": "Sponsoring"}, "footer_sponsors_title": {"nl": "Met dank aan onze sponsoren", "en": "With thanks to our sponsors", "de": "Mit Dank an unsere Sponsoren"}, "footer_credit": {"nl": "Website door", "en": "Website by", "de": "Website von"}, "form_name": {"nl": "Naam *", "en": "Name *", "de": "Name *"}, "form_email": {"nl": "E-mailadres", "en": "Email address", "de": "E-Mail-Adresse"}, "form_phone": {"nl": "Telefoonnummer", "en": "Phone number", "de": "Telefonnummer"}, "form_subject": {"nl": "Onderwerp", "en": "Subject", "de": "Betreff"}, "form_select": {"nl": "Selecteer een onderwerp...", "en": "Select a subject...", "de": "Betreff auswählen..."}, "form_opt1": {"nl": "Vraag over lidmaatschap", "en": "Question about membership", "de": "Frage zur Mitgliedschaft"}, "form_opt4": {"nl": "Sponsoring", "en": "Sponsorship", "de": "Sponsoring"}, "form_opt5": {"nl": "Overige vragen", "en": "Other questions", "de": "Sonstige Fragen"}, "form_message": {"nl": "Bericht *", "en": "Message *", "de": "Nachricht *"}, "form_send": {"nl": "Verstuur bericht →", "en": "Send message →", "de": "Nachricht senden →"}}', true) ?: [];

// Standaardinhoud voor de "Ontstaan"-pagina, alleen gebruikt zolang
// data/ontstaan.json nog niet bestaat. De Nederlandse tekst is de tekst die
// nu al vast in ontstaan.html staat (en dus standaard te zien is, want deze
// pagina toont NL zonder setLang aan te roepen); Engels en Duits komen uit
// hetzelfde i18n-blok in ontstaan.html. Zo verandert er niets aan wat
// bezoekers zien totdat iemand dit tabblad gebruikt.
$ontstaanStandaard = [
  'hero_sub' => [
    'nl' => "Van een vakantie met vrienden tot een bloeiende vereniging, dit is ons verhaal.",
    'en' => "From a holiday with friends to a thriving club, this is our story.",
    'de' => "Von einem Urlaub mit Freunden zu einem blühenden Verein, das ist unsere Geschichte.",
  ],
  'story_p1' => [
    'nl' => "In 2020 zijn de oorspronkelijke oprichters samen op vakantie geweest, waarbij ook enkele RC-auto's werden meegenomen. Het RC-virus werd al snel overgedragen, en kort na deze vakantie had iedereen al één of meerdere RC-auto's aangeschaft.",
    'en' => "In 2020 the original founders went on holiday together, bringing along a few RC cars. The RC virus spread quickly, and shortly after this holiday everyone already had one or more RC cars of their own.",
    'de' => "Im Jahr 2020 waren die ursprünglichen Gründer gemeinsam im Urlaub und hatten dabei auch einige RC-Autos mitgenommen. Das RC-Virus wurde schnell übertragen, und kurz nach diesem Urlaub hatte bereits jeder eines oder mehrere RC-Autos gekauft.",
  ],
  'story_p2' => [
    'nl' => "Tijdens de zoektocht naar een locatie om te kunnen rijden kwamen wij al snel uit op het grasveld bij sportpark Strijthagen (naast het MTB parcours). Al snel bleek dat hier meer hobbyisten met hun RC-auto hun rondje kwamen rijden.",
    'en' => "While searching for a location to drive, we soon found the grass field at Sportpark Strijthagen (next to the MTB course). It quickly became clear that other hobbyists were already coming there with their RC cars too.",
    'de' => "Bei der Suche nach einem Ort zum Fahren landeten wir schnell auf der Wiese beim Sportpark Strijthagen (neben der MTB-Strecke). Schnell stellte sich heraus, dass auch andere Hobbyisten hier mit ihren RC-Autos ihre Runden drehten.",
  ],
  'story_p3' => [
    'nl' => "Dit groeide al snel uit tot een wekelijkse samenkomst op zondag, en de groep hobbyisten én toeschouwers werd steeds groter.",
    'en' => "This soon grew into a weekly gathering on Sundays, and the group of hobbyists and spectators kept getting bigger.",
    'de' => "Daraus wuchs schnell ein wöchentliches Treffen sonntags, und die Gruppe von Hobbyisten und Zuschauern wurde immer größer.",
  ],
  'story_p4' => [
    'nl' => "We kregen ook steeds meer aandacht van de media, o.a. RTV Parkstad, Omroep Landgraaf en L1 hebben ons één of meerdere keren bezocht en hebben eraan meegeholpen dat onze activiteiten steeds meer bekendheid kregen.",
    'en' => "We also received more and more media attention. RTV Parkstad, Omroep Landgraaf, and L1 visited us on several occasions and helped our activities become increasingly well known.",
    'de' => "Wir bekamen auch immer mehr Aufmerksamkeit von den Medien, u.a. haben uns RTV Parkstad, Omroep Landgraaf und L1 mehrmals besucht und dazu beigetragen, dass unsere Aktivitäten immer bekannter wurden.",
  ],
  'story_p5' => [
    'nl' => "Door de steeds groter wordende belangstelling en omdat wij één en ander op een goede, gezellige en veilige manier wilden faciliteren hebben wij al snel onze vereniging RC045 opgericht.",
    'en' => "Because of the growing interest, and because we wanted to facilitate everything in a proper, friendly, and safe way, we soon founded our club RC045.",
    'de' => "Wegen des wachsenden Interesses und weil wir alles auf eine gute, gesellige und sichere Weise gestalten wollten, gründeten wir bald unseren Verein RC045.",
  ],
  'story_p6' => [
    'nl' => "In 2022 kregen we de mogelijkheid om een mooi stukje terrein in Eygelshoven te huren. Na heel hard ploeteren konden we daar eindelijk onze RC-baan realiseren.",
    'en' => "In 2022 we got the opportunity to rent a beautiful piece of land in Eygelshoven. After a lot of hard work, we were finally able to build our RC track there.",
    'de' => "Im Jahr 2022 bekamen wir die Möglichkeit, ein schönes Stück Gelände in Eygelshoven zu mieten. Nach harter Arbeit konnten wir dort endlich unsere RC-Strecke verwirklichen.",
  ],
  'story_p7' => [
    'nl' => "Nog steeds voegen we optimalisaties en veranderingen door aan onze baan, het is nooit af.",
    'en' => "We are still adding improvements and changes to our track, it is never truly finished.",
    'de' => "Wir fügen unserer Strecke noch immer Verbesserungen und Änderungen hinzu, sie ist nie wirklich fertig.",
  ],
];

// Standaardinhoud voor het Baanreglement, alleen gebruikt zolang
// data/baanreglement.json nog niet bestaat. De Nederlandse tekst is exact de
// tekst die nu al vast in baanreglement.html staat; Engels en Duits komen uit
// hetzelfde i18n-blok. Per artikel is de inhoud (voorheen losse bullets/
// subartikelen/alinea's) samengevoegd tot een lopende tekst, met een lege
// regel tussen elk onderdeel: dat wordt bij het opslaan weer als losse
// alinea's op de pagina getoond.
$baanreglementStandaard = [
  'hero_sub' => [
    'nl' => "Dit reglement is in het leven geroepen om onze hobby veilig uit te oefenen. Met het betreden van het RC045-terrein ga je akkoord met de inhoud van dit reglement.",
    'en' => "These regulations were created to ensure our hobby can be practised safely. By entering the RC045 grounds, you agree to the contents of these regulations.",
    'de' => "Dieses Reglement wurde erstellt, um unser Hobby sicher ausüben zu können. Mit dem Betreten des RC045-Geländes stimmst du dem Inhalt dieses Reglements zu.",
  ],
  'intro_bold' => [
    'nl' => "Belangrijk:",
    'en' => "Important:",
    'de' => "Wichtig:",
  ],
  'intro_text' => [
    'nl' => "Er zal niet worden gediscussieerd over de inhoud van dit reglement. Het niet correct opvolgen van deze regels kan leiden tot een reprimande van het bestuur in welke aard dan ook (e.e.a. conform de notarieel vastgelegde statuten).",
    'en' => "These regulations are not open for discussion. Failure to comply may result in a reprimand from the board in any form (in accordance with the notarially recorded statutes).",
    'de' => "Über den Inhalt dieses Reglements wird nicht diskutiert. Die Nichteinhaltung dieser Regeln kann zu einer Abmahnung durch den Vorstand jeglicher Art führen (gemäß den notariell festgelegten Statuten).",
  ],
  'a1_title' => [
    'nl' => "Openingstijden van de baan",
    'en' => "Track Opening Hours",
    'de' => "Öffnungszeiten der Strecke",
  ],
  'a1_body' => [
    'nl' => 'De actuele openingstijden van de baan worden op de website van RC045 gepubliceerd en kunnen indien nodig worden aangepast. De openingstijden voor het betreffende weekend worden uiterlijk vrijdag om 20.00 uur bekendgemaakt.

De woensdagavond is uitsluitend toegankelijk voor leden van RC045.

Wanneer bij een openingstijd ‘tot einde’ wordt vermeld, betekent dit dat het RC045-terrein bij onvoldoende animo eerder kan worden gesloten door de aanwezige sleutelhouder. Een dergelijke sluiting wordt ook via de WhatsApp-groep van RC045 gecommuniceerd.

Bij slecht weer of andere onvoorziene omstandigheden kan het RC045-terrein geheel of gedeeltelijk gesloten blijven. De beslissing hierover wordt genomen door het bestuur van RC045. Controleer daarom voor vertrek altijd de actuele informatie op de website.',
    'en' => 'The current opening hours of the track are published on the RC045 website and may be adjusted when necessary. The opening hours for the relevant weekend will be announced no later than Friday at 20:00.

Wednesday evenings are exclusively for RC045 members.

When an opening time states ‘until closing’, this means that the RC045 premises may close earlier if there is insufficient attendance. The decision to close will be made by the key holder present at the premises and will also be communicated via the RC045 WhatsApp group.

In case of bad weather or other unforeseen circumstances, the RC045 premises may remain fully or partially closed. This decision is made by the RC045 board. Therefore, always check the latest information on the website before travelling to the track.',
    'de' => 'Die aktuellen Öffnungszeiten der Strecke werden auf der RC045-Website veröffentlicht und können bei Bedarf angepasst werden. Die Öffnungszeiten für das jeweilige Wochenende werden spätestens freitags um 20:00 Uhr bekannt gegeben.

Mittwochabends ist die Strecke ausschließlich für Mitglieder von RC045 geöffnet.

Wenn bei einer Öffnungszeit „bis Ende“ angegeben ist, bedeutet dies, dass das RC045-Gelände bei zu geringer Beteiligung früher durch den anwesenden Schlüsselinhaber geschlossen werden kann. Eine solche Schließung wird ebenfalls über die WhatsApp-Gruppe von RC045 bekannt gegeben.

Bei schlechtem Wetter oder anderen unvorhergesehenen Umständen kann das RC045-Gelände ganz oder teilweise geschlossen bleiben. Die Entscheidung darüber trifft der Vorstand von RC045. Bitte prüfe daher vor der Anfahrt immer die aktuellen Informationen auf der Website.',
  ],
  'a2_title' => [
    'nl' => "Veiligheid",
    'en' => "Safety",
    'de' => "Sicherheit",
  ],
  'a2_body' => [
    'nl' => "2.1 Rijderspodium\n\nHet is verboden voor niet-rijders zich op de baan te bevinden. Alleen rijders bevinden zich op de baan (rijderspodium). Aangewezen personen met een hesje mogen op de baan zijn in de rol van baanmeester. Kijkers dienen achter de draad te staan.\n\nDe definitie van rijder is een lid of gastrijder die op dat moment een RC-auto bestuurt.\n\n2.2 Jumptrack\n\nBij de jumptrack mogen alleen aangewezen personen rondom de schans staan. Rijders staan altijd aan de voorzijde. Indien er een auto beschadigd of stuk gaat zal deze door een baanmeester worden opgehaald. Wanneer een baanmeester een signaal geeft of gaat bewegen stopt iedereen met rijden.\n\n2.3 Crawlerbaan\n\nBij en op de crawlerbaan mogen rijders langs het parcours lopen. Kijkers blijven op een gepaste afstand. Wanneer de rijder aangeeft dat iemand opzij moet of van een hindernis af moet dient men hier gehoor aan te geven.\n\nDe crawlerbaan is hoofdzakelijk alleen toegankelijk voor leden en gastrijders. Zonder toestemming van het bestuur is het betreden van de crawlerbaan verboden. Op de crawlerbaan is het alleen toegestaan om met crawler auto's deel te nemen. Kraton, X-Max en dergelijke zijn hier niet toegestaan.\n\n2.4 Accu's en apparatuur\n\nAuto's moeten technisch goed functioneren en alles moet goed vastzitten. Accu's worden buiten geladen en niet in de kantine of op het rijderspodium. Laad-apparatuur is veilig en voorzien van een goed functionerende stekker en stekkermaterialen.\n\n2.5 Rijden op asfalt\n\nHet is niet toegestaan buiten de baan op het asfalt te rijden. Voor het hekwerk gedoseerd rijden met veiligheid op de eerste plaats (alleen rechtstreeks van de baan naar werktafel of v.v.).\n\n2.6 Aansprakelijkheid\n\nHet betreden van het RC045-terrein is op eigen risico. Wij zijn niet aansprakelijk voor diefstal of andere schades aan bv. auto's of materiaal. Dit geldt ook bij schades die onderling veroorzaakt worden op en rondom de banen.\n\n2.7 Verantwoordelijkheid gasten\n\nIeder lid is verantwoordelijk voor het gedrag en de acties van zijn/haar gasten en/of bezoekers.",
    'en' => "2.1 Driver's Platform\n\nNon-drivers are not permitted on the track. Only drivers are allowed on the track (driver's platform). Designated persons wearing a vest may be on the track in the role of track marshal. Spectators must stand behind the barrier.\n\nA driver is defined as a member or guest rider who is actively controlling an RC car at that moment.\n\n2.2 Jump Track\n\nOnly designated persons may stand around the jump ramp. Drivers always stand at the front. If a car is damaged or breaks down, it will be retrieved by a track marshal. When a track marshal gives a signal or begins to move, everyone must stop driving.\n\n2.3 Crawler Track\n\nDrivers may walk alongside the crawler course. Spectators must keep a safe distance. When a driver indicates that someone needs to move aside or step away from an obstacle, this must be respected.\n\nThe crawler track is primarily accessible to members and guest riders only, for safety reasons due to the rough terrain. Entering the crawler track without permission from the board is prohibited. Only crawler vehicles may participate on the crawler track. Kraton, X-Max and similar vehicles are not permitted here.\n\n2.4 Batteries and Equipment\n\nVehicles must be in good technical working order and everything must be securely fastened. Batteries are charged outside only, not in the canteen or on the driver's platform. Charging equipment must be safe and fitted with a properly functioning plug and connector.\n\n2.5 Riding on Asphalt\n\nIt is not permitted to ride on the asphalt outside the designated track area. Near the fence, ride carefully with safety as the top priority (only directly from the track to the work table or vice versa).\n\n2.6 Liability\n\nEntering the RC045 grounds is at your own risk. We are not liable for theft or other damage to vehicles or equipment. This also applies to damage caused between participants on and around the tracks.\n\n2.7 Responsibility for Guests\n\nEach member is responsible for the behaviour and actions of their guests and/or visitors.",
    'de' => "2.1 Fahrerpodium\n\nEs ist Nicht-Fahrern verboten, sich auf der Strecke aufzuhalten. Nur Fahrer befinden sich auf der Strecke (Fahrerpodium). Ausgewiesene Personen mit einer Weste dürfen als Streckenmarschall auf der Strecke sein. Zuschauer müssen hinter der Absperrung stehen.\n\nAls Fahrer gilt ein Mitglied oder Gastfahrer, der zu diesem Zeitpunkt ein RC-Auto steuert.\n\n2.2 Sprungstrecke\n\nBei der Sprungstrecke dürfen nur ausgewiesene Personen rund um die Rampe stehen. Fahrer stehen immer an der Vorderseite. Wenn ein Auto beschädigt wird oder kaputt geht, wird es von einem Streckenmarschall abgeholt. Wenn ein Streckenmarschall ein Signal gibt oder sich bewegt, hört jeder mit dem Fahren auf.\n\n2.3 Crawler-Strecke\n\nAuf und an der Crawler-Strecke dürfen Fahrer entlang des Parcours laufen. Zuschauer halten einen angemessenen Abstand. Wenn der Fahrer angibt, dass jemand zur Seite treten oder von einem Hindernis absteigen soll, muss dem Folge geleistet werden.\n\nDie Crawler-Strecke ist hauptsächlich nur für Mitglieder und Gastfahrer zugänglich. Das Betreten der Crawler-Strecke ohne Genehmigung des Vorstands ist verboten. Auf der Crawler-Strecke dürfen nur Crawler-Fahrzeuge teilnehmen. Kraton, X-Max und ähnliche sind hier nicht erlaubt.\n\n2.4 Akkus und Ausrüstung\n\nFahrzeuge müssen technisch einwandfrei funktionieren und alles muss fest sitzen. Akkus werden nur draußen geladen, nicht in der Kantine oder auf dem Fahrerpodium. Ladegeräte sind sicher und mit einem einwandfrei funktionierenden Stecker und Steckermaterial ausgestattet.\n\n2.5 Fahren auf Asphalt\n\nEs ist nicht erlaubt, außerhalb der Strecke auf dem Asphalt zu fahren. Vor dem Zaun dosiert fahren mit Sicherheit an erster Stelle (nur direkt von der Strecke zum Werktisch oder umgekehrt).\n\n2.6 Haftung\n\nDas Betreten des RC045-Geländes erfolgt auf eigene Gefahr. Wir haften nicht für Diebstahl oder andere Schäden an z.B. Fahrzeugen oder Material. Dies gilt auch für Schäden, die gegenseitig auf und um die Strecken verursacht werden.\n\n2.7 Verantwortung für Gäste\n\nJedes Mitglied ist verantwortlich für das Verhalten und die Handlungen seiner Gäste und/oder Besucher.",
  ],
  'a3_title' => [
    'nl' => "Gastrijders",
    'en' => "Guest Riders",
    'de' => "Gastfahrer",
  ],
  'a3_body' => [
    'nl' => "Een gastrijder moet zich altijd aanmelden bij een bestuurslid of sleuteldrager. De baanregels moeten worden besproken met de gastrijder. Ieder verenigingslid moet zorgdragen dat een gastrijder op de hoogte is van de geldende regels. Gastrijders krijgen het reglement.",
    'en' => "A guest rider must always check in with a board member or key holder. The track rules must be discussed with the guest rider. Every club member must ensure that a guest rider is informed of the applicable rules. Guest riders will receive a copy of the regulations.",
    'de' => "Ein Gastfahrer muss sich immer bei einem Vorstandsmitglied oder Schlüsselträger anmelden. Die Streckenregeln müssen mit dem Gastfahrer besprochen werden. Jedes Vereinsmitglied muss dafür sorgen, dass ein Gastfahrer über die geltenden Regeln informiert ist. Gastfahrer erhalten das Reglement.",
  ],
  'a4_title' => [
    'nl' => "Baanmeester",
    'en' => "Track Marshal",
    'de' => "Streckenmarschall",
  ],
  'a4_body' => [
    'nl' => "De aangewezen baanmeester is herkenbaar door een oranje hesje en heeft op het moment dat hij op de baan staat de leiding. Dat wil zeggen dat alles wat hij zegt moet worden uitgevoerd. Het stil leggen van een race dient per direct opgevolgd te worden.\n\nDe baanmeester kan uit meerdere personen bestaan. Het is een roulerende taak en ieder lid zal zijn rol hierin nemen.",
    'en' => "The designated track marshal is recognisable by an orange vest and has authority when on the track. This means that everything they say must be followed. Stopping a race must be complied with immediately.\n\nThere may be multiple track marshals. It is a rotating role and every member will take on this responsibility.",
    'de' => "Der ausgewiesene Streckenmarschall ist an einer orangenen Weste erkennbar und hat die Leitung, wenn er sich auf der Strecke befindet. Das bedeutet, dass alles, was er sagt, ausgeführt werden muss. Das Stoppen eines Rennens muss sofort befolgt werden.\n\nEs kann mehrere Streckenmarschälle geben. Es ist eine rotierende Aufgabe und jedes Mitglied wird seine Rolle darin übernehmen.",
  ],
  'a5_title' => [
    'nl' => "Rijden en gebruik van brandstof",
    'en' => "Driving and Fuel Use",
    'de' => "Fahren und Kraftstoffnutzung",
  ],
  'a5_body' => [
    'nl' => "Nitro en benzine zijn niet toegestaan. Alleen elektrisch aangedreven voertuigen zijn welkom op ons terrein.",
    'en' => "Nitro and petrol are not permitted. Only electrically powered vehicles are welcome on our grounds.",
    'de' => "Nitro und Benzin sind nicht erlaubt. Nur elektrisch angetriebene Fahrzeuge sind auf unserem Gelände willkommen.",
  ],
  'a6_title' => [
    'nl' => "Verdovende middelen",
    'en' => "Narcotics",
    'de' => "Betäubungsmittel",
  ],
  'a6_body' => [
    'nl' => "Het is verboden op en rond de baan verdovende middelen te gebruiken.",
    'en' => "The use of narcotics on and around the track is prohibited.",
    'de' => "Es ist verboten, auf und um die Strecke Betäubungsmittel zu verwenden.",
  ],
  'a7_title' => [
    'nl' => "Roken en drank",
    'en' => "Smoking and Alcohol",
    'de' => "Rauchen und Alkohol",
  ],
  'a7_body' => [
    'nl' => "Het is niet toegestaan te roken of te drinken op de baan. Alcoholhoudende dranken zijn tijdens openingstijden verboden op het gehele terrein.",
    'en' => "Smoking and drinking on the track are not permitted. Alcoholic beverages are prohibited on the entire grounds during opening hours.",
    'de' => "Es ist nicht erlaubt, auf der Strecke zu rauchen oder zu trinken. Alkoholische Getränke sind während der Öffnungszeiten auf dem gesamten Gelände verboten.",
  ],
  'a8_title' => [
    'nl' => "Opruimen",
    'en' => "Tidying Up",
    'de' => "Aufräumen",
  ],
  'a8_body' => [
    'nl' => "8.1 Terrein netjes houden\n\nIeder lid en gastrijder verplicht zich tot het opruimen en netjes houden van ons terrein. Zwerfafval moet worden voorkomen, waar nodig ruimen we dit met z'n allen op. Afval doen we scheiden in de daarvoor aangewezen prullenbakken en zakken. De kantine wordt door iedereen opgeruimd en schoongemaakt.\n\n8.2 Chemisch afval\n\nElke rijder is verplicht milieubelastende materialen mee naar huis te nemen en zelf in te leveren als chemisch afval (bv. een defecte accu).",
    'en' => "8.1 Keeping the Grounds Tidy\n\nEvery member and guest rider is obliged to help clean up and keep our grounds tidy. Litter must be avoided and we all clean it up together when needed. Waste is separated into the designated bins and bags. The canteen is cleaned up by everyone.\n\n8.2 Chemical Waste\n\nEvery rider is obliged to take environmentally hazardous materials home and dispose of them as chemical waste themselves (e.g. a defective battery).",
    'de' => "8.1 Gelände sauber halten\n\nJedes Mitglied und jeder Gastfahrer verpflichtet sich, unser Gelände aufzuräumen und sauber zu halten. Müll muss vermieden werden; wenn nötig, räumen wir ihn gemeinsam auf. Abfall wird in die dafür vorgesehenen Müllbehälter und Säcke getrennt. Die Kantine wird von allen aufgeräumt und gereinigt.\n\n8.2 Chemischer Abfall\n\nJeder Fahrer ist verpflichtet, umweltbelastende Materialien mit nach Hause zu nehmen und selbst als Sondermüll zu entsorgen (z.B. ein defekter Akku).",
  ],
  'a9_title' => [
    'nl' => "AVG",
    'en' => "GDPR",
    'de' => "DSGVO",
  ],
  'a9_body' => [
    'nl' => "Bij het betreden van het RC045-terrein stem je in met het delen van foto's en video's op social media.",
    'en' => "By entering the RC045 grounds, you consent to the sharing of photos and videos on social media.",
    'de' => "Mit dem Betreten des RC045-Geländes stimmst du der Veröffentlichung von Fotos und Videos in sozialen Medien zu.",
  ],
  'a10_title' => [
    'nl' => "Het bestuur",
    'en' => "The Board",
    'de' => "Der Vorstand",
  ],
  'a10_body' => [
    'nl' => "Indien er een geschil ontstaat over bovengenoemde regels moet dit met het bestuur worden besproken. Een uitspraak van het bestuur is bindend en niet discutabel. Eventuele wijzigingen worden gecommuniceerd via de nieuwsbrief of in een ALV.",
    'en' => "If a dispute arises regarding the above rules, it must be discussed with the board. A decision by the board is binding and not open to discussion. Any changes will be communicated via the newsletter or at a general meeting.",
    'de' => "Wenn ein Streit über die oben genannten Regeln entsteht, muss dieser mit dem Vorstand besprochen werden. Eine Entscheidung des Vorstands ist bindend und nicht diskutierbar. Etwaige Änderungen werden über den Newsletter oder in einer Hauptversammlung kommuniziert.",
  ],
];

// Standaardinhoud voor de hele bedankt-pagina, alleen gebruikt zolang
// data/bedankt.json nog niet bestaat. iban_number is bewust geen nl/en/de-
// veld: een IBAN-nummer is niet vertaalbaar, dus die is overal hetzelfde.
// iban_ref bevat het token {jaar}: dat wordt op de pagina zelf automatisch
// vervangen door het actuele contributiejaar uit de Rekentabel, dus dat
// token moet blijven staan in de tekst.
$bedanktStandaard = [
  'iban_number' => 'NL51 RABO 0367 6153 63',
  'title' => [
    'nl' => "Welkom bij RC045!",
    'en' => "Welcome to RC045!",
    'de' => "Willkommen bei RC045!",
  ],
  'sub' => [
    'nl' => "Je aanmelding is ontvangen. Het bestuur neemt zo snel mogelijk contact met je op om je aanmelding te bevestigen.",
    'en' => "Your registration has been received. The board will contact you as soon as possible to confirm your membership.",
    'de' => "Deine Anmeldung ist eingegangen. Der Vorstand wird sich so schnell wie möglich bei dir melden, um deine Mitgliedschaft zu bestätigen.",
  ],
  'stap1' => [
    'nl' => "Maak de contributie over via onderstaande gegevens. Vermeld je naam duidelijk.",
    'en' => "Transfer the membership fee using the details below. Include your name clearly.",
    'de' => "Überweise den Mitgliedsbeitrag mit den untenstehenden Angaben. Gib deinen Namen deutlich an.",
  ],
  'stap2' => [
    'nl' => "Wacht op bevestiging van het bestuur per e-mail of WhatsApp.",
    'en' => "Wait for confirmation from the board by email or WhatsApp.",
    'de' => "Warte auf die Bestätigung des Vorstands per E-Mail oder WhatsApp.",
  ],
  'stap3' => [
    'nl' => "Je bent van harte welkom op onze baan zodra je lidmaatschap is bevestigd!",
    'en' => "You are very welcome at our track once your membership is confirmed!",
    'de' => "Du bist herzlich willkommen auf unserer Strecke, sobald deine Mitgliedschaft bestätigt ist!",
  ],
  'iban_title' => [
    'nl' => "Betalingsgegevens",
    'en' => "Payment details",
    'de' => "Zahlungsdaten",
  ],
  'iban_name' => [
    'nl' => "T.n.v. RC045",
    'en' => "In the name of RC045",
    'de' => "Auf den Namen RC045",
  ],
  'iban_ref' => [
    'nl' => "Vermeld bij overboeking: voornaam + achternaam + \"contributie RC045 {jaar}\"",
    'en' => "Reference: first name + last name + \"contributie RC045 {jaar}\"",
    'de' => "Verwendungszweck: Vorname + Nachname + \"contributie RC045 {jaar}\"",
  ],
  'btn_home' => [
    'nl' => "🏠 Naar de hoofdpagina",
    'en' => "🏠 Go to the homepage",
    'de' => "🏠 Zur Hauptseite",
  ],
  'btn_location' => [
    'nl' => "📍 Hoe kom ik er?",
    'en' => "📍 How to find us?",
    'de' => "📍 Wie komme ich hin?",
  ],
];
// Velden van het tabblad Bedankt-pagina (los van iban_number hierboven, die
// blijft een enkel veld). Zelfde opzet als $homepageVelden.
$bedanktVelden = [
  'title' => ['Titel bovenaan de pagina ("Welkom bij RC045!")', 'tekst'],
  'sub' => ['Introtekst onder de titel', 'blok'],
  'stap1' => ['Stap 1', 'blok'],
  'stap2' => ['Stap 2', 'blok'],
  'stap3' => ['Stap 3', 'blok'],
  'iban_title' => ['Titel boven de betaalgegevens ("Betalingsgegevens")', 'tekst'],
  'iban_name' => ['Naam rekeninghouder', 'tekst'],
  'iban_ref' => ['Betalingsreferentie (laat {jaar} erin staan, dat wordt automatisch het huidige contributiejaar)', 'blok'],
  'btn_home' => ['Knoptekst "Naar de hoofdpagina"', 'tekst'],
  'btn_location' => ['Knoptekst "Hoe kom ik er?"', 'tekst'],
];
$bedanktGroepen = [
  'Introductie' => ['title', 'sub', 'stap1', 'stap2', 'stap3'],
  'Betaalgegevens' => ['iban_title', 'iban_name', 'iban_ref'],
  'Knoppen' => ['btn_home', 'btn_location'],
];

// Standaardinhoud voor de pagina aanmelden.html, alleen gebruikt zolang
// data/aanmelden.json nog niet bestaat. contrib.title bevat het {jaar}-token,
// dat wordt op de pagina automatisch vervangen door het actuele
// contributiejaar, net als bij de bedankt-pagina.
$aanmeldenStandaard = [
  'hero_label' => [
    'nl' => "Lidmaatschap",
    'en' => "Membership",
    'de' => "Mitgliedschaft",
  ],
  'hero_title' => [
    'nl' => "Aanmelden als lid",
    'en' => "Register as a member",
    'de' => "Als Mitglied anmelden",
  ],
  'hero_sub' => [
    'nl' => "Vul het formulier in om je aan te melden bij RC045. Na ontvangst nemen we zo snel mogelijk contact met je op.",
    'en' => "Fill in the form to register with RC045. We will contact you as soon as possible after receiving your registration.",
    'de' => "Fülle das Formular aus, um dich bei RC045 anzumelden. Nach Eingang melden wir uns so schnell wie möglich bei dir.",
  ],
  'contrib_title' => [
    'nl' => "Jouw contributie {jaar}",
    'en' => "Your membership fee {jaar}",
    'de' => "Dein Mitgliedsbeitrag {jaar}",
  ],
  'contrib_placeholder' => [
    'nl' => "Vul je geboortedatum in om de contributie te berekenen.",
    'en' => "Enter your date of birth to calculate the membership fee.",
    'de' => "Gib dein Geburtsdatum ein, um den Mitgliedsbeitrag zu berechnen.",
  ],
  'form_personal' => [
    'nl' => "Persoonsgegevens",
    'en' => "Personal details",
    'de' => "Persönliche Daten",
  ],
  'form_address' => [
    'nl' => "Adresgegevens",
    'en' => "Address details",
    'de' => "Adressdaten",
  ],
  'form_contact' => [
    'nl' => "Contactgegevens",
    'en' => "Contact details",
    'de' => "Kontaktdaten",
  ],
  'form_agreement' => [
    'nl' => "Akkoordverklaring",
    'en' => "Declaration of agreement",
    'de' => "Einverständniserklärung",
  ],
  'success_title' => [
    'nl' => "✅ Aanmelding ontvangen! We nemen zo snel mogelijk contact met je op.",
    'en' => "✅ Registration received! We will contact you as soon as possible.",
    'de' => "✅ Anmeldung erhalten! Wir werden uns so schnell wie möglich bei dir melden.",
  ],
  'success_sub' => [
    'nl' => "Vergeet niet de contributie over te maken via de betalingsinstructies hierboven.",
    'en' => "Don't forget to transfer the membership fee using the payment instructions above.",
    'de' => "Vergiss nicht, den Mitgliedsbeitrag gemäß den obigen Zahlungsanweisungen zu überweisen.",
  ],
  'faq_title' => [
    'nl' => "Veelgestelde vragen",
    'en' => "Frequently asked questions",
    'de' => "Häufig gestellte Fragen",
  ],
];
$aanmeldenVelden = [
  'hero_label' => ['Hero: sectielabel ("Lidmaatschap")', 'tekst'],
  'hero_title' => ['Hero: titel', 'tekst'],
  'hero_sub' => ['Hero: ondertitel', 'blok'],
  'contrib_title' => ['Titel boven de contributie-berekening (laat {jaar} erin staan)', 'tekst'],
  'contrib_placeholder' => ['Tekst voordat een geboortedatum is ingevuld', 'blok'],
  'form_personal' => ['Formulier: kop "Persoonsgegevens"', 'tekst'],
  'form_address' => ['Formulier: kop "Adresgegevens"', 'tekst'],
  'form_contact' => ['Formulier: kop "Contactgegevens"', 'tekst'],
  'form_agreement' => ['Formulier: kop "Akkoordverklaring"', 'tekst'],
  'success_title' => ['Melding na versturen: titel', 'blok'],
  'success_sub' => ['Melding na versturen: subtekst', 'blok'],
  'faq_title' => ['Titel boven de veelgestelde vragen', 'tekst'],
];
$aanmeldenGroepen = [
  'Hero' => ['hero_label', 'hero_title', 'hero_sub'],
  'Contributie' => ['contrib_title', 'contrib_placeholder'],
  'Formulier (koppen)' => ['form_personal', 'form_address', 'form_contact', 'form_agreement'],
  'Bevestiging na versturen' => ['success_title', 'success_sub'],
  'FAQ' => ['faq_title'],
];

// Standaardinhoud voor de ondertitel op media.html en fotoboek.html, alleen
// gebruikt zolang de bijbehorende bestanden nog niet bestaan. Los van
// media.json/fotoboek.json (de lijst met items/albums), want dit is puur de
// vaste tekst bovenaan de pagina.
$mediaTekstStandaard = [
  'hero_sub' => [
    'nl' => "Voordat wij een eigen baan hadden, gaf de media ons veelvuldig aandacht. Hier vind je een overzicht van die berichtgevingen.",
    'en' => "Before we had our own track, the media paid us frequent attention. Here you will find an overview of those features.",
    'de' => "Bevor wir eine eigene Strecke hatten, schenkten uns die Medien häufig Aufmerksamkeit. Hier findest du eine Übersicht dieser Berichterstattungen.",
  ],
];
$fotoboekTekstStandaard = [
  'hero_sub' => [
    'nl' => "Foto's van onze evenementen en banen, gerangschikt per album. Klik op een album om de foto's te bekijken.",
    'en' => "Photos from our events and tracks, sorted by album. Click an album to view the photos.",
    'de' => "Fotos von unseren Veranstaltungen und Strecken, sortiert nach Album. Klicke auf ein Album, um die Fotos anzusehen.",
  ],
];

// Zet een volledig jaarbedrag om in de pro-rata maandtabel die de rekentabel
// en de contributiecalculator op aanmelden.html gebruiken: bij inschrijving
// in maand $m betaal je (12 - $m) twaalfde deel van het jaarbedrag, naar
// hele euro's afgerond. December (maand 12) levert altijd 0 op en wordt
// door de aanroepende code als speciaal geval behandeld (alleen
// inschrijfkosten, contributie volgend jaar pas overmaken).


// Keuzelijst voor de tijd-dropdowns bij woensdag/zaterdag/zondag: elk half uur van
// 06:00 tot 22:00. Ruim genoeg voor elke realistische openingstijd, met een
// vinkje-vriendelijk aantal opties (geen losse minuten).
function contactTijdOpties() {
  $opties = [];
  for ($minuten = 6 * 60; $minuten <= 22 * 60; $minuten += 30) {
    $opties[] = sprintf('%02d:%02d', intdiv($minuten, 60), $minuten % 60);
  }
  return $opties;
}

// De standen die een dag kan hebben. De sleutel gaat naar
// data/contact.json, het label staat in het keuzemenu hieronder. De tekst die
// bezoekers zien staat niet hier maar in de vertalingen van index.html, zodat
// de melding automatisch in het Nederlands, Engels en Duits klopt.
function contactStatusOpties() {
  return [
    'open' => 'Open (normale tijden)',
    'animo' => '🤝 Alleen bij voldoende animo',
    'animo_leden' => '🤝 Alleen bij voldoende animo, en alleen voor leden',
    'leden' => '👥 Alleen open voor leden',
    'gesloten' => '⛔ Gesloten',
    'onderhoud' => '🔧 Gesloten i.v.m. onderhoud',
    'weer' => '🌧️ Gesloten i.v.m. slecht weer',
  ];
}

// Standen die bij de vaste opzet van een dag horen en dus niet vanzelf mogen
// vervallen. De overige standen zijn tijdelijke afwijkingen en krijgen wel een
// vervalmoment mee.
function contactVasteStanden() {
  return ['open', 'animo', 'animo_leden'];
}

// Het moment waarop een tijdelijke melding vanzelf vervalt: de eerstvolgende
// keer dat die dag zich voordoet, om 20:00. Wordt de stand op zaterdagochtend
// gezet, dan geldt hij diezelfde zaterdag; wordt hij zaterdagavond na achten
// gezet, dan geldt hij de zaterdag erna. Er wordt een absolute tijd met
// tijdzone weggeschreven, zodat de website hem los van de tijdzone van de
// bezoeker goed vergelijkt.
function contactVervalMoment($dag) {
  $engelseDagen = ['woensdag' => 'wednesday', 'zaterdag' => 'saturday', 'zondag' => 'sunday'];
  // Het uur waarop de melding vervalt ligt per dag net na sluitingstijd.
  // Woensdag loopt tot 22:00 en krijgt daarom een later moment dan het weekend.
  $vervalUur = ['woensdag' => 23, 'zaterdag' => 20, 'zondag' => 20];
  if (!isset($engelseDagen[$dag])) return '';
  $uur = $vervalUur[$dag];
  $tz = new DateTimeZone('Europe/Amsterdam');
  $nu = new DateTime('now', $tz);
  $verval = (clone $nu)->modify('this ' . $engelseDagen[$dag])->setTime($uur, 0);
  if ($verval <= $nu) {
    $verval = (clone $nu)->modify('next ' . $engelseDagen[$dag])->setTime($uur, 0);
  }
  return $verval->format('c');
}

// Leesbare weergave van het vervalmoment voor in het beheerscherm.
function contactVervalTekst($iso) {
  if (!$iso) return '';
  try {
    $moment = new DateTime($iso);
    $moment->setTimezone(new DateTimeZone('Europe/Amsterdam'));
  } catch (Exception $e) {
    return '';
  }
  $dagen = ['Sun' => 'zondag', 'Mon' => 'maandag', 'Tue' => 'dinsdag', 'Wed' => 'woensdag', 'Thu' => 'donderdag', 'Fri' => 'vrijdag', 'Sat' => 'zaterdag'];
  $maanden = [1 => 'januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
  return ($dagen[$moment->format('D')] ?? '') . ' ' . $moment->format('j') . ' ' . ($maanden[(int)$moment->format('n')] ?? '') . ' om ' . $moment->format('H:i');
}



function kort($tekst, $max) {
  $tekst = trim($tekst);
  return function_exists('mb_substr') ? mb_substr($tekst, 0, $max) : substr($tekst, 0, $max);
}

// Vult $standaard aan met wat er al is opgeslagen in $opgeslagen, maar laat
// een leeg opgeslagen veld NOOIT een goedgevulde standaardtekst overschrijven.
// array_merge() deed dat namelijk wel: als een veld ooit een keer leeg is
// opgeslagen (bv. omdat het formulier op dat moment nog niet compleet was),
// bleef het voorgoed leeg in beheer, ook nadat de standaardtekst in de code
// later weer goed gevuld werd. Dit is de fix voor dat probleem.
function vulStandaardAan($standaard, $opgeslagen) {
  if (!is_array($opgeslagen)) return $standaard;
  $resultaat = $standaard;
  foreach ($standaard as $sleutel => $default) {
    if (!array_key_exists($sleutel, $opgeslagen)) continue;
    $waarde = $opgeslagen[$sleutel];
    if (is_array($default)) {
      // Veld met nl/en/de: per taal apart bekijken, lege taal = standaard
      // erin laten staan in plaats van leegmaken.
      if (!is_array($waarde)) continue;
      foreach ($default as $taal => $standaardTekst) {
        if (isset($waarde[$taal]) && is_string($waarde[$taal]) && trim($waarde[$taal]) !== '') {
          $resultaat[$sleutel][$taal] = $waarde[$taal];
        }
      }
    } else {
      // Los tekstveld (bv. iban_number): alleen overnemen als niet leeg.
      if (is_string($waarde) && trim($waarde) !== '') {
        $resultaat[$sleutel] = $waarde;
      }
    }
  }
  return $resultaat;
}

// Datum tonen als dd-mm-jjjj in beheer-formulieren, opgeslagen blijft overal
// gewoon yyyy-mm-dd (ISO), want daar rekent de rest van de site (homepage,
// sortering, leeftijdsberekening) mee. Was eerst alleen voor Agenda, geldt nu
// voor elk datumveld in beheer.php: Nieuws, Media, Fotoboek, Leden.


// dd-mm-jjjj (of dd/mm/jjjj, of aaneengeschreven als 8 cijfers ddmmjjjj)
// uit een formulier terugzetten naar yyyy-mm-dd. Ongeldige of lege invoer
// wordt gewoon een lege datum.
function datumNaarIso($tekst) {
  $tekst = trim((string) $tekst);
  if (preg_match('#^(\d{2})[/-]?(\d{2})[/-]?(\d{4})$#', $tekst, $m)) {
    $dag = (int) $m[1]; $maand = (int) $m[2]; $jaar = (int) $m[3];
    if (checkdate($maand, $dag, $jaar)) {
      return sprintf('%04d-%02d-%02d', $jaar, $maand, $dag);
    }
  }
  return '';
}

// Zet een tijdgestempelde kopie van $pad in $backupMap voordat schrijfJson()
// de huidige inhoud overschrijft, en ruimt daarna oude kopieën van datzelfde
// bestand op (ouder dan $bewaardagen, met $maxPerBestand als hardstop).
// Stil falen (@) is bewust: een mislukte back-up mag het opslaan van de
// eigenlijke wijziging nooit blokkeren.
// maakDataBackup() staat in auth.php: schrijfGebruikers() heeft hem daar ook
// nodig en die functie hoort bij de inlogafhandeling.

function schrijfJson($pad, $data) {
  global $dataBackupMap, $dataBackupBewaardagen, $dataBackupMaxPerBestand;
  maakDataBackup($pad, $dataBackupMap, $dataBackupBewaardagen, $dataBackupMaxPerBestand);
  $map = dirname($pad);
  if (!is_dir($map)) {
    mkdir($map, 0755, true);
  }
  $inhoud = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  return file_put_contents($pad, $inhoud, LOCK_EX) !== false;
}

// Geeft de beschikbare back-ups van precies één bestand terug (nieuwste
// eerst), als array van ['bestand' => bestandsnaam zonder pad, 'tijd' =>
// unix-timestamp]. $basisnaam is bijv. "fotoboek.json", zonder pad.
function lijstDataBackups($backupMap, $basisnaam) {
  $bestanden = @glob($backupMap . '/*_' . $basisnaam);
  if ($bestanden === false) return [];
  $resultaat = [];
  foreach ($bestanden as $b) {
    $tijd = @filemtime($b);
    if ($tijd === false) continue;
    $resultaat[] = ['bestand' => basename($b), 'tijd' => $tijd];
  }
  usort($resultaat, function($a, $b) { return $b['tijd'] <=> $a['tijd']; });
  return $resultaat;
}

// Leest de afmetingen van een sponsorlogo dat al op de server staat. Die gaan
// mee in sponsors.json, zodat de website width/height op het <img> kan zetten
// en de footer niet verspringt terwijl de logo's laden. Bestaat het bestand
// niet (meer), dan komen er nullen uit en laat de website de attributen weg.
function sponsorLogoAfmetingen($bestandsnaam) {
  global $sponsorMap;
  if ($bestandsnaam === '') return ['width' => 0, 'height' => 0];
  $pad = $sponsorMap . '/' . $bestandsnaam;
  if (!is_file($pad)) return ['width' => 0, 'height' => 0];
  $info = @getimagesize($pad);
  if ($info === false) return ['width' => 0, 'height' => 0];
  return ['width' => (int) $info[0], 'height' => (int) $info[1]];
}

// Verwerkt (optioneel) een geüpload sponsorlogo. Zonder nieuw bestand blijft
// het huidige logo staan. Bij een nieuw bestand: alleen PNG/JPG/WEBP, max 1MB,
// en een echte afbeelding (gecontroleerd met getimagesize, niet alleen de
// bestandsnaam). Het logo krijgt altijd een vaste naam per slot, zodat een
// vervanging het oude bestand netjes overschrijft.
function verwerkSponsorLogo($bestandVeld, $slotIndex, $huidig) {
  global $sponsorMap;
  if (!isset($_FILES[$bestandVeld]) || $_FILES[$bestandVeld]['error'] === UPLOAD_ERR_NO_FILE) {
    // Geen nieuwe upload: afmetingen alsnog van het bestaande bestand lezen,
    // zodat oudere regels zonder die velden bij de eerstvolgende opslag
    // vanzelf worden aangevuld.
    return ['ok' => true, 'logo' => $huidig] + sponsorLogoAfmetingen($huidig);
  }
  $bestand = $_FILES[$bestandVeld];
  if ($bestand['error'] === UPLOAD_ERR_INI_SIZE || $bestand['error'] === UPLOAD_ERR_FORM_SIZE) {
    return ['ok' => false, 'fout' => 'logo is te groot.'];
  }
  if ($bestand['error'] !== UPLOAD_ERR_OK) {
    return ['ok' => false, 'fout' => 'uploaden van het logo is mislukt.'];
  }
  if ($bestand['size'] > 1024 * 1024) {
    return ['ok' => false, 'fout' => 'logo is groter dan 1 MB.'];
  }
  $info = @getimagesize($bestand['tmp_name']);
  if ($info === false) {
    return ['ok' => false, 'fout' => 'bestand is geen geldige afbeelding.'];
  }
  $extensies = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];
  if (!isset($extensies[$info[2]])) {
    return ['ok' => false, 'fout' => 'alleen PNG, JPG of WEBP toegestaan.'];
  }
  if (!is_dir($sponsorMap)) {
    mkdir($sponsorMap, 0755, true);
  }
  foreach (glob($sponsorMap . '/sponsor_' . $slotIndex . '.*') as $oud) {
    @unlink($oud);
  }
  $bestandsnaam = 'sponsor_' . $slotIndex . '.' . $extensies[$info[2]];
  if (!move_uploaded_file($bestand['tmp_name'], $sponsorMap . '/' . $bestandsnaam)) {
    return ['ok' => false, 'fout' => 'opslaan van het logo op de server is mislukt.'];
  }
  return ['ok' => true, 'logo' => $bestandsnaam, 'width' => (int) $info[0], 'height' => (int) $info[1]];
}

// ===== Fotoboek: albums en foto's =====

// Maakt van een titel een URL-veilige mapnaam, bijv. "ZomerBBQ 2026" -> "zomerbbq-2026".
function maakSlug($tekst) {
  $tekst = trim($tekst);
  if (function_exists('iconv')) {
    $vertaald = @iconv('UTF-8', 'ASCII//TRANSLIT', $tekst);
    if ($vertaald !== false) $tekst = $vertaald;
  }
  $tekst = strtolower($tekst);
  $tekst = preg_replace('/[^a-z0-9]+/', '-', $tekst);
  $tekst = trim($tekst, '-');
  return $tekst === '' ? 'album' : $tekst;
}

// Zorgt dat een slug uniek is tussen de al bestaande albums (voegt -2, -3, enz. toe).
function uniekeSlug($basisSlug, $bestaandeSlugs) {
  $slug = $basisSlug;
  $i = 2;
  while (in_array($slug, $bestaandeSlugs, true)) {
    $slug = $basisSlug . '-' . $i;
    $i++;
  }
  return $slug;
}

// Verkleint een GD-afbeelding naar max $maxBreedte, alleen indien nodig (nooit vergroten).
function fotoboekSchaalAf($bron, $breedte, $hoogte, $maxBreedte) {
  if ($breedte <= $maxBreedte) {
    $nieuw = imagecreatetruecolor($breedte, $hoogte);
    imagecopy($nieuw, $bron, 0, 0, 0, 0, $breedte, $hoogte);
    return $nieuw;
  }
  $factor = $maxBreedte / $breedte;
  $nieuweBreedte = $maxBreedte;
  $nieuweHoogte = (int) round($hoogte * $factor);
  $nieuw = imagecreatetruecolor($nieuweBreedte, $nieuweHoogte);
  imagecopyresampled($nieuw, $bron, 0, 0, 0, 0, $nieuweBreedte, $nieuweHoogte, $breedte, $hoogte);
  return $nieuw;
}

// Zet een klein, semi-transparant watermerk (RC045-logo + "rc045.nl") rechtsonder
// in de afbeelding. Alleen bedoeld voor de volledige (web) versie, niet de thumbnail.
function fotoboekZetWatermerk($afbeelding, $logoPad) {
  $breedte = imagesx($afbeelding);
  $hoogte  = imagesy($afbeelding);

  $logoHoogte = (int) max(18, min(36, round($hoogte * 0.035)));
  $padding    = (int) round($logoHoogte * 0.45);

  $logo = null;
  if ($logoPad && file_exists($logoPad)) {
    $logoBron = @imagecreatefrompng($logoPad);
    if ($logoBron) {
      $logoBreedteOrig = imagesx($logoBron);
      $logoHoogteOrig  = imagesy($logoBron);
      if ($logoHoogteOrig > 0) {
        $logoBreedte = (int) round($logoHoogte * ($logoBreedteOrig / $logoHoogteOrig));
        $logo = imagecreatetruecolor($logoBreedte, $logoHoogte);
        imagealphablending($logo, false);
        imagesavealpha($logo, true);
        $transparant = imagecolorallocatealpha($logo, 0, 0, 0, 127);
        imagefilledrectangle($logo, 0, 0, $logoBreedte, $logoHoogte, $transparant);
        imagealphablending($logo, true);
        imagecopyresampled($logo, $logoBron, 0, 0, 0, 0, $logoBreedte, $logoHoogte, $logoBreedteOrig, $logoHoogteOrig);
      }
      imagedestroy($logoBron);
    }
  }

  $tekst = 'rc045.nl';
  $lettergrootte = 3; // ingebouwd GD-lettertype, 1 t/m 5; 3 is klein en leesbaar
  $tekstBreedte = imagefontwidth($lettergrootte) * strlen($tekst);
  $tekstHoogte  = imagefontheight($lettergrootte);

  $logoBreedte = $logo ? imagesx($logo) : 0;
  $tussenruimte = $logo ? (int) round($padding * 0.6) : 0;
  $vlakBreedte = $logoBreedte + $tussenruimte + $tekstBreedte + $padding * 2;
  $vlakHoogte  = max($logoHoogte, $tekstHoogte) + $padding;

  $x2 = $breedte - $padding;
  $y2 = $hoogte - $padding;
  $x1 = (int) round($x2 - $vlakBreedte);
  $y1 = (int) round($y2 - $vlakHoogte);

  // subtiel donker vlak achter het watermerk, zodat het op elke foto leesbaar blijft
  imagealphablending($afbeelding, true);
  $vlakKleur = imagecolorallocatealpha($afbeelding, 20, 24, 15, 55);
  imagefilledrectangle($afbeelding, $x1, $y1, $x2, $y2, $vlakKleur);

  $middenY = (int) round(($y1 + $y2) / 2);
  $huidigeX = $x1 + $padding;

  if ($logo) {
    imagecopy($afbeelding, $logo, $huidigeX, $middenY - (int) round(imagesy($logo) / 2), 0, 0, imagesx($logo), imagesy($logo));
    $huidigeX += imagesx($logo) + $tussenruimte;
    imagedestroy($logo);
  }

  $wit = imagecolorallocate($afbeelding, 255, 255, 255);
  imagestring($afbeelding, $lettergrootte, $huidigeX, $middenY - (int) round($tekstHoogte / 2), $tekst, $wit);
}

// Verwerkt een geuploade foto: EXIF-rotatie corrigeren, verkleinen naar een
// volledige (web) versie en een thumbnail, optioneel watermerk toevoegen.
// Slaat beide versies op en geeft de opgeslagen breedte/hoogte terug (nodig
// voor de lightbox op de website).
function verwerkFotoboekFoto($tmpPad, $volledigPad, $thumbPad, $watermerkAan, $logoPad, $maxVolledig, $maxThumb) {
  $info = @getimagesize($tmpPad);
  if ($info === false) return ['ok' => false, 'fout' => 'bestand is geen geldige afbeelding.'];

  switch ($info[2]) {
    case IMAGETYPE_JPEG: $bron = @imagecreatefromjpeg($tmpPad); break;
    case IMAGETYPE_PNG:  $bron = @imagecreatefrompng($tmpPad); break;
    case IMAGETYPE_WEBP: $bron = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpPad) : false; break;
    default: $bron = false;
  }
  if (!$bron) return ['ok' => false, 'fout' => 'alleen JPG, PNG of WEBP toegestaan, of bestand kon niet worden geopend.'];

  // EXIF-rotatie corrigeren (mobiele foto's staan vaak "verkeerd om" in de bestandsdata).
  // Niet elke server heeft de exif-extensie aan staan, vandaar de function_exists-check.
  if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
    $exif = @exif_read_data($tmpPad);
    if ($exif && !empty($exif['Orientation'])) {
      if ($exif['Orientation'] === 3) { $gedraaid = imagerotate($bron, 180, 0); imagedestroy($bron); $bron = $gedraaid; }
      elseif ($exif['Orientation'] === 6) { $gedraaid = imagerotate($bron, -90, 0); imagedestroy($bron); $bron = $gedraaid; }
      elseif ($exif['Orientation'] === 8) { $gedraaid = imagerotate($bron, 90, 0); imagedestroy($bron); $bron = $gedraaid; }
    }
  }

  $breedte = imagesx($bron);
  $hoogte  = imagesy($bron);

  $volledig = fotoboekSchaalAf($bron, $breedte, $hoogte, $maxVolledig);
  if ($watermerkAan) fotoboekZetWatermerk($volledig, $logoPad);
  imagejpeg($volledig, $volledigPad, 82);
  $opgeslagenBreedte = imagesx($volledig);
  $opgeslagenHoogte  = imagesy($volledig);
  imagedestroy($volledig);

  $thumb = fotoboekSchaalAf($bron, $breedte, $hoogte, $maxThumb);
  imagejpeg($thumb, $thumbPad, 78);
  imagedestroy($thumb);

  imagedestroy($bron);

  return ['ok' => true, 'width' => $opgeslagenBreedte, 'height' => $opgeslagenHoogte];
}

// Slaat de in de browser gemaakte video-thumbnail (een los frame uit de video,
// als data-URL meegestuurd door JavaScript) op als volledige en thumb-versie,
// net als bij foto's. Geen watermerk: dit is geen upload van een foto, maar
// een automatisch gegrabt frame uit een video. Komt er geen (geldige)
// thumbnail binnen, dan slaat de video zelf gewoon toch op, alleen zonder
// voorbeeldbeeld; de website toont dan een generiek video-icoon.
function verwerkFotoboekVideoPoster($dataUrl, $volledigPad, $thumbPad, $maxVolledig, $maxThumb) {
  if (!preg_match('#^data:image/(jpeg|jpg|png);base64,(.+)$#s', $dataUrl, $match)) {
    return ['ok' => false, 'width' => 0, 'height' => 0];
  }
  $ruw = base64_decode($match[2], true);
  if ($ruw === false) return ['ok' => false, 'width' => 0, 'height' => 0];

  $bron = @imagecreatefromstring($ruw);
  if (!$bron) return ['ok' => false, 'width' => 0, 'height' => 0];

  $breedte = imagesx($bron);
  $hoogte  = imagesy($bron);

  $volledig = fotoboekSchaalAf($bron, $breedte, $hoogte, $maxVolledig);
  imagejpeg($volledig, $volledigPad, 82);
  $opgeslagenBreedte = imagesx($volledig);
  $opgeslagenHoogte  = imagesy($volledig);
  imagedestroy($volledig);

  $thumb = fotoboekSchaalAf($bron, $breedte, $hoogte, $maxThumb);
  imagejpeg($thumb, $thumbPad, 78);
  imagedestroy($thumb);

  imagedestroy($bron);

  return ['ok' => true, 'width' => $opgeslagenBreedte, 'height' => $opgeslagenHoogte];
}

// Zet alsnog een watermerk op een foto die al eerder (zonder watermerk) is
// geüpload. Werkt rechtstreeks op de al opgeslagen volledige (web) versie,
// er wordt niet opnieuw geschaald. De thumbnail blijft ongemoeid, net als bij
// nieuwe uploads. Kan niet ongedaan gemaakt worden: het origineel zonder
// watermerk is niet bewaard.
function fotoboekWatermerkBestaandeFoto($volledigPad, $logoPad) {
  $info = @getimagesize($volledigPad);
  if ($info === false || $info[2] !== IMAGETYPE_JPEG) return false;
  $bron = @imagecreatefromjpeg($volledigPad);
  if (!$bron) return false;
  fotoboekZetWatermerk($bron, $logoPad);
  $ok = imagejpeg($bron, $volledigPad, 82);
  imagedestroy($bron);
  return $ok;
}

// Verwijdert een map met alle inhoud (album verwijderen inclusief foto's en thumbnails).
function verwijderMapRecursief($pad) {
  if (!is_dir($pad)) return;
  foreach (scandir($pad) as $item) {
    if ($item === '.' || $item === '..') continue;
    $volledig = $pad . '/' . $item;
    if (is_dir($volledig)) verwijderMapRecursief($volledig);
    else @unlink($volledig);
  }
  @rmdir($pad);
}

// De gebruikers en het logboek (laadGebruikers, schrijfGebruikers,
// schrijfLog), de lockout bij mislukte inlogpogingen, het inlezen van
// beheer-config.php en de afhandeling van in- en uitloggen staan in
// auth.php. Op dit punt zijn $configOk, $ingelogd, $huidigeGebruiker,
// $isMaster, $inlogFout en $melding/$meldingType dus al gevuld.

// ===== Rechten per gebruiker =====
// Alle beheer-onderdelen die je per gebruiker aan of uit kan zetten. Ook
// Gebruikers, Log en Back-ups: die waren alleen bereikbaar met het
// beheerderswachtwoord, maar zijn nu net zo goed een vinkje.
//
// Let op wat je met Gebruikers weggeeft: wie dat tabblad heeft, kan zichzelf
// en anderen elk ander tabblad geven en wachtwoorden opnieuw instellen. Dat
// is in de praktijk hetzelfde als volledige toegang. Het beheerderswachtwoord
// uit beheer-config.php blijft de terugvaloptie: dat werkt altijd en staat
// niet in deze lijst.
$beheerTabsAlle = [
  'homepage'   => 'Homepage',
  'ontstaan'   => 'Ontstaan',
  'baanreglement' => 'Baanreglement',
  'bedankt'    => 'Bedankt-pagina',
  'aanmelden'  => 'Aanmelden',
  'mededeling' => 'Openingstijden',
  'nieuws'     => 'Nieuws',
  'agenda'     => 'Agenda',
  'faq'        => 'Vragen',
  'sponsors'   => 'Sponsors',
  'contact'    => 'Contact',
  'media'      => 'Media',
  'fotoboek'   => 'Fotoboek',
  'rekentabel' => 'Rekentabel',
  'changelog'  => 'Changelog',
  'gebruikers' => 'Gebruikers',
  'log'        => 'Log',
  'backups'    => 'Back-ups',
];

// Tabbladen die niet via de vinkjes bij Gebruikers gaan maar via de rol in
// de ledenadministratie. Die staan sinds de splitsing allemaal in leden.php,
// dus hier is de lijst leeg. Hij blijft bestaan omdat authRechten() hem
// verwacht en er zo weer een tabblad in kan als dat ooit nodig is.
$beheerTabsViaRol = [];
$beheerTabsToewijsbaar = array_diff_key($beheerTabsAlle, array_flip($beheerTabsViaRol));

// ===== Changelog =====
// De categorieën van een changelogregel. De sleutel staat zo in het
// databestand, dus die niet meer wijzigen; het label en de kleur mogen wel.
$changelogCategorieen = [
  'nieuw'       => 'Nieuw',
  'verbeterd'   => 'Verbeterd',
  'opgelost'    => 'Opgelost',
  'beveiliging' => 'Beveiliging',
  'onderhoud'   => 'Onderhoud',
];

// De vaste regels uit changelog-historie.php. Ontbreekt dat bestand (of is
// het stuk), dan blijft de changelog gewoon werken met alleen de eigen
// regels uit data/changelog.json.
function laadChangelogVast($pad) {
  if (!file_exists($pad)) return [];
  $regels = @include $pad;
  return is_array($regels) ? $regels : [];
}

// De eigen regels van het bestuur (data/changelog.json).
function laadChangelogEigen($pad) {
  if (!file_exists($pad)) return [];
  $json = json_decode(@file_get_contents($pad), true);
  return is_array($json) ? $json : [];
}

// Beide lijsten samenvoegen en op datum zetten, nieuwste bovenaan. Regels
// zonder geldige datum zakken naar onderen in plaats van dat ze verdwijnen.
// $volg is de tiebreaker binnen dezelfde datum: usort is niet in elke
// PHP-versie stabiel, dus de volgorde wordt hier expliciet vastgelegd
// (eigen regels eerst, daarna de vaste).
function changelogSamenvoegen($eigen, $vast) {
  $alles = [];
  $volg = 0;
  foreach ($eigen as $r) {
    $r['bron'] = 'eigen';
    $r['volg'] = $volg++;
    $alles[] = $r;
  }
  foreach ($vast as $r) {
    $r['bron'] = 'vast';
    $r['volg'] = $volg++;
    $alles[] = $r;
  }
  usort($alles, function ($a, $b) {
    $da = (string) ($a['datum'] ?? '');
    $db = (string) ($b['datum'] ?? '');
    if ($da !== $db) return strcmp($db, $da);
    return ($a['volg'] ?? 0) <=> ($b['volg'] ?? 0);
  });
  return $alles;
}

// Velden van het tabblad Homepage: sleutel => [label voor in het formulier,
// 'tekst' (kort, één regel) of 'blok' (langere alinea/textarea)]. Eén lijst,
// gebruikt bij zowel opslaan als het formulier zelf, zodat die twee nooit
// uit elkaar kunnen lopen.
$homepageVelden = [
  'hero_intro'   => ['Intro boven aan de pagina (onder het logo)', 'blok'],
  'hero_btn_member' => ['Hero: knop "Lid worden!"', 'tekst'],
  'hero_btn_more' => ['Hero: knop "Meer over ons"', 'tekst'],
  'update_label' => ['Infobalk: label voor het actueel-bericht ("📣 Actueel:")', 'tekst'],
  'info_hours' => ['Infobalk: label "Openingstijden"', 'tekst'],
  'info_location' => ['Infobalk: label "Locatie"', 'tekst'],
  'info_membership' => ['Infobalk: label "Lidmaatschap"', 'tekst'],
  'info_weather' => ['Infobalk: label "Weer in Eygelshoven"', 'tekst'],
  'about_label' => ['"Wie zijn wij": sectielabel', 'tekst'],
  'about_title' => ['"Wie zijn wij": titel', 'tekst'],
  'about_medialink' => ['"Wie zijn wij": link naar media ("RC045 in de media")', 'tekst'],
  'about_storylink' => ['"Wie zijn wij": link naar ontstaansverhaal', 'tekst'],
  'about_photos_title' => ['"Wie zijn wij": titel boven de fotostrip', 'tekst'],
  'track_label' => ['"De baan": sectielabel', 'tekst'],
  'track_title' => ['"De baan": titel', 'tekst'],
  'hours_title' => ['Openingstijden-kaart: titel ("🕐 Openingstijden")', 'tekst', 'Dit is alleen de tekst rond de openingstijden. De tijden en status zelf pas je aan in het tabblad Openingstijden.'],
  'hours_sat' => ['Openingstijden-kaart: label "Zaterdag"', 'tekst'],
  'hours_sun' => ['Openingstijden-kaart: label "Zondag"', 'tekst'],
  'hours_wed' => ['Openingstijden-kaart: label "Woensdag"', 'tekst'],
  'hours_weather' => ['Openingstijden-kaart: waarschuwing bij slecht weer', 'blok'],
  'hours_note_attention' => ['Openingstijden-kaart: "Let op:"', 'tekst'],
  'hours_note_text' => ['Openingstijden-kaart: notitie over onderhoud', 'blok'],
  'rules_label' => ['"Veiligheid staat voorop": sectielabel', 'tekst'],
  'rules_title' => ['"Veiligheid staat voorop": titel', 'tekst'],
  'rules_sub' => ['"Veiligheid staat voorop": introtekst', 'blok'],
  'rules_link' => ['"Veiligheid staat voorop": link naar volledig baanreglement', 'tekst'],
  'rule1_title' => ['Regel-kaartje 1: titel', 'tekst'],
  'rule1_text' => ['Regel-kaartje 1: tekst', 'blok'],
  'rule2_title' => ['Regel-kaartje 2: titel', 'tekst'],
  'rule2_text' => ['Regel-kaartje 2: tekst', 'blok'],
  'rule3_title' => ['Regel-kaartje 3: titel', 'tekst'],
  'rule3_text' => ['Regel-kaartje 3: tekst', 'blok'],
  'rule4_title' => ['Regel-kaartje 4: titel', 'tekst'],
  'rule4_text' => ['Regel-kaartje 4: tekst', 'blok'],
  'rule5_title' => ['Regel-kaartje 5: titel', 'tekst'],
  'rule5_text' => ['Regel-kaartje 5: tekst', 'blok'],
  'rule6_title' => ['Regel-kaartje 6: titel', 'tekst'],
  'rule6_text' => ['Regel-kaartje 6: tekst', 'blok'],
  'rule7_title' => ['Regel-kaartje 7: titel', 'tekst'],
  'rule7_text' => ['Regel-kaartje 7: tekst', 'blok'],
  'nieuws_label' => ['Nieuws: sectielabel', 'tekst'],
  'nieuws_title' => ['Nieuws: titel', 'tekst'],
  'nieuws_sub' => ['Nieuws: introtekst', 'blok'],
  'agenda_label' => ['Agenda: sectielabel', 'tekst'],
  'agenda_title' => ['Agenda: titel', 'tekst'],
  'agenda_sub' => ['Agenda: introtekst', 'blok'],
  'loc_label' => ['"Bezoek ons": sectielabel', 'tekst'],
  'loc_title' => ['"Bezoek ons": titel', 'tekst'],
  'addr_title' => ['"Bezoek ons": "Adres"', 'tekst'],
  'addr_text' => ['"Bezoek ons": adrestekst', 'blok'],
  'addr_route' => ['"Bezoek ons": link "Routebeschrijving openen"', 'tekst'],
  'instagram_soon' => ['"Bezoek ons": label bij Instagram ("Binnenkort beschikbaar")', 'tekst'],
  'contact_label' => ['Contact: sectielabel', 'tekst'],
  'contact_title' => ['Contact: titel', 'tekst'],
  'contact_text' => ['Contact: introtekst boven het formulier', 'blok'],
  'about_p1'     => ['"Wie zijn wij": eerste alinea', 'blok'],
  'about_p2'     => ['"Wie zijn wij": tweede alinea', 'blok'],
  'feat1_title'  => ['"Wie zijn wij": kaartje 1, titel', 'tekst'],
  'feat1_text'   => ['"Wie zijn wij": kaartje 1, tekst', 'blok'],
  'feat2_title'  => ['"Wie zijn wij": kaartje 2, titel', 'tekst'],
  'feat2_text'   => ['"Wie zijn wij": kaartje 2, tekst', 'blok'],
  'feat3_title'  => ['"Wie zijn wij": kaartje 3, titel', 'tekst'],
  'feat3_text'   => ['"Wie zijn wij": kaartje 3, tekst', 'blok'],
  'feat4_title'  => ['"Wie zijn wij": kaartje 4, titel', 'tekst'],
  'feat4_text'   => ['"Wie zijn wij": kaartje 4, tekst', 'blok'],
  'track_p1'     => ['"De baan": eerste alinea', 'blok'],
  'track_p2'     => ['"De baan": tweede alinea', 'blok'],
  'track_f1'     => ['"De baan": kenmerk 1', 'tekst'],
  'track_f2'     => ['"De baan": kenmerk 2', 'tekst'],
  'track_f3'     => ['"De baan": kenmerk 3', 'tekst'],
  'track_f4'     => ['"De baan": kenmerk 4', 'tekst'],
  'track_f5'     => ['"De baan": kenmerk 5', 'tekst'],
  'pricing_title' => ['"Lidmaatschap": titel boven de twee kaarten', 'tekst'],
  'pricing_sub'  => ['"Lidmaatschap": introtekst boven de twee kaarten', 'blok'],
  'guest_tag'    => ['"Lidmaatschap": labeltje boven de Gastrijden-kaart ("Gastrijden")', 'tekst'],
  'guest_title'  => ['"Lidmaatschap": titel bij Gastrijden ("Kom eens gastrijden!")', 'tekst'],
  'guest_text'   => ['"Lidmaatschap": omschrijving bij Gastrijden', 'blok'],
  'guest_adult'  => ['"Lidmaatschap": Gastrijden, prijsregel "Volwassene (16+)"', 'tekst'],
  'guest_youth'  => ['"Lidmaatschap": Gastrijden, prijsregel "Jeugd (t/m 15 jaar)"', 'tekst'],
  'guest_group'  => ['"Lidmaatschap": Gastrijden, prijsregel "Groepen krijgen korting!"', 'tekst'],
  'guest_btn'    => ['"Lidmaatschap": knoptekst onder Gastrijden ("Stuur ons een berichtje →")', 'tekst', 'De pijl (→) staat in de tekst zelf, die moet je dus meetypen.'],
  'guest_note'   => ['"Lidmaatschap": kleine notitie onder Gastrijden', 'blok', 'Zet een tweede regel (Enter) voor een apart, opvallend blokje, bijvoorbeeld voor de melding over groepen van 4+.'],
  'member_tag'   => ['"Lidmaatschap": labeltje boven de lidmaatschapskaart ("Lidmaatschap")', 'tekst'],
  'member_title' => ['"Lidmaatschap": titel bij het lidmaatschap ("Word lid van RC045")', 'tekst'],
  'member_text'  => ['"Lidmaatschap": omschrijving bij het lidmaatschap', 'blok'],
  'member_youth' => ['"Lidmaatschap": prijsregel "Jeugdlid (t/m 15 jaar)"', 'tekst'],
  'member_senior' => ['"Lidmaatschap": prijsregel "Seniorlid (16+)"', 'tekst'],
  'member_fee'   => ['"Lidmaatschap": prijsregel "Eenmalige inschrijfkosten"', 'tekst'],
  'member_btn'   => ['"Lidmaatschap": knoptekst onder het lidmaatschap ("Ik wil graag lid worden!")', 'tekst'],
  'member_note'  => ['"Lidmaatschap": kleine notitie onder het lidmaatschap', 'blok'],

  'nav_about'      => ['Navigatiemenu: "Over ons"', 'tekst', 'Deze tekst wordt ook gebruikt voor de link "Over ons" in de footer.'],
  'nav_membership' => ['Navigatiemenu: "Lidmaatschap"', 'tekst'],
  'nav_track'      => ['Navigatiemenu: "De baan"', 'tekst'],
  'nav_location'   => ['Navigatiemenu: "Locatie"', 'tekst'],
  'nav_photobook'  => ['Navigatiemenu: "Fotoboek"', 'tekst'],
  'nav_contact'    => ['Navigatiemenu: "Contact"', 'tekst', 'Deze tekst wordt ook gebruikt voor de link "Contact" in de footer.'],
  'nav_join'       => ['Navigatiemenu: "Lid worden" (los knopje uiterst rechts)', 'tekst'],

  'footer_brand'          => ['Footer: omschrijving naast het logo', 'blok'],
  'footer_nav'             => ['Footer: kolomtitel "Navigatie"', 'tekst'],
  'footer_origin'          => ['Footer: link "Het ontstaan"', 'tekst'],
  'footer_media'           => ['Footer: link "Media"', 'tekst'],
  'footer_photobook'       => ['Footer: link "Fotoboek"', 'tekst'],
  'footer_calendar'        => ['Footer: link "Activiteitenkalender"', 'tekst'],
  'footer_join'            => ['Footer: kolomtitel "Meedoen"', 'tekst'],
  'footer_become'          => ['Footer: link "Lid worden"', 'tekst'],
  'footer_rules'           => ['Footer: link "Baanreglement"', 'tekst'],
  'footer_sponsor'         => ['Footer: link "Sponsoring"', 'tekst'],
  'footer_sponsors_title'  => ['Footer: titel boven de sponsorlogo\'s ("Met dank aan onze sponsoren")', 'tekst'],
  'footer_credit'          => ['Footer: "Website door" (voor de naam, die staat vast)', 'tekst'],

  'form_name'    => ['Contactformulier: label "Naam"', 'tekst'],
  'form_email'   => ['Contactformulier: label "E-mailadres"', 'tekst'],
  'form_phone'   => ['Contactformulier: label "Telefoonnummer"', 'tekst'],
  'form_subject' => ['Contactformulier: label "Onderwerp"', 'tekst'],
  'form_select'  => ['Contactformulier: placeholder in de onderwerp-lijst ("Selecteer een onderwerp...")', 'tekst'],
  'form_opt1'    => ['Contactformulier: onderwerp-optie "Vraag over lidmaatschap"', 'tekst'],
  'form_opt4'    => ['Contactformulier: onderwerp-optie "Sponsoring"', 'tekst'],
  'form_opt5'    => ['Contactformulier: onderwerp-optie "Overige vragen"', 'tekst'],
  'form_message' => ['Contactformulier: label "Bericht"', 'tekst'],
  'form_send'    => ['Contactformulier: tekst op de verzendknop ("Verstuur bericht →")', 'tekst', 'De pijl (→) staat in de tekst zelf, die moet je dus meetypen.'],
];
// Zelfde velden, gegroepeerd per kaart voor het formulier. Een aparte lijst
// in plaats van in $homepageVelden zelf, want de volgorde en groepering is
// puur voor de weergave, de opslaglogica hierboven werkt gewoon de hele
// platte lijst af.
// Volgorde van de groepen is gelijkgetrokken met de volgorde waarin ze
// echt op de pagina staan (nav, hero, infobalk, nieuws, wie-zijn-wij,
// lidmaatschap, de baan, agenda, reglement-preview, locatie/openingstijden,
// contact + formulier, footer helemaal onderaan). Stond eerder door elkaar,
// bijvoorbeeld Openingstijden vlak na de hero terwijl die pas onderaan de
// Locatie-sectie staat, en Contactformulier na Footer terwijl het formulier
// juist bij de Contact-sectie hoort, boven de footer.
$homepageGroepen = [
  'Navigatiemenu' => ['nav_about', 'nav_membership', 'nav_track', 'nav_location', 'nav_photobook', 'nav_contact', 'nav_join'],
  'Hero' => ['hero_intro', 'hero_btn_member', 'hero_btn_more'],
  'Infobalk (onder de hero)' => ['update_label', 'info_hours', 'info_location', 'info_membership', 'info_weather'],
  'Nieuws' => ['nieuws_label', 'nieuws_title', 'nieuws_sub'],
  '"Wie zijn wij"' => ['about_label', 'about_title', 'about_p1', 'about_p2', 'about_medialink', 'about_storylink', 'about_photos_title', 'feat1_title', 'feat1_text', 'feat2_title', 'feat2_text', 'feat3_title', 'feat3_text', 'feat4_title', 'feat4_text'],
  '"Lidmaatschap"' => ['pricing_title', 'pricing_sub', 'guest_tag', 'guest_title', 'guest_text', 'guest_adult', 'guest_youth', 'guest_group', 'guest_btn', 'guest_note', 'member_tag', 'member_title', 'member_text', 'member_youth', 'member_senior', 'member_fee', 'member_btn', 'member_note'],
  '"De baan"' => ['track_label', 'track_title', 'track_p1', 'track_p2', 'track_f1', 'track_f2', 'track_f3', 'track_f4', 'track_f5'],
  'Agenda' => ['agenda_label', 'agenda_title', 'agenda_sub'],
  '"Veiligheid staat voorop" (reglement-preview)' => ['rules_label', 'rules_title', 'rules_sub', 'rule1_title', 'rule1_text', 'rule2_title', 'rule2_text', 'rule3_title', 'rule3_text', 'rule4_title', 'rule4_text', 'rule5_title', 'rule5_text', 'rule6_title', 'rule6_text', 'rule7_title', 'rule7_text', 'rules_link'],
  '"Bezoek ons"' => ['loc_label', 'loc_title', 'addr_title', 'addr_text', 'addr_route', 'instagram_soon'],
  'Openingstijden (teksten rond de tijden)' => ['hours_title', 'hours_sat', 'hours_sun', 'hours_wed', 'hours_weather', 'hours_note_attention', 'hours_note_text'],
  'Contact' => ['contact_label', 'contact_title', 'contact_text'],
  'Contactformulier' => ['form_name', 'form_email', 'form_phone', 'form_subject', 'form_select', 'form_opt1', 'form_opt4', 'form_opt5', 'form_message', 'form_send'],
  'Footer' => ['footer_brand', 'footer_nav', 'footer_origin', 'footer_media', 'footer_photobook', 'footer_calendar', 'footer_join', 'footer_become', 'footer_rules', 'footer_sponsor', 'footer_sponsors_title', 'footer_credit'],
];

// De 14 groepen hierboven aan elkaar gebreid onder een paar kopjes, puur
// visueel: welke groep bij welk hoofdstuk hoort. Elders in het formulier
// (data-taal-scope, opslaglogica) verandert hierdoor niets. Volgorde van
// de hoofdstukken zelf volgt nu ook de pagina: boven, midden, locatie/
// contact, footer.
$homepageClusters = [
  'Bovenkant' => ['Navigatiemenu', 'Hero', 'Infobalk (onder de hero)'],
  'Content op de homepage' => ['Nieuws', '"Wie zijn wij"', '"Lidmaatschap"', '"De baan"', 'Agenda', '"Veiligheid staat voorop" (reglement-preview)'],
  'Locatie & openingstijden' => ['"Bezoek ons"', 'Openingstijden (teksten rond de tijden)'],
  'Contact' => ['Contact', 'Contactformulier'],
  'Footer' => ['Footer'],
];
$homepageGroepNaarCluster = [];
foreach ($homepageClusters as $clusterLabel => $groepenInCluster) {
  foreach ($groepenInCluster as $g) {
    $homepageGroepNaarCluster[$g] = $clusterLabel;
  }
}

// Velden van het tabblad Ontstaan: zelfde opzet als $homepageVelden. Geen
// aparte groepen-lijst nodig, dit tabblad is één doorlopend verhaal.
$ontstaanVelden = [
  'hero_sub' => ['Ondertitel boven het verhaal', 'tekst'],
  'story_p1' => ['Alinea 1: het begin (2020, vakantie)', 'blok'],
  'story_p2' => ['Alinea 2: de zoektocht naar een locatie', 'blok'],
  'story_p3' => ['Alinea 3: het wekelijkse samenkomen', 'blok'],
  'story_p4' => ['Alinea 4: media-aandacht', 'blok'],
  'story_p5' => ['Alinea 5: oprichting van de vereniging', 'blok'],
  'story_p6' => ['Alinea 6: de baan in Eygelshoven (2022)', 'blok'],
  'story_p7' => ['Alinea 7: nog steeds in ontwikkeling', 'blok'],
];
// Velden van het tabblad Baanreglement. Per artikel één titelveld en één
// tekstvak met de volledige inhoud (was voorheen losse bullets/subartikelen/
// alinea's, nu één doorlopende tekst per artikel: een lege regel ertussen
// wordt bij het tonen weer als aparte alinea behandeld).
$baanreglementVelden = [
  'hero_sub' => ['Ondertitel boven de pagina', 'tekst'],
  'intro_bold' => ['Vet woord vooraan de introtekst (bijv. "Belangrijk:")', 'tekst'],
  'intro_text' => ['Introtekst', 'blok'],
  'a1_title' => ['Artikel 1: titel', 'tekst'],
  'a1_body' => ['Artikel 1 (Openingstijden van de baan): inhoud', 'blok'],
  'a2_title' => ['Artikel 2: titel', 'tekst'],
  'a2_body' => ['Artikel 2 (Veiligheid): inhoud', 'blok'],
  'a3_title' => ['Artikel 3: titel', 'tekst'],
  'a3_body' => ['Artikel 3 (Gastrijders): inhoud', 'blok'],
  'a4_title' => ['Artikel 4: titel', 'tekst'],
  'a4_body' => ['Artikel 4 (Baanmeester): inhoud', 'blok'],
  'a5_title' => ['Artikel 5: titel', 'tekst'],
  'a5_body' => ['Artikel 5 (Rijden en gebruik van brandstof): inhoud', 'blok'],
  'a6_title' => ['Artikel 6: titel', 'tekst'],
  'a6_body' => ['Artikel 6 (Verdovende middelen): inhoud', 'blok'],
  'a7_title' => ['Artikel 7: titel', 'tekst'],
  'a7_body' => ['Artikel 7 (Roken en drank): inhoud', 'blok'],
  'a8_title' => ['Artikel 8: titel', 'tekst'],
  'a8_body' => ['Artikel 8 (Opruimen): inhoud', 'blok'],
  'a9_title' => ['Artikel 9: titel', 'tekst'],
  'a9_body' => ['Artikel 9 (AVG): inhoud', 'blok'],
  'a10_title' => ['Artikel 10: titel', 'tekst'],
  'a10_body' => ['Artikel 10 (Het bestuur): inhoud', 'blok'],
];
// Zelfde velden, gegroepeerd per artikel voor het formulier.
$baanreglementGroepen = [
  'Intro' => ['hero_sub', 'intro_bold', 'intro_text'],
  'Artikel 1' => ['a1_title', 'a1_body'],
  'Artikel 2' => ['a2_title', 'a2_body'],
  'Artikel 3' => ['a3_title', 'a3_body'],
  'Artikel 4' => ['a4_title', 'a4_body'],
  'Artikel 5' => ['a5_title', 'a5_body'],
  'Artikel 6' => ['a6_title', 'a6_body'],
  'Artikel 7' => ['a7_title', 'a7_body'],
  'Artikel 8' => ['a8_title', 'a8_body'],
  'Artikel 9' => ['a9_title', 'a9_body'],
  'Artikel 10' => ['a10_title', 'a10_body'],
];

// ===== Rechten =====
// Welke tabbladen mag deze sessie zien en opslaan? De regels zelf staan in
// authRechten() in auth.php, zodat leden.php straks precies hetzelfde
// rechtenmodel gebruikt. Kort: master mag alles, een gewone gebruiker mag
// wat er bij Gebruikers is aangevinkt (of alles als daar nooit iets is
// ingesteld), en de tabbladen in $beheerTabsViaRol lopen niet via die
// vinkjes maar via de bestuursfunctie in de ledenadministratie.
//
// Zolang een tabblad nog niet in $beheerTabsAlle staat, doet dat laatste
// niets voor dat tabblad. Voeg het daar toe en de toegang regelt zich hier.
$rechten = authRechten($beheerTabsAlle, $beheerTabsViaRol);
$huidigeGebruikerRecord = $rechten['gebruikerRecord'];
$toegestaneTabs         = $rechten['toegestaneTabs'];
$eigenRol               = $rechten['eigenRol'];
$isBestuurslid          = $rechten['isBestuurslid'];

// Gebruikers, Log en Back-ups waren tot nu toe alleen bereikbaar met het
// beheerderswachtwoord en zijn nu een vinkje net als de rest. Een account
// waarvoor nooit een selectie is opgeslagen mag volgens authRechten() alles,
// en zou er daardoor stilzwijgend drie rechten bij krijgen die het nooit
// heeft gehad. Dus: wie geen expliciete selectie heeft, krijgt deze drie
// niet. Zodra je de toegang van zo'n account een keer opslaat, gelden gewoon
// de vinkjes.
$nieuweRechten = ['gebruikers', 'log', 'backups'];
$heeftEigenSelectie = is_array($huidigeGebruikerRecord['tabs'] ?? null);
if (!$isMaster && !$heeftEigenSelectie) {
  $toegestaneTabs = array_values(array_diff($toegestaneTabs, $nieuweRechten));
}

// Wie hier geen enkel tabblad mag zien, hoort niet op deze pagina. Dat is
// precies de situatie van een account dat alleen voor het ledengedeelte is
// aangemaakt: geen enkel beheertabblad aangevinkt. Meteen afvangen, vóór de
// POST-afhandeling verderop, anders zou zo'n account met een handmatig
// opgebouwd formulier alsnog iets kunnen opslaan. Uitloggen is op dit punt
// al door auth.php gedaan, dus dat blijft werken.
if ($ingelogd && !$isMaster && empty($toegestaneTabs)) {
  header('Content-Type: text/html; charset=utf-8');
  ?><!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Beheer</title>
  <style>
    body { font-family: system-ui, sans-serif; background: #FAF6EC; color: #2A3818; margin: 0; padding: 48px 24px; }
    .kaart { max-width: 460px; margin: 0 auto; background: #fff; border: 1px solid #DDD8C0; border-radius: 12px; padding: 28px; }
    h1 { font-size: 22px; margin: 0 0 12px; }
    p { line-height: 1.6; margin: 0 0 16px; }
    button { background: #3A7A77; color: #fff; border: 0; border-radius: 8px; padding: 10px 18px; font: inherit; cursor: pointer; }
  </style>
</head>
<body>
  <div class="kaart">
    <h1>Geen toegang tot het beheer</h1>
    <p>Voor dit account staat geen enkel beheertabblad aan. Het ledengedeelte vind je op <a href="leden.php">de ledenpagina</a>.</p>
    <form method="post" action="beheer.php">
      <input type="hidden" name="formulier" value="uitloggen">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
      <button type="submit">Uitloggen</button>
    </form>
  </div>
</body>
</html><?php
  exit;
}

// Welk formulier hoort bij welk tabblad, om save-acties ook serverside te
// blokkeren voor een tabblad waar iemand geen toegang toe heeft. Dit is de
// echte beveiliging; het menu en de tabbladen hieronder verbergen dingen
// alleen aan de oppervlakte, iemand die het hash-adres (#leden e.d.) direct
// intypt mag zonder deze check nog steeds gewoon opslaan.
$formulierTab = [
  'actueel' => 'mededeling', 'agenda' => 'agenda', 'faq' => 'faq', 'sponsors' => 'sponsors',
  'contact' => 'contact', 'media' => 'media', 'nieuws' => 'nieuws', 'rekentabel' => 'rekentabel',
  'homepage' => 'homepage',
  'ontstaan' => 'ontstaan',
  'baanreglement' => 'baanreglement',
  'bedankt' => 'bedankt',
  'aanmelden' => 'aanmelden',
  'media_tekst' => 'media',
  'fotoboek_tekst' => 'fotoboek',
  'fotoboek_album_aanmaken' => 'fotoboek', 'fotoboek_album_bewerken' => 'fotoboek',
  'changelog_toevoegen' => 'changelog', 'changelog_bewerken' => 'changelog',
  'changelog_verwijderen' => 'changelog',
  'gebruiker_toevoegen' => 'gebruikers',
  'gebruiker_tabs_bijwerken' => 'gebruikers',
  'gebruiker_verwijderen' => 'gebruikers',
  'backup_herstellen' => 'backups',
];

// ===== Inhoud opslaan (openingstijden / agenda / faq / sponsors / gebruikers) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ingelogd) {
  $formulier = $_POST['formulier'] ?? '';
  if (isset($formulierTab[$formulier]) && !in_array($formulierTab[$formulier], $toegestaneTabs, true)) {
    // Geen toegang tot dit tabblad: net doen alsof er geen bekend formulier
    // is binnengekomen, dan gebeurt er verderop simpelweg niets.
    schrijfLog($logBestand, $huidigeGebruiker, 'toegang_geweigerd', $formulier);
    $formulier = '';
  }

  // Eén slot over het hele opslaan-blok: van inlezen van het huidige bestand
  // tot wegschrijven van de nieuwe versie. Zonder dit zouden twee
  // gelijktijdige opslag-acties elkaar stilletjes kunnen overschrijven,
  // omdat schrijfJson() alleen tijdens het schrijven zelf een lock heeft en
  // niet tijdens het hele lees-wijzig-schrijf-traject. Zie data-slot.php;
  // leden.php, het aanmeldformulier en het inschrijven op een evenement
  // gebruiken hetzelfde slot.
  $lockHandle = dataSlotOpen();

  // Herkent een POST die door PHP zelf al is afgewezen omdat het geheel groter
  // was dan upload_max_filesize/post_max_size: $_POST en $_FILES komen dan
  // allebei leeg binnen, terwijl de browser wel degelijk iets groots stuurde.
  // Zonder deze check zou dit stilzwijgend als "sessie verlopen" ogen, wat
  // vooral bij een te grote video-upload verwarrend zou zijn.
  $mogelijkTeGroot = empty($_POST) && empty($_FILES) && !empty($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 0;

  if ($mogelijkTeGroot) {
    $melding['fotoboek'] = 'Uploaden mislukt: het geheel is waarschijnlijk te groot voor de server. Probeer een kleiner bestand, of vraag na bij Strato of upload_max_filesize/post_max_size hoger kan.';
    $meldingType['fotoboek'] = 'fout';
  } elseif (!csrfOk()) {
    $melding['csrf'] = 'Sessie verlopen. Ververs de pagina en probeer het opnieuw.';
    $meldingType['csrf'] = 'fout';
  } elseif ($formulier === 'actueel') {
    $tekst = kort($_POST['tekst'] ?? '', 500);
    if (schrijfJson($actueelBestand, ['text' => $tekst, 'updated' => date('c')])) {
      $melding['actueel'] = $tekst === ''
        ? 'Opgeslagen. De strook is nu verborgen op de website.'
        : 'Opgeslagen. De nieuwe tekst staat nu op de website.';
      $meldingType['actueel'] = 'ok';
      schrijfLog($logBestand, $huidigeGebruiker, 'openingstijden', $tekst === '' ? 'strook verborgen' : 'tekst bijgewerkt');
    } else {
      $melding['actueel'] = 'Opslaan mislukt. Controleer de schrijfrechten van de map data op de server.';
      $meldingType['actueel'] = 'fout';
    }

  } elseif ($formulier === 'agenda') {
    $ruw = [];
    foreach (($_POST['agenda'] ?? []) as $idx => $rij) {
      $titelNl = kort($rij['title_nl'] ?? '', 80);
      if ($titelNl === '') continue; // NL titel is verplicht, anders wordt de kaart niet getoond
      $tag = $rij['tag'] ?? 'leden';
      if (!isset($agendaTags[$tag])) $tag = 'leden';
      $datum = datumNaarIso($rij['date'] ?? '');
      // Gekozen positie uit de keuzelijst; bij ontbreken (zou niet moeten
      // gebeuren) valt hij terug op de plek waar hij in het formulier stond.
      $volgorde = is_numeric($rij['volgorde'] ?? null) ? (float) $rij['volgorde'] : (float) $idx;
      $ruw[] = [
        'volgorde' => $volgorde,
        'orig' => (int) $idx,
        'event' => [
          'date' => $datum,
          'tag'  => $tag,
          'time' => kort($rij['time'] ?? '', 40),
          'title' => [
            'nl' => $titelNl,
            'en' => kort($rij['title_en'] ?? '', 80),
            'de' => kort($rij['title_de'] ?? '', 80),
          ],
          'desc' => [
            'nl' => kort($rij['desc_nl'] ?? '', 200),
            'en' => kort($rij['desc_en'] ?? '', 200),
            'de' => kort($rij['desc_de'] ?? '', 200),
          ],
          'past' => !empty($rij['past']),
        ],
      ];
    }
    // Sorteren op de gekozen volgorde uit de keuzelijst. Bij een gelijke
    // waarde (bijv. je zet kaart 5 op "3" terwijl kaart 3 daar al staat)
    // wint de kaart die je zelf net verplaatst hebt: die had oorspronkelijk
    // de hoogste positie in het formulier, dus bij gelijke stand komt de
    // hoogste oorspronkelijke positie eerst. Zo schuift de rest gewoon een
    // plekje op, zoals bij het invoegen op een positie hoort te gaan.
    usort($ruw, function($a, $b) {
      return $a['volgorde'] <=> $b['volgorde'] ?: $b['orig'] <=> $a['orig'];
    });
    $events = array_map(function($r) { return $r['event']; }, $ruw);
    if (schrijfJson($agendaBestand, $events)) {
      $melding['agenda'] = 'Opgeslagen. De agenda op de homepage is bijgewerkt.';
      $meldingType['agenda'] = 'ok';
      schrijfLog($logBestand, $huidigeGebruiker, 'agenda', count($events) . ' kaart(en) opgeslagen');
    } else {
      $melding['agenda'] = 'Opslaan mislukt. Controleer de schrijfrechten van de map data op de server.';
      $meldingType['agenda'] = 'fout';
    }

  } elseif ($formulier === 'faq') {
    $items = [];
    foreach (($_POST['faq'] ?? []) as $rij) {
      $vraagNl = kort($rij['q_nl'] ?? '', 150);
      if ($vraagNl === '') continue; // Nederlandse vraag is verplicht, anders wordt de kaart niet getoond
      $items[] = [
        'q' => [
          'nl' => $vraagNl,
          'en' => kort($rij['q_en'] ?? '', 150),
          'de' => kort($rij['q_de'] ?? '', 150),
        ],
        'a' => [
          'nl' => kort($rij['a_nl'] ?? '', 600),
          'en' => kort($rij['a_en'] ?? '', 600),
          'de' => kort($rij['a_de'] ?? '', 600),
        ],
      ];
    }
    if (schrijfJson($faqBestand, $items)) {
      $melding['faq'] = 'Opgeslagen. De vragenlijst op de aanmeldpagina is bijgewerkt.';
      $meldingType['faq'] = 'ok';
      schrijfLog($logBestand, $huidigeGebruiker, 'faq', count($items) . ' vraag/vragen opgeslagen');
    } else {
      $melding['faq'] = 'Opslaan mislukt. Controleer de schrijfrechten van de map data op de server.';
      $meldingType['faq'] = 'fout';
    }

  } elseif ($formulier === 'sponsors') {
    // Huidige logo's inlezen, zodat een slot zonder nieuwe upload zijn logo behoudt.
    // Bestaat het bestand nog niet, dan gelden de vijf sponsors die al op de site staan.
    $bestaandeSponsors = $sponsorStandaard;
    $bestaandeCta = $sponsorCtaStandaard;
    if (file_exists($sponsorBestand)) {
      $json = json_decode(file_get_contents($sponsorBestand), true);
      if (is_array($json) && isset($json['items'])) $bestaandeSponsors = $json['items'];
      if (is_array($json) && isset($json['cta']) && is_array($json['cta'])) $bestaandeCta = array_merge($sponsorCtaStandaard, $json['cta']);
    }

    // Nederlands is verplicht (val terug op de vorige tekst als het veld leeg
    // wordt opgeslagen); Engels en Duits blijven leeg toegestaan, dan valt de
    // website terug op de Nederlandse tekst.
    $ctaNl = kort($_POST['cta_nl'] ?? '', 200);
    $cta = [
      'nl' => $ctaNl !== '' ? $ctaNl : $bestaandeCta['nl'],
      'en' => kort($_POST['cta_en'] ?? '', 200),
      'de' => kort($_POST['cta_de'] ?? '', 200),
    ];

    $items = [];
    $sponsorFout = null;
    foreach (($_POST['sponsor'] ?? []) as $i => $rij) {
      $naam = kort($rij['name'] ?? '', 60);
      if ($naam === '') continue; // lege naam = sponsor wordt niet getoond

      $url = trim($rij['url'] ?? '');
      if ($url !== '' && !preg_match('#^https?://#i', $url)) {
        $sponsorFout = 'Website van "' . $naam . '" moet beginnen met http:// of https://.';
        break;
      }

      $huidigLogo = $bestaandeSponsors[$i]['logo'] ?? '';
      $resultaat = verwerkSponsorLogo('sponsor_logo_' . $i, $i, $huidigLogo);
      if (!$resultaat['ok']) {
        $sponsorFout = 'Logo van "' . $naam . '": ' . $resultaat['fout'];
        break;
      }
      if ($resultaat['logo'] === '') {
        $sponsorFout = 'Voeg een logo toe voor "' . $naam . '".';
        break;
      }

      $items[] = [
        'name'   => $naam,
        'url'    => $url,
        'logo'   => $resultaat['logo'],
        'width'  => (int) ($resultaat['width'] ?? 0),
        'height' => (int) ($resultaat['height'] ?? 0),
      ];
    }

    if ($sponsorFout) {
      $melding['sponsors'] = $sponsorFout;
      $meldingType['sponsors'] = 'fout';
    } elseif (schrijfJson($sponsorBestand, ['updated' => date('c'), 'items' => $items, 'cta' => $cta])) {
      $melding['sponsors'] = 'Opgeslagen. De sponsoren op de website zijn bijgewerkt.';
      $meldingType['sponsors'] = 'ok';
      schrijfLog($logBestand, $huidigeGebruiker, 'sponsors', count($items) . ' sponsor(s) opgeslagen');
    } else {
      $melding['sponsors'] = 'Opslaan mislukt. Controleer de schrijfrechten van de map data op de server.';
      $meldingType['sponsors'] = 'fout';
    }

  } elseif ($formulier === 'contact') {
    $email = trim($_POST['email'] ?? '');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $melding['contact'] = 'Vul een geldig e-mailadres in.';
      $meldingType['contact'] = 'fout';
    } else {
      $facebook = trim($_POST['facebook'] ?? '');
      if ($facebook !== '' && !preg_match('#^https?://#i', $facebook)) {
        $melding['contact'] = 'Facebook-link moet beginnen met http:// of https://.';
        $meldingType['contact'] = 'fout';
      } else {
        // Alle drie de dagen komen uit een keuzemenu, dus altijd HH:MM. Dat
        // "woensdag alleen bij voldoende animo doorgaat" is geen vrije tekst
        // meer maar een stand, zodat het ook in het Engels en Duits klopt.
        $tijdOpties = contactTijdOpties();
        $statusOpties = contactStatusOpties();
        $vanTot = function($dag) use ($tijdOpties, $statusOpties) {
          $van = $_POST['openingstijden'][$dag]['van'] ?? '';
          $tot = $_POST['openingstijden'][$dag]['tot'] ?? '';
          $status = $_POST['openingstijden'][$dag]['status'] ?? '';
          if (!in_array($van, $tijdOpties, true)) $van = $tijdOpties[0];
          if (!in_array($tot, $tijdOpties, true)) $tot = $tijdOpties[0];
          if (!isset($statusOpties[$status])) $status = 'open';
          // Het vervalmoment wordt bij elke opslag opnieuw bepaald. Dat levert
          // steeds dezelfde datum op zolang die dag nog moet komen, en een
          // verlopen sluiting staat op dat moment toch al weer op open.
          $statusTot = in_array($status, contactVasteStanden(), true) ? '' : contactVervalMoment($dag);
          return ['van' => $van, 'tot' => $tot, 'status' => $status, 'status_tot' => $statusTot];
        };
        $contactData = [
          'adres_straat' => kort($_POST['adres_straat'] ?? '', 80),
          'adres_postcode_plaats' => kort($_POST['adres_postcode_plaats'] ?? '', 80),
          'openingstijden' => [
            'woensdag' => $vanTot('woensdag'),
            'zaterdag' => $vanTot('zaterdag'),
            'zondag'   => $vanTot('zondag'),
          ],
          'lidmaatschap_vanaf' => kort($_POST['lidmaatschap_vanaf'] ?? '', 60),
          'email' => $email,
          'facebook' => $facebook,
          // Toont op de website wanneer de openingstijden voor het laatst zijn
          // aangepast ("Laatste update: ..." bij de openingstijden-kaart en de
          // info-balk). Dit formulier bevat ook adres/e-mail/Facebook, dus deze
          // tijd verschuift strikt genomen ook als alleen dat wijzigt, maar in
          // de praktijk wordt dit formulier vrijwel altijd voor de
          // openingstijden zelf gebruikt.
          'updated' => date('c'),
        ];
        if (schrijfJson($contactBestand, $contactData)) {
          $melding['contact'] = 'Opgeslagen. De contactgegevens en openingstijden op de website zijn bijgewerkt.';
          $meldingType['contact'] = 'ok';
          // In het logboek is vooral terug te willen zien wanneer een dag is
          // gesloten, alleen voor leden open staat of weer is vrijgegeven, dus
          // dat komt er los bij.
          $afwijkendeDagen = [];
          foreach (['woensdag', 'zaterdag', 'zondag'] as $dag) {
            $status = $contactData['openingstijden'][$dag]['status'] ?? 'open';
            if (!in_array($status, contactVasteStanden(), true)) $afwijkendeDagen[] = $dag . ' (' . $status . ')';
          }
          $logTekst = 'contactgegevens bijgewerkt';
          $logTekst .= $afwijkendeDagen ? ', afwijkende stand: ' . implode(' + ', $afwijkendeDagen) : ', alle dagen op hun normale stand';
          schrijfLog($logBestand, $huidigeGebruiker, 'contact', $logTekst);
        } else {
          $melding['contact'] = 'Opslaan mislukt. Controleer de schrijfrechten van de map data op de server.';
          $meldingType['contact'] = 'fout';
        }
      }
    }

  } elseif ($formulier === 'media') {
    $items = [];
    $mediaFout = null;
    foreach (($_POST['media'] ?? []) as $rij) {
      $titelNl = kort($rij['title_nl'] ?? '', 100);
      if ($titelNl === '') continue; // NL titel is verplicht, anders wordt de kaart niet getoond
      $link = trim($rij['link'] ?? '');
      if ($link !== '' && !preg_match('#^https?://#i', $link)) {
        $mediaFout = 'Link bij "' . $titelNl . '" moet beginnen met http:// of https://.';
        break;
      }
      $datum = datumNaarIso($rij['date'] ?? '');
      $icoon = ($rij['icoon'] ?? '📺') === '📰' ? '📰' : '📺';
      $items[] = [
        'date' => $datum,
        'bron' => kort($rij['bron'] ?? '', 60),
        'icoon' => $icoon,
        'title' => [
          'nl' => $titelNl,
          'en' => kort($rij['title_en'] ?? '', 100),
          'de' => kort($rij['title_de'] ?? '', 100),
        ],
        'desc' => [
          'nl' => kort($rij['desc_nl'] ?? '', 300),
          'en' => kort($rij['desc_en'] ?? '', 300),
          'de' => kort($rij['desc_de'] ?? '', 300),
        ],
        'link' => $link,
        'linktekst' => [
          'nl' => kort($rij['linktekst_nl'] ?? '', 40),
          'en' => kort($rij['linktekst_en'] ?? '', 40),
          'de' => kort($rij['linktekst_de'] ?? '', 40),
        ],
      ];
    }
    if ($mediaFout) {
      $melding['media'] = $mediaFout;
      $meldingType['media'] = 'fout';
    } elseif (schrijfJson($mediaBestand, $items)) {
      $melding['media'] = 'Opgeslagen. De media-pagina is bijgewerkt.';
      $meldingType['media'] = 'ok';
      schrijfLog($logBestand, $huidigeGebruiker, 'media', count($items) . ' item(s) opgeslagen');
    } else {
      $melding['media'] = 'Opslaan mislukt. Controleer de schrijfrechten van de map data op de server.';
      $meldingType['media'] = 'fout';
    }

  } elseif ($formulier === 'nieuws') {
    $items = [];
    $nieuwsFout = null;
    foreach (($_POST['nieuws'] ?? []) as $rij) {
      $titelNl = kort($rij['title_nl'] ?? '', 100);
      if ($titelNl === '') continue; // NL titel is verplicht, anders wordt de kaart niet getoond
      $link = trim($rij['link'] ?? '');
      if ($link !== '' && !preg_match('#^https?://#i', $link)) {
        $nieuwsFout = 'Link bij "' . $titelNl . '" moet beginnen met http:// of https://.';
        break;
      }
      $datum = datumNaarIso($rij['date'] ?? '');
      $items[] = [
        'date' => $datum,
        'title' => [
          'nl' => $titelNl,
          'en' => kort($rij['title_en'] ?? '', 100),
          'de' => kort($rij['title_de'] ?? '', 100),
        ],
        'desc' => [
          'nl' => kort($rij['desc_nl'] ?? '', 300),
          'en' => kort($rij['desc_en'] ?? '', 300),
          'de' => kort($rij['desc_de'] ?? '', 300),
        ],
        'link' => $link,
        'linktekst' => [
          'nl' => kort($rij['linktekst_nl'] ?? '', 40),
          'en' => kort($rij['linktekst_en'] ?? '', 40),
          'de' => kort($rij['linktekst_de'] ?? '', 40),
        ],
      ];
    }
    if ($nieuwsFout) {
      $melding['nieuws'] = $nieuwsFout;
      $meldingType['nieuws'] = 'fout';
    } elseif (schrijfJson($nieuwsBestand, $items)) {
      $melding['nieuws'] = 'Opgeslagen. Het nieuwsblok op de homepage is bijgewerkt.';
      $meldingType['nieuws'] = 'ok';
      schrijfLog($logBestand, $huidigeGebruiker, 'nieuws', count($items) . ' item(s) opgeslagen');
    } else {
      $melding['nieuws'] = 'Opslaan mislukt. Controleer de schrijfrechten van de map data op de server.';
      $meldingType['nieuws'] = 'fout';
    }

  } elseif ($formulier === 'changelog_toevoegen' || $formulier === 'changelog_bewerken') {
    // Eén blok voor toevoegen en bewerken: de velden en de controles zijn
    // hetzelfde, alleen het wegschrijven verschilt (vooraan de lijst zetten
    // of een bestaande regel overschrijven).
    $clDatum = datumNaarIso($_POST['datum'] ?? '');
    if ($clDatum === '') $clDatum = date('Y-m-d');
    $clCat = (string) ($_POST['cat'] ?? '');
    if (!isset($changelogCategorieen[$clCat])) $clCat = 'nieuw';
    $clTitel = kort($_POST['titel'] ?? '', 120);
    $clTekst = kort($_POST['tekst'] ?? '', 500);

    if ($clTitel === '') {
      $melding['changelog'] = 'Vul in ieder geval een korte omschrijving in.';
      $meldingType['changelog'] = 'fout';
    } else {
      $clRegels = laadChangelogEigen($changelogBestand);

      if ($formulier === 'changelog_toevoegen') {
        array_unshift($clRegels, [
          'id' => date('YmdHis') . '-' . bin2hex(random_bytes(3)),
          'datum' => $clDatum,
          'cat' => $clCat,
          'titel' => $clTitel,
          'tekst' => $clTekst,
          'door' => $huidigeGebruiker,
          'toegevoegd' => date('c'),
        ]);
        $clMeldingOk = 'Toegevoegd aan de changelog.';
      } else {
        $clId = (string) ($_POST['id'] ?? '');
        $clGevonden = false;
        foreach ($clRegels as $i => $r) {
          if ((string) ($r['id'] ?? '') === $clId && $clId !== '') {
            $clRegels[$i]['datum'] = $clDatum;
            $clRegels[$i]['cat'] = $clCat;
            $clRegels[$i]['titel'] = $clTitel;
            $clRegels[$i]['tekst'] = $clTekst;
            $clRegels[$i]['gewijzigd'] = date('c');
            $clRegels[$i]['gewijzigd_door'] = $huidigeGebruiker;
            $clGevonden = true;
            break;
          }
        }
        if (!$clGevonden) {
          $melding['changelog'] = 'Die regel bestaat niet (meer). Ververs de pagina.';
          $meldingType['changelog'] = 'fout';
          $clRegels = null;
        }
        $clMeldingOk = 'Regel bijgewerkt.';
      }

      if (is_array($clRegels)) {
        if (schrijfJson($changelogBestand, $clRegels)) {
          $melding['changelog'] = $clMeldingOk;
          $meldingType['changelog'] = 'ok';
          schrijfLog($logBestand, $huidigeGebruiker, 'changelog', ($formulier === 'changelog_toevoegen' ? 'toegevoegd: ' : 'bijgewerkt: ') . $clTitel);
        } else {
          $melding['changelog'] = 'Opslaan mislukt. Controleer de schrijfrechten van de map data op de server.';
          $meldingType['changelog'] = 'fout';
        }
      }
    }

  } elseif ($formulier === 'changelog_verwijderen') {
    $clId = (string) ($_POST['id'] ?? '');
    $clRegels = laadChangelogEigen($changelogBestand);
    $clOver = [];
    $clWeg = '';
    foreach ($clRegels as $r) {
      if ((string) ($r['id'] ?? '') === $clId && $clId !== '') {
        $clWeg = (string) ($r['titel'] ?? '');
        continue;
      }
      $clOver[] = $r;
    }
    if ($clWeg === '') {
      $melding['changelog'] = 'Die regel bestaat niet (meer). Ververs de pagina.';
      $meldingType['changelog'] = 'fout';
    } elseif (schrijfJson($changelogBestand, $clOver)) {
      $melding['changelog'] = 'Regel verwijderd.';
      $meldingType['changelog'] = 'ok';
      schrijfLog($logBestand, $huidigeGebruiker, 'changelog', 'verwijderd: ' . $clWeg);
    } else {
      $melding['changelog'] = 'Verwijderen mislukt. Controleer de schrijfrechten van de map data op de server.';
      $meldingType['changelog'] = 'fout';
    }

  } elseif ($formulier === 'homepage') {
    $nieuweHomepageData = [];
    foreach ($homepageVelden as $veld => $info) {
      $maxLengte = $info[1] === 'tekst' ? 100 : 600;
      $nieuweHomepageData[$veld] = [
        'nl' => kort($_POST['hp'][$veld]['nl'] ?? '', $maxLengte),
        'en' => kort($_POST['hp'][$veld]['en'] ?? '', $maxLengte),
        'de' => kort($_POST['hp'][$veld]['de'] ?? '', $maxLengte),
      ];
    }
    if (schrijfJson($homepageBestand, $nieuweHomepageData)) {
      $homepageData = $nieuweHomepageData;
      $melding['homepage'] = 'Opgeslagen. De homepage gebruikt meteen deze tekst.';
      $meldingType['homepage'] = 'ok';
      schrijfLog($logBestand, $huidigeGebruiker, 'homepage', 'teksten bijgewerkt');
    } else {
      $melding['homepage'] = 'Opslaan mislukt. Controleer de schrijfrechten van de map data op de server.';
      $meldingType['homepage'] = 'fout';
    }

  } elseif ($formulier === 'ontstaan') {
    $nieuweOntstaanData = [];
    foreach ($ontstaanVelden as $veld => $info) {
      $maxLengte = $info[1] === 'tekst' ? 150 : 600;
      $nieuweOntstaanData[$veld] = [
        'nl' => kort($_POST['ont'][$veld]['nl'] ?? '', $maxLengte),
        'en' => kort($_POST['ont'][$veld]['en'] ?? '', $maxLengte),
        'de' => kort($_POST['ont'][$veld]['de'] ?? '', $maxLengte),
      ];
    }
    if (schrijfJson($ontstaanBestand, $nieuweOntstaanData)) {
      $ontstaanData = $nieuweOntstaanData;
      $melding['ontstaan'] = 'Opgeslagen. De pagina "Het ontstaan" gebruikt meteen deze tekst.';
      $meldingType['ontstaan'] = 'ok';
      schrijfLog($logBestand, $huidigeGebruiker, 'ontstaan', 'teksten bijgewerkt');
    } else {
      $melding['ontstaan'] = 'Opslaan mislukt. Controleer de schrijfrechten van de map data op de server.';
      $meldingType['ontstaan'] = 'fout';
    }

  } elseif ($formulier === 'baanreglement') {
    $nieuweBaanreglementData = [];
    foreach ($baanreglementVelden as $veld => $info) {
      $maxLengte = $info[1] === 'tekst' ? 200 : 3000;
      $nieuweBaanreglementData[$veld] = [
        'nl' => kort($_POST['br'][$veld]['nl'] ?? '', $maxLengte),
        'en' => kort($_POST['br'][$veld]['en'] ?? '', $maxLengte),
        'de' => kort($_POST['br'][$veld]['de'] ?? '', $maxLengte),
      ];
    }
    if (schrijfJson($baanreglementBestand, $nieuweBaanreglementData)) {
      $baanreglementData = $nieuweBaanreglementData;
      $melding['baanreglement'] = 'Opgeslagen. Het baanreglement gebruikt meteen deze tekst.';
      $meldingType['baanreglement'] = 'ok';
      schrijfLog($logBestand, $huidigeGebruiker, 'baanreglement', 'teksten bijgewerkt');
    } else {
      $melding['baanreglement'] = 'Opslaan mislukt. Controleer de schrijfrechten van de map data op de server.';
      $meldingType['baanreglement'] = 'fout';
    }

  } elseif ($formulier === 'bedankt') {
    $ibanNummerNieuw = trim($_POST['iban_number'] ?? '');
    $ibanNaamNlNieuw = trim($_POST['bd']['iban_name']['nl'] ?? '');
    if ($ibanNummerNieuw === '') {
      $melding['bedankt'] = 'Vul een IBAN-nummer in.';
      $meldingType['bedankt'] = 'fout';
    } elseif ($ibanNaamNlNieuw === '') {
      $melding['bedankt'] = 'Vul de naam van de rekeninghouder in (Nederlands).';
      $meldingType['bedankt'] = 'fout';
    } else {
      $nieuweBedanktData = [
        'iban_number' => kort($ibanNummerNieuw, 40),
      ];
      foreach ($bedanktVelden as $veld => $info) {
        $maxLengte = $info[1] === 'tekst' ? 200 : 500;
        $nieuweBedanktData[$veld] = [
          'nl' => kort($_POST['bd'][$veld]['nl'] ?? '', $maxLengte),
          'en' => kort($_POST['bd'][$veld]['en'] ?? '', $maxLengte),
          'de' => kort($_POST['bd'][$veld]['de'] ?? '', $maxLengte),
        ];
      }
      if (schrijfJson($bedanktBestand, $nieuweBedanktData)) {
        $bedanktData = $nieuweBedanktData;
        $melding['bedankt'] = 'Opgeslagen. De bedankt-pagina gebruikt meteen deze gegevens.';
        $meldingType['bedankt'] = 'ok';
        schrijfLog($logBestand, $huidigeGebruiker, 'bedankt', 'betaalgegevens bijgewerkt');
      } else {
        $melding['bedankt'] = 'Opslaan mislukt. Controleer de schrijfrechten van de map data op de server.';
        $meldingType['bedankt'] = 'fout';
      }
    }

  } elseif ($formulier === 'aanmelden') {
    $nieuweAanmeldenData = [];
    foreach ($aanmeldenVelden as $veld => $info) {
      $maxLengte = $info[1] === 'tekst' ? 200 : 500;
      $nieuweAanmeldenData[$veld] = [
        'nl' => kort($_POST['am'][$veld]['nl'] ?? '', $maxLengte),
        'en' => kort($_POST['am'][$veld]['en'] ?? '', $maxLengte),
        'de' => kort($_POST['am'][$veld]['de'] ?? '', $maxLengte),
      ];
    }
    if (schrijfJson($aanmeldenBestand, $nieuweAanmeldenData)) {
      $aanmeldenData = $nieuweAanmeldenData;
      $melding['aanmelden'] = 'Opgeslagen. De aanmeldpagina gebruikt meteen deze tekst.';
      $meldingType['aanmelden'] = 'ok';
      schrijfLog($logBestand, $huidigeGebruiker, 'aanmelden', 'teksten bijgewerkt');
    } else {
      $melding['aanmelden'] = 'Opslaan mislukt. Controleer de schrijfrechten van de map data op de server.';
      $meldingType['aanmelden'] = 'fout';
    }

  } elseif ($formulier === 'media_tekst') {
    $nieuweMediaTekstData = [
      'hero_sub' => [
        'nl' => kort($_POST['mt']['hero_sub']['nl'] ?? '', 400),
        'en' => kort($_POST['mt']['hero_sub']['en'] ?? '', 400),
        'de' => kort($_POST['mt']['hero_sub']['de'] ?? '', 400),
      ],
    ];
    if (schrijfJson($mediaTekstBestand, $nieuweMediaTekstData)) {
      $mediaTekstData = $nieuweMediaTekstData;
      $melding['media_tekst'] = 'Opgeslagen. De media-pagina gebruikt meteen deze tekst.';
      $meldingType['media_tekst'] = 'ok';
      schrijfLog($logBestand, $huidigeGebruiker, 'media_tekst', 'ondertitel bijgewerkt');
    } else {
      $melding['media_tekst'] = 'Opslaan mislukt. Controleer de schrijfrechten van de map data op de server.';
      $meldingType['media_tekst'] = 'fout';
    }

  } elseif ($formulier === 'fotoboek_tekst') {
    $nieuweFotoboekTekstData = [
      'hero_sub' => [
        'nl' => kort($_POST['ft']['hero_sub']['nl'] ?? '', 400),
        'en' => kort($_POST['ft']['hero_sub']['en'] ?? '', 400),
        'de' => kort($_POST['ft']['hero_sub']['de'] ?? '', 400),
      ],
    ];
    if (schrijfJson($fotoboekTekstBestand, $nieuweFotoboekTekstData)) {
      $fotoboekTekstData = $nieuweFotoboekTekstData;
      $melding['fotoboek_tekst'] = 'Opgeslagen. De fotoboek-pagina gebruikt meteen deze tekst.';
      $meldingType['fotoboek_tekst'] = 'ok';
      schrijfLog($logBestand, $huidigeGebruiker, 'fotoboek_tekst', 'ondertitel bijgewerkt');
    } else {
      $melding['fotoboek_tekst'] = 'Opslaan mislukt. Controleer de schrijfrechten van de map data op de server.';
      $meldingType['fotoboek_tekst'] = 'fout';
    }

  } elseif ($formulier === 'rekentabel') {
    $jaar = trim($_POST['jaar'] ?? '');
    $inschrijfkostenNieuw   = str_replace(',', '.', trim($_POST['inschrijfkosten'] ?? ''));
    $jeugdJaarbedragNieuw   = str_replace(',', '.', trim($_POST['jeugd_jaarbedrag'] ?? ''));
    $seniorJaarbedragNieuw  = str_replace(',', '.', trim($_POST['senior_jaarbedrag'] ?? ''));
    $jeugdLeeftijdNieuw     = trim($_POST['jeugd_leeftijd_tot'] ?? '');
    // Leeg mag: dan is de contributie voor volgend jaar nog niet vastgesteld
    // en geldt overal het bedrag van dit jaar.
    $jeugdVolgendNieuw      = str_replace(',', '.', trim($_POST['jeugd_jaarbedrag_volgend'] ?? ''));
    $seniorVolgendNieuw     = str_replace(',', '.', trim($_POST['senior_jaarbedrag_volgend'] ?? ''));

    if ($jaar === '' || !preg_match('/^\d{4}$/', $jaar)) {
      $melding['rekentabel'] = 'Vul een geldig jaartal in (bijv. 2026).';
      $meldingType['rekentabel'] = 'fout';
    } elseif (!is_numeric($inschrijfkostenNieuw) || $inschrijfkostenNieuw < 0) {
      $melding['rekentabel'] = 'Inschrijfkosten moet een bedrag van 0 of hoger zijn.';
      $meldingType['rekentabel'] = 'fout';
    } elseif (!is_numeric($jeugdJaarbedragNieuw) || $jeugdJaarbedragNieuw < 0) {
      $melding['rekentabel'] = 'Jaarbedrag jeugd moet een bedrag van 0 of hoger zijn.';
      $meldingType['rekentabel'] = 'fout';
    } elseif (!is_numeric($seniorJaarbedragNieuw) || $seniorJaarbedragNieuw < 0) {
      $melding['rekentabel'] = 'Jaarbedrag senior moet een bedrag van 0 of hoger zijn.';
      $meldingType['rekentabel'] = 'fout';
    } elseif (!ctype_digit($jeugdLeeftijdNieuw) || (int) $jeugdLeeftijdNieuw < 1 || (int) $jeugdLeeftijdNieuw > 99) {
      $melding['rekentabel'] = 'Leeftijdsgrens jeugd moet een heel getal tussen 1 en 99 zijn.';
      $meldingType['rekentabel'] = 'fout';
    } elseif ($jeugdVolgendNieuw !== '' && (!is_numeric($jeugdVolgendNieuw) || $jeugdVolgendNieuw < 0)) {
      $melding['rekentabel'] = 'Jaarbedrag jeugd volgend jaar moet leeg zijn of een bedrag van 0 of hoger.';
      $meldingType['rekentabel'] = 'fout';
    } elseif ($seniorVolgendNieuw !== '' && (!is_numeric($seniorVolgendNieuw) || $seniorVolgendNieuw < 0)) {
      $melding['rekentabel'] = 'Jaarbedrag senior volgend jaar moet leeg zijn of een bedrag van 0 of hoger.';
      $meldingType['rekentabel'] = 'fout';
    } else {
      $nieuweData = [
        'jaar' => $jaar,
        'inschrijfkosten' => (float) $inschrijfkostenNieuw,
        // Jaarcontributie in hele euro's, ook als er via een handmatige
        // post-request toch een bedrag met centen binnenkomt.
        'jeugd_jaarbedrag' => (float) round((float) $jeugdJaarbedragNieuw),
        'senior_jaarbedrag' => (float) round((float) $seniorJaarbedragNieuw),
        'jeugd_leeftijd_tot' => (int) $jeugdLeeftijdNieuw,
        // Leeg blijft leeg (nog niet vastgesteld), anders hele euro's.
        'jeugd_jaarbedrag_volgend' => $jeugdVolgendNieuw === '' ? '' : (float) round((float) $jeugdVolgendNieuw),
        'senior_jaarbedrag_volgend' => $seniorVolgendNieuw === '' ? '' : (float) round((float) $seniorVolgendNieuw),
      ];
      if (schrijfJson($rekentabelBestand, $nieuweData)) {
        $rekentabelData = $nieuweData;
        $inschrijfkosten = (float) $nieuweData['inschrijfkosten'];
        $tabelJeugd  = rekentabelProRata((float) $nieuweData['jeugd_jaarbedrag']);
        $tabelSenior = rekentabelProRata((float) $nieuweData['senior_jaarbedrag']);
        $melding['rekentabel'] = 'Opgeslagen. De rekentabel en de contributiecalculator op aanmelden.html gebruiken meteen deze bedragen.';
        $meldingType['rekentabel'] = 'ok';
        $volgendJaarLog = ($jeugdVolgendNieuw === '' && $seniorVolgendNieuw === '')
          ? 'volgend jaar nog niet vastgesteld'
          : 'volgend jaar jeugd €' . ($jeugdVolgendNieuw === '' ? '-' : $jeugdVolgendNieuw) . ', senior €' . ($seniorVolgendNieuw === '' ? '-' : $seniorVolgendNieuw);
        schrijfLog($logBestand, $huidigeGebruiker, 'rekentabel', "jaar $jaar, inschrijfkosten €$inschrijfkostenNieuw, jeugd €$jeugdJaarbedragNieuw, senior €$seniorJaarbedragNieuw, $volgendJaarLog");
      } else {
        $melding['rekentabel'] = 'Opslaan mislukt. Controleer de schrijfrechten van de map data op de server.';
        $meldingType['rekentabel'] = 'fout';
      }
    }

  } elseif ($formulier === 'fotoboek_album_aanmaken') {
    $titelNl = kort($_POST['titel_nl'] ?? '', 60);
    if ($titelNl === '') {
      $melding['fotoboek'] = 'Vul een Nederlandse titel in voor het nieuwe album.';
      $meldingType['fotoboek'] = 'fout';
    } else {
      $fotoboekData = ['albums' => []];
      if (file_exists($fotoboekBestand)) {
        $json = json_decode(file_get_contents($fotoboekBestand), true);
        if (is_array($json) && isset($json['albums'])) $fotoboekData = $json;
      }
      $bestaandeSlugs = array_map(function($a) { return $a['slug']; }, $fotoboekData['albums']);
      $slug = uniekeSlug(maakSlug($titelNl), $bestaandeSlugs);

      if (!is_dir($fotoboekMap . '/' . $slug . '/thumbs') && !mkdir($fotoboekMap . '/' . $slug . '/thumbs', 0755, true)) {
        $melding['fotoboek'] = 'Aanmaken mislukt. Controleer de schrijfrechten van de map images op de server.';
        $meldingType['fotoboek'] = 'fout';
      } else {
        $fotoboekData['albums'][] = [
          'slug' => $slug,
          'title' => ['nl' => $titelNl, 'en' => kort($_POST['titel_en'] ?? '', 60), 'de' => kort($_POST['titel_de'] ?? '', 60)],
          'date' => date('Y-m-d'),
          'volgorde' => count($fotoboekData['albums']),
          'cover' => '',
          'verborgen' => false,
          'beschrijving' => ['nl' => '', 'en' => '', 'de' => ''],
          'photos' => [],
        ];
        if (schrijfJson($fotoboekBestand, $fotoboekData)) {
          schrijfLog($logBestand, $huidigeGebruiker, 'fotoboek_album_aangemaakt', $titelNl);
          // Post-Redirect-Get: zonder deze redirect blijft deze pagina het
          // resultaat van een POST. Ververst iemand die pagina dan per ongeluk
          // (bijv. tijdens een lang durende foto-upload verderop), dan vraagt
          // de browser om het formulier opnieuw te verzenden - en dat maakt
          // een tweede album met dezelfde naam aan. De melding gaat via de
          // sessie mee naar de volgende (GET-)weergave van deze pagina.
          $_SESSION['flash'] = ['fotoboek' => [
            'tekst' => 'Album "' . $titelNl . '" is aangemaakt. Voeg hieronder foto\'s toe.',
            'type' => 'ok',
          ]];
          header('Location: beheer.php#fotoboek');
          exit;
        } else {
          $melding['fotoboek'] = 'Opslaan mislukt. Controleer de schrijfrechten van de map data op de server.';
          $meldingType['fotoboek'] = 'fout';
        }
      }
    }

  } elseif ($formulier === 'fotoboek_album_bewerken') {
    $slug = trim($_POST['slug'] ?? '');
    $fotoboekData = ['albums' => []];
    if (file_exists($fotoboekBestand)) {
      $json = json_decode(file_get_contents($fotoboekBestand), true);
      if (is_array($json) && isset($json['albums'])) $fotoboekData = $json;
    }
    $albumIndex = null;
    foreach ($fotoboekData['albums'] as $i => $a) {
      if ($a['slug'] === $slug) { $albumIndex = $i; break; }
    }

    if ($albumIndex === null) {
      $melding['fotoboek'] = 'Album niet gevonden, mogelijk al verwijderd. Ververs de pagina.';
      $meldingType['fotoboek'] = 'fout';
    } elseif (!empty($_POST['album_verwijderen'])) {
      // ===== Album verwijderen: inclusief de map met alle foto's en thumbnails =====
      $titelVoorMelding = $fotoboekData['albums'][$albumIndex]['title']['nl'] ?? $slug;
      verwijderMapRecursief($fotoboekMap . '/' . $slug);
      array_splice($fotoboekData['albums'], $albumIndex, 1);
      if (schrijfJson($fotoboekBestand, $fotoboekData)) {
        schrijfLog($logBestand, $huidigeGebruiker, 'fotoboek_album_verwijderd', $titelVoorMelding);
        $_SESSION['flash'] = ['fotoboek' => [
          'tekst' => 'Album "' . $titelVoorMelding . '" en alle bijbehorende foto\'s zijn verwijderd.',
          'type' => 'ok',
        ]];
        header('Location: beheer.php#fotoboek');
        exit;
      } else {
        $melding['fotoboek'] = 'Verwijderen mislukt. Controleer de schrijfrechten van de map data op de server.';
        $meldingType['fotoboek'] = 'fout';
      }
    } else {
      // ===== Album bijwerken: titel, volgorde, bijschriften, cover, verwijderde foto's, nieuwe uploads =====
      $album = $fotoboekData['albums'][$albumIndex];
      $wasVerborgen = !empty($album['verborgen']);

      $titelNl = kort($_POST['titel_nl'] ?? '', 60);
      if ($titelNl !== '') $album['title']['nl'] = $titelNl;
      $album['title']['en'] = kort($_POST['titel_en'] ?? '', 60);
      $album['title']['de'] = kort($_POST['titel_de'] ?? '', 60);
      $album['volgorde'] = is_numeric($_POST['volgorde'] ?? null) ? (float) $_POST['volgorde'] : ($album['volgorde'] ?? $albumIndex);
      $nieuweAlbumDatum = datumNaarIso($_POST['datum'] ?? '');
      if ($nieuweAlbumDatum !== '') $album['date'] = $nieuweAlbumDatum;
      $album['verborgen'] = !empty($_POST['album_verborgen']);
      // Kort verhaal onder de titel: helemaal optioneel, mag ook leeggemaakt
      // worden (in tegenstelling tot de titel valt dit dus niet terug op de
      // vorige waarde als het veld leeg wordt opgeslagen).
      $album['beschrijving'] = [
        'nl' => kort($_POST['beschrijving_nl'] ?? '', 600),
        'en' => kort($_POST['beschrijving_en'] ?? '', 600),
        'de' => kort($_POST['beschrijving_de'] ?? '', 600),
      ];

      // Bestaande foto's: bijschriften bijwerken, gemarkeerde foto's verwijderen (bestand + thumbnail van schijf),
      // en desgewenst alsnog een watermerk toevoegen aan een foto die dat nog niet heeft.
      //
      // Belangrijk: dit past ALLEEN foto's aan die daadwerkelijk in $_POST['foto']
      // voorkomen, en herbouwt $album['photos'] nooit meer helemaal opnieuw.
      // Eerder deed dit veld dienst als "dit zijn ALLE foto's die mogen
      // blijven staan" - alles wat er niet in voorkwam werd stilzwijgend
      // weggegooid. Dat bleek een keer te hard te kunnen toeslaan: bij een
      // grote foto-upload in losse batches (zie JS) kent een verzoek de
      // foto's van eerdere batches niet, en ook een oude, per ongeluk
      // opnieuw verzonden pagina (bijv. via de "formulier opnieuw
      // verzenden"-vraag van de browser na een refresh) kan een verouderd
      // en onvolledig lijstje meesturen. Met deze aanpak kan zoiets nooit
      // meer foto's laten verdwijnen die niet expliciet zijn aangevinkt om
      // verwijderd te worden.
      $gekozenCoverIndex = $_POST['cover'] ?? null;
      $nieuweCover = '';
      $watermerkToegevoegdTeller = 0;
      $teVerwijderen = [];
      if (isset($_POST['foto']) && is_array($_POST['foto'])) {
        foreach ($_POST['foto'] as $i => $rij) {
          $bestand = basename($rij['bestand'] ?? '');
          if ($bestand === '') continue;

          $fotoIndex = null;
          foreach ($album['photos'] as $pi => $p) { if ($p['file'] === $bestand) { $fotoIndex = $pi; break; } }
          if ($fotoIndex === null) continue;
          $isVideo = ($album['photos'][$fotoIndex]['type'] ?? 'photo') === 'video';

          if (!empty($rij['verwijderen'])) {
            $teVerwijderen[] = $bestand;
            continue;
          }

          $album['photos'][$fotoIndex]['caption'] = [
            'nl' => kort($rij['caption_nl'] ?? '', 150),
            'en' => kort($rij['caption_en'] ?? '', 150),
            'de' => kort($rij['caption_de'] ?? '', 150),
          ];

          // Let op: hier NIET controleren of ['watermerk'] al true is.
          // Dat vlaggetje kan stiekem niet meer kloppen met het echte bestand (bijv.
          // nadat een bestand buiten beheer.php om is teruggezet), dus een vinkje
          // hier moet altijd echt opnieuw het watermerk zetten, ongeacht de huidige vlag.
          // Video's slaan dit altijd over: watermerkeren gebeurt met GD en werkt niet op video.
          if (!$isVideo && !empty($rij['watermerk_toevoegen'])) {
            if (fotoboekWatermerkBestaandeFoto($fotoboekMap . '/' . $slug . '/' . $bestand, $logoPad)) {
              $album['photos'][$fotoIndex]['watermerk'] = true;
              $watermerkToegevoegdTeller++;
            }
          }

          if (!$isVideo && $gekozenCoverIndex !== null && (string) $i === (string) $gekozenCoverIndex) $nieuweCover = $bestand;
        }
      }
      if ($teVerwijderen) {
        foreach ($album['photos'] as $p) {
          if (!in_array($p['file'], $teVerwijderen, true)) continue;
          @unlink($fotoboekMap . '/' . $slug . '/' . $p['file']);
          @unlink($fotoboekMap . '/' . $slug . '/thumbs/' . $p['file']);
          if (!empty($p['poster'])) {
            @unlink($fotoboekMap . '/' . $slug . '/' . $p['poster']);
            @unlink($fotoboekMap . '/' . $slug . '/thumbs/' . $p['poster']);
          }
        }
        $album['photos'] = array_values(array_filter($album['photos'], function($p) use ($teVerwijderen) {
          return !in_array($p['file'], $teVerwijderen, true);
        }));
      }

      // Nieuwe foto's en video's uploaden. Video's (mp4) worden herkend aan de
      // extensie en gaan een ander pad in: geen GD-verwerking (dat kan niet op
      // video), wel wordt een eventueel meegestuurde browser-thumbnail
      // (video_poster, een data-URL) opgeslagen als voorbeeldbeeld.
      $watermerkAan = !empty($_POST['watermerk']);
      $uploadFouten = [];
      $aantalGeupload = 0;
      // De batch-upload (zie JS) stuurt elke foto als een eigen verzoek. Een
      // foutmelding die alleen in DIT ene verzoek zou blijven staan, wordt
      // direct daarna overschreven door het volgende verzoek en is dan nooit
      // zichtbaar geweest - een foto die halverwege een upload van 98 mislukt
      // zou zo geruisloos verdwijnen. Daarom worden fouten hier verzameld in
      // de sessie, per album, en pas bij het laatste verzoek van de batch in
      // hun geheel getoond (en de teller weer gewist).
      $batchVerzoek = !empty($_POST['batch_start']) || !empty($_POST['batch_laatste']);
      if (!empty($_POST['batch_start'])) {
        $_SESSION['fotoboek_batch_fouten'][$slug] = [];
        $_SESSION['fotoboek_batch_totalen'][$slug] = ['geupload' => 0, 'watermerk' => 0];
      }
      if (!empty($_FILES['nieuwe_fotos']) && is_array($_FILES['nieuwe_fotos']['tmp_name'])) {
        // Bekende bestandsinhoud van dit album (sha1 van elk al opgeslagen
        // bestand), om dubbele uploads te herkennen aan de INHOUD, niet de
        // bestandsnaam. Komt van pas als een grote upload halverwege afbreekt
        // (bijv. door de tab te sluiten) en dezelfde foto's per ongeluk nog
        // een keer geselecteerd worden: die komen dan niet dubbel in het
        // album, in plaats van een tweede kopie met "-2" in de bestandsnaam.
        // Foto's van vóór deze functie hebben nog geen hash en worden dus
        // pas vanaf nu meegeteld.
        $bestaandeHashes = [];
        foreach ($album['photos'] as $p) {
          if (!empty($p['hash'])) $bestaandeHashes[$p['hash']] = true;
        }
        foreach ($_FILES['nieuwe_fotos']['tmp_name'] as $i => $tmpPad) {
          if ($_FILES['nieuwe_fotos']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
          $origineleNaam = $_FILES['nieuwe_fotos']['name'][$i] ?? 'bestand';
          if ($_FILES['nieuwe_fotos']['error'][$i] !== UPLOAD_ERR_OK) {
            $uploadFouten[] = $origineleNaam . ': uploaden mislukt.';
            continue;
          }

          $hash = @sha1_file($tmpPad);
          if ($hash && isset($bestaandeHashes[$hash])) {
            $uploadFouten[] = $origineleNaam . ': staat al in dit album, overgeslagen.';
            continue;
          }

          $extensie = strtolower(pathinfo($origineleNaam, PATHINFO_EXTENSION));
          $isVideoUpload = $extensie === 'mp4';

          if ($isVideoUpload && !$fotoboekVideoAan) {
            $uploadFouten[] = $origineleNaam . ': video-upload staat tijdelijk uit.';
            continue;
          }

          $basisNaamOrig = preg_replace('/[^a-z0-9]+/', '-', strtolower(pathinfo($origineleNaam, PATHINFO_FILENAME)));
          $basisNaamOrig = trim($basisNaamOrig, '-');
          if ($basisNaamOrig === '') $basisNaamOrig = $isVideoUpload ? 'video' : 'foto';

          if ($isVideoUpload) {
            if ($_FILES['nieuwe_fotos']['size'][$i] > $fotoboekMaxVideoBytes) {
              $uploadFouten[] = $origineleNaam . ': groter dan ' . (int) round($fotoboekMaxVideoBytes / 1024 / 1024) . ' MB.';
              continue;
            }
            if (function_exists('finfo_open')) {
              $finfo = finfo_open(FILEINFO_MIME_TYPE);
              $mimeType = $finfo ? finfo_file($finfo, $tmpPad) : false;
              if ($finfo) finfo_close($finfo);
              if ($mimeType && strpos($mimeType, 'video/') !== 0) {
                $uploadFouten[] = $origineleNaam . ': geen geldig video-bestand.';
                continue;
              }
            }

            // Bestandsnaam (video + eventuele poster) samen uniek maken, zodat
            // een video en zijn posterbestand altijd dezelfde basisnaam delen.
            $kandidaat = $basisNaamOrig;
            $teller = 2;
            while (file_exists($fotoboekMap . '/' . $slug . '/' . $kandidaat . '.mp4') || file_exists($fotoboekMap . '/' . $slug . '/' . $kandidaat . '.jpg')) {
              $kandidaat = $basisNaamOrig . '-' . $teller;
              $teller++;
            }
            $bestandsnaam = $kandidaat . '.mp4';
            $posterNaam   = $kandidaat . '.jpg';

            if (!move_uploaded_file($tmpPad, $fotoboekMap . '/' . $slug . '/' . $bestandsnaam)) {
              $uploadFouten[] = $origineleNaam . ': opslaan van de video op de server is mislukt.';
              continue;
            }

            $posterResultaat = ['ok' => false, 'width' => 0, 'height' => 0];
            $posterData = $_POST['video_poster'][$i] ?? '';
            if ($posterData !== '') {
              $posterResultaat = verwerkFotoboekVideoPoster(
                $posterData,
                $fotoboekMap . '/' . $slug . '/' . $posterNaam,
                $fotoboekMap . '/' . $slug . '/thumbs/' . $posterNaam,
                $fotoboekMaxVolledig,
                $fotoboekMaxThumb
              );
            }

            $album['photos'][] = [
              'type' => 'video',
              'file' => $bestandsnaam,
              'poster' => $posterResultaat['ok'] ? $posterNaam : '',
              'width' => $posterResultaat['width'],
              'height' => $posterResultaat['height'],
              'caption' => ['nl' => '', 'en' => '', 'de' => ''],
              'hash' => $hash ?: null,
            ];
            if ($hash) $bestaandeHashes[$hash] = true;
            $aantalGeupload++;
            continue;
          }

          if ($_FILES['nieuwe_fotos']['size'][$i] > $fotoboekMaxFotoBytes) {
            $uploadFouten[] = $origineleNaam . ': groter dan ' . (int) round($fotoboekMaxFotoBytes / 1024 / 1024) . ' MB.';
            continue;
          }

          $bestandsnaam = $basisNaamOrig . '.jpg';
          $teller = 2;
          while (file_exists($fotoboekMap . '/' . $slug . '/' . $bestandsnaam)) {
            $bestandsnaam = $basisNaamOrig . '-' . $teller . '.jpg';
            $teller++;
          }

          $resultaat = verwerkFotoboekFoto(
            $tmpPad,
            $fotoboekMap . '/' . $slug . '/' . $bestandsnaam,
            $fotoboekMap . '/' . $slug . '/thumbs/' . $bestandsnaam,
            $watermerkAan,
            $logoPad,
            $fotoboekMaxVolledig,
            $fotoboekMaxThumb
          );
          if ($resultaat['ok']) {
            $album['photos'][] = [
              'type' => 'photo',
              'file' => $bestandsnaam,
              'width' => $resultaat['width'],
              'height' => $resultaat['height'],
              'caption' => ['nl' => '', 'en' => '', 'de' => ''],
              'watermerk' => $watermerkAan,
              'hash' => $hash ?: null,
            ];
            if ($hash) $bestaandeHashes[$hash] = true;
            $aantalGeupload++;
          } else {
            $uploadFouten[] = $origineleNaam . ': ' . $resultaat['fout'];
          }
        }
      }

      // Cover bepalen: expliciet gekozen, anders behouden, anders eerste
      // overgebleven fóto (nooit een video: die heeft geen bruikbaar
      // cover-bestand voor de albumkaart op de website).
      $eersteFotoBestand = null;
      foreach ($album['photos'] as $p) {
        if (($p['type'] ?? 'photo') !== 'video') { $eersteFotoBestand = $p['file']; break; }
      }
      $fotoBestanden = array_column(array_filter($album['photos'], function($p) { return ($p['type'] ?? 'photo') !== 'video'; }), 'file');
      if ($nieuweCover !== '') {
        $album['cover'] = $nieuweCover;
      } elseif (empty($album['cover']) || !in_array($album['cover'], $fotoBestanden, true)) {
        $album['cover'] = $eersteFotoBestand ?? '';
      }

      // Watermerk in één keer voor het hele album: verwerkt ALLE foto's, ook
      // de foto's die al als "watermerk: true" te boek staan. Dat is bewust:
      // dat vlaggetje zegt alleen wat er de vorige keer is opgeslagen, niet of
      // het bestand op schijf nu nog echt een watermerk heeft (dat kan uit de
      // pas zijn na een externe overschrijving), dus dit vinkje mag nooit een
      // foto overslaan. Video's slaan dit altijd over.
      if (!empty($_POST['album_watermerk_alle'])) {
        foreach ($album['photos'] as &$foto) {
          if (($foto['type'] ?? 'photo') === 'video') continue;
          if (fotoboekWatermerkBestaandeFoto($fotoboekMap . '/' . $slug . '/' . $foto['file'], $logoPad)) {
            $foto['watermerk'] = true;
            $watermerkToegevoegdTeller++;
          }
        }
        unset($foto);
      }

      $fotoboekData['albums'][$albumIndex] = $album;
      usort($fotoboekData['albums'], function($a, $b) { return ($a['volgorde'] ?? 0) <=> ($b['volgorde'] ?? 0); });

      // Fouten en aantallen van dit verzoek bij de rest van deze batch
      // optellen (zie hierboven bij $batchVerzoek). Bij een gewone,
      // niet-gebatchte opslag is er niets om bij op te tellen: dan gelden
      // gewoon de aantallen van dit ene verzoek.
      if ($batchVerzoek) {
        if (!isset($_SESSION['fotoboek_batch_fouten'][$slug]) || !is_array($_SESSION['fotoboek_batch_fouten'][$slug])) {
          $_SESSION['fotoboek_batch_fouten'][$slug] = [];
        }
        if ($uploadFouten) {
          $_SESSION['fotoboek_batch_fouten'][$slug] = array_merge($_SESSION['fotoboek_batch_fouten'][$slug], $uploadFouten);
        }
        $alleUploadFouten = $_SESSION['fotoboek_batch_fouten'][$slug];

        if (!isset($_SESSION['fotoboek_batch_totalen'][$slug]) || !is_array($_SESSION['fotoboek_batch_totalen'][$slug])) {
          $_SESSION['fotoboek_batch_totalen'][$slug] = ['geupload' => 0, 'watermerk' => 0];
        }
        $_SESSION['fotoboek_batch_totalen'][$slug]['geupload'] += $aantalGeupload;
        $_SESSION['fotoboek_batch_totalen'][$slug]['watermerk'] += $watermerkToegevoegdTeller;
        $totaalGeupload = $_SESSION['fotoboek_batch_totalen'][$slug]['geupload'];
        $totaalWatermerk = $_SESSION['fotoboek_batch_totalen'][$slug]['watermerk'];

        if (!empty($_POST['batch_laatste'])) {
          unset($_SESSION['fotoboek_batch_fouten'][$slug]);
          unset($_SESSION['fotoboek_batch_totalen'][$slug]);
        }
      } else {
        $alleUploadFouten = $uploadFouten;
        $totaalGeupload = $aantalGeupload;
        $totaalWatermerk = $watermerkToegevoegdTeller;
      }
      // Bij een batch-upload (één foto per verzoek, zie JS) alleen bij het
      // allerlaatste verzoek loggen en de melding tonen, met de opgetelde
      // aantallen van de hele batch. Anders zou een upload van 98 foto's ook
      // 98 bijna-identieke logregels opleveren, en zou de "X foto's
      // toegevoegd"-melding na afloop alleen de laatste foto meetellen.
      $ditIsHetMomentOmTeMeldenEnTeLoggen = !$batchVerzoek || !empty($_POST['batch_laatste']);

      if (schrijfJson($fotoboekBestand, $fotoboekData)) {
        $onderdelen = [];
        if ($totaalGeupload > 0) $onderdelen[] = $totaalGeupload . ' nieuwe foto(\'s) toegevoegd';
        if ($totaalWatermerk > 0) $onderdelen[] = $totaalWatermerk . ' foto(\'s) van een watermerk voorzien';
        if ($album['verborgen'] && !$wasVerborgen) $onderdelen[] = 'album verborgen op de website';
        if (!$album['verborgen'] && $wasVerborgen) $onderdelen[] = 'album weer zichtbaar op de website';
        $meldingTekst = 'Album opgeslagen' . ($onderdelen ? ': ' . implode(', ', $onderdelen) . '.' : '.');
        if ($alleUploadFouten) $meldingTekst .= ' Let op: ' . implode(' ', $alleUploadFouten);
        if ($ditIsHetMomentOmTeMeldenEnTeLoggen) {
          schrijfLog($logBestand, $huidigeGebruiker, 'fotoboek_album_bijgewerkt', $album['title']['nl'] . ($totaalGeupload ? ', ' . $totaalGeupload . ' upload(s)' : '') . ($totaalWatermerk ? ', ' . $totaalWatermerk . ' watermerk(en)' : '') . ($album['verborgen'] !== $wasVerborgen ? ', ' . ($album['verborgen'] ? 'verborgen' : 'weer zichtbaar') : ''));
        }
        // Post-Redirect-Get, zelfde reden als bij het aanmaken van een album:
        // zonder deze redirect blijft deze pagina het resultaat van een POST,
        // en kan een latere verversing (per ongeluk, of doordat de batch-
        // upload aan het einde de pagina herlaadt) de browser laten vragen om
        // dit formulier - met de foto-stand van OP DAT MOMENT - opnieuw te
        // verzenden. Dat verzenden gebeurt hier via fetch() (niet via een
        // echte paginanavigatie), dus dit heeft geen invloed op de lopende
        // batch-upload zelf.
        $_SESSION['flash'] = ['fotoboek' => [
          'tekst' => $meldingTekst,
          'type' => $alleUploadFouten ? 'fout' : 'ok',
        ]];
        header('Location: beheer.php#fotoboek');
        exit;
      } else {
        $melding['fotoboek'] = 'Opslaan mislukt. Controleer de schrijfrechten van de map data op de server.';
        $meldingType['fotoboek'] = 'fout';
      }
    }

  } elseif ($formulier === 'gebruiker_toevoegen') {
    $nieuweNaam = trim($_POST['nieuwe_gebruikersnaam'] ?? '');
    $nieuwWachtwoord = $_POST['nieuw_wachtwoord'] ?? '';
    $nieuwWachtwoordHerhaald = $_POST['nieuw_wachtwoord_herhaald'] ?? '';
    // Alleen bekende tabsleutels overnemen; onbekende waarden (geknoei met
    // het formulier) worden gewoon genegeerd.
    $gekozenTabs = array_values(array_intersect(array_keys($beheerTabsToewijsbaar), (array) ($_POST['tabs'] ?? [])));

    if ($nieuweNaam === '' || !preg_match('/^[a-zA-Z0-9._-]{2,30}$/', $nieuweNaam)) {
      $melding['gebruikers'] = 'Gebruikersnaam moet 2 tot 30 tekens zijn: letters, cijfers, punt, streepje of underscore.';
      $meldingType['gebruikers'] = 'fout';
    } elseif (strcasecmp($nieuweNaam, 'beheerder') === 0) {
      $melding['gebruikers'] = '"beheerder" is gereserveerd voor het beheerderswachtwoord, kies een andere gebruikersnaam.';
      $meldingType['gebruikers'] = 'fout';
    } elseif (strlen($nieuwWachtwoord) < 8) {
      $melding['gebruikers'] = 'Wachtwoord moet minstens 8 tekens zijn.';
      $meldingType['gebruikers'] = 'fout';
    } elseif ($nieuwWachtwoord !== $nieuwWachtwoordHerhaald) {
      $melding['gebruikers'] = 'De twee wachtwoorden komen niet overeen.';
      $meldingType['gebruikers'] = 'fout';
    } else {
      $gebruikers = laadGebruikers($usersBestand);
      $bestondAl = false;
      foreach ($gebruikers as &$g) {
        if (strcasecmp($g['gebruikersnaam'], $nieuweNaam) === 0) {
          // Alleen het wachtwoord: de toegang van een bestaande gebruiker
          // pas je hierboven per gebruiker aan, niet hier. Dit formulier
          // toont altijd alles aangevinkt (het is het "nieuwe gebruiker"-
          // formulier), dus zou anders per ongeluk bestaande beperkingen
          // resetten bij een simpele wachtwoord-reset.
          $g['hash'] = password_hash($nieuwWachtwoord, PASSWORD_DEFAULT);
          $bestondAl = true;
          break;
        }
      }
      unset($g);
      if (!$bestondAl) {
        $gebruikers[] = ['gebruikersnaam' => $nieuweNaam, 'hash' => password_hash($nieuwWachtwoord, PASSWORD_DEFAULT), 'aangemaakt' => date('c'), 'tabs' => $gekozenTabs];
      }
      if (schrijfGebruikers($usersBestand, $gebruikers)) {
        $melding['gebruikers'] = $bestondAl ? ('Wachtwoord van "' . $nieuweNaam . '" is bijgewerkt.') : ('Gebruiker "' . $nieuweNaam . '" is aangemaakt.');
        $meldingType['gebruikers'] = 'ok';
        schrijfLog($logBestand, $huidigeGebruiker, $bestondAl ? 'wachtwoord_reset' : 'gebruiker_aangemaakt', $nieuweNaam);
      } else {
        $melding['gebruikers'] = 'Opslaan mislukt. Controleer de schrijfrechten in de hoofdmap van de server.';
        $meldingType['gebruikers'] = 'fout';
      }
    }

  } elseif ($formulier === 'gebruiker_tabs_bijwerken') {
    // Alleen de toegang aanpassen, los van het wachtwoord: dit is de knop
    // per gebruiker in het overzicht, niet het formulier hieronder.
    $doelNaam = trim($_POST['gebruikersnaam'] ?? '');
    $gekozenTabs = array_values(array_intersect(array_keys($beheerTabsToewijsbaar), (array) ($_POST['tabs'] ?? [])));
    $gebruikers = laadGebruikers($usersBestand);
    $gevonden = false;
    foreach ($gebruikers as &$g) {
      if (isset($g['gebruikersnaam']) && strcasecmp($g['gebruikersnaam'], $doelNaam) === 0) {
        $g['tabs'] = $gekozenTabs;
        $gevonden = true;
        break;
      }
    }
    unset($g);
    if (!$gevonden) {
      $melding['gebruikers'] = 'Gebruiker niet gevonden.';
      $meldingType['gebruikers'] = 'fout';
    } elseif (schrijfGebruikers($usersBestand, $gebruikers)) {
      $melding['gebruikers'] = 'Toegang van "' . $doelNaam . '" is bijgewerkt.';
      $meldingType['gebruikers'] = 'ok';
      schrijfLog($logBestand, $huidigeGebruiker, 'toegang_bijgewerkt', $doelNaam . ': ' . ($gekozenTabs ? implode(', ', $gekozenTabs) : 'geen tabs'));
    } else {
      $melding['gebruikers'] = 'Opslaan mislukt. Controleer de schrijfrechten in de hoofdmap van de server.';
      $meldingType['gebruikers'] = 'fout';
    }

  } elseif ($formulier === 'gebruiker_verwijderen') {
    $teVerwijderen = trim($_POST['gebruikersnaam'] ?? '');
    $gebruikers = laadGebruikers($usersBestand);
    if (!$isMaster && strcasecmp($teVerwijderen, $huidigeGebruiker) === 0) {
      // Jezelf verwijderen terwijl je ermee ingelogd bent: dat is nooit de
      // bedoeling en je bent er meteen mee buitengesloten.
      $melding['gebruikers'] = 'Je kunt je eigen account niet verwijderen.';
      $meldingType['gebruikers'] = 'fout';
      $gebruikers = null;
    }
    if ($gebruikers === null) {
      // Al afgehandeld hierboven.
    } else {
    $nieuweLijst = array_values(array_filter($gebruikers, function($g) use ($teVerwijderen) {
      return !isset($g['gebruikersnaam']) || strcasecmp($g['gebruikersnaam'], $teVerwijderen) !== 0;
    }));
    if (count($nieuweLijst) === count($gebruikers)) {
      $melding['gebruikers'] = 'Gebruiker niet gevonden.';
      $meldingType['gebruikers'] = 'fout';
    } elseif (schrijfGebruikers($usersBestand, $nieuweLijst)) {
      $melding['gebruikers'] = 'Gebruiker "' . $teVerwijderen . '" is verwijderd.';
      $meldingType['gebruikers'] = 'ok';
      schrijfLog($logBestand, $huidigeGebruiker, 'gebruiker_verwijderd', $teVerwijderen);
    } else {
      $melding['gebruikers'] = 'Verwijderen mislukt. Controleer de schrijfrechten in de hoofdmap van de server.';
      $meldingType['gebruikers'] = 'fout';
    }
    }

  } elseif ($formulier === 'backup_herstellen') {
    $sleutel = $_POST['sleutel'] ?? '';
    // basename() eerst: het POST-veld mag nooit een pad bevatten, alleen een
    // kale bestandsnaam. Zo kan dit veld nooit gebruikt worden om buiten
    // data-backups/ te lezen.
    $backupBestandsnaam = basename($_POST['backup_bestand'] ?? '');
    $info = $dataBackupBestanden[$sleutel] ?? null;
    $verwachteUitgang = $info ? ('_' . basename($info['pad'])) : null;

    if (!$info) {
      $melding['backups'] = 'Onbekend databestand.';
      $meldingType['backups'] = 'fout';
    } elseif ($backupBestandsnaam === '' || substr($backupBestandsnaam, -strlen($verwachteUitgang)) !== $verwachteUitgang) {
      // De bestandsnaam moet qua opbouw (tijdstempel_basisnaam) echt bij dit
      // databestand horen, anders zou iemand met een aangepast formulier de
      // back-up van het ene bestand in het andere kunnen proberen herstellen.
      $melding['backups'] = 'Ongeldige back-up geselecteerd.';
      $meldingType['backups'] = 'fout';
    } else {
      $backupPad = $dataBackupMap . '/' . $backupBestandsnaam;
      if (!is_file($backupPad)) {
        $melding['backups'] = 'Deze back-up bestaat niet meer.';
        $meldingType['backups'] = 'fout';
      } else {
        $ruweInhoud = @file_get_contents($backupPad);
        $herstelData = $ruweInhoud === false ? null : json_decode($ruweInhoud, true);
        if ($ruweInhoud === false || json_last_error() !== JSON_ERROR_NONE) {
          $melding['backups'] = 'Back-up kon niet gelezen worden (beschadigd bestand?).';
          $meldingType['backups'] = 'fout';
        } else {
          $backupTijd = filemtime($backupPad);
          // schrijfJson()/schrijfGebruikers() maken zelf, vóórdat ze
          // overschrijven, ook weer een back-up: de huidige (net te vervangen)
          // versie gaat dus niet verloren, ook dit herstel is terug te draaien.
          $gelukt = $info['schrijffunctie'] === 'schrijfGebruikers'
            ? schrijfGebruikers($info['pad'], $herstelData)
            : schrijfJson($info['pad'], $herstelData);
          if ($gelukt) {
            schrijfLog($logBestand, $huidigeGebruiker, 'backup_hersteld', $info['label'] . ' (' . $backupBestandsnaam . ')');
            $_SESSION['flash'] = ['backups' => [
              'tekst' => $info['label'] . ' is teruggezet naar de versie van ' . date('d-m-Y H:i', $backupTijd ?: time()) . '. De versie van vlak vóór dit herstel is zelf ook als back-up bewaard.',
              'type' => 'ok',
            ]];
            header('Location: beheer.php#backups');
            exit;
          } else {
            $melding['backups'] = 'Terugzetten mislukt. Controleer de schrijfrechten op de server.';
            $meldingType['backups'] = 'fout';
          }
        }
      }
    }
  // De opslag-acties van de ledenadministratie, commissies, vergaderingen,
  // taken en evenementen staan in leden.php.
  }

  dataSlotDicht($lockHandle);
}

// ===== Huidige inhoud inlezen voor de formulieren =====

$huidigeTekst = '';
$laatstBijgewerkt = null;
if (file_exists($actueelBestand)) {
  $json = json_decode(file_get_contents($actueelBestand), true);
  if (is_array($json)) {
    $huidigeTekst = $json['text'] ?? '';
    $laatstBijgewerkt = $json['updated'] ?? null;
  }
}

$agendaData = $agendaStandaard;
if (file_exists($agendaBestand)) {
  $json = json_decode(file_get_contents($agendaBestand), true);
  if (is_array($json) && count($json) > 0) {
    // Herkent en converteert automatisch het oude platte formaat
    // (title/desc als tekst, van vóór de talenvelden) naar het huidige
    // genestte formaat. Zo gaat er nooit tekst verloren, ongeacht welke
    // versie van beheer.php het bestand voor het laatst heeft geschreven.
    $agendaData = array_map(function($item) {
      if (isset($item['title']) && is_string($item['title'])) {
        return [
          'date' => $item['date'] ?? '',
          'tag'  => $item['tag'] ?? 'leden',
          'time' => $item['time'] ?? '',
          'title' => ['nl' => $item['title'], 'en' => '', 'de' => ''],
          'desc'  => ['nl' => is_string($item['desc'] ?? null) ? $item['desc'] : '', 'en' => '', 'de' => ''],
          'past' => !empty($item['past']),
        ];
      }
      return [
        'date' => $item['date'] ?? '',
        'tag'  => $item['tag'] ?? 'leden',
        'time' => $item['time'] ?? '',
        'title' => ['nl' => $item['title']['nl'] ?? '', 'en' => $item['title']['en'] ?? '', 'de' => $item['title']['de'] ?? ''],
        'desc'  => ['nl' => $item['desc']['nl'] ?? '', 'en' => $item['desc']['en'] ?? '', 'de' => $item['desc']['de'] ?? ''],
        'past' => !empty($item['past']),
      ];
    }, $json);
  }
}
// Geen vast maximum meer: altijd de bestaande evenementen plus telkens 1 leeg
// blok aan het einde om een nieuw evenement te beginnen. Meerdere in één keer
// toevoegen kan met de knop "+ Agenda-item toevoegen" (JS kloont dan
// clientside een extra leeg blok, zie itemBlokToevoegen()).
$agendaData[] = ['date' => '', 'tag' => 'leden', 'time' => '', 'title' => ['nl' => '', 'en' => '', 'de' => ''], 'desc' => ['nl' => '', 'en' => '', 'de' => ''], 'past' => false];

$faqData = $faqStandaard;
if (file_exists($faqBestand)) {
  $json = json_decode(file_get_contents($faqBestand), true);
  if (is_array($json) && count($json) > 0) {
    // Herkent en converteert automatisch het oude platte formaat
    // ({"q": "tekst", "a": "tekst"}, van vóór de talenvelden) naar het
    // huidige genestte formaat. Zo gaat er nooit tekst verloren, ongeacht
    // welke versie van beheer.php het bestand voor het laatst heeft geschreven.
    $faqData = array_map(function($item) {
      if (isset($item['q']) && is_string($item['q'])) {
        return [
          'q' => ['nl' => $item['q'], 'en' => '', 'de' => ''],
          'a' => ['nl' => is_string($item['a'] ?? null) ? $item['a'] : '', 'en' => '', 'de' => ''],
        ];
      }
      return [
        'q' => ['nl' => $item['q']['nl'] ?? '', 'en' => $item['q']['en'] ?? '', 'de' => $item['q']['de'] ?? ''],
        'a' => ['nl' => $item['a']['nl'] ?? '', 'en' => $item['a']['en'] ?? '', 'de' => $item['a']['de'] ?? ''],
      ];
    }, $json);
  }
}
// Geen vast maximum meer: altijd de bestaande vragen plus telkens 1 leeg
// blok aan het einde om een nieuwe vraag te beginnen. Meerdere vragen in
// één keer toevoegen kan met de knop "Nog een vraag toevoegen" (JS kloont
// dan clientside een extra leeg blok, zie itemBlokToevoegen()).
$faqData[] = ['q' => ['nl' => '', 'en' => '', 'de' => ''], 'a' => ['nl' => '', 'en' => '', 'de' => '']];

$sponsorData = $sponsorStandaard;
$sponsorCtaData = $sponsorCtaStandaard;
if (file_exists($sponsorBestand)) {
  $json = json_decode(file_get_contents($sponsorBestand), true);
  if (is_array($json) && isset($json['items']) && count($json['items']) > 0) {
    $sponsorData = $json['items'];
  }
  if (is_array($json) && isset($json['cta']) && is_array($json['cta'])) {
    $sponsorCtaData = array_merge($sponsorCtaStandaard, $json['cta']);
  }
}
// Geen vast maximum meer: altijd de bestaande sponsors plus telkens 1 leeg
// blok aan het einde. Zie faqData hierboven voor de toelichting; hetzelfde
// principe (en dezelfde itemBlokToevoegen()-knop) geldt hier.
$sponsorData[] = ['name' => '', 'url' => '', 'logo' => ''];

$contactData = $contactStandaard;
if (file_exists($contactBestand)) {
  $json = json_decode(file_get_contents($contactBestand), true);
  if (is_array($json)) {
    $contactData = array_merge($contactStandaard, $json);
    $contactData['openingstijden'] = array_merge($contactStandaard['openingstijden'], $json['openingstijden'] ?? []);
    // Alle drie de dagen horen een ['van' => ..., 'tot' => ...] paar te zijn.
    // Stond er nog een oude losse tekst (van vóór het keuzemenu), dan geldt de
    // standaard tijd als uitgangspunt in plaats van dat de pagina crasht.
    $statusOpties = contactStatusOpties();
    $nuMoment = new DateTime('now', new DateTimeZone('Europe/Amsterdam'));
    foreach (['woensdag', 'zaterdag', 'zondag'] as $dag) {
      if (!is_array($contactData['openingstijden'][$dag] ?? null)) {
        // Woensdag was eerder een vrij tekstveld. Die tekst kan niet naar
        // tijden worden omgerekend, dus daar geldt de standaard voor.
        $contactData['openingstijden'][$dag] = $contactStandaard['openingstijden'][$dag];
      } else {
        // Bestanden van vóór het keuzemenu hebben alleen van/tot, of nog het
        // oude onderhoudsvinkje. Die worden hier omgezet naar een stand.
        $oud = $contactData['openingstijden'][$dag];
        $contactData['openingstijden'][$dag] = array_merge($contactStandaard['openingstijden'][$dag], $oud);
        // De stand waar een dag op terugvalt zodra een tijdelijke melding
        // vervalt. Voor woensdag is dat 'animo', voor het weekend 'open'.
        $terugval = $contactStandaard['openingstijden'][$dag]['status'];
        $status = $contactData['openingstijden'][$dag]['status'] ?? '';
        if (!isset($statusOpties[$status])) {
          $contactData['openingstijden'][$dag]['status'] = !empty($oud['gesloten']) ? 'onderhoud' : $terugval;
        }
        unset($contactData['openingstijden'][$dag]['gesloten']);

        // Is het vervalmoment voorbij, dan valt de dag terug op zijn vaste
        // stand. De
        // website doet hetzelfde, dus het beheerscherm laat zo hetzelfde zien.
        // Het bestand wordt hier niet herschreven; dat gebeurt vanzelf bij de
        // eerstvolgende keer opslaan.
        $statusTot = $contactData['openingstijden'][$dag]['status_tot'] ?? '';
        if (!in_array($contactData['openingstijden'][$dag]['status'], contactVasteStanden(), true) && $statusTot) {
          try {
            if (new DateTime($statusTot) <= $nuMoment) {
              $contactData['openingstijden'][$dag]['status'] = $terugval;
              $contactData['openingstijden'][$dag]['status_tot'] = '';
            }
          } catch (Exception $e) {
            // Onleesbare datum: laat de stand staan zoals hij is.
          }
        }
      }
    }
  }
}

$mediaData = $mediaStandaard;
if (file_exists($mediaBestand)) {
  $json = json_decode(file_get_contents($mediaBestand), true);
  if (is_array($json) && count($json) > 0) $mediaData = $json;
}
// Geen vast maximum meer: altijd de bestaande media-items plus telkens 1
// leeg blok aan het einde. Zie faqData hierboven voor de toelichting.
$mediaData[] = ['date' => '', 'bron' => '', 'icoon' => '📺', 'title' => ['nl' => '', 'en' => '', 'de' => ''], 'desc' => ['nl' => '', 'en' => '', 'de' => ''], 'link' => '', 'linktekst' => ['nl' => '', 'en' => '', 'de' => '']];

$nieuwsData = $nieuwsStandaard;
if (file_exists($nieuwsBestand)) {
  $json = json_decode(file_get_contents($nieuwsBestand), true);
  if (is_array($json) && count($json) > 0) $nieuwsData = $json;
}
// Geen vast maximum meer: altijd de bestaande nieuwsitems plus telkens 1
// leeg blok aan het einde. Zie faqData hierboven voor de toelichting.
$nieuwsData[] = ['date' => '', 'title' => ['nl' => '', 'en' => '', 'de' => ''], 'desc' => ['nl' => '', 'en' => '', 'de' => ''], 'link' => '', 'linktekst' => ['nl' => '', 'en' => '', 'de' => '']];

// Rekentabel contributie: bedragen inlezen en meteen omzetten naar de
// pro-rata maandtabellen die de rest van dit bestand (tabblad Rekentabel)
// gebruikt. Zie $rekentabelStandaard hierboven voor de uitleg.
$rekentabelData = $rekentabelStandaard;
if (file_exists($rekentabelBestand)) {
  $json = json_decode(file_get_contents($rekentabelBestand), true);
  if (is_array($json)) $rekentabelData = array_merge($rekentabelStandaard, $json);
}
$inschrijfkosten = (float) $rekentabelData['inschrijfkosten'];
$tabelJeugd  = rekentabelProRata((float) $rekentabelData['jeugd_jaarbedrag']);
$tabelSenior = rekentabelProRata((float) $rekentabelData['senior_jaarbedrag']);

// Homepage-teksten: zolang data/homepage.json nog niet bestaat, toont het
// formulier $homepageStandaard (de tekst die nu al op de site staat), zodat
// er bij de eerste keer opslaan niets per ongeluk leeggemaakt wordt.
$homepageData = $homepageStandaard;
if (file_exists($homepageBestand)) {
  $json = json_decode(file_get_contents($homepageBestand), true);
  if (is_array($json)) $homepageData = vulStandaardAan($homepageStandaard, $json);
}
$ontstaanData = $ontstaanStandaard;
if (file_exists($ontstaanBestand)) {
  $json = json_decode(file_get_contents($ontstaanBestand), true);
  if (is_array($json)) $ontstaanData = vulStandaardAan($ontstaanStandaard, $json);
}
$baanreglementData = $baanreglementStandaard;
if (file_exists($baanreglementBestand)) {
  $json = json_decode(file_get_contents($baanreglementBestand), true);
  if (is_array($json)) $baanreglementData = vulStandaardAan($baanreglementStandaard, $json);
}

// Eenmalige inhoudsmigratie van artikel 1: oudere versies bevatten hier nog
// concrete woensdag/zaterdag/zondag-tijden. Alleen dat ene veld wordt naar de
// nieuwe tekst omgezet; alle overige via Beheer opgeslagen teksten blijven
// onaangeroerd. Na succesvolle opslag is deze controle voortaan een no-op.
$baanreglementA1Huidig = $baanreglementData['a1_body'] ?? [];
$baanreglementA1Oud = false;
if (is_array($baanreglementA1Huidig)) {
  foreach (['nl', 'en', 'de'] as $taal) {
    $tekst = (string) ($baanreglementA1Huidig[$taal] ?? '');
    if (preg_match('/(?:Woensdagavond|Wednesday evening|Mittwochabend|Zaterdag|Saturday|Samstag|Zondag|Sunday|Sonntag).*\d{1,2}[:.]\d{2}/iu', $tekst)) {
      $baanreglementA1Oud = true;
      break;
    }
  }
}
if ($baanreglementA1Oud) {
  $baanreglementData['a1_body'] = $baanreglementStandaard['a1_body'];
  schrijfJson($baanreglementBestand, $baanreglementData);
}
$bedanktData = $bedanktStandaard;
if (file_exists($bedanktBestand)) {
  $json = json_decode(file_get_contents($bedanktBestand), true);
  if (is_array($json)) $bedanktData = vulStandaardAan($bedanktStandaard, $json);
}
$aanmeldenData = $aanmeldenStandaard;
if (file_exists($aanmeldenBestand)) {
  $json = json_decode(file_get_contents($aanmeldenBestand), true);
  if (is_array($json)) $aanmeldenData = vulStandaardAan($aanmeldenStandaard, $json);
}
$mediaTekstData = $mediaTekstStandaard;
if (file_exists($mediaTekstBestand)) {
  $json = json_decode(file_get_contents($mediaTekstBestand), true);
  if (is_array($json)) $mediaTekstData = vulStandaardAan($mediaTekstStandaard, $json);
}
$fotoboekTekstData = $fotoboekTekstStandaard;
if (file_exists($fotoboekTekstBestand)) {
  $json = json_decode(file_get_contents($fotoboekTekstBestand), true);
  if (is_array($json)) $fotoboekTekstData = vulStandaardAan($fotoboekTekstStandaard, $json);
}

// De ledenadministratie, commissies, vergaderingen, taken en evenementen
// worden nu in leden.php ingelezen en getoond.


$fotoboekData = ['albums' => []];
if (file_exists($fotoboekBestand)) {
  $json = json_decode(file_get_contents($fotoboekBestand), true);
  if (is_array($json) && isset($json['albums'])) $fotoboekData = $json;
}
// 'volgorde' en 'watermerk' bestonden nog niet in fase 1; ontbreken deze velden,
// dan geldt de bestaande volgorde in het bestand als uitgangspunt en tellen
// foto's als nog niet voorzien van een watermerk.
foreach ($fotoboekData['albums'] as $i => &$a) {
  if (!isset($a['volgorde'])) $a['volgorde'] = $i;
  if (!isset($a['title']['en'])) $a['title']['en'] = '';
  if (!isset($a['title']['de'])) $a['title']['de'] = '';
  if (isset($a['photos']) && is_array($a['photos'])) {
    foreach ($a['photos'] as &$foto) {
      if (!isset($foto['watermerk'])) $foto['watermerk'] = false;
    }
    unset($foto);
  }
}
unset($a);
usort($fotoboekData['albums'], function($a, $b) { return ($a['volgorde'] ?? 0) <=> ($b['volgorde'] ?? 0); });

// Changelog: vaste regels (repo) en eigen regels (data/) samen, nieuwste
// eerst. Het filteren op categorie en zoekwoord gebeurt in de browser, dus
// hier gaat de hele lijst mee naar het tabblad.
$changelogEigen = laadChangelogEigen($changelogBestand);
$changelogLijst = changelogSamenvoegen($changelogEigen, laadChangelogVast($changelogVastPad));
$changelogTellingen = array_fill_keys(array_keys($changelogCategorieen), 0);
foreach ($changelogLijst as $clRegel) {
  $clCatSleutel = $clRegel['cat'] ?? '';
  if (isset($changelogTellingen[$clCatSleutel])) $changelogTellingen[$clCatSleutel]++;
}

$gebruikersLijst = in_array('gebruikers', $toegestaneTabs, true) ? laadGebruikers($usersBestand) : [];
$logRegels = [];
if (in_array('log', $toegestaneTabs, true) && file_exists($logBestand)) {
  $json = json_decode(file_get_contents($logBestand), true);
  if (is_array($json)) $logRegels = array_reverse($json);
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Beheer | RC045</title>
  <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
  <link rel="stylesheet" href="paneel.css?v=<?php echo @filemtime(__DIR__ . '/paneel.css'); ?>">

  <script src="paneel-thema.js?v=<?php echo @filemtime(__DIR__ . '/paneel-thema.js'); ?>"></script>
</head>
<body>
  <button type="button" id="thema-switch" class="thema-switch"></button>
  <div class="wrap">

  <?php if (!$configOk): ?>

    <div class="kaart kaart-smal">
      <h1>Beheer</h1>
      <div class="melding fout">
        Configuratie ontbreekt. Upload eenmalig het bestand <strong>beheer-config.php</strong> via FTP naar dezelfde map als deze pagina en stel daarin een eigen wachtwoord in.
      </div>
    </div>

  <?php elseif (!$ingelogd): ?>

    <?php authInlogFormulier('RC045 beheer'); ?>

  <?php else: ?>

    <div class="ingelogd-balk">
      <span>Ingelogd als <strong><?php echo htmlspecialchars($huidigeGebruiker); ?></strong></span>
      <form method="post" action="beheer.php" style="display:inline;">
        <input type="hidden" name="formulier" value="uitloggen">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <button type="submit" class="link-knop">Uitloggen</button>
      </form>
    </div>

    <?php if (isset($melding['csrf'])): ?>
      <div class="melding <?php echo $meldingType['csrf']; ?>"><?php echo htmlspecialchars($melding['csrf']); ?></div>
    <?php endif; ?>

    <div class="beheer-layout">
    <button type="button" class="beheer-menu-knop" id="beheer-menu-knop" aria-expanded="false" aria-controls="beheer-menu">
      <span id="beheer-menu-huidig">Menu</span>
      <span class="streepjes" aria-hidden="true">☰</span>
    </button>
    <nav class="menu" id="beheer-menu">
      <?php
        // Menu-indeling: alleen groepering van de knoppen hierboven, geen
        // wijziging aan welke tabs een gebruiker mag zien. Groepslabel
        // verschijnt alleen als er in die groep ook echt iets zichtbaar is.
        $menuLabels = $beheerTabsAlle;
        // Volgorde binnen elke groep volgt nu ook de site: bij Pagina's
        // eerst de doorloop van de aanmeldflow (Aanmelden -> Bedankt, niet
        // andersom), bij Content de homepage top-naar-onder (mededeling
        // boven de hero, dan nieuws/agenda/contact/sponsors zoals ze onder
        // elkaar staan), met Vragen/Media/Fotoboek als losse pagina's erna.
        $menuGroepen = [
          ['label' => "Pagina's", 'tabs' => ['homepage', 'ontstaan', 'baanreglement', 'aanmelden', 'bedankt']],
          ['label' => 'Content', 'tabs' => ['mededeling', 'nieuws', 'agenda', 'contact', 'sponsors', 'faq', 'media', 'fotoboek']],
          ['label' => 'Contributie', 'tabs' => ['rekentabel']],
          ['label' => 'Beheer', 'tabs' => ['changelog', 'gebruikers', 'log', 'backups']],
        ];
      ?>
      <?php foreach ($menuGroepen as $groepIndex => $groep): ?>
        <?php
          $zichtbaar = [];
          foreach ($groep['tabs'] as $tabSleutel) {
            if (in_array($tabSleutel, $toegestaneTabs, true)) $zichtbaar[] = $tabSleutel;
          }
        ?>
        <?php if (!empty($zichtbaar)): ?>
      <div class="menu-groep">
        <button type="button" class="menu-groep-label" data-groep="<?php echo $groepIndex; ?>" aria-expanded="false" aria-controls="menu-groep-items-<?php echo $groepIndex; ?>">
          <span><?php echo htmlspecialchars($groep['label']); ?></span>
          <span class="menu-groep-pijl" aria-hidden="true">&#9656;</span>
        </button>
        <div class="menu-groep-items" id="menu-groep-items-<?php echo $groepIndex; ?>" data-groep="<?php echo $groepIndex; ?>" hidden>
          <?php foreach ($zichtbaar as $tabSleutel): ?>
      <button type="button" class="menu-item" data-tab="<?php echo $tabSleutel; ?>"><?php echo htmlspecialchars($menuLabels[$tabSleutel]); ?></button>
          <?php endforeach; ?>
        </div>
      </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>

    <div class="beheer-inhoud">

    <?php if (in_array('homepage', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-homepage">
    <!-- ===== HOMEPAGE TEKSTEN ===== -->
    <div class="kaart">
      <h1>Homepage teksten</h1>
      <p class="sub">De tekstblokken op de hoofdpagina: de intro boven aan, "Wie zijn wij", "De baan" en de omschrijvingen bij Lidmaatschap. De prijzen zelf staan bij Rekentabel.</p>

      <?php if (isset($melding['homepage'])): ?>
        <div class="melding <?php echo $meldingType['homepage']; ?>"><?php echo htmlspecialchars($melding['homepage']); ?></div>
      <?php endif; ?>

      <div class="melding" style="background:var(--gold-light); border:1px solid rgba(200,154,26,0.35); color:var(--rust);">
        Nederlands is verplicht per veld. Engels en Duits zijn optioneel: laat je die leeg, dan toont de website automatisch de Nederlandse tekst aan Engelse en Duitse bezoekers.
      </div>
    </div>

    <form method="post" action="beheer.php#homepage">
      <input type="hidden" name="formulier" value="homepage">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">

      <?php $homepageGroepIndex = 0; $homepageAantalGroepen = count($homepageGroepen); $homepageVorigeCluster = null; ?>
      <?php foreach ($homepageGroepen as $homepageGroepNaam => $homepageVeldSleutels): ?>
        <?php $homepageGroepIndex++; ?>
        <?php $homepageHuidigeCluster = $homepageGroepNaarCluster[$homepageGroepNaam] ?? null; ?>
        <?php if ($homepageHuidigeCluster !== null && $homepageHuidigeCluster !== $homepageVorigeCluster): ?>
      <div class="sectie-kop"><?php echo htmlspecialchars($homepageHuidigeCluster); ?></div>
          <?php $homepageVorigeCluster = $homepageHuidigeCluster; ?>
        <?php endif; ?>
        <details class="kaart" data-taal-scope="hp-<?php echo $homepageGroepIndex; ?>"<?php echo $homepageGroepIndex === 1 ? ' open' : ''; ?>>
          <summary><?php echo htmlspecialchars($homepageGroepNaam); ?><span class="kaart-uitklap-telling"><?php echo count($homepageVeldSleutels); ?> veld<?php echo count($homepageVeldSleutels) === 1 ? '' : 'en'; ?></span><span class="taal-toggle-mini"><button type="button" class="auto-vertaal-btn" title="Automatisch vertalen met DeepL">🌐 Vertaal</button><button type="button" class="taal-toggle-btn" data-taal="en" aria-pressed="false">🇬🇧 EN</button><button type="button" class="taal-toggle-btn" data-taal="de" aria-pressed="false">🇩🇪 DE</button></span></summary>
          <div class="kaart-uitklap-inhoud">
          <?php foreach ($homepageVeldSleutels as $veld): ?>
            <?php
              $hpInfo = $homepageVelden[$veld];
              $hpLabel = $hpInfo[0];
              $hpType = $hpInfo[1];
              $hpHuidig = $homepageData[$veld] ?? ['nl' => '', 'en' => '', 'de' => ''];
            ?>
            <div class="veld">
              <label for="hp-<?php echo $veld; ?>-nl"><?php echo htmlspecialchars($hpLabel); ?></label>
              <div class="taal-rij">
                <?php if ($hpType === 'tekst'): ?>
                  <input type="text" class="taal-nl" id="hp-<?php echo $veld; ?>-nl" name="hp[<?php echo $veld; ?>][nl]" maxlength="100" placeholder="Nederlands" value="<?php echo htmlspecialchars($hpHuidig['nl'] ?? ''); ?>">
                  <input type="text" class="taal-en" id="hp-<?php echo $veld; ?>-en" name="hp[<?php echo $veld; ?>][en]" maxlength="100" placeholder="English (optioneel)" value="<?php echo htmlspecialchars($hpHuidig['en'] ?? ''); ?>">
                  <input type="text" class="taal-de" id="hp-<?php echo $veld; ?>-de" name="hp[<?php echo $veld; ?>][de]" maxlength="100" placeholder="Deutsch (optional)" value="<?php echo htmlspecialchars($hpHuidig['de'] ?? ''); ?>">
                <?php else: ?>
                  <textarea class="taal-nl" id="hp-<?php echo $veld; ?>-nl" name="hp[<?php echo $veld; ?>][nl]" maxlength="600" placeholder="Nederlands" style="min-height:80px;"><?php echo htmlspecialchars($hpHuidig['nl'] ?? ''); ?></textarea>
                  <textarea class="taal-en" id="hp-<?php echo $veld; ?>-en" name="hp[<?php echo $veld; ?>][en]" maxlength="600" placeholder="English (optioneel)" style="min-height:80px;"><?php echo htmlspecialchars($hpHuidig['en'] ?? ''); ?></textarea>
                  <textarea class="taal-de" id="hp-<?php echo $veld; ?>-de" name="hp[<?php echo $veld; ?>][de]" maxlength="600" placeholder="Deutsch (optional)" style="min-height:80px;"><?php echo htmlspecialchars($hpHuidig['de'] ?? ''); ?></textarea>
                <?php endif; ?>
              </div>
              <?php if (isset($hpInfo[2])): ?>
                <p class="hint"><?php echo htmlspecialchars($hpInfo[2]); ?></p>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          </div>
        </details>
      <?php endforeach; ?>
      <div class="kaart">
        <button type="submit">Homepage teksten opslaan</button>
      </div>
    </form>
    </div>
    <?php endif; ?>

    <?php if (in_array('ontstaan', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-ontstaan">
    <!-- ===== ONTSTAAN (GESCHIEDENIS) ===== -->
    <div class="kaart">
      <h1>Ontstaan</h1>
      <p class="sub">De tekst op de pagina "Het ontstaan": de ondertitel boven het verhaal en de zeven alinea's van het verhaal zelf.</p>

      <?php if (isset($melding['ontstaan'])): ?>
        <div class="melding <?php echo $meldingType['ontstaan']; ?>"><?php echo htmlspecialchars($melding['ontstaan']); ?></div>
      <?php endif; ?>

      <div class="melding" style="background:var(--gold-light); border:1px solid rgba(200,154,26,0.35); color:var(--rust);">
        Nederlands is verplicht per veld. Engels en Duits zijn optioneel: laat je die leeg, dan toont de website automatisch de Nederlandse tekst aan Engelse en Duitse bezoekers.
      </div>
    </div>

    <form method="post" action="beheer.php#ontstaan">
      <input type="hidden" name="formulier" value="ontstaan">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">

      <div class="kaart" data-taal-scope="ontstaan">
        <div class="taal-scope-kop"><h1>Verhaal</h1><span class="taal-toggle-mini"><button type="button" class="auto-vertaal-btn" title="Automatisch vertalen met DeepL">🌐 Vertaal</button><button type="button" class="taal-toggle-btn" data-taal="en" aria-pressed="false">🇬🇧 EN</button><button type="button" class="taal-toggle-btn" data-taal="de" aria-pressed="false">🇩🇪 DE</button></span></div>
        <?php foreach ($ontstaanVelden as $veld => $info): ?>
          <?php
            $ontLabel = $info[0];
            $ontType = $info[1];
            $ontHuidig = $ontstaanData[$veld] ?? ['nl' => '', 'en' => '', 'de' => ''];
          ?>
          <div class="veld">
            <label for="ont-<?php echo $veld; ?>-nl"><?php echo htmlspecialchars($ontLabel); ?></label>
            <div class="taal-rij">
              <?php if ($ontType === 'tekst'): ?>
                <input type="text" class="taal-nl" id="ont-<?php echo $veld; ?>-nl" name="ont[<?php echo $veld; ?>][nl]" maxlength="150" placeholder="Nederlands" value="<?php echo htmlspecialchars($ontHuidig['nl'] ?? ''); ?>">
                <input type="text" class="taal-en" id="ont-<?php echo $veld; ?>-en" name="ont[<?php echo $veld; ?>][en]" maxlength="150" placeholder="English (optioneel)" value="<?php echo htmlspecialchars($ontHuidig['en'] ?? ''); ?>">
                <input type="text" class="taal-de" id="ont-<?php echo $veld; ?>-de" name="ont[<?php echo $veld; ?>][de]" maxlength="150" placeholder="Deutsch (optional)" value="<?php echo htmlspecialchars($ontHuidig['de'] ?? ''); ?>">
              <?php else: ?>
                <textarea class="taal-nl" id="ont-<?php echo $veld; ?>-nl" name="ont[<?php echo $veld; ?>][nl]" maxlength="600" placeholder="Nederlands" style="min-height:80px;"><?php echo htmlspecialchars($ontHuidig['nl'] ?? ''); ?></textarea>
                <textarea class="taal-en" id="ont-<?php echo $veld; ?>-en" name="ont[<?php echo $veld; ?>][en]" maxlength="600" placeholder="English (optioneel)" style="min-height:80px;"><?php echo htmlspecialchars($ontHuidig['en'] ?? ''); ?></textarea>
                <textarea class="taal-de" id="ont-<?php echo $veld; ?>-de" name="ont[<?php echo $veld; ?>][de]" maxlength="600" placeholder="Deutsch (optional)" style="min-height:80px;"><?php echo htmlspecialchars($ontHuidig['de'] ?? ''); ?></textarea>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <button type="submit">Ontstaan opslaan</button>
      </div>
    </form>
    </div>
    <?php endif; ?>

    <?php if (in_array('baanreglement', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-baanreglement">
    <!-- ===== BAANREGLEMENT ===== -->
    <div class="kaart">
      <h1>Baanreglement</h1>
      <p class="sub">De ondertitel, de introtekst en de tien artikelen van het baanreglement. Per artikel is er één tekstvak: gebruik een lege regel om alinea's te scheiden.</p>

      <?php if (isset($melding['baanreglement'])): ?>
        <div class="melding <?php echo $meldingType['baanreglement']; ?>"><?php echo htmlspecialchars($melding['baanreglement']); ?></div>
      <?php endif; ?>

      <div class="melding" style="background:var(--gold-light); border:1px solid rgba(200,154,26,0.35); color:var(--rust);">
        Nederlands is verplicht per veld. Engels en Duits zijn optioneel: laat je die leeg, dan toont de website automatisch de Nederlandse tekst aan Engelse en Duitse bezoekers.
      </div>
    </div>

    <form method="post" action="beheer.php#baanreglement">
      <input type="hidden" name="formulier" value="baanreglement">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">

      <?php $brGroepIndex = 0; $brAantalGroepen = count($baanreglementGroepen); ?>
      <?php foreach ($baanreglementGroepen as $brGroepNaam => $brVeldSleutels): ?>
        <?php $brGroepIndex++; ?>
        <details class="kaart" data-taal-scope="br-<?php echo $brGroepIndex; ?>"<?php echo $brGroepIndex === 1 ? ' open' : ''; ?>>
          <summary><?php echo htmlspecialchars($brGroepNaam); ?><span class="kaart-uitklap-telling"><?php echo count($brVeldSleutels); ?> veld<?php echo count($brVeldSleutels) === 1 ? '' : 'en'; ?></span><span class="taal-toggle-mini"><button type="button" class="auto-vertaal-btn" title="Automatisch vertalen met DeepL">🌐 Vertaal</button><button type="button" class="taal-toggle-btn" data-taal="en" aria-pressed="false">🇬🇧 EN</button><button type="button" class="taal-toggle-btn" data-taal="de" aria-pressed="false">🇩🇪 DE</button></span></summary>
          <div class="kaart-uitklap-inhoud">
          <?php foreach ($brVeldSleutels as $veld): ?>
            <?php
              $brInfo = $baanreglementVelden[$veld];
              $brLabel = $brInfo[0];
              $brType = $brInfo[1];
              $brHuidig = $baanreglementData[$veld] ?? ['nl' => '', 'en' => '', 'de' => ''];
            ?>
            <div class="veld">
              <label for="br-<?php echo $veld; ?>-nl"><?php echo htmlspecialchars($brLabel); ?></label>
              <div class="taal-rij">
                <?php if ($brType === 'tekst'): ?>
                  <input type="text" class="taal-nl" id="br-<?php echo $veld; ?>-nl" name="br[<?php echo $veld; ?>][nl]" maxlength="200" placeholder="Nederlands" value="<?php echo htmlspecialchars($brHuidig['nl'] ?? ''); ?>">
                  <input type="text" class="taal-en" id="br-<?php echo $veld; ?>-en" name="br[<?php echo $veld; ?>][en]" maxlength="200" placeholder="English (optioneel)" value="<?php echo htmlspecialchars($brHuidig['en'] ?? ''); ?>">
                  <input type="text" class="taal-de" id="br-<?php echo $veld; ?>-de" name="br[<?php echo $veld; ?>][de]" maxlength="200" placeholder="Deutsch (optional)" value="<?php echo htmlspecialchars($brHuidig['de'] ?? ''); ?>">
                <?php else: ?>
                  <textarea class="taal-nl" id="br-<?php echo $veld; ?>-nl" name="br[<?php echo $veld; ?>][nl]" maxlength="3000" placeholder="Nederlands" style="min-height:140px;"><?php echo htmlspecialchars($brHuidig['nl'] ?? ''); ?></textarea>
                  <textarea class="taal-en" id="br-<?php echo $veld; ?>-en" name="br[<?php echo $veld; ?>][en]" maxlength="3000" placeholder="English (optioneel)" style="min-height:140px;"><?php echo htmlspecialchars($brHuidig['en'] ?? ''); ?></textarea>
                  <textarea class="taal-de" id="br-<?php echo $veld; ?>-de" name="br[<?php echo $veld; ?>][de]" maxlength="3000" placeholder="Deutsch (optional)" style="min-height:140px;"><?php echo htmlspecialchars($brHuidig['de'] ?? ''); ?></textarea>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
          </div>
        </details>
      <?php endforeach; ?>
      <div class="kaart">
        <button type="submit">Baanreglement opslaan</button>
      </div>
    </form>
    </div>
    <?php endif; ?>

    <?php if (in_array('bedankt', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-bedankt">
    <!-- ===== BEDANKT-PAGINA (VOLLEDIG) ===== -->
    <div class="kaart">
      <h1>Bedankt-pagina</h1>
      <p class="sub">Alle tekst op de pagina die een nieuw lid ziet direct na het aanmelden: de titel, de introtekst, de drie stappen en de betaalgegevens.</p>

      <?php if (isset($melding['bedankt'])): ?>
        <div class="melding <?php echo $meldingType['bedankt']; ?>"><?php echo htmlspecialchars($melding['bedankt']); ?></div>
      <?php endif; ?>

      <div class="melding" style="background:var(--gold-light); border:1px solid rgba(200,154,26,0.35); color:var(--rust);">
        Nederlands is verplicht per veld. Engels en Duits zijn optioneel: laat je die leeg, dan toont de website automatisch de Nederlandse tekst aan Engelse en Duitse bezoekers.
      </div>
    </div>

    <form method="post" action="beheer.php#bedankt">
      <input type="hidden" name="formulier" value="bedankt">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">

      <div class="kaart">
        <h1>IBAN</h1>
        <div class="veld">
          <label for="bedankt-iban-number">IBAN-nummer</label>
          <input type="text" id="bedankt-iban-number" name="iban_number" maxlength="40" value="<?php echo htmlspecialchars($bedanktData['iban_number']); ?>">
          <p class="hint">Zelfde nummer voor alle talen, een IBAN wordt niet vertaald.</p>
        </div>
      </div>

      <?php $bdGroepIndex = 0; $bdAantalGroepen = count($bedanktGroepen); ?>
      <?php foreach ($bedanktGroepen as $bdGroepNaam => $bdVeldSleutels): ?>
        <?php $bdGroepIndex++; ?>
        <details class="kaart" data-taal-scope="bd-<?php echo $bdGroepIndex; ?>"<?php echo $bdGroepIndex === 1 ? ' open' : ''; ?>>
          <summary><?php echo htmlspecialchars($bdGroepNaam); ?><span class="kaart-uitklap-telling"><?php echo count($bdVeldSleutels); ?> veld<?php echo count($bdVeldSleutels) === 1 ? '' : 'en'; ?></span><span class="taal-toggle-mini"><button type="button" class="auto-vertaal-btn" title="Automatisch vertalen met DeepL">🌐 Vertaal</button><button type="button" class="taal-toggle-btn" data-taal="en" aria-pressed="false">🇬🇧 EN</button><button type="button" class="taal-toggle-btn" data-taal="de" aria-pressed="false">🇩🇪 DE</button></span></summary>
          <div class="kaart-uitklap-inhoud">
          <?php foreach ($bdVeldSleutels as $veld): ?>
            <?php
              $bdInfo = $bedanktVelden[$veld];
              $bdLabel = $bdInfo[0];
              $bdType = $bdInfo[1];
              $bdHuidig = $bedanktData[$veld] ?? ['nl' => '', 'en' => '', 'de' => ''];
            ?>
            <div class="veld">
              <label for="bd-<?php echo $veld; ?>-nl"><?php echo htmlspecialchars($bdLabel); ?></label>
              <div class="taal-rij">
                <?php if ($bdType === 'tekst'): ?>
                  <input type="text" class="taal-nl" id="bd-<?php echo $veld; ?>-nl" name="bd[<?php echo $veld; ?>][nl]" maxlength="200" placeholder="Nederlands" value="<?php echo htmlspecialchars($bdHuidig['nl'] ?? ''); ?>">
                  <input type="text" class="taal-en" id="bd-<?php echo $veld; ?>-en" name="bd[<?php echo $veld; ?>][en]" maxlength="200" placeholder="English (optioneel)" value="<?php echo htmlspecialchars($bdHuidig['en'] ?? ''); ?>">
                  <input type="text" class="taal-de" id="bd-<?php echo $veld; ?>-de" name="bd[<?php echo $veld; ?>][de]" maxlength="200" placeholder="Deutsch (optional)" value="<?php echo htmlspecialchars($bdHuidig['de'] ?? ''); ?>">
                <?php else: ?>
                  <textarea class="taal-nl" id="bd-<?php echo $veld; ?>-nl" name="bd[<?php echo $veld; ?>][nl]" maxlength="500" placeholder="Nederlands" style="min-height:80px;"><?php echo htmlspecialchars($bdHuidig['nl'] ?? ''); ?></textarea>
                  <textarea class="taal-en" id="bd-<?php echo $veld; ?>-en" name="bd[<?php echo $veld; ?>][en]" maxlength="500" placeholder="English (optioneel)" style="min-height:80px;"><?php echo htmlspecialchars($bdHuidig['en'] ?? ''); ?></textarea>
                  <textarea class="taal-de" id="bd-<?php echo $veld; ?>-de" name="bd[<?php echo $veld; ?>][de]" maxlength="500" placeholder="Deutsch (optional)" style="min-height:80px;"><?php echo htmlspecialchars($bdHuidig['de'] ?? ''); ?></textarea>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
          </div>
        </details>
      <?php endforeach; ?>
      <div class="kaart">
        <button type="submit">Bedankt-pagina opslaan</button>
      </div>
    </form>
    </div>
    <?php endif; ?>

    <?php if (in_array('aanmelden', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-aanmelden">
    <!-- ===== AANMELDEN (PAGINA) ===== -->
    <div class="kaart">
      <h1>Aanmelden</h1>
      <p class="sub">De vaste tekst op de aanmeldpagina: de hero, de kop boven de contributie-berekening, de formulierkoppen, de bevestiging na versturen en de FAQ-titel. De vragen zelf staan bij Vragen, de contributiebedragen bij Rekentabel.</p>

      <?php if (isset($melding['aanmelden'])): ?>
        <div class="melding <?php echo $meldingType['aanmelden']; ?>"><?php echo htmlspecialchars($melding['aanmelden']); ?></div>
      <?php endif; ?>

      <div class="melding" style="background:var(--gold-light); border:1px solid rgba(200,154,26,0.35); color:var(--rust);">
        Nederlands is verplicht per veld. Engels en Duits zijn optioneel: laat je die leeg, dan toont de website automatisch de Nederlandse tekst aan Engelse en Duitse bezoekers.
      </div>
    </div>

    <form method="post" action="beheer.php#aanmelden">
      <input type="hidden" name="formulier" value="aanmelden">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">

      <?php $amGroepIndex = 0; $amAantalGroepen = count($aanmeldenGroepen); ?>
      <?php foreach ($aanmeldenGroepen as $amGroepNaam => $amVeldSleutels): ?>
        <?php $amGroepIndex++; ?>
        <details class="kaart" data-taal-scope="am-<?php echo $amGroepIndex; ?>"<?php echo $amGroepIndex === 1 ? ' open' : ''; ?>>
          <summary><?php echo htmlspecialchars($amGroepNaam); ?><span class="kaart-uitklap-telling"><?php echo count($amVeldSleutels); ?> veld<?php echo count($amVeldSleutels) === 1 ? '' : 'en'; ?></span><span class="taal-toggle-mini"><button type="button" class="auto-vertaal-btn" title="Automatisch vertalen met DeepL">🌐 Vertaal</button><button type="button" class="taal-toggle-btn" data-taal="en" aria-pressed="false">🇬🇧 EN</button><button type="button" class="taal-toggle-btn" data-taal="de" aria-pressed="false">🇩🇪 DE</button></span></summary>
          <div class="kaart-uitklap-inhoud">
          <?php foreach ($amVeldSleutels as $veld): ?>
            <?php
              $amInfo = $aanmeldenVelden[$veld];
              $amLabel = $amInfo[0];
              $amType = $amInfo[1];
              $amHuidig = $aanmeldenData[$veld] ?? ['nl' => '', 'en' => '', 'de' => ''];
            ?>
            <div class="veld">
              <label for="am-<?php echo $veld; ?>-nl"><?php echo htmlspecialchars($amLabel); ?></label>
              <div class="taal-rij">
                <?php if ($amType === 'tekst'): ?>
                  <input type="text" class="taal-nl" id="am-<?php echo $veld; ?>-nl" name="am[<?php echo $veld; ?>][nl]" maxlength="200" placeholder="Nederlands" value="<?php echo htmlspecialchars($amHuidig['nl'] ?? ''); ?>">
                  <input type="text" class="taal-en" id="am-<?php echo $veld; ?>-en" name="am[<?php echo $veld; ?>][en]" maxlength="200" placeholder="English (optioneel)" value="<?php echo htmlspecialchars($amHuidig['en'] ?? ''); ?>">
                  <input type="text" class="taal-de" id="am-<?php echo $veld; ?>-de" name="am[<?php echo $veld; ?>][de]" maxlength="200" placeholder="Deutsch (optional)" value="<?php echo htmlspecialchars($amHuidig['de'] ?? ''); ?>">
                <?php else: ?>
                  <textarea class="taal-nl" id="am-<?php echo $veld; ?>-nl" name="am[<?php echo $veld; ?>][nl]" maxlength="500" placeholder="Nederlands" style="min-height:80px;"><?php echo htmlspecialchars($amHuidig['nl'] ?? ''); ?></textarea>
                  <textarea class="taal-en" id="am-<?php echo $veld; ?>-en" name="am[<?php echo $veld; ?>][en]" maxlength="500" placeholder="English (optioneel)" style="min-height:80px;"><?php echo htmlspecialchars($amHuidig['en'] ?? ''); ?></textarea>
                  <textarea class="taal-de" id="am-<?php echo $veld; ?>-de" name="am[<?php echo $veld; ?>][de]" maxlength="500" placeholder="Deutsch (optional)" style="min-height:80px;"><?php echo htmlspecialchars($amHuidig['de'] ?? ''); ?></textarea>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
          </div>
        </details>
      <?php endforeach; ?>
      <div class="kaart">
        <button type="submit">Aanmeldpagina opslaan</button>
      </div>
    </form>
    </div>
    <?php endif; ?>

    <?php if (in_array('mededeling', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-mededeling">
    <!-- ===== AFWIJKENDE OPENINGSTIJDEN ===== -->
    <div class="kaart">
      <h1>Afwijkende openingstijden</h1>
      <p class="sub">Tijdelijke aanvulling op de vaste openingstijden (tab Contact), bijvoorbeeld een keer dicht of een andere sluitingstijd. Verschijnt bovenaan de homepage en bij de openingstijden.</p>

      <?php if (isset($melding['actueel'])): ?>
        <div class="melding <?php echo $meldingType['actueel']; ?>"><?php echo htmlspecialchars($melding['actueel']); ?></div>
      <?php endif; ?>

      <form method="post" action="beheer.php#mededeling">
        <input type="hidden" name="formulier" value="actueel">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <div class="veld">
          <label for="tekst">Tekst voor de website</label>
          <textarea id="tekst" name="tekst" maxlength="500" placeholder="Bijv.: Zaterdag geopend van 10:00 tot 15:00, zondag gesloten wegens regen."><?php echo htmlspecialchars($huidigeTekst); ?></textarea>
          <p class="hint">Veld leegmaken en opslaan verbergt de strook.</p>
        </div>
        <button type="submit">Opslaan</button>
      </form>

      <?php if ($laatstBijgewerkt): ?>
        <p class="laatst">Laatst bijgewerkt: <?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($laatstBijgewerkt))); ?></p>
      <?php endif; ?>
    </div>
    </div>

    <?php endif; ?>

    <?php if (in_array('nieuws', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-nieuws">
    <!-- ===== NIEUWS / UPDATES (blok op de homepage) ===== -->
    <div class="kaart">
      <div class="kaart-header">
        <div>
          <h1>Nieuws / updates</h1>
          <p class="sub">Het nieuwsblok op de homepage. Laat een Nederlandse titel leeg om die kaart te verbergen. Zonder items blijft het blok op de homepage verborgen.</p>
        </div>
        <button type="button" class="knop-toevoegen" onclick="itemBlokToevoegen('nieuws-lijst', 'Item')">+ Nieuwsitem toevoegen</button>
      </div>

      <?php if (isset($melding['nieuws'])): ?>
        <div class="melding <?php echo $meldingType['nieuws']; ?>"><?php echo htmlspecialchars($melding['nieuws']); ?></div>
      <?php endif; ?>

      <div class="melding" style="background:var(--gold-light); border:1px solid rgba(200,154,26,0.35); color:var(--rust);">
        Nederlands is verplicht per kaart. Engels en Duits zijn optioneel: laat je die leeg, dan toont de website automatisch de Nederlandse tekst aan Engelse en Duitse bezoekers.
      </div>

      <form method="post" action="beheer.php#nieuws">
        <input type="hidden" name="formulier" value="nieuws">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">

        <div class="item-lijst" id="nieuws-lijst">
        <?php foreach ($nieuwsData as $i => $ni): ?>
          <div class="item-blok" data-taal-scope="nieuws-<?php echo $i; ?>">
            <div class="item-blok-kop"><div class="item-blok-nr">Item <?php echo $i + 1; ?></div><span class="taal-toggle-mini"><button type="button" class="auto-vertaal-btn" title="Automatisch vertalen met DeepL">🌐 Vertaal</button><button type="button" class="taal-toggle-btn" data-taal="en" aria-pressed="false">🇬🇧 EN</button><button type="button" class="taal-toggle-btn" data-taal="de" aria-pressed="false">🇩🇪 DE</button></span></div>
            <div class="rij-2">
              <div class="veld">
                <label for="nieuws-date-<?php echo $i; ?>">Datum</label>
                <div class="datum-invoer-rij">
                  <input type="text" inputmode="numeric" id="nieuws-date-<?php echo $i; ?>" name="nieuws[<?php echo $i; ?>][date]" maxlength="10" placeholder="dd-mm-jjjj" pattern="\d{2}-\d{2}-\d{4}" value="<?php echo htmlspecialchars(datumWeergave($ni['date'] ?? '')); ?>">
                  <button type="button" class="datum-picker-wrap" title="Datum kiezen uit kalender" aria-label="Datum kiezen"><span class="datum-picker-icoon" aria-hidden="true">📅</span><input type="date" class="datum-picker" data-doel="nieuws-date-<?php echo $i; ?>" tabindex="-1" aria-hidden="true"></button>
                </div>
              </div>
              <div class="veld">
                <label for="nieuws-link-<?php echo $i; ?>">Link (optioneel)</label>
                <input type="text" id="nieuws-link-<?php echo $i; ?>" name="nieuws[<?php echo $i; ?>][link]" maxlength="300" value="<?php echo htmlspecialchars($ni['link'] ?? ''); ?>" placeholder="https://...">
              </div>
            </div>

            <div class="taal-rij">
            <div class="taal-groep taal-nl">
              <div class="taal-label">🇳🇱 Nederlands</div>
              <div class="veld">
                <label for="nieuws-title-nl-<?php echo $i; ?>">Titel</label>
                <input type="text" id="nieuws-title-nl-<?php echo $i; ?>" name="nieuws[<?php echo $i; ?>][title_nl]" maxlength="100" value="<?php echo htmlspecialchars($ni['title']['nl'] ?? ''); ?>">
              </div>
              <div class="veld">
                <label for="nieuws-desc-nl-<?php echo $i; ?>">Tekst</label>
                <textarea id="nieuws-desc-nl-<?php echo $i; ?>" name="nieuws[<?php echo $i; ?>][desc_nl]" maxlength="300" style="min-height:60px;"><?php echo htmlspecialchars($ni['desc']['nl'] ?? ''); ?></textarea>
              </div>
              <div class="veld">
                <label for="nieuws-linktekst-nl-<?php echo $i; ?>">Linktekst</label>
                <input type="text" id="nieuws-linktekst-nl-<?php echo $i; ?>" name="nieuws[<?php echo $i; ?>][linktekst_nl]" maxlength="40" value="<?php echo htmlspecialchars($ni['linktekst']['nl'] ?? ''); ?>" placeholder="Bijv.: Lees meer →">
              </div>
            </div><div class="taal-groep taal-en">
              <div class="taal-label">🇬🇧 English <span class="optioneel">(optioneel)</span></div>
              <div class="veld">
                <label for="nieuws-title-en-<?php echo $i; ?>">Title</label>
                <input type="text" id="nieuws-title-en-<?php echo $i; ?>" name="nieuws[<?php echo $i; ?>][title_en]" maxlength="100" value="<?php echo htmlspecialchars($ni['title']['en'] ?? ''); ?>">
              </div>
              <div class="veld">
                <label for="nieuws-desc-en-<?php echo $i; ?>">Text</label>
                <textarea id="nieuws-desc-en-<?php echo $i; ?>" name="nieuws[<?php echo $i; ?>][desc_en]" maxlength="300" style="min-height:60px;"><?php echo htmlspecialchars($ni['desc']['en'] ?? ''); ?></textarea>
              </div>
              <div class="veld">
                <label for="nieuws-linktekst-en-<?php echo $i; ?>">Link text</label>
                <input type="text" id="nieuws-linktekst-en-<?php echo $i; ?>" name="nieuws[<?php echo $i; ?>][linktekst_en]" maxlength="40" value="<?php echo htmlspecialchars($ni['linktekst']['en'] ?? ''); ?>">
              </div>
            </div><div class="taal-groep taal-de">
              <div class="taal-label">🇩🇪 Deutsch <span class="optioneel">(optioneel)</span></div>
              <div class="veld">
                <label for="nieuws-title-de-<?php echo $i; ?>">Titel</label>
                <input type="text" id="nieuws-title-de-<?php echo $i; ?>" name="nieuws[<?php echo $i; ?>][title_de]" maxlength="100" value="<?php echo htmlspecialchars($ni['title']['de'] ?? ''); ?>">
              </div>
              <div class="veld">
                <label for="nieuws-desc-de-<?php echo $i; ?>">Text</label>
                <textarea id="nieuws-desc-de-<?php echo $i; ?>" name="nieuws[<?php echo $i; ?>][desc_de]" maxlength="300" style="min-height:60px;"><?php echo htmlspecialchars($ni['desc']['de'] ?? ''); ?></textarea>
              </div>
              <div class="veld">
                <label for="nieuws-linktekst-de-<?php echo $i; ?>">Linktext</label>
                <input type="text" id="nieuws-linktekst-de-<?php echo $i; ?>" name="nieuws[<?php echo $i; ?>][linktekst_de]" maxlength="40" value="<?php echo htmlspecialchars($ni['linktekst']['de'] ?? ''); ?>">
              </div>
            </div></div>
          </div>
        <?php endforeach; ?>
        </div>

        <button type="submit">Nieuws opslaan</button>
      </form>
    </div>
    </div>

    <?php endif; ?>

    <?php if (in_array('agenda', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-agenda">
    <!-- ===== AGENDA ===== -->
    <div class="kaart">
      <div class="kaart-header">
        <div>
          <h1>Agenda homepage</h1>
          <p class="sub">De evenementenkaarten op de homepage, inclusief de bestaande kaarten. Laat de Nederlandse titel leeg om die kaart te verbergen.</p>
        </div>
        <button type="button" class="knop-toevoegen" onclick="itemBlokToevoegen('agenda-lijst', 'Kaart')">+ Agenda-item toevoegen</button>
      </div>

      <?php if (isset($melding['agenda'])): ?>
        <div class="melding <?php echo $meldingType['agenda']; ?>"><?php echo htmlspecialchars($melding['agenda']); ?></div>
      <?php endif; ?>

      <div class="melding" style="background:var(--gold-light); border:1px solid rgba(200,154,26,0.35); color:var(--rust);">
        Nederlands is verplicht per kaart. Engels en Duits zijn optioneel: laat je die leeg, dan toont de website automatisch de Nederlandse tekst aan Engelse en Duitse bezoekers.
      </div>

      <form method="post" action="beheer.php#agenda">
        <input type="hidden" name="formulier" value="agenda">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">

        <div class="item-lijst" id="agenda-lijst">
        <?php foreach ($agendaData as $i => $ev): ?>
          <div class="item-blok <?php echo !empty($ev['past']) ? 'is-afgelopen' : ''; ?>" data-taal-scope="agenda-<?php echo $i; ?>">
            <div class="item-blok-kop">
            <div class="item-blok-nr">
              Kaart <?php echo $i + 1; ?>
              <span class="afgelopen-badge" style="<?php echo empty($ev['past']) ? 'display:none;' : ''; ?>">Afgelopen</span>
              <span style="margin-left:auto; display:flex; align-items:center; gap:6px; font-weight:400; text-transform:none; letter-spacing:normal;">
                <label for="agenda-volgorde-<?php echo $i; ?>" style="margin:0;">Volgorde</label>
                <select id="agenda-volgorde-<?php echo $i; ?>" name="agenda[<?php echo $i; ?>][volgorde]">
                  <?php for ($p = 1; $p <= count($agendaData); $p++): ?>
                    <option value="<?php echo $p; ?>" <?php if ($p === $i + 1) echo 'selected'; ?>><?php echo $p; ?></option>
                  <?php endfor; ?>
                </select>
              </span>
            </div>
            <span class="taal-toggle-mini"><button type="button" class="auto-vertaal-btn" title="Automatisch vertalen met DeepL">🌐 Vertaal</button><button type="button" class="taal-toggle-btn" data-taal="en" aria-pressed="false">🇬🇧 EN</button><button type="button" class="taal-toggle-btn" data-taal="de" aria-pressed="false">🇩🇪 DE</button></span>
            </div>
            <p class="hint" style="margin-top:-8px; margin-bottom:12px;">Kies bij welk volgnummer deze kaart moet staan. Kaarten met dezelfde datum die na elkaar staan, komen op de website naast elkaar te staan.</p>
            <div class="rij-2">
              <div class="veld">
                <label for="agenda-date-<?php echo $i; ?>">Datum</label>
                <div class="datum-invoer-rij">
                  <input type="text" inputmode="numeric" id="agenda-date-<?php echo $i; ?>" name="agenda[<?php echo $i; ?>][date]" maxlength="10" placeholder="dd-mm-jjjj" pattern="\d{2}-\d{2}-\d{4}" value="<?php echo htmlspecialchars(datumWeergave($ev['date'] ?? '')); ?>">
                  <button type="button" class="datum-picker-wrap" title="Datum kiezen uit kalender" aria-label="Datum kiezen"><span class="datum-picker-icoon" aria-hidden="true">📅</span><input type="date" class="datum-picker" data-doel="agenda-date-<?php echo $i; ?>" tabindex="-1" aria-hidden="true"></button>
                </div>
              </div>
              <div class="veld">
                <label for="agenda-tag-<?php echo $i; ?>">Type</label>
                <select id="agenda-tag-<?php echo $i; ?>" name="agenda[<?php echo $i; ?>][tag]">
                  <?php foreach ($agendaTags as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php if (($ev['tag'] ?? '') === $key) echo 'selected'; ?>><?php echo $label; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="veld">
              <label for="agenda-time-<?php echo $i; ?>">Tijd</label>
              <input type="text" id="agenda-time-<?php echo $i; ?>" name="agenda[<?php echo $i; ?>][time]" maxlength="40" value="<?php echo htmlspecialchars($ev['time'] ?? ''); ?>" placeholder="Bijv.: 10:00 - 15:00">
              <p class="hint">Tijd wordt niet vertaald, cijfers zijn in elke taal duidelijk.</p>
            </div>
            <div class="veld">
              <label class="fotoboek-check" style="font-weight:700;">
                <input type="checkbox" name="agenda[<?php echo $i; ?>][past]" value="1" onchange="agendaAfgelopenBijwerken(this)" <?php if (!empty($ev['past'])) echo 'checked'; ?>>
                Evenement is afgelopen
              </label>
              <p class="hint">Vinkje aan: de kaart wordt op de website gedimd getoond met een "afgelopen"-label, automatisch in alle talen.</p>
            </div>

            <div class="taal-rij">
            <div class="taal-groep taal-nl">
              <div class="taal-label">🇳🇱 Nederlands</div>
              <div class="veld">
                <label for="agenda-title-nl-<?php echo $i; ?>">Titel</label>
                <input type="text" id="agenda-title-nl-<?php echo $i; ?>" name="agenda[<?php echo $i; ?>][title_nl]" maxlength="80" value="<?php echo htmlspecialchars($ev['title']['nl'] ?? ''); ?>" placeholder="Bijv.: Zomerrit met BBQ">
              </div>
              <div class="veld">
                <label for="agenda-desc-nl-<?php echo $i; ?>">Omschrijving</label>
                <textarea id="agenda-desc-nl-<?php echo $i; ?>" name="agenda[<?php echo $i; ?>][desc_nl]" maxlength="200" style="min-height:60px;"><?php echo htmlspecialchars($ev['desc']['nl'] ?? ''); ?></textarea>
              </div>
            </div><div class="taal-groep taal-en">
              <div class="taal-label">🇬🇧 English <span class="optioneel">(optioneel)</span></div>
              <div class="veld">
                <label for="agenda-title-en-<?php echo $i; ?>">Title</label>
                <input type="text" id="agenda-title-en-<?php echo $i; ?>" name="agenda[<?php echo $i; ?>][title_en]" maxlength="80" value="<?php echo htmlspecialchars($ev['title']['en'] ?? ''); ?>">
              </div>
              <div class="veld">
                <label for="agenda-desc-en-<?php echo $i; ?>">Description</label>
                <textarea id="agenda-desc-en-<?php echo $i; ?>" name="agenda[<?php echo $i; ?>][desc_en]" maxlength="200" style="min-height:60px;"><?php echo htmlspecialchars($ev['desc']['en'] ?? ''); ?></textarea>
              </div>
            </div><div class="taal-groep taal-de">
              <div class="taal-label">🇩🇪 Deutsch <span class="optioneel">(optioneel)</span></div>
              <div class="veld">
                <label for="agenda-title-de-<?php echo $i; ?>">Titel</label>
                <input type="text" id="agenda-title-de-<?php echo $i; ?>" name="agenda[<?php echo $i; ?>][title_de]" maxlength="80" value="<?php echo htmlspecialchars($ev['title']['de'] ?? ''); ?>">
              </div>
              <div class="veld">
                <label for="agenda-desc-de-<?php echo $i; ?>">Beschreibung</label>
                <textarea id="agenda-desc-de-<?php echo $i; ?>" name="agenda[<?php echo $i; ?>][desc_de]" maxlength="200" style="min-height:60px;"><?php echo htmlspecialchars($ev['desc']['de'] ?? ''); ?></textarea>
              </div>
            </div></div>
          </div>
        <?php endforeach; ?>
        </div>

        <button type="submit">Agenda opslaan</button>
      </form>
    </div>
    </div>

    <?php endif; ?>

    <?php if (in_array('faq', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-faq">
    <!-- ===== VEELGESTELDE VRAGEN ===== -->
    <div class="kaart">
      <div class="kaart-header">
        <div>
          <h1>Veelgestelde vragen</h1>
          <p class="sub">De volledige vragenlijst op de aanmeldpagina, inclusief de bestaande vragen. Laat een vraag leeg om die niet te tonen.</p>
        </div>
        <button type="button" class="knop-toevoegen" onclick="itemBlokToevoegen('faq-lijst', 'Vraag')">+ Vraag toevoegen</button>
      </div>

      <?php if (isset($melding['faq'])): ?>
        <div class="melding <?php echo $meldingType['faq']; ?>"><?php echo htmlspecialchars($melding['faq']); ?></div>
      <?php endif; ?>

      <div class="melding" style="background:var(--gold-light); border:1px solid rgba(200,154,26,0.35); color:var(--rust);">
        Nederlands is verplicht per vraag. Engels en Duits zijn optioneel: laat je die leeg, dan toont de website automatisch de Nederlandse tekst aan Engelse en Duitse bezoekers.
      </div>

      <form method="post" action="beheer.php#faq">
        <input type="hidden" name="formulier" value="faq">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">

        <div class="item-lijst" id="faq-lijst">
        <?php foreach ($faqData as $i => $item): ?>
          <div class="item-blok" data-taal-scope="faq-<?php echo $i; ?>">
            <div class="item-blok-kop"><div class="item-blok-nr">Vraag <?php echo $i + 1; ?></div><span class="taal-toggle-mini"><button type="button" class="auto-vertaal-btn" title="Automatisch vertalen met DeepL">🌐 Vertaal</button><button type="button" class="taal-toggle-btn" data-taal="en" aria-pressed="false">🇬🇧 EN</button><button type="button" class="taal-toggle-btn" data-taal="de" aria-pressed="false">🇩🇪 DE</button></span></div>

            <div class="taal-rij">
            <div class="taal-groep taal-nl">
              <div class="taal-label">🇳🇱 Nederlands</div>
              <div class="veld">
                <label for="faq-q-nl-<?php echo $i; ?>">Vraag</label>
                <input type="text" id="faq-q-nl-<?php echo $i; ?>" name="faq[<?php echo $i; ?>][q_nl]" maxlength="150" value="<?php echo htmlspecialchars($item['q']['nl'] ?? ''); ?>" placeholder="Bijv.: Mag ik met een verbrandingsmotor rijden?">
              </div>
              <div class="veld">
                <label for="faq-a-nl-<?php echo $i; ?>">Antwoord</label>
                <textarea id="faq-a-nl-<?php echo $i; ?>" name="faq[<?php echo $i; ?>][a_nl]" maxlength="600"><?php echo htmlspecialchars($item['a']['nl'] ?? ''); ?></textarea>
              </div>
            </div><div class="taal-groep taal-en">
              <div class="taal-label">🇬🇧 English <span class="optioneel">(optioneel)</span></div>
              <div class="veld">
                <label for="faq-q-en-<?php echo $i; ?>">Question</label>
                <input type="text" id="faq-q-en-<?php echo $i; ?>" name="faq[<?php echo $i; ?>][q_en]" maxlength="150" value="<?php echo htmlspecialchars($item['q']['en'] ?? ''); ?>">
              </div>
              <div class="veld">
                <label for="faq-a-en-<?php echo $i; ?>">Answer</label>
                <textarea id="faq-a-en-<?php echo $i; ?>" name="faq[<?php echo $i; ?>][a_en]" maxlength="600"><?php echo htmlspecialchars($item['a']['en'] ?? ''); ?></textarea>
              </div>
            </div><div class="taal-groep taal-de">
              <div class="taal-label">🇩🇪 Deutsch <span class="optioneel">(optioneel)</span></div>
              <div class="veld">
                <label for="faq-q-de-<?php echo $i; ?>">Frage</label>
                <input type="text" id="faq-q-de-<?php echo $i; ?>" name="faq[<?php echo $i; ?>][q_de]" maxlength="150" value="<?php echo htmlspecialchars($item['q']['de'] ?? ''); ?>">
              </div>
              <div class="veld">
                <label for="faq-a-de-<?php echo $i; ?>">Antwort</label>
                <textarea id="faq-a-de-<?php echo $i; ?>" name="faq[<?php echo $i; ?>][a_de]" maxlength="600"><?php echo htmlspecialchars($item['a']['de'] ?? ''); ?></textarea>
              </div>
            </div></div>
          </div>
        <?php endforeach; ?>
        </div>

        <button type="submit">Vragen opslaan</button>
      </form>
    </div>
    </div>

    <?php endif; ?>

    <?php if (in_array('sponsors', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-sponsors">
    <!-- ===== SPONSORS ===== -->
    <div class="kaart">
      <div class="kaart-header">
        <div>
          <h1>Sponsors</h1>
          <p class="sub">De sponsorlogo's onderaan elke pagina. Laat een naam leeg om die sponsor te verbergen.</p>
        </div>
        <button type="button" class="knop-toevoegen" onclick="itemBlokToevoegen('sponsors-lijst', 'Sponsor')">+ Sponsor toevoegen</button>
      </div>

      <?php if (isset($melding['sponsors'])): ?>
        <div class="melding <?php echo $meldingType['sponsors']; ?>"><?php echo htmlspecialchars($melding['sponsors']); ?></div>
      <?php endif; ?>

      <form method="post" action="beheer.php#sponsors" enctype="multipart/form-data">
        <input type="hidden" name="formulier" value="sponsors">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">

        <div class="veld" style="border-bottom:1px solid var(--border); padding-bottom:20px; margin-bottom:20px;" data-taal-scope="sponsors-cta">
          <div class="taal-scope-kop"><label>Tekst "sponsor worden" (onderaan elke pagina, onder de sponsorlogo's)</label><span class="taal-toggle-mini"><button type="button" class="auto-vertaal-btn" title="Automatisch vertalen met DeepL">🌐 Vertaal</button><button type="button" class="taal-toggle-btn" data-taal="en" aria-pressed="false">🇬🇧 EN</button><button type="button" class="taal-toggle-btn" data-taal="de" aria-pressed="false">🇩🇪 DE</button></span></div>
          <p class="hint" style="margin-top:-4px; margin-bottom:10px;">Het woord "contactformulier" (of de vertaling ervan hieronder) wordt op de website automatisch een link naar het contactformulier. Laat dat woord dus letterlijk in de tekst staan.</p>
          <div class="taal-rij">
            <div class="veld taal-nl">
              <label for="sponsor-cta-nl">🇳🇱 Tekst</label>
              <textarea id="sponsor-cta-nl" name="cta_nl" maxlength="200"><?php echo htmlspecialchars($sponsorCtaData['nl'] ?? ''); ?></textarea>
            </div>
            <div class="veld taal-en">
              <label for="sponsor-cta-en">🇬🇧 Text <span class="optioneel">(optioneel)</span></label>
              <textarea id="sponsor-cta-en" name="cta_en" maxlength="200"><?php echo htmlspecialchars($sponsorCtaData['en'] ?? ''); ?></textarea>
            </div>
            <div class="veld taal-de">
              <label for="sponsor-cta-de">🇩🇪 Text <span class="optioneel">(optioneel)</span></label>
              <textarea id="sponsor-cta-de" name="cta_de" maxlength="200"><?php echo htmlspecialchars($sponsorCtaData['de'] ?? ''); ?></textarea>
            </div>
          </div>
          <p class="hint">Engels en Duits leeg laten? Dan toont de website daar automatisch de Nederlandse tekst.</p>
        </div>

        <div class="item-lijst" id="sponsors-lijst">
        <?php foreach ($sponsorData as $i => $sp): ?>
          <div class="item-blok">
            <div class="item-blok-nr">Sponsor <?php echo $i + 1; ?></div>
            <?php if (!empty($sp['logo'])): ?>
              <img src="images/sponsors/<?php echo htmlspecialchars($sp['logo']); ?>" alt="" class="sponsor-logo-preview">
            <?php endif; ?>
            <div class="veld">
              <label for="sponsor-name-<?php echo $i; ?>">Naam</label>
              <input type="text" id="sponsor-name-<?php echo $i; ?>" name="sponsor[<?php echo $i; ?>][name]" maxlength="60" value="<?php echo htmlspecialchars($sp['name'] ?? ''); ?>" placeholder="Bijv.: Traxxas">
            </div>
            <div class="veld">
              <label for="sponsor-url-<?php echo $i; ?>">Website (optioneel)</label>
              <input type="text" id="sponsor-url-<?php echo $i; ?>" name="sponsor[<?php echo $i; ?>][url]" maxlength="200" value="<?php echo htmlspecialchars($sp['url'] ?? ''); ?>" placeholder="https://...">
            </div>
            <div class="veld">
              <label for="sponsor-logo-<?php echo $i; ?>">Logo<?php echo !empty($sp['logo']) ? ' (laat leeg om het huidige logo te behouden)' : ''; ?></label>
              <input type="file" id="sponsor-logo-<?php echo $i; ?>" name="sponsor_logo_<?php echo $i; ?>" accept="image/png,image/jpeg,image/webp">
              <p class="hint">PNG, JPG of WEBP, max 1 MB.</p>
            </div>
          </div>
        <?php endforeach; ?>
        </div>

        <button type="submit">Sponsors opslaan</button>
      </form>
    </div>
    </div>

    <?php endif; ?>

    <?php if (in_array('contact', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-contact">
    <!-- ===== CONTACT & OPENINGSTIJDEN ===== -->
    <div class="kaart">
      <h1>Contact & openingstijden</h1>
      <p class="sub">Adres, openingstijden, lidmaatschapsprijs, e-mail en Facebook-link. Deze gegevens staan op meerdere plekken op de website en worden overal automatisch bijgewerkt.</p>

      <?php if (isset($melding['contact'])): ?>
        <div class="melding <?php echo $meldingType['contact']; ?>"><?php echo htmlspecialchars($melding['contact']); ?></div>
      <?php endif; ?>

      <form method="post" action="beheer.php#contact">
        <input type="hidden" name="formulier" value="contact">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">

        <div class="rij-2">
          <div class="veld">
            <label for="contact-straat">Straat + huisnummer</label>
            <input type="text" id="contact-straat" name="adres_straat" maxlength="80" value="<?php echo htmlspecialchars($contactData['adres_straat'] ?? ''); ?>">
          </div>
          <div class="veld">
            <label for="contact-postcode">Postcode + plaats</label>
            <input type="text" id="contact-postcode" name="adres_postcode_plaats" maxlength="80" value="<?php echo htmlspecialchars($contactData['adres_postcode_plaats'] ?? ''); ?>">
          </div>
        </div>

        <?php $tijdOpties = contactTijdOpties(); ?>
        <?php foreach (['woensdag' => 'Woensdag', 'zaterdag' => 'Zaterdag', 'zondag' => 'Zondag'] as $dag => $dagLabel): ?>
          <div class="rij-2">
            <div class="veld">
              <label for="contact-<?php echo $dag; ?>-van"><?php echo $dagLabel; ?> van</label>
              <select id="contact-<?php echo $dag; ?>-van" name="openingstijden[<?php echo $dag; ?>][van]">
                <?php foreach ($tijdOpties as $optie): ?>
                  <option value="<?php echo $optie; ?>" <?php if (($contactData['openingstijden'][$dag]['van'] ?? '') === $optie) echo 'selected'; ?>><?php echo $optie; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="veld">
              <label for="contact-<?php echo $dag; ?>-tot"><?php echo $dagLabel; ?> tot</label>
              <select id="contact-<?php echo $dag; ?>-tot" name="openingstijden[<?php echo $dag; ?>][tot]">
                <?php foreach ($tijdOpties as $optie): ?>
                  <option value="<?php echo $optie; ?>" <?php if (($contactData['openingstijden'][$dag]['tot'] ?? '') === $optie) echo 'selected'; ?>><?php echo $optie; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="veld">
            <label for="contact-<?php echo $dag; ?>-status"><?php echo $dagLabel; ?>: stand</label>
            <select id="contact-<?php echo $dag; ?>-status" name="openingstijden[<?php echo $dag; ?>][status]">
              <?php foreach (contactStatusOpties() as $waarde => $label): ?>
                <option value="<?php echo $waarde; ?>" <?php if (($contactData['openingstijden'][$dag]['status'] ?? 'open') === $waarde) echo 'selected'; ?>><?php echo htmlspecialchars($label); ?></option>
              <?php endforeach; ?>
            </select>
            <p class="hint">Staat dit op een gesloten-stand, dan wordt de tijd op de website doorgestreept getoond met de reden eronder, automatisch in alle talen. Bij de leden- en animo-standen blijft de tijd gewoon leesbaar staan en komt er alleen een melding onder: de baan is die dag immers open, alleen niet voor iedereen of niet gegarandeerd. De tijden hierboven blijven in alle gevallen bewaard.<br>Een gesloten-stand en <strong>Alleen open voor leden</strong> vervallen vanzelf na afloop van de betreffende dag, dus vergeten terug te zetten kan geen kwaad. De twee animo-standen blijven wel staan: die horen bij de vaste opzet van woensdag en zijn geen tijdelijke afwijking. Ook de open/gesloten-melding bovenaan de homepage houdt hier rekening mee.<?php
              $vervalTekst = contactVervalTekst($contactData['openingstijden'][$dag]['status_tot'] ?? '');
              if ($vervalTekst) echo ' <strong>Deze melding verdwijnt ' . htmlspecialchars($vervalTekst) . '.</strong>';
            ?></p>
          </div>
        <?php endforeach; ?>

        <div class="veld">
          <label for="contact-lidmaatschap">Lidmaatschap vanaf-tekst</label>
          <input type="text" id="contact-lidmaatschap" name="lidmaatschap_vanaf" maxlength="60" value="<?php echo htmlspecialchars($contactData['lidmaatschap_vanaf'] ?? ''); ?>">
          <p class="hint">Wordt getoond op de homepage, bijv. "Vanaf €50/jaar".</p>
        </div>

        <div class="rij-2">
          <div class="veld">
            <label for="contact-email">E-mail</label>
            <input type="text" id="contact-email" name="email" maxlength="100" value="<?php echo htmlspecialchars($contactData['email'] ?? ''); ?>">
          </div>
          <div class="veld">
            <label for="contact-facebook">Facebook-link</label>
            <input type="text" id="contact-facebook" name="facebook" maxlength="200" value="<?php echo htmlspecialchars($contactData['facebook'] ?? ''); ?>">
          </div>
        </div>

        <button type="submit">Contactgegevens opslaan</button>
      </form>
    </div>
    </div>

    <?php endif; ?>

    <?php if (in_array('media', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-media">
    <!-- ===== MEDIA / PERSBERICHTEN ===== -->
    <div class="kaart">
      <h1>Media</h1>
      <p class="sub">De ondertitel bovenaan de pagina en de lijst met persaandacht. Laat een Nederlandse titel bij een item leeg om die kaart te verbergen.</p>

      <?php if (isset($melding['media'])): ?>
        <div class="melding <?php echo $meldingType['media']; ?>"><?php echo htmlspecialchars($melding['media']); ?></div>
      <?php endif; ?>

      <div class="melding" style="background:var(--gold-light); border:1px solid rgba(200,154,26,0.35); color:var(--rust);">
        Nederlands is verplicht per kaart. Engels en Duits zijn optioneel: laat je die leeg, dan toont de website automatisch de Nederlandse tekst aan Engelse en Duitse bezoekers.
      </div>
    </div>

    <div class="kaart">
      <h1>Ondertitel bovenaan de pagina</h1>
      <?php if (isset($melding['media_tekst'])): ?>
        <div class="melding <?php echo $meldingType['media_tekst']; ?>"><?php echo htmlspecialchars($melding['media_tekst']); ?></div>
      <?php endif; ?>
      <form method="post" action="beheer.php#media">
        <input type="hidden" name="formulier" value="media_tekst">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <div class="veld" data-taal-scope="media-ondertitel">
          <div class="taal-scope-kop"><label for="mt-hero-sub-nl">Tekst onder de titel "Media"</label><span class="taal-toggle-mini"><button type="button" class="auto-vertaal-btn" title="Automatisch vertalen met DeepL">🌐 Vertaal</button><button type="button" class="taal-toggle-btn" data-taal="en" aria-pressed="false">🇬🇧 EN</button><button type="button" class="taal-toggle-btn" data-taal="de" aria-pressed="false">🇩🇪 DE</button></span></div>
          <div class="taal-rij">
            <textarea class="taal-nl" id="mt-hero-sub-nl" name="mt[hero_sub][nl]" maxlength="400" placeholder="Nederlands" style="min-height:70px;"><?php echo htmlspecialchars($mediaTekstData['hero_sub']['nl'] ?? ''); ?></textarea>
            <textarea class="taal-en" id="mt-hero-sub-en" name="mt[hero_sub][en]" maxlength="400" placeholder="English (optioneel)" style="min-height:70px;"><?php echo htmlspecialchars($mediaTekstData['hero_sub']['en'] ?? ''); ?></textarea>
            <textarea class="taal-de" id="mt-hero-sub-de" name="mt[hero_sub][de]" maxlength="400" placeholder="Deutsch (optional)" style="min-height:70px;"><?php echo htmlspecialchars($mediaTekstData['hero_sub']['de'] ?? ''); ?></textarea>
          </div>
        </div>
        <button type="submit">Ondertitel opslaan</button>
      </form>
    </div>

    <div class="kaart">
      <div class="kaart-header">
        <h1>Media-items</h1>
        <button type="button" class="knop-toevoegen" onclick="itemBlokToevoegen('media-lijst', 'Item')">+ Media-item toevoegen</button>
      </div>

      <form method="post" action="beheer.php#media">
        <input type="hidden" name="formulier" value="media">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">

        <div class="item-lijst" id="media-lijst">
        <?php foreach ($mediaData as $i => $mi): ?>
          <div class="item-blok" data-taal-scope="media-<?php echo $i; ?>">
            <div class="item-blok-kop"><div class="item-blok-nr">Item <?php echo $i + 1; ?></div><span class="taal-toggle-mini"><button type="button" class="auto-vertaal-btn" title="Automatisch vertalen met DeepL">🌐 Vertaal</button><button type="button" class="taal-toggle-btn" data-taal="en" aria-pressed="false">🇬🇧 EN</button><button type="button" class="taal-toggle-btn" data-taal="de" aria-pressed="false">🇩🇪 DE</button></span></div>
            <div class="rij-3">
              <div class="veld">
                <label for="media-date-<?php echo $i; ?>">Datum</label>
                <div class="datum-invoer-rij">
                  <input type="text" inputmode="numeric" id="media-date-<?php echo $i; ?>" name="media[<?php echo $i; ?>][date]" maxlength="10" placeholder="dd-mm-jjjj" pattern="\d{2}-\d{2}-\d{4}" value="<?php echo htmlspecialchars(datumWeergave($mi['date'] ?? '')); ?>">
                  <button type="button" class="datum-picker-wrap" title="Datum kiezen uit kalender" aria-label="Datum kiezen"><span class="datum-picker-icoon" aria-hidden="true">📅</span><input type="date" class="datum-picker" data-doel="media-date-<?php echo $i; ?>" tabindex="-1" aria-hidden="true"></button>
                </div>
              </div>
              <div class="veld">
                <label for="media-bron-<?php echo $i; ?>">Bron</label>
                <input type="text" id="media-bron-<?php echo $i; ?>" name="media[<?php echo $i; ?>][bron]" maxlength="60" value="<?php echo htmlspecialchars($mi['bron'] ?? ''); ?>" placeholder="Bijv.: L1mburg">
              </div>
              <div class="veld">
                <label for="media-icoon-<?php echo $i; ?>">Type</label>
                <select id="media-icoon-<?php echo $i; ?>" name="media[<?php echo $i; ?>][icoon]">
                  <option value="📺" <?php if (($mi['icoon'] ?? '📺') === '📺') echo 'selected'; ?>>📺 Video</option>
                  <option value="📰" <?php if (($mi['icoon'] ?? '') === '📰') echo 'selected'; ?>>📰 Artikel</option>
                </select>
              </div>
            </div>
            <div class="veld">
              <label for="media-link-<?php echo $i; ?>">Link</label>
              <input type="text" id="media-link-<?php echo $i; ?>" name="media[<?php echo $i; ?>][link]" maxlength="300" value="<?php echo htmlspecialchars($mi['link'] ?? ''); ?>" placeholder="https://...">
            </div>

            <div class="taal-rij">
            <div class="taal-groep taal-nl">
              <div class="taal-label">🇳🇱 Nederlands</div>
              <div class="veld">
                <label for="media-title-nl-<?php echo $i; ?>">Titel</label>
                <input type="text" id="media-title-nl-<?php echo $i; ?>" name="media[<?php echo $i; ?>][title_nl]" maxlength="100" value="<?php echo htmlspecialchars($mi['title']['nl'] ?? ''); ?>">
              </div>
              <div class="veld">
                <label for="media-desc-nl-<?php echo $i; ?>">Omschrijving</label>
                <textarea id="media-desc-nl-<?php echo $i; ?>" name="media[<?php echo $i; ?>][desc_nl]" maxlength="300" style="min-height:60px;"><?php echo htmlspecialchars($mi['desc']['nl'] ?? ''); ?></textarea>
              </div>
              <div class="veld">
                <label for="media-linktekst-nl-<?php echo $i; ?>">Linktekst</label>
                <input type="text" id="media-linktekst-nl-<?php echo $i; ?>" name="media[<?php echo $i; ?>][linktekst_nl]" maxlength="40" value="<?php echo htmlspecialchars($mi['linktekst']['nl'] ?? ''); ?>" placeholder="Bijv.: Bekijk op Facebook →">
              </div>
            </div><div class="taal-groep taal-en">
              <div class="taal-label">🇬🇧 English <span class="optioneel">(optioneel)</span></div>
              <div class="veld">
                <label for="media-title-en-<?php echo $i; ?>">Title</label>
                <input type="text" id="media-title-en-<?php echo $i; ?>" name="media[<?php echo $i; ?>][title_en]" maxlength="100" value="<?php echo htmlspecialchars($mi['title']['en'] ?? ''); ?>">
              </div>
              <div class="veld">
                <label for="media-desc-en-<?php echo $i; ?>">Description</label>
                <textarea id="media-desc-en-<?php echo $i; ?>" name="media[<?php echo $i; ?>][desc_en]" maxlength="300" style="min-height:60px;"><?php echo htmlspecialchars($mi['desc']['en'] ?? ''); ?></textarea>
              </div>
              <div class="veld">
                <label for="media-linktekst-en-<?php echo $i; ?>">Link text</label>
                <input type="text" id="media-linktekst-en-<?php echo $i; ?>" name="media[<?php echo $i; ?>][linktekst_en]" maxlength="40" value="<?php echo htmlspecialchars($mi['linktekst']['en'] ?? ''); ?>">
              </div>
            </div><div class="taal-groep taal-de">
              <div class="taal-label">🇩🇪 Deutsch <span class="optioneel">(optioneel)</span></div>
              <div class="veld">
                <label for="media-title-de-<?php echo $i; ?>">Titel</label>
                <input type="text" id="media-title-de-<?php echo $i; ?>" name="media[<?php echo $i; ?>][title_de]" maxlength="100" value="<?php echo htmlspecialchars($mi['title']['de'] ?? ''); ?>">
              </div>
              <div class="veld">
                <label for="media-desc-de-<?php echo $i; ?>">Beschreibung</label>
                <textarea id="media-desc-de-<?php echo $i; ?>" name="media[<?php echo $i; ?>][desc_de]" maxlength="300" style="min-height:60px;"><?php echo htmlspecialchars($mi['desc']['de'] ?? ''); ?></textarea>
              </div>
              <div class="veld">
                <label for="media-linktekst-de-<?php echo $i; ?>">Linktext</label>
                <input type="text" id="media-linktekst-de-<?php echo $i; ?>" name="media[<?php echo $i; ?>][linktekst_de]" maxlength="40" value="<?php echo htmlspecialchars($mi['linktekst']['de'] ?? ''); ?>">
              </div>
            </div></div>
          </div>
        <?php endforeach; ?>
        </div>

        <button type="submit">Media opslaan</button>
      </form>
    </div>
    </div>

    <?php endif; ?>

    <?php if (in_array('fotoboek', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-fotoboek">
    <!-- ===== FOTOBOEK ===== -->
    <div class="kaart">
      <h1>Ondertitel bovenaan de pagina</h1>
      <?php if (isset($melding['fotoboek_tekst'])): ?>
        <div class="melding <?php echo $meldingType['fotoboek_tekst']; ?>"><?php echo htmlspecialchars($melding['fotoboek_tekst']); ?></div>
      <?php endif; ?>
      <form method="post" action="beheer.php#fotoboek">
        <input type="hidden" name="formulier" value="fotoboek_tekst">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <div class="veld" data-taal-scope="fotoboek-ondertitel">
          <div class="taal-scope-kop"><label for="ft-hero-sub-nl">Tekst onder de titel "Fotoboek"</label><span class="taal-toggle-mini"><button type="button" class="auto-vertaal-btn" title="Automatisch vertalen met DeepL">🌐 Vertaal</button><button type="button" class="taal-toggle-btn" data-taal="en" aria-pressed="false">🇬🇧 EN</button><button type="button" class="taal-toggle-btn" data-taal="de" aria-pressed="false">🇩🇪 DE</button></span></div>
          <div class="taal-rij">
            <textarea class="taal-nl" id="ft-hero-sub-nl" name="ft[hero_sub][nl]" maxlength="400" placeholder="Nederlands" style="min-height:70px;"><?php echo htmlspecialchars($fotoboekTekstData['hero_sub']['nl'] ?? ''); ?></textarea>
            <textarea class="taal-en" id="ft-hero-sub-en" name="ft[hero_sub][en]" maxlength="400" placeholder="English (optioneel)" style="min-height:70px;"><?php echo htmlspecialchars($fotoboekTekstData['hero_sub']['en'] ?? ''); ?></textarea>
            <textarea class="taal-de" id="ft-hero-sub-de" name="ft[hero_sub][de]" maxlength="400" placeholder="Deutsch (optional)" style="min-height:70px;"><?php echo htmlspecialchars($fotoboekTekstData['hero_sub']['de'] ?? ''); ?></textarea>
          </div>
        </div>
        <button type="submit">Ondertitel opslaan</button>
      </form>
    </div>

    <div class="kaart" data-taal-scope="fotoboek-nieuw">
      <div class="taal-scope-kop"><h1>Nieuw album</h1><span class="taal-toggle-mini"><button type="button" class="auto-vertaal-btn" title="Automatisch vertalen met DeepL">🌐 Vertaal</button><button type="button" class="taal-toggle-btn" data-taal="en" aria-pressed="false">🇬🇧 EN</button><button type="button" class="taal-toggle-btn" data-taal="de" aria-pressed="false">🇩🇪 DE</button></span></div>
      <p class="sub">Maak een album aan, daarna kun je er hieronder foto's aan toevoegen.</p>

      <?php if (isset($melding['fotoboek'])): ?>
        <div class="melding <?php echo $meldingType['fotoboek']; ?>"><?php echo htmlspecialchars($melding['fotoboek']); ?></div>
      <?php endif; ?>

      <form method="post" action="beheer.php#fotoboek">
        <input type="hidden" name="formulier" value="fotoboek_album_aanmaken">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <div class="taal-rij">
          <div class="veld taal-nl">
            <label for="fotoboek-nieuw-titel-nl">🇳🇱 Titel</label>
            <input type="text" id="fotoboek-nieuw-titel-nl" name="titel_nl" maxlength="60" placeholder="Bijv.: ZomerBBQ 2026">
          </div>
          <div class="veld taal-en">
            <label for="fotoboek-nieuw-titel-en">🇬🇧 Title <span class="optioneel">(optioneel)</span></label>
            <input type="text" id="fotoboek-nieuw-titel-en" name="titel_en" maxlength="60">
          </div>
          <div class="veld taal-de">
            <label for="fotoboek-nieuw-titel-de">🇩🇪 Titel <span class="optioneel">(optioneel)</span></label>
            <input type="text" id="fotoboek-nieuw-titel-de" name="titel_de" maxlength="60">
          </div>
        </div>
        <button type="submit">Album aanmaken</button>
      </form>
    </div>

    <?php if (count($fotoboekData['albums']) === 0): ?>
      <div class="kaart">
        <p class="hint">Nog geen albums aangemaakt.</p>
      </div>
    <?php endif; ?>

    <?php foreach ($fotoboekData['albums'] as $album): $slug = $album['slug']; ?>
      <div class="kaart">
        <details class="fotoboek-album-details" data-taal-scope="fotoboek-album-<?php echo htmlspecialchars($slug); ?>">
          <summary class="fotoboek-album-kop">
            <span class="fotoboek-album-titel"><span class="fotoboek-album-volgnummer">#<?php echo htmlspecialchars((string) ($album['volgorde'] ?? 0)); ?></span><?php echo htmlspecialchars($album['title']['nl'] ?? $slug); ?><?php if (!empty($album['verborgen'])): ?> <span class="fotoboek-cover-badge" style="background:var(--rust); color:#fff;">verborgen</span><?php endif; ?></span>
            <span class="taal-scope-kop"><span class="hint"><?php echo count($album['photos']); ?> foto('s)</span><span class="taal-toggle-mini"><button type="button" class="auto-vertaal-btn" title="Automatisch vertalen met DeepL">🌐 Vertaal</button><button type="button" class="taal-toggle-btn" data-taal="en" aria-pressed="false">🇬🇧 EN</button><button type="button" class="taal-toggle-btn" data-taal="de" aria-pressed="false">🇩🇪 DE</button></span></span>
          </summary>
          <div class="fotoboek-album-inhoud">
          <form method="post" action="beheer.php#fotoboek" enctype="multipart/form-data" class="fotoboek-album-form">
          <input type="hidden" name="formulier" value="fotoboek_album_bewerken">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
          <input type="hidden" name="slug" value="<?php echo htmlspecialchars($slug); ?>">

          <p class="sub">Map: images/fotoboek/<?php echo htmlspecialchars($slug); ?>/</p>

          <div class="taal-rij">
            <div class="veld taal-nl">
              <label for="fotoboek-<?php echo $slug; ?>-titel-nl">🇳🇱 Titel</label>
              <input type="text" id="fotoboek-<?php echo $slug; ?>-titel-nl" name="titel_nl" maxlength="60" value="<?php echo htmlspecialchars($album['title']['nl'] ?? ''); ?>">
            </div>
            <div class="veld taal-en">
              <label for="fotoboek-<?php echo $slug; ?>-titel-en">🇬🇧 Title <span class="optioneel">(optioneel)</span></label>
              <input type="text" id="fotoboek-<?php echo $slug; ?>-titel-en" name="titel_en" maxlength="60" value="<?php echo htmlspecialchars($album['title']['en'] ?? ''); ?>">
            </div>
            <div class="veld taal-de">
              <label for="fotoboek-<?php echo $slug; ?>-titel-de">🇩🇪 Titel <span class="optioneel">(optioneel)</span></label>
              <input type="text" id="fotoboek-<?php echo $slug; ?>-titel-de" name="titel_de" maxlength="60" value="<?php echo htmlspecialchars($album['title']['de'] ?? ''); ?>">
            </div>
          </div>
          <div class="rij-2">
            <div class="veld">
              <label for="fotoboek-<?php echo $slug; ?>-datum">Datum</label>
              <div class="datum-invoer-rij">
                <input type="text" inputmode="numeric" id="fotoboek-<?php echo $slug; ?>-datum" name="datum" maxlength="10" placeholder="dd-mm-jjjj" pattern="\d{2}-\d{2}-\d{4}" value="<?php echo htmlspecialchars(datumWeergave($album['date'] ?? '')); ?>">
                <button type="button" class="datum-picker-wrap" title="Datum kiezen uit kalender" aria-label="Datum kiezen"><span class="datum-picker-icoon" aria-hidden="true">📅</span><input type="date" class="datum-picker" data-doel="fotoboek-<?php echo $slug; ?>-datum" tabindex="-1" aria-hidden="true"></button>
              </div>
              <p class="hint">Wordt getoond op de albumkaart op de website.</p>
            </div>
            <div class="veld">
              <label for="fotoboek-<?php echo $slug; ?>-volgorde">Volgorde</label>
              <input type="text" inputmode="numeric" id="fotoboek-<?php echo $slug; ?>-volgorde" name="volgorde" value="<?php echo htmlspecialchars((string) ($album['volgorde'] ?? 0)); ?>">
              <p class="hint">Laagste nummer staat vooraan op de website.</p>
            </div>
          </div>

          <div class="veld">
            <label>Kort verhaal <span class="optioneel">(optioneel)</span></label>
            <p class="hint" style="margin-top:-4px; margin-bottom:10px;">Komt onder de titel te staan zodra iemand het album opent. Laat alle drie leeg om niets te tonen.</p>
            <div class="taal-rij">
              <div class="veld taal-nl">
                <label for="fotoboek-<?php echo $slug; ?>-beschrijving-nl">🇳🇱 Tekst</label>
                <textarea id="fotoboek-<?php echo $slug; ?>-beschrijving-nl" name="beschrijving_nl" maxlength="600"><?php echo htmlspecialchars($album['beschrijving']['nl'] ?? ''); ?></textarea>
              </div>
              <div class="veld taal-en">
                <label for="fotoboek-<?php echo $slug; ?>-beschrijving-en">🇬🇧 Text <span class="optioneel">(optioneel)</span></label>
                <textarea id="fotoboek-<?php echo $slug; ?>-beschrijving-en" name="beschrijving_en" maxlength="600"><?php echo htmlspecialchars($album['beschrijving']['en'] ?? ''); ?></textarea>
              </div>
              <div class="veld taal-de">
                <label for="fotoboek-<?php echo $slug; ?>-beschrijving-de">🇩🇪 Text <span class="optioneel">(optioneel)</span></label>
                <textarea id="fotoboek-<?php echo $slug; ?>-beschrijving-de" name="beschrijving_de" maxlength="600"><?php echo htmlspecialchars($album['beschrijving']['de'] ?? ''); ?></textarea>
              </div>
            </div>
            <p class="hint">Engels en Duits leeg laten? Dan toont de website daar automatisch de Nederlandse tekst.</p>
          </div>

          <?php if (count($album['photos']) > 0): ?>
            <div class="veld">
              <label>Foto's</label>
              <p class="hint" style="margin-top:-4px; margin-bottom:12px;">Met de pijltjes verplaats je een foto, de volgorde hier is ook de volgorde op de website. Watermerk toevoegen kan niet ongedaan gemaakt worden, het origineel zonder watermerk wordt niet bewaard.</p>
              <label class="fotoboek-check" style="margin-bottom:12px;">
                <input type="checkbox" name="album_watermerk_alle" value="1">
                Watermerk (opnieuw) toevoegen aan alle foto's in dit album
              </label>
              <p class="hint" style="margin-top:-8px; margin-bottom:12px;">Verwerkt alle foto's in dit album, ook de foto's die al een watermerk-vinkje hebben. Handig als foto's op de server ooit buiten beheer.php om zijn overschreven.</p>
              <div class="fotoboek-foto-lijst">
              <?php foreach ($album['photos'] as $i => $foto): $isVideo = ($foto['type'] ?? 'photo') === 'video'; ?>
                <div class="fotoboek-foto-blok">
                  <div class="fotoboek-foto-volgorde">
                    <button type="button" onclick="fotoboekVerplaats(this, -1)" title="Naar voren" aria-label="Foto naar voren verplaatsen">▲</button>
                    <button type="button" onclick="fotoboekVerplaats(this, 1)" title="Naar achteren" aria-label="Foto naar achteren verplaatsen">▼</button>
                  </div>
                  <?php if ($isVideo): ?>
                    <?php if (!empty($foto['poster'])): ?>
                      <div class="fotoboek-video-thumb" style="background-image:url('images/fotoboek/<?php echo htmlspecialchars($slug); ?>/thumbs/<?php echo htmlspecialchars($foto['poster']); ?>');">▶</div>
                    <?php else: ?>
                      <div class="fotoboek-video-thumb">🎬</div>
                    <?php endif; ?>
                  <?php else: ?>
                    <img src="images/fotoboek/<?php echo htmlspecialchars($slug); ?>/thumbs/<?php echo htmlspecialchars($foto['file']); ?>" alt="">
                  <?php endif; ?>
                  <div class="fotoboek-foto-velden">
                    <input type="hidden" name="foto[<?php echo $i; ?>][bestand]" value="<?php echo htmlspecialchars($foto['file']); ?>">
                    <input type="text" class="taal-nl" name="foto[<?php echo $i; ?>][caption_nl]" maxlength="150" placeholder="Bijschrift NL (optioneel)" value="<?php echo htmlspecialchars($foto['caption']['nl'] ?? ''); ?>">
                    <input type="text" class="taal-en" name="foto[<?php echo $i; ?>][caption_en]" maxlength="150" placeholder="Caption EN (optional)" value="<?php echo htmlspecialchars($foto['caption']['en'] ?? ''); ?>">
                    <input type="text" class="taal-de" name="foto[<?php echo $i; ?>][caption_de]" maxlength="150" placeholder="Bildtext DE (optional)" value="<?php echo htmlspecialchars($foto['caption']['de'] ?? ''); ?>">
                    <div class="fotoboek-foto-rij">
                      <?php if ($isVideo): ?>
                        <span class="fotoboek-cover-badge" style="background:var(--teal-light); color:var(--teal-dark);">🎬 video<?php echo empty($foto['poster']) ? ' (geen voorbeeldbeeld)' : ''; ?></span>
                      <?php else: ?>
                        <label class="fotoboek-check">
                          <input type="radio" name="cover" value="<?php echo $i; ?>" <?php if (($album['cover'] ?? '') === $foto['file']) echo 'checked'; ?>>
                          cover foto
                          <?php if (($album['cover'] ?? '') === $foto['file']): ?><span class="fotoboek-cover-badge">huidige cover</span><?php endif; ?>
                        </label>
                        <?php if (!empty($foto['watermerk'])): ?>
                          <span class="fotoboek-cover-badge" style="background:var(--gold-light); color:var(--rust);">✓ watermerk</span>
                        <?php endif; ?>
                        <label class="fotoboek-check">
                          <input type="checkbox" name="foto[<?php echo $i; ?>][watermerk_toevoegen]" value="1">
                          <?php echo !empty($foto['watermerk']) ? 'watermerk opnieuw toepassen' : 'watermerk toevoegen'; ?>
                        </label>
                      <?php endif; ?>
                      <label class="fotoboek-check">
                        <input type="checkbox" name="foto[<?php echo $i; ?>][verwijderen]" value="1">
                        verwijderen
                      </label>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <div class="veld fotoboek-upload-blok">
            <label for="fotoboek-<?php echo $slug; ?>-upload">Nieuwe foto's<?php echo $fotoboekVideoAan ? ' of video\'s' : ''; ?> toevoegen</label>
            <input type="file" id="fotoboek-<?php echo $slug; ?>-upload" name="nieuwe_fotos[]" accept="image/png,image/jpeg,image/webp,image/heic,image/heif,.heic,.heif<?php echo $fotoboekVideoAan ? ',video/mp4,.mp4' : ''; ?>" multiple>
            <p class="hint">Meerdere bestanden tegelijk mogen, ook heel veel: grote uploads worden automatisch in groepjes verstuurd, dat kan even duren, laat het tabblad gewoon open staan. Foto's: JPG, PNG, WEBP of HEIC (iPhone), max <?php echo (int) round($fotoboekMaxFotoBytes / 1024 / 1024); ?> MB per foto. HEIC wordt automatisch omgezet naar JPEG bij het uploaden.<?php echo $fotoboekVideoAan ? ' Video: mp4, max ' . (int) round($fotoboekMaxVideoBytes / 1024 / 1024) . ' MB. Er wordt automatisch een voorbeeldbeeld uit de video gemaakt, geen watermerk mogelijk.' : ' Video-upload staat tijdelijk uit.'; ?></p>
            <label class="fotoboek-check" style="margin-top:8px;">
              <input type="checkbox" name="watermerk" value="1" checked>
              Klein watermerk (logo + rc045.nl) toevoegen aan nieuwe foto's (niet op video's)
            </label>
          </div>

          <div class="veld fotoboek-verberg-blok">
            <label class="fotoboek-check">
              <input type="checkbox" name="album_verborgen" value="1" <?php if (!empty($album['verborgen'])) echo 'checked'; ?>>
              Album verbergen op de website
            </label>
            <p class="hint" style="margin-top:2px;">Het album en de foto's blijven bewaard, maar zijn niet zichtbaar op de fotoboekpagina totdat je het vinkje weer uitzet. Wijziging wordt opgeslagen samen met de rest van dit album via "Album opslaan".</p>
          </div>

          <button type="submit">Album opslaan</button>

          <div class="fotoboek-verwijder-blok">
            <label class="fotoboek-check">
              <input type="checkbox" name="album_verwijderen" value="1" onchange="if(this.checked && !confirm('Album &quot;<?php echo htmlspecialchars($album['title']['nl'] ?? $slug, ENT_QUOTES); ?>&quot; en alle foto\'s hierin definitief verwijderen?')) this.checked=false;">
              Dit album inclusief alle foto's verwijderen (kan niet ongedaan gemaakt worden)
            </label>
          </div>
          </form>
          </div>
        </details>
      </div>
    <?php endforeach; ?>
    </div>

    <?php endif; ?>

    <?php if (in_array('gebruikers', $toegestaneTabs, true)): ?>

    <div class="tab-paneel" id="tab-gebruikers">
    <!-- ===== GEBRUIKERS ===== -->
    <div class="kaart">
      <h1>Gebruikers</h1>
      <p class="sub">Iedereen die kan inloggen. Zonder aangevinkte tabbladen komt iemand alleen op de ledenpagina; met vinkjes ook hier in het beheer. Koppel een account bij Leden aan het juiste lid, anders ziet die persoon op de ledenpagina niets persoonlijks.</p>

      <?php if (isset($melding['gebruikers'])): ?>
        <div class="melding <?php echo $meldingType['gebruikers']; ?>"><?php echo htmlspecialchars($melding['gebruikers']); ?></div>
      <?php endif; ?>

      <?php if (count($gebruikersLijst) === 0): ?>
        <p class="hint">Nog geen gebruikers aangemaakt.</p>
      <?php else: ?>
        <?php foreach ($gebruikersLijst as $g): ?>
          <?php
            // Geen 'tabs'-veld opgeslagen (nog nooit ingesteld) betekent
            // hier, net als bij het bepalen van de echte rechten hierboven,
            // volledige toegang: alle vinkjes staan dan aan.
            $gHeeftBeperking = isset($g['tabs']) && is_array($g['tabs']);
            // Geen enkel tabblad aangevinkt betekent: alleen de ledenpagina,
            // geen toegang tot dit beheerscherm. Accounts zonder 'tabs'-veld
            // (van voor die instelling) mogen nog alles, zie authRechten().
            $gAlleenLeden = $gHeeftBeperking && count($g['tabs']) === 0;
          ?>
          <div class="gebruiker-rij">
            <div class="gebruiker-rij-boven">
              <div>
                <strong><?php echo htmlspecialchars($g['gebruikersnaam'] ?? ''); ?></strong>
                <span class="gebruiker-sinds"><?php echo $gAlleenLeden ? 'alleen ledenpagina' : 'ook beheer'; ?></span>
                <?php if (!empty($g['aangemaakt'])): ?>
                  <span class="gebruiker-sinds">sinds <?php echo htmlspecialchars(date('d-m-Y', strtotime($g['aangemaakt']))); ?></span>
                <?php endif; ?>
              </div>
              <form method="post" action="beheer.php#gebruikers" onsubmit="return confirm('Gebruiker &quot;<?php echo htmlspecialchars($g['gebruikersnaam'] ?? '', ENT_QUOTES); ?>&quot; verwijderen?');">
                <input type="hidden" name="formulier" value="gebruiker_verwijderen">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="gebruikersnaam" value="<?php echo htmlspecialchars($g['gebruikersnaam'] ?? ''); ?>">
                <button type="submit" class="knop-klein">Verwijderen</button>
              </form>
            </div>
            <form method="post" action="beheer.php#gebruikers" class="gebruiker-tabs-form">
              <input type="hidden" name="formulier" value="gebruiker_tabs_bijwerken">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
              <input type="hidden" name="gebruikersnaam" value="<?php echo htmlspecialchars($g['gebruikersnaam'] ?? ''); ?>">
              <div class="veld">
                <label id="gebruiker-tabs-label-<?php echo htmlspecialchars($g['gebruikersnaam'] ?? ''); ?>">Toegang tot</label>
                <div class="multiselect">
                  <button type="button" class="multiselect-trigger" aria-expanded="false" aria-labelledby="gebruiker-tabs-label-<?php echo htmlspecialchars($g['gebruikersnaam'] ?? ''); ?>">
                    <span class="multiselect-label">Alles</span>
                    <span class="multiselect-pijl" aria-hidden="true">▾</span>
                  </button>
                  <div class="multiselect-paneel" hidden>
                    <input type="text" class="multiselect-zoek" placeholder="Zoeken">
                    <div class="multiselect-opties">
                      <?php foreach ($beheerTabsToewijsbaar as $tabSleutel => $tabLabel): ?>
                        <label class="multiselect-optie">
                          <input type="checkbox" name="tabs[]" value="<?php echo $tabSleutel; ?>" <?php if (!$gHeeftBeperking || in_array($tabSleutel, $g['tabs'], true)) echo 'checked'; ?>>
                          <span><?php echo htmlspecialchars($tabLabel); ?></span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                    <div class="multiselect-acties">
                      <button type="button" data-actie="alles">Alles</button>
                      <button type="button" data-actie="niets">Niets</button>
                    </div>
                  </div>
                </div>
              </div>
              <button type="submit" class="knop-klein">Toegang opslaan</button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="kaart">
      <h1>Nieuwe gebruiker</h1>
      <p class="sub">Bestaat de gebruikersnaam al, dan wordt alleen het wachtwoord bijgewerkt.</p>
      <form method="post" action="beheer.php#gebruikers">
        <input type="hidden" name="formulier" value="gebruiker_toevoegen">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <div class="veld">
          <label for="nieuwe-gebruikersnaam">Gebruikersnaam</label>
          <input type="text" id="nieuwe-gebruikersnaam" name="nieuwe_gebruikersnaam" maxlength="30" placeholder="Bijv.: jan" autocapitalize="off" required>
        </div>
        <div class="veld">
          <label for="nieuw-wachtwoord">Wachtwoord</label>
          <input type="password" id="nieuw-wachtwoord" name="nieuw_wachtwoord" autocomplete="new-password" required>
          <p class="hint">Minstens 8 tekens.</p>
        </div>
        <div class="veld">
          <label for="nieuw-wachtwoord-herhaald">Wachtwoord herhalen</label>
          <input type="password" id="nieuw-wachtwoord-herhaald" name="nieuw_wachtwoord_herhaald" autocomplete="new-password" required>
        </div>
        <div class="veld" id="nieuwe-gebruiker-tabs">
          <label id="nieuwe-gebruiker-tabs-label">Toegang tot</label>
          <div class="multiselect">
            <button type="button" class="multiselect-trigger" aria-expanded="false" aria-labelledby="nieuwe-gebruiker-tabs-label">
              <span class="multiselect-label">Niets geselecteerd</span>
              <span class="multiselect-pijl" aria-hidden="true">▾</span>
            </button>
            <div class="multiselect-paneel" hidden>
              <input type="text" class="multiselect-zoek" placeholder="Zoeken">
              <div class="multiselect-opties">
                <?php foreach ($beheerTabsToewijsbaar as $tabSleutel => $tabLabel): ?>
                  <label class="multiselect-optie">
                    <input type="checkbox" name="tabs[]" value="<?php echo $tabSleutel; ?>">
                    <span><?php echo htmlspecialchars($tabLabel); ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
              <div class="multiselect-acties">
                <button type="button" data-actie="alles">Alles</button>
                <button type="button" data-actie="niets">Niets</button>
              </div>
            </div>
          </div>
          <p class="hint">Standaard staat alles uit: dan kan deze persoon alleen op de ledenpagina, niet in dit beheerscherm. Vink aan waar hij of zij wél bij mag. Achteraf aanpassen kan altijd, hierboven in de lijst. Geldt alleen bij het aanmaken; bij een bestaande gebruikersnaam wordt hier alleen het wachtwoord bijgewerkt en blijft de toegang zoals hij was.</p>
        </div>
        <button type="submit">Gebruiker opslaan</button>
      </form>
    </div>
    </div>
    <?php endif; ?>

    <?php if (in_array('log', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-log">
    <!-- ===== LOGBOEK ===== -->
    <div class="kaart">
      <h1>Logboek</h1>
      <p class="sub">De laatste wijzigingen van de afgelopen 90 dagen, nieuwste bovenaan.</p>

      <?php if (count($logRegels) === 0): ?>
        <p class="hint">Nog geen activiteit gelogd.</p>
      <?php else: ?>
        <table class="reken" id="logboek-tabel">
          <tr>
            <th>Tijd <button type="button" class="logboek-filter-knop" data-kolom="0" aria-label="Filter op tijd">Filter ▾</button></th>
            <th>Gebruiker <button type="button" class="logboek-filter-knop" data-kolom="1" aria-label="Filter op gebruiker">Filter ▾</button></th>
            <th>Actie <button type="button" class="logboek-filter-knop" data-kolom="2" aria-label="Filter op actie">Filter ▾</button></th>
          </tr>
          <?php foreach (array_slice($logRegels, 0, 1000) as $regel): ?>
            <tr>
              <td data-label="Tijd" data-filterwaarde="<?php echo htmlspecialchars(date('d-m-Y', strtotime($regel['tijd'] ?? ''))); ?>"><?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($regel['tijd'] ?? ''))); ?></td>
              <td data-label="Wie" data-filterwaarde="<?php echo htmlspecialchars($regel['gebruiker'] ?? ''); ?>"><?php echo htmlspecialchars($regel['gebruiker'] ?? ''); ?></td>
              <td data-label="Actie" data-filterwaarde="<?php echo htmlspecialchars($regel['actie'] ?? ''); ?>"><?php echo htmlspecialchars($regel['actie'] ?? ''); ?><?php echo !empty($regel['details']) ? ': ' . htmlspecialchars($regel['details']) : ''; ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
        <p class="hint logboek-geen-resultaten-melding" hidden>Geen regels komen overeen met de filters.</p>
      <?php endif; ?>
    </div>
    </div>
    <?php endif; ?>

    <?php if (in_array('backups', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-backups">
    <!-- ===== BACK-UPS ===== -->
    <?php if (isset($melding['backups'])): ?>
      <div class="melding <?php echo $meldingType['backups']; ?>"><?php echo htmlspecialchars($melding['backups']); ?></div>
    <?php endif; ?>

    <?php foreach ($dataBackupBestanden as $sleutel => $info): ?>
      <div class="kaart">
        <h1><?php echo htmlspecialchars($info['label']); ?></h1>
        <p class="sub">Automatische back-up vlak vóór elke keer opslaan, bewaard voor 90 dagen.</p>
        <?php
          $volledigeBackupLijst = lijstDataBackups($dataBackupMap, basename($info['pad']));
          $getoondeBackups = array_slice($volledigeBackupLijst, 0, 20);
        ?>
        <?php if (count($getoondeBackups) === 0): ?>
          <p class="hint">Nog geen back-up van dit bestand.</p>
        <?php else: ?>
          <form method="post" action="beheer.php#backups" class="backup-herstel-form" onsubmit="return confirm('<?php echo htmlspecialchars($info['label'], ENT_QUOTES); ?> terugzetten naar de geselecteerde versie? De huidige versie wordt eerst zelf ook als back-up bewaard.');">
            <input type="hidden" name="formulier" value="backup_herstellen">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="sleutel" value="<?php echo htmlspecialchars($sleutel); ?>">
            <div class="veld">
              <label for="backup-kiezen-<?php echo $sleutel; ?>">Versie</label>
              <select id="backup-kiezen-<?php echo $sleutel; ?>" name="backup_bestand">
                <?php foreach ($getoondeBackups as $b): ?>
                  <option value="<?php echo htmlspecialchars($b['bestand']); ?>"><?php echo htmlspecialchars(date('d-m-Y H:i', $b['tijd'])); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="knop-klein">Terugzetten</button>
          </form>
          <?php if (count($volledigeBackupLijst) > count($getoondeBackups)): ?>
            <p class="hint">Nieuwste 20 van de <?php echo count($volledigeBackupLijst); ?> getoond, nieuwste bovenaan in de lijst.</p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    </div>

    <?php endif; ?>

    <?php if (in_array('rekentabel', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-rekentabel">
    <!-- ===== REKENTABEL CONTRIBUTIE (bewerkbaar) ===== -->
    <div class="kaart">
      <h1>Rekentabel contributie</h1>
      <p class="sub">Deze bedragen bepalen zowel de referentietabel hieronder als de contributiecalculator op aanmelden.html. Wijzig ze hier één keer per jaar; beide plekken gebruiken automatisch dezelfde waarden. De jaarcontributie voor volgend jaar staat er apart onder, omdat aanmelden.html die aan een nieuw lid laat zien.</p>

      <?php if (isset($melding['rekentabel'])): ?>
        <div class="melding <?php echo $meldingType['rekentabel']; ?>"><?php echo htmlspecialchars($melding['rekentabel']); ?></div>
      <?php endif; ?>

      <form method="post" action="beheer.php#rekentabel">
        <input type="hidden" name="formulier" value="rekentabel">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <div class="rij-3">
          <div class="veld">
            <label for="rekentabel-jaar">Contributiejaar</label>
            <input type="number" id="rekentabel-jaar" name="jaar" min="2020" max="2099" step="1" value="<?php echo htmlspecialchars($rekentabelData['jaar']); ?>">
            <p class="hint">Wordt onder meer getoond op aanmelden.html en in de betaalreferentie.</p>
          </div>
          <div class="veld">
            <label for="rekentabel-inschrijfkosten">Inschrijfkosten (eenmalig)</label>
            <input type="number" id="rekentabel-inschrijfkosten" name="inschrijfkosten" min="0" step="0.01" value="<?php echo htmlspecialchars((string) $rekentabelData['inschrijfkosten']); ?>">
          </div>
          <div class="veld">
            <label for="rekentabel-leeftijd">Jeugd t/m leeftijd</label>
            <input type="number" id="rekentabel-leeftijd" name="jeugd_leeftijd_tot" min="1" max="99" step="1" value="<?php echo htmlspecialchars((string) $rekentabelData['jeugd_leeftijd_tot']); ?>">
            <p class="hint">Senior begint bij deze leeftijd + 1.</p>
          </div>
        </div>
        <div class="rij-2">
          <div class="veld">
            <label for="rekentabel-jeugd">Jaarcontributie jeugd</label>
            <input type="number" id="rekentabel-jeugd" name="jeugd_jaarbedrag" min="0" step="1" value="<?php echo htmlspecialchars((string) (int) round((float) $rekentabelData['jeugd_jaarbedrag'])); ?>">
            <p class="hint">Hele euro's, geen centen.</p>
          </div>
          <div class="veld">
            <label for="rekentabel-senior">Jaarcontributie senior</label>
            <input type="number" id="rekentabel-senior" name="senior_jaarbedrag" min="0" step="1" value="<?php echo htmlspecialchars((string) (int) round((float) $rekentabelData['senior_jaarbedrag'])); ?>">
            <p class="hint">Hele euro's, geen centen.</p>
          </div>
        </div>
        <h2 class="leden-kop">Jaarcontributie <?php echo (int) $rekentabelData['jaar'] + 1; ?></h2>
        <p class="hint">Vul deze pas in zodra de contributie voor <?php echo (int) $rekentabelData['jaar'] + 1; ?> is vastgesteld. Zolang ze leeg zijn, rekent de site voor volgend jaar met de bedragen van <?php echo htmlspecialchars($rekentabelData['jaar']); ?>. Wie zich nu aanmeldt ziet dit bedrag op aanmelden.html staan als de contributie die volgend jaar volgt, dus vul hier het nieuwe bedrag in zodra dat bekend is.</p>
        <div class="rij-2">
          <div class="veld">
            <label for="rekentabel-jeugd-volgend">Jaarcontributie jeugd <?php echo (int) $rekentabelData['jaar'] + 1; ?></label>
            <input type="number" id="rekentabel-jeugd-volgend" name="jeugd_jaarbedrag_volgend" min="0" step="1" value="<?php echo $rekentabelData['jeugd_jaarbedrag_volgend'] === '' || $rekentabelData['jeugd_jaarbedrag_volgend'] === null ? '' : htmlspecialchars((string) (int) round((float) $rekentabelData['jeugd_jaarbedrag_volgend'])); ?>" placeholder="nog niet vastgesteld">
            <p class="hint">Leeg laten = zelfde bedrag als <?php echo htmlspecialchars($rekentabelData['jaar']); ?>.</p>
          </div>
          <div class="veld">
            <label for="rekentabel-senior-volgend">Jaarcontributie senior <?php echo (int) $rekentabelData['jaar'] + 1; ?></label>
            <input type="number" id="rekentabel-senior-volgend" name="senior_jaarbedrag_volgend" min="0" step="1" value="<?php echo $rekentabelData['senior_jaarbedrag_volgend'] === '' || $rekentabelData['senior_jaarbedrag_volgend'] === null ? '' : htmlspecialchars((string) (int) round((float) $rekentabelData['senior_jaarbedrag_volgend'])); ?>" placeholder="nog niet vastgesteld">
            <p class="hint">Leeg laten = zelfde bedrag als <?php echo htmlspecialchars($rekentabelData['jaar']); ?>.</p>
          </div>
        </div>
        <p class="hint">De maandbedragen hieronder worden automatisch berekend als pro-rata deel van de jaarcontributie (hele euro's, naar boven/beneden afgerond). December is altijd alleen inschrijfkosten.</p>
        <button type="submit">Rekentabel opslaan</button>
      </form>
    </div>

    <div class="kaart">
      <h1>Referentietabel <?php echo htmlspecialchars($rekentabelData['jaar']); ?></h1>
      <p class="sub">Wat betaalt een nieuw lid, per maand van aanmelding (inclusief <?php echo euro($inschrijfkosten); ?> inschrijfkosten)</p>
      <table class="reken">
        <tr>
          <th>Maand</th>
          <th>Jeugd t/m <?php echo (int) $rekentabelData['jeugd_leeftijd_tot']; ?></th>
          <th>Senior <?php echo (int) $rekentabelData['jeugd_leeftijd_tot'] + 1; ?>+</th>
        </tr>
        <?php foreach ($maandNamen as $m => $naam): ?>
        <tr<?php if ($m === $huidigeMaand) echo ' class="nu"'; ?>>
          <td><?php echo $naam; ?><?php if ($m === $huidigeMaand) echo ' ◀'; ?></td>
          <?php if ($tabelJeugd[$m] === null): ?>
            <td colspan="2"><?php echo euro($inschrijfkosten); ?> (alleen inschrijfkosten, contributie volgend jaar later overmaken)</td>
          <?php else: ?>
            <td><?php echo euro($tabelJeugd[$m] + $inschrijfkosten); ?></td>
            <td><?php echo euro($tabelSenior[$m] + $inschrijfkosten); ?></td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </table>
      <p class="reken-noot">Bedragen zijn pro-rata contributie voor de resterende maanden plus <?php echo euro($inschrijfkosten); ?> eenmalige inschrijfkosten. Volledige jaarcontributie: jeugd <?php echo euro($rekentabelData['jeugd_jaarbedrag']); ?>, senior <?php echo euro($rekentabelData['senior_jaarbedrag']); ?>. Voor <?php echo (int) $rekentabelData['jaar'] + 1; ?><?php
  $volgendJeugd  = rekentabelJaarbedrag($rekentabelData, true,  (int) $rekentabelData['jaar'] + 1);
  $volgendSenior = rekentabelJaarbedrag($rekentabelData, false, (int) $rekentabelData['jaar'] + 1);
  $volgendIngevuld = ($rekentabelData['jeugd_jaarbedrag_volgend'] !== '' && $rekentabelData['jeugd_jaarbedrag_volgend'] !== null)
                  || ($rekentabelData['senior_jaarbedrag_volgend'] !== '' && $rekentabelData['senior_jaarbedrag_volgend'] !== null);
?>: jeugd <?php echo euro($volgendJeugd); ?>, senior <?php echo euro($volgendSenior); ?><?php echo $volgendIngevuld ? '' : ' (nog niet vastgesteld, daarom hetzelfde bedrag)'; ?>. Deze tabel en de calculator op aanmelden.html lezen allebei data/rekentabel.json, bewerk hierboven.</p>
    </div>
    </div>

    <?php endif; ?>

    <?php if (in_array('changelog', $toegestaneTabs, true)): ?>
    <div class="tab-paneel" id="tab-changelog">
    <!-- ===== CHANGELOG ===== -->
    <div class="kaart">
      <h1>Changelog</h1>
      <p class="sub">Wat er aan de website en dit beheerpaneel is veranderd, nieuwste bovenaan. De regels over de website zelf worden bij elke aanpassing meegeleverd met de code. Regels die je hier zelf toevoegt (bijvoorbeeld over de inhoud van de site) komen erbij en zijn wel te bewerken.</p>

      <?php if (isset($melding['changelog'])): ?>
        <div class="melding <?php echo $meldingType['changelog']; ?>"><?php echo htmlspecialchars($melding['changelog']); ?></div>
      <?php endif; ?>
    </div>

    <details class="kaart">
      <summary>Regel toevoegen<span class="kaart-uitklap-telling">eigen regels: <?php echo count($changelogEigen); ?></span></summary>
      <div class="kaart-uitklap-inhoud">
        <form method="post" action="beheer.php#changelog">
          <input type="hidden" name="formulier" value="changelog_toevoegen">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
          <div class="rij-2">
            <div class="veld">
              <label for="cl-nieuw-datum">Datum</label>
              <div class="datum-invoer-rij">
                <input type="text" inputmode="numeric" id="cl-nieuw-datum" name="datum" maxlength="10" placeholder="dd-mm-jjjj" pattern="\d{2}-\d{2}-\d{4}" value="<?php echo htmlspecialchars(datumWeergave(date('Y-m-d'))); ?>">
                <button type="button" class="datum-picker-wrap" title="Datum kiezen uit kalender" aria-label="Datum kiezen"><span class="datum-picker-icoon" aria-hidden="true">📅</span><input type="date" class="datum-picker" data-doel="cl-nieuw-datum" tabindex="-1" aria-hidden="true"></button>
              </div>
            </div>
            <div class="veld">
              <label for="cl-nieuw-cat">Categorie</label>
              <select id="cl-nieuw-cat" name="cat">
                <?php foreach ($changelogCategorieen as $clSleutel => $clLabel): ?>
                  <option value="<?php echo htmlspecialchars($clSleutel); ?>"><?php echo htmlspecialchars($clLabel); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="veld">
            <label for="cl-nieuw-titel">Korte omschrijving</label>
            <input type="text" id="cl-nieuw-titel" name="titel" maxlength="120" placeholder="Bijv.: Nieuwe foto's van de clubdag toegevoegd">
          </div>
          <div class="veld">
            <label for="cl-nieuw-tekst">Toelichting (optioneel)</label>
            <textarea id="cl-nieuw-tekst" name="tekst" maxlength="500" style="min-height:60px;"></textarea>
          </div>
          <button type="submit">Regel toevoegen</button>
        </form>
      </div>
    </details>

    <div class="kaart">
      <div class="cl-filterbalk">
        <?php foreach ($changelogCategorieen as $clSleutel => $clLabel): ?>
          <button type="button" class="leden-badge leden-badge-klikbaar cl-filter-knop cl-<?php echo htmlspecialchars($clSleutel); ?>" data-cat="<?php echo htmlspecialchars($clSleutel); ?>" aria-pressed="false" title="Klik om alleen '<?php echo htmlspecialchars($clLabel); ?>' te tonen"><?php echo htmlspecialchars($clLabel); ?>: <?php echo $changelogTellingen[$clSleutel]; ?></button>
        <?php endforeach; ?>
        <div class="veld cl-zoek">
          <input type="search" id="cl-zoek" placeholder="Zoeken in de changelog" aria-label="Zoeken in de changelog">
        </div>
      </div>
      <p class="hint" id="cl-telling"><?php echo count($changelogLijst); ?> regel<?php echo count($changelogLijst) === 1 ? '' : 's'; ?></p>

      <div class="cl-lijst" id="cl-lijst">
        <?php if (empty($changelogLijst)): ?>
          <p class="cl-leeg">Nog geen regels.</p>
        <?php endif; ?>
        <?php foreach ($changelogLijst as $clI => $clRegel): ?>
          <?php
            $clCat = $clRegel['cat'] ?? 'nieuw';
            if (!isset($changelogCategorieen[$clCat])) $clCat = 'nieuw';
            $clEigen = ($clRegel['bron'] ?? '') === 'eigen';
            $clId = (string) ($clRegel['id'] ?? '');
            // Alles wat doorzoekbaar moet zijn in één attribuut, zodat het
            // filteren in de browser geen DOM hoeft af te lopen per veld.
            $clZoekBron = ($clRegel['titel'] ?? '') . ' ' . ($clRegel['tekst'] ?? '') . ' ' . $changelogCategorieen[$clCat];
            $clZoek = function_exists('mb_strtolower') ? mb_strtolower($clZoekBron, 'UTF-8') : strtolower($clZoekBron);
          ?>
          <div class="cl-regel" data-cat="<?php echo htmlspecialchars($clCat); ?>" data-zoek="<?php echo htmlspecialchars($clZoek); ?>">
            <div class="cl-zij">
              <span class="cl-datum"><?php echo htmlspecialchars(datumWeergave($clRegel['datum'] ?? '')); ?></span>
              <span class="leden-badge cl-<?php echo htmlspecialchars($clCat); ?>"><?php echo htmlspecialchars($changelogCategorieen[$clCat]); ?></span>
            </div>
            <div class="cl-hoofd">
              <div class="cl-titel"><?php echo htmlspecialchars($clRegel['titel'] ?? ''); ?></div>
              <?php if (trim((string) ($clRegel['tekst'] ?? '')) !== ''): ?>
                <div class="cl-tekst"><?php echo nl2br(htmlspecialchars($clRegel['tekst'])); ?></div>
              <?php endif; ?>

              <?php if ($clEigen): ?>
                <?php if (!empty($clRegel['door'])): ?>
                  <div class="cl-meta">Toegevoegd door <?php echo htmlspecialchars($clRegel['door']); ?><?php echo !empty($clRegel['gewijzigd_door']) ? ', laatst bijgewerkt door ' . htmlspecialchars($clRegel['gewijzigd_door']) : ''; ?></div>
                <?php endif; ?>
                <details>
                  <summary>Bewerken</summary>
                  <div class="cl-bewerkvak">
                    <form method="post" action="beheer.php#changelog">
                      <input type="hidden" name="formulier" value="changelog_bewerken">
                      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                      <input type="hidden" name="id" value="<?php echo htmlspecialchars($clId); ?>">
                      <div class="rij-2">
                        <div class="veld">
                          <label for="cl-datum-<?php echo $clI; ?>">Datum</label>
                          <div class="datum-invoer-rij">
                            <input type="text" inputmode="numeric" id="cl-datum-<?php echo $clI; ?>" name="datum" maxlength="10" placeholder="dd-mm-jjjj" pattern="\d{2}-\d{2}-\d{4}" value="<?php echo htmlspecialchars(datumWeergave($clRegel['datum'] ?? '')); ?>">
                            <button type="button" class="datum-picker-wrap" title="Datum kiezen uit kalender" aria-label="Datum kiezen"><span class="datum-picker-icoon" aria-hidden="true">📅</span><input type="date" class="datum-picker" data-doel="cl-datum-<?php echo $clI; ?>" tabindex="-1" aria-hidden="true"></button>
                          </div>
                        </div>
                        <div class="veld">
                          <label for="cl-cat-<?php echo $clI; ?>">Categorie</label>
                          <select id="cl-cat-<?php echo $clI; ?>" name="cat">
                            <?php foreach ($changelogCategorieen as $clSleutel => $clLabel): ?>
                              <option value="<?php echo htmlspecialchars($clSleutel); ?>"<?php echo $clSleutel === $clCat ? ' selected' : ''; ?>><?php echo htmlspecialchars($clLabel); ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>
                      <div class="veld">
                        <label for="cl-titel-<?php echo $clI; ?>">Korte omschrijving</label>
                        <input type="text" id="cl-titel-<?php echo $clI; ?>" name="titel" maxlength="120" value="<?php echo htmlspecialchars($clRegel['titel'] ?? ''); ?>">
                      </div>
                      <div class="veld">
                        <label for="cl-tekst-<?php echo $clI; ?>">Toelichting</label>
                        <textarea id="cl-tekst-<?php echo $clI; ?>" name="tekst" maxlength="500" style="min-height:60px;"><?php echo htmlspecialchars($clRegel['tekst'] ?? ''); ?></textarea>
                      </div>
                      <div class="cl-knoprij">
                        <button type="submit">Opslaan</button>
                      </div>
                    </form>
                    <form method="post" action="beheer.php#changelog" onsubmit="return confirm('Deze regel definitief verwijderen?');" style="margin-top:8px;">
                      <input type="hidden" name="formulier" value="changelog_verwijderen">
                      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                      <input type="hidden" name="id" value="<?php echo htmlspecialchars($clId); ?>">
                      <div class="cl-knoprij">
                        <button type="submit" class="cl-verwijder">Verwijderen</button>
                      </div>
                    </form>
                  </div>
                </details>
              <?php else: ?>
                <div class="cl-bron">Hoort bij de code, niet te bewerken</div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    </div>

    <?php endif; ?>

    <a class="terug" href="index.html">Naar de website</a>

    </div>
    </div>

  <?php endif; ?>

  </div>

  <?php if ($ingelogd): ?>
  <script>
    // Alleen deze pagina: staat video-upload in het fotoboek aan?
    var FOTOBOEK_VIDEO_AAN = <?php echo $fotoboekVideoAan ? 'true' : 'false'; ?>;
  </script>
  <script src="paneel.js?v=<?php echo @filemtime(__DIR__ . '/paneel.js'); ?>"></script>
  <?php endif; ?>
</body>
</html>
