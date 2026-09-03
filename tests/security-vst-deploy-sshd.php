<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;
function o136(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

require_once $root . '/app/deployment/privileged-ops-contract.php';

$rel = 'ops/vps-test-deploy/00-verenigingsplatform-vst-deploy.conf';
$pad = $root . '/' . $rel;
$bron = is_file($pad) ? (string)file_get_contents($pad) : '';
o136($bron !== '', 'canonieke vst-deploy sshd-policy bestaat');
o136(str_starts_with($bron, '# RC045test #136'), 'policy is expliciet aan issue #136 gekoppeld');
o136(substr_count($bron, 'Match User vst-deploy') === 1, 'policy bevat exact één Match User vst-deploy');
o136(substr_count($bron, "\nMatch All\n") === 1 && str_ends_with($bron, "Match All\n"), 'Match-context wordt expliciet met Match All beëindigd');

$verwacht = [
    'AuthenticationMethods' => 'publickey',
    'PasswordAuthentication' => 'no',
    'KbdInteractiveAuthentication' => 'no',
    'PermitTTY' => 'no',
    'AllowTcpForwarding' => 'no',
    'AllowStreamLocalForwarding' => 'no',
    'X11Forwarding' => 'no',
    'AllowAgentForwarding' => 'no',
    'PermitTunnel' => 'no',
    'PermitUserRC' => 'no',
    'ForceCommand' => '/usr/local/bin/verenigingsplatform-github-entry',
];
foreach ($verwacht as $keyword => $waarde) {
    $regel = '    ' . $keyword . ' ' . $waarde;
    o136(substr_count($bron, $regel) === 1, "sshd-policy dwingt exact {$keyword} {$waarde} af");
}
o136(!str_contains($bron, '/srv/verenigingsplatform/current') && !str_contains($bron, '/srv/verenigingsplatform/releases/'), '#157: sshd-policy executeert geen releasepad');
o136(!str_contains(strtolower($bron), 'sudoers') && !str_contains($bron, 'NOPASSWD'), '#137: sshd-policy verbreedt of wijzigt sudoers niet');

$contract = privilegedOpsContract();
$byId = [];
foreach (($contract['tools'] ?? []) as $tool) {
    if (is_array($tool)) $byId[(string)($tool['id'] ?? '')] = $tool;
}
$sshd = $byId['github-sshd-policy'] ?? [];
o136(($sshd['source_path'] ?? '') === $rel, '#135: sshd-policy heeft exact canoniek bronpad');
o136(($sshd['installed_path'] ?? '') === '/etc/ssh/sshd_config.d/00-verenigingsplatform-vst-deploy.conf', '#135: sshd-policy heeft exact allowlisted installatiepad');
o136(($sshd['expected_uid'] ?? null) === 0 && ($sshd['expected_gid'] ?? null) === 0, '#135: sshd-policy vereist root:root');
o136(($sshd['expected_mode'] ?? null) === 0644 && ($sshd['expected_executable'] ?? null) === false, '#135: sshd-policy vereist mode 0644 en non-executable');
$hash = is_file($pad) ? hash_file('sha256', $pad) : false;
o136(is_string($hash) && hash_equals((string)($sshd['expected_sha256'] ?? ''), $hash), '#135: sshd-policy SHA-256 is byte-exact aan repositorybron gebonden');
o136(is_array($sshd) && privilegedOpsDefinitionValid($sshd), '#135: sshd-policy is een geldige immutable artifactdefinitie');

$entry = (string)file_get_contents($root . '/ops/vps-test-deploy/verenigingsplatform-github-entry');
o136(str_contains($entry, '${SSH_ORIGINAL_COMMAND:-}'), 'ForceCommand-doel blijft uitsluitend SSH_ORIGINAL_COMMAND routeren');
o136(str_contains($entry, "^deploy[[:space:]]+([0-9a-f]{40})$")
    && str_contains($entry, "'e2e check'")
    && str_contains($entry, "'e2e apply'")
    && str_contains($entry, "'e2e cleanup'"), 'bestaand deploy- en E2E-commandcontract blijft exact aanwezig');

$deployDoc = (string)file_get_contents($root . '/docs/GITHUB-VPS-TEST-DEPLOYMENT.md');
o136(str_contains($deployDoc, 'restrict,command="/usr/local/bin/verenigingsplatform-github-entry"'), 'authorized_keys behoudt onafhankelijke restrict+forced-command laag');
$hardeningDoc = (string)file_get_contents($root . '/docs/VST-DEPLOY-SSHD-HARDENING.md');
foreach (['/usr/sbin/sshd -t', '/usr/sbin/sshd -T -C', 'systemctl reload ssh', 'ROLLBACK', 'e2e check', 'e2e apply', 'e2e cleanup'] as $bewijs) {
    o136(str_contains($hardeningDoc, $bewijs), 'operationele #136-procedure bevat: ' . $bewijs);
}

echo "Issue #136 vst-deploy sshd hardening: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
