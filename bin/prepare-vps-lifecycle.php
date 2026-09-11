<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Alleen via CLI beschikbaar.'); }
require_once dirname(__DIR__) . '/app/deployment/lifecycle-contract.php';
require_once dirname(__DIR__) . '/app/storage/private-filesystem.php';

function prepare48Stop(string $m, int $c = 1): never { fwrite(STDERR, "FOUT: {$m}\n"); exit($c); }
function prepare48Help(): void
{
    echo "Gebruik:\n";
    echo "  php bin/prepare-vps-lifecycle.php --monitoring-plan=/srv/verenigingen/club/monitoring/monitoring-plan.json [--output=...] [--dry-run] [--force]\n\n";
    echo "Genereert een secretvrij fase-4.8 lifecycleplan. Er worden geen root- of lifecycleacties uitgevoerd.\n";
}
function prepare48Schrijf(string $pad, string $inhoud, bool $force): string
{
    if (runtime41SymlinkInPad($pad) !== null) prepare48Stop('Lifecycle-output mag geen symlink bevatten.');
    $dir = dirname($pad);
    if (!privateFilesystemBeveiligMap($dir, true)) prepare48Stop('Lifecycle outputmap kon niet met restrictieve mode worden aangemaakt of gehard.');
    if (runtime41SymlinkInPad($dir) !== null) prepare48Stop('Lifecycle outputmap bevat een symlink.');
    if (is_file($pad)) {
        $oud = @file_get_contents($pad);
        if (is_string($oud) && hash_equals(hash('sha256', $oud), hash('sha256', $inhoud))) return 'ongewijzigd';
        if (!$force) prepare48Stop('Afwijkend lifecycle-plan bestaat al; gebruik --force na controle.');
    } elseif (file_exists($pad)) prepare48Stop('Lifecycle-plandoel is geen regulier bestand.');
    if (!privateFilesystemAtomischSchrijf($pad, $inhoud, 0640)) {
        prepare48Stop('Lifecycle-plan kon niet atomisch met restrictieve mode worden geplaatst.');
    }
    return 'geschreven';
}

foreach ($_SERVER['argv'] ?? [] as $arg) {
    if (preg_match('/^--(?:password|pass|secret|token|key|dsn|webhook|credential)(?:=|$)/i', (string)$arg) === 1) prepare48Stop('Secrets horen niet in fase-4.8 CLI-argumenten.');
}
$opt = getopt('', ['monitoring-plan:', 'output::', 'dry-run', 'force', 'help']);
if (isset($opt['help'])) { prepare48Help(); exit(0); }
$monitoringPad = trim((string)($opt['monitoring-plan'] ?? ''));
if ($monitoringPad === '') prepare48Stop('--monitoring-plan is verplicht.');
try {
    $context = lifecycle48Context($monitoringPad);
    $plan = lifecycle48Plan($context);
    $output = trim((string)($opt['output'] ?? $plan['filesystem']['plan_file']));
    if ($output === '') $output = (string)$plan['filesystem']['plan_file'];
    if (!runtime41IsAbsoluutPad($output) || runtime41HeeftRelatieveSegmenten($output)) throw new RuntimeException('--output moet een absoluut veilig POSIX-pad zijn.');
    if (!hash_equals(runtime41NormPad($output), runtime41NormPad((string)$plan['filesystem']['plan_file']))) throw new RuntimeException('Lifecycleplan mag uitsluitend naar het vaste tenantpad worden geschreven.');
    $json = lifecycle48Json($plan);
    if (isset($opt['dry-run'])) { echo $json; exit(0); }
    $status = prepare48Schrijf($output, $json, isset($opt['force']));
    echo strtoupper($status) . '  ' . $output . "\n";
    echo 'Lifecycleplan klaar: tenant=' . $plan['tenant_key'] . "\n";
} catch (Throwable $e) { prepare48Stop($e->getMessage()); }
