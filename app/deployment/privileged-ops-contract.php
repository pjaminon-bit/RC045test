<?php
// Immutable integriteitscontract voor root-owned hostartifacts buiten de
// applicatierelease. Productie meet deze vaste allowlist uitsluitend vanuit
// trusted root host-tooling; de weblaag consumeert alleen de gepubliceerde
// snapshot en krijgt geen leesrecht op root-only artifacts.

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
        // #157 root/release trust-boundary: deze launcher blijft onderdeel van
        // hetzelfde immutable hostcontract en mag door #135 niet verdwijnen.
        'host-php' => [
            'source_path' => 'ops/vps-test-deploy/verenigingsplatform-host-php',
            'installed_path' => '/usr/local/sbin/verenigingsplatform-host-php',
            'expected_uid' => 0,
            'expected_gid' => 0,
            'expected_mode' => 0755,
            'expected_executable' => true,
        ],
    ];
}

function privilegedOpsContract(): array
{
    $hashes = [
        'github-entry' => '48bdaaa5e9cd3a23987b3dd996c641a9a16f278a64623fbf1108cb4c237e5324',
        'github-deploy' => 'b51529d46bc65088c180caa0fba85b13328b44f653d5aa897287883862d0f12f',
        'github-e2e' => 'a416e4cb44a680f20c9bf924ddde2cefec49f715ea542c7c706b4d46db46e32e',
        'github-e2e-sudoers' => '4e74398220aeef8c1307ef8931e726a6e375c911ef4fb6f813673a470199f59d',
        'host-php' => '2c796b58fd10e47093099d7d3b5a5b74f0b0f0ff9224930aa598e11fc0ff42a0',
    ];

    $tools = [];
    foreach (privilegedOpsStructuralDefinitions() as $id => $definition) {
        $sha = $hashes[$id] ?? '';
        $tools[] = ['id'=>$id, 'version'=>'sha256-' . substr($sha, 0, 12)] + $definition + ['expected_sha256'=>$sha];
    }
    return ['schema'=>1, 'phase'=>'privileged-ops-integrity', 'tools'=>$tools];
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

/** Meet één bestand zonder het uit te voeren of te wijzigen. */
function privilegedOpsMeasureFile(
    string $path,
    string $expectedSha256,
    int $expectedUid,
    int $expectedGid,
    int $expectedMode,
    bool $expectedExecutable
): array {
    $missing = ['status'=>'missing','installed_sha256'=>null,'reason'=>'missing'];
    if ($path === '' || !str_starts_with($path, '/') || str_contains($path, "\0") || preg_match('#(?:^|/)\.\.?(/|$)#', $path)) {
        return ['status'=>'unsafe','installed_sha256'=>null,'reason'=>'unsafe_path'];
    }
    if (preg_match('/^[0-9a-f]{64}$/D', $expectedSha256) !== 1) {
        return ['status'=>'unsafe','installed_sha256'=>null,'reason'=>'invalid_expectation'];
    }
    if (is_link($path)) return ['status'=>'unsafe','installed_sha256'=>null,'reason'=>'symlink'];
    if (!file_exists($path)) return $missing;

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
    if (($contract['schema'] ?? null) !== 1 || ($contract['phase'] ?? '') !== 'privileged-ops-integrity' || !is_array($contract['tools'] ?? null)) {
        throw new RuntimeException('Privileged ops-contract heeft onbekend schema.');
    }

    $tools = [];
    $overall = 'ok';
    $rank = ['ok'=>0,'drift'=>1,'missing'=>2,'unsafe'=>3];
    foreach ($contract['tools'] as $definition) {
        if (!is_array($definition) || !privilegedOpsDefinitionValid($definition)) {
            throw new RuntimeException('Privileged ops-contract bevat een ongeldige artifactdefinitie.');
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

/**
 * Valideer een door root gepubliceerde snapshot zonder privileged bestanden te
 * openen. Exact dezelfde artifactset en verwachtingen als de actieve release
 * zijn vereist; ontbrekende/verouderde rootmeting faalt als unknown.
 */
function privilegedOpsPublishedSnapshot(mixed $value): array
{
    if (!is_array($value)
        || ($value['schema'] ?? null) !== 1
        || !in_array((string)($value['status'] ?? ''), ['ok','drift','missing','unsafe'], true)
        || !is_array($value['tools'] ?? null)) {
        return ['schema'=>1,'status'=>'unknown','tools'=>[]];
    }

    $expected = [];
    foreach (privilegedOpsContract()['tools'] as $definition) {
        $expected[(string)$definition['id']] = $definition;
    }
    if (count($value['tools']) !== count($expected)) return ['schema'=>1,'status'=>'unknown','tools'=>[]];

    $rank = ['ok'=>0,'drift'=>1,'missing'=>2,'unsafe'=>3];
    $overall = 'ok';
    $tools = [];
    $seen = [];
    foreach ($value['tools'] as $tool) {
        if (!is_array($tool)) return ['schema'=>1,'status'=>'unknown','tools'=>[]];
        $id = (string)($tool['id'] ?? '');
        $definition = $expected[$id] ?? null;
        $status = (string)($tool['status'] ?? '');
        $version = (string)($tool['version'] ?? '');
        $expectedSha = (string)($tool['expected_sha256'] ?? '');
        $installed = $tool['installed_sha256'] ?? null;
        $reason = $tool['reason'] ?? null;
        if (!is_array($definition) || isset($seen[$id])
            || !hash_equals((string)$definition['version'], $version)
            || !hash_equals((string)$definition['expected_sha256'], $expectedSha)
            || !array_key_exists($status, $rank)
            || ($installed !== null && (!is_string($installed) || preg_match('/^[0-9a-f]{64}$/D', $installed) !== 1))
            || ($reason !== null && (!is_string($reason) || preg_match('/^[a-z_]{1,40}$/D', $reason) !== 1))) {
            return ['schema'=>1,'status'=>'unknown','tools'=>[]];
        }
        $seen[$id] = true;
        if ($rank[$status] > $rank[$overall]) $overall = $status;
        $tools[] = [
            'id'=>$id,
            'version'=>$version,
            'status'=>$status,
            'expected_sha256'=>$expectedSha,
            'installed_sha256'=>$installed,
            'reason'=>$reason,
        ];
    }
    if (count($seen) !== count($expected) || !hash_equals($overall, (string)$value['status'])) {
        return ['schema'=>1,'status'=>'unknown','tools'=>[]];
    }
    return ['schema'=>1,'status'=>$overall,'tools'=>$tools];
}
