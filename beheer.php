<?php
// Backwards-compatible ingang. Het beheer is nu een echte map met index.php.
$script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/beheer.php'));
$basis = rtrim(str_replace('\\', '/', dirname($script)), '/');
header('Location: ' . ($basis === '' ? '' : $basis) . '/beheer/', true, 308);
exit;
