<?php
// ============================================================
// Fase 4.6 — privacybewuste operationele applicatielogging
// ============================================================
// Alleen externe VPS-tenants schrijven hier. Context is strikt allowlisted:
// geen request-URI/query, IP, user-agent, gebruiker, e-mail, cookie, sessie,
// token, password of vrije exceptiontekst wordt naar dit log geschreven.
// ============================================================

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
    if (is_link($map)) return false;
    if (!is_dir($map) && !@mkdir($map, 0750, true) && !is_dir($map)) return false;
    @chmod($map, 0750);
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
    $ok = @file_put_contents($pad, $json . "\n", FILE_APPEND | LOCK_EX) !== false;
    if ($ok) @chmod($pad, 0640);
    return $ok;
}

function vpOps46RegisterFatalLogger(array $config): void
{
    if (vpOps46ExternTenant($config) === null) return;
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
