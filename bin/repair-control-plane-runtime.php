<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Alleen via CLI beschikbaar.'); }

function cprStop(string $message, int $code = 1): never
{
    fwrite(STDERR, "FOUT: {$message}\n");
    exit($code);
}
function cprAbs(string $path): bool
{
    return str_starts_with($path, '/') && !str_contains($path, "\0") && preg_match('#(?:^|/)\.\.?(/|$)#', $path) !== 1;
}
function cprSymlinkInPad(string $path): ?string
{
    if (!cprAbs($path)) return $path;
    $parts = array_values(array_filter(explode('/', trim($path, '/')), static fn($v) => $v !== ''));
    $current = '';
    foreach ($parts as $part) {
        $current .= '/' . $part;
        if (is_link($current)) return $current;
        if (!file_exists($current)) break;
    }
    return null;
}
function cprUid(string|int $owner): int
{
    if (is_int($owner) || ctype_digit((string)$owner)) return (int)$owner;
    if (!function_exists('posix_getpwnam')) throw new RuntimeException('Ownercontrole vereist POSIX.');
    $u = @posix_getpwnam((string)$owner);
    if (!is_array($u)) throw new RuntimeException('Runtime-user bestaat niet: ' . $owner);
    return (int)$u['uid'];
}
function cprGid(string|int $group): int
{
    if (is_int($group) || ctype_digit((string)$group)) return (int)$group;
    if (!function_exists('posix_getgrnam')) throw new RuntimeException('Groepscontrole vereist POSIX.');
    $g = @posix_getgrnam((string)$group);
    if (!is_array($g)) throw new RuntimeException('Runtime-group bestaat niet: ' . $group);
    return (int)$g['gid'];
}
function cprMeta(string $path, int $mode, string|int $owner, string|int $group): void
{
    $s = @lstat($path);
    if (!is_array($s) || is_link($path) || !is_dir($path)) throw new RuntimeException('Runtimepad is geen veilige map: ' . $path);
    if ((int)$s['uid'] !== cprUid($owner) || (int)$s['gid'] !== cprGid($group) || (((int)$s['mode'] & 0777) !== $mode)) {
        throw new RuntimeException('Owner/group/mode wijkt af: ' . $path);
    }
}
function cprDir(string $path, int $mode, string|int $owner, string|int $group, bool $repair): void
{
    if (!cprAbs($path) || cprSymlinkInPad($path) !== null) throw new RuntimeException('Symlink of onveilig runtimepad geweigerd: ' . $path);
    if (!$repair) {
        cprMeta($path, $mode, $owner, $group);
        return;
    }
    if (!is_dir($path) && !@mkdir($path, $mode, false) && !is_dir($path)) throw new RuntimeException('Runtimepad kon niet worden aangemaakt: ' . $path);
    if (!@chown($path, $owner) || !@chgrp($path, $group) || !@chmod($path, $mode)) throw new RuntimeException('Runtimepadmetadata kon niet worden hersteld: ' . $path);
    clearstatcache(true, $path);
    cprMeta($path, $mode, $owner, $group);
}
function cprConfig(string $path): array
{
    if (!cprAbs($path) || is_link($path) || !is_file($path)) throw new RuntimeException('Runtimeconfig ontbreekt of is onveilig.');
    $raw = @file_get_contents($path);
    try { $c = is_string($raw) ? json_decode($raw, true, 64, JSON_THROW_ON_ERROR) : null; }
    catch (Throwable $e) { $c = null; }
    $keys = ['runtime_user','pending_dir','processing_dir','results_dir','sessions_dir','snapshot_file'];
    if (!is_array($c) || (int)($c['schema'] ?? 0) !== 1 || ($c['phase'] ?? '') !== '5.1-runtime') throw new RuntimeException('Runtimeconfig heeft onbekend schema.');
    foreach ($keys as $key) if (!isset($c[$key]) || !is_string($c[$key]) || $c[$key] === '') throw new RuntimeException('Runtimeconfig mist ' . $key . '.');
    foreach (['pending_dir','processing_dir','results_dir','sessions_dir','snapshot_file'] as $key) if (!cprAbs($c[$key])) throw new RuntimeException('Runtimeconfig bevat onveilig pad: ' . $key);

    $requests = dirname($c['pending_dir']);
    $state = dirname($requests);
    if (!hash_equals($requests, dirname($c['processing_dir']))) throw new RuntimeException('Pending en processing delen niet dezelfde requests-root.');
    if (!hash_equals($state, dirname($c['results_dir'])) || !hash_equals($state, dirname($c['sessions_dir'])) || !hash_equals($state, dirname($c['snapshot_file']))) {
        throw new RuntimeException('Control-plane statepaden delen niet dezelfde state-root.');
    }
    return $c + ['_requests_root'=>$requests, '_state_root'=>$state];
}

foreach ($_SERVER['argv'] ?? [] as $arg) {
    if (preg_match('/^--(?:password|pass|secret|token|credential|webhook)(?:=|$)/i', (string)$arg) === 1) cprStop('Secrets horen niet in repair-argumenten.');
}
$opt = getopt('', ['config:','check','repair','help']);
if (isset($opt['help'])) {
    echo "Gebruik:\n  sudo php bin/repair-control-plane-runtime.php --config=/etc/verenigingsplatform/control-plane/runtime.json --check\n  sudo php bin/repair-control-plane-runtime.php --config=/etc/verenigingsplatform/control-plane/runtime.json --repair\n";
    exit(0);
}
if ((isset($opt['check']) ? 1 : 0) + (isset($opt['repair']) ? 1 : 0) !== 1) cprStop('Kies exact --check of --repair.');
if (PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid') || posix_geteuid() !== 0) cprStop('Control-plane runtimecontrole vereist Linux root.', 77);

try {
    $config = trim((string)($opt['config'] ?? ''));
    if ($config === '') throw new RuntimeException('--config is verplicht.');
    $c = cprConfig($config);
    $repair = isset($opt['repair']);
    $runtime = $c['runtime_user'];

    cprDir($c['_state_root'], 0750, 0, $runtime, $repair);
    cprDir($c['_requests_root'], 0750, 0, $runtime, $repair);
    cprDir($c['pending_dir'], 0730, $runtime, $runtime, $repair);
    cprDir($c['processing_dir'], 0700, 0, 0, $repair);
    cprDir($c['results_dir'], 0750, 0, $runtime, $repair);
    cprDir($c['sessions_dir'], 0700, $runtime, $runtime, $repair);

    echo ($repair ? 'REPAIR OK' : 'CHECK OK') . " control-plane-runtime\n";
} catch (Throwable $e) {
    cprStop($e->getMessage());
}
