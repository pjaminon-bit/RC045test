<?php
// Regression #266: private writers mogen chmod-/modefailure nooit als succes rapporteren.

require_once dirname(__DIR__) . '/app/core/tenant-runtime.php';
require_once dirname(__DIR__) . '/app/storage/private-filesystem.php';
require_once dirname(__DIR__) . '/app/leden/import-preview-store.php';
require_once dirname(__DIR__) . '/app/storage/private-json-pdo-migration.php';
require_once dirname(__DIR__) . '/app/storage/backup-attestation.php';

function test266Assert(bool $ok, string $melding): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL security-266: {$melding}\n");
        exit(1);
    }
}

function test266Mode(string $pad): ?int
{
    clearstatcache(true, $pad);
    $mode = @fileperms($pad);
    return is_int($mode) ? ($mode & 0777) : null;
}

function test266Rm(string $pad): void
{
    if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach ((array)@scandir($pad) as $item) {
        if ($item === '.' || $item === '..') continue;
        test266Rm($pad . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($pad);
}

function test266Run(array $command): array
{
    test266Assert(function_exists('proc_open'), 'proc_open is nodig voor de geforceerde chmod-failuretest');
    $pipes = [];
    $proc = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    test266Assert(is_resource($proc), 'subprocess kon niet worden gestart');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    return ['code' => $code, 'stdout' => (string)$stdout, 'stderr' => (string)$stderr];
}

$basis = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rc045-security-266-' . bin2hex(random_bytes(6));
test266Assert(@mkdir($basis, 0700, true), 'tijdelijke testroot kon niet worden aangemaakt');
$oudeUmask = umask(0000);

try {
    // 1. Centrale primitive: process-umask mag nooit world-readable output opleveren.
    $primitiveMap = $basis . '/primitive';
    test266Assert(privateFilesystemBeveiligMap($primitiveMap, true), 'private map kon niet veilig worden aangemaakt');
    test266Assert(test266Mode($primitiveMap) === 0750, 'private map is niet exact 0750');
    $primitiveBestand = $primitiveMap . '/data.json';
    test266Assert(privateFilesystemAtomischSchrijf($primitiveBestand, "{}\n", 0640), 'private bestand kon niet atomisch worden geschreven');
    test266Assert(test266Mode($primitiveBestand) === 0640, 'private bestand is niet exact 0640');

    // 2. Echte ledenimport-previewwriter: owner/TTL-pad blijft werken en modes zijn aantoonbaar privé.
    $previewBoundary = $basis . '/sessions';
    test266Assert(@mkdir($previewBoundary, 0750, true), 'preview-boundary kon niet worden aangemaakt');
    @chmod($previewBoundary, 0750);
    $previewContext = [
        'root' => $previewBoundary . '/leden-import-previews',
        'boundary' => $previewBoundary,
        'tenant_key' => 'test-tenant',
        'owner_binding' => str_repeat('a', 64),
    ];
    $preview = ['resultaten' => [['rij' => ['naam' => 'Testpersoon', 'email' => 'test@example.invalid']]]];
    $previewId = ledenImportPreviewStoreBewaar($previewContext, $preview, 1700000000);
    test266Assert(is_string($previewId) && ledenImportPreviewStoreIdGeldig($previewId), 'previewwriter rapporteerde geen geldig id');
    $previewPad = ledenImportPreviewStorePad($previewContext, (string)$previewId);
    test266Assert(is_string($previewPad) && is_file($previewPad), 'previewbestand ontbreekt');
    test266Assert(test266Mode($previewContext['root']) === 0750, 'previewroot is niet exact 0750');
    test266Assert(test266Mode($previewPad) === 0640, 'previewbestand is niet exact 0640');
    $status = null;
    $gelezen = ledenImportPreviewStoreLees($previewContext, (string)$previewId, $status, 1700000001);
    test266Assert($status === 'ok' && $gelezen === $preview, 'preview owner/TTL-readcontract is gewijzigd');
    test266Assert(ledenImportPreviewStoreVerwijder($previewContext, (string)$previewId), 'preview kon niet worden verwijderd');

    // 3. Echte JSON→PDO-migratieartefacten: directories 0700, source/proof 0600.
    $privateRoot = $basis . '/tenant-private';
    $collections = $privateRoot . '/collections';
    test266Assert(@mkdir($collections, 0750, true), 'migratie-collectieroot kon niet worden aangemaakt');
    @chmod($privateRoot, 0750);
    @chmod($collections, 0750);
    $bronPad = $collections . '/leden.json';
    test266Assert(file_put_contents($bronPad, "[{\"id\":1,\"naam\":\"Test\"}]\n") !== false, 'migratiebron kon niet worden geschreven');
    @chmod($bronPad, 0640);
    $inventory = privateMigrationInventory($privateRoot);
    test266Assert(isset($inventory['leden']), 'migratie-inventory mist leden');
    $snapshot = privateMigrationSnapshot($privateRoot, 'test-tenant', $inventory);
    $sourcePad = $snapshot['source_dir'] . '/leden.json';
    test266Assert(test266Mode($privateRoot . '/migrations') === 0700, 'migrations-root is niet exact 0700');
    test266Assert(test266Mode($privateRoot . '/migrations/private-json-to-pdo') === 0700, 'migratie-root is niet exact 0700');
    test266Assert(test266Mode($snapshot['dir']) === 0700, 'snapshotdir is niet exact 0700');
    test266Assert(test266Mode($snapshot['source_dir']) === 0700, 'snapshot sourcedir is niet exact 0700');
    test266Assert(test266Mode($sourcePad) === 0600, 'migratiebronkopie is niet exact 0600');
    $summary = privateMigrationSamenvatting($inventory);
    $proof = privateMigrationProofSchrijf('test-tenant', $privateRoot, $snapshot, $inventory, ['target' => $summary]);
    test266Assert(is_file($proof['path']) && test266Mode($proof['path']) === 0600, 'migration-proof is niet exact 0600');

    // 4. Sidecarwriter: temp + final krijgen 0640.
    $attMap = $basis . '/attestation';
    test266Assert(@mkdir($attMap, 0750, true), 'attestationmap kon niet worden aangemaakt');
    @chmod($attMap, 0750);
    $sidecar = $attMap . '/snapshot.json.sig';
    test266Assert(backupAttestatieSchrijfSidecar($sidecar, ['schema' => 1, 'test' => true]), 'attestatiesidecar kon niet worden geschreven');
    test266Assert(test266Mode($sidecar) === 0640, 'attestatiesidecar is niet exact 0640');

    // 5. Geforceerde chmod-failure: centrale writer, preview, migratie en sidecar moeten allemaal fail-closed stoppen.
    $child = $basis . '/forced-failure.php';
    $repo = dirname(__DIR__);
    $childCode = <<<'PHP'
<?php
function privateFilesystemChmod(string $pad, int $mode): bool { return false; }
$repo = getenv('RC045_TEST_REPO');
$root = getenv('RC045_TEST_ROOT');
require_once $repo . '/app/core/tenant-runtime.php';
require_once $repo . '/app/storage/private-filesystem.php';
require_once $repo . '/app/leden/import-preview-store.php';
require_once $repo . '/app/storage/private-json-pdo-migration.php';
require_once $repo . '/app/storage/backup-attestation.php';
function fail266(string $m): never { fwrite(STDERR, $m . "\n"); exit(1); }
@mkdir($root, 0750, true);
@chmod($root, 0750);
$atomicDir = $root . '/atomic'; @mkdir($atomicDir, 0750, true); @chmod($atomicDir, 0750);
$atomic = $atomicDir . '/data.json';
if (privateFilesystemAtomischSchrijf($atomic, '{}', 0640) !== false || file_exists($atomic)) fail266('atomic writer accepteerde chmod-failure');
if (glob($atomic . '.tmp.*')) fail266('atomic writer liet tempbestand achter');
$boundary = $root . '/sessions'; @mkdir($boundary, 0750, true); @chmod($boundary, 0750);
$ctx = ['root'=>$boundary . '/leden-import-previews','boundary'=>$boundary,'tenant_key'=>'test-tenant','owner_binding'=>str_repeat('b',64)];
$preview = ['resultaten'=>[['rij'=>['naam'=>'Test']]]];
if (ledenImportPreviewStoreBewaar($ctx, $preview, 1700000000) !== null) fail266('previewwriter accepteerde chmod-failure');
$private = $root . '/tenant-private'; $collections = $private . '/collections'; @mkdir($collections, 0750, true); @chmod($private,0750); @chmod($collections,0750);
file_put_contents($collections . '/leden.json', '[]'); @chmod($collections . '/leden.json',0640);
$thrown = false;
try { privateMigrationInventory($private); } catch (RuntimeException $e) { $thrown = true; }
if (!$thrown) fail266('migratie accepteerde directory chmod-failure');
$att = $root . '/att'; @mkdir($att,0750,true); @chmod($att,0750);
$sidecar = $att . '/x.sig';
if (backupAttestatieSchrijfSidecar($sidecar, ['schema'=>1]) !== false || file_exists($sidecar)) fail266('sidecarwriter accepteerde chmod-failure');
if (glob($sidecar . '.tmp.*')) fail266('sidecarwriter liet tempbestand achter');
echo "forced-failure-ok\n";
PHP;
    test266Assert(file_put_contents($child, $childCode) !== false, 'failure-childscript kon niet worden geschreven');
    $envRoot = $basis . '/forced';
    putenv('RC045_TEST_REPO=' . $repo);
    putenv('RC045_TEST_ROOT=' . $envRoot);
    $childResult = test266Run([PHP_BINARY, $child]);
    test266Assert($childResult['code'] === 0 && str_contains($childResult['stdout'], 'forced-failure-ok'), 'geforceerde chmod-failuretest faalde: ' . trim($childResult['stderr']));

    // 6. Provisioning gebruikt dezelfde primitive en mag onder dezelfde syscallfailure geen config publiceren.
    $prepend = $basis . '/prepend.php';
    test266Assert(file_put_contents($prepend, "<?php function privateFilesystemChmod(string \$pad, int \$mode): bool { return false; }\n") !== false, 'prepend kon niet worden geschreven');
    $provisionRoot = $basis . '/provision-root';
    test266Assert(@mkdir($provisionRoot, 0750, true), 'provision-root kon niet worden aangemaakt');
    @chmod($provisionRoot, 0750);
    $prov = test266Run([
        PHP_BINARY,
        '-d', 'auto_prepend_file=' . $prepend,
        $repo . '/bin/provision-tenant.php',
        '--key=test-tenant-266',
        '--name=Test Tenant',
        '--url=https://example.invalid',
        '--root=' . $provisionRoot,
        '--driver=json',
    ]);
    test266Assert($prov['code'] !== 0, 'provisioner rapporteerde succes terwijl chmod geforceerd faalde');
    test266Assert(!is_file($provisionRoot . '/test-tenant-266/config.php'), 'provisioner publiceerde config ondanks chmod-failure');

    echo "OK security-266 private mode fail-closed\n";
} finally {
    umask($oudeUmask);
    test266Rm($basis);
}
