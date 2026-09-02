<?php
// Control-plane admin suite — pure shared contract for roles, schedules and
// sanitized operational metadata. No process execution or privileged writes.

require_once __DIR__ . '/runtime-contract.php';

function control58OperatorValid(string $operator): bool
{
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9._@-]{1,63}$/D', $operator) === 1;
}

function control58Roles(): array
{
    return ['owner', 'operator', 'viewer'];
}

function control58RoleLabel(string $role): string
{
    return match ($role) {
        'owner' => 'Eigenaar',
        'operator' => 'Beheerder',
        'viewer' => 'Alleen lezen',
        default => 'Onbekend',
    };
}

function control58RoleCan(string $role, string $capability): bool
{
    if (!in_array($role, control58Roles(), true)) return false;
    if ($capability === 'read') return true;
    if ($role === 'owner') return in_array($capability, ['mutate','schedule','diagnose','tls','roles'], true);
    if ($role === 'operator') return in_array($capability, ['mutate','schedule','diagnose','tls'], true);
    return false;
}

function control58StatePaths(array $config): array
{
    $snapshot = (string)($config['snapshot_file'] ?? '');
    if (!runtime41IsAbsoluutPad($snapshot) || runtime41HeeftRelatieveSegmenten($snapshot)) {
        throw new RuntimeException('Control-plane snapshotpad is ongeldig voor admin-suite state.');
    }
    $root = runtime41NormPad(dirname($snapshot));
    if ($root === '/') throw new RuntimeException('Control-plane state-root mag niet /. zijn.');
    return [
        'root' => $root,
        'roles_file' => $root . '/operators.json',
        'roles_bootstrap_file' => $root . '/operators-bootstrap.json',
        'audit_view_file' => $root . '/audit-view.json',
        'schedules_dir' => $root . '/schedules',
    ];
}

function control58RolesDocument(mixed $input): ?array
{
    if (!is_array($input)
        || (int)($input['schema'] ?? 0) !== 1
        || ($input['phase'] ?? '') !== '5.8-operators'
        || !is_array($input['roles'] ?? null)
        || strtotime((string)($input['updated_at_utc'] ?? '')) === false) {
        return null;
    }
    $roles = [];
    foreach ($input['roles'] as $operator => $role) {
        if (!is_string($operator) || !control58OperatorValid($operator) || !is_string($role) || !in_array($role, control58Roles(), true)) return null;
        $roles[$operator] = $role;
    }
    ksort($roles, SORT_STRING);
    if ($roles !== [] && !in_array('owner', $roles, true)) return null;
    return ['schema'=>1,'phase'=>'5.8-operators','updated_at_utc'=>(string)$input['updated_at_utc'],'roles'=>$roles];
}

function control58InitialRolesDocument(array $users, string $owner, ?string $timestamp = null): array
{
    if (!control58OperatorValid($owner)) throw new RuntimeException('Bootstrap-owner is ongeldig.');
    $roles = [];
    foreach ($users as $user) {
        if (!is_string($user) || !control58OperatorValid($user)) throw new RuntimeException('Bootstrap bevat ongeldige operator.');
        $roles[$user] = 'viewer';
    }
    if (!array_key_exists($owner, $roles)) throw new RuntimeException('Bootstrap-owner staat niet in het Basic-Auth operatorbestand.');
    $roles[$owner] = 'owner';
    ksort($roles, SORT_STRING);
    $doc = [
        'schema'=>1,
        'phase'=>'5.8-operators',
        'updated_at_utc'=>$timestamp ?? gmdate('Y-m-d\TH:i:s\Z'),
        'roles'=>$roles,
    ];
    if (control58RolesDocument($doc) === null) throw new RuntimeException('Bootstrap-rollen konden niet veilig worden opgebouwd.');
    return $doc;
}

function control58RolesBootstrapDocument(mixed $input): ?array
{
    if (!is_array($input)
        || (int)($input['schema'] ?? 0) !== 1
        || ($input['phase'] ?? '') !== '5.8-roles-bootstrap'
        || !control58OperatorValid((string)($input['owner'] ?? ''))
        || strtotime((string)($input['initialized_at_utc'] ?? '')) === false
        || !is_int($input['recovery_count'] ?? null)
        || (int)$input['recovery_count'] < 0) {
        return null;
    }
    $recovered = $input['last_recovered_at_utc'] ?? null;
    if ($recovered !== null && (!is_string($recovered) || strtotime($recovered) === false)) return null;
    return [
        'schema'=>1,
        'phase'=>'5.8-roles-bootstrap',
        'owner'=>(string)$input['owner'],
        'initialized_at_utc'=>(string)$input['initialized_at_utc'],
        'recovery_count'=>(int)$input['recovery_count'],
        'last_recovered_at_utc'=>$recovered,
    ];
}

function control58ScheduleActions(): array
{
    // Destructive delete/purge are deliberately excluded from delayed execution.
    return ['suspend', 'activate', 'export', 'cancel-delete'];
}

function control58ScheduleDocument(mixed $input): ?array
{
    if (!is_array($input)
        || (int)($input['schema'] ?? 0) !== 1
        || ($input['phase'] ?? '') !== '5.8-schedule') return null;
    $id = (string)($input['schedule_id'] ?? '');
    $tenant = (string)($input['tenant_key'] ?? '');
    $operator = (string)($input['operator'] ?? '');
    $action = (string)($input['action'] ?? '');
    $status = (string)($input['status'] ?? '');
    $execute = (string)($input['execute_at_utc'] ?? '');
    if (preg_match('/^[0-9a-f]{32}$/D', $id) !== 1
        || !runtime41CanoniekeTenantKey($tenant)
        || !control58OperatorValid($operator)
        || !in_array($action, control58ScheduleActions(), true)
        || !in_array($status, ['scheduled','queued','completed','failed','cancelled'], true)
        || strtotime($execute) === false) return null;
    $requestId = $input['request_id'] ?? null;
    if ($requestId !== null && (!is_string($requestId) || preg_match('/^[0-9a-f]{32}$/D', $requestId) !== 1)) return null;
    $message = $input['message'] ?? null;
    if ($message !== null && (!is_string($message) || mb_strlen($message) > 500)) return null;
    return [
        'schema'=>1,'phase'=>'5.8-schedule','schedule_id'=>$id,'tenant_key'=>$tenant,
        'operator'=>$operator,'action'=>$action,'execute_at_utc'=>$execute,'status'=>$status,
        'request_id'=>$requestId,'message'=>$message,
    ];
}

function control58PlatformActions(): array
{
    return ['admin-refresh','operator-role-set','schedule-create','schedule-cancel','diagnose','tls-renew','onboarding-resume'];
}

function control58AuditDocument(mixed $input): ?array
{
    if (!is_array($input)
        || (int)($input['schema'] ?? 0) !== 1
        || ($input['phase'] ?? '') !== '5.8-audit-view'
        || strtotime((string)($input['generated_at_utc'] ?? '')) === false
        || !is_array($input['rows'] ?? null)) return null;
    $rows = [];
    foreach ($input['rows'] as $row) {
        if (!is_array($row)) return null;
        $ts=(string)($row['timestamp_utc']??'');$op=(string)($row['operator']??'');$tenant=(string)($row['tenant_key']??'');
        $action=(string)($row['action']??'');$result=(string)($row['result']??'');$message=(string)($row['message']??'');
        if (strtotime($ts)===false || !control58OperatorValid($op)
            || ($tenant !== 'platform' && !runtime41CanoniekeTenantKey($tenant))
            || !in_array($result,['ok','failed'],true) || mb_strlen($action)>64 || mb_strlen($message)>300) return null;
        $rows[]=['timestamp_utc'=>$ts,'operator'=>$op,'tenant_key'=>$tenant,'action'=>$action,'result'=>$result,'message'=>$message];
    }
    return ['schema'=>1,'phase'=>'5.8-audit-view','generated_at_utc'=>(string)$input['generated_at_utc'],'rows'=>$rows];
}

function control58Utc(string $value): string
{
    $ts = strtotime($value);
    if ($ts === false) throw new RuntimeException('UTC-tijdstip is ongeldig.');
    return gmdate('Y-m-d\TH:i:s\Z', $ts);
}
