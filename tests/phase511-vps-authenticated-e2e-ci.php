<?php
$root = dirname(__DIR__); $ok = 0; $fout = 0;
function c511ci(bool $conditie, string $label): void {
    global $ok, $fout;
    if ($conditie) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}
function c511job(string $workflow, string $naam): string {
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
$deploy = (string)file_get_contents($root . '/.github/workflows/deploy-vps-test.yml');
$full = (string)file_get_contents($root . '/.github/workflows/full-regression.yml');
$authSpec = (string)file_get_contents($root . '/tests/live-dev-authenticated.spec.js');
$ephemeralCli = (string)file_get_contents($root . '/bin/vps-authenticated-e2e-ephemeral.php');
$deployJob = c511job($deploy, 'deploy-vps-test');
$setupJob = c511job($deploy, 'e2e-fixture-setup');
$browserJob = c511job($deploy, 'live-authenticated');
$cleanupJob = c511job($deploy, 'e2e-fixture-cleanup');
$tailHost = <<<'TXT'
VPS_TAILSCALE_HOST: ${{ vars.VPS_TEST_TAILSCALE_HOST || '100.104.242.66' }}
TXT;
$ciphertextOutput = <<<'TXT'
printf 'e2e_password_ciphertext=%s.%s\n' "$tag" "$ciphertext" >> "$GITHUB_OUTPUT"
TXT;
$ciphertextInput = <<<'TXT'
E2E_PASSWORD_CIPHERTEXT: ${{ needs.e2e-fixture-setup.outputs.e2e_password_ciphertext }}
TXT;
$passwordFileInput = <<<'TXT'
E2E_PASSWORD_FILE: ${{ steps.credential.outputs.password_file }}
TXT;

c511ci($deployJob !== '' && $setupJob !== '' && $browserJob !== '' && $cleanupJob !== '', 'authenticated E2E is opgesplitst in deploy, fixture-setup, browser en cleanup');
c511ci(str_contains($setupJob, 'needs: deploy-vps-test') && str_contains($browserJob, 'needs: e2e-fixture-setup'), 'authenticated E2E blijft verplicht na succesvolle VPS-deploy en fixture-setup');
c511ci(str_contains($cleanupJob, "if: \${{ always() && needs.e2e-fixture-setup.result != 'skipped' }}"), 'fixture-cleanup blijft fail-safe actief na browserfouten');
c511ci(substr_count($deploy, 'tailscale/github-action@306e68a486fd2350f2bfc3b19fcd143891a4a2d8') === 3 && substr_count($deploy, 'version: 1.94.2') === 3, 'alleen deploy, fixture-setup en cleanup gebruiken de gepinde Tailscale action');
c511ci(str_contains($setupJob, $tailHost) && str_contains($cleanupJob, $tailHost) && str_contains($deployJob, $tailHost), 'privileged jobs gebruiken dezelfde private Tailscale-host');
c511ci(str_contains($browserJob, 'E2E_ADMIN_USER: vps-e2e-admin') && str_contains($browserJob, 'E2E_MEMBER_USER: vps-e2e-member'), 'browseridentiteiten zijn dezelfde server-side allowlist-identiteiten');
c511ci(!str_contains($deploy, 'VPS_TEST_ADMIN_USER') && !str_contains($deploy, 'VPS_TEST_MEMBER_USER') && !str_contains($deploy, 'VPS_TEST_E2E_PASSWORD') && !str_contains($full, 'VPS_TEST_E2E_PASSWORD'), 'geen permanente authenticated E2E-applicatiecredentials blijven in workflows');
c511ci(str_contains($setupJob, 'secrets.token_urlsafe(48)') && str_contains($setupJob, $ciphertextOutput) && !str_contains($setupJob, "printf 'e2e_password=%s"), 'wachtwoord wordt per hosted run cryptografisch gegenereerd en alleen als versleutelde joboutput doorgegeven');
c511ci(str_contains($browserJob, $ciphertextInput) && str_contains($browserJob, $passwordFileInput) && !str_contains($browserJob, 'E2E_PASSWORD: ${{ needs.e2e-fixture-setup.outputs.'), 'browser ontvangt cross-job alleen ciphertext; Playwright-stepmetadata bevat alleen lokaal credentialbestandpad');
c511ci(str_contains($setupJob, "'e2e check'") && str_contains($setupJob, "'e2e apply'") && str_contains($setupJob, "'e2e cleanup'") && str_contains($cleanupJob, "'e2e cleanup'"), 'fixturejobs gebruiken uitsluitend check, apply en cleanup via de beperkte gateway');
$check = strpos($setupJob, "'e2e check'");
$stale = strpos($setupJob, "'e2e cleanup'", is_int($check) ? $check : 0);
$apply = strpos($setupJob, "'e2e apply'");
$browser = strpos($browserJob, 'npx playwright test tests/live-dev-authenticated.spec.js');
$final = strpos($cleanupJob, "'e2e cleanup'");
c511ci(is_int($check) && is_int($stale) && is_int($apply) && is_int($browser) && is_int($final) && $check < $stale && $stale < $apply, 'fixture lifecycle behoudt check → stale-cleanup → apply vóór browseracceptatie en aparte cleanup daarna');
c511ci(str_contains($setupJob, 'gateway_ready=1') && str_contains($setupJob, 'if [[ "$rc" -ne 0 && "$gateway_ready" -eq 1 ]]'), 'setup ruimt een gedeeltelijke fixture direct fail-safe op bij een fout');
c511ci(str_contains($browserJob, 'authenticated-browser-acceptance-${{ github.run_id }}') && str_contains($browserJob, 'retention-days: 30'), 'authenticated browserdiagnose wordt als begrensd artifact bewaard');
c511ci(str_contains($browserJob, 'Controleer browserartifacts op plaintextcredential') && str_contains($browserJob, "if: \${{ always() && steps.artifact-scan.outputs.safe == 'true' }}"), 'authenticated browserdiagnose wordt alleen na plaintextcredentialscan geüpload');
c511ci(!str_contains($full, "  live-authenticated:\n") && str_contains($full, 'workflows:') && str_contains($full, '- Deploy RC045test to VPS test'), 'full regression wacht op de volledige deployworkflow inclusief cleanup');
c511ci(!str_contains($deploy, 'VPS_TEST_AUTH_E2E_ENABLED') && !str_contains($full, 'VPS_TEST_AUTH_E2E_ENABLED'), 'authenticated E2E blijft zonder handmatige enable-flag verplicht');

c511ci(!str_contains($browserJob, 'environment: vps-test') && !str_contains($browserJob, 'id-token: write') && substr_count($browserJob, 'secrets.') === 1 && substr_count($browserJob, 'secrets.E2E_CREDENTIAL_WRAP_KEY') === 1, 'repositorygestuurde browserjob heeft geen vps-test environment/OIDC/infrasecrets en alleen de dedicated transportkey');
c511ci(!str_contains($browserJob, 'tailscale/github-action@') && !str_contains($browserJob, 'VPS_TEST_DEPLOY_KEY') && !str_contains($browserJob, 'VPS_TEST_SSH_KNOWN_HOSTS') && !str_contains($browserJob, 'ssh '), 'browserjob heeft geen Tailscale-, deploykey-, hosttrust- of SSH-pad');
c511ci(!str_contains($setupJob, 'actions/checkout@') && !str_contains($cleanupJob, 'actions/checkout@'), 'privileged fixturejobs checken repositorycode niet uit');
c511ci(!str_contains($setupJob, 'npm ') && !str_contains($setupJob, 'npx ') && !str_contains($cleanupJob, 'npm ') && !str_contains($cleanupJob, 'npx '), 'privileged fixturejobs voeren geen repositorygestuurde Node/Playwright-code uit');
c511ci(substr_count($deploy, 'mktemp -d "$RUNNER_TEMP/') === 3 && substr_count($deploy, 'rm -rf "$ssh_dir"') === 3, 'alle tijdelijke SSH-keybestanden worden in iedere privileged SSH-stap opgeruimd');

c511ci(str_contains($ephemeralCli, 'function e2e511LidKoppelingOk') && str_contains($ephemeralCli, 'Ephemeral E2E-lidkoppeling ontbreekt na apply.'), 'apply verifieert na PDO-write de vaste user_id- en accountkoppeling van het fixturelid');
c511ci(str_contains($ephemeralCli, 'function e2e511PortalRuntimeProbe') && str_contains($ephemeralCli, 'Portalprobe ') && str_contains($ephemeralCli, 'portaalVergaderingenVoorLid') && str_contains($ephemeralCli, 'contributieVoorLidJaar'), 'apply traceert server-side portaldata per veilige fasenaam zonder extra SSH- of logrechten');
$statusAssert = strpos($authSpec, 'Authenticated pagina ${path} gaf HTTP ${response.status()} na login');
$unlinkedAssert = strpos($authSpec, 'E2E-memberaccount is niet aan het fixturelid gekoppeld');
$welcomeAssert = strpos($authSpec, "getByRole('heading',{name:/Welkom, E2E/i})");
c511ci(str_contains($authSpec, 'page.waitForNavigation') && is_int($statusAssert), 'authenticated browsertest rapporteert de HTTP-status van de uiteindelijke pagina na login');
c511ci(is_int($unlinkedAssert) && is_int($welcomeAssert) && $unlinkedAssert < $welcomeAssert, 'ledenportaaltest onderscheidt ontbrekende accountkoppeling vóór inhoudsasserties');

echo "Phase 5.11 automatic ephemeral authenticated E2E CI: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
