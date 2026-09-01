<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Alleen via CLI beschikbaar.'); }
require_once dirname(__DIR__) . '/app/deployment/control-plane-contract.php';

function cp51Stop(string $m, int $c = 1): never { fwrite(STDERR, "FOUT: {$m}\n"); exit($c); }
foreach ($_SERVER['argv'] ?? [] as $a) {
    if (preg_match('/^--(?:password|pass|secret|token|credential|webhook)(?:=|$)/i', (string)$a) === 1) {
        cp51Stop('Secrets horen niet in fase-5.1 CLI-argumenten.');
    }
}
$o = getopt('', ['host:', 'app-root:', 'tenants-root:', 'php-version::', 'cert-name::', 'output:', 'dry-run', 'force', 'help']);
if (isset($o['help'])) {
    echo "Gebruik: php bin/prepare-vps-control-plane.php --host=beheer.example.nl --app-root=/srv/verenigingsplatform/current --tenants-root=/srv/verenigingsplatform/tenants --output=/root/control-plane [--php-version=8.5] [--cert-name=platform-beheer] [--dry-run] [--force]\n";
    exit(0);
}
foreach (['host','app-root','tenants-root','output'] as $k) if (!isset($o[$k]) || trim((string)$o[$k]) === '') cp51Stop('--' . $k . ' is verplicht.');
$php = trim((string)($o['php-version'] ?? '8.5'));
$cert = trim((string)($o['cert-name'] ?? 'verenigingsplatform-beheer'));
try {
    $appArg = trim((string)$o['app-root']);
    $appReal = realpath($appArg);
    if ($appReal === false || !is_dir($appReal)) throw new RuntimeException('Control-plane app-root kon niet fysiek naar de trusted release worden opgelost.');
    $appRoot = runtime41NormPad($appReal);
    $plan = control51Plan(trim((string)$o['host']), $appRoot, trim((string)$o['tenants-root']), $php, $cert, trim((string)$o['output']));
    if (isset($o['dry-run'])) { echo control51Json($plan); exit(0); }
    $dir = (string)$plan['bundle']['output_dir'];
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('Outputmap kon niet worden aangemaakt.');
    if (runtime41SymlinkInPad($dir) !== null) throw new RuntimeException('Outputmap bevat een symlink.');
    $artifacts = control51Artifacts($plan);
    $files = $artifacts + [$plan['bundle']['plan_file'] => control51Json($plan)];
    foreach ($files as $pad => $inhoud) {
        if (is_link($pad)) throw new RuntimeException('Outputdoel is een symlink: ' . $pad);
        if (is_file($pad)) {
            $actueel = @file_get_contents($pad);
            if (is_string($actueel) && hash_equals(hash('sha256',$actueel), hash('sha256',$inhoud))) continue;
            if (!isset($o['force'])) throw new RuntimeException('Bestaand control-plane artifact wijkt af; gebruik alleen bewust --force: ' . $pad);
        }
        $tmp = dirname($pad) . '/.' . basename($pad) . '.tmp.' . bin2hex(random_bytes(5));
        if (@file_put_contents($tmp, $inhoud, LOCK_EX) === false) throw new RuntimeException('Tijdelijke write faalde: ' . $pad);
        @chmod($tmp, 0640);
        if (!@rename($tmp, $pad)) { @unlink($tmp); throw new RuntimeException('Artifact kon niet atomisch worden geplaatst: ' . $pad); }
        @chmod($pad, 0640);
    }
    echo 'CONTROL-PLANE BUNDLE OK host=' . $plan['host'] . ' output=' . $dir . "\n";
} catch (Throwable $e) { cp51Stop($e->getMessage()); }
