<?php
$root = dirname(__DIR__);
$pad = $root . '/ops/vps-test-deploy/finalize-public-root-cutover';
$raw = @file_get_contents($pad);
if (!is_string($raw)) {
    fwrite(STDERR, "FOUT: #226 cutoveroperator ontbreekt.\n");
    exit(1);
}

$ok = 0;
$fout = 0;
$check = static function (bool $conditie, string $melding) use (&$ok, &$fout): void {
    if ($conditie) {
        $ok++;
        echo "OK  {$melding}\n";
        return;
    }
    $fout++;
    fwrite(STDERR, "FOUT  {$melding}\n");
};

$check(str_starts_with($raw, "#!/usr/bin/bash\nset -Eeuo pipefail\n"), 'operator gebruikt fail-fast bash met ERR/EXIT-propagatie');
$check(str_contains($raw, 'finalize-public-root-cutover <40-hex-main-commit> <absolute-schone-checkout>'), 'operator vereist expliciete commit en schone checkout');
$check(str_contains($raw, "PUBLIC_IPV4='149.143.36.59'") && str_contains($raw, "RESOLVER='1.1.1.1'"), 'VPS-test cutover bewaart publieke DNS-view en expliciete resolver');
$check(str_contains($raw, 'ls-remote origin refs/heads/main') && str_contains($raw, "[[ \"$REMOTE_MAIN\" == \"$TARGET\" ]]"), 'operator weigert een commit die niet de actuele remote main-tip is');
$check(str_contains($raw, 'install-verenigingsplatform-host-engine') && str_contains($raw, '.host-engine-manifest.sha256'), 'trusted host-engine wordt vóór tenantplannen geïnstalleerd en integraal gevalideerd');
$check(str_contains($raw, '.shared_code.document_root == "/srv/verenigingsplatform/current/public"'), 'webplan moet expliciet de minimale public-root bewijzen');
$check(str_contains($raw, 'DocumentRoot "/srv/verenigingsplatform/current/public"') && str_contains($raw, 'Options +FollowSymLinks'), 'routingfragment moet public-root en gecontroleerde current-symlink traversal bevatten');
$check(str_contains($raw, '.rules.resolver_context_bound_readiness == true') && str_contains($raw, 'live_system_resolver_required_for_readiness'), 'DNS-regeneratie bewaakt het nieuwe #239 resolvercontract en weigert de oude regel');
$check(str_contains($raw, '--resolver="$RESOLVER" --samples=3 --interval=2 --no-write') && str_contains($raw, '--resolver="$RESOLVER" --samples=3 --interval=2'), 'DNS krijgt eerst een niet-schrijvende preflight en daarna pas readiness');
$check(str_contains($raw, '.propagation.scope == "explicit-public-resolver"') && str_contains($raw, '.resolver.endpoint == $resolver'), 'geschreven readiness wordt inhoudelijk aan de expliciete resolver gebonden');
$check(str_contains($raw, 'apply-vps-tls.php" --plan="$TLS_PLAN" --check') && !str_contains($raw, 'apply-vps-tls.php" --plan="$TLS_PLAN" --apply'), 'bestaande TLS wordt gevalideerd zonder onnodige ACME/certificaatmutatie');
$check(str_contains($raw, 'prepare-vps-monitoring.php') && str_contains($raw, 'prepare-vps-lifecycle.php'), 'monitoring en lifecycle worden transitief opnieuw gebonden vóór cutover');
$check(str_contains($raw, 'health --monitoring-plan="$MON_PLAN" --probe --write-status'), 'live health wordt vóór en na de public-root cutover geprobed');
$check(str_contains($raw, 'rollback_fragment()') && str_contains($raw, 'trap cleanup EXIT') && str_contains($raw, 'ROLLBACK OK: oorspronkelijke routing actief.'), 'post-cutover fouten hebben een automatische Apache-fragmentrollback');
$check(str_contains($raw, '/usr/sbin/apache2ctl configtest') && str_contains($raw, '/usr/bin/systemctl reload apache2'), 'rollback vereist geldige Apache-config vóór reload');

$posDns = strpos($raw, "log '3/8 DNS-plan");
$posTls = strpos($raw, "log '5/8 TLS, monitoring en lifecycle");
$posBackup = strpos($raw, "log '6/8 actieve Apache-state");
$posLive = strpos($raw, "log '7/8 public-root");
$check($posDns !== false && $posTls !== false && $posBackup !== false && $posLive !== false && $posDns < $posTls && $posTls < $posBackup && $posBackup < $posLive, 'alle bron-/DNS-/TLS-/healthcontroles gebeuren vóór de eerste live Apache-mutatie');

foreach (['/', '/index.php', '/beheer/', '/healthz.php', '/styles.css', '/favicon.ico'] as $route) {
    $check(str_contains($raw, "expect_code '{$route}'"), "publieke acceptance bevat {$route}");
}
foreach (['/app/core/platform-definities.php', '/bin/apply-vps-release.php', '/tests/phase42-apache-vhosts.php', '/docs/VPS-DEPLOYMENT.md', '/ops/vps-test-deploy/verenigingsplatform-github-deploy', '/.git/config', '/.github/workflows/full-regression.yml'] as $route) {
    $check(str_contains($raw, "expect_code '{$route}' 404"), "interne route is structureel 404: {$route}");
}
$check(str_contains($raw, "expect_closed '/%2e%2e/app/core/platform-definities.php'"), 'encoded traversal heeft expliciete fail-closed acceptance');
$check(str_contains($raw, "echo 'READY_FOR_GITHUB_DEPLOY=1'"), 'operator geeft pas na volledige acceptance een expliciet deploysein');
$check(!str_contains($raw, 'git reset --hard') && !str_contains($raw, 'rm -rf') && !str_contains($raw, 'a2ensite'), 'operator bevat geen brede checkout-/filesystem-/site-enable bypass');

printf("Architecture #226 transactional cutover: %d OK, %d fout(en)\n", $ok, $fout);
exit($fout === 0 ? 0 : 1);
