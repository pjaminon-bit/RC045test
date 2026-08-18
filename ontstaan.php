<?php
// Generieke contentpagina-route: de URL blijft ontstaan.php/ontstaan.html,
// maar layout en inhoud worden opgebouwd via het paginatype "verhaal".
require_once __DIR__ . '/content-renderer.php';
contentRenderVerhaal('ontstaan');
