<?php
// Fase 5.1 — niet-root control-plane webruntime.
// Deze laag leest alleen de veilige snapshot en schrijft strikt geschematiseerde
// verzoeken. Er staan bewust geen proc_open/exec/system/shell_exec-aanroepen in.

function cp51Fail(string $intern, int $status = 503): never
{
    error_log('[control-plane] ' . $intern);
    http_response_code($status);
    exit($status === 403 ? 'Toegang geweigerd.' : 'Platformbeheer tijdelijk niet beschikbaar.');
}

function cp51Absoluut(string $p): bool
{
    return str_starts_with($p, '/') && !str_contains($p, "\0") && !preg_match('#(?:^|/)\.\.?(/|$)#', $p);
}

function cp51Config(): array
{
    static $cfg = null;
    if (is_array($cfg)) return $cfg;
    $pad = getenv('VP_CONTROL_PLANE_CONFIG');
    if (!is_string($pad) || !cp51Absoluut($pad) || is_link($pad) || !is_file($pad)) cp51Fail('runtimeconfig ontbreekt of is onveilig');
    $raw = @file_get_contents($pad);
    try { $c = is_string($raw) ? json_decode($raw, true, 64, JSON_THROW_ON_ERROR) : null; }
    catch (Throwable $e) { $c = null; }
    $vereist = ['host','app_root','tenants_root','runtime_user','pending_dir','processing_dir','results_dir','sessions_dir','snapshot_file','executor_lock','audit_file','lifecycle_apply'];
    if (!is_array($c) || (int)($c['schema'] ?? 0) !== 1 || ($c['phase'] ?? '') !== '5.1-runtime') cp51Fail('runtimeconfig heeft onbekend schema');
    foreach ($vereist as $k) if (!isset($c[$k]) || !is_string($c[$k]) || $c[$k] === '') cp51Fail('runtimeconfig mist ' . $k);
    foreach (['app_root','tenants_root','pending_dir','processing_dir','results_dir','sessions_dir','snapshot_file','executor_lock','audit_file','lifecycle_apply'] as $k) {
        if (!cp51Absoluut($c[$k])) cp51Fail('onveilig runtimepad: ' . $k);
    }
    $cfg = $c;
    return $cfg;
}

function cp51Operator(): string
{
    $u = (string)($_SERVER['REMOTE_USER'] ?? '');
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._@-]{1,63}$/D', $u) !== 1) cp51Fail('REMOTE_USER ontbreekt of is ongeldig', 403);
    return $u;
}

function cp51SessionStart(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $c = cp51Config();
    $dir = $c['sessions_dir'];
    if (is_link($dir) || !is_dir($dir) || !is_writable($dir)) cp51Fail('sessiemap niet beschikbaar');
    session_name('vp_control');
    session_save_path($dir);
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    if (!session_start()) cp51Fail('sessie kon niet starten');
}

function cp51Csrf(): string
{
    cp51SessionStart();
    $t = $_SESSION['csrf'] ?? null;
    if (!is_string($t) || preg_match('/^[0-9a-f]{64}$/D', $t) !== 1) {
        $t = bin2hex(random_bytes(32));
        $_SESSION['csrf'] = $t;
    }
    return $t;
}

function cp51CsrfControle(string $token): void
{
    $verwacht = cp51Csrf();
    if (!preg_match('/^[0-9a-f]{64}$/D', $token) || !hash_equals($verwacht, $token)) cp51Fail('CSRF-controle faalde', 403);
}

function cp51Snapshot(): array
{
    $f = cp51Config()['snapshot_file'];
    if (is_link($f) || !is_file($f)) return ['generated_at_utc'=>null,'tenants'=>[]];
    $raw = @file_get_contents($f);
    try { $s = is_string($raw) ? json_decode($raw, true, 128, JSON_THROW_ON_ERROR) : null; }
    catch (Throwable $e) { $s = null; }
    if (!is_array($s) || (int)($s['schema'] ?? 0) !== 1 || ($s['phase'] ?? '') !== '5.1-snapshot' || !is_array($s['tenants'] ?? null)) {
        cp51Fail('snapshot ongeldig');
    }
    return $s;
}

function cp51TenantUitSnapshot(string $tenant): array
{
    if (preg_match('/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?$/D', $tenant) !== 1) throw new RuntimeException('Ongeldige tenant-key.');
    foreach (cp51Snapshot()['tenants'] as $t) {
        if (is_array($t) && hash_equals($tenant, (string)($t['tenant_key'] ?? ''))) return $t;
    }
    throw new RuntimeException('Tenant staat niet in de actuele platformstatus.');
}

function cp51ToegestaneActies(array $tenant): array
{
    $status = (string)($tenant['status'] ?? '');
    $transition = $tenant['transition'] ?? null;
    if ($transition !== null) return ['recover'];
    if ($status === 'unmanaged') return ['adopt-active'];
    if ($status === 'active') return ['suspend'];
    if ($status === 'suspended') {
        $a = ['activate','export'];
        if (is_array($tenant['last_export'] ?? null) && preg_match('/^[0-9a-f]{64}$/D', (string)($tenant['last_export']['sha256'] ?? ''))) $a[] = 'delete';
        return $a;
    }
    if ($status === 'pending_delete') {
        $a = ['cancel-delete'];
        $nb = strtotime((string)($tenant['purge_not_before_utc'] ?? ''));
        if ($nb !== false && time() >= $nb) $a[] = 'purge';
        return $a;
    }
    return [];
}

function cp51Request(string $tenantKey, string $actie, array $input): string
{
    $tenant = cp51TenantUitSnapshot($tenantKey);
    if (!in_array($actie, cp51ToegestaneActies($tenant), true)) throw new RuntimeException('Actie is niet toegestaan vanuit de actuele tenantstatus.');
    $operator = cp51Operator();
    $id = bin2hex(random_bytes(16));
    $r = [
        'schema'=>1,
        'phase'=>'5.1-request',
        'request_id'=>$id,
        'tenant_key'=>$tenantKey,
        'action'=>$actie,
        'operator'=>$operator,
        'requested_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),
        'confirm'=>[],
    ];
    if (in_array($actie, ['delete','purge'], true)) {
        $typed = trim((string)($input['confirm_tenant'] ?? ''));
        if (!hash_equals($tenantKey, $typed)) throw new RuntimeException('Typ de tenant-key exact ter bevestiging.');
        $sha = (string)($tenant['last_export']['sha256'] ?? $tenant['delete_export']['sha256'] ?? '');
        if (preg_match('/^[0-9a-f]{64}$/D', $sha) !== 1) throw new RuntimeException('Er is geen geldige geverifieerde export gekoppeld.');
        $r['confirm']['tenant'] = $tenantKey;
        $r['confirm']['export_sha256'] = $sha;
    }
    if ($actie === 'purge') {
        $zin = trim((string)($input['confirm_purge'] ?? ''));
        if (!hash_equals('VERWIJDER-DEFINITIEF', $zin)) throw new RuntimeException('Definitieve bevestiging is niet exact ingevoerd.');
        $r['confirm']['purge'] = $zin;
    }
    $json = json_encode($r, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) throw new RuntimeException('Aanvraag kon niet worden geserialiseerd.');
    $dir = cp51Config()['pending_dir'];
    if (is_link($dir) || !is_dir($dir) || !is_writable($dir)) throw new RuntimeException('Aanvraagqueue is niet schrijfbaar.');
    $pad = $dir . '/' . $id . '.json';
    $h = @fopen($pad, 'x');
    if (!is_resource($h)) throw new RuntimeException('Aanvraag kon niet exclusief worden aangemaakt.');
    try {
        if (!flock($h, LOCK_EX) || fwrite($h, $json . "\n") === false || !fflush($h)) throw new RuntimeException('Aanvraagwrite faalde.');
    } finally { fclose($h); }
    @chmod($pad, 0640);
    return $id;
}

function cp51RecentResult(string $requestId, ?string $operator = null): ?array
{
    if (preg_match('/^[0-9a-f]{32}$/D', $requestId) !== 1) return null;
    $operator ??= cp51Operator();
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._@-]{1,63}$/D', $operator) !== 1) return null;
    $f = cp51Config()['results_dir'] . '/' . $requestId . '.json';
    if (is_link($f) || !is_file($f)) return null;
    $raw = @file_get_contents($f);
    try { $r = is_string($raw) ? json_decode($raw, true, 32, JSON_THROW_ON_ERROR) : null; }
    catch (Throwable $e) { $r = null; }
    $acties = ['adopt-active','suspend','activate','recover','export','delete','cancel-delete','purge'];
    if (!is_array($r)
        || (int)($r['schema'] ?? 0) !== 1
        || ($r['phase'] ?? '') !== '5.1-result'
        || !hash_equals($requestId, (string)($r['request_id'] ?? ''))
        || !hash_equals($operator, (string)($r['operator'] ?? ''))
        || preg_match('/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?$/D', (string)($r['tenant_key'] ?? '')) !== 1
        || !in_array((string)($r['action'] ?? ''), $acties, true)
        || !in_array((string)($r['result'] ?? ''), ['ok','failed'], true)
        || !is_string($r['message'] ?? null)
        || mb_strlen((string)$r['message']) > 500
        || strtotime((string)($r['completed_at_utc'] ?? '')) === false) {
        return null;
    }
    return [
        'request_id'=>$requestId,
        'tenant_key'=>(string)$r['tenant_key'],
        'action'=>(string)$r['action'],
        'operator'=>(string)$r['operator'],
        'result'=>(string)$r['result'],
        'message'=>(string)$r['message'],
        'completed_at_utc'=>(string)$r['completed_at_utc'],
    ];
}
