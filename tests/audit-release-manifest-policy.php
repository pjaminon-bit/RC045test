<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;
function mp47(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) {
        $ok++;
        echo "OK: {$label}\n";
    } else {
        $fout++;
        fwrite(STDERR, "FOUT: {$label}\n");
    }
}
function mp47Rm(string $dir): void
{
    if (!is_dir($dir) || is_link($dir)) return;
    foreach (scandir($dir) ?: [] as $naam) {
        if ($naam === '.' || $naam === '..') continue;
        $pad = $dir . '/' . $naam;
        if (is_link($pad) || is_file($pad)) @unlink($pad);
        elseif (is_dir($pad)) mp47Rm($pad);
    }
    @rmdir($dir);
}
function mp47Copy(string $bron, string $doel): void
{
    @mkdir(dirname($doel), 0755, true);
    if (!copy($bron, $doel)) throw new RuntimeException('Testbestand kon niet worden gekopieerd.');
}
function mp47Bron(string $root, string $doel): void
{
    $vereist = [
        'site-config.php',
        'auth.php',
        'healthz.php',
        'bin/check-vps-health.php',
        'bin/check-release-tenant.php',
        'app/deployment/release-contract.php',
    ];
    foreach ($vereist as $rel) mp47Copy($root . '/' . $rel, $doel . '/' . $rel);
    @mkdir($doel . '/ops/vps-test-deploy', 0755, true);
    file_put_contents($doel . '/ops/vps-test-deploy/bootstrap-helper', "legacy-only\n");
    file_put_contents($doel . '/index.php', "<?php echo 'ok';\n");
}
function mp47MarkerSchrijf(string $release, array $marker): void
{
    file_put_contents(
        $release . '/.verenigingsplatform-release.json',
        json_encode($marker, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n"
    );
}

require_once $root . '/app/deployment/release-contract.php';
$tmp = sys_get_temp_dir() . '/rc045-manifest-policy-' . bin2hex(random_bytes(6));
@mkdir($tmp, 0755, true);

try {
    $bron = $tmp . '/source';
    mp47Bron($root, $bron);

    $legacy = release47Manifest($bron, 1);
    $actueel = release47Manifest($bron);
    mp47(release47ManifestPolicyActueel() === 2, 'actuele manifestpolicy is expliciet versie 2');
    mp47(isset($legacy['files']['ops/vps-test-deploy/bootstrap-helper']), 'legacy policy 1 telt ops mee');
    mp47(!isset($actueel['files']['ops/vps-test-deploy/bootstrap-helper']), 'policy 2 sluit ops uit');
    mp47($legacy['sha256'] !== $actueel['sha256'], 'policywijziging levert aantoonbaar ander manifest op');

    $plan = release47Plan($bron, str_repeat('a', 40), $tmp . '/platform', $tmp . '/tenants');
    mp47(($plan['source']['manifest_policy'] ?? null) === 2, 'nieuwe releaseplannen binden expliciet aan policy 2');
    $nieuweMarker = release47Marker($plan);
    mp47(($nieuweMarker['manifest_policy'] ?? null) === 2, 'nieuwe releasemarkers dragen manifestpolicy 2');

    $legacyRelease = $tmp . '/releases/' . str_repeat('1', 40);
    mp47Bron($root, $legacyRelease);
    $legacyManifest = release47Manifest($legacyRelease, 1);
    mp47MarkerSchrijf($legacyRelease, [
        'schema' => 1,
        'phase' => '4.7-release',
        'commit' => str_repeat('1', 40),
        'manifest_sha256' => $legacyManifest['sha256'],
        'file_count' => $legacyManifest['file_count'],
        'bytes' => $legacyManifest['bytes'],
        'immutable' => true,
    ]);
    try {
        $ctx = release47MarkerLees($legacyRelease, true);
        mp47(($ctx['manifest_policy'] ?? null) === 1, 'marker zonder policy wordt uitsluitend als legacy policy 1 geïnterpreteerd');
    } catch (Throwable $e) {
        mp47(false, 'marker zonder policy wordt uitsluitend als legacy policy 1 geïnterpreteerd');
    }

    $v2Release = $tmp . '/releases/' . str_repeat('2', 40);
    mp47Bron($root, $v2Release);
    $v2Manifest = release47Manifest($v2Release, 2);
    mp47MarkerSchrijf($v2Release, [
        'schema' => 1,
        'phase' => '4.7-release',
        'commit' => str_repeat('2', 40),
        'manifest_policy' => 2,
        'manifest_sha256' => $v2Manifest['sha256'],
        'file_count' => $v2Manifest['file_count'],
        'bytes' => $v2Manifest['bytes'],
        'immutable' => true,
    ]);
    try {
        $ctx = release47MarkerLees($v2Release, true);
        mp47(($ctx['manifest_policy'] ?? null) === 2, 'policy-2 marker valideert met policy-2 regels');
    } catch (Throwable $e) {
        mp47(false, 'policy-2 marker valideert met policy-2 regels');
    }

    file_put_contents($legacyRelease . '/index.php', "<?php echo 'tampered';\n");
    try {
        release47MarkerLees($legacyRelease, true);
        mp47(false, 'legacy compatibiliteit verzwakt integriteitscontrole niet');
    } catch (Throwable $e) {
        mp47(str_contains($e->getMessage(), 'wijkt af'), 'legacy compatibiliteit verzwakt integriteitscontrole niet');
    }

    $foutMarker = $tmp . '/releases/' . str_repeat('3', 40);
    mp47Bron($root, $foutMarker);
    $foutManifest = release47Manifest($foutMarker, 2);
    mp47MarkerSchrijf($foutMarker, [
        'schema' => 1,
        'phase' => '4.7-release',
        'commit' => str_repeat('3', 40),
        'manifest_policy' => 99,
        'manifest_sha256' => $foutManifest['sha256'],
        'file_count' => $foutManifest['file_count'],
        'bytes' => $foutManifest['bytes'],
        'immutable' => true,
    ]);
    try {
        release47MarkerLees($foutMarker, true);
        mp47(false, 'onbekende toekomstige manifestpolicy wordt fail-closed geweigerd');
    } catch (Throwable $e) {
        mp47(str_contains($e->getMessage(), 'ongeldig'), 'onbekende toekomstige manifestpolicy wordt fail-closed geweigerd');
    }
} finally {
    mp47Rm($tmp);
}

echo "Release manifest policy: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
