<?php
$root = dirname(__DIR__);
require_once $root . '/app/auth-storage.php';

$ok = 0;
$fout = 0;

function check411(bool $cond, string $label): void
{
    global $ok, $fout;
    if ($cond) {
        $ok++;
        echo "OK: {$label}\n";
    } else {
        $fout++;
        fwrite(STDERR, "FOUT: {$label}\n");
    }
}

function rr411(string $pad): void
{
    if (is_link($pad)) {
        @unlink($pad);
        return;
    }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $kind = $pad . DIRECTORY_SEPARATOR . $item;
        if (is_dir($kind) && !is_link($kind)) rr411($kind); else @unlink($kind);
    }
    @rmdir($pad);
}

$authBron = (string)file_get_contents($root . '/app/auth-storage.php');
$runtimeBron = (string)file_get_contents($root . '/app/deployment/runtime-contract.php');

$actiefPos = strpos($authBron, '$actiefPad = (string)ini_get(\'session.save_path\');');
$guardPos = strpos($authBron, 'if (!hash_equals($sessiePad, $actiefPad))');
$setPos = strpos($authBron, '$gezet = ini_set(\'session.save_path\', $sessiePad);');

check411(
    str_contains($runtimeBron, "php_admin_value[session.save_path] = "),
    'FPM-contract zet tenant session.save_path als php_admin_value vast'
);
check411(
    $actiefPos !== false && $guardPos !== false && $setPos !== false
        && $actiefPos < $guardPos && $guardPos < $setPos,
    'auth-storage accepteert eerst een reeds exact vastgezet session.save_path voordat ini_set wordt geprobeerd'
);

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phase411-session-' . bin2hex(random_bytes(5));
$private = $tmp . '/private';
$sessions = $private . '/sessions';
$anderPad = $tmp . '/ander-session-pad';
@mkdir($sessions, 0750, true);
@mkdir($anderPad, 0750, true);
$oudePad = (string)ini_get('session.save_path');
$oudeNaam = session_name();
$config = [
    'vereniging' => [
        'sleutel' => 'phase411-test',
        'site_url' => 'https://phase411.example',
    ],
    'opslag' => [
        'private_root' => $private,
    ],
];

try {
    ini_set('session.save_path', $sessions);
    $context = authStorageActiveerSessieIsolatie($config, $root, $private);
    check411(
        ($context['path'] ?? '') === $sessions && (string)ini_get('session.save_path') === $sessions,
        'reeds correct session.save_path blijft bruikbaar zonder padwijziging'
    );

    session_name($oudeNaam);
    ini_set('session.save_path', $anderPad);
    $context2 = authStorageActiveerSessieIsolatie($config, $root, $private);
    check411(
        ($context2['path'] ?? '') === $sessions && (string)ini_get('session.save_path') === $sessions,
        'afwijkend maar wijzigbaar session.save_path wordt nog steeds naar tenantpad gecorrigeerd'
    );
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    session_name($oudeNaam);
    ini_set('session.save_path', $oudePad);
    rr411($tmp);
}

echo "Phase 4.1.1 FPM session admin path: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
