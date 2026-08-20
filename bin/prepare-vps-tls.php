<?php
// ============================================================
// Fase 4.4 — genereer TLS/HTTPS bundle per tenant
// ============================================================
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Alleen via CLI beschikbaar.'); }
require_once dirname(__DIR__) . '/app/deployment/tls-contract.php';

function prepare44Stop(string $m, int $c = 1): void { fwrite(STDERR, "FOUT: {$m}\n"); exit($c); }
function prepare44Help(): void
{
    echo "Gebruik:\n  php bin/prepare-vps-tls.php --dns-readiness=/srv/verenigingen/club/dns/dns-readiness.json [opties]\n\n";
    echo "Opties:\n  --output-dir=PAD   standaard: <tenantroot>/tls\n  --force            afwijkende bestaande bundle gecontroleerd vervangen\n  --dry-run          valideer en toon tls-plan.json zonder te schrijven\n  --help             toon hulp\n\n";
    echo "Vereist een verse fase-4.3 DNS-readiness. De bundle bevat geen certificaatprivate key of ACME-accountsecret.\n";
}
function prepare44Schrijf(string $pad, string $inhoud, bool $force, int $mode = 0640): string
{
    $map = dirname($pad);
    if (!is_dir($map) || is_link($map)) prepare44Stop('TLS outputmap is niet veilig beschikbaar.');
    if (is_link($pad)) prepare44Stop('TLS-bundle mag geen symlinkdoel overschrijven.');
    if (is_file($pad)) {
        $h = @file_get_contents($pad); if (!is_string($h)) prepare44Stop("Bestaand TLS-bestand is niet leesbaar: {$pad}");
        if (hash_equals(hash('sha256', $h), hash('sha256', $inhoud))) return 'ongewijzigd';
        if (!$force) prepare44Stop('TLS-bundle bestaat al met andere inhoud; gebruik --force na controle.');
    } elseif (file_exists($pad)) prepare44Stop('TLS-bundledoel bestaat maar is geen regulier bestand.');
    $tmp = $map . '/.' . basename($pad) . '.tmp.' . bin2hex(random_bytes(8));
    if (runtime41SymlinkInPad($tmp) !== null) prepare44Stop('Onveilig tijdelijk TLS-pad.');
    if (@file_put_contents($tmp, $inhoud, LOCK_EX) === false) prepare44Stop('TLS-bundle kon niet tijdelijk worden geschreven.');
    @chmod($tmp, $mode); clearstatcache(true, $pad);
    if (is_link($pad)) { @unlink($tmp); prepare44Stop('TLS-doel werd tijdens write een symlink.'); }
    if (!@rename($tmp, $pad)) { @unlink($tmp); prepare44Stop('TLS-bundle kon niet atomisch worden geplaatst.'); }
    @chmod($pad, $mode); return 'geschreven';
}
foreach ($_SERVER['argv'] ?? [] as $arg) {
    if (preg_match('/^--(?:password|hash|secret|dsn|db-password|token|key|certificate|private-key|email)(?:=|$)/i', (string)$arg) === 1) prepare44Stop('Secrets/contactdata horen niet in fase-4.4 tenant CLI-argumenten.');
}
$opt = getopt('', ['dns-readiness:', 'output-dir::', 'force', 'dry-run', 'help']);
if (isset($opt['help'])) { prepare44Help(); exit(0); }
$ready = trim((string)($opt['dns-readiness'] ?? ''));
if ($ready === '') prepare44Stop('--dns-readiness=/absoluut/pad/dns-readiness.json is verplicht.');
try {
    $ctx = tls44Context($ready, true);
    $out = isset($opt['output-dir']) && trim((string)$opt['output-dir']) !== '' ? (string)$opt['output-dir'] : $ctx['tenant_root'] . '/tls';
    $plan = tls44Plan($ctx, $out); $json = tls44Json($plan); $artifacts = tls44Artifacts($plan);
} catch (Throwable $e) { prepare44Stop($e->getMessage()); }
if (isset($opt['dry-run'])) { echo $json; exit(0); }
if (!is_dir($out)) {
    try { runtime41BestaandPad(dirname($out), 'Parent van TLS outputmap', true); }
    catch (Throwable $e) { prepare44Stop($e->getMessage()); }
    if (!@mkdir($out, 0750) && !is_dir($out)) prepare44Stop('TLS outputmap kon niet worden aangemaakt.');
}
@chmod($out, 0750);
try { $real = runtime41BestaandPad($out, 'TLS outputmap', true); if (!runtime41Binnen($real, $ctx['tenant_root'])) prepare44Stop('TLS outputmap valt buiten tenantroot.'); }
catch (Throwable $e) { prepare44Stop($e->getMessage()); }
$force = isset($opt['force']);
echo strtoupper(prepare44Schrijf($plan['bundle']['plan_file'], $json, $force)) . '  ' . $plan['bundle']['plan_file'] . "\n";
foreach ($artifacts as $pad => $inhoud) echo strtoupper(prepare44Schrijf($pad, $inhoud, $force)) . '  ' . $pad . "\n";
echo 'TLS 4.4-bundle gereed voor ' . $plan['canonical_host'] . '. Gebruik apply-vps-tls.php --check vóór root-activatie.' . "\n";
