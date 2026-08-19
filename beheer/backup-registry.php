<?php
// Centrale lijst van automatisch geback-upte beheerbestanden.
// `json` is publieke/configdata, `phpjson` is private data met PHP-voorloop.
$root = dirname(__DIR__);
$data = $root . '/data';
return [
    'homepage' => ['label'=>'Homepage teksten','pad'=>$data.'/homepage.json','schrijffunctie'=>'json'],
    'ontstaan' => ['label'=>'Ontstaan (geschiedenis)','pad'=>$data.'/ontstaan.json','schrijffunctie'=>'json'],
    'baanreglement' => ['label'=>'Baanreglement','pad'=>$data.'/baanreglement.json','schrijffunctie'=>'json'],
    'bedankt' => ['label'=>'Bedankt-pagina (betaalgegevens)','pad'=>$data.'/bedankt.json','schrijffunctie'=>'json'],
    'aanmelden' => ['label'=>'Aanmelden (pagina)','pad'=>$data.'/aanmelden.json','schrijffunctie'=>'json'],
    'media_pagina' => ['label'=>'Media (paginatekst)','pad'=>$data.'/media-pagina.json','schrijffunctie'=>'json'],
    'fotoboek_pagina' => ['label'=>'Fotoboek (paginatekst)','pad'=>$data.'/fotoboek-pagina.json','schrijffunctie'=>'json'],
    'mededeling' => ['label'=>'Openingstijden','pad'=>$data.'/actueel.json','schrijffunctie'=>'json'],
    'agenda' => ['label'=>'Agenda','pad'=>$data.'/agenda.json','schrijffunctie'=>'json'],
    'faq' => ['label'=>'Vragen (FAQ)','pad'=>$data.'/faq.json','schrijffunctie'=>'json'],
    'sponsors' => ['label'=>'Sponsors','pad'=>$data.'/sponsors.json','schrijffunctie'=>'json'],
    'contact' => ['label'=>'Contact','pad'=>$data.'/contact.json','schrijffunctie'=>'json'],
    'media' => ['label'=>'Media','pad'=>$data.'/media.json','schrijffunctie'=>'json'],
    'fotoboek' => ['label'=>'Fotoboek','pad'=>$data.'/fotoboek.json','schrijffunctie'=>'json'],
    'nieuws' => ['label'=>'Nieuws','pad'=>$data.'/nieuws.json','schrijffunctie'=>'json'],
    'rekentabel' => ['label'=>'Rekentabel contributie (legacy/compatibiliteit)','pad'=>$data.'/rekentabel.json','schrijffunctie'=>'json'],
    'lidmaatschapstypen' => ['label'=>'Lidmaatschapstypen','pad'=>$data.'/lidmaatschapstypen.json','schrijffunctie'=>'json'],
    'changelog' => ['label'=>'Changelog (eigen regels)','pad'=>$data.'/changelog.json','schrijffunctie'=>'json'],

    // Private verenigingsadministratie.
    'leden' => ['label'=>'Ledenadministratie','pad'=>$root.'/leden-data.php','schrijffunctie'=>'phpjson'],
    'aanmeldingen_inbox' => ['label'=>'Aanmeldingen-inbox','pad'=>$root.'/aanmeldingen-data.php','schrijffunctie'=>'phpjson'],
    'vergaderingen' => ['label'=>'Vergaderingen','pad'=>$root.'/vergaderingen-data.php','schrijffunctie'=>'phpjson'],
    'taken' => ['label'=>'Taken','pad'=>$root.'/taken-data.php','schrijffunctie'=>'phpjson'],
    'operationele_taken' => ['label'=>'Operationele taken','pad'=>$root.'/operationele-taken-data.php','schrijffunctie'=>'phpjson'],
    'evenementen' => ['label'=>'Evenementen','pad'=>$root.'/evenementen-data.php','schrijffunctie'=>'phpjson'],

    'gebruikers' => ['label'=>'Gebruikers','pad'=>$root.'/beheer-users.json','schrijffunctie'=>'gebruikers'],
];
