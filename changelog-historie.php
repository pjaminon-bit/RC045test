<?php
// Recente ontwikkelaarswijzigingen. De volledige bestaande historie staat
// ongewijzigd in app/core/changelog-historie-legacy.php.
$recent = [
  [
    'datum' => '2026-08-19',
    'cat' => 'opgelost',
    'titel' => 'Beheer- en ledenmodules herkennen de nieuwe directoryroutes weer',
    'tekst' => 'Na de overgang naar /beheer/ en /leden/ controleerden enkele beveiligings- en modulelagen nog uitsluitend op beheer.php en leden.php. De contextdetectie ondersteunt nu zowel de oude compatibiliteitsingangen als de nieuwe directory-entrypoints. Daarmee werken modulebewaking, verborgen tabs en de DEV build-indicator weer via de canonieke routes.',
  ],
  [
    'datum' => '2026-08-19',
    'cat' => 'verbetering',
    'titel' => 'Gemigreerde beheeronderdelen zijn herkenbaar met een sterretje',
    'tekst' => 'Menu-items die al naar de nieuwe modulaire beheerarchitectuur zijn overgezet krijgen voortaan een sterretje. Ook zijn hun links aangepast aan de echte /beheer/-directory, zodat geen dubbele /beheer/beheer/-paden meer ontstaan.',
  ],
  [
    'datum' => '2026-08-19',
    'cat' => 'onderhoud',
    'titel' => 'Interne applicatiecode verder uit de webroot gehaald',
    'tekst' => 'De content- en templatekern is onder app/content geplaatst en technische helpers staan onder app/. De nieuwe app/core-laag verzamelt gedeelde site-, module- en paneellogica. De hele app-map is server-only, waardoor minder losse blokkades in .htaccess nodig zijn.',
  ],
  [
    'datum' => '2026-08-19',
    'cat' => 'verbetering',
    'titel' => 'Beheer en Leden gebruiken echte directory-entrypoints',
    'tekst' => '/beheer/ en /leden/ draaien nu via echte index.php-entrypoints in plaats van virtuele Apache-rewrites. De oude beheer.php- en leden.php-adressen blijven als compatibiliteitsredirect bestaan. Hierdoor werken assets en module-URL’s voorspelbaarder en is minder .htaccess-logica nodig.',
  ],
  [
    'datum' => '2026-08-19',
    'cat' => 'beveiliging',
    'titel' => 'Gebruikerssessies worden direct ingetrokken na gevoelige accountwijzigingen',
    'tekst' => 'Beheeraccounts hebben nu een sessieversie. Wachtwoord- en rechtenwijzigingen, blokkeren en verwijderen maken bestaande sessies direct ongeldig. Oude sessies kunnen een nieuwere sessieversie niet overnemen en interne sessiehelpers zijn rechtstreeks via HTTP geblokkeerd.',
  ],
  [
    'datum' => '2026-08-19',
    'cat' => 'verbetering',
    'titel' => 'Gebruikersbeheer uitgebreid met blokkeren, sessiebeheer en beveiligingsinformatie',
    'tekst' => 'Accounts kunnen tijdelijk worden geblokkeerd zonder ze te verwijderen, alle sessies kunnen gericht worden uitgelogd en recente loginproblemen zijn zichtbaar. Wachtwoordvelden hebben Nederlandse validatie, minimaal tien tekens, een oogknop en compactere accountopmaak.',
  ],
  [
    'datum' => '2026-08-19',
    'cat' => 'onderhoud',
    'titel' => 'Oude gebruikers-, logboek- en back-upcode uit beheer verwijderd',
    'tekst' => 'De zelfstandige modules Gebruikers, Logboek en Back-ups zijn de enige actieve codepaden geworden. Oude tabpanelen, POST-handlers en ongebruikte JavaScript uit het historische beheerbestand zijn verwijderd.',
  ],
  [
    'datum' => '2026-08-18',
    'cat' => 'verbetering',
    'titel' => 'Fotoboekbeheer en albumroutes verder gemoderniseerd',
    'tekst' => 'Albums worden via nette /fotoboek/<album>-routes geopend in plaats van hash-navigatie. Het fotoboekbeheer is uitgebreid en opgeschoond, inclusief albumdiagnose, filters en robuustere album- en fotoafhandeling.',
  ],
];

$legacy = require __DIR__ . '/app/core/changelog-historie-legacy.php';
return array_merge($recent, is_array($legacy) ? $legacy : []);
