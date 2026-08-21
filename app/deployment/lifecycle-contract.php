<?php
// ============================================================
// Fase 4.8 — tenant lifecycle contract
// ============================================================
// Pure helpers. De root-laag zit in bin/apply-vps-lifecycle.php.
// Lifecycle wordt uitsluitend opgebouwd uit een volledig gevalideerd 4.6-plan,
// en is daarmee transitief gebonden aan runtime, web, TLS en database.
// ============================================================
require_once __DIR__ . '/monitoring-contract.php';

function lifecycle48Context(string $monitoringPlanPad): array
{
    $mctx = monitoring46PlanLeesEnValideer($monitoringPlanPad);
    $monitoring = $mctx['plan'];
    $c = $mctx['context'];
    $tenant = (string)$monitoring['tenant_key'];
    if (!runtime41CanoniekeTenantKey($tenant)) throw new RuntimeException('Monitoringplan bevat geen canonieke tenant-key.');

    $runtimeCtx = runtime41PlanLeesEnValideer((string)$monitoring['source']['runtime_plan_file']);
    $runtime = $runtimeCtx['plan'];
    $tlsCtx = tls44PlanLeesEnValideer((string)$monitoring['source']['tls_plan_file'], false);
    $tls = $tlsCtx['plan'];
    $dbCtx = database45PlanLeesEnValideer((string)$monitoring['source']['database_plan_file']);
    $db = $dbCtx['plan'];
    foreach ([$runtime['tenant_key'] ?? '', $tls['tenant_key'] ?? '', $db['tenant_key'] ?? ''] as $key) {
        if (!hash_equals($tenant, (string)$key)) throw new RuntimeException('Lifecyclebronnen horen niet bij dezelfde tenant.');
    }

    $tenantRoot = (string)$c['tenant_root'];
    $php = (string)$c['php_version'];
    $pool = (string)$c['pool'];
    if (!runtime41PhpVersie($php) || $pool === '') throw new RuntimeException('Lifecyclebron bevat geen geldige PHP-FPM identiteit.');

    $monitoringRaw = @file_get_contents($mctx['path']);
    $runtimeRaw = @file_get_contents($runtimeCtx['path']);
    $tlsRaw = @file_get_contents($tlsCtx['path']);
    $dbRaw = @file_get_contents($dbCtx['path']);
    if (!is_string($monitoringRaw) || !is_string($runtimeRaw) || !is_string($tlsRaw) || !is_string($dbRaw)) {
        throw new RuntimeException('Lifecyclebron kon niet byte-exact worden gelezen.');
    }

    return [
        'tenant_key' => $tenant,
        'canonical_host' => (string)$monitoring['canonical_host'],
        'tenant_root' => $tenantRoot,
        'private_root' => (string)$c['private_root'],
        'app_root' => (string)$c['app_root'],
        'runtime_user' => (string)$c['runtime_user'],
        'runtime_group' => (string)$c['runtime_group'],
        'php_version' => $php,
        'pool' => $pool,
        'socket' => (string)$c['socket'],
        'runtime_plan_path' => $runtimeCtx['path'],
        'runtime_plan_sha256' => hash('sha256', $runtimeRaw),
        'runtime_pool_bundle' => (string)$runtime['bundle']['php_fpm_file'],
        'monitoring_plan_path' => $mctx['path'],
        'monitoring_plan_sha256' => hash('sha256', $monitoringRaw),
        'monitoring' => $monitoring,
        'tls_plan_path' => $tlsCtx['path'],
        'tls_plan_sha256' => hash('sha256', $tlsRaw),
        'tls' => $tls,
        'database_plan_path' => $dbCtx['path'],
        'database_plan_sha256' => hash('sha256', $dbRaw),
        'database' => $db,
    ];
}

function lifecycle48OutputDir(string $tenantRoot): string
{
    $pad = runtime41NormPad($tenantRoot . '/lifecycle');
    if (!runtime41Binnen($pad, $tenantRoot) || $pad === runtime41NormPad($tenantRoot)) throw new RuntimeException('Lifecyclebundle valt niet veilig binnen tenantroot.');
    $link = runtime41SymlinkInPad($pad);
    if ($link !== null) throw new RuntimeException('Lifecyclebundle mag geen symlink bevatten: ' . $link);
    return $pad;
}

function lifecycle48Plan(array $c): array
{
    $tenant = $c['tenant_key'];
    $out = lifecycle48OutputDir($c['tenant_root']);
    $tls = $c['tls'];
    $db = $c['database'];
    $monitoring = $c['monitoring'];
    $php = $c['php_version'];
    $pool = $c['pool'];
    $http = (string)$tls['apache']['tenant_http_filename'];
    $https = (string)$tls['apache']['tenant_https_filename'];
    $fpmInstalled = '/etc/php/' . $php . '/fpm/pool.d/' . basename((string)$c['runtime_pool_bundle']);
    $stateDir = '/var/lib/verenigingsplatform/lifecycle';
    $exportRoot = '/var/backups/verenigingsplatform/tenants/' . $tenant;

    return [
        'schema' => 1,
        'phase' => '4.8',
        'tenant_key' => $tenant,
        'canonical_host' => $c['canonical_host'],
        'source' => [
            'monitoring_plan_file' => $c['monitoring_plan_path'],
            'monitoring_plan_sha256' => $c['monitoring_plan_sha256'],
            'runtime_plan_file' => $c['runtime_plan_path'],
            'runtime_plan_sha256' => $c['runtime_plan_sha256'],
            'tls_plan_file' => $c['tls_plan_path'],
            'tls_plan_sha256' => $c['tls_plan_sha256'],
            'database_plan_file' => $c['database_plan_path'],
            'database_plan_sha256' => $c['database_plan_sha256'],
        ],
        'runtime' => [
            'user' => $c['runtime_user'],
            'group' => $c['runtime_group'],
            'php_version' => $php,
            'php_binary' => '/usr/bin/php' . $php,
            'app_root' => $c['app_root'],
            'fpm_service' => 'php' . $php . '-fpm.service',
            'fpm_test_binary' => '/usr/sbin/php-fpm' . $php,
            'pool' => $pool,
            'socket' => $c['socket'],
            'pool_bundle' => $c['runtime_pool_bundle'],
            'pool_installed' => $fpmInstalled,
        ],
        'apache' => [
            'control_binary' => '/usr/sbin/apache2ctl',
            'sites_available_dir' => '/etc/apache2/sites-available',
            'sites_enabled_dir' => '/etc/apache2/sites-enabled',
            'tenant_http_bundle' => (string)$tls['bundle']['tenant_http'],
            'tenant_https_bundle' => (string)$tls['bundle']['tenant_https'],
            'tenant_http_available' => '/etc/apache2/sites-available/' . $http,
            'tenant_http_enabled' => '/etc/apache2/sites-enabled/' . $http,
            'tenant_https_available' => '/etc/apache2/sites-available/' . $https,
            'tenant_https_enabled' => '/etc/apache2/sites-enabled/' . $https,
            'routing_fragment' => (string)$tls['apache']['routing_fragment_installed'],
        ],
        'database' => [
            'plan_file' => $c['database_plan_path'],
            'database' => (string)$db['isolation']['database'],
            'owner_role' => (string)$db['isolation']['owner_role'],
            'app_role' => (string)$db['isolation']['app_role'],
            'marker' => database45Marker($tenant),
            'hba_file' => rtrim((string)$db['postgresql']['hba_include_dir'], '/') . '/' . (string)$db['postgresql']['tenant_hba_filename'],
            'admin_os_user' => 'postgres',
            'socket_dir' => (string)$db['connection']['unix_socket_dir'],
        ],
        'monitoring' => [
            'plan_file' => $c['monitoring_plan_path'],
            'timer_unit' => (string)$monitoring['systemd']['timer_filename'],
            'service_unit' => (string)$monitoring['systemd']['service_filename'],
            'timer_file' => (string)$monitoring['systemd']['unit_dir'] . '/' . (string)$monitoring['systemd']['timer_filename'],
            'service_file' => (string)$monitoring['systemd']['unit_dir'] . '/' . (string)$monitoring['systemd']['service_filename'],
            'tenant_logrotate' => (string)$monitoring['logrotate']['tenant_file'],
            'health_status' => (string)$monitoring['logging']['health_status'],
            'alert_state' => (string)$monitoring['alerts']['state_file'],
        ],
        'tls' => [
            'cert_name' => (string)$tls['acme']['cert_name'],
            'acme_webroot' => (string)$tls['acme']['webroot'],
            'renewal_conf' => (string)$tls['certificate']['renewal_conf'],
        ],
        'filesystem' => [
            'tenant_root' => $c['tenant_root'],
            'private_root' => $c['private_root'],
            'bundle_dir' => $out,
            'plan_file' => $out . '/lifecycle-plan.json',
            'state_dir' => $stateDir,
            'state_file' => $stateDir . '/' . $tenant . '.json',
            'plan_snapshot_dir' => $stateDir . '/plans',
            'plan_snapshot_file' => $stateDir . '/plans/' . $tenant . '.json',
            'audit_file' => '/var/log/verenigingsplatform/lifecycle.jsonl',
            'tombstone_dir' => $stateDir . '/tombstones',
            'tombstone_file' => $stateDir . '/tombstones/' . $tenant . '.json',
            'export_root' => $exportRoot,
        ],
        'lifecycle' => [
            'managed_states' => ['active', 'suspended', 'pending_delete'],
            'adopt_active_required_for_existing_installation' => true,
            'suspend_blocks_web_database_and_fpm' => true,
            'export_requires_suspended' => true,
            'delete_requires_suspended_and_verified_export' => true,
            'delete_enters_pending_delete' => true,
            'purge_grace_seconds' => 86400,
            'purge_requires_second_explicit_confirmation' => true,
            'purge_preserves_export_and_tombstone' => true,
            'dns_provider_records_are_never_deleted_automatically' => true,
        ],
        'security' => [
            'root_only_mutations' => true,
            'ordinary_tenant_admin_forbidden' => true,
            'secrets_in_plan_forbidden' => true,
            'symlinks_in_tenant_tree_forbidden_for_export_or_purge' => true,
            'database_passwords_forbidden' => true,
            'export_root_only_mode' => '0600',
            'state_root_owned' => true,
            'audit_append_only_intent' => true,
            'root_plan_snapshot_required_before_mutation' => true,
        ],
    ];
}

function lifecycle48Json(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($json)) throw new RuntimeException('Lifecyclecontract kon niet als JSON worden opgebouwd.');
    return $json . "\n";
}

function lifecycle48PlanLeesEnValideer(string $pad): array
{
    $pad = runtime41BestaandPad($pad, 'lifecycle-plan.json');
    $raw = @file_get_contents($pad);
    if (!is_string($raw)) throw new RuntimeException('lifecycle-plan.json kon niet worden gelezen.');
    try { $plan = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new RuntimeException('lifecycle-plan.json bevat ongeldige JSON.'); }
    if (!is_array($plan) || (int)($plan['schema'] ?? 0) !== 1 || ($plan['phase'] ?? '') !== '4.8') throw new RuntimeException('lifecycle-plan.json heeft een onbekend schema/fase.');

    $context = lifecycle48Context((string)($plan['source']['monitoring_plan_file'] ?? ''));
    foreach ([
        'monitoring_plan_sha256' => $context['monitoring_plan_sha256'],
        'runtime_plan_sha256' => $context['runtime_plan_sha256'],
        'tls_plan_sha256' => $context['tls_plan_sha256'],
        'database_plan_sha256' => $context['database_plan_sha256'],
    ] as $key => $verwacht) {
        if (!hash_equals($verwacht, (string)($plan['source'][$key] ?? ''))) throw new RuntimeException('Lifecycleplan is niet meer byte-exact aan bron ' . $key . ' gebonden.');
    }
    $verwachtPlan = lifecycle48Plan($context);
    if (!hash_equals(lifecycle48Json($verwachtPlan), lifecycle48Json($plan))) throw new RuntimeException('lifecycle-plan.json wijkt af van het actuele 4.1-4.6 contract.');
    if (!hash_equals(runtime41NormPad(dirname($pad)), runtime41NormPad((string)$plan['filesystem']['bundle_dir']))) throw new RuntimeException('Lifecycleplan staat niet in zijn vaste tenantbundle.');
    return ['path' => $pad, 'sha256' => hash('sha256', $raw), 'plan' => $plan, 'context' => $context];
}
