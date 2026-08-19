<?php
// Echte entrypoint voor /leden/.
//
// De bestaande, omvangrijke ledenapplicatie blijft voorlopig als intern
// rootbestand leden-app.php staan. Daardoor behouden alle bestaande __DIR__-
// gebaseerde opslagpaden exact dezelfde betekenis, terwijl de publieke URL
// wel een normale directory-app is en geen Apache rewrite meer nodig heeft.
//
// De oude applicatie gebruikt nog relatieve browserpaden alsof hij vanuit de
// root wordt uitgevoerd. Alleen de uiteindelijke HTML en Location-header
// worden daarom hier naar de echte /leden/-context vertaald. De opslag- en
// autorisatielogica zelf wordt niet aangepast.

// POST-Redirect-GET in leden-app.php verwijst historisch naar leden.php#... .
// Zet zo'n Location vóór verzending om naar ./#..., ook wanneer de app exit
// aanroept. De bestaande HTTP-status (normaal 302) blijft behouden.
header_register_callback(function () {
    foreach (headers_list() as $headerRegel) {
        if (stripos($headerRegel, 'Location: leden.php') !== 0) continue;
        $doel = trim(substr($headerRegel, strlen('Location:')));
        $doel = preg_replace('#^leden\.php#i', './', $doel);
        header_remove('Location');
        header('Location: ' . $doel);
        break;
    }
});

// Pas uitsluitend browser-URL's in de gerenderde HTML aan. PHP-bestandspaden
// blijven onaangeroerd omdat leden-app.php fysiek in de installatie-root staat.
ob_start(function ($html) {
    $html = str_replace('leden.php', './', $html);
    $html = str_replace('href="beheer.php"', 'href="../beheer/"', $html);
    $html = str_replace('href="index.html"', 'href="../index.html"', $html);
    $html = str_replace('href="favicon-32x32.png"', 'href="../favicon-32x32.png"', $html);
    $html = str_replace('href="paneel.css?', 'href="../paneel.css?', $html);
    $html = str_replace('src="paneel-thema.js?', 'src="../paneel-thema.js?', $html);
    $html = str_replace('src="paneel.js?', 'src="../paneel.js?', $html);
    $html = str_replace('src="images/', 'src="../images/', $html);
    $html = str_replace("url('images/", "url('../images/", $html);
    return $html;
});

require dirname(__DIR__) . '/leden-app.php';
