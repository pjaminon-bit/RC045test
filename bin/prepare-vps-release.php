<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Alleen via CLI beschikbaar.'); }
require_once dirname(__DIR__) . '/app/deployment/release-contract.php';

function prep47Stop(string $m, int $c = 1): void { fwrite(STDERR, "FOUT: {$m}\n"); exit($c); }
foreach ($_SERVER['argv'] ?? [] as $arg) {
    if (preg_match('/^--(?:password|secret|token|key|dsn|webhook)(?:=|$)/i', (string)$arg) === 1) prep47Stop('Secrets horen niet in fase-4.7 CLI-argumenten.');
}
$opt = getopt('', ['source:', 'commit:', 'platform-root::', 'tenant-base::', 'output:', 'force', 'dry-run', 'help']);
if (isset($opt['help'])) {
    echo "Gebruik: php bin/prepare-vps-release.php --source=/staging/repo --commit=<40hex> --output=/staging/release-plan.json [opties]\n";
    echo "  --platform-root=/srv/verenigingsplatform\n  --tenant-base=/srv/verenigingen\n  --force\n  --dry-run\n";
    exit(0);
}
$source = trim((string)($opt['source'] ?? ''));
$commit = trim((string)($opt['commit'] ?? ''));
$output = trim((string)($opt['output'] ?? ''));
$platform = trim((string)($opt['platform-root'] ?? '/srv/verenigingsplatform'));
$tenants = trim((string)($opt['tenant-base'] ?? '/srv/verenigingen'));
if ($source === '' || $commit === '' || $output === '') prep47Stop('--source, --commit en --output zijn verplicht.');
try {
    $plan = release47Plan($source, $commit, $platform, $tenants);
    $output = release47VeiligAbsoluut($output, 'Releaseplan output');
    if (runtime41Binnen($output, (string)$plan['source']['root'])) throw new RuntimeException('Releaseplan output mag niet binnen de releasebron staan.');
    $link = runtime41SymlinkInPad($output);
    if ($link !== null) throw new RuntimeException("Releaseplan output mag geen symlink bevatten: {$link}");
    $json = release47Json($plan);
} catch (Throwable $e) { prep47Stop($e->getMessage()); }
if (isset($opt['dry-run'])) { echo $json; exit(0); }
$dir = dirname($output);
if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) prep47Stop('Outputmap kon niet worden aangemaakt.');
if (is_link($output)) prep47Stop('Releaseplan output is een symlink.');
if (is_file($output)) {
    $oud = @file_get_contents($output);
    if (is_string($oud) && hash_equals(hash('sha256', $oud), hash('sha256', $json))) {
        @chmod($output, 0640); echo "ONGEWIJZIGD  {$output}\n"; exit(0);
    }
    if (!isset($opt['force'])) prep47Stop('Afwijkend releaseplan bestaat al; gebruik --force na controle.');
} elseif (file_exists($output)) prep47Stop('Releaseplan output is geen regulier bestand.');
$tmp = $output . '.tmp.' . bin2hex(random_bytes(6));
if (@file_put_contents($tmp, $json, LOCK_EX) === false) prep47Stop('Releaseplan kon niet tijdelijk worden geschreven.');
@chmod($tmp, 0640);
if (is_link($output)) { @unlink($tmp); prep47Stop('Releaseplan output werd tijdens write een symlink.'); }
if (!@rename($tmp, $output)) { @unlink($tmp); prep47Stop('Releaseplan kon niet atomisch worden geplaatst.'); }
@chmod($output, 0640);
echo "GESCHREVEN  {$output}\n";
