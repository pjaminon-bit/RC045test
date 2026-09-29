<?php
$root = dirname(__DIR__);
require_once $root . '/app/auth-storage.php';

ob_start();

$ok = 0;
$fout = 0;

function check411f(bool $cond, string $label): void
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

function rr411f(string $pad): void
{
    if (is_link($pad)) {
        @unlink($pad);
        return;
    }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $kind = $pad . DIRECTORY_SEPARATOR . $item;
        if (is_dir($kind) && !is_link($kind)) rr411f($kind); else @unlink($kind);
    }
    @rmdir($pad);
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phase411-session-fallback-' . bin2hex(random_bytes(5));
$expected = $tmp . '/pilot/private/sessions';
$wrong = $tmp . '/andere-tenant/private/sessions';
@mkdir($expected, 0700, true);
@mkdir($wrong, 0700, true);

$requestedId = str_repeat('a', 32);
$sessionName = 'VST' . bin2hex(random_bytes(6));

try {
    ini_set('session.save_handler', 'files');
    ini_set('session.save_path', $wrong);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_probability', '0');

    check411f(
        is_subclass_of(AuthStorageTenantFileSessionHandler::class, SessionHandler::class)
        && in_array(SessionUpdateTimestampHandlerInterface::class, class_implements(AuthStorageTenantFileSessionHandler::class) ?: [], true),
        'tenant fallback behoudt native files-handler en implementeert strict-mode validatie'
    );

    session_name($sessionName);
    check411f(
        authStorageRegistreerTenantFileSessionHandler($expected),
        'tenantgebonden native sessionhandler kan vóór session_start worden geregistreerd'
    );

    session_id($requestedId);
    check411f(session_start(), 'sessie start met tenantgebonden fallback');
    $actualId = session_id();
    check411f(
        $actualId !== '' && !hash_equals($requestedId, $actualId),
        'strict mode weigert een nog niet bestaande aangeleverde sessie-id'
    );

    $_SESSION['phase411_fallback'] = 'tenant-bound';
    session_write_close();

    $expectedFile = $expected . '/sess_' . $actualId;
    $wrongFile = $wrong . '/sess_' . $actualId;
    clearstatcache(true, $expectedFile);
    clearstatcache(true, $wrongFile);
    check411f(
        is_file($expectedFile) && !is_link($expectedFile),
        'sessiedata wordt in het expliciete tenantpad opgeslagen'
    );
    check411f(
        !file_exists($wrongFile) && !is_link($wrongFile),
        'foutieve actieve session.save_path ontvangt geen tenant-sessie'
    );

    session_id($actualId);
    check411f(session_start(), 'bestaande tenantgebonden sessie kan opnieuw worden geopend');
    check411f(
        ($_SESSION['phase411_fallback'] ?? null) === 'tenant-bound',
        'bestaande sessiedata wordt uit het eigen tenantpad teruggelezen'
    );
    session_write_close();
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    rr411f($tmp);
}

$output = ob_get_clean();
echo $output;
echo "Phase 4.1.1 tenant session fallback: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
