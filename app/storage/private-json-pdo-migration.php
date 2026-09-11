<?php
// ============================================================
// JSON → PDO private-store migration core (#211)
// ============================================================
// Pure data-/prooflogica. De privileged cutover staat bewust elders.
// ============================================================

require_once __DIR__ . '/private-filesystem.php';

function privateMigrationAssoc(array $waarde): bool
{
    return $waarde !== [] && !array_is_list($waarde);
}

function privateMigrationCanoniekWaarde(mixed $waarde): mixed
{
    if (!is_array($waarde)) return $waarde;
    if (privateMigrationAssoc($waarde)) {
        ksort($waarde, SORT_STRING);
        foreach ($waarde as $k => $v) $waarde[$k] = privateMigrationCanoniekWaarde($v);
        return $waarde;
    }
    foreach ($waarde as $k => $v) $waarde[$k] = privateMigrationCanoniekWaarde($v);
    return $waarde;
}

function privateMigrationCanoniekJson(array $data): string
{
    $json = json_encode(
        privateMigrationCanoniekWaarde($data),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
    );
    if (!is_string($json)) throw new RuntimeException('Canonieke migratie-JSON kon niet worden opgebouwd.');
    return $json;
}

function privateMigrationDataHash(array $data): string
{
    return hash('sha256', privateMigrationCanoniekJson($data));
}

function privateMigrationPadBinnen(string $pad, string $root): bool
{
    $norm = static function (string $p): string {
        $p = str_replace('\\', '/', $p);
        $p = (string)preg_replace('~/+~', '/', $p);
        return rtrim($p, '/');
    };
    $pad = $norm($pad); $root = $norm($root);
    return $pad === $root || strncmp($pad, $root . '/', strlen($root) + 1) === 0;
}

function privateMigrationCollectiesDir(string $privateRoot): string
{
    if (!tenantRuntimeIsAbsoluutPad($privateRoot)) throw new RuntimeException('Migratie-private-root is niet absoluut.');
    if (is_link($privateRoot) || !is_dir($privateRoot)) throw new RuntimeException('Migratie-private-root is niet veilig beschikbaar.');
    $dir = rtrim($privateRoot, '/\\') . DIRECTORY_SEPARATOR . 'collections';
    if (!privateFilesystemBeveiligMap($dir, true)) {
        throw new RuntimeException('Private collectie-root kon niet met veilige mode worden aangemaakt of gevalideerd.');
    }
    if (is_link($dir) || !is_dir($dir)) throw new RuntimeException('Private collectie-root is geen veilige map.');
    return $dir;
}

/** @return array<string,array{key:string,path:string,raw:string,raw_sha256:string,canonical_sha256:string,count:int,data:array}> */
function privateMigrationInventory(string $privateRoot): array
{
    $dir = privateMigrationCollectiesDir($privateRoot);
    $items = scandir($dir);
    if (!is_array($items)) throw new RuntimeException('Private collecties konden niet worden geïnventariseerd.');
    $result = [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $pad = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_link($pad)) throw new RuntimeException('Private collectie-inventaris bevat een symlink: ' . $item);
        if (!is_file($pad)) throw new RuntimeException('Private collectie-inventaris bevat een onverwacht object: ' . $item);
        if (preg_match('/^([a-z0-9][a-z0-9_-]*)\.json$/D', $item, $m) !== 1) {
            throw new RuntimeException('Private collectie-inventaris bevat een onverwachte bestandsnaam: ' . $item);
        }
        $key = $m[1];
        if (!hash_equals($key, tenantRuntimeCollectieSleutel($key))) {
            throw new RuntimeException('Private collectie heeft geen canonieke sleutel: ' . $item);
        }
        $raw = @file_get_contents($pad);
        if (!is_string($raw)) throw new RuntimeException('Private collectie kon niet worden gelezen: ' . $key);
        try { $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
        catch (JsonException $e) { throw new RuntimeException('Private collectie bevat ongeldige JSON: ' . $key, 0, $e); }
        if (!is_array($data)) throw new RuntimeException('Private collectie bevat geen JSON-document/array: ' . $key);
        $result[$key] = [
            'key' => $key,
            'path' => $pad,
            'raw' => $raw,
            'raw_sha256' => hash('sha256', $raw),
            'canonical_sha256' => privateMigrationDataHash($data),
            'count' => count($data),
            'data' => $data,
        ];
    }
    ksort($result, SORT_STRING);
    return $result;
}

function privateMigrationSamenvatting(array $inventory): array
{
    $collections = [];
    foreach ($inventory as $key => $entry) {
        $collections[$key] = [
            'canonical_sha256' => (string)$entry['canonical_sha256'],
            'count' => (int)$entry['count'],
        ];
    }
    ksort($collections, SORT_STRING);
    $json = json_encode($collections, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    return [
        'collection_count' => count($collections),
        'collections' => $collections,
        'aggregate_sha256' => hash('sha256', (string)$json),
    ];
}

function privateMigrationSnapshot(string $privateRoot, string $tenant, array $inventory): array
{
    $migrationsDir = rtrim($privateRoot, '/\\') . DIRECTORY_SEPARATOR . 'migrations';
    $migratieRoot = $migrationsDir . DIRECTORY_SEPARATOR . 'private-json-to-pdo';
    if (!privateFilesystemBeveiligMapMetMode($migrationsDir, 0700, true)
        || !privateFilesystemBeveiligMapMetMode($migratieRoot, 0700, true)) {
        throw new RuntimeException('Migratiesnapshot-root kon niet met veilige mode worden aangemaakt.');
    }
    if (is_link($migratieRoot) || !is_dir($migratieRoot)) throw new RuntimeException('Migratiesnapshot-root is onveilig.');
    $seed = $tenant . "\0" . microtime(true) . "\0" . bin2hex(random_bytes(12));
    $id = gmdate('Ymd\THis\Z') . '-' . substr(hash('sha256', $seed), 0, 16);
    $dir = $migratieRoot . DIRECTORY_SEPARATOR . $id;
    $sourceDir = $dir . DIRECTORY_SEPARATOR . 'source';
    if (!privateFilesystemBeveiligMapMetMode($dir, 0700, true)
        || !privateFilesystemBeveiligMapMetMode($sourceDir, 0700, true)) {
        throw new RuntimeException('Migratiesnapshot kon niet met veilige mode worden aangemaakt.');
    }

    foreach ($inventory as $key => $entry) {
        $doel = $sourceDir . DIRECTORY_SEPARATOR . $key . '.json';
        if (!privateFilesystemAtomischSchrijf($doel, (string)$entry['raw'], 0600)) {
            throw new RuntimeException('Migratiesnapshot kon collectie niet veilig opslaan: ' . $key);
        }
        $hash = @hash_file('sha256', $doel);
        if (!is_string($hash) || !hash_equals((string)$entry['raw_sha256'], $hash)) {
            throw new RuntimeException('Migratiesnapshot wijkt direct na schrijven af: ' . $key);
        }
    }
    return ['id' => $id, 'dir' => $dir, 'source_dir' => $sourceDir];
}

/** @return array<string,array{key:string,canonical_sha256:string,count:int,data:array}> */
function privateMigrationTargetInventory(PDO $pdo, string $tenant): array
{
    $stmt = $pdo->query('SELECT tenant_key, collection_key, payload FROM vereniging_private_store ORDER BY tenant_key, collection_key');
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    if (!is_array($rows)) throw new RuntimeException('PDO-migratiedoel kon niet worden geïnventariseerd.');
    $result = [];
    foreach ($rows as $row) {
        $rowTenant = (string)($row['tenant_key'] ?? '');
        if (!hash_equals($tenant, $rowTenant)) {
            throw new RuntimeException('PDO-migratiedoel bevat data van een andere tenant.');
        }
        $key = (string)($row['collection_key'] ?? '');
        if ($key === '' || !hash_equals($key, tenantRuntimeCollectieSleutel($key))) {
            throw new RuntimeException('PDO-migratiedoel bevat een ongeldige collectiesleutel.');
        }
        try { $data = json_decode((string)($row['payload'] ?? ''), true, 512, JSON_THROW_ON_ERROR); }
        catch (JsonException $e) { throw new RuntimeException('PDO-migratiedoel bevat ongeldige JSON voor ' . $key, 0, $e); }
        if (!is_array($data)) throw new RuntimeException('PDO-migratiedoel bevat geen document/array voor ' . $key);
        $result[$key] = [
            'key' => $key,
            'canonical_sha256' => privateMigrationDataHash($data),
            'count' => count($data),
            'data' => $data,
        ];
    }
    ksort($result, SORT_STRING);
    return $result;
}

function privateMigrationVergelijk(array $source, array $target): array
{
    $s = privateMigrationSamenvatting($source);
    $t = privateMigrationSamenvatting($target);
    $exact = hash_equals((string)$s['aggregate_sha256'], (string)$t['aggregate_sha256'])
        && $s['collections'] === $t['collections'];
    return ['exact' => $exact, 'source' => $s, 'target' => $t];
}

function privateMigrationImport(PDO $pdo, string $tenant, array $source): array
{
    $voor = privateMigrationTargetInventory($pdo, $tenant);
    if ($voor !== []) {
        $vergelijk = privateMigrationVergelijk($source, $voor);
        if (!$vergelijk['exact']) {
            throw new RuntimeException('PDO-migratiedoel is niet leeg en wijkt af; overschrijven is geweigerd.');
        }
        return ['status' => 'already-exact', 'comparison' => $vergelijk];
    }

    $eigen = !$pdo->inTransaction();
    if ($eigen && !$pdo->beginTransaction()) throw new RuntimeException('PDO-migratietransactie kon niet starten.');
    try {
        $insert = $pdo->prepare('INSERT INTO vereniging_private_store (tenant_key, collection_key, payload, updated_at) VALUES (:tenant,:collection,:payload,:updated)');
        if (!$insert) throw new RuntimeException('PDO-migratie INSERT kon niet worden voorbereid.');
        foreach ($source as $key => $entry) {
            $payload = json_encode($entry['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            $ok = $insert->execute([
                'tenant' => $tenant,
                'collection' => $key,
                'payload' => $payload,
                'updated' => gmdate('c'),
            ]);
            if (!$ok) throw new RuntimeException('PDO-migratie kon collectie niet importeren: ' . $key);
        }
        $na = privateMigrationTargetInventory($pdo, $tenant);
        $vergelijk = privateMigrationVergelijk($source, $na);
        if (!$vergelijk['exact']) throw new RuntimeException('PDO-migratiedoel wijkt vóór commit af van de JSON-bron.');
        if ($eigen && !$pdo->commit()) throw new RuntimeException('PDO-migratietransactie kon niet committen.');
        return ['status' => 'imported', 'comparison' => $vergelijk];
    } catch (Throwable $e) {
        if ($eigen && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function privateMigrationProofSchrijf(
    string $tenant,
    string $privateRoot,
    array $snapshot,
    array $source,
    array $targetComparison,
    array $bindings = []
): array {
    $sourceSummary = privateMigrationSamenvatting($source);
    $targetSummary = (array)($targetComparison['target'] ?? []);
    if (!hash_equals((string)$sourceSummary['aggregate_sha256'], (string)($targetSummary['aggregate_sha256'] ?? ''))) {
        throw new RuntimeException('Migratiebewijs kan niet worden geschreven zonder exacte bron/doel-hash.');
    }
    $rawFiles = [];
    foreach ($source as $key => $entry) {
        $rawFiles[$key] = [
            'file' => 'source/' . $key . '.json',
            'raw_sha256' => (string)$entry['raw_sha256'],
            'canonical_sha256' => (string)$entry['canonical_sha256'],
            'count' => (int)$entry['count'],
        ];
    }
    $proof = [
        'schema' => 1,
        'phase' => 'private-json-to-pdo',
        'status' => 'verified',
        'tenant_key' => $tenant,
        'created_at' => gmdate('c'),
        'source' => $sourceSummary + ['files' => $rawFiles],
        'target' => $targetSummary,
        'bindings' => $bindings,
        'rollback_contract' => 'allowed-only-while-live-json-and-pdo-still-match-this-proof',
    ];
    $json = json_encode($proof, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $pad = $snapshot['dir'] . DIRECTORY_SEPARATOR . 'migration-proof.json';
    if (!privateFilesystemAtomischSchrijf($pad, $json, 0600)) throw new RuntimeException('Migratiebewijs kon niet veilig worden opgeslagen.');
    $hash = @hash_file('sha256', $pad);
    if (!is_string($hash)) throw new RuntimeException('Migratiebewijs kon niet worden gehasht.');
    return ['path' => $pad, 'sha256' => $hash, 'data' => $proof];
}

function privateMigrationProofLees(string $proofPad, string $privateRoot, string $tenant): array
{
    $root = rtrim($privateRoot, '/\\') . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . 'private-json-to-pdo';
    if (!tenantRuntimeIsAbsoluutPad($proofPad) || !privateMigrationPadBinnen($proofPad, $root) || is_link($proofPad) || !is_file($proofPad)) {
        throw new RuntimeException('Migratiebewijs heeft geen veilig pad.');
    }
    $raw = @file_get_contents($proofPad);
    if (!is_string($raw)) throw new RuntimeException('Migratiebewijs kon niet worden gelezen.');
    try { $proof = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new RuntimeException('Migratiebewijs bevat ongeldige JSON.', 0, $e); }
    if (!is_array($proof)
        || (int)($proof['schema'] ?? 0) !== 1
        || ($proof['phase'] ?? '') !== 'private-json-to-pdo'
        || ($proof['status'] ?? '') !== 'verified'
        || !hash_equals($tenant, (string)($proof['tenant_key'] ?? ''))) {
        throw new RuntimeException('Migratiebewijs hoort niet bij deze tenant/contractversie.');
    }
    return ['raw' => $raw, 'sha256' => hash('sha256', $raw), 'data' => $proof];
}

function privateMigrationProofControleer(PDO $pdo, string $tenant, string $privateRoot, string $proofPad): array
{
    $proofInfo = privateMigrationProofLees($proofPad, $privateRoot, $tenant);
    $proof = $proofInfo['data'];
    $live = privateMigrationInventory($privateRoot);
    $liveSummary = privateMigrationSamenvatting($live);
    if (!hash_equals((string)($proof['source']['aggregate_sha256'] ?? ''), (string)$liveSummary['aggregate_sha256'])
        || (array)($proof['source']['collections'] ?? []) !== $liveSummary['collections']) {
        throw new RuntimeException('Live JSON-bron is sinds het migratiebewijs gewijzigd.');
    }

    foreach ((array)($proof['source']['files'] ?? []) as $key => $meta) {
        $snapshot = dirname($proofPad) . DIRECTORY_SEPARATOR . (string)($meta['file'] ?? '');
        if (!privateMigrationPadBinnen($snapshot, dirname($proofPad)) || is_link($snapshot) || !is_file($snapshot)) {
            throw new RuntimeException('Migratiesnapshot ontbreekt voor ' . $key);
        }
        $hash = @hash_file('sha256', $snapshot);
        if (!is_string($hash) || !hash_equals((string)($meta['raw_sha256'] ?? ''), $hash)) {
            throw new RuntimeException('Migratiesnapshot is gewijzigd voor ' . $key);
        }
    }

    $target = privateMigrationTargetInventory($pdo, $tenant);
    $targetSummary = privateMigrationSamenvatting($target);
    if (!hash_equals((string)($proof['target']['aggregate_sha256'] ?? ''), (string)$targetSummary['aggregate_sha256'])
        || (array)($proof['target']['collections'] ?? []) !== $targetSummary['collections']) {
        throw new RuntimeException('PDO-doel is sinds het migratiebewijs gewijzigd.');
    }
    return [
        'proof_path' => $proofPad,
        'proof_sha256' => $proofInfo['sha256'],
        'source' => $liveSummary,
        'target' => $targetSummary,
        'rollback_safe' => true,
    ];
}
