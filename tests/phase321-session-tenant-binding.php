<?php
require_once dirname(__DIR__) . '/app/auth-session-tenant.php';

$checks = [];
function check321session(bool $cond, string $label): void
{
    global $checks;
    $checks[] = [$cond, $label];
}
function rrmdir321session(string $dir): void
{
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $pad = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($pad)) rrmdir321session($pad); else @unlink($pad);
    }
    @rmdir($dir);
}
function nieuwe321session(): string
{
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    session_id('');
    if (!session_start()) throw new RuntimeException('Testsessie kon niet starten.');
    return session_id();
}
function open321session(string $id): void
{
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    session_id($id);
    if (!session_start()) throw new RuntimeException('Bestaande testsessie kon niet openen.');
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rc045-phase321-session-' . bin2hex(random_bytes(5));
@mkdir($tmp, 0700, true);
ini_set('session.save_path', $tmp);
ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.cache_limiter', '');
session_name('RC045TESTSESSION');

try {
    // 1. Leg een echte geauthenticeerde sessie voor tenant A vast.
    $aId = nieuwe321session();
    $_SESSION = [
        'tenant_key' => 'tenant-a',
        'csrf' => 'csrf-a',
        'gebruiker' => 'alice',
        'is_master' => false,
        'user_session_version' => 7,
    ];
    session_write_close();
    $aBestand = $tmp . '/sess_' . $aId;
    $aHashVoor = is_file($aBestand) ? hash_file('sha256', $aBestand) : '';
    check321session($aHashVoor !== '', 'tenant A sessiebestand bestaat');

    // 2. Presenteer exact A's session-id aan tenant B. De guard moet A alleen
    //    lezen, met session_abort loslaten en voor B een schoon nieuw ID maken.
    open321session($aId);
    $csrf = (string)($_SESSION['csrf'] ?? '');
    $okB = authSessionTenantBewaak('tenant-b', true, $csrf);
    $bId = session_id();
    check321session($okB === false, 'tenant B weigert sessie die aan tenant A is gebonden');
    check321session($bId !== '' && !hash_equals($aId, $bId), 'tenant B krijgt na mismatch een nieuw session-id');
    check321session(($_SESSION['tenant_key'] ?? '') === 'tenant-b', 'schone vervangsessie is aan tenant B gebonden');
    check321session(!isset($_SESSION['gebruiker']) && !isset($_SESSION['is_master']) && !isset($_SESSION['user_session_version']), 'authstate van tenant A gaat niet mee naar tenant B');
    check321session($csrf !== '' && hash_equals($csrf, (string)($_SESSION['csrf'] ?? '')), 'CSRF-token wordt na sessieherstart correct vernieuwd');
    session_write_close();

    // 3. Bewijs dat B de oorspronkelijke sessie van A niet heeft vernietigd
    //    of overschreven. Dit is de reden voor session_abort i.p.v. destroy.
    $aHashNa = is_file($aBestand) ? hash_file('sha256', $aBestand) : '';
    check321session($aHashNa !== '' && hash_equals($aHashVoor, $aHashNa), 'mismatchrequest wijzigt sessiebestand van tenant A niet');
    open321session($aId);
    check321session(($_SESSION['tenant_key'] ?? '') === 'tenant-a' && ($_SESSION['gebruiker'] ?? '') === 'alice' && (int)($_SESSION['user_session_version'] ?? 0) === 7, 'oorspronkelijke tenant A sessie blijft volledig bruikbaar voor A');
    session_write_close();

    // 4. Externe tenant: oude reeds ingelogde sessie zonder tenantbinding is
    //    niet betrouwbaar en moet worden verworpen.
    $externOudId = nieuwe321session();
    $_SESSION = ['csrf' => 'oud', 'gebruiker' => 'oude-user', 'is_master' => false];
    session_write_close();
    open321session($externOudId);
    $csrfExtern = (string)$_SESSION['csrf'];
    $externOk = authSessionTenantBewaak('tenant-c', true, $csrfExtern);
    check321session($externOk === false && session_id() !== $externOudId, 'externe tenant weigert geauthenticeerde ongebonden legacy sessie');
    check321session(!isset($_SESSION['gebruiker']) && ($_SESSION['tenant_key'] ?? '') === 'tenant-c', 'vervanging van externe legacy sessie bevat geen oude authstate');
    session_write_close();

    // 5. Een anonieme externe sessie heeft nog geen rechten en mag veilig aan
    //    de huidige tenant worden gebonden zonder ID-rotatie.
    $anonId = nieuwe321session();
    $_SESSION = ['csrf' => 'anon'];
    $csrfAnon = 'anon';
    $anonOk = authSessionTenantBewaak('tenant-d', true, $csrfAnon);
    check321session($anonOk === true && session_id() === $anonId && ($_SESSION['tenant_key'] ?? '') === 'tenant-d', 'anonieme externe sessie wordt in-place aan actieve tenant gebonden');
    session_write_close();

    // 6. Standalone RC045: bestaande ingelogde sessies van vóór deze wijziging
    //    blijven werken en krijgen alleen tenant_key toegevoegd.
    $legacyId = nieuwe321session();
    $_SESSION = ['csrf' => 'legacy', 'gebruiker' => 'bestaand-account', 'user_session_version' => 1];
    $csrfLegacy = 'legacy';
    $legacyOk = authSessionTenantBewaak('rc045', false, $csrfLegacy);
    check321session($legacyOk === true && session_id() === $legacyId, 'standalone RC045 behoudt bestaand session-id');
    check321session(($_SESSION['gebruiker'] ?? '') === 'bestaand-account' && ($_SESSION['tenant_key'] ?? '') === 'rc045', 'standalone RC045 bindt bestaande login zonder uitloggen');
    session_write_close();

    // 7. Ook een beschadigde/niet-string tenantbinding faalt gesloten.
    $malformedId = nieuwe321session();
    $_SESSION = ['tenant_key' => ['tenant-e'], 'csrf' => 'x', 'gebruiker' => 'mallory'];
    $csrfMalformed = 'x';
    $malformedOk = authSessionTenantBewaak('tenant-e', true, $csrfMalformed);
    check321session($malformedOk === false && session_id() !== $malformedId && !isset($_SESSION['gebruiker']), 'ongeldige tenantbinding wordt fail-closed vervangen');
    session_write_close();

    // 8. Technische sleutel komt uit de actieve siteconfig.
    check321session(authSessionTenantSleutel(['vereniging' => ['sleutel' => 'Tenant-X']]) === 'tenant-x', 'sessiebinding gebruikt genormaliseerde actieve tenantkey');
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    rrmdir321session($tmp);
}

$ok = 0; $fout = 0;
foreach ($checks as [$cond, $label]) {
    if ($cond) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}
echo "Phase 3.2.1 session tenant binding: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
