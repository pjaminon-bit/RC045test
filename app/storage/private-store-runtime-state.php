<?php
// ============================================================
// Effective private-store runtime state
// ============================================================
// Een JSON-geprovisioneerde VPS-tenant kan na een volledig bewezen migratie
// effectief PDO gebruiken zonder config.php/tenant.json/deployment-artifacts
// achteraf te herschrijven. Alleen een root-owned, niet-schrijfbare runtime-
// state met een intact migratiebewijs mag die overgang activeren.
// ============================================================

function privateStoreRuntimeStatePad(): ?string
{
    $extern = trim((string)(getenv('VERENIGING_CONFIG_FILE') ?: ''));
    if ($extern === '' || !tenantRuntimeIsAbsoluutPad($extern)) return null;
    return dirname($extern) . DIRECTORY_SEPARATOR . 'storage-runtime' . DIRECTORY_SEPARATOR . 'private-store-runtime.json';
}

function privateStoreRuntimeStateBinnen(string $pad, string $root): bool
{
    $norm = static function (string $p): string {
        $p = str_replace('\\', '/', $p);
        $p = (string)preg_replace('~/+~', '/', $p);
        return rtrim($p, '/');
    };
    $pad = $norm($pad); $root = $norm($root);
    return $pad === $root || strncmp($pad, $root . '/', strlen($root) + 1) === 0;
}

function privateStoreRuntimeStateBestandVeilig(string $pad): void
{
    if (is_link($pad) || !is_file($pad) || !is_readable($pad)) {
        throw new RuntimeException('Private datastore runtime-state is niet veilig leesbaar.');
    }
    $stat = @stat($pad);
    if (!is_array($stat)) throw new RuntimeException('Private datastore runtime-state metadata ontbreekt.');
    $mode = (int)$stat['mode'] & 0777;
    if (($mode & 0022) !== 0) throw new RuntimeException('Private datastore runtime-state is schrijfbaar door group/others.');

    // Op de echte VPS is dit bestand uitsluitend root-owned. Tempfixtures en
    // niet-POSIX ontwikkelomgevingen hoeven die Linux-eigenaarsgrens niet na te
    // bootsen; /srv/verenigingen is de harde productiegrens.
    if (DIRECTORY_SEPARATOR === '/' && str_starts_with($pad, '/srv/verenigingen/')) {
        if ((int)$stat['uid'] !== 0) throw new RuntimeException('Private datastore runtime-state is niet root-owned.');
        $dir = dirname($pad);
        if (is_link($dir) || !is_dir($dir)) throw new RuntimeException('Private datastore runtime-map is onveilig.');
        $dirStat = @stat($dir);
        if (!is_array($dirStat) || (int)$dirStat['uid'] !== 0 || (((int)$dirStat['mode'] & 0777) & 0022) !== 0) {
            throw new RuntimeException('Private datastore runtime-map heeft onveilige eigenaar/rechten.');
        }
    }
}

function privateStoreRuntimeStateLees(array $config): ?array
{
    $pad = privateStoreRuntimeStatePad();
    if ($pad === null || !file_exists($pad)) return null;
    privateStoreRuntimeStateBestandVeilig($pad);
    $raw = @file_get_contents($pad);
    if (!is_string($raw)) throw new RuntimeException('Private datastore runtime-state kon niet worden gelezen.');
    try { $state = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new RuntimeException('Private datastore runtime-state bevat ongeldige JSON.', 0, $e); }
    if (!is_array($state)
        || (int)($state['schema'] ?? 0) !== 1
        || ($state['phase'] ?? '') !== 'json-to-pdo-cutover'
        || ($state['source_driver'] ?? '') !== 'json'
        || ($state['effective_driver'] ?? '') !== 'pdo') {
        throw new RuntimeException('Private datastore runtime-state heeft een onbekend contract.');
    }

    $tenant = tenantRuntimeVeiligeSleutel((string)($config['vereniging']['sleutel'] ?? ''));
    if ($tenant === 'default' || !hash_equals($tenant, (string)($state['tenant_key'] ?? ''))) {
        throw new RuntimeException('Private datastore runtime-state hoort bij een andere tenant.');
    }
    $privateRoot = tenantRuntimePrivateRoot($config);
    if ($privateRoot === null) throw new RuntimeException('Private datastore runtime-state mist een private tenantroot.');

    $proof = (string)($state['proof_path'] ?? '');
    $proofHash = strtolower((string)($state['proof_sha256'] ?? ''));
    $proofRoot = rtrim($privateRoot, '/\\') . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . 'private-json-to-pdo';
    if (!tenantRuntimeIsAbsoluutPad($proof)
        || !privateStoreRuntimeStateBinnen($proof, $proofRoot)
        || preg_match('/^[0-9a-f]{64}$/D', $proofHash) !== 1
        || is_link($proof)
        || !is_file($proof)) {
        throw new RuntimeException('Private datastore runtime-state bevat geen veilig migratiebewijs.');
    }
    $werkelijk = @hash_file('sha256', $proof);
    if (!is_string($werkelijk) || !hash_equals($proofHash, strtolower($werkelijk))) {
        throw new RuntimeException('Private datastore migratiebewijs wijkt af van de runtime-state.');
    }
    $proofData = json_decode((string)@file_get_contents($proof), true);
    if (!is_array($proofData)
        || (int)($proofData['schema'] ?? 0) !== 1
        || ($proofData['phase'] ?? '') !== 'private-json-to-pdo'
        || ($proofData['status'] ?? '') !== 'verified'
        || !hash_equals($tenant, (string)($proofData['tenant_key'] ?? ''))
        || !hash_equals((string)($state['target_aggregate_sha256'] ?? ''), (string)($proofData['target']['aggregate_sha256'] ?? ''))) {
        throw new RuntimeException('Private datastore migratiebewijs past niet bij de runtime-state.');
    }
    return $state;
}

function privateStoreEffectiveDriver(array $config, string $configuredDriver): string
{
    $configuredDriver = strtolower(trim($configuredDriver));
    if (!in_array($configuredDriver, ['json', 'pdo'], true)) {
        throw new RuntimeException('Private datastore-driver moet expliciet pdo of json zijn.');
    }
    $state = privateStoreRuntimeStateLees($config);
    if ($state === null) return $configuredDriver;
    if ($configuredDriver !== 'json') {
        throw new RuntimeException('Private datastore runtime-state is alleen toegestaan boven een JSON-geprovisioneerde tenant.');
    }
    return 'pdo';
}
