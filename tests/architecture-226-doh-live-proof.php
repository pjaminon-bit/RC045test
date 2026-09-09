<?php
$root = dirname(__DIR__);
require_once $root . '/app/deployment/dns-contract.php';

$host = 'test.vps.holox.nl';
$expected = ['149.143.36.59'];
try {
    $resolver = dns43ResolverContextVanCli('doh:cloudflare');
    $obs = dns43Resolve($host, $resolver);
    if (($obs['a'] ?? []) !== $expected || ($obs['aaaa'] ?? []) !== [] || ($obs['cname'] ?? []) !== []) {
        fwrite(STDERR, 'FOUT: productie-DoH geeft onverwachte publieke DNS-view: ' . json_encode($obs, JSON_UNESCAPED_SLASHES) . "\n");
        exit(1);
    }
    echo 'OK: productie-DoH resolver ziet A=149.143.36.59 en geen AAAA/CNAME.' . "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FOUT: productie-DoH resolver faalt: ' . $e->getMessage() . "\n");
    exit(1);
}
