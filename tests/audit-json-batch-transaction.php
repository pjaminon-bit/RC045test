<?php
require_once dirname(__DIR__) . '/app/storage/private-store-batch-transaction.php';

function ajbtAssert(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, "FOUT: {$message}\n");
        exit(1);
    }
}

ajbtAssert(privateStoreDriver() === 'json', 'Deze regressietest verwacht het standalone JSON-profiel.');
ajbtAssert(privateStoreJsonRoot() === null, 'Deze regressietest verwacht de standalone legacy-padroute.');

$root = sys_get_temp_dir() . '/vp-json-batch-' . bin2hex(random_bytes(6));
if (!mkdir($root, 0700, true)) throw new RuntimeException('Testmap kon niet worden gemaakt.');
$a = $root . '/leden.php';
$b = $root . '/contributies.php';
file_put_contents($a, 'leden-oud');
file_put_contents($b, 'contributies-oud');

$exception = false;
try {
    privateStoreBatchTransactie(['leden','contributies'], [$a,$b], function() use ($a,$b): void {
        file_put_contents($a, 'leden-nieuw');
        file_put_contents($b, 'contributies-nieuw');
        throw new RuntimeException('simuleer fout na beide writes');
    });
} catch (RuntimeException $e) {
    $exception = true;
}
ajbtAssert($exception, 'Gesimuleerde importfout moet worden doorgegeven.');
ajbtAssert(file_get_contents($a) === 'leden-oud', 'Ledenbestand moet na fout volledig zijn teruggedraaid.');
ajbtAssert(file_get_contents($b) === 'contributies-oud', 'Contributiebestand moet na fout volledig zijn teruggedraaid.');

$result = privateStoreBatchTransactie(['leden','contributies'], [$a,$b], function() use ($a,$b): string {
    file_put_contents($a, 'leden-definitief');
    file_put_contents($b, 'contributies-definitief');
    return 'ok';
});
ajbtAssert($result === 'ok', 'Succesresultaat van batchtransactie moet behouden blijven.');
ajbtAssert(file_get_contents($a) === 'leden-definitief' && file_get_contents($b) === 'contributies-definitief', 'Succesvolle batchtransactie moet beide writes behouden.');

$import = file_get_contents(dirname(__DIR__) . '/beheer/leden-import.php');
ajbtAssert(is_string($import), 'leden-import.php kon niet worden gelezen.');
ajbtAssert(str_contains($import, "privateStoreBatchTransactie(['leden','contributies'],[ledenBestandPad(),contributiesBestandPad()]"), 'CSV-import moet Leden en Contributies aan één batchtransactie binden.');
ajbtAssert(!str_contains($import, 'In PDO-modus is de transactie teruggedraaid'), 'Importmelding mag JSON niet langer als niet-transactioneel behandelen.');

@unlink($a); @unlink($b); @rmdir($root);
echo "audit-json-batch-transaction: OK\n";
