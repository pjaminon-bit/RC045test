<?php
// ============================================================
// Publieke tenantcontent: neutrale defaults en legacy-lekbeveiliging
// ============================================================
// De standalone RC045-installatie behoudt zijn bestaande inhoud. Externe
// tenants krijgen uitsluitend tenant-eigen content of neutrale platformtekst.
// Een dataset met herkenbare RC045-fingerprints wordt fail-closed genegeerd.
// ============================================================

require_once dirname(__DIR__) . '/core/tenant-runtime.php';

function tenantContentIsExtern(): bool
{
    return tenantRuntimeExternConfigPad() !== null || tenantRuntimeConfigVerplicht();
}

function tenantContentBevatLegacy($waarde): bool
{
    if (!is_scalar($waarde) && !is_array($waarde)) return false;
    $tekst = is_array($waarde)
        ? (json_encode($waarde, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '')
        : (string) $waarde;
    $tekst = strtolower($tekst);
    foreach ([
        'rc045',
        'bashers of the south',
        'bestuur@rc045.nl',
        'eygelshoven',
        'kok lexmond',
        'wijngaardsberg',
        'facebook.com/rc045',
    ] as $vingerafdruk) {
        if (str_contains($tekst, $vingerafdruk)) return true;
    }
    return false;
}

function tenantContentTaalveld(string $nl, string $en, string $de): array
{
    return ['nl' => $nl, 'en' => $en, 'de' => $de];
}

function tenantContentNeutraleHomepage(string $naam): array
{
    return [
        'nav_about' => tenantContentTaalveld('Over ons', 'About us', 'Über uns'),
        'nav_membership' => tenantContentTaalveld('Lidmaatschap', 'Membership', 'Mitgliedschaft'),
        'nav_contact' => tenantContentTaalveld('Contact', 'Contact', 'Kontakt'),
        'nav_join' => tenantContentTaalveld('Lid worden', 'Become a member', 'Mitglied werden'),
        'nav_track' => tenantContentTaalveld('De baan', 'The venue', 'Das Gelände'),
        'nav_location' => tenantContentTaalveld('Locatie', 'Location', 'Standort'),
        'nav_photobook' => tenantContentTaalveld('Fotoboek', 'Photo book', 'Fotobuch'),
        'hero_intro' => tenantContentTaalveld(
            'Welkom op de officiële website van ' . $naam . '.',
            'Welcome to the official website of ' . $naam . '.',
            'Willkommen auf der offiziellen Website von ' . $naam . '.'
        ),
        'hero_btn_member' => tenantContentTaalveld('Bekijk de vereniging', 'Discover the association', 'Verein entdecken'),
        'hero_btn_more' => tenantContentTaalveld('Neem contact op', 'Contact us', 'Kontakt aufnehmen'),
        'about_label' => tenantContentTaalveld('Onze vereniging', 'Our association', 'Unser Verein'),
        'about_title' => tenantContentTaalveld('Samen maken we de vereniging', 'Building our association together', 'Gemeinsam gestalten wir den Verein'),
        'about_p1' => tenantContentTaalveld(
            $naam . ' brengt leden, vrijwilligers en belangstellenden samen.',
            $naam . ' brings members, volunteers and visitors together.',
            $naam . ' bringt Mitglieder, Ehrenamtliche und Interessierte zusammen.'
        ),
        'about_p2' => tenantContentTaalveld(
            'Op deze website vind je informatie over onze vereniging, activiteiten en mogelijkheden om mee te doen.',
            'This website contains information about our association, activities and ways to participate.',
            'Auf dieser Website findest du Informationen über unseren Verein, Aktivitäten und Möglichkeiten zum Mitmachen.'
        ),
        'feat1_title' => tenantContentTaalveld('Leden', 'Members', 'Mitglieder'),
        'feat1_text' => tenantContentTaalveld('Een centrale plek voor leden en betrokkenen.', 'A central place for members and everyone involved.', 'Ein zentraler Ort für Mitglieder und Beteiligte.'),
        'feat2_title' => tenantContentTaalveld('Activiteiten', 'Activities', 'Aktivitäten'),
        'feat2_text' => tenantContentTaalveld('Blijf op de hoogte van nieuws en activiteiten.', 'Stay informed about news and activities.', 'Bleib über Neuigkeiten und Aktivitäten informiert.'),
        'feat3_title' => tenantContentTaalveld('Vrijwilligers', 'Volunteers', 'Ehrenamtliche'),
        'feat3_text' => tenantContentTaalveld('Samen houden we de vereniging in beweging.', 'Together we keep the association moving.', 'Gemeinsam halten wir den Verein in Bewegung.'),
        'feat4_title' => tenantContentTaalveld('Contact', 'Contact', 'Kontakt'),
        'feat4_text' => tenantContentTaalveld('Neem gerust contact op voor meer informatie.', 'Feel free to contact us for more information.', 'Kontaktiere uns gerne für weitere Informationen.'),
        'pricing_title' => tenantContentTaalveld('Meedoen met ' . $naam, 'Join ' . $naam, 'Mitmachen bei ' . $naam),
        'pricing_sub' => tenantContentTaalveld('Bekijk hoe je lid kunt worden of op een andere manier kunt bijdragen.', 'See how to become a member or contribute in another way.', 'Erfahre, wie du Mitglied werden oder dich anderweitig einbringen kannst.'),
        'member_title' => tenantContentTaalveld('Word lid', 'Become a member', 'Mitglied werden'),
        'member_text' => tenantContentTaalveld('Samen maken we meer mogelijk.', 'Together we make more possible.', 'Gemeinsam ermöglichen wir mehr.'),
        'contact_label' => tenantContentTaalveld('Contact', 'Contact', 'Kontakt'),
        'contact_title' => tenantContentTaalveld('Neem contact op', 'Get in touch', 'Kontakt aufnehmen'),
        'contact_text' => tenantContentTaalveld('Heb je een vraag? Neem dan contact op met de vereniging.', 'Have a question? Please contact the association.', 'Hast du eine Frage? Nimm Kontakt mit dem Verein auf.'),
        'update_label' => tenantContentTaalveld('Actueel:', 'Latest:', 'Aktuell:'),
        'info_hours' => tenantContentTaalveld('Openingstijden', 'Opening hours', 'Öffnungszeiten'),
        'info_location' => tenantContentTaalveld('Locatie', 'Location', 'Standort'),
        'info_membership' => tenantContentTaalveld('Lidmaatschap', 'Membership', 'Mitgliedschaft'),
        'info_weather' => tenantContentTaalveld('Weer', 'Weather', 'Wetter'),
        'nieuws_label' => tenantContentTaalveld('Nieuws', 'News', 'Neuigkeiten'),
        'nieuws_title' => tenantContentTaalveld('Laatste updates', 'Latest updates', 'Letzte Updates'),
        'nieuws_sub' => tenantContentTaalveld('Het laatste nieuws van ' . $naam . '.', 'The latest news from ' . $naam . '.', 'Die neuesten Nachrichten von ' . $naam . '.'),
        'about_medialink' => tenantContentTaalveld($naam . ' in de media →', $naam . ' in the media →', $naam . ' in den Medien →'),
        'about_storylink' => tenantContentTaalveld('Lees ons verhaal →', 'Read our story →', 'Unsere Geschichte →'),
        'about_photos_title' => tenantContentTaalveld('Fotogalerij', 'Photo gallery', 'Fotogalerie'),
        'guest_tag' => tenantContentTaalveld('Kennismaken', 'Introduction', 'Kennenlernen'),
        'guest_title' => tenantContentTaalveld('Kom kennismaken', 'Come and meet us', 'Lerne uns kennen'),
        'guest_text' => tenantContentTaalveld('Maak vrijblijvend kennis met onze vereniging en activiteiten.', 'Discover our association and activities without obligation.', 'Lerne unseren Verein und unsere Aktivitäten unverbindlich kennen.'),
        'guest_adult' => tenantContentTaalveld('Volwassenen', 'Adults', 'Erwachsene'),
        'guest_youth' => tenantContentTaalveld('Jeugd', 'Youth', 'Jugend'),
        'guest_group' => tenantContentTaalveld('Groepen', 'Groups', 'Gruppen'),
        'guest_btn' => tenantContentTaalveld('Stuur ons een bericht →', 'Send us a message →', 'Nachricht senden →'),
        'guest_note' => tenantContentTaalveld('Neem vooraf contact op voor de actuele mogelijkheden.', 'Contact us in advance for current options.', 'Kontaktiere uns vorab zu den aktuellen Möglichkeiten.'),
        'member_tag' => tenantContentTaalveld('Lidmaatschap', 'Membership', 'Mitgliedschaft'),
        'member_youth' => tenantContentTaalveld('Jeugdlid', 'Youth member', 'Jugendmitglied'),
        'member_senior' => tenantContentTaalveld('Lid', 'Member', 'Mitglied'),
        'member_fee' => tenantContentTaalveld('Inschrijfkosten', 'Registration fee', 'Anmeldegebühr'),
        'member_btn' => tenantContentTaalveld('Ik wil lid worden →', 'I want to join →', 'Ich möchte Mitglied werden →'),
        'member_note' => tenantContentTaalveld('Vraag het bestuur naar de actuele contributie en voorwaarden.', 'Ask the board about current fees and terms.', 'Frage den Vorstand nach aktuellen Beiträgen und Bedingungen.'),
        'track_label' => tenantContentTaalveld('Onze locatie', 'Our venue', 'Unser Standort'),
        'track_title' => tenantContentTaalveld('Ruimte voor onze activiteiten', 'A place for our activities', 'Raum für unsere Aktivitäten'),
        'track_p1' => tenantContentTaalveld('Hier organiseert ' . $naam . ' verenigingsactiviteiten voor leden en bezoekers.', $naam . ' organises activities here for members and visitors.', 'Hier organisiert ' . $naam . ' Aktivitäten für Mitglieder und Gäste.'),
        'track_p2' => tenantContentTaalveld('Bekijk de actuele agenda of neem contact op voor praktische informatie.', 'See the current calendar or contact us for practical information.', 'Sieh in den Kalender oder kontaktiere uns für praktische Informationen.'),
        'track_f1' => tenantContentTaalveld('Activiteiten voor leden', 'Member activities', 'Aktivitäten für Mitglieder'),
        'track_f2' => tenantContentTaalveld('Ruimte voor ontmoeting', 'A place to meet', 'Raum für Begegnung'),
        'track_f3' => tenantContentTaalveld('Regelmatige evenementen', 'Regular events', 'Regelmäßige Veranstaltungen'),
        'track_f4' => tenantContentTaalveld('Vrijwilligers en werkgroepen', 'Volunteers and working groups', 'Ehrenamtliche und Arbeitsgruppen'),
        'track_f5' => tenantContentTaalveld('Praktische informatie via contact', 'Practical information via contact', 'Praktische Informationen über Kontakt'),
        'agenda_label' => tenantContentTaalveld('Agenda', 'Calendar', 'Kalender'),
        'agenda_title' => tenantContentTaalveld('Activiteiten', 'Activities', 'Aktivitäten'),
        'agenda_sub' => tenantContentTaalveld('Bekijk wat er op de planning staat bij ' . $naam . '.', 'See what is planned at ' . $naam . '.', 'Sieh, was bei ' . $naam . ' geplant ist.'),
        'rules_label' => tenantContentTaalveld('Reglement', 'Rules', 'Regeln'),
        'rules_title' => tenantContentTaalveld('Duidelijke afspraken', 'Clear agreements', 'Klare Vereinbarungen'),
        'rules_sub' => tenantContentTaalveld('Goede afspraken zorgen voor een veilige en prettige vereniging.', 'Clear agreements create a safe and welcoming association.', 'Klare Vereinbarungen sorgen für einen sicheren und angenehmen Verein.'),
        'rules_link' => tenantContentTaalveld('Volledig reglement lezen →', 'Read the full rules →', 'Vollständige Regeln lesen →'),
        'rule1_title' => tenantContentTaalveld('Respect voor elkaar', 'Respect each other', 'Respekt füreinander'),
        'rule1_text' => tenantContentTaalveld('We gaan zorgvuldig en respectvol met elkaar om.', 'We treat each other with care and respect.', 'Wir gehen respektvoll miteinander um.'),
        'rule2_title' => tenantContentTaalveld('Veiligheid', 'Safety', 'Sicherheit'),
        'rule2_text' => tenantContentTaalveld('Volg altijd de veiligheidsafspraken en aanwijzingen.', 'Always follow safety agreements and instructions.', 'Befolge immer die Sicherheitsregeln und Hinweise.'),
        'rule3_title' => tenantContentTaalveld('Bezoekers', 'Visitors', 'Gäste'),
        'rule3_text' => tenantContentTaalveld('Bezoekers melden zich bij een bestuurslid of contactpersoon.', 'Visitors report to a board member or contact person.', 'Gäste melden sich bei einem Vorstandsmitglied oder Ansprechpartner.'),
        'rule4_title' => tenantContentTaalveld('Materialen', 'Equipment', 'Materialien'),
        'rule4_text' => tenantContentTaalveld('Gebruik materialen en voorzieningen zorgvuldig.', 'Use equipment and facilities with care.', 'Gehe sorgfältig mit Materialien und Einrichtungen um.'),
        'rule5_title' => tenantContentTaalveld('Samen opruimen', 'Clean up together', 'Gemeinsam aufräumen'),
        'rule5_text' => tenantContentTaalveld('We laten onze locatie netjes achter.', 'We leave our venue tidy.', 'Wir hinterlassen unseren Standort ordentlich.'),
        'rule6_title' => tenantContentTaalveld('Verantwoord gedrag', 'Responsible conduct', 'Verantwortliches Verhalten'),
        'rule6_text' => tenantContentTaalveld('Iedereen draagt bij aan een prettige sfeer.', 'Everyone contributes to a positive atmosphere.', 'Alle tragen zu einer guten Atmosphäre bei.'),
        'rule7_title' => tenantContentTaalveld('Aanwijzingen opvolgen', 'Follow instructions', 'Hinweise befolgen'),
        'rule7_text' => tenantContentTaalveld('Aanwijzingen van bestuur en vrijwilligers worden opgevolgd.', 'Instructions from the board and volunteers are followed.', 'Hinweise von Vorstand und Ehrenamtlichen werden befolgt.'),
        'loc_label' => tenantContentTaalveld('Bezoek ons', 'Visit us', 'Besuche uns'),
        'loc_title' => tenantContentTaalveld('Hoe vind je ons?', 'How to find us', 'So findest du uns'),
        'hours_title' => tenantContentTaalveld('Openingstijden', 'Opening hours', 'Öffnungszeiten'),
        'hours_wed' => tenantContentTaalveld('Woensdag', 'Wednesday', 'Mittwoch'),
        'hours_sat' => tenantContentTaalveld('Zaterdag', 'Saturday', 'Samstag'),
        'hours_sun' => tenantContentTaalveld('Zondag', 'Sunday', 'Sonntag'),
        'hours_weather' => tenantContentTaalveld('Actuele tijden worden door het bestuur bijgehouden.', 'Current hours are maintained by the board.', 'Aktuelle Zeiten werden vom Vorstand gepflegt.'),
        'hours_note_attention' => tenantContentTaalveld('Let op:', 'Please note:', 'Hinweis:'),
        'hours_note_text' => tenantContentTaalveld('Controleer altijd de actuele informatie voor je bezoek.', 'Always check current information before your visit.', 'Prüfe vor deinem Besuch immer die aktuellen Informationen.'),
        'addr_title' => tenantContentTaalveld('Adres', 'Address', 'Adresse'),
        'addr_text' => tenantContentTaalveld('Adresgegevens worden door het verenigingsbeheer ingevuld.', 'Address details are maintained by the association.', 'Adressdaten werden von der Vereinsverwaltung gepflegt.'),
        'addr_route' => tenantContentTaalveld('Routebeschrijving openen →', 'Open directions →', 'Route öffnen →'),
        'instagram_soon' => tenantContentTaalveld('Binnenkort beschikbaar', 'Coming soon', 'Bald verfügbar'),
        'form_name' => tenantContentTaalveld('Naam *', 'Name *', 'Name *'),
        'form_email' => tenantContentTaalveld('E-mailadres', 'Email address', 'E-Mail-Adresse'),
        'form_phone' => tenantContentTaalveld('Telefoonnummer', 'Phone number', 'Telefonnummer'),
        'form_subject' => tenantContentTaalveld('Onderwerp', 'Subject', 'Betreff'),
        'form_select' => tenantContentTaalveld('Selecteer een onderwerp...', 'Select a subject...', 'Betreff auswählen...'),
        'form_opt1' => tenantContentTaalveld('Vraag over lidmaatschap', 'Membership question', 'Frage zur Mitgliedschaft'),
        'form_opt4' => tenantContentTaalveld('Sponsoring', 'Sponsorship', 'Sponsoring'),
        'form_opt5' => tenantContentTaalveld('Overige vragen', 'Other questions', 'Sonstige Fragen'),
        'form_message' => tenantContentTaalveld('Bericht *', 'Message *', 'Nachricht *'),
        'form_send' => tenantContentTaalveld('Verstuur bericht →', 'Send message →', 'Nachricht senden →'),
        'footer_brand' => tenantContentTaalveld('De officiële website van ' . $naam . '.', 'The official website of ' . $naam . '.', 'Die offizielle Website von ' . $naam . '.'),
        'footer_nav' => tenantContentTaalveld('Navigatie', 'Navigation', 'Navigation'),
        'footer_origin' => tenantContentTaalveld('Het ontstaan', 'Our story', 'Unsere Geschichte'),
        'footer_media' => tenantContentTaalveld('Media', 'Media', 'Medien'),
        'footer_photobook' => tenantContentTaalveld('Fotoboek', 'Photo book', 'Fotobuch'),
        'footer_calendar' => tenantContentTaalveld('Activiteitenkalender', 'Events calendar', 'Veranstaltungskalender'),
        'footer_join' => tenantContentTaalveld('Meedoen', 'Join us', 'Mitmachen'),
        'footer_become' => tenantContentTaalveld('Lid worden', 'Become a member', 'Mitglied werden'),
        'footer_rules' => tenantContentTaalveld('Reglement', 'Rules', 'Regeln'),
        'footer_sponsor' => tenantContentTaalveld('Sponsoring', 'Sponsorship', 'Sponsoring'),
        'footer_sponsors_title' => tenantContentTaalveld('Met dank aan onze sponsoren', 'Thanks to our sponsors', 'Dank an unsere Sponsoren'),
        'footer_credit' => tenantContentTaalveld('Website door', 'Website by', 'Website von'),
    ];
}

function tenantContentNeutraalContact(): array
{
    return [
        'email' => '',
        'facebook' => '',
        'instagram' => '',
        'adres_straat' => '',
        'adres_postcode_plaats' => '',
        'lidmaatschap_vanaf' => '',
        'openingstijden' => [],
    ];
}

function tenantContentVervangLegacy($waarde, string $naam)
{
    if (is_array($waarde)) {
        foreach ($waarde as $sleutel => $item) $waarde[$sleutel] = tenantContentVervangLegacy($item, $naam);
        return $waarde;
    }
    if (!is_string($waarde)) return $waarde;
    $waarde = str_ireplace('RC045', $naam, $waarde);
    return tenantContentBevatLegacy($waarde) ? '' : $waarde;
}

function tenantContentStandaardVoorRuntime(string $sleutel, array $standaard, string $naam): array
{
    if (!tenantContentIsExtern()) return $standaard;
    if ($sleutel === 'homepage') return tenantContentNeutraleHomepage($naam);
    return tenantContentVervangLegacy($standaard, $naam);
}

function tenantContentDefinitieVoorRuntime(string $sleutel, array $definitie, string $naam): array
{
    if (!tenantContentIsExtern()) return $definitie;

    if (isset($definitie['hero']) && is_array($definitie['hero'])) {
        $definitie['hero']['achtergrond'] = '';
    }
    if ($sleutel === 'ontstaan') {
        $definitie['hero']['label'] = tenantContentTaalveld('Over ons', 'About us', 'Über uns');
        $definitie['hero']['titel'] = tenantContentTaalveld('Over ' . $naam, 'About ' . $naam, 'Über ' . $naam);
        $definitie['galerij'] = ['titel' => tenantContentTaalveld("Foto's", 'Photos', 'Fotos'), 'afbeeldingen' => []];
    } elseif ($sleutel === 'baanreglement') {
        $definitie['hero']['label'] = tenantContentTaalveld($naam, $naam, $naam);
        $definitie['hero']['titel'] = tenantContentTaalveld('Reglement', 'Rules', 'Regelwerk');
    }

    return tenantContentVervangLegacy($definitie, $naam);
}
