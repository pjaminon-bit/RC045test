<?php
// ============================================================
// Fase 4.5 — PostgreSQL database provisioningcontract
// ============================================================
// Pure helpers. Canonieke productie-isolatie:
// - één lokale PostgreSQL-database per tenant;
// - aparte NOLOGIN ownerrol;
// - app-loginrol is exact de Linux/PHP-FPM user uit fase 4.1;
// - uitsluitend Unix-socket peer authentication, dus geen DB-wachtwoord;
// - PostgreSQL is socket-only: geen TCP-listener;
// - schema/DDL blijft buiten de webrequest-runtime.
// ============================================================

require_once __DIR__ . '/runtime-contract.php';

function database45PgIdentifier(string $waarde): bool
{
    return strlen($waarde) >= 1
        && strlen($waarde) <= 63
        && preg_match('/^[a-z_][a-z0-9_]*$/D', $waarde) === 1;
}

function database45DatabaseNaam(string $tenantKey): string
{
    return 'vstdb' . substr(hash('sha256', "database\0" . $tenantKey), 0, 20);
}

function database45OwnerRol(string $tenantKey): string
{
    return 'vsto' . substr(hash('sha256', "owner\0" . $tenantKey), 0, 20);
}

function database45Marker(string $tenantKey): string
{
    return 'verenigingsplatform:tenant=' . $tenantKey . ';phase=4.5';
}

function database45SqlLiteral(string $waarde): string
{
    if (str_contains($waarde, "\0")) throw new RuntimeException('SQL-literal bevat een nulkarakter.');
    return "'" . str_replace("'", "''", $waarde) . "'";
}

function database45RuntimeContext(string $runtimePlanPad): array
{
    $context = runtime41PlanLeesEnValideer($runtimePlanPad);
    $runtime = $context['plan'];
    $deployment = $context['deployment'];
    $tenantKey = (string)($runtime['tenant_key'] ?? '');
    if (!runtime41CanoniekeTenantKey($tenantKey)) throw new RuntimeException('Runtimeplan bevat geen canonieke tenant-key.');

    $configPad = (string)($deployment['config_file'] ?? '');
    $config = require $configPad;
    if (!is_array($config)
        || !hash_equals($tenantKey, (string)($config['vereniging']['sleutel'] ?? ''))
        || strtolower(trim((string)($config['opslag']['private_driver'] ?? ''))) !== 'pdo') {
        throw new RuntimeException('Fase 4.5 vereist een tenant die expliciet met private_driver=pdo is geprovisioneerd.');
    }
    $pdoConfig = $config['opslag']['pdo'] ?? null;
    if (!is_array($pdoConfig)) $pdoConfig = [];
    foreach (['dsn', 'user', 'password'] as $veld) {
        if (trim((string)($pdoConfig[$veld] ?? '')) !== '') {
            throw new RuntimeException('Databaseverbinding mag geen DSN/user/password-secret in config.php bevatten.');
        }
    }

    $tenantRoot = (string)$runtime['filesystem']['tenant_root']['path'];
    $manifestPad = $tenantRoot . '/tenant.json';
    $manifestPad = runtime41BestaandPad($manifestPad, 'tenant.json');
    $manifestRaw = @file_get_contents($manifestPad);
    if (!is_string($manifestRaw)) throw new RuntimeException('tenant.json kon niet worden gelezen.');
    try { $manifest = json_decode($manifestRaw, true, 512, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new RuntimeException('tenant.json bevat ongeldige JSON.'); }
    if (!is_array($manifest)
        || !hash_equals($tenantKey, (string)($manifest['tenant_key'] ?? ''))
        || (string)($manifest['private_driver'] ?? '') !== 'pdo') {
        throw new RuntimeException('tenant.json bindt niet aan dezelfde PDO-tenant.');
    }

    $runtimeEnvPad = runtime41BestaandPad($tenantRoot . '/runtime.env', 'runtime.env');
    $runtimeEnv = @file_get_contents($runtimeEnvPad);
    if (!is_string($runtimeEnv)) throw new RuntimeException('runtime.env kon niet worden gelezen.');
    if (preg_match('/(?:DB_|DATABASE|PGPASSWORD|PGPASS|PDO_)/i', $runtimeEnv) === 1) {
        throw new RuntimeException('runtime.env mag geen databasecredential of databaseverbinding bevatten.');
    }

    $osUser = (string)($runtime['os']['user'] ?? '');
    if (!runtime41LinuxNaam($osUser)
        || !hash_equals((string)($runtime['os']['group'] ?? ''), $osUser)) {
        throw new RuntimeException('Database-appidentiteit moet exact de geïsoleerde fase-4.1 Linux-user/group zijn.');
    }

    $raw = @file_get_contents($context['path']);
    if (!is_string($raw)) throw new RuntimeException('runtime-plan.json kon niet opnieuw worden gelezen.');

    return [
        'runtime_context' => $context,
        'runtime_plan' => $runtime,
        'runtime_plan_path' => $context['path'],
        'runtime_plan_sha256' => hash('sha256', $raw),
        'deployment' => $deployment,
        'tenant_key' => $tenantKey,
        'tenant_root' => $tenantRoot,
        'private_root' => (string)$runtime['filesystem']['private_root']['path'],
        'os_user' => $osUser,
        'os_group' => $osUser,
        'config_file' => $configPad,
        'manifest_file' => $manifestPad,
        'manifest_sha256' => hash('sha256', $manifestRaw),
    ];
}

function database45OutputDir(string $tenantRoot): string
{
    $pad = runtime41NormPad($tenantRoot . '/database');
    if (!runtime41Binnen($pad, $tenantRoot) || $pad === runtime41NormPad($tenantRoot)) {
        throw new RuntimeException('Databasebundle valt niet veilig binnen de tenantroot.');
    }
    $link = runtime41SymlinkInPad($pad);
    if ($link !== null) throw new RuntimeException("Databasebundle mag geen symlink bevatten: {$link}");
    return $pad;
}

function database45Plan(array $context): array
{
    $tenantKey = $context['tenant_key'];
    $outputDir = database45OutputDir($context['tenant_root']);
    $database = database45DatabaseNaam($tenantKey);
    $owner = database45OwnerRol($tenantKey);
    $appRole = $context['os_user'];
    foreach ([$database, $owner, $appRole] as $identifier) {
        if (!database45PgIdentifier($identifier)) throw new RuntimeException('Deterministische PostgreSQL-identiteit is ongeldig.');
    }

    $hbaNaam = '100-vp-' . $tenantKey . '-peer.conf';
    $runtimeFile = $outputDir . '/database-runtime.json';
    $migrationFile = $outputDir . '/001-private-store.sql';
    $hbaFile = $outputDir . '/' . $hbaNaam;
    $planFile = $outputDir . '/database-plan.json';

    $basis = [
        'schema' => 1,
        'phase' => '4.5',
        'tenant_key' => $tenantKey,
        'source' => [
            'runtime_plan_file' => $context['runtime_plan_path'],
            'runtime_plan_sha256' => $context['runtime_plan_sha256'],
            'deployment_file' => $context['deployment']['path'],
            'deployment_sha256' => $context['deployment']['sha256'],
            'tenant_manifest_file' => $context['manifest_file'],
            'tenant_manifest_sha256' => $context['manifest_sha256'],
        ],
        'isolation' => [
            'one_database_per_tenant' => true,
            'database' => $database,
            'owner_role' => $owner,
            'app_role' => $appRole,
            'app_role_equals_os_user' => true,
            'owner_role_login' => false,
            'app_role_login' => true,
            'app_role_owns_database' => false,
            'app_role_ddl_forbidden' => true,
        ],
        'connection' => [
            'driver' => 'pgsql',
            'unix_socket_dir' => '/var/run/postgresql',
            'auth_method' => 'peer',
            'password_required' => false,
            'password_storage_forbidden' => true,
            'dsn' => 'pgsql:host=/var/run/postgresql;dbname=' . $database,
            'user' => $appRole,
        ],
        'schema_contract' => [
            'schema_name' => 'vst',
            'schema_version' => 1,
            'tenant_marker_component' => 'private_store',
            'runtime_ddl_forbidden' => true,
            'migration_name' => '001-private-store',
        ],
        'postgresql' => [
            'minimum_major_version' => 16,
            'socket_only_required' => true,
            'listen_addresses_required' => '',
            'admin_os_user' => 'postgres',
            'admin_database' => 'postgres',
            'psql_binary' => 'psql',
            'runuser_binary' => 'runuser',
            'hba_include_dir' => '/etc/verenigingsplatform/postgresql/pg_hba.d',
            'hba_include_directive' => "include_dir '/etc/verenigingsplatform/postgresql/pg_hba.d'",
            'tenant_hba_filename' => $hbaNaam,
            'hba_allow_own_database_only' => true,
            'hba_reject_other_databases_for_tenant_user' => true,
        ],
        'bundle' => [
            'output_dir' => $outputDir,
            'plan_file' => $planFile,
            'runtime_file' => $runtimeFile,
            'migration_file' => $migrationFile,
            'hba_file' => $hbaFile,
        ],
        'filesystem' => [
            'tenant_runtime_user' => $context['os_user'],
            'tenant_runtime_group' => $context['os_group'],
            'runtime_file_mode' => '0640',
            'bundle_directory_mode' => '0750',
            'database_secrets_file' => null,
        ],
        'security' => [
            'no_database_secret_in_git' => true,
            'no_database_secret_in_tenant_config' => true,
            'no_database_secret_in_runtime_bundle' => true,
            'peer_binds_database_login_to_kernel_os_identity' => true,
            'socket_only_database_server_required' => true,
            'public_database_privileges_revoked' => true,
            'public_schema_privileges_revoked' => true,
            'app_privilege_drift_normalized' => true,
            'cross_database_hba_reject_required' => true,
            'database_tenant_marker_required' => true,
            'connectivity_check_as_tenant_os_user_required' => true,
        ],
    ];

    $hba = database45HbaConfig($basis);
    $migration = database45MigrationSql($basis);
    $basis['artifacts'] = [
        'runtime_sha256' => hash('sha256', database45RuntimeJson($basis)),
        'migration_sha256' => hash('sha256', $migration),
        'hba_sha256' => hash('sha256', $hba),
    ];
    return $basis;
}

function database45RuntimeData(array $plan): array
{
    return [
        'schema' => 1,
        'phase' => '4.5-runtime',
        'tenant_key' => $plan['tenant_key'],
        'driver' => 'pgsql',
        'auth_method' => 'peer',
        'unix_socket_dir' => $plan['connection']['unix_socket_dir'],
        'database' => $plan['isolation']['database'],
        'user' => $plan['isolation']['app_role'],
        'dsn' => $plan['connection']['dsn'],
        'schema_name' => $plan['schema_contract']['schema_name'],
        'schema_version' => $plan['schema_contract']['schema_version'],
        'password_required' => false,
    ];
}

function database45RuntimeJson(array $plan): string
{
    return database45Json(database45RuntimeData($plan));
}

function database45HbaConfig(array $plan): string
{
    $database = $plan['isolation']['database'];
    $user = $plan['isolation']['app_role'];
    if (!database45PgIdentifier($database) || !database45PgIdentifier($user)) {
        throw new RuntimeException('HBA-artifact bevat ongeldige database/user-identiteit.');
    }
    return implode("\n", [
        '# Gegenereerd door fase 4.5. Exacte lokale tenantbinding via kernel peer identity.',
        '# Eerst alleen eigen database toestaan; daarna dezelfde tenantuser voor iedere andere database afwijzen.',
        'local ' . $database . ' ' . $user . ' peer',
        'local all ' . $user . ' reject',
        '',
    ]);
}

function database45MigrationSql(array $plan): string
{
    $tenant = database45SqlLiteral((string)$plan['tenant_key']);
    $owner = (string)$plan['isolation']['owner_role'];
    $app = (string)$plan['isolation']['app_role'];
    foreach ([$owner, $app] as $identifier) {
        if (!database45PgIdentifier($identifier)) throw new RuntimeException('Migratie bevat ongeldige PostgreSQL-role.');
    }
    return implode("\n", [
        '-- Gegenereerd door fase 4.5. Uitvoeren als PostgreSQL-superuser tegen uitsluitend de tenantdatabase.',
        '\\set ON_ERROR_STOP on',
        'BEGIN;',
        'CREATE SCHEMA IF NOT EXISTS vst AUTHORIZATION ' . $owner . ';',
        'ALTER SCHEMA vst OWNER TO ' . $owner . ';',
        'REVOKE ALL ON SCHEMA public FROM PUBLIC;',
        'REVOKE ALL ON SCHEMA public FROM ' . $app . ';',
        'REVOKE ALL ON SCHEMA vst FROM PUBLIC;',
        'REVOKE ALL ON SCHEMA vst FROM ' . $app . ';',
        'CREATE TABLE IF NOT EXISTS vst.vereniging_schema_meta (',
        '    component VARCHAR(80) PRIMARY KEY,',
        '    schema_version INTEGER NOT NULL,',
        '    tenant_key VARCHAR(80) NOT NULL,',
        '    applied_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP',
        ');',
        'ALTER TABLE vst.vereniging_schema_meta OWNER TO ' . $owner . ';',
        'CREATE TABLE IF NOT EXISTS vst.vereniging_private_store (',
        '    tenant_key VARCHAR(80) NOT NULL,',
        '    collection_key VARCHAR(120) NOT NULL,',
        '    payload TEXT NOT NULL,',
        '    updated_at VARCHAR(40) NOT NULL,',
        '    PRIMARY KEY (tenant_key, collection_key)',
        ');',
        'ALTER TABLE vst.vereniging_private_store OWNER TO ' . $owner . ';',
        'REVOKE ALL ON TABLE vst.vereniging_schema_meta FROM PUBLIC;',
        'REVOKE ALL ON TABLE vst.vereniging_schema_meta FROM ' . $app . ';',
        'REVOKE ALL ON TABLE vst.vereniging_private_store FROM PUBLIC;',
        'REVOKE ALL ON TABLE vst.vereniging_private_store FROM ' . $app . ';',
        'INSERT INTO vst.vereniging_schema_meta (component, schema_version, tenant_key)',
        "VALUES ('private_store', 1, {$tenant})",
        'ON CONFLICT (component) DO NOTHING;',
        'GRANT USAGE ON SCHEMA vst TO ' . $app . ';',
        'GRANT SELECT ON TABLE vst.vereniging_schema_meta TO ' . $app . ';',
        'GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE vst.vereniging_private_store TO ' . $app . ';',
        'COMMIT;',
        '',
    ]);
}

function database45Json(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($json)) throw new RuntimeException('Databasecontract kon niet als JSON worden opgebouwd.');
    return $json . "\n";
}

function database45PlanLeesEnValideer(string $planPad): array
{
    $planPad = runtime41BestaandPad($planPad, 'database-plan.json');
    $raw = @file_get_contents($planPad);
    if (!is_string($raw)) throw new RuntimeException('database-plan.json kon niet worden gelezen.');
    try { $plan = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new RuntimeException('database-plan.json bevat ongeldige JSON.'); }
    if (!is_array($plan) || (int)($plan['schema'] ?? 0) !== 1 || ($plan['phase'] ?? '') !== '4.5') {
        throw new RuntimeException('database-plan.json heeft een onbekend schema/fase.');
    }

    $runtimePlan = (string)($plan['source']['runtime_plan_file'] ?? '');
    $context = database45RuntimeContext($runtimePlan);
    $verwacht = database45Plan($context);
    if (!hash_equals(database45Json($verwacht), database45Json($plan))) {
        throw new RuntimeException('database-plan.json wijkt af van het actuele tenant/runtimecontract.');
    }
    if (!hash_equals(runtime41NormPad(dirname($planPad)), runtime41NormPad($verwacht['bundle']['output_dir']))) {
        throw new RuntimeException('database-plan.json staat niet in de vaste tenant databasebundle.');
    }

    $artifacts = [
        'runtime_file' => database45RuntimeJson($plan),
        'migration_file' => database45MigrationSql($plan),
        'hba_file' => database45HbaConfig($plan),
    ];
    foreach ($artifacts as $sleutel => $verwachteInhoud) {
        $pad = (string)($plan['bundle'][$sleutel] ?? '');
        $pad = runtime41BestaandPad($pad, $sleutel);
        $inhoud = @file_get_contents($pad);
        if (!is_string($inhoud) || !hash_equals(hash('sha256', $verwachteInhoud), hash('sha256', $inhoud))) {
            throw new RuntimeException("Databaseartifact {$sleutel} wijkt af van het plan.");
        }
    }

    return [
        'path' => $planPad,
        'sha256' => hash('sha256', $raw),
        'plan' => $plan,
        'context' => $context,
    ];
}
