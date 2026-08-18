<?php
// ============================================================
// Centrale definities voor configureerbare contentpagina's
// ============================================================

$homepageGroepen = [
    'Navigatiemenu' => ['nav_about','nav_membership','nav_track','nav_location','nav_photobook','nav_contact','nav_join'],
    'Hero' => ['hero_intro','hero_btn_member','hero_btn_more'],
    'Infobalk (onder de hero)' => ['update_label','info_hours','info_location','info_membership','info_weather'],
    'Nieuws' => ['nieuws_label','nieuws_title','nieuws_sub'],
    '"Wie zijn wij"' => ['about_label','about_title','about_p1','about_p2','about_medialink','about_storylink','about_photos_title','feat1_title','feat1_text','feat2_title','feat2_text','feat3_title','feat3_text','feat4_title','feat4_text'],
    '"Lidmaatschap"' => ['pricing_title','pricing_sub','guest_tag','guest_title','guest_text','guest_adult','guest_youth','guest_group','guest_btn','guest_note','member_tag','member_title','member_text','member_youth','member_senior','member_fee','member_btn','member_note'],
    '"De baan"' => ['track_label','track_title','track_p1','track_p2','track_f1','track_f2','track_f3','track_f4','track_f5'],
    'Agenda' => ['agenda_label','agenda_title','agenda_sub'],
    '"Veiligheid staat voorop" (reglement-preview)' => ['rules_label','rules_title','rules_sub','rule1_title','rule1_text','rule2_title','rule2_text','rule3_title','rule3_text','rule4_title','rule4_text','rule5_title','rule5_text','rule6_title','rule6_text','rule7_title','rule7_text','rules_link'],
    '"Bezoek ons"' => ['loc_label','loc_title','addr_title','addr_text','addr_route','instagram_soon'],
    'Openingstijden (teksten rond de tijden)' => ['hours_title','hours_sat','hours_sun','hours_wed','hours_weather','hours_note_attention','hours_note_text'],
    'Contact' => ['contact_label','contact_title','contact_text'],
    'Contactformulier' => ['form_name','form_email','form_phone','form_subject','form_select','form_opt1','form_opt4','form_opt5','form_message','form_send'],
    'Footer' => ['footer_brand','footer_nav','footer_origin','footer_media','footer_photobook','footer_calendar','footer_join','footer_become','footer_rules','footer_sponsor','footer_sponsors_title','footer_credit'],
];
$homepageBlokVelden = array_flip([
    'hero_intro','hours_weather','hours_note_text','rules_sub','rule1_text','rule2_text','rule3_text','rule4_text','rule5_text','rule6_text','rule7_text','nieuws_sub','agenda_sub','addr_text','contact_text','about_p1','about_p2','feat1_text','feat2_text','feat3_text','feat4_text','track_p1','track_p2','pricing_sub','guest_text','guest_note','member_text','member_note','footer_brand'
]);
$homepageLabels = [
    'nav_about'=>'Navigatiemenu: "Over ons"','nav_membership'=>'Navigatiemenu: "Lidmaatschap"','nav_track'=>'Navigatiemenu: "De baan"','nav_location'=>'Navigatiemenu: "Locatie"','nav_photobook'=>'Navigatiemenu: "Fotoboek"','nav_contact'=>'Navigatiemenu: "Contact"','nav_join'=>'Navigatiemenu: "Lid worden"',
    'hero_intro'=>'Intro boven aan de pagina (onder het logo)','hero_btn_member'=>'Hero: knop "Lid worden!"','hero_btn_more'=>'Hero: knop "Meer over ons"',
    'update_label'=>'Infobalk: label voor het actueel-bericht','info_hours'=>'Infobalk: label "Openingstijden"','info_location'=>'Infobalk: label "Locatie"','info_membership'=>'Infobalk: label "Lidmaatschap"','info_weather'=>'Infobalk: label "Weer in Eygelshoven"',
    'about_label'=>'"Wie zijn wij": sectielabel','about_title'=>'"Wie zijn wij": titel','about_p1'=>'"Wie zijn wij": eerste alinea','about_p2'=>'"Wie zijn wij": tweede alinea','about_medialink'=>'"Wie zijn wij": link naar media','about_storylink'=>'"Wie zijn wij": link naar ontstaansverhaal','about_photos_title'=>'"Wie zijn wij": titel boven de fotostrip',
    'track_label'=>'"De baan": sectielabel','track_title'=>'"De baan": titel','track_p1'=>'"De baan": eerste alinea','track_p2'=>'"De baan": tweede alinea',
    'pricing_title'=>'"Lidmaatschap": titel boven de twee kaarten','pricing_sub'=>'"Lidmaatschap": introtekst boven de twee kaarten',
    'nieuws_label'=>'Nieuws: sectielabel','nieuws_title'=>'Nieuws: titel','nieuws_sub'=>'Nieuws: introtekst','agenda_label'=>'Agenda: sectielabel','agenda_title'=>'Agenda: titel','agenda_sub'=>'Agenda: introtekst',
    'rules_label'=>'Reglement-preview: sectielabel','rules_title'=>'Reglement-preview: titel','rules_sub'=>'Reglement-preview: introtekst','rules_link'=>'Reglement-preview: link naar volledig baanreglement',
    'loc_label'=>'"Bezoek ons": sectielabel','loc_title'=>'"Bezoek ons": titel','addr_title'=>'"Bezoek ons": "Adres"','addr_text'=>'"Bezoek ons": adrestekst','addr_route'=>'"Bezoek ons": link "Routebeschrijving openen"','instagram_soon'=>'"Bezoek ons": label bij Instagram',
    'hours_title'=>'Openingstijden-kaart: titel','hours_sat'=>'Openingstijden-kaart: label "Zaterdag"','hours_sun'=>'Openingstijden-kaart: label "Zondag"','hours_wed'=>'Openingstijden-kaart: label "Woensdag"','hours_weather'=>'Openingstijden-kaart: waarschuwing bij slecht weer','hours_note_attention'=>'Openingstijden-kaart: "Let op:"','hours_note_text'=>'Openingstijden-kaart: notitie over onderhoud',
    'contact_label'=>'Contact: sectielabel','contact_title'=>'Contact: titel','contact_text'=>'Contact: introtekst boven het formulier',
];
$homepageVelden = [];
foreach ($homepageGroepen as $groepVelden) {
    foreach ($groepVelden as $veld) {
        if (isset($homepageVelden[$veld])) continue;
        $label = $homepageLabels[$veld] ?? ucfirst(str_replace('_', ' ', $veld));
        if (preg_match('/^(feat|rule)(\d+)_(title|text)$/', $veld, $m)) $label = ($m[1] === 'feat' ? 'Kaartje ' : 'Regel-kaartje ') . $m[2] . ': ' . ($m[3] === 'title' ? 'titel' : 'tekst');
        if (preg_match('/^track_f(\d+)$/', $veld, $m)) $label = '"De baan": kenmerk ' . $m[1];
        $homepageVelden[$veld] = ['type' => isset($homepageBlokVelden[$veld]) ? 'blok' : 'tekst', 'label' => $label];
    }
}

return [
    'homepage' => [
        'slug' => 'index',
        'label' => 'Homepage teksten',
        'type' => 'homepage',
        'beheer_tab' => 'homepage',
        'beheer_prefix' => 'hp',
        'data_bestand' => 'data/homepage.json',
        'velden' => $homepageVelden,
        'groepen' => $homepageGroepen,
        'max_lengte' => ['tekst' => 100, 'blok' => 600],
    ],
    'ontstaan' => [
        'slug' => 'ontstaan','label' => 'Ontstaan / geschiedenis','type' => 'verhaal','seo_sleutel' => 'ontstaan','beheer_tab' => 'ontstaan','beheer_prefix' => 'ont','data_bestand' => 'data/ontstaan.json',
        'hero' => ['achtergrond'=>'images/crawlergroen.jpg','positie'=>'center','opacity'=>0.35,'label'=>['nl'=>'Onze geschiedenis','en'=>'Our history','de'=>'Unsere Geschichte'],'titel'=>['nl'=>'Het ontstaan van RC045','en'=>'The story of RC045','de'=>'Die Entstehung von RC045']],
        'velden' => [
            'hero_sub'=>['type'=>'tekst','label'=>'Ondertitel boven het verhaal'],'story_p1'=>['type'=>'blok','label'=>'Alinea 1'],'story_p2'=>['type'=>'blok','label'=>'Alinea 2'],'story_p3'=>['type'=>'blok','label'=>'Alinea 3'],'story_p4'=>['type'=>'blok','label'=>'Alinea 4'],'story_p5'=>['type'=>'blok','label'=>'Alinea 5'],'story_p6'=>['type'=>'blok','label'=>'Alinea 6'],'story_p7'=>['type'=>'blok','label'=>'Alinea 7'],
        ],
        'galerij' => ['titel'=>['nl'=>"Foto's door de jaren heen",'en'=>'Photos through the years','de'=>'Fotos im Laufe der Jahre'],'afbeeldingen'=>[
            ['src'=>'images/basherbaaneersteaanleg.jpg','alt'=>'Eerste aanleg van de RC045 baan'],['src'=>'images/basherbaaneersteaanleghek.jpg','alt'=>'Plaatsen van het hek tijdens de eerste aanleg'],['src'=>'images/basherbaaneersteaanleglucht.jpg','alt'=>'Luchtfoto van de eerste aanleg van de baan'],['src'=>'images/basherbaaneersteaanlegmaaien.jpg','alt'=>'Maaien tijdens de eerste aanleg van de baan'],['src'=>'images/basherbaaneersteaanlegtrack.jpg','alt'=>'Aanleg van het parcours'],['src'=>'images/crawlerbaaneersteaanleg.jpg','alt'=>'Eerste aanleg van de crawlerbaan'],
        ]],
        'legacy_layout'=>false,
    ],
    'baanreglement' => [
        'slug'=>'baanreglement','label'=>'Reglement','type'=>'artikelen','seo_sleutel'=>'baanreglement','beheer_tab'=>'baanreglement','beheer_prefix'=>'br','data_bestand'=>'data/baanreglement.json',
        'hero'=>['achtergrond'=>'images/hero-achtergrond.jpg','positie'=>'center','opacity'=>0.35,'label'=>['nl'=>'RC045','en'=>'RC045','de'=>'RC045'],'titel'=>['nl'=>'Baanreglement','en'=>'Track rules','de'=>'Bahnordnung']],
        'velden'=>['hero_sub'=>['type'=>'tekst','label'=>'Ondertitel boven de pagina'],'intro_bold'=>['type'=>'tekst','label'=>'Vet woord vooraan de introtekst'],'intro_text'=>['type'=>'blok','label'=>'Introtekst']],
        'artikelen'=>[
            1=>['titel'=>'a1_title','inhoud'=>'a1_body'],2=>['titel'=>'a2_title','inhoud'=>'a2_body'],3=>['titel'=>'a3_title','inhoud'=>'a3_body'],4=>['titel'=>'a4_title','inhoud'=>'a4_body'],5=>['titel'=>'a5_title','inhoud'=>'a5_body'],6=>['titel'=>'a6_title','inhoud'=>'a6_body'],7=>['titel'=>'a7_title','inhoud'=>'a7_body'],8=>['titel'=>'a8_title','inhoud'=>'a8_body'],9=>['titel'=>'a9_title','inhoud'=>'a9_body'],10=>['titel'=>'a10_title','inhoud'=>'a10_body'],
        ],
        'legacy_layout'=>false,
    ],
];
