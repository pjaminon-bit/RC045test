<?php
// Generieke contentpagina-route: de URL blijft ontstaan.php/ontstaan.html,
// maar layout en inhoud worden opgebouwd via het paginatype "verhaal".
require_once __DIR__ . '/content-renderer.php';
require_once __DIR__ . '/content-publieke-nav.php';
contentPubliekeNavStartFilter('ontstaan', rc045Taal());
contentRenderVerhaal('ontstaan');
