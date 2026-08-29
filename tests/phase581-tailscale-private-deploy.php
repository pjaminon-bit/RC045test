<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function c581(bool $cond, string $label): void {
    global $ok, $fout;
    if ($cond) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

$deploy = (string) file_get_contents($root . '/.github/workflows/deploy-vps-test.yml');
$docs = (string) file_get_contents($root . '/docs/GITHUB-VPS-TEST-DEPLOYMENT.md');

c581(str_contains($deploy, 'id-token: write'), 'deployworkflow kan uitsluitend via GitHub OIDC een Tailscale workload identity ophalen');
c581(str_contains($deploy, 'tailscale/github-action@306e68a486fd2350f2bfc3b19fcd143891a4a2d8'), 'Tailscale GitHub Action is op een vaste v4-commit gepind');
c581(str_contains($deploy, 'version: 1.94.2'), 'Tailscale clientversie is deterministisch en ondersteunt workload identity federation');
c581(str_contains($deploy, 'secrets.TS_OAUTH_CLIENT_ID') && str_contains($deploy, 'secrets.TS_AUDIENCE'), 'workflow gebruikt federated identity client-ID en audience');
c581(!str_contains($deploy, 'oauth-secret:') && !str_contains($deploy, 'authkey:'), 'workflow bewaart geen langdurig Tailscale OAuth-secret of authkey');
c581(str_contains($deploy, 'tags: tag:github-rc045test'), 'ephemeral GitHub-runner krijgt een afzonderlijke least-privilege tag');
c581(str_contains($deploy, "'100.104.242.66'") && str_contains($deploy, 'VPS_TAILSCALE_HOST'), 'SSH-doel is het private Tailscale-adres van platform');
c581(!str_contains($deploy, "VPS_SSH_HOST: ${{ vars.VPS_TEST_SSH_HOST || 'vps.holox.nl' }}"), 'publieke VPS-hostnaam is niet langer het netwerkdoel voor SSH');
c581(str_contains($deploy, '-o HostKeyAlias="$VPS_SSH_HOST_ALIAS"') && str_contains($deploy, 'VPS_SSH_HOST_ALIAS: vps.holox.nl'), 'bestaande out-of-band geverifieerde SSH-hostkey blijft via HostKeyAlias afdwingbaar');
c581(str_contains($deploy, 'StrictHostKeyChecking=yes') && !str_contains($deploy, 'ssh-keyscan'), 'SSH blijft fail-closed met gepinde hosttrust en zonder TOFU');
c581(str_contains($deploy, "github.event_name == 'workflow_dispatch' && github.ref == 'refs/heads/main'"), 'handmatige privileged deploy kan alleen vanaf main worden gestart');
c581(str_contains($deploy, 'ping: ${{ vars.VPS_TEST_TAILSCALE_HOST') && str_contains($deploy, '100.104.242.66'), 'Tailscale-connectiviteit wordt vóór SSH actief geverifieerd');
c581(str_contains($docs, 'Tailscale') && str_contains($docs, 'tag:github-rc045test') && str_contains($docs, 'TS_OAUTH_CLIENT_ID') && str_contains($docs, 'TS_AUDIENCE'), 'documentatie beschrijft private Tailscale/OIDC-keten');
c581(str_contains($docs, 'geen publieke SSH-poort') || str_contains($docs, 'publieke SSH-poort hoeft niet open'), 'documentatie borgt dat internet-SSH gesloten kan blijven');

echo "Phase 5.8.1 Tailscale private deploy: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
