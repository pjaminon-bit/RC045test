<?php
// Read-only contract voor privileged/root-artifacts die bewust buiten de
// immutable applicatierelease blijven. De actieve release beschrijft welke
// bytes en metadata op een kleine vaste allowlist van installatiepaden worden
// verwacht. Productie meet dit contract vanuit de bestaande root-executor en
// publiceert alleen de uitkomst in de control-plane snapshot.

function privilegedOpsStructuralDefinitions(): array
{
    return [
        'github-entry' => [
            'source_path' => 'ops/vps-test-deploy/verenigingsplatform-github-entry',
            'installed_path' => '/usr/local/bin/verenigingsplatform-github-entry',
            'expected_uid' => 0,
            'expected_gid' => 0,
            'expected_mode' => 0755,
            'expected_executable' => true,
        ],
        'github-deploy' => [
            'source_path' => 'ops/vps-test-deploy/verenigingsplatform-github-deploy',
            'installed_path' => '/usr/local/sbin/verenigingsplatform-github-deploy',
            'expected_uid' => 0,
            'expected_gid' => 0,
            'expected_mode' => 0755,
            'expected_executable' => true,
        ],
        'github-e2e' => [
            'source_path' => 'ops/vps-test-deploy/verenigingsplatform-github-e2e',
            'installed_path' => '/usr/local/sbin/verenigingsplatform-github-e2e',
            'expected_uid' => 0,
            'expected_gid' => 0,
            'expected_mode' => 0755,
            'expected_executable' => true,
        ],
        'github-e2e-sudoers' => [
            'source_path' => 'ops/vps-test-deploy/verenigingsplatform-github-e2e.sudoers',
            'installed_path' => '/etc/sudoers.d/verenigingsplatform-github-e2e',
            'expected_uid' => 0,
            'expected_gid' => 0,
            'expected_mode' => 0440,
            'expected_executable' => false,
        ],
    ];
}

function privilegedOpsContract(): array
{
    return [
        'schema' => 1,
        'phase' => 'privileged-ops-integrity',
        'tools' => [
            [
                'id' => 'github-entry',
                'version' => 'sha256-48bdaaa5e9cd',
                'source_path' => 'ops/vps-test-deploy/verenigingsplatform-github-entry',
                'installed_path' => '/usr/local/bin/verenigingsplatform-github-entry',
                'expected_sha256' => '48bdaaa5e9cd3a23987b3dd996c641a9a16f278a64623fbf1108cb4c237e5324',
                'expected_uid' => 0,
                'expected_gid' => 0,
                'expected_mode' => 0755,
                'expected_executable' => true,
            ],
            [
                'id' => 'github-deploy',
                'version' => 'sha256-41114f1c3027',
                'source_path' => 'ops/vps-test-deploy/verenigingsplatform-github-deploy',
                'installed_path' => '/usr/local/sbin/verenigingsplatform-github-deploy',
                'expected_sha256' => '41114f1c30278de0804462c70b651fb71f07e6cbd43e8a983b513af18708e879',
                'expected_uid' => 0,
                'expected_gid' => 0,
                'expected_mode' => 0755,
                'expected_executable' => true,
            ],
            [
                'id' => 'github-e2e',
                'version' => 'sha256-a416e4cb44a6',
                'source_path' => 'ops/vps-test-deploy/verenigingsplatform-github-e2e',
                'installed_path' => '/usr/local/sbin/verenigingsplatform-github-e2e',
                'expected_sha256' => 'a416e4cb44a680f20c9bf924ddde2cefec49f715ea542c7c706b4d46db46e32e',
                'expected_uid' => 0,
                'expected_gid' => 0,
                'expected_mode' => 0755,
                'expected_executable' => true,
            ],
            [
                'id' => 'github-e2e-sudoers',
                'version' => 'sha256-4e74398220ae',
                'source_path' => 'ops/vps-test-deploy/verenigingsplatform-github-e2e.sudoers',
                'installed_path' => '/etc/sudoers.d/verenigingsplatform-github-e2e',
                'expected_sha256' => '4e74398220aeef8c1307ef8931e726a6e375c911ef4fb6f813673a470199f59d',
                'expected_uid' => 0,
                'expected_gid' => 0,
                'expected_mode' => 0440,
                'expected_executable' => false,
            ],
        ],
    ];
}

function privilegedOpsDefinitionValid(array $tool): bool
{
    $id = (string)($tool['id'] ?? '');
    $version = (string)($tool['version'] ?? '');
    $sha = (string)($tool['expected_sha256'] ?? '');
    $structural = privilegedOpsStructuralDefinitions()[$id] ?? null;

    if (!is_array($structural)) return false;
    if (preg_match('/^sha256-[0-9a-f]{12}$/D', $version) !== 1) return false;
    if (preg_match('/^[0-9a-f]{64}$/D', $sha) !== 1) return false;
    if (!hash_equals('sha256-' . substr($sha, 0, 12), $version)) return false;

    foreach ($structural as $key => $expected) {
        if (!array_key_exists($key, $tool) || $tool[$key] !== $expected) return false;
    }
    return true;
}

/**
 * Meet één bestand zonder iets uit te voeren of te wijzigen. Het padargument
 * is generiek gehouden zodat de functie met tijdelijke fixtures testbaar is;
 * productie roept hem uitsluitend vanuit de root-executor aan met het vaste
 * contract hierboven.
 */
function privilegedOpsMeasureFile(
    string $path,
    string $expectedSha256,
    int $expectedUid,
    int $expectedGid,
    int $expectedMode,
    bool $expectedExecutable
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
    if ((int)$stat['uid'] !== $expectedUid
        || (int)$stat['gid'] !== $expectedGid
        || $mode !== $expectedMode
        || is_executable($path) !== $expectedExecutable) {
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
            (int)$definition['expected_mode'],
            (bool)$definition['expected_executable']
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
