<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function audit312Check(bool $conditie, string $melding): void
{
    global $ok, $fout;
    if ($conditie) {
        $ok++;
        echo "OK: {$melding}\n";
        return;
    }
    $fout++;
    fwrite(STDERR, "FOUT: {$melding}\n");
}

$docs = [
    'PROVISIONING.md' => (string)file_get_contents($root . '/docs/PROVISIONING.md'),
    'VPS-RUNTIME-ISOLATION.md' => (string)file_get_contents($root . '/docs/VPS-RUNTIME-ISOLATION.md'),
    'VPS-DNS.md' => (string)file_get_contents($root . '/docs/VPS-DNS.md'),
    'VPS-TLS.md' => (string)file_get_contents($root . '/docs/VPS-TLS.md'),
    'VPS-RELEASES.md' => (string)file_get_contents($root . '/docs/VPS-RELEASES.md'),
];

$verboden = [
    'De eerstvolgende stap is fase 5.3',
    'de daadwerkelijke root-`--apply` wordt pas op de toekomstige VPS uitgevoerd',
    'op de toekomstige VPS',
    'daadwerkelijke certificaatuitgifte en activatie gebeuren pas op de echte VPS',
    'de echte `--apply` wordt pas op de productie-VPS uitgevoerd',
    'Een toekomstige platform-control-plane kan deze vaste primitive aanroepen',
];

foreach ($docs as $naam => $inhoud) {
    audit312Check($inhoud !== '', "{$naam} is leesbaar");
    foreach ($verboden as $tekst) {
        audit312Check(!str_contains($inhoud, $tekst), "{$naam} bevat geen stale statusclaim: {$tekst}");
    }
}

audit312Check(str_contains($docs['PROVISIONING.md'], 'Fase 5.3 is op 28 augustus 2026 volledig groen afgerond'), 'provisioningdocument noemt geaccepteerde fase 5.3');
audit312Check(str_contains($docs['VPS-RUNTIME-ISOLATION.md'], 'echte VPS-validatie afgerond'), 'runtime-isolatiedoc vermeldt VPS-validatie');
audit312Check(str_contains($docs['VPS-DNS.md'], 'live VPS-validatie afgerond'), 'DNS-doc vermeldt live VPS-validatie');
audit312Check(str_contains($docs['VPS-TLS.md'], 'echte certificaatuitgifte/renewalvalidatie op de VPS afgerond'), 'TLS-doc vermeldt echte certificaatacceptatie');
audit312Check(str_contains($docs['VPS-RELEASES.md'], 'echte releasewissel/rollback op de VPS afgerond'), 'release-doc vermeldt echte rollbackacceptatie');

printf("Audit #312 VPS-documentatiestatus: %d OK, %d fout(en)\n", $ok, $fout);
exit($fout === 0 ? 0 : 1);
