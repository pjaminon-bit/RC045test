<?php
$root = dirname(__DIR__); $ok = 0; $fout = 0;
function c511ci(bool $conditie, string $label): void {
    global $ok, $fout;
    if ($conditie) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}
$deploy = (string)file_get_contents($root . '/.github/workflows/deploy-vps-test.yml');
$full = (string)file_get_contents($root . '/.github/workflows/full-regression.yml');

c511ci(str_contains($deploy, "  live-authenticated:\n") && str_contains($deploy, 'needs: deploy-vps-test'), 'authenticated E2E is een verplichte tweede job na succesvolle VPS-deploy');
c511ci(str_contains($deploy, 'environment: vps-test') && str_contains($deploy, 'id-token: write'), 'authenticated E2E blijft binnen vps-test environment en OIDC-permission');
c511ci(substr_count($deploy, 'tailscale/github-action@306e68a486fd2350f2bfc3b19fcd143891a4a2d8') === 2 && substr_count($deploy, 'version: 1.94.2') === 2, 'deploy en E2E gebruiken dezelfde gepinde Tailscale action en clientversie');
c511ci(str_contains($deploy, 'TS_OAUTH_CLIENT_ID: ${{ secrets.TS_OAUTH_CLIENT_ID }}') && str_contains($deploy, 'TS_AUDIENCE: ${{ secrets.TS_AUDIENCE }}'), 'E2E hergebruikt uitsluitend de bestaande WIF-instellingen');
c511ci(str_contains($deploy, "VPS_TAILSCALE_HOST: \${{ vars.VPS_TEST_TAILSCALE_HOST || '100.104.242.66' }}") && str_contains($deploy, 'HostKeyAlias $VPS_SSH_HOST_ALIAS') && str_contains($deploy, 'StrictHostKeyChecking yes'), 'E2E gebruikt private Tailscale-host met gepinde SSH-hosttrust');
c511ci(str_contains($deploy, 'E2E_ADMIN_USER: vps-e2e-admin') && str_contains($deploy, 'E2E_MEMBER_USER: vps-e2e-member'), 'workflowidentiteiten zijn dezelfde server-side allowlist-identiteiten');
c511ci(!str_contains($deploy, 'VPS_TEST_ADMIN_USER') && !str_contains($deploy, 'VPS_TEST_MEMBER_USER') && !str_contains($deploy, 'VPS_TEST_E2E_PASSWORD') && !str_contains($full, 'VPS_TEST_E2E_PASSWORD'), 'geen permanente authenticated E2E-credentials blijven in workflows');
c511ci(str_contains($deploy, 'secrets.token_urlsafe(48)') && str_contains($deploy, '::add-mask::$password') && str_contains($deploy, "printf 'E2E_PASSWORD=%s\\n' \"$password\" >> \"$GITHUB_ENV\""), 'wachtwoord wordt per hosted run cryptografisch gegenereerd en gemaskeerd');
c511ci(str_contains($deploy, "ssh vps-test-private 'e2e check'") && str_contains($deploy, "ssh vps-test-private 'e2e apply'") && substr_count($deploy, "ssh vps-test-private 'e2e cleanup'") === 2, 'CI kan via SSH uitsluitend check, apply en cleanup voor de fixture aanroepen');
c511ci(str_contains($deploy, "printf '%s\\n' \"$E2E_PASSWORD\" | ssh vps-test-private 'e2e apply'"), 'ephemeral wachtwoord bereikt de VPS uitsluitend via stdin');
$stale = strpos($deploy, 'Ruim eventueel achtergebleven fixture op');
$apply = strpos($deploy, 'Maak ephemeral authenticated fixture');
$browser = strpos($deploy, 'Bewijs authenticated beheer en gekoppeld ledenportaal');
$final = strpos($deploy, 'Verwijder ephemeral authenticated fixture');
c511ci(is_int($stale) && is_int($apply) && is_int($browser) && is_int($final) && $stale < $apply && $apply < $browser && $browser < $final, 'lifecycle is stale-cleanup → apply → browseracceptatie → cleanup');
c511ci(str_contains($deploy, "if: \${{ always() && steps.gateway.outputs.ready == 'true' }}") && str_contains($deploy, "printf 'ready=true\\n' >> \"$GITHUB_OUTPUT\""), 'fail-safe cleanup draait na iedere latere fout zodra gateway bewezen bereikbaar is');
c511ci(str_contains($deploy, 'authenticated-browser-acceptance-${{ github.run_id }}') && str_contains($deploy, 'retention-days: 30'), 'authenticated browserdiagnose wordt als begrensd artifact bewaard');
c511ci(!str_contains($full, "  live-authenticated:\n") && str_contains($full, 'workflows:') && str_contains($full, '- Deploy RC045test to VPS test'), 'full regression wacht op de volledige deployworkflow inclusief authenticated E2E');
c511ci(!str_contains($deploy, 'VPS_TEST_AUTH_E2E_ENABLED') && !str_contains($full, 'VPS_TEST_AUTH_E2E_ENABLED'), 'authenticated E2E is na gateway-installatie niet meer afhankelijk van een handmatige enable-flag');

echo "Phase 5.11 automatic ephemeral authenticated E2E CI: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
