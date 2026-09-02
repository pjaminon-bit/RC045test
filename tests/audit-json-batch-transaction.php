<?php
require_once dirname(__DIR__) . '/app/storage/private-store-batch-transaction.php';

function ajbtAssert(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, "FOUT: {$message}\n");
        exit(1);
    }
}

function ajbtWriter(string $pad, bool $mislukt = false): callable
{
    return static function(array $data) use ($pad, $mislukt): bool {
        if ($mislukt) return false;
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return $json !== false && file_put_contents($pad, $json, LOCK_EX) !== false;
    };
}

ajbtAssert(privateStoreDriver() === 'json', 'Deze regressietest verwacht het standalone JSON-profiel.');
ajbtAssert(privateStoreJsonRoot() === null, 'Deze regressietest verwacht de standalone legacy-padroute.');

$root = sys_get_temp_dir() . '/vp-json-batch-' . bin2hex(random_bytes(6));
if (!mkdir($root, 0700, true)) throw new RuntimeException('Testmap kon niet worden gemaakt.');
$a = $root . '/leden.php';
$b = $root . '/contributies.php';
$c = $root . '/aanmeldingen.php';
file_put_contents($a, 'leden-oud');
file_put_contents($b, 'contributies-oud');
file_put_contents($c, 'aanmeldingen-oud');

// Bestaande expliciete batchhelper: exception na meerdere writes moet alles terugzetten.
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

// #118-reproductie: gewone privateStoreTransactie() schreef in JSON vroeger write 1
// definitief weg voordat een latere collection-write faalde. De expliciete
// legacy-paden maken de test onafhankelijk van productie-databestanden.
file_put_contents($a, 'leden-voor-generic');
file_put_contents($b, 'contributies-voor-generic');
$laterWriteFout = false;
try {
    privateStoreTransactie(function() use ($a,$b): void {
        if (!privateStoreSchrijf('test_leden', ['waarde'=>'nieuw-lid'], ajbtWriter($a), $a)) {
            throw new RuntimeException('eerste write faalde onverwacht');
        }
        if (!privateStoreSchrijf('test_contributies', ['waarde'=>'nieuw-fin'], ajbtWriter($b, true), $b)) {
            throw new RuntimeException('gesimuleerde latere collection-write faalde');
        }
    });
} catch (RuntimeException $e) {
    $laterWriteFout = str_contains($e->getMessage(), 'gesimuleerde latere collection-write');
}
ajbtAssert($laterWriteFout, 'Latere collection-writefout moet de transactie verlaten als fout.');
ajbtAssert(file_get_contents($a) === 'leden-voor-generic', 'Een eerdere gewone JSON-transactionwrite moet na latere writefout worden teruggedraaid.');
ajbtAssert(file_get_contents($b) === 'contributies-voor-generic', 'Het falende latere opslagdoel moet zijn oorspronkelijke toestand behouden.');

// Ook een writer die false teruggeeft zonder dat de callback zelf gooit, moet
// door het centrale contract als transactiefout worden gezien en terugrollen.
file_put_contents($a, 'leden-voor-false');
file_put_contents($b, 'contributies-voor-false');
$falseResult = privateStoreTransactie(function() use ($a,$b): string {
    privateStoreSchrijf('test_leden', ['waarde'=>'nieuwer-lid'], ajbtWriter($a), $a);
    privateStoreSchrijf('test_contributies', ['waarde'=>'mislukt'], ajbtWriter($b, true), $b);
    return 'callback-negeerde-false';
});
ajbtAssert($falseResult === false, 'Een gemarkeerde writefout moet de JSON-transactie false laten teruggeven.');
ajbtAssert(file_get_contents($a) === 'leden-voor-false', 'Genegeerde writer-false mag geen eerdere collection-write laten staan.');
ajbtAssert(file_get_contents($b) === 'contributies-voor-false', 'Genegeerde writer-false mag het falende doel niet wijzigen.');

// Succespad van de gewone transactie: alle collecties committen samen.
$genericResult = privateStoreTransactie(function() use ($a,$b,$c): string {
    if (!privateStoreSchrijf('test_leden', ['waarde'=>'commit-lid'], ajbtWriter($a), $a)) throw new RuntimeException('leden commit faalde');
    if (!privateStoreSchrijf('test_contributies', ['waarde'=>'commit-fin'], ajbtWriter($b), $b)) throw new RuntimeException('contributies commit faalde');
    if (!privateStoreSchrijf('test_aanmeldingen', ['waarde'=>'commit-inbox'], ajbtWriter($c), $c)) throw new RuntimeException('aanmeldingen commit faalde');
    return 'generic-ok';
});
ajbtAssert($genericResult === 'generic-ok', 'Gewone JSON-transactie moet succesresultaat behouden.');
ajbtAssert(str_contains((string)file_get_contents($a), 'commit-lid'), 'Succespad moet ledenwrite committen.');
ajbtAssert(str_contains((string)file_get_contents($b), 'commit-fin'), 'Succespad moet contributiewrite committen.');
ajbtAssert(str_contains((string)file_get_contents($c), 'commit-inbox'), 'Succespad moet inboxwrite committen.');

// Corrupt/fout input tijdens een latere stap: eerdere writes rollback, corrupte
// bronbytes blijven onaangeroerd. Dit test het transactiefailurepad; #145 blijft
// apart verantwoordelijk voor legacy readers die corruptie nu nog als leeg zien.
file_put_contents($a, 'leden-voor-corrupt');
file_put_contents($b, "<?php exit; ?>\n{kapotte-json");
$corruptFout = false;
try {
    privateStoreTransactie(function() use ($a,$b): void {
        if (!privateStoreSchrijf('test_leden', ['waarde'=>'tijdelijk'], ajbtWriter($a), $a)) throw new RuntimeException('eerste corrupttest-write faalde');
        privateStoreLees('test_corrupt', static function() use ($b): array {
            $raw = file_get_contents($b);
            $start = is_string($raw) ? strpos($raw, '{') : false;
            $data = $start === false ? null : json_decode(substr($raw, $start), true);
            if (!is_array($data)) throw new RuntimeException('gesimuleerde corrupte latere collectie');
            return $data;
        });
    });
} catch (RuntimeException $e) {
    $corruptFout = str_contains($e->getMessage(), 'gesimuleerde corrupte latere collectie');
}
ajbtAssert($corruptFout, 'Corrupte latere input moet als fout uit de transactie komen.');
ajbtAssert(file_get_contents($a) === 'leden-voor-corrupt', 'Eerdere write moet na corrupte latere input rollbacken.');
ajbtAssert(file_get_contents($b) === "<?php exit; ?>\n{kapotte-json", 'Corrupte bronbytes mogen door rollback niet worden overschreven.');

// Nieuwe standalone collecties mogen niet ongesnapshot worden geschreven. Een
// ontbrekende rollbackbinding moet vóór de writer hard falen. Callers kunnen een
// expliciet absoluut legacy-pad meegeven zodra zo'n collectie transactioneel is.
$writerAangeroepen = false;
$bindingFout = false;
try {
    privateStoreTransactie(function() use (&$writerAangeroepen): void {
        privateStoreSchrijf('onbekende_multi_collectie', ['waarde'=>1], static function(array $data) use (&$writerAangeroepen): bool {
            $writerAangeroepen = true;
            return true;
        });
    });
} catch (RuntimeException $e) {
    $bindingFout = str_contains($e->getMessage(), 'rollbackbinding');
}
ajbtAssert($bindingFout, 'Ongebonden standalone collection-write moet fail-closed worden geweigerd.');
ajbtAssert(!$writerAangeroepen, 'Ongebonden standalone collection-write mag de writer nooit bereiken.');

$import = file_get_contents(dirname(__DIR__) . '/beheer/leden-import.php');
ajbtAssert(is_string($import), 'leden-import.php kon niet worden gelezen.');
ajbtAssert(str_contains($import, "privateStoreBatchTransactie(['leden','contributies'],[ledenBestandPad(),contributiesBestandPad()]"), 'CSV-import moet Leden en Contributies aan één batchtransactie binden.');
ajbtAssert(!str_contains($import, 'In PDO-modus is de transactie teruggedraaid'), 'Importmelding mag JSON niet langer als niet-transactioneel behandelen.');

@unlink($a); @unlink($b); @unlink($c); @rmdir($root);
echo "audit-json-batch-transaction: OK\n";
