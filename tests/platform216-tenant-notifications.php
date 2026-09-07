<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function c216(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) { $ok++; echo "OK: {$label}\n"; return; }
    $fout++; fwrite(STDERR, "FOUT: {$label}\n");
}

function wis216(string $pad): void
{
    if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        wis216($pad . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($pad);
}

$tmp = sys_get_temp_dir() . '/issue216-notifications-' . bin2hex(random_bytes(5));
$private = $tmp . '/private';
$notifications = $private . '/notifications';
$configPad = $tmp . '/config.php';
@mkdir($private . '/collections', 0750, true);
@mkdir($notifications, 0750, true);
$credentials = $notifications . '/smtp-credentials.json';
file_put_contents($credentials, json_encode(['username'=>'pilot-user','password'=>'dummy-secret-value'], JSON_THROW_ON_ERROR));
@chmod($credentials, 0600);

$config = [
    'vereniging'=>[
        'sleutel'=>'notify-test',
        'naam'=>'Notification Test',
        'volledige_naam'=>'Notification Test',
        'site_url'=>'https://notify.example.invalid',
        'timezone'=>'Europe/Amsterdam',
    ],
    'modules'=>['website'=>true,'aanmelden'=>true],
    'opslag'=>[
        'private_driver'=>'json',
        'private_root'=>$private,
        'pdo'=>['dsn'=>'','user'=>'','password'=>''],
    ],
    'notificaties'=>[
        'enabled'=>true,
        'required'=>true,
        'provider'=>'smtp',
        'from'=>'no-reply@notify.example.invalid',
        'recipients'=>[
            'contact.received'=>['board@notify.example.invalid'],
            'membership.received'=>['members@notify.example.invalid'],
        ],
        'stale_after_seconds'=>300,
        'smtp'=>[
            'host'=>'smtp.notify.example.invalid',
            'port'=>587,
            'security'=>'starttls',
            'credentials_file'=>$credentials,
        ],
    ],
];
file_put_contents($configPad, "<?php\nreturn " . var_export($config, true) . ";\n");
putenv('VERENIGING_REQUIRE_TENANT_CONFIG=1');
putenv('VERENIGING_CONFIG_FILE=' . $configPad);
putenv('VERENIGING_PRIVATE_ROOT=' . $private);

try {
    require_once $root . '/app/notifications/tenant-notifications.php';

    $disabled = $config;
    $disabled['notificaties'] = ['enabled'=>false,'required'=>false];
    $statusDisabled = tenantNotificationConfigStatus($disabled);
    c216(($statusDisabled['ok'] ?? false) === true && ($statusDisabled['code'] ?? '') === 'disabled', 'expliciet disabled notificaties vereisen geen provider of secret');

    $requiredDisabled = $disabled;
    $requiredDisabled['notificaties']['required'] = true;
    $statusRequiredDisabled = tenantNotificationConfigStatus($requiredDisabled);
    c216(($statusRequiredDisabled['ok'] ?? true) === false && ($statusRequiredDisabled['code'] ?? '') === 'required_disabled', 'required maar disabled faalt readiness gesloten');

    $status = tenantNotificationConfigStatus($config);
    c216(($status['ok'] ?? false) === true && ($status['code'] ?? '') === 'ready' && ($status['provider'] ?? '') === 'smtp', 'geldige tenantbound SMTP-config is ready');
    c216(($status['smtp']['security'] ?? '') === 'starttls', 'SMTP readiness houdt TLS-modus expliciet vast');

    $badTls = $config;
    $badTls['notificaties']['smtp']['security'] = 'none';
    c216((tenantNotificationConfigStatus($badTls)['code'] ?? '') === 'smtp_security_invalid', 'plaintext SMTP-config wordt geweigerd');

    $badFrom = $config;
    $badFrom['notificaties']['from'] = "board@notify.example.invalid\r\nBcc: attacker@example.invalid";
    c216((tenantNotificationConfigStatus($badFrom)['code'] ?? '') === 'from_invalid', 'mailheader-injectie via afzender wordt geweigerd');

    $outside = $tmp . '/outside-credentials.json';
    file_put_contents($outside, json_encode(['username'=>'x','password'=>'y'], JSON_THROW_ON_ERROR));
    @chmod($outside, 0600);
    $badOutside = $config;
    $badOutside['notificaties']['smtp']['credentials_file'] = $outside;
    c216((tenantNotificationConfigStatus($badOutside)['code'] ?? '') === 'credentials_outside_tenant', 'credentials buiten tenantgrens worden geweigerd');

    $unsafe = $notifications . '/unsafe.json';
    file_put_contents($unsafe, json_encode(['username'=>'x','password'=>'y'], JSON_THROW_ON_ERROR));
    @chmod($unsafe, 0644);
    $badMode = $config;
    $badMode['notificaties']['smtp']['credentials_file'] = $unsafe;
    c216((tenantNotificationConfigStatus($badMode)['code'] ?? '') === 'credentials_permissions', 'world-readable credentials worden geweigerd');

    $malformed = $notifications . '/malformed.json';
    file_put_contents($malformed, '{broken');
    @chmod($malformed, 0600);
    $badJson = $config;
    $badJson['notificaties']['smtp']['credentials_file'] = $malformed;
    c216((tenantNotificationConfigStatus($badJson)['code'] ?? '') === 'credentials_invalid', 'ongeldige credential-JSON faalt gesloten');

    $link = $notifications . '/linked.json';
    if (@symlink($credentials, $link)) {
        $badLink = $config;
        $badLink['notificaties']['smtp']['credentials_file'] = $link;
        c216((tenantNotificationConfigStatus($badLink)['code'] ?? '') === 'credentials_missing', 'credentialsymlink wordt geweigerd');
    }

    $piiItem = [
        'id'=>'ntf_render_test',
        'type'=>'contact.received',
        'naam'=>'Sensitive Person',
        'email'=>'sensitive@example.invalid',
        'telefoon'=>'0612345678',
        'bericht'=>'zeer geheime formulierinhoud',
        'geboortedatum'=>'1985-01-01',
    ];
    $mail = tenantNotificationRender($status, $piiItem);
    $mailTekst = implode("\n", [(string)$mail['subject'], (string)$mail['body']]);
    c216(str_contains($mailTekst, '/beheer/contactberichten.php'), 'contactnotificatie verwijst naar geauthenticeerde beheerinbox');
    foreach (['Sensitive Person','sensitive@example.invalid','0612345678','zeer geheime formulierinhoud','1985-01-01'] as $pii) {
        c216(!str_contains($mailTekst, $pii), 'notification renderer lekt geen bron-PII: ' . $pii);
    }

    $membershipMail = tenantNotificationRender($status, ['id'=>'ntf_membership','type'=>'membership.received']);
    c216(str_contains((string)$membershipMail['body'], '/beheer/aanmeldingen.php'), 'aanmeldnotificatie verwijst naar beveiligde aanmeldingeninbox');

    $invalidTransport = tenantNotificationSmtpSend($status, $mail, static fn(): array => []);
    c216(($invalidTransport['ok'] ?? true) === false && ($invalidTransport['code'] ?? '') === 'transport_contract', 'provideroverride moet expliciet deliverycontract teruggeven');
    $safeFailure = tenantNotificationSmtpSend($status, $mail, static fn(): array => ['ok'=>false,'code'=>'transport_timeout','secret'=>'mag-niet-door']);
    c216($safeFailure === ['ok'=>false,'code'=>'transport_timeout'], 'providerfailure wordt tot veilige foutcode genormaliseerd');

    c216(tenantNotificationBackoffSeconds(1) === 60 && tenantNotificationBackoffSeconds(2) === 300 && tenantNotificationBackoffSeconds(3) === 900 && tenantNotificationBackoffSeconds(4) === 3600, 'retrybackoff is begrensd en deterministisch');

    $now = 2000000000;
    c216(tenantNotificationEnqueue('contact.received', 'msg_test', $now - 10), 'contactevent wordt duurzaam in tenantoutbox gezet');
    c216(tenantNotificationEnqueue('contact.received', 'msg_test', $now - 10), 'dezelfde notification intent is idempotent');
    $outbox = tenantNotificationOutboxLees();
    c216(count($outbox['items']) === 1 && ($outbox['items'][0]['status'] ?? '') === 'pending', 'outbox bevat één PII-arme pending intent');
    $serializedOutbox = json_encode($outbox, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    foreach (['Sensitive Person','sensitive@example.invalid','0612345678','geboortedatum','bericht'] as $pii) {
        c216(!str_contains($serializedOutbox, $pii), 'outbox bewaart geen formulier-PII: ' . $pii);
    }

    $fail = tenantNotificationDispatchOne($config, static fn(): array => ['ok'=>false,'code'=>'transport_timeout'], $now);
    c216(($fail['attempted'] ?? false) === true && ($fail['ok'] ?? true) === false && ($fail['state'] ?? '') === 'transport_timeout', 'providerfailure blijft observeerbare mislukte deliverypoging');
    $outboxNaFail = tenantNotificationOutboxLees();
    $pending = $outboxNaFail['items'][0] ?? [];
    c216(($pending['status'] ?? '') === 'pending' && (int)($pending['attempts'] ?? 0) === 1 && ($pending['last_error_code'] ?? '') === 'transport_timeout', 'mislukte delivery blijft duurzaam pending met veilige foutcode');
    c216(tenantNotificationTimestamp((string)($pending['next_attempt_at'] ?? '')) === $now + 60, 'mislukte eerste delivery plant begrensde retry');

    $teVroeg = tenantNotificationDispatchOne($config, static fn(): array => ['ok'=>true], $now + 30);
    c216(($teVroeg['attempted'] ?? true) === false && ($teVroeg['state'] ?? '') === 'idle', 'retry wordt niet vóór next_attempt_at uitgevoerd');
    $succes = tenantNotificationDispatchOne($config, static fn(): array => ['ok'=>true], $now + 61);
    c216(($succes['attempted'] ?? false) === true && ($succes['ok'] ?? false) === true, 'succesvolle retry wordt afgeleverd');
    $outboxNaSucces = tenantNotificationOutboxLees();
    c216(($outboxNaSucces['items'][0]['status'] ?? '') === 'delivered' && ($outboxNaSucces['items'][0]['delivered_at'] ?? null) !== null, 'alleen succesvolle provideracceptatie markeert delivered');

    c216(tenantNotificationEnqueue('membership.received', 'app_stale', $now - 1000), 'tweede event kan onafhankelijk worden gequeued');
    $backlog = tenantNotificationBacklogStatus($config, $now);
    c216(($backlog['ok'] ?? true) === false && ($backlog['code'] ?? '') === 'backlog_stale' && (int)($backlog['pending'] ?? 0) === 1, 'stale pending backlog maakt notification readiness ongezond');

    $contactBron = (string)file_get_contents($root . '/contact-ontvangst.php');
    $aanmeldBron = (string)file_get_contents($root . '/aanmelden-ontvangst.php');
    $notificationBron = (string)file_get_contents($root . '/app/notifications/tenant-notifications.php');
    $healthBron = (string)file_get_contents($root . '/healthz.php');
    $siteConfigBron = (string)file_get_contents($root . '/site-config.php');
    foreach ([['contact',$contactBron,'contactBerichtenSchrijf($inbox)','tenantNotificationEnqueue('],['aanmelden',$aanmeldBron,'aanmeldingenSchrijf($inbox)','tenantNotificationEnqueue(']] as [$naam,$bron,$primary,$enqueue]) {
        $primaryPos = strpos($bron, $primary); $enqueuePos = strpos($bron, $enqueue);
        c216($primaryPos !== false && $enqueuePos !== false && $primaryPos < $enqueuePos, $naam . ': primaire lokale write gebeurt vóór notification enqueue');
        c216(!str_contains($bron, 'privateStoreTransactie('), $naam . ': notification enqueue kan primaire formulierwrite niet transactioneel terugrollen');
        c216(!str_contains($bron, 'tenantNotificationSmtpSend(') && !str_contains($bron, 'curl_'), $naam . ': browserrequest voert geen provider-netwerkcall uit');
    }
    c216(preg_match('/\b(?:exec|shell_exec|system|proc_open|passthru)\s*\(/', $notificationBron) !== 1, 'notification runtime krijgt geen shell/root-executieboundary');
    c216(str_contains($healthBron, 'tenantNotificationDispatchOne($config)') && str_contains($healthBron, 'tenantNotificationBacklogStatus($config)'), 'bestaande tenant-FPM healthhook verzorgt begrensde retry en backlogbewaking');
    c216(str_contains($siteConfigBron, '$formAction = "\'self\'"') && str_contains($siteConfigBron, 'form-action {$formAction}'), 'CSP blijft alle publieke formulieren same-origin begrenzen');
} finally {
    putenv('VERENIGING_REQUIRE_TENANT_CONFIG');
    putenv('VERENIGING_CONFIG_FILE');
    putenv('VERENIGING_PRIVATE_ROOT');
    wis216($tmp);
}

echo "Issue #216 tenant notifications: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
