<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function checkLIPS(bool $cond, string $label): void
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

function rrLIPS(string $dir): void
{
    if (is_link($dir)) {
        @unlink($dir);
        return;
    }
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $naam) {
        if ($naam === '.' || $naam === '..') continue;
        $pad = $dir . DIRECTORY_SEPARATOR . $naam;
        if (is_dir($pad) && !is_link($pad)) rrLIPS($pad);
        else @unlink($pad);
    }
    @rmdir($dir);
}

require_once $root . '/app/leden/import-preview-store.php';

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rc045-import-preview-' . bin2hex(random_bytes(5));
$private = $tmp . '/tenant/private';
$sessions = $private . '/sessions';
mkdir($sessions, 0750, true);

$auth = [
    'tenant_private' => true,
    'sessions' => $sessions,
    'session_binding' => hash('sha256', 'installatie-a'),
];
$ctxA = ledenImportPreviewStoreContext($auth, 'tenant-a', 'beheerder-a', 'session-a');
$ctxAndereUser = ledenImportPreviewStoreContext($auth, 'tenant-a', 'beheerder-b', 'session-a');
$ctxAndereSessie = ledenImportPreviewStoreContext($auth, 'tenant-a', 'beheerder-a', 'session-b');
$ctxAndereTenant = ledenImportPreviewStoreContext($auth, 'tenant-b', 'beheerder-a', 'session-a');

try {
    checkLIPS(is_array($ctxA), 'geldige tenant/user/sessiecontext wordt opgebouwd');
    checkLIPS(
        is_array($ctxA) && str_starts_with((string)$ctxA['root'], $private . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR),
        'externe tenantpreview blijft onder private_root'
    );
    checkLIPS(
        is_array($ctxA) && preg_match('/^[a-f0-9]{64}$/D', (string)$ctxA['owner_binding']) === 1,
        'ownerbinding wordt alleen als hash opgeslagen'
    );
    checkLIPS(
        is_array($ctxA) && is_array($ctxAndereUser) && !hash_equals((string)$ctxA['owner_binding'], (string)$ctxAndereUser['owner_binding']),
        'andere gebruiker krijgt andere ownerbinding'
    );
    checkLIPS(
        is_array($ctxA) && is_array($ctxAndereSessie) && !hash_equals((string)$ctxA['owner_binding'], (string)$ctxAndereSessie['owner_binding']),
        'andere PHP-sessie krijgt andere ownerbinding'
    );

    $resultaten = [];
    for ($i = 0; $i < LEDEN_IMPORT_PREVIEW_MAX_RIJEN; $i++) {
        $resultaten[] = [
            'rij' => ['voornaam' => 'Test', 'achternaam' => 'Lid' . $i, 'email' => 'lid' . $i . '@example.test'],
            'match_index' => null,
            'reden' => '',
            'naam' => 'Test Lid' . $i,
            'nummer' => $i + 1,
        ];
    }
    $preview5000 = [
        'bestand' => 'leden.csv',
        'resultaten' => $resultaten,
        'aantal_nieuw' => LEDEN_IMPORT_PREVIEW_MAX_RIJEN,
        'aantal_bijwerken' => 0,
        'aantal_financieel' => 0,
    ];

    $id = ledenImportPreviewStoreBewaar($ctxA, $preview5000);
    checkLIPS(is_string($id) && ledenImportPreviewStoreIdGeldig($id), 'maximaal 5000 rijen krijgen een 256-bit random preview-id');
    $pad = is_string($id) ? ledenImportPreviewStorePad($ctxA, $id) : null;
    checkLIPS(is_string($pad) && is_file($pad) && !is_link($pad), 'previewpayload staat in regulier server-side tempfile');
    checkLIPS(is_string($pad) && ((fileperms($pad) & 0777) === 0640), 'previewbestand heeft mode 0640');
    checkLIPS(is_array($ctxA) && ((fileperms((string)$ctxA['root']) & 0777) === 0750), 'previewmap heeft mode 0750');
    checkLIPS(is_string($pad) && filesize($pad) <= LEDEN_IMPORT_PREVIEW_MAX_BYTES, '5000-rijenpreview blijft onder de harde storegrens');

    $status = null;
    $gelezen = ledenImportPreviewStoreLees($ctxA, (string)$id, $status);
    checkLIPS(is_array($gelezen) && count($gelezen['resultaten'] ?? []) === 5000 && $status === 'ok', 'eigen sessie kan volledige preview teruglezen');

    $status = null;
    checkLIPS(
        ledenImportPreviewStoreLees($ctxAndereUser, (string)$id, $status) === null && $status === 'forbidden',
        'zelfde token is voor andere gebruiker niet bruikbaar'
    );
    $status = null;
    checkLIPS(
        ledenImportPreviewStoreLees($ctxAndereSessie, (string)$id, $status) === null && $status === 'forbidden',
        'zelfde token is vanuit andere PHP-sessie niet bruikbaar'
    );
    $status = null;
    checkLIPS(
        ledenImportPreviewStoreLees($ctxAndereTenant, (string)$id, $status) === null && $status === 'forbidden',
        'zelfde token is voor andere tenant niet bruikbaar'
    );
    checkLIPS(is_string($pad) && is_file($pad), 'cross-bindingpogingen verwijderen geldige preview niet');
    checkLIPS(!ledenImportPreviewStoreVerwijder($ctxAndereUser, (string)$id) && is_file((string)$pad), 'andere gebruiker kan preview ook niet annuleren/verwijderen');
    checkLIPS(ledenImportPreviewStoreVerwijder($ctxA, (string)$id) && !file_exists((string)$pad), 'annuleren/succes kan eigen preview actief verwijderen');

    unset($resultaten, $preview5000, $gelezen);

    $teVeel = ['bestand' => 'teveel.csv', 'resultaten' => [], 'aantal_nieuw' => 0, 'aantal_bijwerken' => 0, 'aantal_financieel' => 0];
    for ($i = 0; $i < LEDEN_IMPORT_PREVIEW_MAX_RIJEN + 1; $i++) {
        $teVeel['resultaten'][] = ['rij' => ['nummer' => $i], 'match_index' => null, 'reden' => '', 'naam' => 'x', 'nummer' => $i];
    }
    checkLIPS(ledenImportPreviewStoreBewaar($ctxA, $teVeel) === null, 'meer dan 5000 previewrijen wordt hard geweigerd');
    unset($teVeel);

    $teGroot = [
        'bestand' => 'tegroot.csv',
        'resultaten' => [[
            'rij' => ['opmerking' => str_repeat('X', LEDEN_IMPORT_PREVIEW_MAX_BYTES + 1024)],
            'match_index' => null,
            'reden' => '',
            'naam' => 'Te Groot',
            'nummer' => 1,
        ]],
        'aantal_nieuw' => 1,
        'aantal_bijwerken' => 0,
        'aantal_financieel' => 0,
    ];
    checkLIPS(ledenImportPreviewStoreBewaar($ctxA, $teGroot) === null, 'preview boven harde JSON-bytegrens wordt geweigerd');
    unset($teGroot);

    $nu = time();
    $klein = [
        'bestand' => 'klein.csv',
        'resultaten' => [[
            'rij' => ['voornaam' => 'Privacy', 'email' => 'privacy@example.test'],
            'match_index' => null,
            'reden' => '',
            'naam' => 'Privacy Test',
            'nummer' => 1,
        ]],
        'aantal_nieuw' => 1,
        'aantal_bijwerken' => 0,
        'aantal_financieel' => 0,
    ];
    $expId = ledenImportPreviewStoreBewaar($ctxA, $klein, $nu);
    $expPad = is_string($expId) ? ledenImportPreviewStorePad($ctxA, $expId) : null;
    $status = null;
    $expRead = is_string($expId) ? ledenImportPreviewStoreLees($ctxA, $expId, $status, $nu + LEDEN_IMPORT_PREVIEW_TTL + 1) : null;
    checkLIPS($expRead === null && $status === 'expired' && is_string($expPad) && !file_exists($expPad), 'preview wordt bij lezen direct na TTL verwijderd');

    $abandonId = ledenImportPreviewStoreBewaar($ctxA, $klein, $nu);
    $abandonPad = is_string($abandonId) ? ledenImportPreviewStorePad($ctxA, $abandonId) : null;
    $removed = ledenImportPreviewStoreCleanup($ctxA, $nu + LEDEN_IMPORT_PREVIEW_TTL + 1);
    checkLIPS($removed >= 1 && is_string($abandonPad) && !file_exists($abandonPad), 'opportunistische cleanup verwijdert verlaten preview rond TTL');

    checkLIPS(ledenImportPreviewStorePad($ctxA, '../sessie') === null, 'preview-id accepteert geen path traversal');

    $sentinel = $tmp . '/sentinel.txt';
    file_put_contents($sentinel, 'blijft-bestaan');
    $symlinkId = str_repeat('a', 64);
    $symlinkPad = (string)$ctxA['root'] . DIRECTORY_SEPARATOR . $symlinkId . '.json';
    @symlink($sentinel, $symlinkPad);
    if (is_link($symlinkPad)) {
        ledenImportPreviewStoreCleanup($ctxA, time());
        checkLIPS(!is_link($symlinkPad) && file_get_contents($sentinel) === 'blijft-bestaan', 'cleanup volgt symlinkpreview nooit naar extern doel');
    } else {
        checkLIPS(true, 'symlinktest niet ondersteund door testfilesystem; geen securityassertie overgeslagen in productiecode');
    }

    $standaloneAuth = [
        'tenant_private' => false,
        'sessions' => $tmp . '/standalone-sessions',
        'session_binding' => hash('sha256', 'standalone-installatie'),
    ];
    $standaloneCtx = ledenImportPreviewStoreContext($standaloneAuth, 'default', 'beheerder', 'session-x');
    checkLIPS(
        is_array($standaloneCtx) && str_starts_with((string)$standaloneCtx['root'], (string)$standaloneAuth['sessions']),
        'standalone preview gebruikt installatie-geïsoleerde server-side session-root in plaats van webroot'
    );

    $page = file_get_contents($root . '/beheer/leden-import.php');
    checkLIPS(
        is_string($page)
        && str_contains($page, "\$_SESSION['leden_import_preview_id'] = \$previewId")
        && !preg_match('/\$_SESSION\s*\[\s*[\'\"]leden_import_preview[\'\"]\s*\]\s*=/', $page),
        'ledenimport bewaart alleen opaque preview-id en geen previewpayload meer in PHP-sessie'
    );
    checkLIPS(
        is_string($page)
        && str_contains($page, 'ledenImportPreviewStoreCleanup($previewContext)')
        && str_contains($page, 'liPreviewWissen($previewContext)'),
        'pagina activeert TTL-cleanup en expliciete cleanup bij annuleren/succes'
    );
    checkLIPS(
        LEDEN_IMPORT_PREVIEW_TTL === 3600
        && LEDEN_IMPORT_PREVIEW_MAX_RIJEN === 5000
        && LEDEN_IMPORT_PREVIEW_MAX_BYTES === 16 * 1024 * 1024
        && LEDEN_IMPORT_PREVIEW_MAX_BESTANDEN === 200,
        'tempstore heeft harde TTL-, rij-, byte- en bestandlimieten'
    );
} finally {
    rrLIPS($tmp);
}

echo "Security ledenimport previewstore: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
