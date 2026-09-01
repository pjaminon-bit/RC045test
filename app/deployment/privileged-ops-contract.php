<?php
// Read-only contract voor privileged/root-tooling die bewust buiten de
// immutable applicatierelease blijft. De actieve release beschrijft welke
// bytes op vaste root-owned installatiepaden worden verwacht. Observability
// controleert die wereldleesbare executables alleen met lstat/hash_file en
// krijgt daarmee geen extra rootrechten of uitvoermogelijkheid.

function privilegedOpsContract(): array
{
    return [
        'schema' => 1,
        'phase' => 'privileged-ops-integrity',
        'tools' => [
            [
                'id' => 'github-entry',
                'version' => 'sha256-b34d36a418eb',
                'source_path' => 'ops/vps-test-deploy/verenigingsplatform-github-entry',
                'installed_path' => '/usr/local/bin/verenigingsplatform-github-entry',
                'expected_sha256' => 'b34d36a418eb0be4c7806803976b41e76603001b593247c7ba279f7988fd2b8b',
                'expected_uid' => 0,
                'expected_gid' => 0,
                'expected_mode' => 0755,
            ],
            [
                'id' => 'github-deploy',
                'version' => 'sha256-df1b56ba6666',
                'source_path' => 'ops/vps-test-deploy/verenigingsplatform-github-deploy',
                'installed_path' => '/usr/local/sbin/verenigingsplatform-github-deploy',
                'expected_sha256' => 'df1b56ba66665424aa811e959d9199b6354f8d9018634a5ea0e510b2124997d6',
                'expected_uid' => 0,
                'expected_gid' => 0,
                'expected_mode' => 0755,
            ],
        ],
    ];
}

function privilegedOpsDefinitionValid(array $tool): bool
{
    $id = (string)($tool['id'] ?? '');
    $version = (string)($tool['version'] ?? '');
    $source = (string)($tool['source_path'] ?? '');
    $installed = (string)($tool['installed_path'] ?? '');
    $sha = (string)($tool['expected_sha256'] ?? '');
    $uid = $tool['expected_uid'] ?? null;
    $gid = $tool['expected_gid'] ?? null;
    $mode = $tool['expected_mode'] ?? null;

    if (preg_match('/^[a-z0-9][a-z0-9-]{1,63}$/D', $id) !== 1) return false;
    if (preg_match('/^sha256-[0-9a-f]{12}$/D', $version) !== 1) return false;
    if (preg_match('#^ops/vps-test-deploy/[a-z0-9][a-z0-9._-]{1,100}$#D', $source) !== 1) return false;
    if (preg_match('#^/usr/local/(?:bin|sbin)/[a-z0-9][a-z0-9._-]{1,100}$#D', $installed) !== 1) return false;
    if (preg_match('/^[0-9a-f]{64}$/D', $sha) !== 1) return false;
    if (!is_int($uid) || $uid < 0 || !is_int($gid) || $gid < 0) return false;
    if (!is_int($mode) || $mode < 0 || $mode > 0777) return false;
    return hash_equals('sha256-' . substr($sha, 0, 12), $version);
}

/**
 * Meet één bestand zonder iets uit te voeren of te wijzigen. Het padargument
 * is generiek gehouden zodat de functie met tijdelijke fixtures testbaar is;
 * productie roept hem uitsluitend aan met het statische contract hierboven.
 */
function privilegedOpsMeasureFile(
    string $path,
    string $expectedSha256,
    int $expectedUid,
    int $expectedGid,
    int $expectedMode
): array {
    $result = ['status'=>'missing','installed_sha256'=>null,'reason'=>'missing'];
    if ($path === '' || !str_starts_with($path, '/') || str_contains($path, "\0") || preg_match('#(?:^|/)\.\.?(/|$)#', $path)) {
        return ['status'=>'unsafe','installed_sha256'=>null,'reason'=>'unsafe_path'];
    }
    if (preg_match('/^[0-9a-f]{64}$/D', $expectedSha256) !== 1) {
        return ['status'=>'unsafe','installed_sha256'=>null,'reason'=>'invalid_expectation'];
    }
    if (is_link($path)) return ['status'=>'unsafe','installed_sha256'=>null,'reason'=>'symlink'];
    if (!file_exists($path)) return $result;

    $stat = @lstat($path);
    if (!is_array($stat) || !is_file($path) || !is_readable($path)) {
        return ['status'=>'unsafe','installed_sha256'=>null,'reason'=>'not_regular_readable'];
    }
    $actual = @hash_file('sha256', $path);
    if (!is_string($actual) || preg_match('/^[0-9a-f]{64}$/D', $actual) !== 1) {
        return ['status'=>'unsafe','installed_sha256'=>null,'reason'=>'hash_failed'];
    }
    $mode = (int)$stat['mode'] & 0777;
    if ((int)$stat['uid'] !== $expectedUid || (int)$stat['gid'] !== $expectedGid || $mode !== $expectedMode || !is_executable($path)) {
        return ['status'=>'unsafe','installed_sha256'=>$actual,'reason'=>'metadata'];
    }
    if (!hash_equals($expectedSha256, $actual)) {
        return ['status'=>'drift','installed_sha256'=>$actual,'reason'=>'hash_mismatch'];
    }
    return ['status'=>'ok','installed_sha256'=>$actual,'reason'=>null];
}

function privilegedOpsSnapshot(): array
{
    $contract = privilegedOpsContract();
    if ((int)($contract['schema'] ?? 0) !== 1 || ($contract['phase'] ?? '') !== 'privileged-ops-integrity' || !is_array($contract['tools'] ?? null)) {
        throw new RuntimeException('Privileged ops-contract heeft onbekend schema.');
    }

    $tools = [];
    $overall = 'ok';
    $rank = ['ok'=>0,'drift'=>1,'missing'=>2,'unsafe'=>3];
    foreach ($contract['tools'] as $definition) {
        if (!is_array($definition) || !privilegedOpsDefinitionValid($definition)) {
            throw new RuntimeException('Privileged ops-contract bevat een ongeldige tooldefinitie.');
        }
        $measured = privilegedOpsMeasureFile(
            (string)$definition['installed_path'],
            (string)$definition['expected_sha256'],
            (int)$definition['expected_uid'],
            (int)$definition['expected_gid'],
            (int)$definition['expected_mode']
        );
        $status = (string)$measured['status'];
        if (($rank[$status] ?? 99) > ($rank[$overall] ?? 99)) $overall = $status;
        $tools[] = [
            'id'=>(string)$definition['id'],
            'version'=>(string)$definition['version'],
            'status'=>$status,
            'expected_sha256'=>(string)$definition['expected_sha256'],
            'installed_sha256'=>$measured['installed_sha256'],
            'reason'=>$measured['reason'],
        ];
    }

    return ['schema'=>1,'status'=>$overall,'tools'=>$tools];
}
