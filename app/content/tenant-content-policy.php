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
        'footer_brand' => tenantContentTaalveld('De officiële website van ' . $naam . '.', 'The official website of ' . $naam . '.', 'Die offizielle Website von ' . $naam . '.'),
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
