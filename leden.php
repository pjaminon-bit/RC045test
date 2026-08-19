<?php
// Backwards-compatible ingang. Het ledengedeelte is nu een echte map.
$script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/leden.php'));
$basis = rtrim(str_replace('\\', '/', dirname($script)), '/');
header('Location: ' . ($basis === '' ? '' : $basis) . '/leden/', true, 308);
exit;
