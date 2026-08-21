<?php
// ============================================================
// Fase 4.5 — server-only PostgreSQL runtimebinding
// ============================================================
// Dit bestand bevat geen provisioning/root-acties. Het leest uitsluitend het
// vaste, secretvrije database-runtime.json naast de externe tenantconfig en
// valideert na connectie tenantmarker + schemaversie fail-closed.
// ============================================================

function pdoRuntime45NormPad(string $pad): string
{
    $pad = str_replace('\\', '/', $pad);
    $pad = (string)preg_replace('~/+~', '/', $pad);
    return $pad === '/' ? '/' : rtrim($pad, '/');
}

function pdoRuntime45ServerConfig(string $tenantKey): ?array
{
    $configEnv = trim((string)(getenv('VERENIGING_CONFIG_FILE') ?: ''));
    if ($configEnv === '') return null;
    if (!str_starts_with($configEnv, '/') || str_contains($configEnv, "\0")) {
        throw new RuntimeException('Externe tenantconfig heeft geen veilig absoluut pad.');
    }
    $configReal = realpath($configEnv);
    if ($configReal === false || is_link($configEnv) || !is_file($configReal)) {
        throw new RuntimeException('Externe tenantconfig kan niet veilig worden opgelost.');
    }
    $tenantRoot = dirname($configReal);
    $databaseDir = $tenantRoot . '/database';
    $runtimePad = $databaseDir . '/database-runtime.json';
    if (is_link($databaseDir) || is_link($runtimePad)) {
        throw new RuntimeException('Database-runtimepad mag geen symlink zijn.');
    }
    if (!is_file($runtimePad)) {
        throw new RuntimeException('PDO-tenant mist fase-4.5 database-runtime.json.');
    }
    $runtimeReal = realpath($runtimePad);
    if ($runtimeReal === false || !hash_equals(pdoRuntime45NormPad($runtimePad), pdoRuntime45NormPad($runtimeReal))) {
        throw new RuntimeException('Database-runtimebestand wijst niet naar het vaste tenantpad.');
    }
    $mode = @fileperms($runtimeReal);
    if ($mode === false || (($mode & 0007) !== 0)) {
        throw new RuntimeException('Database-runtimebestand heeft onveilige world-rechten.');
    }
    $raw = @file_get_contents($runtimeReal);
    if (!is_string($raw)) throw new RuntimeException('Database-runtimebestand kon niet worden gelezen.');
    try { $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new RuntimeException('Database-runtimebestand bevat ongeldige JSON.'); }
    if (!is_array($data)
        || (int)($data['schema'] ?? 0) !== 1
        || ($data['phase'] ?? '') !== '4.5-runtime'
        || !hash_equals($tenantKey, (string)($data['tenant_key'] ?? ''))
        || ($data['driver'] ?? '') !== 'pgsql'
        || ($data['auth_method'] ?? '') !== 'peer'
        || ($data['password_required'] ?? true) !== false
        || ($data['unix_socket_dir'] ?? '') !== '/var/run/postgresql'
        || ($data['schema_name'] ?? '') !== 'vst'
        || (int)($data['schema_version'] ?? 0) !== 1) {
        throw new RuntimeException('Database-runtimebestand voldoet niet aan het fase-4.5 contract.');
    }
    foreach (array_keys($data) as $sleutel) {
        if (preg_match('/password|secret|token|passphrase/i', (string)$sleutel) === 1 && $sleutel !== 'password_required') {
            throw new RuntimeException('Database-runtimebestand bevat een verboden secretveld.');
        }
    }
    $database = (string)($data['database'] ?? '');
    $user = (string)($data['user'] ?? '');
    if (preg_match('/^[a-z_][a-z0-9_]{0,62}$/D', $database) !== 1
        || preg_match('/^[a-z_][a-z0-9_]{0,62}$/D', $user) !== 1) {
        throw new RuntimeException('Database-runtime bevat geen veilige PostgreSQL-identiteiten.');
    }
    $dsn = 'pgsql:host=/var/run/postgresql;dbname=' . $database;
    if (!hash_equals($dsn, (string)($data['dsn'] ?? ''))) {
        throw new RuntimeException('Database-runtime DSN wijkt af van het vaste lokale peer-contract.');
    }
    $data['_path'] = $runtimeReal;
    return $data;
}

function pdoRuntime45ValideerSchema(PDO $pdo, array $runtime, string $tenantKey): void
{
    $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    if ($driver !== 'pgsql') throw new RuntimeException('Fase-4.5 serverruntime vereist pdo_pgsql.');
    $pdo->exec('SET search_path TO vst, pg_catalog');

    $identiteit = $pdo->query('SELECT current_database() AS database_name, current_user AS role_name');
    $rij = $identiteit === false ? false : $identiteit->fetch(PDO::FETCH_ASSOC);
    if (!is_array($rij)
        || !hash_equals((string)$runtime['database'], (string)($rij['database_name'] ?? ''))
        || !hash_equals((string)$runtime['user'], (string)($rij['role_name'] ?? ''))) {
        throw new RuntimeException('PostgreSQL-verbinding is niet aan de verwachte tenantdatabase/role gebonden.');
    }

    $stmt = $pdo->prepare("SELECT tenant_key, schema_version FROM vst.vereniging_schema_meta WHERE component = 'private_store'");
    if (!$stmt->execute()) throw new RuntimeException('Databaseschemamarker kon niet worden gelezen.');
    $meta = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($meta)
        || !hash_equals($tenantKey, (string)($meta['tenant_key'] ?? ''))
        || (int)($meta['schema_version'] ?? 0) !== (int)$runtime['schema_version']) {
        throw new RuntimeException('Tenantmarker of databaseschemaversie wijkt af; runtime faalt gesloten.');
    }

    $probe = $pdo->query('SELECT 1 FROM vst.vereniging_private_store WHERE 1 = 0');
    if ($probe === false) throw new RuntimeException('Private-store tabel is niet bruikbaar.');
}
