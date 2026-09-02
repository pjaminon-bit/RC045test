<?php
$root = dirname(__DIR__);
$workflow = (string) file_get_contents($root . '/.github/workflows/deploy-vps-test.yml');
$ok = 0;
$fout = 0;

function ci141(bool $conditie, string $label): void {
    global $ok, $fout;
    if ($conditie) {
        $ok++;
        echo "OK: {$label}\n";
        return;
    }
    $fout++;
    fwrite(STDERR, "FOUT: {$label}\n");
}

function ci141Job(string $workflow, string $naam): string {
    $marker = "\n  {$naam}:\n";
    $start = strpos($workflow, $marker);
    if ($start === false) return '';
    $start += 1;
    $rest = substr($workflow, $start);
    if (preg_match('/\n  [a-z0-9][a-z0-9-]*:\n/', $rest, $match, PREG_OFFSET_CAPTURE, strlen("  {$naam}:\n")) === 1) {
        return substr($rest, 0, $match[0][1]);
    }
    return $rest;
}

function ci141Header(string $job): string {
    $steps = strpos($job, "\n    steps:\n");
    return $steps === false ? $job : substr($job, 0, $steps);
}

$deploy = ci141Job($workflow, 'deploy-vps-test');
$setup = ci141Job($workflow, 'e2e-fixture-setup');
$browser = ci141Job($workflow, 'live-authenticated');
$cleanup = ci141Job($workflow, 'e2e-fixture-cleanup');
$voorJobs = strstr($workflow, "\njobs:\n", true);

ci141($deploy !== '' && $setup !== '' && $browser !== '' && $cleanup !== '', 'deploy, fixture-setup, browser en fixture-cleanup zijn afzonderlijke jobs');
ci141(is_string($voorJobs) && !str_contains($voorJobs, 'id-token: write'), 'OIDC is niet workflow-breed beschikbaar');
ci141(str_contains($deploy, 'id-token: write') && str_contains($setup, 'id-token: write') && str_contains($cleanup, 'id-token: write'), 'alleen privileged netwerkjobs krijgen OIDC voor Tailscale WIF');
ci141(str_contains($browser, "permissions:\n      contents: read") && !str_contains($browser, 'id-token: write'), 'repositorygestuurde browserjob heeft uitsluitend contents:read');
ci141(!str_contains($browser, 'environment: vps-test'), 'browserjob is niet gekoppeld aan het secret-bearing vps-test environment');

$verbodenBrowser = [
    'secrets.' => 'GitHub secrets',
    'VPS_TEST_DEPLOY_KEY' => 'deploykey',
    'VPS_TEST_SSH_KNOWN_HOSTS' => 'SSH hosttrust-secret',
    'TS_OAUTH_CLIENT_ID' => 'Tailscale client-id',
    'TS_AUDIENCE' => 'Tailscale audience',
    'tailscale/github-action@' => 'Tailscale action',
    'ssh ' => 'SSH-client',
    '$HOME/.ssh' => 'persistente SSH-directory',
];
foreach ($verbodenBrowser as $needle => $label) {
    ci141(!str_contains($browser, $needle), "browserjob bevat geen {$label}");
}
ci141(str_contains($browser, 'actions/checkout@11d5960a326750d5838078e36cf38b85af677262'), 'alle repositorycheckout blijft uitsluitend in de unprivileged browserjob');
ci141(str_contains($browser, 'npm ci --ignore-scripts') && str_contains($browser, 'npx playwright install --with-deps chromium'), 'browsertooling wordt geïnstalleerd zonder privileged credentials');
ci141(str_contains($browser, 'E2E_PASSWORD: ${{ needs.e2e-fixture-setup.outputs.e2e_password }}'), 'browser ontvangt uitsluitend de ephemeral applicatiecredential van fixture-setup');
$playwrightPos = strpos($browser, 'npx playwright test tests/live-dev-authenticated.spec.js');
$passwordPos = strpos($browser, 'E2E_PASSWORD: ${{ needs.e2e-fixture-setup.outputs.e2e_password }}');
$npmPos = strpos($browser, 'npm ci --ignore-scripts');
ci141(is_int($npmPos) && is_int($passwordPos) && is_int($playwrightPos) && $npmPos < $passwordPos && $passwordPos < $playwrightPos, 'ephemeral wachtwoord wordt pas aan de Playwright-stap beschikbaar gemaakt');

ci141(!str_contains($setup, 'actions/checkout@') && !str_contains($setup, 'npm ') && !str_contains($setup, 'npx ') && !str_contains($setup, 'node '), 'fixture-setup voert geen repositorygestuurde Node/Playwright-code uit');
ci141(!str_contains($cleanup, 'actions/checkout@') && !str_contains($cleanup, 'npm ') && !str_contains($cleanup, 'npx ') && !str_contains($cleanup, 'node '), 'fixture-cleanup voert geen repositorygestuurde Node/Playwright-code uit');
ci141(!str_contains(ci141Header($deploy), 'secrets.') && !str_contains(ci141Header($setup), 'secrets.') && !str_contains(ci141Header($cleanup), 'secrets.'), 'langlevende VPS/Tailscale-secrets staan niet in job-level env of jobmetadata');
ci141(substr_count($workflow, 'VPS_TEST_DEPLOY_KEY: ${{ secrets.VPS_TEST_DEPLOY_KEY }}') === 3, 'deploykey wordt alleen aan drie minimale SSH-stappen gekoppeld');
ci141(substr_count($workflow, 'VPS_TEST_SSH_KNOWN_HOSTS: ${{ secrets.VPS_TEST_SSH_KNOWN_HOSTS }}') === 3, 'SSH hosttrust-secret wordt alleen aan drie minimale SSH-stappen gekoppeld');
ci141(substr_count($workflow, 'oauth-client-id: ${{ secrets.TS_OAUTH_CLIENT_ID }}') === 3 && substr_count($workflow, 'audience: ${{ secrets.TS_AUDIENCE }}') === 3, 'Tailscale WIF-secrets worden uitsluitend direct aan de drie privileged Tailscale-actions gegeven');
ci141(substr_count($workflow, 'tailscale/github-action@306e68a486fd2350f2bfc3b19fcd143891a4a2d8') === 3 && substr_count($workflow, 'version: 1.94.2') === 3, 'alle privileged netwerkjobs gebruiken dezelfde gepinde Tailscale action en clientversie');

ci141(substr_count($workflow, 'mktemp -d "$RUNNER_TEMP/') === 3 && substr_count($workflow, 'rm -rf "$ssh_dir"') === 3, 'iedere SSH-stap gebruikt een tijdelijke keydirectory die via EXIT wordt verwijderd');
ci141(substr_count($workflow, 'trap finish EXIT') === 3 && substr_count($workflow, 'trap - EXIT') === 3, 'tijdelijke SSH-credentials hebben fail-safe cleanuptraps');
ci141(!str_contains($workflow, '$HOME/.ssh'), 'workflow schrijft geen deploycredential meer naar de persistente runner-home');

$check = strpos($setup, "'e2e check'");
$stale = strpos($setup, "'e2e cleanup'", is_int($check) ? $check : 0);
$apply = strpos($setup, "'e2e apply'");
ci141(is_int($check) && is_int($stale) && is_int($apply) && $check < $stale && $stale < $apply, 'fixture-setup behoudt check → stale cleanup → apply');
ci141(str_contains($setup, 'gateway_ready=1') && str_contains($setup, 'if [[ "$rc" -ne 0 && "$gateway_ready" -eq 1 ]]'), 'fixture-setup ruimt een gedeeltelijke fixture fail-safe op bij latere fouten');
ci141(str_contains($cleanup, "if: \${{ always() && needs.e2e-fixture-setup.result != 'skipped' }}") && str_contains($cleanup, "'e2e cleanup'"), 'aparte cleanupjob draait ook na browserfouten');
ci141(str_contains($setup, 'secrets.token_urlsafe(48)') && str_contains($setup, "printf 'e2e_password=%s\\n' \"\$password\" >> \"\$GITHUB_OUTPUT\""), 'fixture-setup genereert een eenmalige applicatiecredential en geeft alleen die door');
ci141(!str_contains($workflow, 'VPS_TEST_E2E_PASSWORD') && !str_contains($workflow, 'VPS_TEST_ADMIN_USER') && !str_contains($workflow, 'VPS_TEST_MEMBER_USER'), 'geen permanente authenticated E2E-credentials zijn opnieuw geïntroduceerd');

echo "Security CI credential isolation regression: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
