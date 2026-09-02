<?php
$root = dirname(__DIR__);
require_once $root . '/app/storage/legacy-private-json.php';

function sljrAssert(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, "FOUT: {$message}\n");
        exit(1);
    }
}

function sljrThrows(callable $callback): bool
{
    try { $callback(); }
    catch (RuntimeException $e) { return true; }
    return false;
}

$temp = sys_get_temp_dir() . '/rc045test-legacy-json-145-' . bin2hex(random_bytes(6));
if (!mkdir($temp, 0700, true)) throw new RuntimeException('Tijdelijke testmap kon niet worden gemaakt.');
$pad = $temp . '/legacy-data.php';

// Ontbrekend runtimebestand blijft een geldige lege/new-collection toestand.
sljrAssert(legacyPrivateJsonLees($pad, 'test', ['regels']) === null, 'Ontbrekend legacy bestand moet als ontbrekend worden gemeld, niet als fout.');

// Syntaxcorruptie faalt hard en de oorspronkelijke bytes blijven exact staan.
$corrupt = "<?php exit; ?>\n{kapotte-json";
file_put_contents($pad, $corrupt);
sljrAssert(sljrThrows(static fn() => legacyPrivateJsonLees($pad, 'test', ['regels'])), 'Corrupte legacy JSON moet een storagefout geven.');
sljrAssert(file_get_contents($pad) === $corrupt, 'Corrupte legacy bronbytes moeten byte-for-byte behouden blijven.');

// Geldige JSON met ongeldige documentstructuur is eveneens geen lege collectie.
$structuur = "<?php exit; ?>\n" . json_encode(['verkeerd' => []], JSON_UNESCAPED_UNICODE);
file_put_contents($pad, $structuur);
sljrAssert(sljrThrows(static fn() => legacyPrivateJsonLees($pad, 'test', ['regels'])), 'Structureel ongeldig legacy document moet een storagefout geven.');
sljrAssert(file_get_contents($pad) === $structuur, 'Structureel ongeldige legacy bronbytes mogen niet wijzigen.');

// Een bestaand niet-regulier opslagdoel mag nooit als "ontbreekt dus leeg" gelden.
@unlink($pad);
mkdir($pad, 0700);
sljrAssert(sljrThrows(static fn() => legacyPrivateJsonLees($pad, 'test', ['regels'])), 'Bestaand niet-regulier legacy opslagdoel moet fail-closed zijn.');
@rmdir($pad);
@rmdir($temp);

// Repo-brede inventaris van ondersteunde standalone private collecties.
// De exacte helperaanroep is bewust een source contract: nieuwe/gewijzigde
// legacy readers mogen niet terugvallen naar handgeschreven "fout => leeg".
$readers = [
    'leden-opslag.php' => "legacyPrivateJsonLees(ledenBestandPad(), 'leden', ['leden'])",
    'vergaderingen-opslag.php' => "legacyPrivateJsonLees(vergaderingenBestandPad(), 'vergaderingen', ['vergaderingen'])",
    'taken-opslag.php' => "legacyPrivateJsonLees(takenBestandPad(), 'taken', ['taken'])",
    'operationele-taken-opslag.php' => "legacyPrivateJsonLees(otaakBestandPad(), 'operationele_taken', ['taken'])",
    'evenementen-opslag.php' => "legacyPrivateJsonLees(evenementBestandPad(), 'evenementen', ['evenementen'])",
    'groepen-opslag.php' => "legacyPrivateJsonLees(groepenBestandPad(),'groepen',['groepen'])",
    'ledenlabels-opslag.php' => "legacyPrivateJsonLees(ledenlabelsBestandPad(),'ledenlabels',['labels'])",
    'aanmeldingen-opslag.php' => "legacyPrivateJsonLees(aanmeldingenBestandPad(),'aanmeldingen',['aanmeldingen'])",
    'app/leden/contributies.php' => "legacyPrivateJsonLees(contributiesBestandPad(),'contributies',['regels'])",
    'contactberichten-opslag.php' => "legacyPrivateJsonLees(contactBerichtenBestandPad(),'contactberichten',['berichten'])",
];
foreach ($readers as $bestand => $needle) {
    $bron = file_get_contents($root . '/' . $bestand);
    sljrAssert(is_string($bron), $bestand . ' kon niet worden gelezen.');
    sljrAssert(str_contains($bron, $needle), $bestand . ' moet de centrale fail-closed legacy JSON-reader gebruiken.');
}

$privateStore = file_get_contents($root . '/app/storage/private-store.php');
sljrAssert(is_string($privateStore), 'private-store.php kon niet worden gelezen.');
sljrAssert(str_contains($privateStore, '$data=$jsonLezer();return is_array($data)?$data:[];'), 'Standalone privateStoreLees moet de legacy reader exception laten doorlopen.');
sljrAssert(!str_contains($privateStore, 'catch(Throwable $e){$data=$jsonLezer()'), 'privateStoreLees mag legacy readerfouten niet terugvertalen naar leeg.');

echo "security-legacy-json-readers-fail-closed: OK\n";
