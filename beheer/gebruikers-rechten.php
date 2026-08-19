<?php
// ============================================================
// Centrale rechtenpresentatie voor gebruikersbeheer
// ============================================================
// Geen eigen rechtenlijst meer: de platformdefinities zijn de bron.
// ============================================================
require_once dirname(__DIR__) . '/app/auth-capabilities.php';

$groepen = authCapabilityGroepen();
$gevoelig = [];
foreach (authCapabilityDefinities() as $capability => $def) {
    if (!empty($def['gevoelig'])) $gevoelig[] = (string) $capability;
}

return [
    'groepen' => $groepen,
    'gevoelig' => $gevoelig,
];
