<?php
// ============================================================
// Fase 4.3 — controleer live DNS-readiness vóór TLS
// ============================================================
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Alleen via CLI beschikbaar.');
}

require_once dirname(__DIR__) . '/app/deployment/dns-contract.php';

function check43Stop(string $melding, int $code = 1): void
{
    fwrite(STDERR, "FOUT: {$melding}\n");
    exit($code);
}

function check43Help(): void
{
    echo "Gebruik:\n";
    echo "  php bin/check-vps-dns.php --plan=/srv/verenigingen/club/dns/dns-plan.json [opties]\n\n";
    echo "Opties:\n";
    echo "  --samples=N        aantal opeenvolgende live checks, standaard 3 (1..10)\n";
    echo "  --interval=N       seconden tussen checks, standaard 2 (0..30)\n";
    echo "  --no-write         controleer live maar schrijf geen dns-readiness.json\n";
    echo "  --help             toon deze hulp\n\n";
    echo "De check gebruikt de systeemresolver van de VPS. Een geslaagde readiness is maximaal 15 minuten geldig voor fase 4.4.\n";
}

function check43ReadinessVerwijder(string $pad): void
{
    clearstatcache(true, $pad);
    if (is_link($pad)) check43Stop('Readinessdoel is onverwacht een symlink; handmatige inspectie vereist.');
    if (is_file($pad) && !@unlink($pad)) check43Stop('Verouderde DNS-readiness kon niet worden ingetrokken.');
    if (file_exists($pad) && !is_file($pad)) check43Stop('Readinessdoel is geen regulier bestand.');
}

function check43SchrijfAtomisch(string $pad, string $inhoud): void
{
    $map = dirname($pad);
    if (!is_dir($map) || is_link($map)) check43Stop('DNS outputmap is niet veilig beschikbaar.');
    if (is_link($pad)) check43Stop('Readiness mag geen symlinkdoel overschrijven.');
    $tmp = $map . '/.' . basename($pad) . '.tmp.' . bin2hex(random_bytes(8));
    if (runtime41SymlinkInPad($tmp) !== null) check43Stop('Onveilig tijdelijk readinesspad.');
    if (@file_put_contents($tmp, $inhoud, LOCK_EX) === false) check43Stop('DNS-readiness kon niet tijdelijk worden geschreven.');
    @chmod($tmp, 0640);
    clearstatcache(true, $pad);
    if (is_link($pad)) { @unlink($tmp); check43Stop('Readinessdoel werd tijdens write een symlink.'); }
    if (!@rename($tmp, $pad)) { @unlink($tmp); check43Stop('DNS-readiness kon niet atomisch worden geplaatst.'); }
    @chmod($pad, 0640);
}

foreach ($_SERVER['argv'] ?? [] as $arg) {
    if (preg_match('/^--(?:password|hash|secret|dsn|db-password|token|key|certificate|private-key)(?:=|$)/i', (string)$arg) === 1) {
        check43Stop('Secrets horen niet in fase-4.3 DNS-checks.');
    }
}

$opt = getopt('', ['plan:', 'samples::', 'interval::', 'no-write', 'help']);
if (isset($opt['help'])) { check43Help(); exit(0); }
$planPad = trim((string)($opt['plan'] ?? ''));
if ($planPad === '') check43Stop('--plan=/absoluut/pad/dns-plan.json is verplicht.');
$samplesRaw = (string)($opt['samples'] ?? '3');
$intervalRaw = (string)($opt['interval'] ?? '2');
if (preg_match('/^[0-9]+$/D', $samplesRaw) !== 1 || preg_match('/^[0-9]+$/D', $intervalRaw) !== 1) {
    check43Stop('--samples en --interval moeten gehele getallen zijn.');
}
$samples = (int)$samplesRaw; $interval = (int)$intervalRaw;
if ($samples < 1 || $samples > 10) check43Stop('--samples moet tussen 1 en 10 liggen.');
if ($interval < 0 || $interval > 30) check43Stop('--interval moet tussen 0 en 30 liggen.');

try { $context = dns43PlanLeesEnValideer($planPad); }
catch (Throwable $e) { check43Stop($e->getMessage()); }
$plan = $context['plan'];
$readyPad = (string)$plan['bundle']['readiness_file'];
if (!isset($opt['no-write'])
    && ($samples < (int)$plan['rules']['minimum_readiness_samples']
        || $interval < (int)$plan['rules']['minimum_sample_interval_seconds'])) {
    check43Stop('Een schrijfbare readiness vereist minimaal ' . $plan['rules']['minimum_readiness_samples'] . ' samples met ' . $plan['rules']['minimum_sample_interval_seconds'] . ' seconden interval.');
}
$laatsteOwner = null; $laatsteTerminal = null;

for ($i = 1; $i <= $samples; $i++) {
    try {
        $owner = dns43Resolve((string)$plan['canonical_host']);
        $terminal = null;
        if (($plan['strategy'] ?? '') === 'cname') $terminal = dns43Resolve((string)$plan['expected']['terminal']['name']);
        $result = dns43Beoordeel($plan, $owner, $terminal);
    } catch (Throwable $e) {
        check43ReadinessVerwijder($readyPad);
        check43Stop('Live DNS-resolutie faalde: ' . $e->getMessage(), 2);
    }
    if (($result['ready'] ?? false) !== true) {
        check43ReadinessVerwijder($readyPad);
        foreach ((array)($result['errors'] ?? []) as $fout) fwrite(STDERR, "DNS: {$fout}\n");
        check43Stop('DNS is nog niet exact volgens het fase-4.3 plan; eventuele oude readiness is ingetrokken.', 2);
    }
    $laatsteOwner = $owner; $laatsteTerminal = $terminal;
    echo 'OK sample ' . $i . '/' . $samples . ': ' . $plan['canonical_host'] . "\n";
    if ($i < $samples && $interval > 0) sleep($interval);
}

try { $na = dns43PlanLeesEnValideer($planPad); }
catch (Throwable $e) { check43ReadinessVerwijder($readyPad); check43Stop($e->getMessage()); }
if (!hash_equals($context['sha256'], $na['sha256'])) {
    check43ReadinessVerwijder($readyPad);
    check43Stop('dns-plan.json wijzigde tijdens de readinesscontrole.');
}

if (isset($opt['no-write'])) {
    echo 'CHECK OK: live DNS voldoet aan fase 4.3; readinessbestand niet geschreven.' . "\n";
    exit(0);
}

$now = time();
$status = [
    'schema' => 1,
    'phase' => '4.3-readiness',
    'tenant_key' => $plan['tenant_key'],
    'canonical_host' => $plan['canonical_host'],
    'strategy' => $plan['strategy'],
    'ready' => true,
    'resolver_mode' => 'system',
    'checked_at_utc' => gmdate('Y-m-d\\TH:i:s\\Z', $now),
    'expires_at_utc' => gmdate('Y-m-d\\TH:i:s\\Z', $now + (int)$plan['rules']['readiness_max_age_seconds']),
    'source' => [
        'dns_plan_file' => $context['path'],
        'dns_plan_sha256' => $context['sha256'],
        'web_plan_sha256' => $plan['source']['web_plan_sha256'],
    ],
    'propagation' => [
        'sample_count' => $samples,
        'interval_seconds' => $interval,
        'scope' => 'configured-system-resolver',
    ],
    'observed' => ['owner' => $laatsteOwner, 'terminal' => $laatsteTerminal],
];
check43SchrijfAtomisch($readyPad, dns43Json($status));
echo 'READY: ' . $readyPad . "\n";
echo 'Fase 4.4 mag deze readiness alleen gebruiken zolang bronhash en vervaltijd nog geldig zijn.' . "\n";
