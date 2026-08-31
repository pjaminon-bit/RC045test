<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;
function o134(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}
function rm134(string $pad): void
{
    if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $naam) {
        if ($naam === '.' || $naam === '..') continue;
        rm134($pad . '/' . $naam);
    }
    @rmdir($pad);
}
function heredoc134(string $script, string $variabele, string $marker): ?string
{
    $needle = 'cat >"$' . $variabele . '" <<\'' . $marker . "'\n";
    $start = strpos($script, $needle);
    if ($start === false) return null;
    $start += strlen($needle);
    $einde = strpos($script, "\n" . $marker . "\n", $start);
    if ($einde === false) return null;
    return substr($script, $start, $einde - $start + 1);
}

require_once $root . '/app/deployment/privileged-ops-contract.php';

$contract = privilegedOpsContract();
o134(($contract['schema'] ?? null) === 1 && ($contract['phase'] ?? '') === 'privileged-ops-integrity', 'privileged ops-contract heeft vast schema');
$tools = is_array($contract['tools'] ?? null) ? $contract['tools'] : [];
o134(count($tools) === 4, 'contract bewaakt deploy-entry, deploywrapper, E2E-wrapper en E2E-sudoers');

$verwachtePaden = [
    'github-entry' => '/usr/local/bin/verenigingsplatform-github-entry',
    'github-deploy' => '/usr/local/sbin/verenigingsplatform-github-deploy',
    'github-e2e' => '/usr/local/sbin/verenigingsplatform-github-e2e',
    'github-e2e-sudoers' => '/etc/sudoers.d/verenigingsplatform-github-e2e',
];
$verwachteModes = [
    'github-entry' => [0755, true],
    'github-deploy' => [0755, true],
    'github-e2e' => [0755, true],
    'github-e2e-sudoers' => [0440, false],
];
foreach ($tools as $tool) {
    $id = (string)($tool['id'] ?? '');
    $source = $root . '/' . (string)($tool['source_path'] ?? '');
    $sha = is_file($source) ? hash_file('sha256', $source) : false;
    o134(privilegedOpsDefinitionValid($tool), 'artifactdefinitie ' . $id . ' is strikt geldig');
    o134(isset($verwachtePaden[$id]) && ($tool['installed_path'] ?? '') === $verwachtePaden[$id], 'artifact ' . $id . ' gebruikt vast allowlisted installatiepad');
    o134(is_string($sha) && hash_equals((string)$tool['expected_sha256'], $sha), 'verwachte SHA-256 van ' . $id . ' blijft gelijk aan repositorybron');
    o134(($tool['version'] ?? '') === 'sha256-' . substr((string)$tool['expected_sha256'], 0, 12), 'zichtbare artifactversie ' . $id . ' is aan SHA-256 gebonden');
    [$mode, $executable] = $verwachteModes[$id] ?? [null, null];
    o134(($tool['expected_mode'] ?? null) === $mode && ($tool['expected_executable'] ?? null) === $executable, 'metadata-contract van ' . $id . ' is minimaal en exact');
}

$ongeldig = $tools[0] ?? [];
$ongeldig['installed_path'] = '/tmp/onveilig';
o134(!privilegedOpsDefinitionValid($ongeldig), 'contract weigert installatiepad buiten de vaste artifactallowlist');
$ongeldig = $tools[0] ?? [];
$ongeldig['version'] = 'sha256-000000000000';
o134(!privilegedOpsDefinitionValid($ongeldig), 'contract weigert versie die niet bij de verwachte hash hoort');
$ongeldig = $tools[0] ?? [];
$ongeldig['expected_mode'] = 0777;
o134(!privilegedOpsDefinitionValid($ongeldig), 'contract weigert verruimde artifactrechten');

$tmp = sys_get_temp_dir() . '/rc045-ops-drift-' . bin2hex(random_bytes(5));
@mkdir($tmp, 0700, true);
try {
    $fixture = $tmp . '/tool';
    $inhoud = "#!/bin/sh\necho veilig\n";
    file_put_contents($fixture, $inhoud);
    chmod($fixture, 0755);
    clearstatcache(true, $fixture);
    $stat = lstat($fixture);
    if (!is_array($stat)) throw new RuntimeException('Testfixture kon niet worden gestat.');
    $hash = hash('sha256', $inhoud);
    $uid = (int)$stat['uid'];
    $gid = (int)$stat['gid'];

    $status = privilegedOpsMeasureFile($fixture, $hash, $uid, $gid, 0755, true);
    o134(($status['status'] ?? '') === 'ok' && hash_equals($hash, (string)($status['installed_sha256'] ?? '')), 'identieke executable met verwachte metadata rapporteert ok');

    file_put_contents($fixture, $inhoud . '# drift\n');
    clearstatcache(true, $fixture);
    $status = privilegedOpsMeasureFile($fixture, $hash, $uid, $gid, 0755, true);
    o134(($status['status'] ?? '') === 'drift' && ($status['reason'] ?? '') === 'hash_mismatch', 'inhoudswijziging wordt als drift gedetecteerd');

    file_put_contents($fixture, $inhoud);
    chmod($fixture, 0644);
    clearstatcache(true, $fixture);
    $status = privilegedOpsMeasureFile($fixture, $hash, $uid, $gid, 0755, true);
    o134(($status['status'] ?? '') === 'unsafe' && ($status['reason'] ?? '') === 'metadata', 'afwijkende executable-metadata wordt als unsafe gedetecteerd');

    chmod($fixture, 0440);
    clearstatcache(true, $fixture);
    $status = privilegedOpsMeasureFile($fixture, $hash, $uid, $gid, 0440, false);
    o134(($status['status'] ?? '') === 'ok', 'root-only configuratie kan exact als 0440 niet-executable worden gevalideerd');

    @unlink($fixture);
    $status = privilegedOpsMeasureFile($fixture, $hash, $uid, $gid, 0755, true);
    o134(($status['status'] ?? '') === 'missing', 'ontbrekend privileged artifact wordt gedetecteerd');

    $doel = $tmp . '/doel';
    file_put_contents($doel, $inhoud);
    chmod($doel, 0755);
    symlink($doel, $fixture);
    $status = privilegedOpsMeasureFile($fixture, $hash, $uid, $gid, 0755, true);
    o134(($status['status'] ?? '') === 'unsafe' && ($status['reason'] ?? '') === 'symlink', 'symlink op privileged installatiepad faalt gesloten');
} finally {
    rm134($tmp);
}

$entryBron = (string)file_get_contents($root . '/ops/vps-test-deploy/verenigingsplatform-github-entry');
$e2eBron = (string)file_get_contents($root . '/ops/vps-test-deploy/verenigingsplatform-github-e2e');
$sudoersBron = (string)file_get_contents($root . '/ops/vps-test-deploy/verenigingsplatform-github-e2e.sudoers');
$installer = (string)file_get_contents($root . '/bin/install-vps-authenticated-e2e-gateway.sh');
o134(hash_equals($entryBron, (string)heredoc134($installer, 'tmp_entry', 'ENTRY')), 'E2E-installer gebruikt bytegelijk dezelfde forced-command entry als de canonieke ops-bron');
o134(hash_equals($e2eBron, (string)heredoc134($installer, 'tmp_e2e', 'E2E')), 'E2E-installer gebruikt bytegelijk dezelfde rootwrapper als de canonieke ops-bron');
o134(hash_equals($sudoersBron, (string)heredoc134($installer, 'tmp_sudoers', 'SUDOERS')), 'E2E-installer gebruikt bytegelijk dezelfde sudoersregels als de canonieke ops-bron');
o134(str_contains($entryBron, "'e2e check'") && str_contains($entryBron, "'e2e apply'") && str_contains($entryBron, "'e2e cleanup'"), 'canonieke forced-command entry bevat uitsluitend de bedoelde E2E-subcommando’s naast deploy');
o134(substr_count($sudoersBron, 'NOPASSWD: /usr/local/sbin/verenigingsplatform-github-e2e ') === 3, 'canonieke sudoersbron bevat exact drie beperkte E2E-regels');

$executor = (string)file_get_contents($root . '/bin/control-plane-executor.php');
$observability = (string)file_get_contents($root . '/app/control-plane/control-plane-observability.php');
o134(str_contains($executor, "'/app/deployment/privileged-ops-contract.php'") && str_contains($executor, "'privileged_ops'=>privilegedOpsSnapshot()"), 'root-executor meet privileged integriteit in de beveiligde platformsnapshot');
o134(!str_contains($observability, 'privilegedOpsSnapshot()') && str_contains($observability, 'cpAdminPrivilegedOpsUitSnapshot($snapshot)'), 'niet-root weblaag leest uitsluitend de root-gemeten integriteitsstatus');
o134(str_contains($observability, 'wijkt af van de versie die de actieve release verwacht'), 'platformbeheer waarschuwt bij privileged ops-drift');
o134(str_contains($observability, "\$warnings[] = 'Privileged deploytooling '") && !str_contains($observability, "\$critical[] = 'Privileged deploytooling '"), 'ops-integriteit blijft waarschuwing en introduceert geen UI-only lifecycleblokkade');
o134(str_contains($observability, 'Integriteitsstatus van privileged deploytooling ontbreekt in de root-snapshot.'), 'ontbrekende rootmeting wordt zichtbaar in plaats van stil als gezond beschouwd');

echo "Privileged ops drift audit: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
