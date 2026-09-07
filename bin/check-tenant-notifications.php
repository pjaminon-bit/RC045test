<?php
// Fase 6A — secretvrije notification readiness check.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Alleen via CLI beschikbaar.');
}

$opt = getopt('', ['send-test', 'help']);
if (isset($opt['help'])) {
    echo "Gebruik:\n";
    echo "  php bin/check-tenant-notifications.php\n";
    echo "  php bin/check-tenant-notifications.php --send-test\n\n";
    echo "De tenant wordt uitsluitend uit VERENIGING_CONFIG_FILE geladen.\n";
    echo "Uitvoer bevat nooit SMTP-credentials of providerresponses.\n";
    exit(0);
}

require_once dirname(__DIR__) . '/app/storage/private-store.php';
require_once dirname(__DIR__) . '/app/notifications/tenant-notifications.php';
$config = privateStoreConfig();
$status = tenantNotificationConfigStatus($config);

$toon = [
    'tenant_key' => privateStoreTenant(),
    'enabled' => (bool)($status['enabled'] ?? false),
    'required' => (bool)($status['required'] ?? false),
    'config_state' => (string)($status['code'] ?? 'unknown'),
];

if (($status['ok'] ?? false) === true && ($status['enabled'] ?? false) === true) {
    try {
        $backlog = tenantNotificationBacklogStatus($config);
        $toon['backlog_state'] = (string)($backlog['code'] ?? 'unknown');
        $toon['pending'] = (int)($backlog['pending'] ?? 0);
        $toon['oldest_age_seconds'] = (int)($backlog['oldest_age'] ?? 0);
    } catch (Throwable $e) {
        $toon['backlog_state'] = 'storage_unavailable';
        $toon['pending'] = 0;
        $toon['oldest_age_seconds'] = 0;
        $status['ok'] = false;
    }
}

echo json_encode($toon, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
if (($status['ok'] ?? false) !== true) exit(1);
if (($status['enabled'] ?? false) !== true) exit(0);

if (isset($opt['send-test'])) {
    $bericht = [
        'from' => (string)$status['from'],
        'to' => (array)$status['recipients']['contact.received'],
        'subject' => 'Verenigingsplatform testnotificatie',
        'body' => "De tenant-specifieke server-side notificatieroute is bereikbaar.\n\nDeze test bevat geen formulier- of ledengegevens.",
        'event_id' => 'test_' . bin2hex(random_bytes(8)),
    ];
    $delivery = tenantNotificationSmtpSend($status, $bericht);
    echo json_encode([
        'test_delivery' => (string)($delivery['code'] ?? 'transport_failed'),
        'delivered' => ($delivery['ok'] ?? false) === true,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(($delivery['ok'] ?? false) === true ? 0 : 2);
}

$backlog = $backlog ?? tenantNotificationBacklogStatus($config);
exit(($backlog['ok'] ?? false) === true ? 0 : 3);
