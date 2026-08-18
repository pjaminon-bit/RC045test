<?php
// Generieke contentpagina-route: de URL blijft baanreglement.php/baanreglement.html,
// maar layout en inhoud worden opgebouwd via het paginatype "artikelen".
require_once __DIR__ . '/content-renderer.php';
require_once __DIR__ . '/content-publieke-nav.php';
contentPubliekeNavStartFilter('baanreglement', rc045Taal());
contentRenderArtikelen('baanreglement');
