<?php
// Backwards-compatible ingang. Het ledengedeelte is nu een echte map.
// Oude directe links zoals leden.php?lid=... blijven volledig bruikbaar:
// behalve het pad nemen we ook de bestaande querystring mee naar /leden/.
$script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/leden.php'));
$basis = rtrim(str_replace('\\', '/', dirname($script)), '/');
$query = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
$doel = ($basis === '' ? '' : $basis) . '/leden/' . ($query === '' ? '' : '?' . $query);
header('Location: ' . $doel, true, 308);
exit;
