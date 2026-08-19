<?php
// ============================================================
// Centrale platformdefinities — enige bron van waarheid
// ============================================================
$appBeheerModules = dirname(__DIR__) . '/beheer/modules';

return [
    'features' => [
        'website' => [
            'label'=>'Publieke website','type'=>'kern','publieke_paginas'=>[],'publieke_links'=>[],
            'publieke_selectors'=>[],'beheer_tabs'=>[],'beheer_formulieren'=>[],'leden_tabs'=>[],'leden_formulieren'=>[],
        ],
        'ledenadministratie' => [
            'label'=>'Ledenadministratie','type'=>'intern','publieke_paginas'=>[],'publieke_links'=>[],'publieke_selectors'=>[],
            'beheer_tabs'=>[],'beheer_formulieren'=>[],'leden_tabs'=>[], 'leden_formulieren'=>[],
        ],
        'vergaderingen' => [
            'label'=>'Vergaderingen','type'=>'intern','publieke_paginas'=>[],'publieke_links'=>[],'publieke_selectors'=>[],
            'beheer_tabs'=>[],'beheer_formulieren'=>[],'leden_tabs'=>[],'leden_formulieren'=>[],
        ],
        'taken' => [
            'label'=>'Taken','type'=>'intern','publieke_paginas'=>[],'publieke_links'=>[],'publieke_selectors'=>[],
            'beheer_tabs'=>[],'beheer_formulieren'=>[],'leden_tabs'=>[],'leden_formulieren'=>[],
        ],
        'operationele_taken' => [
            'label'=>'Operationele taken','type'=>'intern','publieke_paginas'=>[],'publieke_links'=>[],'publieke_selectors'=>[],
            'beheer_tabs'=>[],'beheer_formulieren'=>[],'leden_tabs'=>[],'leden_formulieren'=>[],
        ],
        'fotoboek' => [
            'label'=>'Fotoboek','type'=>'publiek','publieke_paginas'=>['fotoboek'],'publieke_links'=>['fotoboek.html'],'publieke_selectors'=>[],
            'beheer_tabs'=>['fotoboek'],'beheer_formulieren'=>['fotoboek_tekst','fotoboek_album_aanmaken','fotoboek_album_bewerken'],'leden_tabs'=>[],'leden_formulieren'=>[],
        ],
        'media' => [
            'label'=>'Media','type'=>'publiek','publieke_paginas'=>['media'],'publieke_links'=>['media.html'],'publieke_selectors'=>[],
            'beheer_tabs'=>['media'],'beheer_formulieren'=>['media','media_tekst'],'leden_tabs'=>[],'leden_formulieren'=>[],
        ],
        'aanmelden' => [
            'label'=>'Aanmelden','type'=>'publiek','publieke_paginas'=>['aanmelden','bedankt'],'publieke_links'=>['aanmelden.html','bedankt.html'],'publieke_selectors'=>[],
            'beheer_tabs'=>['aanmelden','bedankt','faq','aanmeldingen'],'beheer_formulieren'=>['aanmelden','bedankt','faq'],
            'leden_tabs'=>[],'leden_formulieren'=>[],'verberg_nav_lid'=>true,
        ],
        'evenementen' => [
            'label'=>'Evenementen','type'=>'hybride','publieke_paginas'=>[],'publieke_links'=>[],
            'beheer_tabs'=>['agenda'],'beheer_formulieren'=>['agenda'],'leden_tabs'=>[],'leden_formulieren'=>[],
            'publieke_selectors'=>['#activiteiten','a[href="index.html#activiteiten"]','a[href="#activiteiten"]','#footer-link-calendar'],
        ],
        'sponsors' => [
            'label'=>'Sponsors','type'=>'publiek','publieke_paginas'=>[],'publieke_links'=>[],'beheer_tabs'=>['sponsors'],'beheer_formulieren'=>['sponsors'],
            'leden_tabs'=>[],'leden_formulieren'=>[],'publieke_selectors'=>['.footer-sponsors','#footer-link-sponsor','#sponsors-grid','#footer-sponsors-title','#footer-sponsors-cta'],
        ],
    ],

    'beheer' => [
        'homepage'=>['label'=>'Homepage','categorie'=>'Pagina’s','route'=>'content.php?pagina=homepage','capability'=>'content.homepage.manage','feature'=>'website'],
        'ontstaan'=>['label'=>'Ontstaan / geschiedenis','categorie'=>'Pagina’s','route'=>'content.php?pagina=ontstaan','capability'=>'content.ontstaan.manage','feature'=>'website'],
        'baanreglement'=>['label'=>'Reglement','categorie'=>'Pagina’s','route'=>'content.php?pagina=baanreglement','capability'=>'content.reglement.manage','feature'=>'website'],
        'aanmelden'=>['label'=>'Aanmelden','categorie'=>'Pagina’s','route'=>'content.php?pagina=aanmelden','capability'=>'content.aanmelden.manage','feature'=>'aanmelden'],
        'bedankt'=>['label'=>'Bedankt-pagina','categorie'=>'Pagina’s','route'=>'bedankt.php','capability'=>'content.bedankt.manage','feature'=>'aanmelden','bootstrap'=>$appBeheerModules.'/bedankt.php'],

        'actueel'=>['label'=>'Mededeling','categorie'=>'Content','route'=>'actueel.php','capability'=>'content.mededeling.manage','feature'=>'website','bootstrap'=>$appBeheerModules.'/actueel.php'],
        'nieuws'=>['label'=>'Nieuws','categorie'=>'Content','route'=>'nieuws.php','capability'=>'content.nieuws.manage','feature'=>'website','bootstrap'=>$appBeheerModules.'/nieuws.php'],
        'agenda'=>['label'=>'Agenda','categorie'=>'Content','route'=>'agenda.php','capability'=>'events.agenda.manage','feature'=>'evenementen','bootstrap'=>$appBeheerModules.'/agenda.php'],
        'contact'=>['label'=>'Contact','categorie'=>'Content','route'=>'contact.php','capability'=>'content.contact.manage','feature'=>'website','bootstrap'=>$appBeheerModules.'/contact.php'],
        'sponsors'=>['label'=>'Sponsors','categorie'=>'Content','route'=>'sponsors.php','capability'=>'content.sponsors.manage','feature'=>'sponsors','bootstrap'=>$appBeheerModules.'/sponsors.php'],
        'faq'=>['label'=>'Vragen','categorie'=>'Content','route'=>'faq.php','capability'=>'content.faq.manage','feature'=>'aanmelden','bootstrap'=>$appBeheerModules.'/faq.php'],
        'media'=>['label'=>'Media','categorie'=>'Content','route'=>'media.php','capability'=>'content.media.manage','feature'=>'media','bootstrap'=>$appBeheerModules.'/media.php'],
        'fotoboek'=>['label'=>'Fotoboek','categorie'=>'Content','route'=>'fotoboek.php','capability'=>'content.fotoboek.manage','feature'=>'fotoboek','bootstrap'=>$appBeheerModules.'/fotoboek.php'],

        'lidmaatschapstypen'=>['label'=>'Lidmaatschapstypen','categorie'=>'Contributie','route'=>'lidmaatschap.php','capability'=>'memberships.fees.manage','feature'=>'ledenadministratie'],
        'rekentabel'=>['label'=>'Rekentabel / overgang','categorie'=>'Contributie','route'=>'rekentabel.php','capability'=>'memberships.fees.manage','feature'=>'ledenadministratie','bootstrap'=>$appBeheerModules.'/rekentabel.php'],

        'aanmeldingen'=>['label'=>'Aanmeldingen','categorie'=>'Vereniging','route'=>'aanmeldingen.php','capability'=>'applications.manage','feature'=>'aanmelden'],
        'leden'=>['label'=>'Leden','categorie'=>'Vereniging','route'=>'leden.php','capability'=>'members.view','feature'=>'ledenadministratie'],
        'leden_import'=>['label'=>'Leden importeren','categorie'=>'Vereniging','route'=>'leden-import.php','capability'=>'members.manage','feature'=>'ledenadministratie'],
        'commissies'=>['label'=>'Commissies','categorie'=>'Vereniging','route'=>'commissies.php','capability'=>'committees.manage','feature'=>'ledenadministratie'],
        'vergaderingen'=>['label'=>'Vergaderingen','categorie'=>'Vereniging','route'=>'vergaderingen.php','capability'=>'meetings.manage','feature'=>'vergaderingen'],
        'taken'=>['label'=>'Taken','categorie'=>'Vereniging','route'=>'taken.php','capability'=>'tasks.manage','feature'=>'taken'],
        'operationele_taken'=>['label'=>'Operationele taken','categorie'=>'Vereniging','route'=>'operationele-taken.php','capability'=>'ops_tasks.manage','feature'=>'operationele_taken'],
        'evenementen'=>['label'=>'Evenementen','categorie'=>'Vereniging','route'=>'evenementen.php','capability'=>'events.manage','feature'=>'evenementen'],

        'changelog'=>['label'=>'Changelog','categorie'=>'Beheer','route'=>'changelog.php','capability'=>'system.changelog.manage','feature'=>'website','bootstrap'=>$appBeheerModules.'/changelog.php'],
        'gebruikers'=>['label'=>'Gebruikers','categorie'=>'Beheer','route'=>'gebruikers.php','capability'=>'system.users.manage','gevoelig'=>true,'bootstrap'=>$appBeheerModules.'/gebruikers.php'],
        'logboek'=>['label'=>'Logboek','categorie'=>'Beheer','route'=>'logboek.php','capability'=>'system.audit.read','gevoelig'=>true,'bootstrap'=>$appBeheerModules.'/logboek.php'],
        'backups'=>['label'=>'Back-ups','categorie'=>'Beheer','route'=>'backups.php','capability'=>'system.backups.manage','gevoelig'=>true,'bootstrap'=>$appBeheerModules.'/backups.php'],
    ],

    'capabilities' => [
        'content.homepage.manage'=>['label'=>'Homepage beheren','categorie'=>'Pagina’s','legacy'=>['homepage']],
        'content.ontstaan.manage'=>['label'=>'Ontstaan beheren','categorie'=>'Pagina’s','legacy'=>['ontstaan']],
        'content.reglement.manage'=>['label'=>'Reglement beheren','categorie'=>'Pagina’s','legacy'=>['baanreglement']],
        'content.aanmelden.manage'=>['label'=>'Aanmeldpagina beheren','categorie'=>'Pagina’s','legacy'=>['aanmelden']],
        'content.bedankt.manage'=>['label'=>'Bedankt-pagina beheren','categorie'=>'Pagina’s','legacy'=>['bedankt']],
        'content.mededeling.manage'=>['label'=>'Mededeling beheren','categorie'=>'Content','legacy'=>['mededeling']],
        'content.nieuws.manage'=>['label'=>'Nieuws beheren','categorie'=>'Content','legacy'=>['nieuws']],
        'events.agenda.manage'=>['label'=>'Publieke agenda beheren','categorie'=>'Content','legacy'=>['agenda']],
        'content.contact.manage'=>['label'=>'Contact beheren','categorie'=>'Content','legacy'=>['contact']],
        'content.sponsors.manage'=>['label'=>'Sponsors beheren','categorie'=>'Content','legacy'=>['sponsors']],
        'content.faq.manage'=>['label'=>'Vragen beheren','categorie'=>'Content','legacy'=>['faq']],
        'content.media.manage'=>['label'=>'Media beheren','categorie'=>'Content','legacy'=>['media']],
        'content.fotoboek.manage'=>['label'=>'Fotoboek beheren','categorie'=>'Content','legacy'=>['fotoboek']],

        'memberships.fees.manage'=>['label'=>'Lidmaatschapstypen en tarieven beheren','categorie'=>'Contributie','legacy'=>['rekentabel']],
        'members.view'=>['label'=>'Leden bekijken','categorie'=>'Vereniging','legacy'=>['leden']],
        'members.manage'=>['label'=>'Leden wijzigen, importeren en archiveren','categorie'=>'Vereniging','legacy'=>['leden']],
        'members.fees.manage'=>['label'=>'Contributiestatus per lid beheren','categorie'=>'Vereniging','legacy'=>['leden']],
        'members.erase'=>['label'=>'Leden definitief wissen / privacyactie','categorie'=>'Vereniging','legacy'=>[],'gevoelig'=>true],
        'applications.manage'=>['label'=>'Aanmeldingen beoordelen','categorie'=>'Vereniging','legacy'=>[]],
        'committees.manage'=>['label'=>'Commissies beheren','categorie'=>'Vereniging','legacy'=>['commissies']],
        'meetings.manage'=>['label'=>'Vergaderingen beheren','categorie'=>'Vereniging','legacy'=>['bestuursvergadering','ledenvergadering']],
        'tasks.manage'=>['label'=>'Taken beheren','categorie'=>'Vereniging','legacy'=>['takenlijst']],
        'ops_tasks.manage'=>['label'=>'Operationele taken beheren','categorie'=>'Vereniging','legacy'=>['operationele_taken']],
        'events.manage'=>['label'=>'Evenementen beheren','categorie'=>'Vereniging','legacy'=>['evenementen']],

        'system.changelog.manage'=>['label'=>'Changelog beheren','categorie'=>'Beheer','legacy'=>['changelog']],
        'system.users.manage'=>['label'=>'Gebruikers en rechten beheren','categorie'=>'Beheer','legacy'=>['gebruikers'],'gevoelig'=>true],
        'system.audit.read'=>['label'=>'Logboek lezen','categorie'=>'Beheer','legacy'=>['log'],'gevoelig'=>true],
        'system.backups.manage'=>['label'=>'Back-ups herstellen','categorie'=>'Beheer','legacy'=>['backups'],'gevoelig'=>true],
    ],

    'rol_capabilities'=>[
        'bestuur'=>['meetings.manage','tasks.manage'],
    ],
];
