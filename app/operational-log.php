<?php
// ============================================================
// Fase 4.6 — privacybewuste operationele applicatielogging
// ============================================================
// Alleen externe VPS-tenants schrijven hier. Context is strikt allowlisted:
// geen request-URI/query, IP, user-agent, gebruiker, e-mail, cookie, sessie,
// token, password of vrije exceptiontekst wordt naar dit log geschreven.
// ============================================================
require_once __DIR__ . '/storage/private-filesystem.php';

function vpOps46ExternTenant(array $config): ?array
{
    $extern = trim((string)(getenv('VERENIGING_CONFIG_FILE') ?: ''));
    $private = trim((string)($config['opslag']['private_root'] ?? ''));
    $tenant = trim((string)($config['vereniging']['sleutel'] ?? ''));
    if ($extern === '' || $private === '' || $tenant === '') return null;
    if (!str_starts_with($private, '/') || str_contains($private, "\0")) return null;
    if (preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D', $tenant) !== 1) return null;
    return ['tenant_key' => $tenant, 'private_root' => rtrim($private, '/')];
}

function vpOps46SafeContext(array $context): array
{
    $toegestaan = ['component', 'check', 'state', 'code', 'error_class', 'script', 'line', 'count', 'duration_ms'];
    $uit = [];
    foreach ($toegestaan as $sleutel) {
        if (!array_key_exists($sleutel, $context)) continue;
        $waarde = $context[$sleutel];
        if (is_bool($waarde) || is_int($waarde) || is_float($waarde)) {
            $uit[$sleutel] = $waarde;
            continue;
        }
        if (!is_string($waarde)) continue;
        $waarde = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $waarde) ?? '';
        $uit[$sleutel] = function_exists('mb_substr') ? mb_substr($waarde, 0, 120) : substr($waarde, 0, 120);
    }
    return $uit;
}

function vpOps46Log(array $config, string $event, string $level = 'info', array $context = []): bool
{
    $tenant = vpOps46ExternTenant($config);
    if ($tenant === null) return false;
    if (preg_match('/^[a-z][a-z0-9_.-]{1,63}$/D', $event) !== 1) return false;
    if (!in_array($level, ['info', 'warning', 'error'], true)) $level = 'error';

    $map = $tenant['private_root'] . '/monitoring';
    if (!privateFilesystemBeveiligMap($map, true)) return false;
    $pad = $map . '/operations.jsonl';
    if (is_link($pad)) return false;

    $regel = [
        'ts' => gmdate('Y-m-d\TH:i:s\Z'),
        'tenant_key' => $tenant['tenant_key'],
        'level' => $level,
        'event' => $event,
        'context' => vpOps46SafeContext($context),
    ];
    $json = json_encode($regel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) return false;

    // Open eerst, dwing daarna de private eindmode af en schrijf pas vervolgens
    // bytes. Zo kan een permissieve umask of chmod-failure nooit eerst data in
    // een onbewezen bestand publiceren.
    $h = @fopen($pad, 'ab');
    if (!is_resource($h)) return false;
    $ok = false;
    try {
        if (!privateFilesystemBeveiligBestand($pad, 0640)) return false;
        if (!flock($h, LOCK_EX)) return false;
        $regelBytes = $json . "\n";
        $geschreven = fwrite($h, $regelBytes);
        if ($geschreven !== strlen($regelBytes) || !fflush($h)) return false;
        $ok = privateFilesystemBeveiligBestand($pad, 0640);
        return $ok;
    } finally {
        @flock($h, LOCK_UN);
        fclose($h);
    }
}

function vpOps46RegisterFatalLogger(array $config): void
{
    if (PHP_SAPI === 'cli' || vpOps46ExternTenant($config) === null) return;
    static $geregistreerd = false;
    if ($geregistreerd) return;
    $geregistreerd = true;
    register_shutdown_function(static function () use ($config): void {
        $fout = error_get_last();
        if (!is_array($fout)) return;
        $fataleTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if (!in_array((int)($fout['type'] ?? 0), $fataleTypes, true)) return;
        vpOps46Log($config, 'php_fatal', 'error', [
            'component' => 'php',
            'code' => (int)($fout['type'] ?? 0),
            'script' => basename((string)($fout['file'] ?? 'unknown')),
            'line' => (int)($fout['line'] ?? 0),
        ]);
    });
}
