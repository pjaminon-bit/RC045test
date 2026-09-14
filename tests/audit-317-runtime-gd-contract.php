<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function a317(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) {
        $ok++;
        echo "OK: {$label}\n";
        return;
    }
    $fout++;
    fwrite(STDERR, "FOUT: {$label}\n");
}

function a317Run(array $argv): array
{
    $spec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proces = proc_open($argv, $spec, $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($proces)) {
        return [255, '', 'proc_open faalde'];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    return [proc_close($proces), (string)$stdout, (string)$stderr];
}

$tmp = sys_get_temp_dir() . '/rc045-audit-317-' . bin2hex(random_bytes(5));
@mkdir($tmp, 0750, true);

require_once $root . '/app/deployment/php-runtime-requirements.php';

try {
    [$code, $stdout, $stderr] = a317Run([
        PHP_BINARY,
        $root . '/bin/prepare-first-vps-bootstrap.php',
        '--source=' . $root,
        '--commit=' . str_repeat('a', 40),
        '--output=' . $tmp . '/bundle',
        '--platform-root=' . $tmp . '/platform',
        '--tenant-base=' . $tmp . '/tenants',
        '--platform-host=beheer.platform.example',
        '--platform-strategy=direct',
        '--platform-ipv4=203.0.113.10',
        '--tenant-key=voorbeeld',
        '--tenant-name=Voorbeeldvereniging',
        '--tenant-host=voorbeeld.platform.example',
        '--tenant-strategy=direct',
        '--tenant-ipv4=203.0.113.10',
        '--operator-user=platformadmin',
        '--php-version=8.5',
        '--modules=website,ledenadministratie',
        '--dry-run',
    ]);

    $plan = json_decode($stdout, true);
    a317($code === 0 && is_array($plan), 'fase-5.2 dry-run levert een geldig plan: ' . trim($stderr));

    $centraal = platformPhpRequiredExtensions();
    $bootstrap = is_array($plan) ? (array)($plan['preflight']['required_php_modules'] ?? []) : [];
    a317(in_array('gd', $centraal, true), 'centrale productie-runtime vereist GD');
    a317($bootstrap === $centraal, 'first-VPS bootstrap gebruikt exact de centrale PHP-extensielijst');

    $workflow = (string)file_get_contents($root . '/.github/workflows/php85-compatibility.yml');
    a317(
        preg_match('/^\s*extensions:\s*[^\n]*\bgd\b/m', $workflow) === 1,
        'PHP 8.5 workflow installeert GD expliciet'
    );
    a317(
        str_contains($workflow, 'php -m | grep -Fx gd'),
        'PHP 8.5 workflow bewijst dat GD geladen is'
    );

    $readme = (string)file_get_contents($root . '/README.md');
    a317(
        str_contains($readme, '`gd`') && str_contains($readme, 'fotoboek'),
        'README documenteert GD als productie-eis voor fotoboekverwerking'
    );

    $fotoboek = (string)file_get_contents($root . '/beheer/fotoboek-lib.php');
    a317(
        str_contains($fotoboek, 'imagecreatetruecolor(')
        && str_contains($fotoboek, 'imagecopyresampled(')
        && str_contains($fotoboek, 'imagejpeg('),
        'GD-eis blijft aan het daadwerkelijke productiepad voor fotobewerking gebonden'
    );

    a317(
        str_contains($fotoboek, "function_exists('exif_read_data')"),
        'optionele EXIF-functionaliteit wordt niet tot harde runtime-eis verheven'
    );
} finally {
    if (is_dir($tmp)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $pad = $item->getPathname();
            $item->isDir() ? @rmdir($pad) : @unlink($pad);
        }
        @rmdir($tmp);
    }
}

echo "Audit #317 runtime-GD: {$ok} checks OK, {$fout} fouten.\n";
exit($fout === 0 ? 0 : 1);
