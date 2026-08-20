<?php
// ============================================================
// Fase 4.2 — genereer Apache 2.4 webserverbundle per tenant
// ============================================================
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Alleen via CLI beschikbaar.');
}

require_once dirname(__DIR__) . '/app/deployment/webserver-contract.php';

function prepare42Stop(string $melding, int $code = 1): void
{
    fwrite(STDERR, "FOUT: {$melding}\n");
    exit($code);
}

function prepare42Help(): void
{
    echo "Gebruik:\n";
    echo "  php bin/prepare-vps-webserver.php \\\n";
    echo "    --runtime-plan=/srv/verenigingen/club/runtime/runtime-plan.json [opties]\n\n";
    echo "Opties:\n";
    echo "  --output-dir=PAD   standaard: <tenantroot>/webserver\n";
    echo "  --force            afwijkende bestaande bundle gecontroleerd vervangen\n";
    echo "  --dry-run          valideer en toon web-plan.json zonder te schrijven\n";
    echo "  --help             toon deze hulp\n\n";
    echo "De bundle is Apache 2.4 voor Ubuntu/Debian, bevat geen secrets en wordt in fase 4.2 niet geactiveerd.\n";
}

function prepare42SchrijfAtomisch(string $pad, string $inhoud, bool $force): string
{
    $map = dirname($pad);
    if (!is_dir($map) || is_link($map)) prepare42Stop('Webserver outputmap is niet veilig beschikbaar.');
    if (is_link($pad)) prepare42Stop('Webserverbundle mag geen symlinkdoel overschrijven.');
    if (is_file($pad)) {
        $huidig = @file_get_contents($pad);
        if (!is_string($huidig)) prepare42Stop("Bestaand webserverbestand is niet leesbaar: {$pad}");
        if (hash_equals(hash('sha256', $huidig), hash('sha256', $inhoud))) return 'ongewijzigd';
        if (!$force) prepare42Stop('Webserverbundle bestaat al met andere inhoud; gebruik --force na controle.');
    } elseif (file_exists($pad)) {
        prepare42Stop('Webserverbundledoel bestaat maar is geen regulier bestand.');
    }

    $tmp = $map . '/.' . basename($pad) . '.tmp.' . bin2hex(random_bytes(8));
    if (runtime41SymlinkInPad($tmp) !== null) prepare42Stop('Onveilig tijdelijk webserverbundlepad.');
    if (@file_put_contents($tmp, $inhoud, LOCK_EX) === false) prepare42Stop('Webserverbundle kon niet tijdelijk worden geschreven.');
    @chmod($tmp, 0640);
    clearstatcache(true, $pad);
    if (is_link($pad)) { @unlink($tmp); prepare42Stop('Webserverbundledoel werd tijdens write een symlink.'); }
    if (!@rename($tmp, $pad)) { @unlink($tmp); prepare42Stop('Webserverbundle kon niet atomisch worden geplaatst.'); }
    @chmod($pad, 0640);
    return 'geschreven';
}

foreach ($_SERVER['argv'] ?? [] as $arg) {
    if (preg_match('/^--(?:password|hash|secret|dsn|db-password|token|key|certificate|private-key)(?:=|$)/i', (string)$arg) === 1) {
        prepare42Stop('Secrets horen niet in fase-4.2 CLI-argumenten of webserverbundles.');
    }
}

$opt = getopt('', ['runtime-plan:', 'output-dir::', 'force', 'dry-run', 'help']);
if (isset($opt['help'])) { prepare42Help(); exit(0); }
$runtimePlan = trim((string)($opt['runtime-plan'] ?? ''));
if ($runtimePlan === '') prepare42Stop('--runtime-plan=/absoluut/pad/runtime-plan.json is verplicht.');

try {
    $context = web42RuntimeContext($runtimePlan);
    $outputDir = isset($opt['output-dir']) && trim((string)$opt['output-dir']) !== ''
        ? (string)$opt['output-dir']
        : $context['tenant_root'] . '/webserver';
    $plan = web42Plan($context, $outputDir);
    $planJson = web42Json($plan);
    $artifacts = web42Artifacts($plan);
} catch (Throwable $e) {
    prepare42Stop($e->getMessage());
}

if (isset($opt['dry-run'])) {
    echo $planJson;
    exit(0);
}

if (!is_dir($outputDir)) {
    $parent = dirname($outputDir);
    try { runtime41BestaandPad($parent, 'Parent van webserver outputmap', true); }
    catch (Throwable $e) { prepare42Stop($e->getMessage()); }
    if (!@mkdir($outputDir, 0750) && !is_dir($outputDir)) prepare42Stop('Webserver outputmap kon niet worden aangemaakt.');
}
@chmod($outputDir, 0750);
try {
    $outputDirReal = runtime41BestaandPad($outputDir, 'Webserver outputmap', true);
    if (!runtime41Binnen($outputDirReal, $context['tenant_root'])) prepare42Stop('Webserver outputmap valt buiten de tenantroot.');
} catch (Throwable $e) {
    prepare42Stop($e->getMessage());
}

$force = isset($opt['force']);
$statusPlan = prepare42SchrijfAtomisch($plan['bundle']['plan_file'], $planJson, $force);
echo strtoupper($statusPlan) . '  ' . $plan['bundle']['plan_file'] . "\n";
foreach ($artifacts as $pad => $inhoud) {
    $status = prepare42SchrijfAtomisch($pad, $inhoud, $force);
    echo strtoupper($status) . '  ' . $pad . "\n";
}
echo 'Apache 4.2-bundle gereed voor tenant ' . $plan['tenant_key'] . '. Gebruik apply-vps-webserver.php --check vóór root-installatie.' . "\n";
echo 'De artifacts blijven bewust INACTIEF tot DNS/TLS en fase 4.4 gereed zijn.' . "\n";
