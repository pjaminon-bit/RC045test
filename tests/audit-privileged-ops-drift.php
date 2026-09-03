<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;
function o135(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}
function rm135(string $pad): void
{
    if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $naam) {
        if ($naam === '.' || $naam === '..') continue;
        rm135($pad . '/' . $naam);
    }
    @rmdir($pad);
}
function heredoc135(string $script, string $variabele, string $marker): ?string
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
o135(($contract['schema'] ?? null) === 1 && ($contract['phase'] ?? '') === 'privileged-ops-integrity', 'privileged ops-contract heeft vast schema');
$tools = is_array($contract['tools'] ?? null) ? $contract['tools'] : [];
$byId = [];
foreach ($tools as $tool) if (is_array($tool)) $byId[(string)($tool['id'] ?? '')] = $tool;

$gateway = [
    'github-entry' => ['/usr/local/bin/verenigingsplatform-github-entry', 0755, true],
    'github-deploy' => ['/usr/local/sbin/verenigingsplatform-github-deploy', 0755, true],
    'github-e2e' => ['/usr/local/sbin/verenigingsplatform-github-e2e', 0755, true],
    'github-e2e-sudoers' => ['/etc/sudoers.d/verenigingsplatform-github-e2e', 0440, false],
    'github-sshd-policy' => ['/etc/ssh/sshd_config.d/00-verenigingsplatform-vst-deploy.conf', 0644, false],
];
o135(count(array_intersect(array_keys($gateway), array_keys($byId))) === 5, 'alle vijf GitHub/E2E gateway-artifacts zitten in hetzelfde contract');
o135(isset($byId['host-php']), '#157 host-launcher blijft in hetzelfde privileged hostcontract bewaakt');
o135(count($tools) === 6, 'contract bevat vijf gateway-artifacts plus de #157 host-launcher');

foreach ($tools as $tool) {
    if (!is_array($tool)) { o135(false, 'contracttool is een array'); continue; }
    $id = (string)($tool['id'] ?? '');
    $source = $root . '/' . (string)($tool['source_path'] ?? '');
    $sha = is_file($source) ? hash_file('sha256', $source) : false;
    o135(privilegedOpsDefinitionValid($tool), 'artifactdefinitie ' . $id . ' is strikt geldig');
    o135(($tool['expected_uid'] ?? null) === 0 && ($tool['expected_gid'] ?? null) === 0, 'artifact ' . $id . ' vereist exact root:root');
    o135(is_string($sha) && hash_equals((string)$tool['expected_sha256'], $sha), 'repositorybron ↔ verwachte SHA-256 is byte-exact voor ' . $id);
    o135(($tool['version'] ?? '') === 'sha256-' . substr((string)$tool['expected_sha256'], 0, 12), 'zichtbare artifactversie ' . $id . ' is aan SHA-256 gebonden');
}
foreach ($gateway as $id => [$pad, $mode, $executable]) {
    $tool = $byId[$id] ?? [];
    o135(($tool['installed_path'] ?? '') === $pad, 'gateway-artifact ' . $id . ' gebruikt uitsluitend exact allowlisted installatiepad');
    o135(($tool['expected_mode'] ?? null) === $mode && ($tool['expected_executable'] ?? null) === $executable, 'gateway-artifact ' . $id . ' heeft exact mode/executable-contract');
}

$ongeldig = $byId['github-entry'] ?? [];
$ongeldig['installed_path'] = '/tmp/onveilig';
o135(!privilegedOpsDefinitionValid($ongeldig), 'contract weigert installatiepad buiten de exacte allowlist');
$ongeldig = $byId['github-e2e-sudoers'] ?? [];
$ongeldig['installed_path'] = '/etc/sudoers.d/ander-bestand';
o135(!privilegedOpsDefinitionValid($ongeldig), 'sudoersvertrouwen is uitsluitend exact /etc/sudoers.d/verenigingsplatform-github-e2e');
$ongeldig = $byId['github-e2e-sudoers'] ?? [];
$ongeldig['installed_path'] = '/etc/verenigingsplatform/verenigingsplatform-github-e2e';
o135(!privilegedOpsDefinitionValid($ongeldig), 'contract accepteert geen brede /etc-directorytrust');
$ongeldig = $byId['github-sshd-policy'] ?? [];
$ongeldig['installed_path'] = '/etc/ssh/sshd_config.d/99-anders.conf';
o135(!privilegedOpsDefinitionValid($ongeldig), 'sshd-policyvertrouwen is uitsluitend exact het #136 drop-inpad');
$ongeldig = $byId['github-entry'] ?? [];
$ongeldig['expected_mode'] = 0777;
o135(!privilegedOpsDefinitionValid($ongeldig), 'contract weigert verruimde artifactrechten');
$ongeldig = $byId['github-entry'] ?? [];
$ongeldig['expected_executable'] = false;
o135(!privilegedOpsDefinitionValid($ongeldig), 'contract weigert afwijkende executable-eigenschap');

$tmp = sys_get_temp_dir() . '/rc045-ops-drift-' . bin2hex(random_bytes(5));
@mkdir($tmp, 0700, true);
try {
    foreach ($gateway as $id => [, $mode, $expectedExecutable]) {
        $fixture = $tmp . '/' . $id;
        $inhoud = "artifact={$id}\n";
        file_put_contents($fixture, $inhoud);
        chmod($fixture, $mode);
        clearstatcache(true, $fixture);
        $stat = lstat($fixture);
        if (!is_array($stat)) throw new RuntimeException('Testfixture kon niet worden gestat: ' . $id);
        $hash = hash('sha256', $inhoud);
        $uid = (int)$stat['uid'];
        $gid = (int)$stat['gid'];

        $status = privilegedOpsMeasureFile($fixture, $hash, $uid, $gid, $mode, $expectedExecutable);
        o135(($status['status'] ?? '') === 'ok' && hash_equals($hash, (string)($status['installed_sha256'] ?? '')), $id . ': geldige canonieke toestand rapporteert ok');

        $status = privilegedOpsMeasureFile($fixture, $hash, $uid + 1, $gid, $mode, $expectedExecutable);
        o135(($status['status'] ?? '') === 'unsafe' && ($status['reason'] ?? '') === 'metadata', $id . ': verkeerde owner wordt gedetecteerd');
        $status = privilegedOpsMeasureFile($fixture, $hash, $uid, $gid + 1, $mode, $expectedExecutable);
        o135(($status['status'] ?? '') === 'unsafe' && ($status['reason'] ?? '') === 'metadata', $id . ': verkeerde group wordt gedetecteerd');

        $wrongMode = $mode === 0755 ? 0700 : 0400;
        chmod($fixture, $wrongMode);
        clearstatcache(true, $fixture);
        $status = privilegedOpsMeasureFile($fixture, $hash, $uid, $gid, $mode, $expectedExecutable);
        o135(($status['status'] ?? '') === 'unsafe' && ($status['reason'] ?? '') === 'metadata', $id . ': verkeerde mode wordt gedetecteerd');
        chmod($fixture, $mode);
        clearstatcache(true, $fixture);

        $status = privilegedOpsMeasureFile($fixture, $hash, $uid, $gid, $mode, !$expectedExecutable);
        o135(($status['status'] ?? '') === 'unsafe' && ($status['reason'] ?? '') === 'metadata', $id . ': executable-bit/eigenschap afwijking wordt gedetecteerd');

        chmod($fixture, 0600);
        file_put_contents($fixture, $inhoud . "drift\n");
        chmod($fixture, $mode);
        clearstatcache(true, $fixture);
        $status = privilegedOpsMeasureFile($fixture, $hash, $uid, $gid, $mode, $expectedExecutable);
        o135(($status['status'] ?? '') === 'drift' && ($status['reason'] ?? '') === 'hash_mismatch', $id . ': verkeerde hash/content wordt gedetecteerd');
        chmod($fixture, 0600);
        file_put_contents($fixture, $inhoud);
        chmod($fixture, $mode);
        clearstatcache(true, $fixture);

        @unlink($fixture);
        $status = privilegedOpsMeasureFile($fixture, $hash, $uid, $gid, $mode, $expectedExecutable);
        o135(($status['status'] ?? '') === 'missing', $id . ': ontbrekend bestand wordt gedetecteerd');

        $doel = $tmp . '/' . $id . '.target';
        file_put_contents($doel, $inhoud);
        chmod($doel, $mode);
        symlink($doel, $fixture);
        $status = privilegedOpsMeasureFile($fixture, $hash, $uid, $gid, $mode, $expectedExecutable);
        o135(($status['status'] ?? '') === 'unsafe' && ($status['reason'] ?? '') === 'symlink', $id . ': symlink wordt fail-closed gedetecteerd');
        @unlink($fixture);
        @unlink($doel);

        mkdir($fixture, 0700);
        $status = privilegedOpsMeasureFile($fixture, $hash, $uid, $gid, $mode, $expectedExecutable);
        o135(($status['status'] ?? '') === 'unsafe' && ($status['reason'] ?? '') === 'not_regular_readable', $id . ': verkeerd bestandstype wordt gedetecteerd');
        @rmdir($fixture);
    }
} finally {
    rm135($tmp);
}

$publishedTools = [];
foreach ($tools as $tool) {
    $publishedTools[] = [
        'id'=>$tool['id'],
        'version'=>$tool['version'],
        'status'=>'ok',
        'expected_sha256'=>$tool['expected_sha256'],
        'installed_sha256'=>$tool['expected_sha256'],
        'reason'=>null,
    ];
}
$published = ['schema'=>1,'status'=>'ok','tools'=>$publishedTools];
$validated = privilegedOpsPublishedSnapshot($published);
o135(($validated['status'] ?? '') === 'ok' && count($validated['tools'] ?? []) === 6, 'volledige root-gepubliceerde artifactset valideert als ok');
$missingPublished = $published;
array_pop($missingPublished['tools']);
o135((privilegedOpsPublishedSnapshot($missingPublished)['status'] ?? '') === 'unknown', 'root-snapshot die één artifact mist faalt als unknown');
$tamperedPublished = $published;
$tamperedPublished['tools'][0]['expected_sha256'] = str_repeat('0', 64);
o135((privilegedOpsPublishedSnapshot($tamperedPublished)['status'] ?? '') === 'unknown', 'root-snapshot met verouderde/onjuiste contracthash faalt als unknown');

$entryBron = (string)file_get_contents($root . '/ops/vps-test-deploy/verenigingsplatform-github-entry');
$e2eBron = (string)file_get_contents($root . '/ops/vps-test-deploy/verenigingsplatform-github-e2e');
$sudoersBron = (string)file_get_contents($root . '/ops/vps-test-deploy/verenigingsplatform-github-e2e.sudoers');
$installer = (string)file_get_contents($root . '/bin/install-vps-authenticated-e2e-gateway.sh');
o135(hash_equals($entryBron, (string)heredoc135($installer, 'tmp_entry', 'ENTRY')), 'E2E-installer gebruikt bytegelijk dezelfde forced-command entry als canonieke ops-bron');
o135(hash_equals($e2eBron, (string)heredoc135($installer, 'tmp_e2e', 'E2E')), 'E2E-installer gebruikt bytegelijk dezelfde rootwrapper als canonieke ops-bron');
o135(hash_equals($sudoersBron, (string)heredoc135($installer, 'tmp_sudoers', 'SUDOERS')), 'E2E-installer gebruikt bytegelijk dezelfde sudoersregels als canonieke ops-bron');
$regels = array_values(array_filter(explode("\n", trim($sudoersBron)), static fn(string $r): bool => $r !== ''));
o135(count($regels) === 3, 'canonieke E2E-sudoers bevat exact drie regels');
o135($regels === [
    'vst-deploy ALL=(root) NOPASSWD: /usr/local/sbin/verenigingsplatform-github-e2e check',
    'vst-deploy ALL=(root) NOPASSWD: /usr/local/sbin/verenigingsplatform-github-e2e apply',
    'vst-deploy ALL=(root) NOPASSWD: /usr/local/sbin/verenigingsplatform-github-e2e cleanup',
], 'sudoers laat uitsluitend exact check/apply/cleanup op de E2E-wrapper toe');
o135(!str_contains($sudoersBron, '*') && !str_contains($sudoersBron, '/etc/') && !str_contains($sudoersBron, 'ALL=(ALL)'), 'sudoers bevat geen wildcard, /etc-padtrust of brede ALL-rootregel');
o135(str_contains($installer, '/usr/sbin/visudo -cf "$tmp_sudoers"') && str_contains($installer, '/usr/sbin/visudo -cf /etc/sudoers'), 'installer valideert sudoerssyntax vóór en na installatie via visudo');
if (is_executable('/usr/sbin/visudo')) {
    $cmd = '/usr/sbin/visudo -cf ' . escapeshellarg($root . '/ops/vps-test-deploy/verenigingsplatform-github-e2e.sudoers') . ' 2>&1';
    exec($cmd, $visudoOut, $visudoCode);
    o135($visudoCode === 0, 'canonieke E2E-sudoers is rechtstreeks visudo-valideerbaar');
}

$wrapper = (string)file_get_contents($root . '/bin/control-plane-integrity-wrapper.php');
$launcher = (string)file_get_contents($root . '/ops/vps-test-deploy/verenigingsplatform-host-php');
$deploy = (string)file_get_contents($root . '/ops/vps-test-deploy/verenigingsplatform-github-deploy');
$observability = (string)file_get_contents($root . '/app/control-plane/control-plane-observability.php');
o135(str_contains($wrapper, 'privilegedOpsSnapshot()') && str_contains($wrapper, "'/etc/verenigingsplatform/control-plane/runtime.json'"), 'root-owned host-wrapper meet exact contract en publiceert alleen via vaste control-plane config');
o135(!str_contains($wrapper, '/srv/verenigingsplatform/current') && !str_contains($wrapper, '/srv/verenigingsplatform/releases/'), '#135 rootmeting introduceert geen root→current/release-PHP-pad');
o135(str_contains($launcher, "control-plane) script='bin/control-plane-integrity-wrapper.php'"), 'host-launcher routeert control-plane via root-owned integriteitswrapper');
o135(str_contains($deploy, "host_launcher='/usr/local/sbin/verenigingsplatform-host-php'") && str_contains($deploy, '"$host_launcher" control-plane'), 'deploy blijft control-plane uitsluitend via #157 host-launcher uitvoeren');
o135(str_contains($e2eBron, '/usr/sbin/runuser -u "$runtime_user" -- /usr/bin/php8.5'), 'E2E-wrapper dropt privileges vóór release-PHP wordt uitgevoerd');
o135(!str_contains($e2eBron, 'exec /usr/bin/php8.5 "$script"'), 'E2E-wrapper voert release-PHP nooit rechtstreeks als root uit');
o135(!str_contains($observability, 'privilegedOpsSnapshot()') && str_contains($observability, 'privilegedOpsPublishedSnapshot('), 'niet-root weblaag consumeert uitsluitend root-gepubliceerde integriteitsstatus');
o135(str_contains($observability, 'Integriteitsstatus van privileged deploytooling ontbreekt of is verouderd in de root-snapshot.'), 'ontbrekende/verouderde rootmeting wordt zichtbaar en niet als gezond aangenomen');

foreach ([
    'ops/vps-test-deploy/verenigingsplatform-github-entry',
    'ops/vps-test-deploy/verenigingsplatform-github-deploy',
    'ops/vps-test-deploy/verenigingsplatform-github-e2e',
    'ops/vps-test-deploy/verenigingsplatform-host-php',
] as $script) {
    $cmd = '/usr/bin/bash -n ' . escapeshellarg($root . '/' . $script) . ' 2>&1';
    exec($cmd, $out, $code);
    o135($code === 0, 'bash syntax geldig: ' . $script);
}
$cmd = '/usr/bin/php -l ' . escapeshellarg($root . '/bin/control-plane-integrity-wrapper.php') . ' 2>&1';
exec($cmd, $out, $code);
o135($code === 0, 'PHP syntax geldig: control-plane-integrity-wrapper.php');

echo "Issue #135 privileged artifact integrity: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
