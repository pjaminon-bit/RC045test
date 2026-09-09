<?php
// Tijdelijke CI-only live proof; deze file wordt nooit naar main gemerged.
$root = dirname(__DIR__);
require_once $root . '/app/deployment/dns-contract.php';

try {
    $resolver = dns43ResolverContextVanCli('doh:cloudflare');
    $obs = dns43Resolve('test.vps.holox.nl', $resolver);
    echo "#226 PRODUCTION DOH DIAGNOSTIC\n";
    echo json_encode(['resolver'=>$resolver,'observed'=>$obs], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), "\n";
    $ok = ($resolver['mode'] ?? '') === 'doh'
        && ($resolver['endpoint'] ?? '') === 'cloudflare-dns.com'
        && ($resolver['port'] ?? 0) === 443
        && ($obs['a'] ?? []) === ['149.143.36.59']
        && ($obs['aaaa'] ?? []) === []
        && ($obs['cname'] ?? []) === [];
    if (!$ok) {
        fwrite(STDERR, "FOUT: productie-DoH-view voldoet niet aan #226.\n");
        exit(1);
    }
    echo "OK: productie-DoH-code ziet exact de publieke #226 DNS-view.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FOUT: productie-DoH-diagnose faalt: ' . $e->getMessage() . "\n");
    exit(1);
}
