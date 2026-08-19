<?php
// Generieke contentpagina-route: de URL blijft baanreglement.php/baanreglement.html,
// maar layout en inhoud worden opgebouwd via het paginatype "artikelen".
require_once __DIR__ . '/app/content/content-renderer.php';
require_once __DIR__ . '/app/content/content-publieke-nav.php';
contentPubliekeNavStartFilter('baanreglement', rc045Taal());
contentRenderArtikelen('baanreglement');
