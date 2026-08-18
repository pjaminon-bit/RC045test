<?php
// Generieke contentpagina-route: de URL blijft baanreglement.php/baanreglement.html,
// maar layout en inhoud worden opgebouwd via het paginatype "artikelen".
require_once __DIR__ . '/content-renderer.php';
contentRenderArtikelen('baanreglement');
