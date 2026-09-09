<?php
require_once dirname(__DIR__) . '/app/web/public-route-contract.php';

if (public226CliServerStatic(__DIR__, (string)($_SERVER['REQUEST_URI'] ?? '/'))) {
    return false;
}

$public226Target = public226Dispatch();
if (is_string($public226Target)) {
    require $public226Target;
}
unset($public226Target);