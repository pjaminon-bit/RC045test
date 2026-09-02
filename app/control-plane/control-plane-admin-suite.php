<?php
// Non-root control-plane admin suite. Reads only sanitized state exposed to the
// vst-control runtime and writes strictly validated requests to the existing queue.

require_once dirname(__DIR__) . '/deployment/control-plane-admin-suite-contract.php';

function cpSuitePaths(): array
{
    return control58StatePaths(cp51Config());
}

function cpSuiteReadJson(string $path): mixed
{
    if (!cp51Absoluut($path) || is_link($path) || !is_file($path) || !is_readable($path)) return null;
    $raw = @file_get_contents($path);
    if (!is_string($raw)) return null;
    try { return json_decode($raw, true, 128, JSON_THROW_ON_ERROR); }
    catch (Throwable $e) { return null; }
}

function cpSuiteRolesState(?string $operator = null): array
{
    $operator ??= cp51Operator();
    $path = cpSuitePaths()['roles_file'];
    if (!file_exists($path) && !is_link($path)) {
        return ['initialized'=>true,'valid'=>false,'state'=>'missing','role'=>'viewer','roles'=>[],'updated_at_utc'=>null];
    }
    $doc = control58RolesDocument(cpSuiteReadJson($path));
    if ($doc === null) return ['initialized'=>true,'valid'=>false,'state'=>'invalid','role'=>'viewer','roles'=>[],'updated_at_utc'=>null];
    return [
        'initialized'=>true,'valid'=>true,'state'=>'valid',
        'role'=>(string)($doc['roles'][$operator] ?? 'viewer'),
        'roles'=>$doc['roles'],'updated_at_utc'=>$doc['updated_at_utc'],
    ];
}

function cpSuiteRole(?string $operator = null): string
{
    return (string)cpSuiteRolesState($operator)['role'];
}

function cpSuiteCan(string $capability, ?string $operator = null): bool
{
    return control58RoleCan(cpSuiteRole($operator), $capability);
}

function cpSuiteRequire(string $capability): void
{
    if (!cpSuiteCan($capability)) throw new RuntimeException('Je platformrol staat deze beheeractie niet toe.');
}

function cpSuiteQueue(string $tenant, string $action, array $admin = []): string
{
    $operator = cp51Operator();
    if (($tenant !== 'platform' && preg_match('/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?$/D', $tenant) !== 1)
        || !in_array($action, control58PlatformActions(), true)) {
        throw new RuntimeException('Ongeldige platformbeheeractie.');
    }
    $request = [
        'schema'=>1,'phase'=>'5.1-request','request_id'=>bin2hex(random_bytes(16)),
        'tenant_key'=>$tenant,'action'=>$action,'operator'=>$operator,
        'requested_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'confirm'=>[],
    ];
    if ($admin !== []) $request['admin'] = $admin;
    return cp51QueueSchrijf($request);
}

function cpSuiteRefreshRequest(): string
{
    cpSuiteRequire('read');
    return cpSuiteQueue('platform', 'admin-refresh');
}

function cpSuiteRoleRequest(array $input): string
{
    cpSuiteRequire('roles');
    $target = trim((string)($input['target_operator'] ?? ''));
    $role = trim((string)($input['target_role'] ?? ''));
    if (!control58OperatorValid($target) || !in_array($role, control58Roles(), true)) throw new RuntimeException('Operator of rol is ongeldig.');
    return cpSuiteQueue('platform', 'operator-role-set', ['target_operator'=>$target,'role'=>$role]);
}

function cpSuiteScheduleRequest(array $input): string
{
    cpSuiteRequire('schedule');
    $tenant = trim((string)($input['tenant'] ?? ''));
    $action = trim((string)($input['scheduled_action'] ?? ''));
    $local = trim((string)($input['execute_at_local'] ?? ''));
    cp51TenantUitSnapshot($tenant);
    if (!in_array($action, control58ScheduleActions(), true)) throw new RuntimeException('Deze lifecycleactie mag niet vertraagd worden uitgevoerd.');
    try {
        $when = new DateTimeImmutable($local, new DateTimeZone('Europe/Amsterdam'));
    } catch (Throwable $e) {
        throw new RuntimeException('Uitvoermoment is ongeldig.');
    }
    $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam'));
    if ($when < $now->modify('+1 minute')) throw new RuntimeException('Plan minimaal één minuut in de toekomst.');
    if ($when > $now->modify('+366 days')) throw new RuntimeException('Een geplande actie mag maximaal 366 dagen vooruit staan.');
    return cpSuiteQueue($tenant, 'schedule-create', [
        'target_action'=>$action,
        'execute_at_utc'=>$when->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
    ]);
}

function cpSuiteScheduleCancelRequest(array $input): string
{
    cpSuiteRequire('schedule');
    $tenant = trim((string)($input['tenant'] ?? ''));
    $id = trim((string)($input['schedule_id'] ?? ''));
    if (preg_match('/^[0-9a-f]{32}$/D', $id) !== 1) throw new RuntimeException('Schedule-id is ongeldig.');
    cp51TenantUitSnapshot($tenant);
    return cpSuiteQueue($tenant, 'schedule-cancel', ['schedule_id'=>$id]);
}

function cpSuiteDiagnoseRequest(string $tenant): string
{
    cpSuiteRequire('diagnose');
    cp51TenantUitSnapshot($tenant);
    return cpSuiteQueue($tenant, 'diagnose');
}

function cpSuiteTlsRenewRequest(string $tenant): string
{
    cpSuiteRequire('tls');
    $snapshot = cp51TenantUitSnapshot($tenant);
    $tls = is_array($snapshot['tls'] ?? null) ? $snapshot['tls'] : [];
    $days = $tls['days_remaining'] ?? null;
    if (($tls['status'] ?? '') !== 'expiring' && !is_int($days)) throw new RuntimeException('TLS-vernieuwing is alleen beschikbaar voor een geldig certificaat dat de vernieuwingsperiode nadert.');
    if (is_int($days) && $days > 35) throw new RuntimeException('Certificaat is nog te lang geldig voor een veilige renew-run.');
    return cpSuiteQueue($tenant, 'tls-renew');
}

function cpSuiteSchedules(int $limit = 100): array
{
    $dir = cpSuitePaths()['schedules_dir'];
    if (!cp51Absoluut($dir) || is_link($dir) || !is_dir($dir) || !is_readable($dir)) return [];
    $files = glob($dir . '/*.json') ?: [];
    usort($files, static fn(string $a,string $b):int => ((int)@filemtime($b)) <=> ((int)@filemtime($a)));
    $out=[];
    foreach($files as$file){
        if(is_link($file)||!is_file($file)||preg_match('/^[0-9a-f]{32}\.json$/D',basename($file))!==1)continue;
        $doc=control58ScheduleDocument(cpSuiteReadJson($file));if($doc===null)continue;$out[]=$doc;if(count($out)>=max(1,min(250,$limit)))break;
    }
    return $out;
}

function cpSuiteAuditRows(int $limit = 250): array
{
    $doc = control58AuditDocument(cpSuiteReadJson(cpSuitePaths()['audit_view_file']));
    if ($doc === null) return [];
    return array_slice(array_reverse($doc['rows']), 0, max(1, min(500, $limit)));
}

function cpSuiteNotifications(array $snapshot, array $platform, array $schedules): array
{
    $items=[];
    foreach($platform['critical']??[]as$message)$items[]=['severity'=>'critical','title'=>'Platform','message'=>(string)$message,'tenant'=>null];
    foreach($platform['warnings']??[]as$message)$items[]=['severity'=>'warning','title'=>'Platform','message'=>(string)$message,'tenant'=>null];
    foreach($snapshot['tenants']??[]as$tenant){
        if(!is_array($tenant))continue;$key=(string)($tenant['tenant_key']??'');$status=(string)($tenant['status']??'');
        if($status==='active'&&($tenant['healthy']??false)!==true)$items[]=['severity'=>'critical','title'=>$key,'message'=>'Actieve tenant heeft geen actuele gezonde monitoringstatus.','tenant'=>$key];
        if(($tenant['transition']??null)!==null)$items[]=['severity'=>'critical','title'=>$key,'message'=>'Lifecycle-transition is nog niet afgerond.','tenant'=>$key];
        $tls=is_array($tenant['tls']??null)?$tenant['tls']:[];$days=$tls['days_remaining']??null;
        if($status==='active'&&($tls['status']??'')==='missing')$items[]=['severity'=>'critical','title'=>$key,'message'=>'Actieve tenant heeft geen leesbaar TLS-certificaat.','tenant'=>$key];
        elseif(is_int($days)&&$days<=7)$items[]=['severity'=>'critical','title'=>$key,'message'=>'TLS-certificaat verloopt binnen '.$days.' dag(en).','tenant'=>$key];
        elseif(is_int($days)&&$days<=30)$items[]=['severity'=>'warning','title'=>$key,'message'=>'TLS-certificaat verloopt binnen '.$days.' dag(en).','tenant'=>$key];
        $backup=is_array($tenant['backup']??null)?$tenant['backup']:[];$age=$backup['age_days']??null;
        if($status==='suspended'&&($backup['available']??false)!==true)$items[]=['severity'=>'warning','title'=>$key,'message'=>'Uitgeschakelde tenant heeft nog geen geverifieerde export.','tenant'=>$key];
        elseif(is_int($age)&&$age>=60)$items[]=['severity'=>'critical','title'=>$key,'message'=>'Laatste geverifieerde export is '.$age.' dagen oud.','tenant'=>$key];
        elseif(is_int($age)&&$age>=30)$items[]=['severity'=>'warning','title'=>$key,'message'=>'Laatste geverifieerde export is '.$age.' dagen oud.','tenant'=>$key];
        if($status==='setup_required')$items[]=['severity'=>'info','title'=>$key,'message'=>'Onboarding moet nog worden afgerond.','tenant'=>$key];
    }
    foreach($schedules as$s){if(($s['status']??'')==='failed')$items[]=['severity'=>'critical','title'=>(string)$s['tenant_key'],'message'=>'Geplande actie '.(string)$s['action'].' is mislukt.','tenant'=>(string)$s['tenant_key']];}
    $rank=['critical'=>0,'warning'=>1,'info'=>2];
    usort($items,static fn($a,$b)=>($rank[$a['severity']]??9)<=>($rank[$b['severity']]??9));
    return $items;
}

function cpSuiteOnboarding(array $tenant): array
{
    $o=is_array($tenant['onboarding']??null)?$tenant['onboarding']:[];
    $steps=is_array($o['steps']??null)?$o['steps']:[];
    $total=count($steps);$done=0;foreach($steps as$s)if(is_array($s)&&($s['done']??false)===true)$done++;
    return ['steps'=>$steps,'done'=>$done,'total'=>$total,'percent'=>$total>0?(int)round(($done/$total)*100):(($tenant['status']??'')==='active'?100:0)];
}
