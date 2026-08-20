<?php
// ============================================================
// Fase 4.3 — genereer tenantgebonden DNS-plan
// ============================================================
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Alleen via CLI beschikbaar.');
}

require_once dirname(__DIR__) . '/app/deployment/dns-contract.php';

function prepare43Stop(string $melding, int $code = 1): void
{
    fwrite(STDERR, "FOUT: {$melding}\n");
    exit($code);
}

function prepare43Help(): void
{
    echo "Gebruik:\n";
    echo "  php bin/prepare-vps-dns.php --web-plan=/srv/verenigingen/club/webserver/web-plan.json \\\n";
    echo "    --strategy=direct --ipv4=203.0.113.10 [--ipv6=2001:db8::10] [opties]\n\n";
    echo "Of met CNAME:\n";
    echo "  php bin/prepare-vps-dns.php --web-plan=... --strategy=cname \\\n";
    echo "    --cname=vps.example.net --ipv4=203.0.113.10 [--ipv6=2001:db8::10]\n\n";
    echo "Opties:\n";
    echo "  --output-dir=PAD   standaard: <tenantroot>/dns\n";
    echo "  --force            afwijkend bestaand dns-plan.json gecontroleerd vervangen\n";
    echo "  --dry-run          valideer en toon plan zonder te schrijven\n";
    echo "  --help             toon deze hulp\n\n";
    echo "IPv4/IPv6 zijn comma-separated exacte verwachte adressen. Niet opgegeven families moeten ook werkelijk afwezig zijn.\n";
}

function prepare43SchrijfAtomisch(string $pad, string $inhoud, bool $force): string
{
    $map = dirname($pad);
    if (!is_dir($map) || is_link($map)) prepare43Stop('DNS outputmap is niet veilig beschikbaar.');
    if (is_link($pad)) prepare43Stop('DNS-plan mag geen symlinkdoel overschrijven.');
    if (is_file($pad)) {
        $huidig = @file_get_contents($pad);
        if (!is_string($huidig)) prepare43Stop('Bestaand DNS-plan is niet leesbaar.');
        if (hash_equals(hash('sha256', $huidig), hash('sha256', $inhoud))) return 'ongewijzigd';
        if (!$force) prepare43Stop('dns-plan.json bestaat al met andere inhoud; gebruik --force na controle.');
    } elseif (file_exists($pad)) {
        prepare43Stop('DNS-plandoel bestaat maar is geen regulier bestand.');
    }
    $tmp = $map . '/.' . basename($pad) . '.tmp.' . bin2hex(random_bytes(8));
    if (runtime41SymlinkInPad($tmp) !== null) prepare43Stop('Onveilig tijdelijk DNS-planpad.');
    if (@file_put_contents($tmp, $inhoud, LOCK_EX) === false) prepare43Stop('DNS-plan kon niet tijdelijk worden geschreven.');
    @chmod($tmp, 0640);
    clearstatcache(true, $pad);
    if (is_link($pad)) { @unlink($tmp); prepare43Stop('DNS-plandoel werd tijdens write een symlink.'); }
    if (!@rename($tmp, $pad)) { @unlink($tmp); prepare43Stop('DNS-plan kon niet atomisch worden geplaatst.'); }
    @chmod($pad, 0640);
    return 'geschreven';
}

function prepare43TrekReadinessIn(string $pad): void
{
    clearstatcache(true, $pad);
    if (is_link($pad)) prepare43Stop('Bestaande DNS-readiness is onverwacht een symlink; handmatige inspectie vereist.');
    if (is_file($pad) && !@unlink($pad)) prepare43Stop('Bestaande DNS-readiness kon na planwijziging niet worden ingetrokken.');
    if (file_exists($pad) && !is_file($pad)) prepare43Stop('DNS-readinessdoel is geen regulier bestand.');
}

foreach ($_SERVER['argv'] ?? [] as $arg) {
    if (preg_match('/^--(?:password|hash|secret|dsn|db-password|token|key|certificate|private-key)(?:=|$)/i', (string)$arg) === 1) {
        prepare43Stop('Secrets horen niet in fase-4.3 CLI-argumenten of DNS-plannen.');
    }
}

$opt = getopt('', ['web-plan:', 'strategy:', 'ipv4::', 'ipv6::', 'cname::', 'output-dir::', 'force', 'dry-run', 'help']);
if (isset($opt['help'])) { prepare43Help(); exit(0); }
$webPlan = trim((string)($opt['web-plan'] ?? ''));
$strategy = trim((string)($opt['strategy'] ?? ''));
if ($webPlan === '') prepare43Stop('--web-plan=/absoluut/pad/web-plan.json is verplicht.');
if ($strategy === '') prepare43Stop('--strategy=direct|cname is verplicht.');

try {
    $context = dns43WebContext($webPlan);
    $outputDir = isset($opt['output-dir']) && trim((string)$opt['output-dir']) !== '' ? (string)$opt['output-dir'] : $context['tenant_root'] . '/dns';
    $ipv4 = dns43IpLijst((string)($opt['ipv4'] ?? ''), 4);
    $ipv6 = dns43IpLijst((string)($opt['ipv6'] ?? ''), 6);
    $plan = dns43Plan($context, $outputDir, $strategy, $ipv4, $ipv6, (string)($opt['cname'] ?? ''));
    $json = dns43Json($plan);
} catch (Throwable $e) { prepare43Stop($e->getMessage()); }

if (isset($opt['dry-run'])) { echo $json; exit(0); }

if (!is_dir($outputDir)) {
    $parent = dirname($outputDir);
    try { runtime41BestaandPad($parent, 'Parent van DNS outputmap', true); }
    catch (Throwable $e) { prepare43Stop($e->getMessage()); }
    if (!@mkdir($outputDir, 0750) && !is_dir($outputDir)) prepare43Stop('DNS outputmap kon niet worden aangemaakt.');
}
@chmod($outputDir, 0750);
try {
    $real = runtime41BestaandPad($outputDir, 'DNS outputmap', true);
    if (!runtime41Binnen($real, $context['tenant_root'])) prepare43Stop('DNS outputmap valt buiten de tenantroot.');
} catch (Throwable $e) { prepare43Stop($e->getMessage()); }

$status = prepare43SchrijfAtomisch($plan['bundle']['plan_file'], $json, isset($opt['force']));
if ($status === 'geschreven') prepare43TrekReadinessIn((string)$plan['bundle']['readiness_file']);
echo strtoupper($status) . '  ' . $plan['bundle']['plan_file'] . "\n";
echo 'DNS-plan gereed voor ' . $plan['canonical_host'] . ' (' . $plan['strategy'] . ').' . "\n";
echo 'Voer check-vps-dns.php uit nadat de DNS-providerrecords zijn ingesteld; TLS blijft geblokkeerd zonder verse readiness.' . "\n";
