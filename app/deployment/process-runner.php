<?php
// Fase 5.2.1 — gedeelde fail-closed subprocess-runner voor privileged deploymenttools.
// - uitsluitend absolute executables;
// - nooit via een shell;
// - stdout en stderr worden gelijktijdig gedraind;
// - stdin wordt tegelijk geschreven zodat volle pipes geen deadlock veroorzaken;
// - begrensde runtime en capture-output.

function process521Command(array $cmd): array
{
    if ($cmd === [] || !isset($cmd[0])) throw new RuntimeException('Procescommando ontbreekt.');
    $out = [];
    foreach ($cmd as $arg) {
        if (!is_string($arg) && !is_int($arg) && !is_float($arg)) throw new RuntimeException('Procesargument heeft ongeldig type.');
        $arg = (string)$arg;
        if (str_contains($arg, "\0")) throw new RuntimeException('Procesargument bevat NUL-byte.');
        $out[] = $arg;
    }
    $binary = $out[0];
    if (!str_starts_with($binary, '/') || preg_match('#(?:^|/)\.\.?(/|$)#', $binary)) {
        throw new RuntimeException('Privileged subprocess vereist een absoluut executablepad.');
    }
    if (!is_file($binary) || !is_executable($binary)) throw new RuntimeException('Executable ontbreekt of is niet uitvoerbaar: ' . $binary);
    return $out;
}

function process521Run(
    array $cmd,
    ?string $stdin = null,
    ?string $stdoutFile = null,
    ?array $env = null,
    int $timeoutSeconds = 900,
    int $maxCapturedBytes = 2097152
): array {
    $cmd = process521Command($cmd);
    if ($timeoutSeconds < 1 || $timeoutSeconds > 3600) throw new RuntimeException('Ongeldige subprocess-timeout.');
    if ($maxCapturedBytes < 65536 || $maxCapturedBytes > 16777216) throw new RuntimeException('Ongeldige subprocess-outputlimiet.');
    if ($stdoutFile !== null) {
        if (!str_starts_with($stdoutFile, '/') || str_contains($stdoutFile, "\0") || preg_match('#(?:^|/)\.\.?(/|$)#', $stdoutFile) || is_link($stdoutFile)) {
            throw new RuntimeException('Subprocess stdout-bestandspad is onveilig.');
        }
    }
    if ($env !== null) {
        foreach ($env as $k => $v) {
            if (!is_string($k) || $k === '' || str_contains($k, "\0") || str_contains($k, '=') || (!is_string($v) && !is_int($v) && !is_float($v)) || str_contains((string)$v, "\0")) {
                throw new RuntimeException('Subprocess environment bevat ongeldige invoer.');
            }
            $env[$k] = (string)$v;
        }
    }

    $descriptor = [
        0 => ['pipe', 'r'],
        1 => $stdoutFile === null ? ['pipe', 'w'] : ['file', $stdoutFile, 'wb'],
        2 => ['pipe', 'w'],
    ];
    $pipes = [];
    $proc = @proc_open($cmd, $descriptor, $pipes, null, $env, ['bypass_shell' => true]);
    if (!is_resource($proc)) return [255, '', 'proces kon niet starten'];

    $in = $pipes[0] ?? null;
    $outPipe = $stdoutFile === null ? ($pipes[1] ?? null) : null;
    $errPipe = $pipes[2] ?? null;
    foreach ([$in, $outPipe, $errPipe] as $pipe) if (is_resource($pipe)) stream_set_blocking($pipe, false);

    $input = $stdin ?? '';
    $inputOffset = 0;
    if ($input === '' && is_resource($in)) { fclose($in); $in = null; }
    $stdout = '';
    $stderr = '';
    $deadline = microtime(true) + $timeoutSeconds;
    $knownExit = null;
    $forcedCode = null;
    $forcedMessage = '';

    while (true) {
        $status = @proc_get_status($proc);
        if (!is_array($status)) {
            $forcedCode = 125;
            $forcedMessage = 'processtatus kon niet worden gelezen';
            break;
        }
        $running = (bool)($status['running'] ?? false);
        $exit = (int)($status['exitcode'] ?? -1);
        if (!$running && $exit >= 0) $knownExit = $exit;
        if (!$running && is_resource($in)) { fclose($in); $in = null; }

        if ($running && microtime(true) > $deadline) {
            $forcedCode = 124;
            $forcedMessage = 'proces overschreed de maximale runtime';
            break;
        }

        $read = [];
        if (is_resource($outPipe)) $read[] = $outPipe;
        if (is_resource($errPipe)) $read[] = $errPipe;
        $write = [];
        if ($running && is_resource($in) && $inputOffset < strlen($input)) $write[] = $in;

        if (!$running && $read === []) break;
        if ($read === [] && $write === []) { usleep(10000); continue; }

        $except = null;
        $ready = @stream_select($read, $write, $except, 0, 200000);
        if ($ready === false) {
            $forcedCode = 125;
            $forcedMessage = 'procespipes konden niet veilig worden gemultiplexed';
            break;
        }

        foreach ($write as $pipe) {
            $chunk = substr($input, $inputOffset, 65536);
            $written = @fwrite($pipe, $chunk);
            if ($written === false) {
                $forcedCode = 125;
                $forcedMessage = 'proces-stdin kon niet worden geschreven';
                break 2;
            }
            if ($written > 0) $inputOffset += $written;
            if ($inputOffset >= strlen($input) && is_resource($in)) { fclose($in); $in = null; }
        }

        foreach ($read as $pipe) {
            $chunk = @fread($pipe, 65536);
            if ($chunk === false) {
                $forcedCode = 125;
                $forcedMessage = 'procesoutput kon niet worden gelezen';
                break 2;
            }
            if ($chunk !== '') {
                if ($pipe === $outPipe) $stdout .= $chunk;
                else $stderr .= $chunk;
                if (strlen($stdout) > $maxCapturedBytes || strlen($stderr) > $maxCapturedBytes) {
                    $forcedCode = 126;
                    $forcedMessage = 'procesoutput overschrijdt de capturelimiet';
                    break 2;
                }
            }
            if (feof($pipe)) {
                fclose($pipe);
                if ($pipe === $outPipe) $outPipe = null;
                if ($pipe === $errPipe) $errPipe = null;
            }
        }
    }

    if ($forcedCode !== null) {
        @proc_terminate($proc);
        usleep(100000);
        $status = @proc_get_status($proc);
        if (is_array($status) && ($status['running'] ?? false)) @proc_terminate($proc, 9);
    }

    foreach ([$in, $outPipe, $errPipe] as $pipe) if (is_resource($pipe)) fclose($pipe);
    $closed = proc_close($proc);
    if ($forcedCode !== null) {
        $stderr = trim($stderr . ($stderr !== '' ? "\n" : '') . $forcedMessage);
        return [$forcedCode, trim($stdout), $stderr];
    }
    $code = $knownExit ?? $closed;
    return [$code, trim($stdout), trim($stderr)];
}
