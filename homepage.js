// RC045 homepage-app
//
// Alle homepage-specifieke JavaScript staat bewust in één lifecycle.
// Gedeelde sitefunctionaliteit (taalhelpers, thema, mobiel menu, footer/sponsors)
// blijft in site-i18n.js. De homepage initialiseert pas nadat de DOM gereed is.

(function () {
  'use strict';

  function initHomepage() {
    const i18n = {
      nl: {
        'nav.about': 'Over ons', 'nav.membership': 'Lidmaatschap', 'nav.track': 'De baan',
        'nav.location': 'Locatie', 'nav.photobook': 'Fotoboek', 'nav.contact': 'Contact', 'nav.join': 'Lid worden', 'hero.intro': 'Wij zijn een gezellige vereniging uit het zuiden van Limburg voor liefhebbers van elektrisch aangedreven, radiografisch bestuurbare auto\'s. Voor beginners én ervaren hobbyisten. Jong én oud.',
        'hero.btn.member': 'Lid worden!', 'hero.btn.more': 'Meer over ons',
        'update.label': '📣 Actueel:', 'info.hours': 'Openingstijden', 'info.location': 'Locatie', 'info.membership': 'Lidmaatschap', 'info.weather': 'Weer in Eygelshoven',
        'info.hours.note': 'Op vrijdag passen we onze actuele openingstijden aan.',
        'about.label': 'Wie zijn wij', 'about.title': 'Dé RC-vereniging van Zuid-Limburg',
        'about.p1': 'RC045 is een actieve vereniging voor liefhebbers van radiografisch bestuurbare auto\'s. We rijden met elektrische RC-auto\'s in alle schalen. Of je nu net begint of al jaren rijdt: bij ons ben je welkom.',
        'about.p2': 'We beschikken over een eigen baan in Eygelshoven, op het terrein van Kok Lexmond. Naast de basher baan hebben we ook een enorm crawler-parcours en een jump-track.',
        'feat1.title': 'Alleen elektrisch', 'feat1.text': 'Nitro en benzine zijn niet toegestaan. Alle elektrische auto\'s zijn welkom!',
        'feat2.title': 'Crawler-baan', 'feat2.text': 'Speciaal terrein voor crawlers en uitdagende obstakels, we breiden ons parcours regelmatig uit.',
        'feat3.title': 'Jump-track', 'feat3.text': 'Volle gas over de schans! Voor wie van actie houdt.',
        'feat4.title': 'Voor iedereen', 'feat4.text': 'Vanaf 4 jaar is iedereen welkom!',
        'about.storylink': 'Lees het ontstaansverhaal →', 'about.medialink': 'RC045 in de media →', 'about.photos.title': 'Crawlerparcours',
        'pricing.label': 'Meedoen', 'pricing.title': 'Lid worden of een keer komen kijken?',
        'pricing.sub': 'Je kunt altijd eerst als gast komen rijden om te ervaren of het iets voor jou is. Daarna kun je eventueel lid worden en volop genieten van onze banen.',
        'guest.tag': 'Gastrijden', 'guest.title': 'Kom eens gastrijden!',
        'guest.text': 'Rij een hele dag mee op onze baan zonder lidmaatschap. Check onze openingstijden en kom gewoon langs, meld je wel even bij een (bestuurs)lid als je er bent!',
        'guest.adult': 'Volwassene (16+)', 'guest.youth': 'Jeugd (t/m 15 jaar)', 'guest.group': 'Groepen krijgen korting!',
        'guest.btn': 'Stuur ons een berichtje →',
        'guest.notes': 'Begeleiding door ouder/verzorger verplicht voor -16 jaar. Tijdens besloten- of ledenevenementen is gastrijden niet mogelijk.\nKom je met 4 of meer personen? Meld je dan van te voren via het contactformulier of bestuur@rc045.nl.',
        'member.tag': 'Lidmaatschap', 'member.title': 'Word lid van RC045',
        'member.text': 'Onbeperkt rijden op alle banen, toegang tot de groepsapp, kennis delen met medehobbyisten en altijd iemand om je mee te helpen.',
        'member.youth': 'Jeugdlid (t/m 15 jaar)', 'member.senior': 'Seniorlid (16+)', 'member.fee': 'Eenmalige inschrijfkosten',
        'member.btn': 'Ik wil graag lid worden! →',
        'member.notes': 'Contributie pro-rata: je betaalt alleen voor de resterende maanden van het jaar.',
        'track.label': 'Onze locatie', 'track.title': 'De baan in Eygelshoven',
        'track.p1': 'Ons terrein bevindt zich op het perceel van Kok Lexmond in Eygelshoven (Kerkrade). We beschikken over meerdere banen: een race-circuit, een crawler-parcours, en een jump-track voor de echte thrill-seekers.',
        'track.p2': 'Volg bij aankomst de pijlen met het RC045-logo en je ziet ons vanzelf. Er is voldoende gratis parkeergelegenheid.',
        'track.f1': 'Race-circuit voor buggy\'s, truggies en meer', 'track.f2': 'Off-road crawler-parcours',
        'track.f3': 'Jump-track met schans', 'track.f4': 'Kantine & werkruimte aanwezig', 'track.f5': 'Voldoende parkeerruimte',
        'nieuws.label': 'Nieuws', 'nieuws.title': 'Laatste updates',
        'nieuws.sub': 'Het laatste nieuws van RC045.',
        'nieuws.meer': 'Lees meer →',
        'agenda.label': 'Agenda', 'agenda.title': 'Activiteiten',
        'agenda.sub': 'Kijk hier wat er op de planning staat bij RC045. Check onze Facebook-pagina voor de meest actuele informatie.',
        'agenda.tag.opendag': 'Open dag', 'agenda.tag.leden': 'Ledenevenement', 'agenda.tag.wedstrijd': 'Wedstrijd',
        'agenda.past': 'Afgelopen',
        'rules.label': 'Reglement', 'rules.title': 'Veiligheid staat voorop',
        'rules.sub': 'We hebben duidelijke regels zodat iedereen veilig en met plezier kan rijden. Hieronder lees je de belangrijkste punten.',
        'rule1.title': 'Alleen elektrisch', 'rule1.text': 'Nitro en benzine zijn niet toegestaan op ons terrein. Alleen elektrisch aangedreven voertuigen zijn welkom.',
        'rule2.title': 'Veiligheid baan', 'rule2.text': 'Alleen rijders mogen zich op het rijderspodium begeven. Kijken doe je achter het hek. De baanmeester (oranje hesje) bepaalt of er gereden mag worden.',
        'rule3.title': 'Gastrijders', 'rule3.text': 'Aanmelden bij een bestuurslid verplicht. Onder 16 jaar altijd begeleid door ouder/verzorger.',
        'rule4.title': 'Laden van accu\'s', 'rule4.text': 'Accu\'s laden we alleen buiten, bij de daarvoor bestemde laadplek te herkennen aan het laadpaal-bord. Defecte accu\'s mag je niet weggooien in onze emmers, neem ze mee naar huis en voer ze zelf af.',
        'rule5.title': 'Opgeruimd staat netjes', 'rule5.text': 'Ieder lid ruimt mee op. Afval scheiden we in de daarvoor aangewezen bakken. De kantine laten we schoon achter.',
        'rule6.title': 'Geen alcohol of drugs', 'rule6.text': 'Alcoholhoudende dranken en verdovende middelen zijn te allen tijde verboden op het gehele terrein.',
        'rule7.title': 'We rijden nooit op het asfalt', 'rule7.text': 'Het is verboden om te rijden op het asfalt. Van de kantine naar het rijderspodium rijd je stapvoets.', 'rules.link': 'Volledig (statutair) baanreglement lezen →',
        'loc.label': 'Bezoek ons', 'loc.title': 'Hoe vind je ons?',
        'hours.title': '🕐 Openingstijden', 'hours.wed': 'Woensdag', 'hours.sat': 'Zaterdag', 'hours.sun': 'Zondag',
        'hours.this.wed': 'Deze woensdag', 'hours.this.sat': 'Deze zaterdag', 'hours.this.sun': 'Deze zondag',
        'hours.closed': 'gesloten', 'hours.closed.maintenance': 'gesloten i.v.m. onderhoud', 'hours.closed.weather': 'gesloten i.v.m. slecht weer', 'hours.members': 'alleen open voor leden', 'hours.animo': 'alleen bij voldoende animo', 'hours.animo.members': 'alleen bij voldoende animo, en alleen voor leden',
        'hours.note.attention': 'Let op:', 'hours.note.text': 'We zijn de eerste zaterdag of zondag van de maand gesloten wegens onderhoud.',
        'hours.weather': 'Bij slecht weer kunnen we besluiten eerder te sluiten of helemaal niet open te gaan.',
        'addr.title': 'Adres', 'addr.text': 'Onze baan ligt op het terrein van Kok Lexmond, bij aankomst volg je de pijlen RC045.',
        'addr.route': 'Routebeschrijving openen →',
        'contact.label': 'Contact', 'contact.title': 'Heb je een vraag?',
        'contact.text': 'Wil je meer weten over een lidmaatschap, gastrijden, eens komen kijken, of heb je gewoon een vraag? Stuur ons een bericht en we reageren zo snel mogelijk.',
        'contact.email.label': 'E-mail', 'instagram.soon': 'Binnenkort beschikbaar',
        'form.name': 'Naam *', 'form.email': 'E-mailadres', 'form.phone': 'Telefoonnummer', 'form.subject': 'Onderwerp',
        'form.select': 'Selecteer een onderwerp...', 'form.opt1': 'Vraag over lidmaatschap',
        'form.opt4': 'Sponsoring', 'form.opt5': 'Overige vragen',
        'form.message': 'Bericht *', 'form.message.ph': 'Schrijf hier je vraag of bericht...', 'form.send': 'Verstuur bericht →',
        'warn.email': '⚠️ Vul een geldig e-mailadres in (bijv. naam@voorbeeld.nl)',
        'warn.phone': '⚠️ Vul een geldig telefoonnummer in (minimaal 9 cijfers)',
        'warn.contact': '⚠️ We hebben een e-mailadres of telefoonnummer van je nodig om contact op te nemen.',
        'form.sending': '⏳ Verzenden...', 'form.sent': '✅ Verzonden!',
        'form.success': '✅ Bericht verzonden! We nemen zo snel mogelijk contact op.',
        'form.error': '❌ Er ging iets mis. Probeer het opnieuw of mail naar bestuur@rc045.nl',
        'footer.brand': 'Een gezellige vereniging voor liefhebbers van elektrisch aangedreven RC-auto\'s in de regio Zuid-Limburg. Voor beginners én ervaren rijders.',
        'footer.nav': 'Navigatie', 'footer.origin': 'Het ontstaan', 'footer.media': 'Media', 'footer.photobook': 'Fotoboek', 'footer.calendar': 'Activiteitenkalender', 'footer.join': 'Meedoen',
        'footer.become': 'Lid worden', 'footer.rules': 'Baanreglement', 'footer.sponsor': 'Sponsoring',
        'footer.credit': 'Website door', 'footer.sponsors.title': 'Met dank aan onze sponsoren',
        'status.open': 'Nu open', 'status.closed': 'Nu gesloten', 'status.members': 'Nu open voor leden', 'status.animo': 'Open bij voldoende animo', 'status.animo.members': 'Open voor leden bij voldoende animo',
        'meta.description': 'RC045 – Bashers of the South: een gezellige vereniging in Zuid-Limburg voor liefhebbers van elektrisch aangedreven, radiografisch bestuurbare auto\'s. Voor beginners en ervaren hobbyisten, jong en oud.'
      },
      en: {
        'nav.about': 'About us', 'nav.membership': 'Membership', 'nav.track': 'The track',
        'nav.location': 'Location', 'nav.photobook': 'Photo book', 'nav.contact': 'Contact', 'nav.join': 'Become a member', 'hero.intro': 'We are a friendly club from the south of Limburg for enthusiasts of electrically powered, radio-controlled cars. For beginners and experienced hobbyists alike. Young and old.',
        'hero.btn.member': 'Become a member!', 'hero.btn.more': 'More about us',
        'update.label': '📣 Update:', 'info.hours': 'Opening hours', 'info.location': 'Location', 'info.membership': 'Membership', 'info.weather': 'Weather in Eygelshoven',
        'info.hours.note': 'We update our opening hours every Friday.',
        'about.label': 'Who we are', 'about.title': 'The RC club of South Limburg',
        'about.p1': 'RC045 is an active club for enthusiasts of radio-controlled cars. We drive electric RC cars in all scales. Whether you\'re just starting out or have been racing for years: you\'re welcome here.',
        'about.p2': 'We have our own track in Eygelshoven, on the grounds of Kok Lexmond. Besides the basher track, we also have a huge crawler course and a jump track.',
        'feat1.title': 'Electric only', 'feat1.text': 'Nitro and petrol are not allowed. All electric cars are welcome!',
        'feat2.title': 'Crawler track', 'feat2.text': 'Dedicated terrain for crawlers and challenging obstacles, we regularly expand the course.',
        'feat3.title': 'Jump track', 'feat3.text': 'Full throttle over the ramp! For those who love action.',
        'feat4.title': 'For everyone', 'feat4.text': 'From age 4, everyone is welcome!',
        'about.storylink': 'Read our story →', 'about.medialink': 'RC045 in the media →', 'about.photos.title': 'Crawler course',
        'pricing.label': 'Join us', 'pricing.title': 'Become a member or come and have a look?',
        'pricing.sub': 'You can always come as a guest first to see if it suits you. After that, you can become a member and enjoy our tracks to the fullest.',
        'guest.tag': 'Guest riding', 'guest.title': 'Come for a guest ride!',
        'guest.text': 'Ride all day on our track without a membership. Check our opening hours and just show up, and check in with a club member when you arrive!',
        'guest.adult': 'Adult (16+)', 'guest.youth': 'Youth (up to 15)', 'guest.group': 'Groups get a discount!',
        'guest.btn': 'Send us a message →',
        'guest.notes': 'Supervision by a parent or guardian required for under 16. Not available during private or members-only events.\nComing with 4 or more people? Please let us know in advance via the contact form or bestuur@rc045.nl.',
        'member.tag': 'Membership', 'member.title': 'Become a member of RC045',
        'member.text': 'Unlimited riding on all tracks, access to the group app, sharing knowledge with fellow hobbyists, and always someone to help you out.',
        'member.youth': 'Youth member (up to 15)', 'member.senior': 'Senior member (16+)', 'member.fee': 'One-time registration fee',
        'member.btn': 'I would like to become a member! →',
        'member.notes': 'Pro-rata membership: you only pay for the remaining months of the year.',
        'track.label': 'Our location', 'track.title': 'The track in Eygelshoven',
        'track.p1': 'Our grounds are located on the Kok Lexmond site in Eygelshoven (Kerkrade). We have multiple tracks: a race circuit, a crawler course, and a jump track for the real thrill-seekers.',
        'track.p2': 'Follow the RC045 arrows on arrival and you\'ll find us easily. There is plenty of free parking.',
        'track.f1': 'Race circuit for buggies, truggies and more', 'track.f2': 'Off-road crawler course',
        'track.f3': 'Jump track with ramp', 'track.f4': 'Canteen & workshop available', 'track.f5': 'Ample parking',
        'nieuws.label': 'News', 'nieuws.title': 'Latest updates',
        'nieuws.sub': 'The latest news from RC045.',
        'nieuws.meer': 'Read more →',
        'agenda.label': 'Events', 'agenda.title': 'Activities',
        'agenda.sub': 'Check what is planned at RC045. Follow our Facebook page for the most up-to-date information.',
        'agenda.tag.opendag': 'Open day', 'agenda.tag.leden': 'Members event', 'agenda.tag.wedstrijd': 'Race',
        'agenda.past': 'Past event',
        'rules.label': 'Rules', 'rules.title': 'Safety comes first',
        'rules.sub': 'We have clear rules so everyone can ride safely and have fun. Below you can read the most important points.',
        'rule1.title': 'Electric only', 'rule1.text': 'Nitro and petrol are not permitted on our grounds. Only electrically powered vehicles are welcome.',
        'rule2.title': 'Track safety', 'rule2.text': 'Only riders are allowed on the driver\'s platform. Spectators watch from behind the fence. The track marshal (orange vest) decides whether riding is permitted.',
        'rule3.title': 'Guest riders', 'rule3.text': 'Check-in with a board member is mandatory. Under 16 must always be accompanied by a parent or guardian.',
        'rule4.title': 'Charging batteries', 'rule4.text': 'Batteries are only charged outside, at the designated charging area marked with the charging point sign. Do not throw defective batteries in our bins, take them home and dispose of them yourself.',
        'rule5.title': 'Tidy up after yourself', 'rule5.text': 'Every member helps clean up. We separate waste in the designated bins. We leave the canteen as we found it.',
        'rule6.title': 'No alcohol or drugs', 'rule6.text': 'Alcoholic beverages and narcotics are strictly prohibited at all times on the entire premises.',
        'rule7.title': 'We never ride on the asphalt', 'rule7.text': 'It is forbidden to ride on the asphalt. From the canteen to the driver\'s platform, you ride at walking pace.', 'rules.link': 'Read the full (statutory) track regulations →',
        'loc.label': 'Visit us', 'loc.title': 'How to find us?',
        'hours.title': '🕐 Opening hours', 'hours.wed': 'Wednesday', 'hours.sat': 'Saturday', 'hours.sun': 'Sunday',
        'hours.this.wed': 'This Wednesday', 'hours.this.sat': 'This Saturday', 'hours.this.sun': 'This Sunday',
        'hours.closed': 'closed', 'hours.closed.maintenance': 'closed for maintenance', 'hours.closed.weather': 'closed due to bad weather', 'hours.members': 'open to members only', 'hours.animo': 'only if enough people turn up', 'hours.animo.members': 'members only, and only if enough people turn up',
        'hours.note.attention': 'Please note:', 'hours.note.text': 'We are closed the first Saturday or Sunday of the month for maintenance.',
        'hours.weather': 'In bad weather we may decide to close early or not open at all.',
        'addr.title': 'Address', 'addr.text': 'Our track is on the Kok Lexmond site, follow the RC045 arrows on arrival.',
        'addr.route': 'Open directions →',
        'contact.label': 'Contact', 'contact.title': 'Got a question?',
        'contact.text': 'Want to know more about membership, guest riding, or just have a question? Send us a message and we\'ll get back to you as soon as possible.',
        'contact.email.label': 'Email', 'instagram.soon': 'Coming soon',
        'form.name': 'Name *', 'form.email': 'Email address', 'form.phone': 'Phone number', 'form.subject': 'Subject',
        'form.select': 'Select a subject...', 'form.opt1': 'Question about membership',
        'form.opt4': 'Sponsorship', 'form.opt5': 'Other questions',
        'form.message': 'Message *', 'form.message.ph': 'Write your question or message here...', 'form.send': 'Send message →',
        'warn.email': '⚠️ Please enter a valid email address (e.g. name@example.com)',
        'warn.phone': '⚠️ Please enter a valid phone number (at least 9 digits)',
        'warn.contact': '⚠️ We need an email address or phone number to get in touch.',
        'form.sending': '⏳ Sending...', 'form.sent': '✅ Sent!',
        'form.success': '✅ Message sent! We will get back to you as soon as possible.',
        'form.error': '❌ Something went wrong. Please try again or email bestuur@rc045.nl',
        'footer.brand': 'A friendly club for enthusiasts of electrically powered RC cars in the South Limburg region. For beginners and experienced riders alike.',
        'footer.nav': 'Navigation', 'footer.origin': 'Our history', 'footer.media': 'Media', 'footer.photobook': 'Photo book', 'footer.calendar': 'Events calendar', 'footer.join': 'Get involved',
        'footer.become': 'Become a member', 'footer.rules': 'Track regulations', 'footer.sponsor': 'Sponsorship',
        'footer.credit': 'Website by', 'footer.sponsors.title': 'With thanks to our sponsors',
        'status.open': 'Now open', 'status.closed': 'Now closed', 'status.members': 'Now open for members', 'status.animo': 'Open if enough people turn up', 'status.animo.members': 'Open for members if enough people turn up',
        'meta.description': 'RC045 – Bashers of the South: a friendly club in South Limburg for fans of electric radio controlled cars. For beginners and experienced hobbyists, young and old.'
      },
      de: {
        'nav.about': 'Über uns', 'nav.membership': 'Mitgliedschaft', 'nav.track': 'Die Strecke',
        'nav.location': 'Standort', 'nav.photobook': 'Fotobuch', 'nav.contact': 'Kontakt', 'nav.join': 'Mitglied werden', 'hero.intro': 'Wir sind ein freundlicher Verein aus dem Süden von Limburg für Liebhaber von elektrisch angetriebenen, ferngesteuerten Autos. Für Anfänger und erfahrene Hobbyisten. Jung und Alt.',
        'hero.btn.member': 'Mitglied werden!', 'hero.btn.more': 'Mehr über uns',
        'update.label': '📣 Aktuell:', 'info.hours': 'Öffnungszeiten', 'info.location': 'Standort', 'info.membership': 'Mitgliedschaft', 'info.weather': 'Wetter in Eygelshoven',
        'info.hours.note': 'Freitags aktualisieren wir unsere Öffnungszeiten.',
        'about.label': 'Wer wir sind', 'about.title': 'Der RC-Verein in Südlimburg',
        'about.p1': 'RC045 ist ein aktiver Verein für Liebhaber von ferngesteuerten Autos. Wir fahren elektrische RC-Autos in allen Maßstäben. Ob Anfänger oder Erfahrener, bei uns bist du willkommen.',
        'about.p2': 'Wir haben eine eigene Strecke in Eygelshoven auf dem Gelände von Kok Lexmond. Neben der Basher-Strecke gibt es auch einen riesigen Crawler-Parcours und eine Sprungstrecke.',
        'feat1.title': 'Nur elektrisch', 'feat1.text': 'Nitro und Benzin sind nicht erlaubt. Alle elektrischen Autos sind willkommen!',
        'feat2.title': 'Crawler-Strecke', 'feat2.text': 'Spezielles Gelände für Crawler und anspruchsvolle Hindernisse, wir erweitern den Parcours regelmäßig.',
        'feat3.title': 'Sprungstrecke', 'feat3.text': 'Vollgas über die Rampe! Für alle, die Action lieben.',
        'feat4.title': 'Für alle', 'feat4.text': 'Ab 4 Jahren ist jeder willkommen!',
        'about.storylink': 'Unsere Geschichte →', 'about.medialink': 'RC045 in den Medien →', 'about.photos.title': 'Crawler-Parcours',
        'pricing.label': 'Mitmachen', 'pricing.title': 'Mitglied werden oder einfach vorbeischauen?',
        'pricing.sub': 'Du kannst zunächst als Gast fahren, um zu sehen, ob es dir gefällt. Danach kannst du Mitglied werden und unsere Strecken in vollen Zügen genießen.',
        'guest.tag': 'Gastfahren', 'guest.title': 'Komm mal als Gast fahren!',
        'guest.text': 'Fahre einen ganzen Tag auf unserer Strecke ohne Mitgliedschaft. Schau einfach vorbei, melde dich beim Ankommen kurz bei einem Vereinsmitglied!',
        'guest.adult': 'Erwachsener (16+)', 'guest.youth': 'Jugend (bis 15 Jahre)', 'guest.group': 'Gruppen bekommen Rabatt!',
        'guest.btn': 'Schick uns eine Nachricht →',
        'guest.notes': 'Begleitung durch Elternteil oder Erziehungsberechtigte für unter 16 Jahre erforderlich. Nicht möglich bei geschlossenen Veranstaltungen.\nKommst du mit 4 oder mehr Personen? Melde dich dann immer vorher über das Kontaktformular oder bestuur@rc045.nl.',
        'member.tag': 'Mitgliedschaft', 'member.title': 'Werde Mitglied bei RC045',
        'member.text': 'Unbegrenztes Fahren auf allen Strecken, Zugang zur Gruppen-App, Wissensaustausch mit Gleichgesinnten und immer jemand zum Helfen.',
        'member.youth': 'Jugendmitglied (bis 15 Jahre)', 'member.senior': 'Seniorenmitglied (16+)', 'member.fee': 'Einmalige Anmeldegebühr',
        'member.btn': 'Ich möchte gerne Mitglied werden! →',
        'member.notes': 'Anteilige Mitgliedschaft: Du zahlst nur für die verbleibenden Monate des Jahres.',
        'track.label': 'Unser Standort', 'track.title': 'Die Strecke in Eygelshoven',
        'track.p1': 'Unser Gelände befindet sich auf dem Kok Lexmond Grundstück in Eygelshoven (Kerkrade). Wir haben mehrere Strecken: einen Rennkurs, einen Crawler-Parcours und eine Sprungstrecke für echte Adrenalin-Junkies.',
        'track.p2': 'Folge beim Ankommen den RC045-Schildern und du findest uns sofort. Es gibt ausreichend kostenlose Parkplätze.',
        'track.f1': 'Rennstrecke für Buggys, Truggies und mehr', 'track.f2': 'Offroad-Crawler-Parcours',
        'track.f3': 'Sprungstrecke mit Rampe', 'track.f4': 'Kantine & Werkraum vorhanden', 'track.f5': 'Ausreichend Parkplätze',
        'nieuws.label': 'Neuigkeiten', 'nieuws.title': 'Letzte Updates',
        'nieuws.sub': 'Die neuesten Nachrichten von RC045.',
        'nieuws.meer': 'Mehr lesen →',
        'agenda.label': 'Veranstaltungen', 'agenda.title': 'Aktivitäten',
        'agenda.sub': 'Schau hier, was bei RC045 geplant ist. Folge unserer Facebook-Seite für die aktuellsten Informationen.',
        'agenda.tag.opendag': 'Offener Tag', 'agenda.tag.leden': 'Mitgliederevent', 'agenda.tag.wedstrijd': 'Rennen',
        'agenda.past': 'Vorbei',
        'rules.label': 'Reglement', 'rules.title': 'Sicherheit geht vor',
        'rules.sub': 'Wir haben klare Regeln, damit alle sicher und mit Freude fahren können. Im Folgenden liest du die wichtigsten Punkte.',
        'rule1.title': 'Nur elektrisch', 'rule1.text': 'Nitro und Benzin sind auf unserem Gelände nicht erlaubt. Nur elektrisch angetriebene Fahrzeuge sind willkommen.',
        'rule2.title': 'Streckensicherheit', 'rule2.text': 'Nur Fahrer dürfen das Fahrerpodium betreten. Zuschauer bleiben hinter dem Zaun. Der Streckenmarschall (orangene Weste) entscheidet, ob gefahren werden darf.',
        'rule3.title': 'Gastfahrer', 'rule3.text': 'Anmeldung bei einem Vorstandsmitglied erforderlich. Unter 16 Jahren immer mit Elternteil oder Erziehungsberechtigtem.',
        'rule4.title': 'Akkus laden', 'rule4.text': 'Akkus werden nur draußen geladen, an dem dafür vorgesehenen Ladeplatz, erkennbar am Ladesäulen-Schild. Defekte Akkus nicht in unsere Eimer werfen, nimm sie mit nach Hause und entsorge sie selbst.',
        'rule5.title': 'Aufgeräumt ist besser', 'rule5.text': 'Jedes Mitglied räumt mit auf. Müll trennen wir in die vorgesehenen Behälter. Wir hinterlassen die Kantine sauber.',
        'rule6.title': 'Kein Alkohol oder Drogen', 'rule6.text': 'Alkoholische Getränke und Betäubungsmittel sind zu jeder Zeit auf dem gesamten Gelände verboten.',
        'rule7.title': 'Wir fahren nie auf dem Asphalt', 'rule7.text': 'Es ist verboten, auf dem Asphalt zu fahren. Von der Kantine zum Fahrerpodium fährst du Schrittgeschwindigkeit.', 'rules.link': 'Vollständiges (satzungsgemäßes) Streckenreglement lesen →',
        'loc.label': 'Besuche uns', 'loc.title': 'Wie findest du uns?',
        'hours.title': '🕐 Öffnungszeiten', 'hours.wed': 'Mittwoch', 'hours.sat': 'Samstag', 'hours.sun': 'Sonntag',
        'hours.this.wed': 'Diesen Mittwoch', 'hours.this.sat': 'Diesen Samstag', 'hours.this.sun': 'Diesen Sonntag',
        'hours.closed': 'geschlossen', 'hours.closed.maintenance': 'wegen Wartung geschlossen', 'hours.closed.weather': 'wegen schlechtem Wetter geschlossen', 'hours.members': 'nur für Mitglieder geöffnet', 'hours.animo': 'nur bei genügend Andrang', 'hours.animo.members': 'nur für Mitglieder und nur bei genügend Andrang',
        'hours.note.attention': 'Hinweis:', 'hours.note.text': 'Wir sind am ersten Samstag oder Sonntag des Monats wegen Wartungsarbeiten geschlossen.',
        'hours.weather': 'Bei schlechtem Wetter können wir früher schließen oder gar nicht öffnen.',
        'addr.title': 'Adresse', 'addr.text': 'Unsere Strecke liegt auf dem Gelände von Kok Lexmond, folge beim Ankommen den RC045-Schildern.',
        'addr.route': 'Route öffnen →',
        'contact.label': 'Kontakt', 'contact.title': 'Hast du eine Frage?',
        'contact.text': 'Möchtest du mehr über die Mitgliedschaft, Gastfahren oder hast du einfach eine Frage? Schick uns eine Nachricht und wir antworten so schnell wie möglich.',
        'contact.email.label': 'E-Mail', 'instagram.soon': 'Bald verfügbar',
        'form.name': 'Name *', 'form.email': 'E-Mail-Adresse', 'form.phone': 'Telefonnummer', 'form.subject': 'Betreff',
        'form.select': 'Betreff auswählen...', 'form.opt1': 'Frage zur Mitgliedschaft',
        'form.opt4': 'Sponsoring', 'form.opt5': 'Sonstige Fragen',
        'form.message': 'Nachricht *', 'form.message.ph': 'Schreib hier deine Frage oder Nachricht...', 'form.send': 'Nachricht senden →',
        'warn.email': '⚠️ Bitte gib eine gültige E-Mail-Adresse ein (z. B. name@beispiel.de)',
        'warn.phone': '⚠️ Bitte gib eine gültige Telefonnummer ein (mindestens 9 Ziffern)',
        'warn.contact': '⚠️ Wir benötigen eine E-Mail-Adresse oder Telefonnummer, um dich zu erreichen.',
        'form.sending': '⏳ Wird gesendet...', 'form.sent': '✅ Gesendet!',
        'form.success': '✅ Nachricht gesendet! Wir melden uns so schnell wie möglich.',
        'form.error': '❌ Etwas ist schiefgelaufen. Versuch es erneut oder schreib an bestuur@rc045.nl',
        'footer.brand': 'Ein freundlicher Verein für Liebhaber von elektrisch angetriebenen RC-Autos in der Region Südlimburg. Für Anfänger und erfahrene Fahrer.',
        'footer.nav': 'Navigation', 'footer.origin': 'Unsere Geschichte', 'footer.media': 'Medien', 'footer.photobook': 'Fotobuch', 'footer.calendar': 'Veranstaltungskalender', 'footer.join': 'Mitmachen',
        'footer.become': 'Mitglied werden', 'footer.rules': 'Streckenreglement', 'footer.sponsor': 'Sponsoring',
        'footer.credit': 'Website von', 'footer.sponsors.title': 'Mit Dank an unsere Sponsoren',
        'status.open': 'Jetzt geöffnet', 'status.closed': 'Jetzt geschlossen', 'status.members': 'Jetzt für Mitglieder geöffnet', 'status.animo': 'Geöffnet bei genügend Andrang', 'status.animo.members': 'Für Mitglieder geöffnet bei genügend Andrang',
        'meta.description': 'RC045 – Bashers of the South: ein geselliger Verein in Süd-Limburg für Freunde elektrisch angetriebener, ferngesteuerter Autos. Für Anfänger und erfahrene Hobbyisten, jung und alt.'
      }
    };

  

    let currentLang = getInitialLang(i18n);

    function setLang(lang) {
      currentLang = lang;
      const t = i18n[lang];
      document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (t[key]) el.textContent = t[key];
      });
      document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
        const key = el.getAttribute('data-i18n-placeholder');
        if (t[key]) el.placeholder = t[key];
      });
      document.querySelectorAll('.lang-flag').forEach(btn => { btn.classList.remove('active'); btn.setAttribute('aria-pressed', 'false'); });
      const activeBtn = document.querySelector(`.lang-flag[onclick="setLang('${lang}')"]`);
      activeBtn.classList.add('active');
      activeBtn.setAttribute('aria-pressed', 'true');
      document.documentElement.lang = lang;
      // Beschrijving en canonical volgen de gekozen taal, zodat ze overeenkomen
      // met de hreflang-tags in de head.
      var metaDesc = document.getElementById('meta-description');
      if (metaDesc && t['meta.description']) metaDesc.setAttribute('content', t['meta.description']);
      var canonical = document.getElementById('canonical-link');
      if (canonical) canonical.setAttribute('href', lang === 'nl' ? 'https://rc045.nl/' : 'https://rc045.nl/?lang=' + lang);
      localStorage.setItem('rc045_lang', lang);
      const currentUrl = new URL(window.location.href);
      if (lang === 'nl') currentUrl.searchParams.delete('lang');
      else currentUrl.searchParams.set('lang', lang);
      history.replaceState(null, '', currentUrl.pathname + currentUrl.search + currentUrl.hash);
      updateInternalLinks(lang);
      updateWeatherDisplay();
      updateStatus();
      renderNieuws();
      renderAgenda();
      renderContact();
      renderSponsorCta();
      renderNavFooterTeksten();
      renderHomepageTeksten();
    }

    // Alleen deze hook is bewust globaal: de bestaande taalbuttons gebruiken onclick.
    window.setLang = setLang;

    // ===== CONTACTFORMULIER =====
    const form = document.getElementById('contact-form');
    if (form) {
      form.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Honeypot: als dit verborgen veld is ingevuld, is het een bot. Stilletjes niets doen.
        if (document.getElementById('website').value.trim() !== '') {
          return;
        }

        const btn = document.getElementById('form-btn');
        const success = document.getElementById('form-success');
        const error = document.getElementById('form-error');

        const email = document.getElementById('email').value.trim();
        const telefoon = document.getElementById('telefoon').value.trim();
        const landcode = document.getElementById('landcode').value;
        if (telefoon) {
          document.getElementById('telefoon-combined').value = landcode + ' ' + telefoon;
        }

        const warning = document.getElementById('contact-warning');
        if (!email && !telefoon) {
          warning.style.display = 'block';
          return;
        }
        warning.style.display = 'none';

        const emailWarning = document.getElementById('email-warning');
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
          emailWarning.style.display = 'block';
          return;
        }
        emailWarning.style.display = 'none';

        const phoneWarning = document.getElementById('phone-warning');
        const telefoonVal = telefoon.replace(/[\s\-()]/g, '');
        if (telefoon && (!/^\d+$/.test(telefoonVal) || telefoonVal.length < 9)) {
          phoneWarning.style.display = 'block';
          return;
        }
        phoneWarning.style.display = 'none';

        btn.textContent = i18n[currentLang]['form.sending'];
        btn.disabled = true;
        success.style.display = 'none';
        error.style.display = 'none';

        try {
          const res = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'Accept': 'application/json' }
          });
          if (res.ok) {
            success.style.display = 'block';
            form.reset();
            btn.textContent = i18n[currentLang]['form.sent'];
          } else {
            throw new Error();
          }
        } catch {
          error.style.display = 'block';
          btn.textContent = i18n[currentLang]['form.send'];
          btn.disabled = false;
        }
      });
    }

    // ===== WEER =====
    const weatherCodes = {
      0: ['☀️','Helder'], 1: ['🌤️','Vrijwel helder'], 2: ['⛅','Bewolkt'], 3: ['☁️','Zwaar bewolkt'],
      45: ['🌫️','Mist'], 48: ['🌫️','IJsmist'],
      51: ['🌦️','Lichte motregen'], 53: ['🌦️','Motregen'], 55: ['🌧️','Zware motregen'],
      61: ['🌧️','Lichte regen'], 63: ['🌧️','Regen'], 65: ['🌧️','Zware regen'],
      71: ['❄️','Lichte sneeuw'], 73: ['❄️','Sneeuw'], 75: ['❄️','Zware sneeuw'],
      80: ['🌦️','Buien'], 81: ['🌧️','Zware buien'], 82: ['⛈️','Hevige buien'],
      95: ['⛈️','Onweer'], 96: ['⛈️','Onweer met hagel'], 99: ['⛈️','Zwaar onweer']
    };
    const weatherCodesEN = {
      0: 'Clear', 1: 'Mostly clear', 2: 'Partly cloudy', 3: 'Overcast', 45: 'Fog', 48: 'Icy fog',
      51: 'Light drizzle', 53: 'Drizzle', 55: 'Heavy drizzle', 61: 'Light rain', 63: 'Rain', 65: 'Heavy rain',
      71: 'Light snow', 73: 'Snow', 75: 'Heavy snow', 80: 'Showers', 81: 'Heavy showers', 82: 'Violent showers',
      95: 'Thunderstorm', 96: 'Thunderstorm with hail', 99: 'Heavy thunderstorm'
    };
    const weatherCodesDE = {
      0: 'Klar', 1: 'Meist klar', 2: 'Teilweise bewölkt', 3: 'Bedeckt', 45: 'Nebel', 48: 'Eisnebel',
      51: 'Leichter Nieselregen', 53: 'Nieselregen', 55: 'Starker Nieselregen', 61: 'Leichter Regen', 63: 'Regen', 65: 'Starker Regen',
      71: 'Leichter Schnee', 73: 'Schnee', 75: 'Starker Schnee', 80: 'Schauer', 81: 'Starke Schauer', 82: 'Heftige Schauer',
      95: 'Gewitter', 96: 'Gewitter mit Hagel', 99: 'Schweres Gewitter'
    };
    let weatherData = null;
    async function fetchWeather() {
      try {
        const res = await fetch('https://api.open-meteo.com/v1/forecast?latitude=50.889&longitude=6.072&current=temperature_2m,weathercode,windspeed_10m,relativehumidity_2m&wind_speed_unit=kmh&timezone=Europe/Amsterdam');
        const data = await res.json();
        weatherData = data.current;
        updateWeatherDisplay();
      } catch(e) {
        document.getElementById('weather-desc').textContent = '—';
      }
    }
    function updateWeatherDisplay() {
      if (!weatherData) return;
      const code = weatherData.weathercode;
      const temp = Math.round(weatherData.temperature_2m);
      const wind = Math.round(weatherData.windspeed_10m);
      const [icon, descNL] = weatherCodes[code] || ['🌤️', 'Onbekend'];
      const descEN = weatherCodesEN[code] || 'Unknown';
      const descDE = weatherCodesDE[code] || 'Unbekannt';
      const desc = currentLang === 'en' ? descEN : currentLang === 'de' ? descDE : descNL;
      document.getElementById('weather-icon').textContent = icon;
      document.getElementById('weather-temp').textContent = temp + '°C';
      document.getElementById('weather-desc').textContent = desc;
      const windEl = document.getElementById('weather-wind');
      const humidEl = document.getElementById('weather-humid');
      if (windEl) windEl.textContent = wind + ' km/h';
      if (humidEl) humidEl.textContent = Math.round(weatherData.relativehumidity_2m) + '%';
    }
    fetchWeather();
    setInterval(fetchWeather, 600000);

    // ===== STATUS =====
    // Alle drie de dagen komen uit data/contact.json (van/tot en stand, bijgewerkt
    // via beheer.php). De waarden hieronder zijn alleen een terugval zolang dat
    // bestand nog niet geladen is of een oude opzet heeft.
    function vanTotUren(vanTot) {
      if (!vanTot || !vanTot.van || !vanTot.tot) return null;
      var van = vanTot.van.split(':');
      var tot = vanTot.tot.split(':');
      return { van: parseInt(van[0], 10) + parseInt(van[1], 10) / 60, tot: parseInt(tot[0], 10) + parseInt(tot[1], 10) / 60 };
    }
    function updateStatus() {
      const t = i18n[currentLang];
      const now = new Date();
      const day = now.getDay();
      const hour = now.getHours();
      const minute = now.getMinutes();
      const time = hour + minute / 60;
      const oh = (contactData && contactData.openingstijden) || {};
      const woensdag = vanTotUren(oh.woensdag) || { van: 19, tot: 22 };
      const zaterdag = vanTotUren(oh.zaterdag) || { van: 10, tot: 15 };
      const zondag = vanTotUren(oh.zondag) || { van: 10, tot: 15 };
      // Staat de dag in beheer.php op gesloten (om welke reden dan ook), dan is
      // die dag altijd dicht, ongeacht de tijden die eronder bewaard blijven.
      // Staat hij op 'alleen leden', dan is de baan wel open binnen de tijden,
      // maar niet voor gasten; dat krijgt een eigen melding.
      const woensdagStand = dagStatus(oh.woensdag, 'animo');
      const zaterdagStand = dagStatus(oh.zaterdag);
      const zondagStand = dagStatus(oh.zondag);
      const woensdagDicht = isDicht(woensdagStand);
      const zaterdagDicht = isDicht(zaterdagStand);
      const zondagDicht = isDicht(zondagStand);
      const isOpen = (day === 3 && !woensdagDicht && time >= woensdag.van && time < woensdag.tot) || (day === 6 && !zaterdagDicht && time >= zaterdag.van && time < zaterdag.tot) || (day === 0 && !zondagDicht && time >= zondag.van && time < zondag.tot);
      // 'leden' en 'animo' zijn geen sluiting, maar wel een voorbehoud. Dan is
      // "Nu open" te stellig, dus die dagen krijgen hun eigen tekst.
      const standVandaag = day === 3 ? woensdagStand : day === 6 ? zaterdagStand : day === 0 ? zondagStand : 'open';
      const voorbehoud = isOpen && (standVandaag === 'leden' || isAnimo(standVandaag));
      const el = document.getElementById('status-indicator');
      if (voorbehoud) {
        el.className = isAnimo(standVandaag) ? 'status-animo' : 'status-members';
        el.textContent = standVandaag === 'animo_leden' ? t['status.animo.members'] : standVandaag === 'animo' ? t['status.animo'] : t['status.members'];
      } else if (isOpen) {
        el.className = 'status-open';
        el.textContent = t['status.open'];
      } else {
        el.className = 'status-closed';
        el.textContent = t['status.closed'];
      }
    }
    updateStatus();
    // Elke minuut opnieuw: zo verdwijnt een gesloten-melding vanzelf zodra het
    // vervalmoment is bereikt, ook als de pagina open blijft staan.
    setInterval(function() { updateStatus(); renderContact(); }, 60000);

    // ===== FOOTER JAAR =====
    document.getElementById('footer-year').textContent = new Date().getFullYear();

    // De initiële taal wordt pas helemaal onderaan toegepast, nadat alle
    // renderhelpers en hun gedeelde variabelen zijn geïnitialiseerd.

    // ===== AGENDA DRUPPEL =====
    const agendaObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          agendaObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.15 });
    document.querySelectorAll('.agenda-card').forEach(el => agendaObserver.observe(el));

    // ===== SCROLL REVEAL =====
    const revealObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          revealObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });
    document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

    // ===== ACTIVE NAV =====
    const navItems = document.querySelectorAll('.nav-links li[data-section]');
    const navObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          navItems.forEach(li => li.classList.remove('active'));
          const active = document.querySelector(`.nav-links li[data-section="${entry.target.id}"]`);
          if (active) active.classList.add('active');
        }
      });
    }, { rootMargin: '-40% 0px -50% 0px' });
    document.querySelectorAll('section[id]').forEach(s => navObserver.observe(s));

    // ===== PARALLAX =====
    const heroBg = document.getElementById('hero-bg');
    window.addEventListener('scroll', function() {
      const scrollY = window.scrollY;
      if (scrollY < window.innerHeight * 1.2) heroBg.style.transform = `translateY(${scrollY * 0.5}px)`;
    }, { passive: true });

    // ===== CAROUSEL =====
    // De foto's zijn losgekoppeld van de HTML (data-src en data-bg). Ze worden pas
    // opgehaald wanneer de carousel in beeld komt, en dan alleen de zichtbare slide
    // plus de eerstvolgende. Zo laadt de pagina niet 17 foto's tegelijk.
    const carousel = document.getElementById('photo-carousel');
    if (carousel) {
      const carImages = carousel.querySelectorAll('.carousel-slide');
      const carDots = carousel.querySelectorAll('.carousel-dot');
      let carIndex = 0;
      let carInterval = null;

      function laadSlide(n) {
        const slide = carImages[(n + carImages.length) % carImages.length];
        if (!slide || slide.dataset.geladen) return;
        const bg = slide.querySelector('.carousel-slide-bg');
        const img = slide.querySelector('.carousel-img');
        if (bg && bg.dataset.bg) bg.style.backgroundImage = "url('" + bg.dataset.bg + "')";
        if (img && img.dataset.src) img.src = img.dataset.src;
        slide.dataset.geladen = '1';
      }

      function showSlide(i) {
        carIndex = (i + carImages.length) % carImages.length;
        laadSlide(carIndex);
        laadSlide(carIndex + 1); // volgende alvast klaarzetten voor de overgang
        carImages.forEach((img, n) => img.classList.toggle('active', n === carIndex));
        carDots.forEach((dot, n) => dot.classList.toggle('active', n === carIndex));
      }
      var beperkteBeweging = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');
      function startAutoplay() {
        if (carInterval) return;
        // Geen automatisch doorschuiven als de bezoeker "verminder beweging" aan heeft staan.
        // Pijlen en bolletjes blijven gewoon werken.
        if (beperkteBeweging && beperkteBeweging.matches) return;
        carInterval = setInterval(() => showSlide(carIndex + 1), 5000);
      }
      function stopAutoplay() {
        clearInterval(carInterval);
        carInterval = null;
      }
      function resetAutoplay() {
        stopAutoplay();
        startAutoplay();
      }
      carousel.querySelector('.carousel-prev').addEventListener('click', () => { showSlide(carIndex - 1); resetAutoplay(); });
      carousel.querySelector('.carousel-next').addEventListener('click', () => { showSlide(carIndex + 1); resetAutoplay(); });
      carDots.forEach(dot => dot.addEventListener('click', () => { showSlide(parseInt(dot.dataset.index)); resetAutoplay(); }));

      // Pas starten als de carousel bijna in beeld is, en pauzeren zodra hij eruit is.
      if ('IntersectionObserver' in window) {
        const carObs = new IntersectionObserver(function(entries) {
          entries.forEach(function(e) {
            if (e.isIntersecting) { showSlide(carIndex); startAutoplay(); }
            else { stopAutoplay(); }
          });
        }, { rootMargin: '200px' });
        carObs.observe(carousel);
      } else {
        showSlide(0);
        startAutoplay();
      }

      // Autoplay ook stilzetten als het tabblad naar de achtergrond gaat.
      document.addEventListener('visibilitychange', function() {
        if (document.hidden) stopAutoplay();
        else {
          const r = carousel.getBoundingClientRect();
          if (r.top < window.innerHeight && r.bottom > 0) startAutoplay();
        }
      });
    }

    // ===== LIGHTBOX =====
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = document.getElementById('lightbox-img');
    document.querySelectorAll('.lightbox-trigger').forEach(img => {
      img.addEventListener('click', function() {
        lightboxImg.src = this.src; lightboxImg.alt = this.alt;
        lightbox.classList.add('open'); document.body.style.overflow = 'hidden';
      });
    });
    document.getElementById('lightbox-close').addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', function(e) { if (e.target === lightbox) closeLightbox(); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeLightbox(); });
    function closeLightbox() { lightbox.classList.remove('open'); document.body.style.overflow = ''; }

    // ===== HOMEPAGE TEKSTEN (data/homepage.json, bijwerken via beheer.php) =====
    // Hero-intro, "Wie zijn wij", "De baan" en de beschrijvende teksten bij
    // Lidmaatschap stonden hier als vaste tekst per taal in dit bestand zelf.
    // Nu staat de Nederlandse tekst nog steeds hier als vangnet (zichtbaar
    // zolang het JSON-bestand leeg is of niet laadt), maar overschrijft
    // data/homepage.json 'm zodra dat er is. Net als bij sponsors/agenda/
    // nieuws: leeg gelaten EN/DE valt terug op de Nederlandse tekst.
    var homepageData = null;
    var homepageVelden = [
      'hero_intro', 'hero_btn_member', 'hero_btn_more',
      'update_label', 'info_hours', 'info_location', 'info_membership', 'info_weather',
      'about_label', 'about_title', 'about_p1', 'about_p2', 'about_medialink', 'about_storylink', 'about_photos_title',
      'feat1_title', 'feat1_text', 'feat2_title', 'feat2_text',
      'feat3_title', 'feat3_text', 'feat4_title', 'feat4_text',
      'track_label', 'track_title', 'track_p1', 'track_p2',
      'track_f1', 'track_f2', 'track_f3', 'track_f4', 'track_f5',
      'hours_title', 'hours_sat', 'hours_sun', 'hours_wed', 'hours_weather', 'hours_note_attention', 'hours_note_text',
      'rules_label', 'rules_title', 'rules_sub', 'rules_link',
      'rule1_title', 'rule1_text', 'rule2_title', 'rule2_text', 'rule3_title', 'rule3_text', 'rule4_title', 'rule4_text',
      'rule5_title', 'rule5_text', 'rule6_title', 'rule6_text', 'rule7_title', 'rule7_text',
      'nieuws_label', 'nieuws_title', 'nieuws_sub',
      'agenda_label', 'agenda_title', 'agenda_sub',
      'pricing_title', 'pricing_sub',
      'guest_tag', 'guest_title', 'guest_text', 'guest_adult', 'guest_youth', 'guest_group', 'guest_btn', 'guest_note',
      'member_tag', 'member_title', 'member_text', 'member_youth', 'member_senior', 'member_fee', 'member_btn', 'member_note',
      'loc_label', 'loc_title', 'addr_title', 'addr_text', 'addr_route', 'instagram_soon',
      'contact_label', 'contact_title', 'contact_text',
      'form_name', 'form_email', 'form_phone', 'form_subject', 'form_select',
      'form_opt1', 'form_opt4', 'form_opt5', 'form_message', 'form_send'
    ];
    // De notities onder beide prijskaarten staan als opsomming onder de knop.
    // Bron zijn de velden guest_note en member_note (beheer.php), met de
    // i18n-teksten als terugval. Elke regel en
    // elke afgeronde zin binnen die regel wordt een eigen punt. Op een vraagteken
    // wordt niet gesplitst, zodat vraag en antwoord ("Kom je met 4 of meer
    // personen? Meld je dan...") bij elkaar blijven staan. De losse attentieregel
    // (regel 2 en verder, bijvoorbeeld de melding over groepen) staat bovenaan.
    var GEEN_ZINSEINDE = ['i.v.m.', 'bijv.', 'o.a.', 'ca.', 'evt.', 'nr.', 'incl.', 'excl.', 'max.', 'min.', 'dhr.', 'mevr.'];
    function splitsZinnen(regel) {
      return regel
        .replace(/(\.)\s+(?=[A-Z\u00C0-\u00D6\u00D8-\u00DE])/g, function (match, teken, offset, geheel) {
          var laatsteWoord = (geheel.slice(0, offset + 1).match(/\S+$/) || [''])[0].toLowerCase();
          if (GEEN_ZINSEINDE.indexOf(laatsteWoord) !== -1) return match;
          if (/\.[a-z]{2,4}\.$/.test(laatsteWoord)) return match; // e-mailadres of domeinnaam
          return teken + '\n';
        })
        .split('\n')
        .map(function (zin) { return zin.trim(); })
        .filter(Boolean);
    }
    function notitieTekst(veld, i18nSleutel) {
      var bron = homepageData && homepageData[veld];
      if (bron) {
        var uitCms = (bron[currentLang] && String(bron[currentLang]).trim()) ? bron[currentLang] : (bron.nl || '');
        if (String(uitCms).trim()) return uitCms;
      }
      var t = i18n[currentLang] || i18n.nl;
      return (t && t[i18nSleutel]) || i18n.nl[i18nSleutel] || '';
    }
    // Verwijzingen naar het contactformulier en e-mailadressen worden klikbaar
    // gemaakt. De tekst komt uit het CMS, dus die wordt eerst geescaped: er mag
    // geen HTML uit een tekstveld in de pagina belanden.
    var CONTACT_WOORD = { nl: 'contactformulier', en: 'contact form', de: 'Kontaktformular' };
    function escapeHtml(tekst) {
      return String(tekst).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function maakLinks(tekst) {
      var uit = escapeHtml(tekst);
      uit = uit.replace(/([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})/g, '<a href="mailto:$1">$1</a>');
      var woord = CONTACT_WOORD[currentLang] || CONTACT_WOORD.nl;
      uit = uit.replace(new RegExp('(' + woord.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'i'), '<a href="#contact">$1</a>');
      return uit;
    }
    function renderNotities(elementId, veld, i18nSleutel) {
      var lijst = document.getElementById(elementId);
      if (!lijst) return;
      var regels = notitieTekst(veld, i18nSleutel).split(/\r?\n/).map(function (r) { return r.trim(); }).filter(Boolean);
      if (!regels.length) return;
      var volgorde = regels.length > 1 ? regels.slice(1).concat(regels.slice(0, 1)) : regels;
      var punten = [];
      volgorde.forEach(function (regel) { punten = punten.concat(splitsZinnen(regel)); });
      lijst.innerHTML = punten.map(function (punt) { return '<li>' + maakLinks(punt) + '</li>'; }).join('');
    }
    function renderHomepageTeksten() {
      renderNotities('hp-guest-notes', 'guest_note', 'guest.notes');
      renderNotities('hp-member-notes', 'member_note', 'member.notes');
      if (!homepageData) return;
      homepageVelden.forEach(function (veld) {
        var bron = homepageData[veld];
        if (!bron) return;
        var tekst = (bron[currentLang] && String(bron[currentLang]).trim()) ? bron[currentLang] : (bron.nl || '');
        if (!tekst) return;
        // guest_note en member_note worden als opsomming gerenderd, zie renderNotities().
        if (veld === 'guest_note' || veld === 'member_note') return;
        // De verzendknop heeft al een id (form-btn, gebruikt door de submit-code
        // verderop), dus die krijgt geen hp-form-send maar hergebruikt dat id.
        var elId = veld === 'form_send' ? 'form-btn' : 'hp-' + veld.replace(/_/g, '-');
        var el = document.getElementById(elId);
        if (el) el.textContent = tekst;
      });
    }
    fetch('data/homepage.json', { cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d) return;
        homepageData = d;
        renderHomepageTeksten();
      })
      .catch(function () {});

    // ===== PRIJZEN LIDMAATSCHAP (data/rekentabel.json, bijwerken via beheer.php) =====
    // Voorheen stonden €50/€100/€10 hier als vaste tekst, los van de Rekentabel
    // in beheer. Wie daar de contributie aanpaste, moest deze pagina apart met
    // de hand bijwerken. Nu leest de homepage hetzelfde bestand als de
    // rekentabel en de calculator op aanmelden.html, dus één bron voor alle
    // drie. De "Vanaf €.../jaar" in de infobalk komt nog uit data/contact.json
    // (veld "Lidmaatschap" bij Contact), die dus los blijft van dit bedrag.
    function euroTekst(bedrag) {
      var n = Number(bedrag);
      if (!isFinite(n)) return '';
      var s = n.toFixed(2).replace('.', ',');
      if (s.slice(-3) === ',00') s = s.slice(0, -3);
      return '€' + s;
    }
    fetch('data/rekentabel.json', { cache: 'no-store' })
      .then(function(r) { return r.ok ? r.json() : null; })
      .then(function(d) {
        if (!d) return;
        var jeugd = document.getElementById('prijs-jeugd');
        var senior = document.getElementById('prijs-senior');
        var inschrijf = document.getElementById('prijs-inschrijf');
        if (jeugd && d.jeugd_jaarbedrag !== undefined) jeugd.textContent = euroTekst(d.jeugd_jaarbedrag) + '/jaar';
        if (senior && d.senior_jaarbedrag !== undefined) senior.textContent = euroTekst(d.senior_jaarbedrag) + '/jaar';
        if (inschrijf && d.inschrijfkosten !== undefined) inschrijf.textContent = euroTekst(d.inschrijfkosten);
      })
      .catch(function() {});

    // ===== ACTUELE UPDATE STROOK (data/actueel.json, bijwerken via beheer.php) =====
    fetch('data/actueel.json', { cache: 'no-store' })
      .then(function(r) { return r.ok ? r.json() : null; })
      .then(function(d) {
        if (!d || !d.text || !d.text.trim()) return;
        var tekst = d.text.trim();
        document.getElementById('announce-text').textContent = tekst;
        document.getElementById('announce-bar').style.display = 'flex';
        document.getElementById('actueel-hours-text').textContent = tekst;
        document.getElementById('actueel-hours').style.display = 'block';
      })
      .catch(function() {});

    // ===== AGENDA (data/agenda.json, bijwerken via beheer.php) =====
    // Nederlands is verplicht per kaart; Engels/Duits vallen terug op
    // Nederlands als het bestuur die niet heeft ingevuld. renderAgenda()
    // tekent opnieuw bij elke taalwissel (aangeroepen vanuit setLang).
    var agendaMaanden = ['Jan','Feb','Mrt','Apr','Mei','Jun','Jul','Aug','Sep','Okt','Nov','Dec'];
    var agendaTagInfo = {
      opendag:   { klasse: 'open-dag', key: 'agenda.tag.opendag' },
      leden:     { klasse: 'leden',    key: 'agenda.tag.leden' },
      wedstrijd: { klasse: 'wedstrijd', key: 'agenda.tag.wedstrijd' }
    };
    var agendaData = null;

    function renderAgenda() {
      if (!agendaData) return;
      var grid = document.getElementById('agenda-grid');
      grid.innerHTML = ''; // eigen container, alleen door onszelf gevuld
      var t = i18n[currentLang];

      agendaData.forEach(function(ev, i) {
        if (!ev) return;

        // Ondersteunt zowel het oude platte formaat (title/desc als tekst,
        // van vóór de talenvelden) als het huidige formaat ({nl,en,de}), zodat
        // er nooit "undefined" verschijnt als data/agenda.json nog niet
        // opnieuw is opgeslagen.
        var titelIsTekst = typeof ev.title === 'string';
        var titelNl = titelIsTekst ? ev.title : ((ev.title && ev.title.nl) || '');
        if (!titelNl || !String(titelNl).trim()) return;

        var titelTekst = titelNl;
        var omschrijvingTekst = titelIsTekst ? (ev.desc || '') : '';
        if (!titelIsTekst) {
          titelTekst = (ev.title[currentLang] && String(ev.title[currentLang]).trim()) ? ev.title[currentLang] : titelNl;
          var descBron = (typeof ev.desc === 'object' && ev.desc) ? ev.desc : { nl: ev.desc || '' };
          omschrijvingTekst = (descBron[currentLang] && String(descBron[currentLang]).trim()) ? descBron[currentLang] : (descBron.nl || '');
        }

        var info = agendaTagInfo[ev.tag] || agendaTagInfo.leden;
        var dagTekst = '', maandTekst = '';
        if (ev.date && /^\d{4}-\d{2}-\d{2}$/.test(ev.date)) {
          var delen = ev.date.split('-');
          dagTekst = String(parseInt(delen[2], 10));
          maandTekst = agendaMaanden[parseInt(delen[1], 10) - 1] || '';
        }

        var card = document.createElement('div');
        card.className = 'agenda-card reveal reveal-delay-' + (((i % 4) + 1)) + (ev.past ? ' afgelopen' : '');

        var dateBox = document.createElement('div');
        dateBox.className = 'agenda-card-date';
        var daySpan = document.createElement('span');
        daySpan.className = 'agenda-card-day'; daySpan.textContent = dagTekst;
        var monthSpan = document.createElement('span');
        monthSpan.className = 'agenda-card-month'; monthSpan.textContent = maandTekst;
        dateBox.appendChild(daySpan); dateBox.appendChild(monthSpan);

        var tagSpan = document.createElement('span');
        tagSpan.className = 'agenda-card-tag ' + info.klasse;
        tagSpan.setAttribute('data-i18n', info.key);
        tagSpan.textContent = t[info.key] || '';

        var h3 = document.createElement('h3');
        h3.textContent = titelTekst;

        var p = document.createElement('p');
        p.textContent = omschrijvingTekst;

        var timeBox = document.createElement('div');
        timeBox.className = 'agenda-card-time';
        timeBox.textContent = ev.time ? ('🕙 ' + ev.time) : '';

        card.appendChild(dateBox);
        card.appendChild(tagSpan);
        card.appendChild(h3);
        card.appendChild(p);
        card.appendChild(timeBox);

        if (ev.past) {
          var pastBadge = document.createElement('span');
          pastBadge.className = 'agenda-card-past-badge';
          pastBadge.setAttribute('data-i18n', 'agenda.past');
          pastBadge.textContent = t['agenda.past'] || '';
          card.appendChild(pastBadge);
        }

        grid.appendChild(card);

        agendaObserver.observe(card);
        revealObserver.observe(card);
      });
    }

    fetch('data/agenda.json', { cache: 'no-store' })
      .then(function(r) { return r.ok ? r.json() : []; })
      .then(function(events) {
        agendaData = Array.isArray(events) ? events : [];
        renderAgenda();
      })
      .catch(function() { agendaData = []; });

    // ===== NIEUWS (data/nieuws.json, bijwerken via beheer.php) =====
    // Zelfde opzet als agenda hierboven: sectie blijft verborgen (zie de
    // inline style="display:none" op <section id="nieuws">) totdat er
    // minstens één item met een Nederlandse titel is.
    var nieuwsData = null;

    function nieuwsFormatDatum(datumStr) {
      var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(datumStr || '');
      if (!m) return '';
      var maanden = agendaMaanden;
      var dag = parseInt(m[3], 10);
      var maand = maanden[parseInt(m[2], 10) - 1];
      return currentLang === 'de' ? (dag + '. ' + maand + ' ' + m[1]) : (dag + ' ' + maand + ' ' + m[1]);
    }

    function renderNieuws() {
      if (!nieuwsData) return;
      var grid = document.getElementById('nieuws-grid');
      var sectie = document.getElementById('nieuws');
      if (!grid || !sectie) return;
      grid.innerHTML = '';
      var t = i18n[currentLang];
      var zichtbaar = 0;

      nieuwsData.forEach(function(item, i) {
        if (!item) return;
        var titelNl = (item.title && item.title.nl) || '';
        if (!String(titelNl).trim()) return;
        zichtbaar++;

        var titelTekst = (item.title[currentLang] && String(item.title[currentLang]).trim()) ? item.title[currentLang] : titelNl;
        var descBron = item.desc || { nl: '' };
        var descTekst = (descBron[currentLang] && String(descBron[currentLang]).trim()) ? descBron[currentLang] : (descBron.nl || '');
        var linktekstBron = item.linktekst || { nl: '' };
        var linktekstTekst = (linktekstBron[currentLang] && String(linktekstBron[currentLang]).trim()) ? linktekstBron[currentLang] : (linktekstBron.nl || '');

        var card = document.createElement('div');
        card.className = 'nieuws-card reveal reveal-delay-' + (((i % 4) + 1));

        if (item.date && /^\d{4}-\d{2}-\d{2}$/.test(item.date)) {
          var datumEl = document.createElement('div');
          datumEl.className = 'nieuws-card-date';
          datumEl.textContent = nieuwsFormatDatum(item.date);
          card.appendChild(datumEl);
        }

        var h3 = document.createElement('h3');
        h3.textContent = titelTekst;
        card.appendChild(h3);

        if (descTekst) {
          var p = document.createElement('p');
          p.textContent = descTekst;
          card.appendChild(p);
        }

        if (item.link) {
          var a = document.createElement('a');
          a.href = item.link;
          a.target = '_blank';
          a.rel = 'noopener';
          a.className = 'nieuws-card-link';
          a.textContent = linktekstTekst || t['nieuws.meer'] || 'Lees meer →';
          card.appendChild(a);
        }

        grid.appendChild(card);
        revealObserver.observe(card);
      });

      sectie.style.display = zichtbaar > 0 ? '' : 'none';
    }

    fetch('data/nieuws.json', { cache: 'no-store' })
      .then(function(r) { return r.ok ? r.json() : []; })
      .then(function(items) {
        nieuwsData = Array.isArray(items) ? items : [];
        renderNieuws();
      })
      .catch(function() { nieuwsData = []; renderNieuws(); });

    // ===== CONTACT & OPENINGSTIJDEN (data/contact.json, bijwerken via beheer.php) =====
    // Bestaat het bestand nog niet, dan blijft de hardcoded tekst in de HTML
    // gewoon staan (die is gelijk aan de standaardwaarden in beheer.php).
    // renderContact() tekent opnieuw bij elke taalwissel (aangeroepen vanuit setLang),
    // zodat de vertaalde dagnamen kloppen terwijl de tijden/adres/contactgegevens
    // zelf niet per taal worden opgeslagen.
    // Deze pagina haalt contact.json zelf op (openingstijden, adres, links) en
    // zet daarbij ook de Facebook-link in de footer. Deze vlag zorgt dat
    // site-i18n.js dat bestand niet nog een keer ophaalt.
    window.rc045EigenContact = true;
    var contactData = null;
    function vanTotTekst(vanTot) {
      if (!vanTot || !vanTot.van || !vanTot.tot) return '';
      return vanTot.van + ' – ' + vanTot.tot;
    }
    // De dagstand komt uit beheer.php: 'open', 'leden', 'gesloten', 'onderhoud'
    // of 'weer'. 'leden' betekent dat de baan die dag wel open is, maar alleen
    // voor leden; de andere afwijkende standen zijn echte sluitingen.
    var statusSleutels = { animo: 'hours.animo', animo_leden: 'hours.animo.members', leden: 'hours.members', gesloten: 'hours.closed', onderhoud: 'hours.closed.maintenance', weer: 'hours.closed.weather' };
    // Het icoon staat los van de vertalingen, zodat het maar op één plek hoeft
    // te staan en in alle drie de talen hetzelfde is.
    var statusIconen = { animo: '🤝', animo_leden: '🤝', leden: '👥', gesloten: '⛔', onderhoud: '🔧', weer: '🌧️' };
    // Alleen deze standen betekenen dat de baan dicht is. 'leden' hoort daar
    // bewust niet bij: die dag telt gewoon als open, met een melding erbij.
    function isDicht(status) {
      return status === 'gesloten' || status === 'onderhoud' || status === 'weer';
    }
    // Elke stand die van "normaal open" afwijkt. Dit is bewust een functie en
    // geen lookup in statusSleutels: updateStatus() draait al bij het laden van
    // de pagina, dus voordat de var hierboven een waarde heeft gekregen.
    function isAfwijkend(status) {
      return status === 'leden' || isAnimo(status) || isDicht(status);
    }
    // De animo-standen horen bij de vaste opzet van een dag en vervallen dus
    // niet vanzelf. Dit is dezelfde afspraak als contactVasteStanden() in
    // beheer.php.
    function isAnimo(status) {
      return status === 'animo' || status === 'animo_leden';
    }
    // 'terugval' is de stand waar de dag op terugvalt zodra een tijdelijke
    // melding vervalt: voor woensdag 'animo', voor het weekend 'open'. Dat is
    // dezelfde afspraak als in beheer.php.
    function dagStatus(dag, terugval) {
      terugval = terugval || 'open';
      var status = (dag && dag.status) || '';
      if (!isAfwijkend(status)) {
        // Bestanden van vóór het keuzemenu hadden alleen een onderhoudsvinkje.
        if (dag && dag.gesloten) status = 'onderhoud';
        else return terugval;
      }
      if (isAnimo(status)) return status;
      // beheer.php zet er een vervalmoment bij: de betreffende dag om 20:00.
      // Daarna verdwijnt de melding vanzelf, zodat een afwijkende stand niet
      // blijft staan als iemand vergeet hem terug te zetten. Staat er niets, dan blijft
      // de melding staan tot hij handmatig wordt weggehaald.
      if (dag && dag.status_tot) {
        var verval = new Date(dag.status_tot);
        if (!isNaN(verval.getTime()) && Date.now() > verval.getTime()) return terugval;
      }
      return status;
    }
    // De animo-standen horen bij de vaste opzet van een dag, dus die blijven
    // "Woensdag alleen bij voldoende animo". De overige standen zijn tijdelijk
    // (zie contactVasteStanden() in beheer.php) en gelden alleen voor die ene
    // dag: die worden "Deze zondag alleen open voor leden", zodat het niet lijkt
    // of het elke zondag zo is. De dagnaam met "deze" staat als eigen sleutel in
    // de vertalingen, omdat de hoofdletter per taal verschilt: 'deze zondag',
    // maar 'This Sunday' en 'Diesen Sonntag'.
    function dagTekstVoorStand(status, dagSleutel, t) {
      if (!isAnimo(status)) {
        var deze = t['hours.this.' + dagSleutel];
        if (deze) return deze;
      }
      return t['hours.' + dagSleutel];
    }
    function statusTekst(status, dagSleutel, t) {
      var tekst = t[statusSleutels[status]] || t['hours.closed'];
      return (statusIconen[status] ? statusIconen[status] + ' ' : '') + dagTekstVoorStand(status, dagSleutel, t) + ' ' + tekst;
    }
    // Vult een regel in de info-balk: "Zaterdag 10:00 – 15:00". Is de dag dicht,
    // dan wordt de tijd doorgestreept en komt de melding eronder te staan. Bij
    // 'alleen leden' blijft de tijd normaal staan en komt alleen de melding erbij.
    function zetInfoTijd(id, dagSleutel, tijdTekst, status, t) {
      var el = document.getElementById(id);
      if (!el || !tijdTekst) return;
      var dicht = isDicht(status);
      el.textContent = '';
      el.appendChild(document.createTextNode(t['hours.' + dagSleutel] + ' '));
      var tijd = document.createElement('span');
      tijd.textContent = tijdTekst;
      if (dicht) tijd.className = 'tijd-gesloten';
      el.appendChild(tijd);
      if (status !== 'open') {
        var melding = document.createElement('span');
        melding.className = 'info-closed-note' + (status === 'leden' ? ' is-leden' : isAnimo(status) ? ' is-animo' : '');
        melding.textContent = statusTekst(status, dagSleutel, t);
        el.appendChild(melding);
      }
    }
    // Zet de markering en de melding op een regel in de openingstijden-kaart.
    function zetHoursRij(rijId, meldingId, dagSleutel, status, t) {
      var rij = document.getElementById(rijId);
      if (rij) {
        rij.classList.toggle('is-gesloten', isDicht(status));
        rij.classList.toggle('is-leden', status === 'leden');
        rij.classList.toggle('is-animo', isAnimo(status));
      }
      var melding = document.getElementById(meldingId);
      if (melding && status !== 'open') melding.textContent = statusTekst(status, dagSleutel, t);
    }
    // Houdt de openingstijden in de structured data gelijk aan contact.json.
    // Alleen dagen met een echte begin- en eindtijd komen erin; woensdag is
    // vrije tekst en hoort daar dus niet thuis.
    function updateStructuredData() {
      var el = document.getElementById('structured-data');
      if (!el || !contactData || !contactData.openingstijden) return;
      var dagen = { zaterdag: 'Saturday', zondag: 'Sunday' };
      var spec = [];
      Object.keys(dagen).forEach(function(dag) {
        var d = contactData.openingstijden[dag];
        if (!d || !d.van || !d.tot) return;
        spec.push({ '@type': 'OpeningHoursSpecification', dayOfWeek: dagen[dag], opens: d.van, closes: d.tot });
      });
      if (!spec.length) return;
      try {
        var data = JSON.parse(el.textContent);
        data.openingHoursSpecification = spec;
        el.textContent = JSON.stringify(data, null, 2);
      } catch (e) {}
    }

    function renderContact() {
      if (!contactData) return;
      updateStructuredData();
      var oh = contactData.openingstijden || {};
      var t = i18n[currentLang];
      var woensdagTekst = vanTotTekst(oh.woensdag);
      var zaterdagTekst = vanTotTekst(oh.zaterdag);
      var zondagTekst = vanTotTekst(oh.zondag);
      var woensdagStatus = dagStatus(oh.woensdag, 'animo');
      var zaterdagStatus = dagStatus(oh.zaterdag);
      var zondagStatus = dagStatus(oh.zondag);

      zetInfoTijd('info-sat-value', 'sat', zaterdagTekst, zaterdagStatus, t);
      zetInfoTijd('info-sun-value', 'sun', zondagTekst, zondagStatus, t);

      var elWed = document.getElementById('hours-wed-time');
      // Woensdag was eerder vrije tekst. Staat er in een oud contact.json nog een
      // losse zin, dan blijft die gewoon leesbaar in plaats van te verdwijnen.
      if (elWed) {
        if (woensdagTekst) elWed.textContent = woensdagTekst;
        else if (typeof oh.woensdag === 'string' && oh.woensdag) elWed.textContent = oh.woensdag;
      }
      var elHoursSat = document.getElementById('hours-sat-time');
      if (elHoursSat && zaterdagTekst) elHoursSat.textContent = zaterdagTekst;
      var elHoursSun = document.getElementById('hours-sun-time');
      if (elHoursSun && zondagTekst) elHoursSun.textContent = zondagTekst;

      zetHoursRij('hours-wed-row', 'hours-wed-closed', 'wed', woensdagTekst ? woensdagStatus : 'open', t);
      zetHoursRij('hours-sat-row', 'hours-sat-closed', 'sat', zaterdagStatus, t);
      zetHoursRij('hours-sun-row', 'hours-sun-closed', 'sun', zondagStatus, t);

      if (contactData.adres_straat) {
        ['info-adres-straat', 'addr-straat'].forEach(function(id) {
          var el = document.getElementById(id);
          if (el) el.textContent = contactData.adres_straat;
        });
      }
      if (contactData.adres_postcode_plaats) {
        ['info-adres-plaats', 'addr-postcode-plaats'].forEach(function(id) {
          var el = document.getElementById(id);
          if (el) el.textContent = contactData.adres_postcode_plaats;
        });
      }

      var elMembership = document.getElementById('info-membership-value');
      if (elMembership && contactData.lidmaatschap_vanaf) elMembership.textContent = contactData.lidmaatschap_vanaf;

      if (contactData.facebook) {
        ['contact-facebook-link', 'footer-facebook-link'].forEach(function(id) {
          var el = document.getElementById(id);
          if (el) el.href = contactData.facebook;
        });
        var fbValueEl = document.getElementById('contact-facebook-value');
        if (fbValueEl) fbValueEl.textContent = contactData.facebook.replace(/^https?:\/\//i, '').replace(/\/$/, '');
      }
      if (contactData.email) {
        var emailLink = document.getElementById('contact-email-link');
        if (emailLink) emailLink.href = 'mailto:' + contactData.email;
        var emailValue = document.getElementById('contact-email-value');
        if (emailValue) emailValue.textContent = contactData.email;
      }
    }

    fetch('data/contact.json', { cache: 'no-store' })
      .then(function(r) { return r.ok ? r.json() : null; })
      .then(function(d) {
        if (!d) return;
        contactData = d;
        renderContact();
        updateStatus();
      })
      .catch(function() {});

    // Pas de opgeslagen/URL-taal pas toe nadat alle helpers en gedeelde
    // variabelen hierboven hun initiële waarde hebben gekregen. Bij een directe
    // refresh op ?lang=en of ?lang=de riep setLang() eerder renderHomepageTeksten()
    // aan voordat o.a. CONTACT_WOORD was geïnitialiseerd; die JavaScript-fout
    // voorkwam vervolgens dat de reveal/observer-code verderop werd uitgevoerd.
    if (currentLang !== 'nl') setLang(currentLang);
    else updateInternalLinks('nl');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHomepage, { once: true });
  } else {
    initHomepage();
  }
})();
