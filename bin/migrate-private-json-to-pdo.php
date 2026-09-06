<?php
// ============================================================
// Unprivileged JSON → PDO private-store worker (#211)
// ============================================================
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Alleen via CLI beschikbaar.'); }

require_once dirname(__DIR__) . '/app/storage/private-store.php';
require_once dirname(__DIR__) . '/app/storage/private-json-pdo-migration.php';

function migrate211Stop(string $melding, int $code = 1): never
{
    fwrite(STDERR, "FOUT: {$melding}\n");
    exit($code);
}

function migrate211Uit(array $data): never
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    exit(0);
}

function migrate211Bindings(array $config, string $privateRoot): array
{
    $extern = trim((string)(getenv('VERENIGING_CONFIG_FILE') ?: ''));
    if ($extern === '' || !tenantRuntimeIsAbsoluutPad($extern) || is_link($extern) || !is_file($extern)) {
        throw new RuntimeException('Migratieworker vereist een expliciete externe tenantconfig.');
    }
    $tenantRoot = dirname($extern);
    $manifest = $tenantRoot . DIRECTORY_SEPARATOR . 'tenant.json';
    $dbRuntime = $tenantRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database-runtime.json';
    $bindings = [
        'config_sha256' => hash_file('sha256', $extern),
        'private_root' => $privateRoot,
    ];
    if (is_file($manifest) && !is_link($manifest)) $bindings['tenant_manifest_sha256'] = hash_file('sha256', $manifest);
    if (is_file($dbRuntime) && !is_link($dbRuntime)) $bindings['database_runtime_sha256'] = hash_file('sha256', $dbRuntime);
    return $bindings;
}

$opt = getopt('', ['mode:', 'proof:', 'help']);
if (isset($opt['help'])) {
    echo "Gebruik: php bin/migrate-private-json-to-pdo.php --mode=inventory|apply|verify|rollback-check [--proof=/absoluut/pad]\n";
    echo "Deze worker wijzigt nooit config/driver-state en hoort als tenant-runtimeuser te draaien.\n";
    exit(0);
}
$mode = strtolower(trim((string)($opt['mode'] ?? '')));
if (!in_array($mode, ['inventory', 'apply', 'verify', 'rollback-check'], true)) migrate211Stop('--mode is verplicht en moet inventory, apply, verify of rollback-check zijn.');

try {
    $config = privateStoreConfig();
    $configured = strtolower(trim((string)($config['opslag']['private_driver'] ?? '')));
    if ($configured !== 'json') throw new RuntimeException('JSON→PDO migratie is alleen toegestaan voor een JSON-geprovisioneerde tenant.');
    $tenant = privateStoreTenant();
    if ($tenant === 'default') throw new RuntimeException('Migratieworker weigert de default/standalone tenant.');
    $privateRoot = privateStoreJsonRoot();
    if ($privateRoot === null) throw new RuntimeException('Migratieworker mist een tenant-private root.');
    $pdo = privateStorePdo();

    if ($mode === 'inventory') {
        $source = privateMigrationInventory($privateRoot);
        $target = privateMigrationTargetInventory($pdo, $tenant);
        $vergelijk = privateMigrationVergelijk($source, $target);
        migrate211Uit([
            'schema' => 1,
            'phase' => 'private-json-to-pdo-worker',
            'mode' => 'inventory',
            'tenant_key' => $tenant,
            'source' => $vergelijk['source'],
            'target' => $vergelijk['target'],
            'target_empty' => $target === [],
            'already_exact' => (bool)$vergelijk['exact'],
        ]);
    }

    $proofPad = trim((string)($opt['proof'] ?? ''));
    if (in_array($mode, ['verify', 'rollback-check'], true) && $proofPad === '') {
        throw new RuntimeException('--proof is verplicht voor verify/rollback-check.');
    }

    $result = privateStoreMigrationExclusive(function () use ($mode, $proofPad, $tenant, $privateRoot, $pdo, $config): array {
        if ($mode === 'apply') {
            $source = privateMigrationInventory($privateRoot);
            $snapshot = privateMigrationSnapshot($privateRoot, $tenant, $source);
            $import = privateMigrationImport($pdo, $tenant, $source);
            $proof = privateMigrationProofSchrijf(
                $tenant,
                $privateRoot,
                $snapshot,
                $source,
                (array)$import['comparison'],
                migrate211Bindings($config, $privateRoot)
            );
            $controle = privateMigrationProofControleer($pdo, $tenant, $privateRoot, (string)$proof['path']);
            return [
                'schema' => 1,
                'phase' => 'private-json-to-pdo-worker',
                'mode' => 'apply',
                'tenant_key' => $tenant,
                'import_status' => (string)$import['status'],
                'proof_path' => (string)$proof['path'],
                'proof_sha256' => (string)$proof['sha256'],
                'source_aggregate_sha256' => (string)$controle['source']['aggregate_sha256'],
                'target_aggregate_sha256' => (string)$controle['target']['aggregate_sha256'],
                'verified' => true,
            ];
        }
        $controle = privateMigrationProofControleer($pdo, $tenant, $privateRoot, $proofPad);
        return [
            'schema' => 1,
            'phase' => 'private-json-to-pdo-worker',
            'mode' => $mode,
            'tenant_key' => $tenant,
            'proof_path' => (string)$controle['proof_path'],
            'proof_sha256' => (string)$controle['proof_sha256'],
            'source_aggregate_sha256' => (string)$controle['source']['aggregate_sha256'],
            'target_aggregate_sha256' => (string)$controle['target']['aggregate_sha256'],
            'verified' => true,
            'rollback_safe' => (bool)$controle['rollback_safe'],
        ];
    });
    migrate211Uit($result);
} catch (Throwable $e) {
    migrate211Stop($e->getMessage());
}
