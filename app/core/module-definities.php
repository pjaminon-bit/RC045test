<?php
// ============================================================
// Compatibiliteitsadapter voor feature-/moduledefinities
// ============================================================
// Vanaf fase 2.5 staat de enige bron van waarheid in platform-definities.php.
// Bestaande code die dit bestand require't blijft daardoor werken zonder een
// tweede, afwijkende modulelijst bij te houden.
// ============================================================

$platform = require __DIR__ . '/platform-definities.php';
return isset($platform['features']) && is_array($platform['features'])
    ? $platform['features']
    : [];
