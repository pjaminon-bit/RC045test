from pathlib import Path
import hashlib
import re

ROOT = Path(__file__).resolve().parents[1]

def replace(path, old, new):
    p = ROOT / path
    s = p.read_text()
    if old not in s:
        raise SystemExit(f"expected text missing in {path}: {old[:160]!r}")
    p.write_text(s.replace(old, new, 1))

# Private-store: effective runtime-state + migration write barrier.
replace(
    "app/storage/private-store.php",
    "require_once __DIR__ . '/pdo-runtime.php';\n",
    "require_once __DIR__ . '/pdo-runtime.php';\nrequire_once __DIR__ . '/private-store-migration-guard.php';\nrequire_once __DIR__ . '/private-store-runtime-state.php';\n",
)
replace(
    "app/storage/private-store.php",
    "    $driver=strtolower(trim((string)($config['opslag']['private_driver']??'')));\n    if($driver==='pdo'||$driver==='json')return$driver;\n    throw new RuntimeException('Private datastore-driver moet expliciet pdo of json zijn.');",
    "    $driver=strtolower(trim((string)($config['opslag']['private_driver']??'')));\n    return privateStoreEffectiveDriver($config,$driver);",
)
replace(
    "app/storage/private-store.php",
    "function privateStoreTransactie(callable $callback)\n{\n",
    "function privateStoreTransactie(callable $callback)\n{\n    privateStoreMigrationWriteGuardEnter();\n    try{return privateStoreTransactieOnbewaakt($callback);}\n    finally{privateStoreMigrationWriteGuardLeave();}\n}\n\nfunction privateStoreTransactieOnbewaakt(callable $callback)\n{\n",
)
replace(
    "app/storage/private-store.php",
    "function privateStoreSchrijf(string $collectie,array $data,callable $jsonSchrijver,?string $legacyPad=null): bool\n{\n",
    "function privateStoreSchrijf(string $collectie,array $data,callable $jsonSchrijver,?string $legacyPad=null): bool\n{\n    privateStoreMigrationWriteGuardEnter();\n    try{return privateStoreSchrijfOnbewaakt($collectie,$data,$jsonSchrijver,$legacyPad);}\n    finally{privateStoreMigrationWriteGuardLeave();}\n}\n\nfunction privateStoreSchrijfOnbewaakt(string $collectie,array $data,callable $jsonSchrijver,?string $legacyPad=null): bool\n{\n",
)

# Database Phase 4.5: allow an explicit JSON migration target while keeping
# the normal PDO provisioning contract byte-compatible by default.
replace(
    "app/deployment/database-contract.php",
    "function database45RuntimeContext(string $runtimePlanPad): array\n{",
    "function database45RuntimeContext(string $runtimePlanPad, bool $migrationTarget = false): array\n{",
)
replace(
    "app/deployment/database-contract.php",
    "    $config = require $configPad;\n    if (!is_array($config)\n        || !hash_equals($tenantKey, (string)($config['vereniging']['sleutel'] ?? ''))\n        || strtolower(trim((string)($config['opslag']['private_driver'] ?? ''))) !== 'pdo') {\n        throw new RuntimeException('Fase 4.5 vereist een tenant die expliciet met private_driver=pdo is geprovisioneerd.');\n    }",
    "    $config = require $configPad;\n    $expectedDriver = $migrationTarget ? 'json' : 'pdo';\n    if (!is_array($config)\n        || !hash_equals($tenantKey, (string)($config['vereniging']['sleutel'] ?? ''))\n        || strtolower(trim((string)($config['opslag']['private_driver'] ?? ''))) !== $expectedDriver) {\n        throw new RuntimeException($migrationTarget\n            ? 'Fase 4.5 migration-target vereist een tenant die expliciet met private_driver=json is geprovisioneerd.'\n            : 'Fase 4.5 vereist een tenant die expliciet met private_driver=pdo is geprovisioneerd.');\n    }",
)
replace(
    "app/deployment/database-contract.php",
    "    if (!is_array($manifest)\n        || !hash_equals($tenantKey, (string)($manifest['tenant_key'] ?? ''))\n        || (string)($manifest['private_driver'] ?? '') !== 'pdo') {\n        throw new RuntimeException('tenant.json bindt niet aan dezelfde PDO-tenant.');\n    }",
    "    if (!is_array($manifest)\n        || !hash_equals($tenantKey, (string)($manifest['tenant_key'] ?? ''))\n        || (string)($manifest['private_driver'] ?? '') !== $expectedDriver) {\n        throw new RuntimeException($migrationTarget\n            ? 'tenant.json bindt niet aan dezelfde JSON migration-target tenant.'\n            : 'tenant.json bindt niet aan dezelfde PDO-tenant.');\n    }",
)
replace(
    "app/deployment/database-contract.php",
    "        'manifest_sha256' => hash('sha256', $manifestRaw),\n    ];",
    "        'manifest_sha256' => hash('sha256', $manifestRaw),\n        'migration_target' => $migrationTarget,\n    ];",
)
replace(
    "app/deployment/database-contract.php",
    "    $hba = database45HbaConfig($basis);",
    "    if (!empty($context['migration_target'])) $basis['source']['migration_target'] = true;\n\n    $hba = database45HbaConfig($basis);",
)
replace(
    "app/deployment/database-contract.php",
    "    $runtimePlan = (string)($plan['source']['runtime_plan_file'] ?? '');\n    $context = database45RuntimeContext($runtimePlan);",
    "    $source = $plan['source'] ?? null;\n    if (!is_array($source)) throw new RuntimeException('database-plan.json mist bronbinding.');\n    if (array_key_exists('migration_target', $source) && $source['migration_target'] !== true) {\n        throw new RuntimeException('database-plan.json bevat een ongeldige migration_target vlag.');\n    }\n    $migrationTarget = ($source['migration_target'] ?? false) === true;\n    $runtimePlan = (string)($source['runtime_plan_file'] ?? '');\n    $context = database45RuntimeContext($runtimePlan, $migrationTarget);",
)

# CLI preparer exposes the migration target explicitly.
replace(
    "bin/prepare-vps-database.php",
    "    echo \"  php bin/prepare-vps-database.php --runtime-plan=/srv/verenigingen/club/runtime/runtime-plan.json [--force] [--dry-run]\\n\\n\";",
    "    echo \"  php bin/prepare-vps-database.php --runtime-plan=/srv/verenigingen/club/runtime/runtime-plan.json [--migration-target] [--force] [--dry-run]\\n\\n\";\n    echo \"  --migration-target  provision PDO-doel voor een nog actieve JSON-tenant; activeert PDO nog niet\\n\";",
)
replace(
    "bin/prepare-vps-database.php",
    "$opt = getopt('', ['runtime-plan:', 'force', 'dry-run', 'help']);",
    "$opt = getopt('', ['runtime-plan:', 'migration-target', 'force', 'dry-run', 'help']);",
)
replace(
    "bin/prepare-vps-database.php",
    "    $context = database45RuntimeContext($runtimePlan);",
    "    $context = database45RuntimeContext($runtimePlan, isset($opt['migration-target']));",
)

# Trusted root host-engine gets one narrow new action. The unprivileged worker
# itself remains in the immutable app/bin engine and is invoked via runuser.
replace(
    "ops/vps-test-deploy/verenigingsplatform-host-php",
    "  echo 'Gebruik: verenigingsplatform-host-php <health|release-prepare|release-apply|control-plane|lifecycle|provision|monitoring-prepare|monitoring-apply|lifecycle-prepare|control-plane-prepare|control-plane-apply> [args...]' >&2",
    "  echo 'Gebruik: verenigingsplatform-host-php <health|release-prepare|release-apply|control-plane|lifecycle|provision|storage-migrate|monitoring-prepare|monitoring-apply|lifecycle-prepare|control-plane-prepare|control-plane-apply> [args...]' >&2",
)
replace(
    "ops/vps-test-deploy/verenigingsplatform-host-php",
    "  provision) script='bin/provision-tenant.php' ;;\n",
    "  provision) script='bin/provision-tenant.php' ;;\n  storage-migrate) script='bin/apply-private-store-migration.php' ;;\n",
)

# Update the immutable host-launcher drift contract to the transformed bytes.
launcher = (ROOT / "ops/vps-test-deploy/verenigingsplatform-host-php").read_bytes()
launcher_hash = hashlib.sha256(launcher).hexdigest()
p = ROOT / "app/deployment/privileged-ops-contract.php"
s = p.read_text()
s2, n = re.subn(r"('host-php'\s*=>\s*')[0-9a-f]{64}(')", rf"\g<1>{launcher_hash}\2", s, count=1)
if n != 1:
    raise SystemExit("host-php expected hash not found")
p.write_text(s2)
