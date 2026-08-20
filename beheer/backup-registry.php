<?php
// Centrale registry voor backup/restore.
// Standalone RC045 behoudt de historische bestandsregistry. Externe tenants
// gebruiken logische storage-items zodat JSON en PDO dezelfde restorelaag delen.
require_once dirname(__DIR__) . '/app/core/site.php';
require_once dirname(__DIR__) . '/app/storage/tenant-backup-store.php';

if (tenantBackupActief()) {
    require_once dirname(__DIR__) . '/app/content/public-content-store.php';

    $labelsPubliek = [
        'homepage'=>'Homepage teksten','ontstaan'=>'Ontstaan (geschiedenis)','baanreglement'=>'Baanreglement',
        'bedankt'=>'Bedankt-pagina (betaalgegevens)','aanmelden'=>'Aanmelden (pagina)','media-pagina'=>'Media (paginatekst)',
        'fotoboek-pagina'=>'Fotoboek (paginatekst)','actueel'=>'Openingstijden','agenda'=>'Agenda','faq'=>'Vragen (FAQ)',
        'sponsors'=>'Sponsors','contact'=>'Contact','media'=>'Media','fotoboek'=>'Fotoboek','nieuws'=>'Nieuws',
        'rekentabel'=>'Rekentabel contributie (legacy/compatibiliteit)','lidmaatschapstypen'=>'Lidmaatschapstypen',
        'changelog'=>'Changelog (eigen regels)',
    ];
    $registry = [];
    foreach (publicContentDefinities() as $sleutel => $bestand) {
        $registry['public_'.$sleutel] = [
            'label'=>$labelsPubliek[$sleutel]??('Publieke content: '.$sleutel),
            'type'=>'public','source'=>$sleutel,'backup_key'=>'public-'.$sleutel,
        ];
    }

    $private = [
        'leden'=>'Ledenadministratie','aanmeldingen'=>'Aanmeldingen-inbox','contributies'=>'Contributie-administratie',
        'groepen'=>'Commissies en werkgroepen','ledenlabels'=>'Ledenlabels en segmenten','vergaderingen'=>'Vergaderingen',
        'taken'=>'Taken','operationele_taken'=>'Operationele taken','evenementen'=>'Evenementen',
    ];
    foreach ($private as $collectie=>$label) {
        $registry['private_'.$collectie] = [
            'label'=>$label,'type'=>'private','source'=>$collectie,
            'backup_key'=>'private-'.tenantRuntimeCollectieSleutel($collectie),
        ];
    }

    $registry['gebruikers'] = ['label'=>'Gebruikers','type'=>'users','source'=>'gebruikers','backup_key'=>'auth-gebruikers'];
    $registry['assets_fotoboek'] = ['label'=>'Fotoboek bestanden','type'=>'assets','source'=>'fotoboek','backup_key'=>'assets-fotoboek'];
    $registry['assets_sponsors'] = ['label'=>'Sponsorlogo’s','type'=>'assets','source'=>'sponsors','backup_key'=>'assets-sponsors'];
    return $registry;
}

// Legacy/standalone: `json` is publieke/configdata, `phpjson` private data.
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
    'leden' => ['label'=>'Ledenadministratie','pad'=>$root.'/leden-data.php','schrijffunctie'=>'phpjson'],
    'aanmeldingen_inbox' => ['label'=>'Aanmeldingen-inbox','pad'=>$root.'/aanmeldingen-data.php','schrijffunctie'=>'phpjson'],
    'contributies' => ['label'=>'Contributie-administratie','pad'=>$root.'/contributies-data.php','schrijffunctie'=>'phpjson'],
    'groepen' => ['label'=>'Commissies en werkgroepen','pad'=>$root.'/groepen-data.php','schrijffunctie'=>'phpjson'],
    'ledenlabels' => ['label'=>'Ledenlabels en segmenten','pad'=>$root.'/ledenlabels-data.php','schrijffunctie'=>'phpjson'],
    'vergaderingen' => ['label'=>'Vergaderingen','pad'=>$root.'/vergaderingen-data.php','schrijffunctie'=>'phpjson'],
    'taken' => ['label'=>'Taken','pad'=>$root.'/taken-data.php','schrijffunctie'=>'phpjson'],
    'operationele_taken' => ['label'=>'Operationele taken','pad'=>$root.'/operationele-taken-data.php','schrijffunctie'=>'phpjson'],
    'evenementen' => ['label'=>'Evenementen','pad'=>$root.'/evenementen-data.php','schrijffunctie'=>'phpjson'],
    'gebruikers' => ['label'=>'Gebruikers','pad'=>$root.'/beheer-users.json','schrijffunctie'=>'gebruikers'],
];
