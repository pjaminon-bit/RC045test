<?php
// ============================================================
// Fase 4.6 — monitoring & logging contract
// ============================================================
// Pure helpers: binden fase 4.6 byte-exact aan TLS 4.4 + database 4.5.
// Geen root-acties, service-reloads of alertdelivery in deze laag.
// ============================================================

require_once __DIR__ . '/tls-contract.php';
require_once __DIR__ . '/database-contract.php';

function monitoring46Context(string $tlsPlanPad, string $databasePlanPad): array
{
    $tlsCtx = tls44PlanLeesEnValideer($tlsPlanPad, false);
    $dbCtx = database45PlanLeesEnValideer($databasePlanPad);
    $tls = $tlsCtx['plan'];
    $db = $dbCtx['plan'];
    $tenant = (string)($tls['tenant_key'] ?? '');
    if (!hash_equals($tenant, (string)($db['tenant_key'] ?? ''))) {
        throw new RuntimeException('TLS- en databaseplan horen niet bij dezelfde tenant.');
    }
    $tlsRuntime = (string)($tlsCtx['context']['web']['source']['runtime_plan_file'] ?? '');
    $dbRuntime = (string)($db['source']['runtime_plan_file'] ?? '');
    if ($tlsRuntime === '' || !hash_equals(runtime41NormPad($tlsRuntime), runtime41NormPad($dbRuntime))) {
        throw new RuntimeException('TLS en database zijn niet aan hetzelfde runtimeplan gebonden.');
    }
    $runtimeCtx = runtime41PlanLeesEnValideer($dbRuntime);
    $runtime = $runtimeCtx['plan'];
    $host = (string)($tls['canonical_host'] ?? '');
    if (!hash_equals($host, (string)($tlsCtx['context']['host'] ?? ''))) {
        throw new RuntimeException('Canonical host in TLS-context wijkt af.');
    }
    $tlsRaw = @file_get_contents($tlsCtx['path']);
    $dbRaw = @file_get_contents($dbCtx['path']);
    $runtimeRaw = @file_get_contents($runtimeCtx['path']);
    if (!is_string($tlsRaw) || !is_string($dbRaw) || !is_string($runtimeRaw)) {
        throw new RuntimeException('Fase-4.6 bronplan kon niet byte-exact worden gelezen.');
    }
    return [
        'tenant_key' => $tenant,
        'canonical_host' => $host,
        'tenant_root' => (string)$runtime['filesystem']['tenant_root']['path'],
        'private_root' => (string)$runtime['filesystem']['private_root']['path'],
        'app_root' => (string)$runtime['filesystem']['shared_code']['path'],
        'runtime_user' => (string)$runtime['os']['user'],
        'runtime_group' => (string)$runtime['os']['group'],
        'php_version' => (string)$runtime['settings']['php_version'],
        'pool' => (string)$runtime['php_fpm']['pool'],
        'socket' => (string)$runtime['php_fpm']['socket'],
        'runtime_plan_path' => $runtimeCtx['path'],
        'runtime_plan_sha256' => hash('sha256', $runtimeRaw),
        'tls_plan_path' => $tlsCtx['path'],
        'tls_plan_sha256' => hash('sha256', $tlsRaw),
        'database_plan_path' => $dbCtx['path'],
        'database_plan_sha256' => hash('sha256', $dbRaw),
        'certificate_fullchain' => (string)$tls['certificate']['fullchain'],
        'certificate_privkey' => (string)$tls['certificate']['privkey'],
        'tenant_https_filename' => (string)$tls['apache']['tenant_https_filename'],
        'database' => (string)$db['isolation']['database'],
        'database_user' => (string)$db['isolation']['app_role'],
    ];
}

function monitoring46OutputDir(string $tenantRoot): string
{
    $pad = runtime41NormPad($tenantRoot . '/monitoring');
    if (!runtime41Binnen($pad, $tenantRoot) || $pad === runtime41NormPad($tenantRoot)) {
        throw new RuntimeException('Monitoringbundle valt niet veilig binnen de tenantroot.');
    }
    $link = runtime41SymlinkInPad($pad);
    if ($link !== null) throw new RuntimeException("Monitoringbundle mag geen symlink bevatten: {$link}");
    return $pad;
}

function monitoring46Plan(array $context): array
{
    $tenant = $context['tenant_key'];
    $output = monitoring46OutputDir($context['tenant_root']);
    $php = $context['php_version'];
    if (!runtime41PhpVersie($php)) throw new RuntimeException('Monitoring vereist een geldige runtime PHP-versie.');
    $fpmPoolFile = '/etc/php/' . $php . '/fpm/pool.d/' . $context['pool'] . '.conf';
    $service = 'verenigingsplatform-health-' . $tenant . '.service';
    $timer = 'verenigingsplatform-health-' . $tenant . '.timer';
    $privateMonitoring = $context['private_root'] . '/monitoring';

    return [
        'schema' => 1,
        'phase' => '4.6',
        'tenant_key' => $tenant,
        'canonical_host' => $context['canonical_host'],
        'source' => [
            'runtime_plan_file' => $context['runtime_plan_path'],
            'runtime_plan_sha256' => $context['runtime_plan_sha256'],
            'tls_plan_file' => $context['tls_plan_path'],
            'tls_plan_sha256' => $context['tls_plan_sha256'],
            'database_plan_file' => $context['database_plan_path'],
            'database_plan_sha256' => $context['database_plan_sha256'],
        ],
        'health' => [
            'public_path' => '/healthz.php',
            'public_url' => 'https://' . $context['canonical_host'] . '/healthz.php',
            'success_http_status' => 204,
            'failure_http_status' => 503,
            'standalone_http_status' => 404,
            'local_resolve_address' => '127.0.0.1',
            'interval_seconds' => 60,
            'timeout_seconds' => 15,
            'certificate_warning_seconds' => 1209600,
            'disk_minimum_free_percent' => 10,
            'disk_minimum_free_bytes' => 536870912,
        ],
        'runtime' => [
            'user' => $context['runtime_user'],
            'group' => $context['runtime_group'],
            'php_version' => $php,
            'fpm_service' => 'php' . $php . '-fpm.service',
            'fpm_binary' => '/usr/sbin/php-fpm' . $php,
            'fpm_pool' => $context['pool'],
            'fpm_socket' => $context['socket'],
            'fpm_pool_file' => $fpmPoolFile,
            'apache_service' => 'apache2.service',
            'postgresql_service' => 'postgresql.service',
            'tenant_https_available' => '/etc/apache2/sites-available/' . $context['tenant_https_filename'],
            'tenant_https_enabled' => '/etc/apache2/sites-enabled/' . $context['tenant_https_filename'],
        ],
        'database' => [
            'database' => $context['database'],
            'user' => $context['database_user'],
            'peer_only' => true,
        ],
        'certificate' => [
            'fullchain' => $context['certificate_fullchain'],
            'private_key' => $context['certificate_privkey'],
        ],
        'logging' => [
            'root' => '/var/log/verenigingsplatform',
            'apache_access' => '/var/log/verenigingsplatform/apache-access.log',
            'apache_error' => '/var/log/verenigingsplatform/apache-error.log',
            'fpm_dir' => '/var/log/verenigingsplatform/fpm',
            'fpm_access' => '/var/log/verenigingsplatform/fpm/' . $tenant . '.access.log',
            'fpm_error' => '/var/log/verenigingsplatform/fpm/' . $tenant . '.php-error.log',
            'fpm_slow' => '/var/log/verenigingsplatform/fpm/' . $tenant . '.slow.log',
            'app_dir' => $privateMonitoring,
            'app_operations' => $privateMonitoring . '/operations.jsonl',
            'health_status' => '/var/lib/verenigingsplatform/monitoring/' . $tenant . '-health.json',
            'retention_days' => 14,
            'apache_access_excludes_ip' => true,
            'apache_access_excludes_path' => true,
            'apache_access_excludes_query' => true,
            'apache_access_excludes_user_agent' => true,
            'apache_access_excludes_referrer' => true,
            'apache_access_excludes_auth_and_cookies' => true,
        ],
        'alerts' => [
            'adapter' => '/etc/verenigingsplatform/monitoring/alert-command',
            'adapter_must_be_root_owned' => true,
            'adapter_must_not_be_group_or_world_writable' => true,
            'payload_via_stdin' => true,
            'secret_in_plan_forbidden' => true,
            'reminder_seconds' => 3600,
            'state_file' => '/var/lib/verenigingsplatform/monitoring/' . $tenant . '-alert.json',
            'alert_on_failure_transition' => true,
            'alert_on_recovery_transition' => true,
        ],
        'systemd' => [
            'service_filename' => $service,
            'timer_filename' => $timer,
            'unit_dir' => '/etc/systemd/system',
        ],
        'apache' => [
            'config_available' => '/etc/apache2/conf-available/90-verenigingsplatform-monitoring.conf',
            'config_enabled' => '/etc/apache2/conf-enabled/90-verenigingsplatform-monitoring.conf',
            'control_binary' => '/usr/sbin/apache2ctl',
        ],
        'logrotate' => [
            'global_file' => '/etc/logrotate.d/verenigingsplatform-apache',
            'tenant_file' => '/etc/logrotate.d/verenigingsplatform-' . $tenant,
        ],
        'bundle' => [
            'output_dir' => $output,
            'plan_file' => $output . '/monitoring-plan.json',
            'apache_config' => $output . '/90-verenigingsplatform-monitoring.conf',
            'fpm_fragment' => $output . '/fpm-monitoring.inc.conf',
            'systemd_service' => $output . '/' . $service,
            'systemd_timer' => $output . '/' . $timer,
            'logrotate_global' => $output . '/verenigingsplatform-apache.logrotate',
            'logrotate_tenant' => $output . '/verenigingsplatform-' . $tenant . '.logrotate',
        ],
        'security' => [
            'no_secrets_in_bundle' => true,
            'health_endpoint_discloses_no_tenant_identity' => true,
            'health_endpoint_discloses_no_failure_detail' => true,
            'monitoring_paths_outside_document_root' => true,
            'alert_adapter_outside_git' => true,
            'raw_request_headers_forbidden_in_platform_access_log' => true,
        ],
    ];
}

function monitoring46Json(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($json)) throw new RuntimeException('Monitoringcontract kon niet als JSON worden opgebouwd.');
    return $json . "\n";
}

function monitoring46ApacheConfig(array $plan): string
{
    return implode("\n", [
        '# Fase 4.6 — platformbrede, privacybewuste Apache logging.',
        '# Geen client-IP, querystring, referrer, user-agent, cookies of Authorization in de accesslog.',
        'LogFormat "%{%Y-%m-%dT%H:%M:%S%z}t\\t%v\\t%m\\t%>s\\t%B\\t%D" vp_safe',
        'CustomLog "' . $plan['logging']['apache_access'] . '" vp_safe',
        'ErrorLog "' . $plan['logging']['apache_error'] . '"',
        'ErrorLogFormat "[%{cu}t] [%-m:%l] [vhost %V] [pid %P] %E: %M"',
        'LogLevel warn',
        '',
    ]);
}

function monitoring46FpmFragment(array $plan): string
{
    return implode("\n", [
        '; BEGIN verenigingsplatform fase 4.6 ' . $plan['tenant_key'],
        '; Privacybewuste FPM logging: geen IP, querystring, user-agent of request headers.',
        'access.log = ' . $plan['logging']['fpm_access'],
        'access.format = "%t %m %s %{milli}d"',
        'slowlog = ' . $plan['logging']['fpm_slow'],
        'request_slowlog_timeout = 5s',
        'request_slowlog_trace_depth = 20',
        'php_admin_flag[log_errors] = on',
        'php_admin_flag[display_errors] = off',
        'php_admin_value[error_log] = ' . $plan['logging']['fpm_error'],
        '; END verenigingsplatform fase 4.6 ' . $plan['tenant_key'],
        '',
    ]);
}

function monitoring46SystemdService(array $plan): string
{
    $app = $plan['source']['runtime_plan_file'];
    $appRoot = runtime41PlanLeesEnValideer($app)['plan']['filesystem']['shared_code']['path'];
    return implode("\n", [
        '[Unit]',
        'Description=Verenigingsplatform healthcheck ' . $plan['tenant_key'],
        'After=network-online.target apache2.service ' . $plan['runtime']['fpm_service'] . ' postgresql.service',
        '',
        '[Service]',
        'Type=oneshot',
        'ExecStart=/usr/bin/php ' . $appRoot . '/bin/check-vps-health.php --monitoring-plan=' . $plan['bundle']['plan_file'] . ' --probe --write-status --alert',
        'NoNewPrivileges=true',
        'PrivateTmp=true',
        'ProtectHome=true',
        'ProtectSystem=strict',
        'ReadWritePaths=' . $plan['logging']['app_dir'] . ' /var/lib/verenigingsplatform/monitoring',
        '',
    ]);
}

function monitoring46SystemdTimer(array $plan): string
{
    return implode("\n", [
        '[Unit]',
        'Description=Verenigingsplatform health timer ' . $plan['tenant_key'],
        '',
        '[Timer]',
        'OnCalendar=minutely',
        'AccuracySec=1s',
        'RandomizedDelaySec=5s',
        'Persistent=true',
        'Unit=' . $plan['systemd']['service_filename'],
        '',
        '[Install]',
        'WantedBy=timers.target',
        '',
    ]);
}

function monitoring46LogrotateGlobal(array $plan): string
{
    return implode("\n", [
        $plan['logging']['apache_access'] . ' ' . $plan['logging']['apache_error'] . ' {',
        '    daily',
        '    rotate ' . (int)$plan['logging']['retention_days'],
        '    compress',
        '    delaycompress',
        '    missingok',
        '    notifempty',
        '    copytruncate',
        '    maxsize 50M',
        '}',
        '',
    ]);
}

function monitoring46LogrotateTenant(array $plan): string
{
    return implode("\n", [
        $plan['logging']['fpm_access'] . ' ' . $plan['logging']['fpm_error'] . ' ' . $plan['logging']['fpm_slow'] . ' ' . $plan['logging']['app_operations'] . ' {',
        '    daily',
        '    rotate ' . (int)$plan['logging']['retention_days'],
        '    compress',
        '    delaycompress',
        '    missingok',
        '    notifempty',
        '    copytruncate',
        '    maxsize 25M',
        '}',
        '',
    ]);
}

function monitoring46Artifacts(array $plan): array
{
    return [
        $plan['bundle']['apache_config'] => monitoring46ApacheConfig($plan),
        $plan['bundle']['fpm_fragment'] => monitoring46FpmFragment($plan),
        $plan['bundle']['systemd_service'] => monitoring46SystemdService($plan),
        $plan['bundle']['systemd_timer'] => monitoring46SystemdTimer($plan),
        $plan['bundle']['logrotate_global'] => monitoring46LogrotateGlobal($plan),
        $plan['bundle']['logrotate_tenant'] => monitoring46LogrotateTenant($plan),
    ];
}

function monitoring46PlanLeesEnValideer(string $planPad): array
{
    $planPad = runtime41BestaandPad($planPad, 'monitoring-plan.json');
    $raw = @file_get_contents($planPad);
    if (!is_string($raw)) throw new RuntimeException('monitoring-plan.json kon niet worden gelezen.');
    try { $plan = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new RuntimeException('monitoring-plan.json bevat ongeldige JSON.'); }
    if (!is_array($plan) || (int)($plan['schema'] ?? 0) !== 1 || ($plan['phase'] ?? '') !== '4.6') {
        throw new RuntimeException('monitoring-plan.json heeft een onbekend schema/fase.');
    }
    $context = monitoring46Context((string)($plan['source']['tls_plan_file'] ?? ''), (string)($plan['source']['database_plan_file'] ?? ''));
    $verwacht = monitoring46Plan($context);
    if (!hash_equals(monitoring46Json($verwacht), monitoring46Json($plan))) {
        throw new RuntimeException('monitoring-plan.json wijkt af van het actuele 4.4/4.5/runtimecontract.');
    }
    if (!hash_equals(runtime41NormPad(dirname($planPad)), runtime41NormPad($plan['bundle']['output_dir']))) {
        throw new RuntimeException('monitoring-plan.json staat niet in de vaste tenant monitoringbundle.');
    }
    foreach (monitoring46Artifacts($plan) as $pad => $verwachteInhoud) {
        $real = runtime41BestaandPad($pad, 'monitoringartifact');
        $inhoud = @file_get_contents($real);
        if (!is_string($inhoud) || !hash_equals(hash('sha256', $verwachteInhoud), hash('sha256', $inhoud))) {
            throw new RuntimeException('Monitoringartifact wijkt af van monitoring-plan.json.');
        }
    }
    return ['path' => $planPad, 'sha256' => hash('sha256', $raw), 'plan' => $plan, 'context' => $context];
}
