<?php
$root = dirname(__DIR__);

require_once $root . '/leden-opslag.php';
require_once $root . '/vergaderingen-opslag.php';
require_once $root . '/taken-opslag.php';
require_once $root . '/operationele-taken-opslag.php';
require_once $root . '/evenementen-opslag.php';
require_once $root . '/groepen-opslag.php';
require_once $root . '/ledenlabels-opslag.php';
require_once $root . '/aanmeldingen-opslag.php';
require_once $root . '/contactberichten-opslag.php';
require_once $root . '/app/leden/contributies.php';

function sljrrAssert(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}

function sljrrThrows(callable $callback): bool
{
    try { $callback(); }
    catch (RuntimeException $e) { return true; }
    return false;
}

$readers = [
    'leden' => [ledenBestandPad(), 'ledenLees', ['leden'=>[]]],
    'vergaderingen' => [vergaderingenBestandPad(), 'vergaderingenLees', ['vergaderingen'=>[]]],
    'taken' => [takenBestandPad(), 'takenLees', ['taken'=>[]]],
    'operationele_taken' => [otaakBestandPad(), 'otakenLees', ['taken'=>[]]],
    'evenementen' => [evenementBestandPad(), 'evenementenLees', ['evenementen'=>[]]],
    'groepen' => [groepenBestandPad(), 'groepenLees', ['rollen'=>[],'groepen'=>[]]],
    'ledenlabels' => [ledenlabelsBestandPad(), 'ledenlabelsLees', ['labels'=>[],'toewijzingen'=>[]]],
    'aanmeldingen' => [aanmeldingenBestandPad(), 'aanmeldingenJsonLees', ['aanmeldingen'=>[]]],
    'contributies' => [contributiesBestandPad(), 'contributiesJsonLees', ['regels'=>[]]],
    'contactberichten' => [contactBerichtenBestandPad(), 'contactBerichtenJsonLees', ['berichten'=>[]]],
];

// Deze test mag nooit echte lokale/runtime data aanraken. CI-checkouts horen
// deze gitignored bestanden niet te bevatten; lokaal wordt bij aanwezigheid
// fail-closed gestopt voordat ook maar één byte wordt geschreven.
foreach ($readers as $domein => [$pad]) {
    sljrrAssert(!file_exists($pad) && !is_link($pad), "{$domein}: runtimebestand bestaat al; test weigert lokale data aan te raken.");
}

$aangemaakt = [];
try {
    foreach ($readers as $domein => [$pad, $reader, $minimaal]) {
        // Ontbrekend bestand: bestaande compatibiliteit blijft een lege/new
        // collectie opleveren met de verwachte documentstructuur.
        $leeg = $reader();
        sljrrAssert(is_array($leeg), "{$domein}: ontbrekend bestand moet een leeg document opleveren.");
        foreach (array_keys($minimaal) as $sleutel) {
            sljrrAssert(isset($leeg[$sleutel]) && is_array($leeg[$sleutel]), "{$domein}: leeg document mist arrayveld {$sleutel}.");
        }

        // Syntactisch corrupte bestaande bron: hard falen, bytes exact
        // behouden en de read-modify-write vervolgstap nooit bereiken.
        $corrupt = "<?php exit; ?>\n{kapotte-json-{$domein}";
        sljrrAssert(file_put_contents($pad, $corrupt, LOCK_EX) !== false, "{$domein}: corrupte fixture kon niet worden geschreven.");
        $aangemaakt[$pad] = true;
        $vervolgBereikt = false;
        $gooide = sljrrThrows(static function() use ($reader, &$vervolgBereikt): void {
            $reader();
            $vervolgBereikt = true;
        });
        sljrrAssert($gooide, "{$domein}: corrupte bestaande JSON moet een storagefout geven.");
        sljrrAssert(!$vervolgBereikt, "{$domein}: caller mag na corrupte read niet bij een beheerwrite kunnen komen.");
        sljrrAssert(file_get_contents($pad) === $corrupt, "{$domein}: corrupte bronbytes zijn gewijzigd.");
        @unlink($pad); unset($aangemaakt[$pad]);

        // Parsebare maar structureel ongeldige bron is eveneens corrupt en
        // mag niet stil als leeg worden geïnterpreteerd.
        $ongeldig = "<?php exit; ?>\n" . json_encode(['verkeerd'=>[]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        sljrrAssert(file_put_contents($pad, $ongeldig, LOCK_EX) !== false, "{$domein}: structuurfixture kon niet worden geschreven.");
        $aangemaakt[$pad] = true;
        sljrrAssert(sljrrThrows($reader), "{$domein}: structureel ongeldig document moet een storagefout geven.");
        sljrrAssert(file_get_contents($pad) === $ongeldig, "{$domein}: structureel ongeldige bronbytes zijn gewijzigd.");
        @unlink($pad); unset($aangemaakt[$pad]);

        // Minimaal geldig bestaand document blijft leesbaar. Zo bewaken we
        // dat de fail-closed wijziging geen normale standalone opslag breekt.
        $geldig = "<?php exit; ?>\n" . json_encode($minimaal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        sljrrAssert(file_put_contents($pad, $geldig, LOCK_EX) !== false, "{$domein}: geldige fixture kon niet worden geschreven.");
        $aangemaakt[$pad] = true;
        $document = $reader();
        foreach (array_keys($minimaal) as $sleutel) {
            sljrrAssert(isset($document[$sleutel]) && is_array($document[$sleutel]), "{$domein}: geldig document verloor arrayveld {$sleutel}.");
        }
        @unlink($pad); unset($aangemaakt[$pad]);
    }
} finally {
    foreach (array_keys($aangemaakt) as $pad) @unlink($pad);
}

echo "security-legacy-json-readers-runtime: OK — 10 legacy private collecties fail-closed bewezen\n";
