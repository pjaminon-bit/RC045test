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

require_once $root . '/app/deployment/privileged-ops-contract.php';

$contract = privilegedOpsContract();
o134(($contract['schema'] ?? null) === 1 && ($contract['phase'] ?? '') === 'privileged-ops-integrity', 'privileged ops-contract heeft vast schema');
$tools = is_array($contract['tools'] ?? null) ? $contract['tools'] : [];
o134(count($tools) === 2, 'contract bewaakt entrypoint en root-deploywrapper');

$verwachtePaden = [
    'github-entry' => '/usr/local/bin/verenigingsplatform-github-entry',
    'github-deploy' => '/usr/local/sbin/verenigingsplatform-github-deploy',
];
foreach ($tools as $tool) {
    $id = (string)($tool['id'] ?? '');
    $source = $root . '/' . (string)($tool['source_path'] ?? '');
    $sha = is_file($source) ? hash_file('sha256', $source) : false;
    o134(privilegedOpsDefinitionValid($tool), 'tooldefinitie ' . $id . ' is strikt geldig');
    o134(isset($verwachtePaden[$id]) && ($tool['installed_path'] ?? '') === $verwachtePaden[$id], 'tool ' . $id . ' gebruikt vast allowlisted installatiepad');
    o134(is_string($sha) && hash_equals((string)$tool['expected_sha256'], $sha), 'verwachte SHA-256 van ' . $id . ' blijft gelijk aan repositorybron');
    o134(($tool['version'] ?? '') === 'sha256-' . substr((string)$tool['expected_sha256'], 0, 12), 'zichtbare toolversie ' . $id . ' is aan SHA-256 gebonden');
}

$ongeldig = $tools[0] ?? [];
$ongeldig['installed_path'] = '/tmp/onveilig';
o134(!privilegedOpsDefinitionValid($ongeldig), 'contract weigert installatiepad buiten /usr/local/bin of /usr/local/sbin');
$ongeldig = $tools[0] ?? [];
$ongeldig['version'] = 'sha256-000000000000';
o134(!privilegedOpsDefinitionValid($ongeldig), 'contract weigert versie die niet bij de verwachte hash hoort');

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

    $status = privilegedOpsMeasureFile($fixture, $hash, $uid, $gid, 0755);
    o134(($status['status'] ?? '') === 'ok' && hash_equals($hash, (string)($status['installed_sha256'] ?? '')), 'identieke executable met verwachte metadata rapporteert ok');

    file_put_contents($fixture, $inhoud . '# drift\n');
    clearstatcache(true, $fixture);
    $status = privilegedOpsMeasureFile($fixture, $hash, $uid, $gid, 0755);
    o134(($status['status'] ?? '') === 'drift' && ($status['reason'] ?? '') === 'hash_mismatch', 'inhoudswijziging wordt als drift gedetecteerd');

    file_put_contents($fixture, $inhoud);
    chmod($fixture, 0644);
    clearstatcache(true, $fixture);
    $status = privilegedOpsMeasureFile($fixture, $hash, $uid, $gid, 0755);
    o134(($status['status'] ?? '') === 'unsafe' && ($status['reason'] ?? '') === 'metadata', 'afwijkende executable-metadata wordt als unsafe gedetecteerd');

    @unlink($fixture);
    $status = privilegedOpsMeasureFile($fixture, $hash, $uid, $gid, 0755);
    o134(($status['status'] ?? '') === 'missing', 'ontbrekende privileged tool wordt gedetecteerd');

    $doel = $tmp . '/doel';
    file_put_contents($doel, $inhoud);
    chmod($doel, 0755);
    symlink($doel, $fixture);
    $status = privilegedOpsMeasureFile($fixture, $hash, $uid, $gid, 0755);
    o134(($status['status'] ?? '') === 'unsafe' && ($status['reason'] ?? '') === 'symlink', 'symlink op privileged installatiepad faalt gesloten');
} finally {
    rm134($tmp);
}

$observability = (string)file_get_contents($root . '/app/control-plane/control-plane-observability.php');
o134(str_contains($observability, "'privileged_ops'=>privilegedOpsSnapshot()"), 'platformobservability meet privileged tooling read-only');
o134(str_contains($observability, 'wijkt af van de versie die de actieve release verwacht'), 'platformbeheer waarschuwt bij privileged ops-drift');
o134(str_contains($observability, "$warnings[] = 'Privileged deploytooling '") && !str_contains($observability, "$critical[] = 'Privileged deploytooling '"), 'ops-integriteit blijft waarschuwing en introduceert geen UI-only lifecycleblokkade');

echo "Privileged ops drift audit: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
