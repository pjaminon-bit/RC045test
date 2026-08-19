<?php
// Backwards-compatible ingang. Het beheer is nu een echte map met index.php.
// Eventuele oude queryparameters blijven behouden bij de redirect naar /beheer/.
$script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/beheer.php'));
$basis = rtrim(str_replace('\\', '/', dirname($script)), '/');
$query = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
$doel = ($basis === '' ? '' : $basis) . '/beheer/' . ($query === '' ? '' : '?' . $query);
header('Location: ' . $doel, true, 308);
exit;
