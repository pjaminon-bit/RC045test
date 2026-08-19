<?php
// ============================================================
// Tenant-aware private storage abstraction
// ============================================================
// JSON/PHP-bestanden blijven de standaard op gedeelde hosting. Op een VPS
// kan dezelfde service via site-config.local.php op PDO worden gezet zonder
// dat controllers of domeinservices hun opslagpad zelf hoeven te kennen.
//
// De PDO-driver gebruikt bewust één documenttabel als migratiebrug. Fase 3
// kan daarna per domein relationele repositories invoeren zonder opnieuw de
// UI/controllers te verbouwen.
// ============================================================

function privateStoreConfig(): array
{
    static $config = null;
    if ($config === null) {
        $geladen = require dirname(__DIR__, 2) . '/site-config.php';
        $config = is_array($geladen) ? $geladen : [];
    }
    return $config;
}

function privateStoreTenant(): string
{
    $config = privateStoreConfig();
    $sleutel = strtolower(trim((string) ($config['vereniging']['sleutel'] ?? 'default')));
    $sleutel = preg_replace('/[^a-z0-9_-]+/', '-', $sleutel);
    return trim((string) $sleutel, '-') ?: 'default';
}

function privateStoreDriver(): string
{
    $config = privateStoreConfig();
    $driver = strtolower(trim((string) ($config['opslag']['private_driver'] ?? 'json')));
    return $driver === 'pdo' ? 'pdo' : 'json';
}

function privateStorePdo(): ?PDO
{
    static $pdo = false;
    if ($pdo instanceof PDO) return $pdo;
    if ($pdo === null) return null;
    $config = privateStoreConfig();
    $dsn = trim((string) ($config['opslag']['pdo']['dsn'] ?? getenv('VERENIGING_DB_DSN') ?: ''));
    if ($dsn === '') { $pdo = null; return null; }
    $user = (string) ($config['opslag']['pdo']['user'] ?? getenv('VERENIGING_DB_USER') ?: '');
    $pass = (string) ($config['opslag']['pdo']['password'] ?? getenv('VERENIGING_DB_PASSWORD') ?: '');
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        privateStoreEnsureSchema($pdo);
        return $pdo;
    } catch (Throwable $e) {
        error_log('[platform] private PDO niet beschikbaar: ' . $e->getMessage());
        $pdo = null;
        return null;
    }
}

function privateStoreEnsureSchema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS vereniging_private_store (
        tenant_key VARCHAR(80) NOT NULL,
        collection_key VARCHAR(120) NOT NULL,
        payload TEXT NOT NULL,
        updated_at VARCHAR(40) NOT NULL,
        PRIMARY KEY (tenant_key, collection_key)
    )');
}

function privateStoreLees(string $collectie, callable $jsonLezer): array
{
    $collectie = trim($collectie);
    if ($collectie === '') return [];
    if (privateStoreDriver() !== 'pdo') {
        $data = $jsonLezer();
        return is_array($data) ? $data : [];
    }
    $pdo = privateStorePdo();
    if (!$pdo) return [];
    $stmt = $pdo->prepare('SELECT payload FROM vereniging_private_store WHERE tenant_key = :tenant AND collection_key = :collection');
    $stmt->execute(['tenant'=>privateStoreTenant(),'collection'=>$collectie]);
    $rij = $stmt->fetch();
    if (!$rij) return [];
    $data = json_decode((string) $rij['payload'], true);
    return is_array($data) ? $data : [];
}

function privateStoreSchrijf(string $collectie, array $data, callable $jsonSchrijver): bool
{
    $collectie = trim($collectie);
    if ($collectie === '') return false;
    if (privateStoreDriver() !== 'pdo') return (bool) $jsonSchrijver($data);
    $pdo = privateStorePdo();
    if (!$pdo) return false;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    $tenant = privateStoreTenant();
    $nu = date('c');
    $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    if ($driver === 'pgsql') {
        $sql = 'INSERT INTO vereniging_private_store (tenant_key, collection_key, payload, updated_at)
                VALUES (:tenant,:collection,:payload,:updated)
                ON CONFLICT (tenant_key, collection_key) DO UPDATE SET payload = EXCLUDED.payload, updated_at = EXCLUDED.updated_at';
    } elseif ($driver === 'sqlite') {
        $sql = 'INSERT INTO vereniging_private_store (tenant_key, collection_key, payload, updated_at)
                VALUES (:tenant,:collection,:payload,:updated)
                ON CONFLICT(tenant_key, collection_key) DO UPDATE SET payload=excluded.payload, updated_at=excluded.updated_at';
    } else {
        // MySQL/MariaDB fallback voor lokale tests.
        $sql = 'INSERT INTO vereniging_private_store (tenant_key, collection_key, payload, updated_at)
                VALUES (:tenant,:collection,:payload,:updated)
                ON DUPLICATE KEY UPDATE payload=VALUES(payload), updated_at=VALUES(updated_at)';
    }
    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute(['tenant'=>$tenant,'collection'=>$collectie,'payload'=>$json,'updated'=>$nu]);
    } catch (Throwable $e) {
        error_log('[platform] private store write mislukt: ' . $e->getMessage());
        return false;
    }
}
