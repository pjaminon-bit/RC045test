<?php
// ============================================================
// Fase 6A — tenant-specifieke server-side notificaties
// ============================================================
// Primaire formulierdata blijft uitsluitend in de lokale tenantstore. Deze
// laag bewaart alleen PII-arme delivery-intents en verstuurt ze asynchroon via
// een tenantgebonden SMTP-relay. De webrequest doet nooit een provider-call.
// ============================================================
require_once dirname(__DIR__) . '/data-slot.php';
require_once dirname(__DIR__) . '/storage/private-store.php';

function tenantNotificationRawConfig(?array $config = null): array
{
    $config = $config ?? privateStoreConfig();
    $raw = $config['notificaties'] ?? [];
    return is_array($raw) ? $raw : [];
}

function tenantNotificationBool(array $raw, string $key, bool $default = false): bool
{
    if (!array_key_exists($key, $raw)) return $default;
    return $raw[$key] === true;
}

function tenantNotificationEnabled(?array $config = null): bool
{
    return tenantNotificationBool(tenantNotificationRawConfig($config), 'enabled', false);
}

function tenantNotificationRequired(?array $config = null): bool
{
    return tenantNotificationBool(tenantNotificationRawConfig($config), 'required', false);
}

function tenantNotificationShouldEnqueue(?array $config = null): bool
{
    $raw = tenantNotificationRawConfig($config);
    return tenantNotificationBool($raw, 'enabled', false) || tenantNotificationBool($raw, 'required', false);
}

function tenantNotificationEmail($waarde): ?string
{
    if (!is_scalar($waarde)) return null;
    $waarde = trim((string)$waarde);
    if ($waarde === '' || str_contains($waarde, "\r") || str_contains($waarde, "\n")) return null;
    return filter_var($waarde, FILTER_VALIDATE_EMAIL) ? $waarde : null;
}

function tenantNotificationAdresLijst($waarde): ?array
{
    if (!is_array($waarde) || $waarde === []) return null;
    $uit = [];
    foreach ($waarde as $adres) {
        $veilig = tenantNotificationEmail($adres);
        if ($veilig === null) return null;
        if (!in_array($veilig, $uit, true)) $uit[] = $veilig;
    }
    return $uit === [] ? null : $uit;
}

function tenantNotificationPathBinnen(string $pad, string $root): bool
{
    $norm = static function (string $waarde): string {
        $waarde = str_replace('\\', '/', $waarde);
        $waarde = (string)preg_replace('~/+~', '/', $waarde);
        return rtrim($waarde, '/');
    };
    $pad = $norm($pad);
    $root = $norm($root);
    return $pad === $root || strncmp($pad, $root . '/', strlen($root) + 1) === 0;
}

function tenantNotificationConfigStatus(?array $config = null): array
{
    $config = $config ?? privateStoreConfig();
    $raw = tenantNotificationRawConfig($config);
    foreach (['enabled','required'] as $booleanKey) {
        if (array_key_exists($booleanKey, $raw) && !is_bool($raw[$booleanKey])) {
            return ['ok'=>false,'enabled'=>false,'required'=>false,'code'=>'boolean_invalid'];
        }
    }
    $enabled = tenantNotificationBool($raw, 'enabled', false);
    $required = tenantNotificationBool($raw, 'required', false);

    if (!$enabled && !$required) return ['ok'=>true,'enabled'=>false,'required'=>false,'code'=>'disabled'];
    if (!$enabled && $required) return ['ok'=>false,'enabled'=>false,'required'=>true,'code'=>'required_disabled'];
    if (($raw['provider'] ?? '') !== 'smtp') return ['ok'=>false,'enabled'=>true,'required'=>$required,'code'=>'provider_invalid'];

    $from = tenantNotificationEmail($raw['from'] ?? null);
    if ($from === null) return ['ok'=>false,'enabled'=>true,'required'=>$required,'code'=>'from_invalid'];

    $recipients = is_array($raw['recipients'] ?? null) ? $raw['recipients'] : [];
    foreach (['contact.received','membership.received'] as $type) {
        if (tenantNotificationAdresLijst($recipients[$type] ?? null) === null) {
            return ['ok'=>false,'enabled'=>true,'required'=>$required,'code'=>'recipients_invalid'];
        }
    }

    $smtp = is_array($raw['smtp'] ?? null) ? $raw['smtp'] : [];
    $host = trim((string)($smtp['host'] ?? ''));
    if ($host === '' || strlen($host) > 253 || preg_match('/^[A-Za-z0-9.-]+$/D', $host) !== 1) {
        return ['ok'=>false,'enabled'=>true,'required'=>$required,'code'=>'smtp_host_invalid'];
    }
    $port = (int)($smtp['port'] ?? 0);
    if ($port < 1 || $port > 65535) return ['ok'=>false,'enabled'=>true,'required'=>$required,'code'=>'smtp_port_invalid'];
    $security = (string)($smtp['security'] ?? '');
    if (!in_array($security, ['starttls','smtps'], true)) {
        return ['ok'=>false,'enabled'=>true,'required'=>$required,'code'=>'smtp_security_invalid'];
    }

    $privateRoot = tenantRuntimePrivateRoot($config);
    if ($privateRoot === null || !is_dir($privateRoot) || is_link($privateRoot)) {
        return ['ok'=>false,'enabled'=>true,'required'=>$required,'code'=>'private_root_invalid'];
    }
    $rootReal = realpath($privateRoot);
    if ($rootReal === false) return ['ok'=>false,'enabled'=>true,'required'=>$required,'code'=>'private_root_invalid'];

    $secretPad = trim((string)($smtp['credentials_file'] ?? ''));
    if ($secretPad === '' || !tenantRuntimeIsAbsoluutPad($secretPad) || is_link($secretPad) || !is_file($secretPad)) {
        return ['ok'=>false,'enabled'=>true,'required'=>$required,'code'=>'credentials_missing'];
    }
    $secretReal = realpath($secretPad);
    if ($secretReal === false || !tenantNotificationPathBinnen($secretReal, $rootReal)) {
        return ['ok'=>false,'enabled'=>true,'required'=>$required,'code'=>'credentials_outside_tenant'];
    }
    $stat = @stat($secretReal);
    if (!is_array($stat)) return ['ok'=>false,'enabled'=>true,'required'=>$required,'code'=>'credentials_stat_failed'];
    $mode = (int)($stat['mode'] ?? 0) & 0777;
    if (($mode & 0037) !== 0) {
        return ['ok'=>false,'enabled'=>true,'required'=>$required,'code'=>'credentials_permissions'];
    }

    $rawSecret = @file_get_contents($secretReal);
    if ($rawSecret === false) return ['ok'=>false,'enabled'=>true,'required'=>$required,'code'=>'credentials_unreadable'];
    try { $secret = json_decode($rawSecret, true, 16, JSON_THROW_ON_ERROR); }
    catch (Throwable $e) { return ['ok'=>false,'enabled'=>true,'required'=>$required,'code'=>'credentials_invalid']; }
    if (!is_array($secret)) return ['ok'=>false,'enabled'=>true,'required'=>$required,'code'=>'credentials_invalid'];
    $username = trim((string)($secret['username'] ?? ''));
    $password = (string)($secret['password'] ?? '');
    if ($username === '' || $password === '' || str_contains($username, "\0") || str_contains($password, "\0")) {
        return ['ok'=>false,'enabled'=>true,'required'=>$required,'code'=>'credentials_invalid'];
    }

    $siteUrl = rtrim(trim((string)($config['vereniging']['site_url'] ?? '')), '/');
    if (!filter_var($siteUrl, FILTER_VALIDATE_URL) || strtolower((string)parse_url($siteUrl, PHP_URL_SCHEME)) !== 'https') {
        return ['ok'=>false,'enabled'=>true,'required'=>$required,'code'=>'site_url_invalid'];
    }

    $stale = max(300, min(86400, (int)($raw['stale_after_seconds'] ?? 900)));
    return [
        'ok'=>true,
        'enabled'=>true,
        'required'=>$required,
        'code'=>'ready',
        'provider'=>'smtp',
        'from'=>$from,
        'recipients'=>[
            'contact.received'=>tenantNotificationAdresLijst($recipients['contact.received']),
            'membership.received'=>tenantNotificationAdresLijst($recipients['membership.received']),
        ],
        'smtp'=>[
            'host'=>$host,
            'port'=>$port,
            'security'=>$security,
            'credentials_file'=>$secretReal,
        ],
        'site_url'=>$siteUrl,
        'stale_after_seconds'=>$stale,
    ];
}

function tenantNotificationCredentials(array $status): array
{
    $pad = (string)($status['smtp']['credentials_file'] ?? '');
    if ($pad === '' || is_link($pad) || !is_file($pad)) throw new RuntimeException('Notification credentials zijn niet veilig beschikbaar.');
    $raw = @file_get_contents($pad);
    if ($raw === false) throw new RuntimeException('Notification credentials zijn niet leesbaar.');
    try { $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR); }
    catch (Throwable $e) { throw new RuntimeException('Notification credentials zijn ongeldig.'); }
    if (!is_array($data)) throw new RuntimeException('Notification credentials zijn ongeldig.');
    return ['username'=>(string)($data['username'] ?? ''),'password'=>(string)($data['password'] ?? '')];
}

function tenantNotificationOutboxLeeg(): array
{
    return ['schema'=>1,'updated'=>date('c'),'items'=>[]];
}

function tenantNotificationOutboxLees(): array
{
    $data = privateStoreLees('notification_outbox', static fn(): array => []);
    if ($data === []) return tenantNotificationOutboxLeeg();
    if ((int)($data['schema'] ?? 0) !== 1 || !is_array($data['items'] ?? null)) {
        throw new RuntimeException('Notification outbox heeft een ongeldig schema.');
    }
    return $data;
}

function tenantNotificationOutboxSchrijf(array $data): bool
{
    $data['schema'] = 1;
    $data['updated'] = date('c');
    return privateStoreSchrijf('notification_outbox', $data, static fn(array $ignored): bool => false);
}

function tenantNotificationEventKey(string $type, string $sourceId): string
{
    if (!in_array($type, ['contact.received','membership.received'], true)) throw new InvalidArgumentException('Onbekend notification eventtype.');
    $sourceId = trim($sourceId);
    if ($sourceId === '' || strlen($sourceId) > 120 || preg_match('/^[A-Za-z0-9_-]+$/D', $sourceId) !== 1) {
        throw new InvalidArgumentException('Ongeldige notification bronidentiteit.');
    }
    return hash('sha256', privateStoreTenant() . "\0" . $type . "\0" . $sourceId);
}

function tenantNotificationEnqueue(string $type, string $sourceId, ?int $now = null): bool
{
    if (!tenantNotificationShouldEnqueue()) return true;
    $now = $now ?? time();
    $key = tenantNotificationEventKey($type, $sourceId);
    $outbox = tenantNotificationOutboxLees();
    foreach ((array)$outbox['items'] as $item) {
        if (is_array($item) && hash_equals((string)($item['event_key'] ?? ''), $key)) return true;
    }
    $outbox['items'][] = [
        'id'=>'ntf_' . bin2hex(random_bytes(10)),
        'event_key'=>$key,
        'type'=>$type,
        'status'=>'pending',
        'attempts'=>0,
        'created_at'=>gmdate('Y-m-d\TH:i:s\Z', $now),
        'next_attempt_at'=>gmdate('Y-m-d\TH:i:s\Z', $now),
        'last_attempt_at'=>null,
        'last_error_code'=>null,
        'lease_token'=>null,
        'lease_until'=>null,
        'delivered_at'=>null,
    ];
    return tenantNotificationOutboxSchrijf($outbox);
}

function tenantNotificationBackoffSeconds(int $attempts): int
{
    return match (true) {
        $attempts <= 1 => 60,
        $attempts === 2 => 300,
        $attempts === 3 => 900,
        default => 3600,
    };
}

function tenantNotificationTimestamp(?string $waarde): int
{
    $t = $waarde === null ? false : strtotime($waarde);
    return $t === false ? 0 : $t;
}

function tenantNotificationClaim(?int $now = null): ?array
{
    $now = $now ?? time();
    $slot = dataSlotOpen();
    try {
        $outbox = tenantNotificationOutboxLees();
        foreach ($outbox['items'] as $i => $item) {
            if (!is_array($item) || ($item['status'] ?? '') !== 'pending') continue;
            $next = tenantNotificationTimestamp(isset($item['next_attempt_at']) ? (string)$item['next_attempt_at'] : null);
            $leaseUntil = tenantNotificationTimestamp(isset($item['lease_until']) ? (string)$item['lease_until'] : null);
            if ($next > $now || $leaseUntil > $now) continue;
            $token = bin2hex(random_bytes(12));
            $item['lease_token'] = $token;
            $item['lease_until'] = gmdate('Y-m-d\TH:i:s\Z', $now + 30);
            $outbox['items'][$i] = $item;
            if (!tenantNotificationOutboxSchrijf($outbox)) throw new RuntimeException('Notification outbox claim kon niet worden opgeslagen.');
            return $item;
        }
        return null;
    } finally {
        dataSlotDicht($slot);
    }
}

function tenantNotificationRender(array $status, array $item): array
{
    $type = (string)($item['type'] ?? '');
    $site = (string)($status['site_url'] ?? '');
    if ($type === 'contact.received') {
        $subject = 'Nieuw contactbericht staat klaar';
        $pad = '/beheer/contactberichten.php';
        $label = 'Er staat een nieuw contactbericht klaar in de beveiligde beheerinbox.';
    } elseif ($type === 'membership.received') {
        $subject = 'Nieuwe lidmaatschapsaanmelding staat klaar';
        $pad = '/beheer/aanmeldingen.php';
        $label = 'Er staat een nieuwe lidmaatschapsaanmelding klaar in de beveiligde beheerinbox.';
    } else {
        throw new InvalidArgumentException('Onbekend notification eventtype.');
    }
    return [
        'from'=>(string)$status['from'],
        'to'=>(array)$status['recipients'][$type],
        'subject'=>$subject,
        'body'=>$label . "\n\nOpen beheer: " . $site . $pad . "\n\nDe formulierinhoud en persoonsgegevens zijn bewust niet in deze e-mail opgenomen.",
        'event_id'=>(string)($item['id'] ?? ''),
    ];
}

function tenantNotificationMimeHeader(string $waarde): string
{
    $waarde = trim(str_replace(["\r","\n"], ' ', $waarde));
    return '=?UTF-8?B?' . base64_encode($waarde) . '?=';
}

function tenantNotificationSmtpPayload(array $message, string $tenantKey): string
{
    $from = tenantNotificationEmail($message['from'] ?? null);
    $to = tenantNotificationAdresLijst($message['to'] ?? null);
    if ($from === null || $to === null) throw new InvalidArgumentException('Notification mailadressen zijn ongeldig.');
    $subject = tenantNotificationMimeHeader((string)($message['subject'] ?? 'Notificatie'));
    $eventId = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)($message['event_id'] ?? 'event')) ?: 'event';
    $tenantKey = preg_replace('/[^a-z0-9-]+/', '-', strtolower($tenantKey)) ?: 'tenant';
    $body = str_replace(["\r\n","\r"], "\n", (string)($message['body'] ?? ''));
    $body = str_replace("\n", "\r\n", $body);
    $headers = [
        'From: <' . $from . '>',
        'To: ' . implode(', ', array_map(static fn(string $adres): string => '<'.$adres.'>', $to)),
        'Subject: ' . $subject,
        'Date: ' . gmdate('D, d M Y H:i:s O'),
        'Message-ID: <' . $eventId . '@' . $tenantKey . '.notifications>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    return implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n";
}

function tenantNotificationSmtpSend(array $status, array $message, ?callable $transportOverride = null): array
{
    if ($transportOverride !== null) {
        $result = $transportOverride($status, $message);
        if (!is_array($result) || !array_key_exists('ok', $result)) return ['ok'=>false,'code'=>'transport_contract'];
        return ['ok'=>$result['ok'] === true,'code'=>$result['ok'] === true ? 'delivered' : (string)($result['code'] ?? 'transport_failed')];
    }
    if (!function_exists('curl_init')) return ['ok'=>false,'code'=>'curl_missing'];

    try { $credentials = tenantNotificationCredentials($status); }
    catch (Throwable $e) { return ['ok'=>false,'code'=>'credentials_invalid']; }

    $smtp = $status['smtp'];
    $scheme = ($smtp['security'] ?? '') === 'smtps' ? 'smtps' : 'smtp';
    $url = $scheme . '://' . (string)$smtp['host'] . ':' . (int)$smtp['port'];
    $payload = tenantNotificationSmtpPayload($message, privateStoreTenant());
    $offset = 0;
    $ch = curl_init($url);
    if ($ch === false) return ['ok'=>false,'code'=>'transport_init'];

    $protocols = 0;
    if (defined('CURLPROTO_SMTP')) $protocols |= CURLPROTO_SMTP;
    if (defined('CURLPROTO_SMTPS')) $protocols |= CURLPROTO_SMTPS;
    $options = [
        CURLOPT_UPLOAD=>true,
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_CONNECTTIMEOUT=>5,
        CURLOPT_TIMEOUT=>12,
        CURLOPT_USERNAME=>$credentials['username'],
        CURLOPT_PASSWORD=>$credentials['password'],
        CURLOPT_MAIL_FROM=>'<' . (string)$message['from'] . '>',
        CURLOPT_MAIL_RCPT=>array_map(static fn(string $adres): string => '<'.$adres.'>', (array)$message['to']),
        CURLOPT_USE_SSL=>CURLUSESSL_ALL,
        CURLOPT_SSL_VERIFYPEER=>true,
        CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_INFILESIZE=>strlen($payload),
        CURLOPT_READFUNCTION=>static function ($handle, $stream, int $length) use (&$payload, &$offset): string {
            $chunk = substr($payload, $offset, $length);
            $offset += strlen($chunk);
            return $chunk;
        },
    ];
    if ($protocols !== 0 && defined('CURLOPT_PROTOCOLS')) $options[CURLOPT_PROTOCOLS] = $protocols;
    if (defined('CURLOPT_REDIR_PROTOCOLS')) $options[CURLOPT_REDIR_PROTOCOLS] = 0;
    if (!curl_setopt_array($ch, $options)) return ['ok'=>false,'code'=>'transport_init'];

    $result = curl_exec($ch);
    if ($result !== false) return ['ok'=>true,'code'=>'delivered'];
    $errno = curl_errno($ch);
    if (defined('CURLE_OPERATION_TIMEDOUT') && $errno === CURLE_OPERATION_TIMEDOUT) return ['ok'=>false,'code'=>'transport_timeout'];
    if (defined('CURLE_LOGIN_DENIED') && $errno === CURLE_LOGIN_DENIED) return ['ok'=>false,'code'=>'auth_failed'];
    foreach (['CURLE_SSL_CONNECT_ERROR','CURLE_PEER_FAILED_VERIFICATION','CURLE_SSL_CACERT_BADFILE','CURLE_USE_SSL_FAILED'] as $naam) {
        if (defined($naam) && $errno === constant($naam)) return ['ok'=>false,'code'=>'tls_failed'];
    }
    return ['ok'=>false,'code'=>'transport_failed'];
}

function tenantNotificationAfronden(array $claimed, array $delivery, ?int $now = null): bool
{
    $now = $now ?? time();
    $id = (string)($claimed['id'] ?? '');
    $token = (string)($claimed['lease_token'] ?? '');
    if ($id === '' || $token === '') return false;

    $slot = dataSlotOpen();
    try {
        $outbox = tenantNotificationOutboxLees();
        foreach ($outbox['items'] as $i => $item) {
            if (!is_array($item) || !hash_equals((string)($item['id'] ?? ''), $id)) continue;
            if (!hash_equals((string)($item['lease_token'] ?? ''), $token)) return false;
            $pogingen = max(0, (int)($item['attempts'] ?? 0)) + 1;
            $item['attempts'] = $pogingen;
            $item['last_attempt_at'] = gmdate('Y-m-d\TH:i:s\Z', $now);
            $item['lease_token'] = null;
            $item['lease_until'] = null;
            if (($delivery['ok'] ?? false) === true) {
                $item['status'] = 'delivered';
                $item['last_error_code'] = null;
                $item['delivered_at'] = gmdate('Y-m-d\TH:i:s\Z', $now);
                $item['next_attempt_at'] = null;
            } else {
                $item['status'] = 'pending';
                $code = (string)($delivery['code'] ?? 'transport_failed');
                $item['last_error_code'] = preg_match('/^[a-z][a-z0-9_-]{1,63}$/D', $code) === 1 ? $code : 'transport_failed';
                $item['next_attempt_at'] = gmdate('Y-m-d\TH:i:s\Z', $now + tenantNotificationBackoffSeconds($pogingen));
            }
            $outbox['items'][$i] = $item;
            $grens = $now - 7 * 86400;
            $outbox['items'] = array_values(array_filter($outbox['items'], static function ($record) use ($grens): bool {
                if (!is_array($record) || ($record['status'] ?? '') !== 'delivered') return true;
                return tenantNotificationTimestamp(isset($record['delivered_at']) ? (string)$record['delivered_at'] : null) >= $grens;
            }));
            if (!tenantNotificationOutboxSchrijf($outbox)) throw new RuntimeException('Notification outbox delivery-state kon niet worden opgeslagen.');
            return true;
        }
        return false;
    } finally {
        dataSlotDicht($slot);
    }
}

function tenantNotificationDispatchOne(?array $config = null, ?callable $transportOverride = null, ?int $now = null): array
{
    $config = $config ?? privateStoreConfig();
    $status = tenantNotificationConfigStatus($config);
    if (($status['enabled'] ?? false) !== true) return ['ok'=>(bool)($status['ok'] ?? false),'state'=>(string)($status['code'] ?? 'disabled'),'attempted'=>false];
    if (($status['ok'] ?? false) !== true) return ['ok'=>false,'state'=>(string)($status['code'] ?? 'config_invalid'),'attempted'=>false];

    $claimed = tenantNotificationClaim($now);
    if ($claimed === null) return ['ok'=>true,'state'=>'idle','attempted'=>false];
    try {
        $message = tenantNotificationRender($status, $claimed);
        $delivery = tenantNotificationSmtpSend($status, $message, $transportOverride);
    } catch (Throwable $e) {
        $delivery = ['ok'=>false,'code'=>'render_failed'];
    }
    tenantNotificationAfronden($claimed, $delivery, $now);
    return ['ok'=>($delivery['ok'] ?? false) === true,'state'=>(string)($delivery['code'] ?? 'transport_failed'),'attempted'=>true];
}

function tenantNotificationBacklogStatus(?array $config = null, ?int $now = null): array
{
    $config = $config ?? privateStoreConfig();
    $now = $now ?? time();
    $cfg = tenantNotificationConfigStatus($config);
    if (($cfg['enabled'] ?? false) !== true) return ['ok'=>(bool)($cfg['ok'] ?? false),'code'=>(string)($cfg['code'] ?? 'disabled'),'pending'=>0,'oldest_age'=>0];
    if (($cfg['ok'] ?? false) !== true) return ['ok'=>false,'code'=>(string)($cfg['code'] ?? 'config_invalid'),'pending'=>0,'oldest_age'=>0];

    $outbox = tenantNotificationOutboxLees();
    $pending = 0;
    $oudste = $now;
    foreach ($outbox['items'] as $item) {
        if (!is_array($item) || ($item['status'] ?? '') !== 'pending') continue;
        $pending++;
        $created = tenantNotificationTimestamp(isset($item['created_at']) ? (string)$item['created_at'] : null);
        if ($created > 0) $oudste = min($oudste, $created);
    }
    $age = $pending > 0 ? max(0, $now - $oudste) : 0;
    $stale = $pending > 0 && $age > (int)$cfg['stale_after_seconds'];
    return ['ok'=>!$stale,'code'=>$stale?'backlog_stale':'ready','pending'=>$pending,'oldest_age'=>$age];
}
