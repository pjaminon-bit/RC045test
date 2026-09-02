<?php
$root = dirname(__DIR__);
$workflow = (string) file_get_contents($root . '/.github/workflows/deploy-vps-test.yml');
$ok = 0;
$fout = 0;

function ci175(bool $conditie, string $label): void {
    global $ok, $fout;
    if ($conditie) {
        $ok++;
        echo "OK: {$label}\n";
        return;
    }
    $fout++;
    fwrite(STDERR, "FOUT: {$label}\n");
}

function ci175Job(string $workflow, string $naam): string {
    $marker = "\n  {$naam}:\n";
    $start = strpos($workflow, $marker);
    if ($start === false) return '';
    $rest = substr($workflow, $start + 1);
    if (preg_match('/\n  [a-z0-9][a-z0-9-]*:\n/', $rest, $m, PREG_OFFSET_CAPTURE, strlen("  {$naam}:\n")) === 1) {
        return substr($rest, 0, $m[0][1]);
    }
    return $rest;
}

$setup = ci175Job($workflow, 'e2e-fixture-setup');
$browser = ci175Job($workflow, 'live-authenticated');
$cleanup = ci175Job($workflow, 'e2e-fixture-cleanup');

ci175($setup !== '' && $browser !== '' && $cleanup !== '', 'fixture producer, browser consumer en cleanup bestaan');
ci175(!str_contains($workflow, 'outputs:\n      e2e_password:') && !str_contains($workflow, "printf 'e2e_password=%s"), 'geen plaintext e2e_password job-output bestaat');
ci175(!str_contains($workflow, 'E2E_PASSWORD: ${{ needs.e2e-fixture-setup.outputs.'), 'geen gewone needs-output wordt rechtstreeks als E2E_PASSWORD step-env gezet');
ci175(str_contains($setup, 'e2e_password_ciphertext: ${{ steps.fixture.outputs.e2e_password_ciphertext }}'), 'producer exporteert alleen versleutelde transportdata');

$generate = strpos($setup, 'secrets.token_urlsafe(48)');
$mask = strpos($setup, 'echo "::add-mask::$password"');
$apply = strpos($setup, "'e2e apply'");
$output = strpos($setup, "printf 'e2e_password_ciphertext=%s.%s\\n'");
ci175(is_int($generate) && is_int($mask) && is_int($apply) && is_int($output) && $generate < $mask && $mask < $apply && $apply < $output, 'plaintext wordt direct na generatie gemaskeerd en nooit als output geschreven');
ci175(str_contains($setup, 'openssl enc -aes-256-cbc -pbkdf2 -iter 200000 -salt -a -A'), 'producer versleutelt met salted PBKDF2 transport');
ci175(str_contains($setup, 'hmac.new(') && str_contains($browser, 'hmac.compare_digest('), 'ciphertext krijgt integrity tag die consumer constant-time controleert');
ci175(substr_count($workflow, 'E2E_CREDENTIAL_WRAP_KEY: ${{ secrets.E2E_CREDENTIAL_WRAP_KEY }}') === 2, 'dedicated wrap-key bestaat alleen in producer- en decryptiestap');

$decrypt = strpos($browser, 'Ontsleutel ephemeral applicatiecredential vlak voor browsergebruik');
$checkout = strpos($browser, 'Checkout exact gedeployde bron');
$playwright = strpos($browser, 'npx playwright test tests/live-dev-authenticated.spec.js');
ci175(is_int($decrypt) && is_int($playwright), 'consumer heeft expliciete decryptie en Playwrightgebruik');
ci175(str_contains($browser, 'password_file=$RUNNER_TEMP/e2e-password-') || str_contains($browser, 'password_file="$RUNNER_TEMP/e2e-password-'), 'plaintext wordt alleen in RUNNER_TEMP als lokaal bestand vastgelegd');
ci175(str_contains($browser, "chmod 600 \"\$password_file\""), 'credentialbestand is mode 0600');
ci175(str_contains($browser, 'E2E_PASSWORD_FILE: ${{ steps.credential.outputs.password_file }}'), 'Playwright step-env bevat alleen bestandpad, niet plaintext');
ci175(!str_contains($browser, 'VPS_TEST_DEPLOY_KEY') && !str_contains($browser, 'VPS_TEST_SSH_KNOWN_HOSTS') && !str_contains($browser, 'tailscale/github-action@') && !str_contains($browser, 'id-token: write') && !str_contains($browser, 'environment: vps-test'), 'consumer behoudt #141 infra trust-boundary');

ci175(str_contains($browser, 'Controleer browserartifacts op plaintextcredential'), 'browser heeft pre-upload plaintext artifactscan');
ci175(str_contains($browser, 'grep -R -a -F -l -- "$password"') && str_contains($browser, 'rm -rf playwright-report test-results'), 'artifactscan verwijdert rapporten fail-closed bij credentialhit');
ci175(str_contains($browser, "if: \${{ always() && steps.artifact-scan.outputs.safe == 'true' }}"), 'artifactupload is alleen toegestaan na schone scan');
ci175(str_contains($browser, 'Verwijder lokaal ephemeral credentialbestand') && str_contains($browser, "if: \${{ always() }}") && str_contains($browser, 'rm -f -- "$E2E_PASSWORD_FILE"'), 'lokaal credentialbestand wordt always verwijderd');
ci175(str_contains($cleanup, "if: \${{ always() && needs.e2e-fixture-setup.result != 'skipped' }}") && !str_contains($cleanup, 'E2E_PASSWORD') && !str_contains($cleanup, 'e2e_password_ciphertext'), 'VPS fixture cleanup blijft always en credentialvrij');

// Stronger boundary target: decrypt the persistent transport key before repository checkout.
ci175(is_int($decrypt) && is_int($checkout) && $decrypt < $checkout, 'wrap-key wordt gebruikt vóór repositorycheckout en is niet aanwezig tijdens repositorycode');

echo "Security CI ephemeral credential transport regression: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
