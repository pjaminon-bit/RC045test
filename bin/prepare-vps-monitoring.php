<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Alleen via CLI beschikbaar.'); }
require_once dirname(__DIR__) . '/app/deployment/monitoring-contract.php';

function prep46Stop(string $melding, int $code = 1): void { fwrite(STDERR, "FOUT: {$melding}\n"); exit($code); }
function prep46Help(): void
{
    echo "Gebruik:\n";
    echo "  php bin/prepare-vps-monitoring.php --tls-plan=/srv/.../tls/tls-plan.json --database-plan=/srv/.../database/database-plan.json [--dry-run] [--force]\n";
}
function prep46Write(string $pad, string $inhoud, int $mode, bool $force): string
{
    if (is_link($pad)) prep46Stop("Symlinkdoel geweigerd: {$pad}");
    if (is_file($pad)) {
        $huidig = @file_get_contents($pad);
        if (is_string($huidig) && hash_equals(hash('sha256', $huidig), hash('sha256', $inhoud))) return 'ongewijzigd';
        if (!$force) prep46Stop("Afwijkend bestand bestaat al: {$pad}; gebruik --force na controle.");
    } elseif (file_exists($pad)) prep46Stop("Doel is geen regulier bestand: {$pad}");
    $tmp = $pad . '.tmp.' . bin2hex(random_bytes(6));
    if (@file_put_contents($tmp, $inhoud, LOCK_EX) === false) prep46Stop("Tijdelijk bestand kon niet worden geschreven: {$pad}");
    @chmod($tmp, $mode);
    if (is_link($pad)) { @unlink($tmp); prep46Stop("Doel werd tijdens write een symlink: {$pad}"); }
    if (!@rename($tmp, $pad)) { @unlink($tmp); prep46Stop("Bestand kon niet atomisch worden geplaatst: {$pad}"); }
    @chmod($pad, $mode);
    return 'geschreven';
}
foreach ($_SERVER['argv'] ?? [] as $arg) {
    if (preg_match('/^--(?:password|secret|token|key|dsn|webhook|email)(?:=|$)/i', (string)$arg) === 1) prep46Stop('Secrets/contactdata horen niet in fase-4.6 CLI-argumenten.');
}
$opt = getopt('', ['tls-plan:', 'database-plan:', 'dry-run', 'force', 'help']);
if (isset($opt['help'])) { prep46Help(); exit(0); }
$tls = trim((string)($opt['tls-plan'] ?? ''));
$db = trim((string)($opt['database-plan'] ?? ''));
if ($tls === '' || $db === '') prep46Stop('--tls-plan en --database-plan zijn verplicht.');
try { $context = monitoring46Context($tls, $db); $plan = monitoring46Plan($context); }
catch (Throwable $e) { prep46Stop($e->getMessage()); }
if (isset($opt['dry-run'])) { echo monitoring46Json($plan); exit(0); }
$out = (string)$plan['bundle']['output_dir'];
if (is_link($out)) prep46Stop('Monitoring outputmap mag geen symlink zijn.');
if (!is_dir($out) && !@mkdir($out, 0750, true) && !is_dir($out)) prep46Stop('Monitoring outputmap kon niet worden aangemaakt.');
@chmod($out, 0750);
$force = isset($opt['force']);
prep46Write((string)$plan['bundle']['plan_file'], monitoring46Json($plan), 0640, $force);
foreach (monitoring46Artifacts($plan) as $pad => $inhoud) prep46Write((string)$pad, $inhoud, 0640, $force);
echo 'OK  fase 4.6 monitoringbundle tenant=' . $plan['tenant_key'] . "\n";
