<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Alleen via CLI beschikbaar.'); }

require_once dirname(__DIR__) . '/app/deployment/process-runner.php';
require_once dirname(__DIR__) . '/app/deployment/privileged-ops-contract.php';

function cp135Stop(string $message, int $code = 1): never
{
    fwrite(STDERR, "FOUT: {$message}\n");
    exit($code);
}

function cp135EngineRoot(): string
{
    $root = (string)(getenv('VERENIGINGSPLATFORM_HOST_ENGINE_ROOT') ?: '');
    if (preg_match('#^/usr/local/libexec/verenigingsplatform/host-engine/[0-9a-f]{40}$#D', $root) !== 1
        || is_link($root) || !is_dir($root)) {
        throw new RuntimeException('Privileged integriteitswrapper vereist een geldige root-owned host-engine.');
    }
    $stat = @lstat($root);
    if (!is_array($stat) || (int)$stat['uid'] !== 0 || (int)$stat['gid'] !== 0 || (((int)$stat['mode'] & 0777) !== 0555)) {
        throw new RuntimeException('Host-engine metadata wijkt af van root:root/0555.');
    }
    return $root;
}

function cp135ConfigArgument(array $args): ?string
{
    $config = null;
    foreach ($args as $arg) {
        if (!is_string($arg)) continue;
        if (str_starts_with($arg, '--config=')) {
            if ($config !== null) throw new RuntimeException('Control-plane config is dubbel opgegeven.');
            $config = substr($arg, 9);
        }
    }
    return $config;
}

function cp135Publish(string $configPath): void
{
    if (!hash_equals('/etc/verenigingsplatform/control-plane/runtime.json', $configPath)) {
        throw new RuntimeException('Privileged integriteitswrapper accepteert uitsluitend de vaste control-plane runtimeconfig.');
    }
    if (is_link($configPath) || !is_file($configPath) || !is_readable($configPath)) {
        throw new RuntimeException('Control-plane runtimeconfig ontbreekt of is onveilig.');
    }
    $raw = @file_get_contents($configPath);
    try {
        $config = is_string($raw) ? json_decode($raw, true, 64, JSON_THROW_ON_ERROR) : null;
    } catch (Throwable $e) {
        $config = null;
    }
    if (!is_array($config) || ($config['schema'] ?? null) !== 1 || ($config['phase'] ?? '') !== '5.1-runtime') {
        throw new RuntimeException('Control-plane runtimeconfig heeft onbekend schema.');
    }
    $snapshotPath = (string)($config['snapshot_file'] ?? '');
    if ($snapshotPath === '' || !str_starts_with($snapshotPath, '/') || str_contains($snapshotPath, "\0")
        || preg_match('#(?:^|/)\.\.?(/|$)#', $snapshotPath) === 1
        || is_link($snapshotPath) || !is_file($snapshotPath) || !is_readable($snapshotPath)) {
        throw new RuntimeException('Control-plane snapshotpad is onveilig.');
    }
    $stat = @lstat($snapshotPath);
    if (!is_array($stat) || (int)$stat['uid'] !== 0 || (((int)$stat['mode'] & 0777) !== 0640)) {
        throw new RuntimeException('Control-plane snapshot heeft onverwachte owner/mode.');
    }

    $raw = @file_get_contents($snapshotPath);
    try {
        $snapshot = is_string($raw) ? json_decode($raw, true, 128, JSON_THROW_ON_ERROR) : null;
    } catch (Throwable $e) {
        $snapshot = null;
    }
    if (!is_array($snapshot) || ($snapshot['schema'] ?? null) !== 1 || ($snapshot['phase'] ?? '') !== '5.1-snapshot'
        || !is_array($snapshot['tenants'] ?? null)) {
        throw new RuntimeException('Control-plane snapshot heeft onbekend schema.');
    }

    $snapshot['privileged_ops'] = privilegedOpsSnapshot();
    $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($json)) throw new RuntimeException('Privileged integriteit kon niet worden geserialiseerd.');

    $dir = dirname($snapshotPath);
    if (is_link($dir) || !is_dir($dir)) throw new RuntimeException('Control-plane snapshotmap is onveilig.');
    $tmp = @tempnam($dir, '.privileged-ops.');
    if (!is_string($tmp) || $tmp === '') throw new RuntimeException('Tijdelijk privileged snapshotbestand kon niet worden gemaakt.');
    try {
        if (@file_put_contents($tmp, $json . "\n", LOCK_EX) === false
            || !@chown($tmp, 0)
            || !@chgrp($tmp, (int)$stat['gid'])
            || !@chmod($tmp, 0640)) {
            throw new RuntimeException('Tijdelijk privileged snapshotbestand kon niet veilig worden geschreven.');
        }
        $tmpStat = @lstat($tmp);
        if (!is_array($tmpStat) || is_link($tmp) || !is_file($tmp)
            || (int)$tmpStat['uid'] !== 0 || (int)$tmpStat['gid'] !== (int)$stat['gid']
            || (((int)$tmpStat['mode'] & 0777) !== 0640)) {
            throw new RuntimeException('Tijdelijk privileged snapshotbestand heeft onveilige metadata.');
        }
        if (is_link($snapshotPath) || !@rename($tmp, $snapshotPath)) {
            throw new RuntimeException('Privileged snapshot kon niet atomisch worden gepubliceerd.');
        }
        $tmp = '';
    } finally {
        if ($tmp !== '' && (is_file($tmp) || is_link($tmp))) @unlink($tmp);
    }
}

$args = array_slice($_SERVER['argv'] ?? [], 1);
$help = in_array('--help', $args, true);
$refreshOnly = in_array('--refresh-only', $args, true);
try {
    $engineRoot = cp135EngineRoot();
    $executor = $engineRoot . '/bin/control-plane-executor.php';
    if (is_link($executor) || !is_file($executor)) throw new RuntimeException('Root-owned control-plane executor ontbreekt.');
    $cmd = ['/usr/bin/php8.5', $executor, ...$args];
    [$code, $out, $err] = process521Run($cmd, null, null, null, 3600);
    if ($out !== '') fwrite(STDOUT, $out);
    if ($err !== '') fwrite(STDERR, $err);
    if ($code !== 0 || $help) exit($code);

    $configPath = cp135ConfigArgument($args);
    if ($configPath === null) throw new RuntimeException('Control-plane config ontbreekt voor privileged integriteitsmeting.');
    try {
        cp135Publish($configPath);
    } catch (Throwable $publishError) {
        if ($refreshOnly) throw $publishError;
        fwrite(STDERR, 'WAARSCHUWING: privileged integriteitsstatus kon na executoruitvoering niet worden gepubliceerd: ' . $publishError->getMessage() . "\n");
    }
    exit(0);
} catch (Throwable $e) {
    cp135Stop($e->getMessage(), 70);
}
