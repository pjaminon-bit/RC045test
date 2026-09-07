<?php
// Fase 6A — configureer alleen niet-geheime tenantnotificatiemetadata.
// SMTP-credentials worden bewust niet via argv, repository of deze CLI gezet.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Alleen via CLI beschikbaar.');
}

function notifProvisionStop(string $melding): never
{
    fwrite(STDERR, "FOUT: {$melding}\n");
    exit(1);
}

function notifProvisionHelp(): void
{
    echo "Gebruik:\n";
    echo "  php bin/configure-tenant-notifications.php --config=/srv/verenigingen/<tenant>/config.php \\\n";
    echo "    --from=notificaties@vereniging.example \\\n";
    echo "    --contact-to=bestuur@vereniging.example \\\n";
    echo "    --membership-to=ledenadministratie@vereniging.example \\\n";
    echo "    --smtp-host=smtp.provider.example [--smtp-port=587] [--security=starttls|smtps] [--required] [--dry-run]\n\n";
    echo "Meerdere ontvangers: komma-gescheiden. Deze CLI schrijft nooit SMTP-credentials.\n";
}

$opt = getopt('', [
    'config:', 'from:', 'contact-to:', 'membership-to:', 'smtp-host:',
    'smtp-port::', 'security::', 'required', 'dry-run', 'help',
]);
if (isset($opt['help'])) { notifProvisionHelp(); exit(0); }
foreach (['config','from','contact-to','membership-to','smtp-host'] as $key) {
    if (!isset($opt[$key]) || trim((string)$opt[$key]) === '') notifProvisionStop("--{$key} is verplicht.");
}

require_once dirname(__DIR__) . '/app/core/tenant-runtime.php';
require_once dirname(__DIR__) . '/app/notifications/tenant-notifications.php';

$configPad = trim((string)$opt['config']);
if (!tenantRuntimeIsAbsoluutPad($configPad) || is_link($configPad) || !is_file($configPad) || !is_readable($configPad)) {
    notifProvisionStop('--config moet een bestaand, absoluut, niet-symlink serverconfigbestand zijn.');
}
$configReal = realpath($configPad);
if ($configReal === false) notifProvisionStop('Tenantconfig kon niet fysiek worden opgelost.');
$config = require $configReal;
if (!is_array($config)) notifProvisionStop('Tenantconfig retourneert geen array.');

$privateRoot = tenantRuntimePrivateRoot($config);
if ($privateRoot === null || is_link($privateRoot) || !is_dir($privateRoot)) notifProvisionStop('Tenantconfig heeft geen veilige bestaande private_root.');
$privateReal = realpath($privateRoot);
if ($privateReal === false) notifProvisionStop('private_root kon niet fysiek worden opgelost.');
$tenantRoot = dirname($privateReal);
if (basename($privateReal) !== 'private' || dirname($configReal) !== $tenantRoot) {
    notifProvisionStop('Voor deze onboarding moet config.php direct naast de provisioned private-map staan.');
}

$from = tenantNotificationEmail($opt['from']);
if ($from === null) notifProvisionStop('--from is geen veilig e-mailadres.');
$parseRecipients = static function (string $raw, string $label): array {
    $uit = [];
    foreach (explode(',', $raw) as $item) {
        $adres = tenantNotificationEmail($item);
        if ($adres === null) notifProvisionStop("{$label} bevat een ongeldig e-mailadres.");
        if (!in_array($adres, $uit, true)) $uit[] = $adres;
    }
    if ($uit === []) notifProvisionStop("{$label} bevat geen ontvanger.");
    return $uit;
};
$contactTo = $parseRecipients((string)$opt['contact-to'], '--contact-to');
$membershipTo = $parseRecipients((string)$opt['membership-to'], '--membership-to');

$host = trim((string)$opt['smtp-host']);
if (strlen($host) > 253 || preg_match('/^[A-Za-z0-9.-]+$/D', $host) !== 1) notifProvisionStop('--smtp-host is ongeldig.');
$port = (int)($opt['smtp-port'] ?? 587);
if ($port < 1 || $port > 65535) notifProvisionStop('--smtp-port is ongeldig.');
$security = strtolower(trim((string)($opt['security'] ?? 'starttls')));
if (!in_array($security, ['starttls','smtps'], true)) notifProvisionStop('--security moet starttls of smtps zijn.');

$secretsDir = $privateReal . DIRECTORY_SEPARATOR . 'secrets';
$credentials = $secretsDir . DIRECTORY_SEPARATOR . 'notifications-smtp.json';
if (is_link($secretsDir) || is_link($credentials)) notifProvisionStop('Notification secretpad mag geen symlink bevatten.');
if (file_exists($credentials) && (!is_file($credentials) || is_link($credentials))) notifProvisionStop('Bestaand credentialsdoel is geen veilig regulier bestand.');

$config['notificaties'] = [
    'enabled' => true,
    'required' => isset($opt['required']),
    'provider' => 'smtp',
    'from' => $from,
    'recipients' => [
        'contact.received' => $contactTo,
        'membership.received' => $membershipTo,
    ],
    'smtp' => [
        'host' => $host,
        'port' => $port,
        'security' => $security,
        'credentials_file' => $credentials,
    ],
    'stale_after_seconds' => 900,
];

$inhoud = "<?php\n// Gegenereerd/bijgewerkt door tenant onboardingtools.\nreturn " . var_export($config, true) . ";\n";
$dryRun = isset($opt['dry-run']);
if (!$dryRun) {
    if (!is_dir($secretsDir) && !@mkdir($secretsDir, 0750, false)) notifProvisionStop('private/secrets kon niet worden aangemaakt.');
    clearstatcache(true, $secretsDir);
    if (!is_dir($secretsDir) || is_link($secretsDir) || realpath(dirname($secretsDir)) !== $privateReal) {
        notifProvisionStop('private/secrets voldoet na aanmaak niet aan de tenantgrens.');
    }
    @chmod($secretsDir, 0750);

    $tmp = $configReal . '.tmp.' . bin2hex(random_bytes(6));
    if (@file_put_contents($tmp, $inhoud, LOCK_EX) === false) notifProvisionStop('Tijdelijke tenantconfig kon niet worden geschreven.');
    @chmod($tmp, 0640);
    if (is_link($configReal) || !@rename($tmp, $configReal)) { @unlink($tmp); notifProvisionStop('Tenantconfig kon niet atomisch worden vervangen.'); }
    @chmod($configReal, 0640);
}

echo ($dryRun ? 'DRY-RUN' : 'GEREED') . ": notification transportconfig voor " . (string)($config['vereniging']['sleutel'] ?? 'tenant') . "\n";
echo "Credentialsbestand (niet door deze CLI aangemaakt): {$credentials}\n";
echo "Vereist formaat: JSON-object met de sleutels username en password; bestand mode 0640 of strenger.\n";
echo "Daarna: php bin/check-tenant-notifications.php en vervolgens desgewenst --send-test.\n";
