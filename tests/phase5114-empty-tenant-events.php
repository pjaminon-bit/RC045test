<?php
$root = dirname(__DIR__);
require_once $root . '/app/storage/domein-repositories.php';

$ok = 0;
$fout = 0;
function c5114(bool $conditie, string $label): void {
    global $ok, $fout;
    if ($conditie) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

$leeg = repoNormaliseerEvenementenDocument([]);
c5114(isset($leeg['evenementen']) && is_array($leeg['evenementen']) && $leeg['evenementen'] === [], 'ontbrekende tenantcollectie wordt een leeg evenementendocument');
c5114(($leeg['volgnummer'] ?? null) === 0, 'leeg evenementendocument start met volgnummer nul');
c5114(evenementenGesorteerd($leeg) === [], 'ledenportaal kan lege evenementen veilig sorteren');
c5114(evenementVolgendNummer($leeg) === 1, 'beheer kan eerste evenement op lege tenant nummeren');

$bestaand = repoNormaliseerEvenementenDocument(['evenementen' => [], 'updated' => 'test']);
c5114(($bestaand['volgnummer'] ?? null) === 0, 'bestaand document zonder volgnummer krijgt compatibel nul');

$gooide = false;
try {
    repoNormaliseerEvenementenDocument(['updated' => 'beschadigd']);
} catch (RuntimeException $e) {
    $gooide = str_contains($e->getMessage(), 'ongeldige documentstructuur');
}
c5114($gooide, 'niet-lege corrupte evenementenstructuur blijft fail-closed');

echo "Phase 5.11.4 empty tenant events: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
