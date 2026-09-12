<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function f282(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) {
        $ok++;
        echo "OK: {$label}\n";
        return;
    }
    $fout++;
    fwrite(STDERR, "FOUT: {$label}\n");
}

require_once $root . '/app/leden/groepen.php';

function f282Groep(string $status, string $einddatum = '', array $extra = []): array
{
    return array_merge([
        'id' => 'werkgroep_test',
        'type' => 'werkgroep',
        'naam' => 'Testgroep',
        'omschrijving' => '',
        'doel' => '',
        'status' => $status,
        'startdatum' => '2026-01-10',
        'einddatum' => $einddatum,
        'leden' => [[
            'lid_id' => 'lid_1',
            'rollen' => ['lid'],
            'sinds' => '2026-01-10',
            'tot' => $status === 'actief' ? '' : '2026-09-12',
        ]],
        'aangemaakt' => '2026-01-10T12:00:00+01:00',
        'gewijzigd' => '2026-09-12T12:00:00+02:00',
    ], $extra);
}

$oudActief = ['groepen' => [f282Groep('actief')]];
$nieuwArchief = ['groepen' => [f282Groep('gearchiveerd', '2026-09-12')]];
$gearchiveerd = groepenPeriodeTransities($nieuwArchief, $oudActief, '2026-09-12');
$archiefGroep = $gearchiveerd['groepen'][0];
f282(($archiefGroep['status'] ?? '') === 'gearchiveerd', 'archiveren behoudt sluitstatus');
f282(array_key_exists('einddatum_voor_status', $archiefGroep), 'archiveren legt periodeherstelmetadata vast');
f282(($archiefGroep['einddatum_voor_status'] ?? null) === '', 'archiveren bewaart een oorspronkelijk lege einddatum');
f282(($archiefGroep['einddatum'] ?? '') === '2026-09-12', 'archiveren behoudt de administratieve sluitdatum');

$herstelInput = ['groepen' => [f282Groep('actief', '2026-09-12')]];
$hersteld = groepenPeriodeTransities($herstelInput, $gearchiveerd, '2026-09-13');
$hersteldGroep = $hersteld['groepen'][0];
f282(($hersteldGroep['status'] ?? '') === 'actief', 'herstellen zet groep actief');
f282(($hersteldGroep['einddatum'] ?? null) === '', 'herstellen verwijdert alleen de door sluiting geïntroduceerde einddatum');
f282(!array_key_exists('einddatum_voor_status', $hersteldGroep), 'herstellen ruimt tijdelijke periodeherstelmetadata op');
f282(count(groepenActieveLeden($hersteldGroep)) === 0, 'herstellen heropent afgesloten deelnames niet automatisch');

$oudMetEinddatum = ['groepen' => [f282Groep('actief', '2026-12-31')]];
$nieuwMetSluitstatus = ['groepen' => [f282Groep('gearchiveerd', '2026-12-31')]];
$archiefMetEinddatum = groepenPeriodeTransities($nieuwMetSluitstatus, $oudMetEinddatum, '2026-09-12');
f282(($archiefMetEinddatum['groepen'][0]['einddatum_voor_status'] ?? '') === '2026-12-31', 'archiveren bewaart een reeds bestaande einddatum');
$herstelMetEinddatum = ['groepen' => [f282Groep('actief', '2026-12-31')]];
$hersteldMetEinddatum = groepenPeriodeTransities($herstelMetEinddatum, $archiefMetEinddatum, '2026-09-13');
f282(($hersteldMetEinddatum['groepen'][0]['einddatum'] ?? '') === '2026-12-31', 'herstellen zet een vóór archivering bestaande einddatum terug');

$blijftGeslotenInput = ['groepen' => [f282Groep('afgerond', '2026-09-12')]];
$blijftGesloten = groepenPeriodeTransities($blijftGeslotenInput, $gearchiveerd, '2026-09-13');
f282(array_key_exists('einddatum_voor_status', $blijftGesloten['groepen'][0]), 'wissel tussen gesloten statussen behoudt herstelmetadata');
f282(($blijftGesloten['groepen'][0]['einddatum_voor_status'] ?? null) === '', 'wissel tussen gesloten statussen behoudt oorspronkelijke lege einddatum');

$legacyOud = ['groepen' => [f282Groep('gearchiveerd', '2025-12-31')]];
$legacyNieuw = ['groepen' => [f282Groep('actief', '2025-12-31')]];
$legacyHerstel = groepenPeriodeTransities($legacyNieuw, $legacyOud, '2026-09-13');
f282(($legacyHerstel['groepen'][0]['einddatum'] ?? '') === '2025-12-31', 'legacy gesloten groep zonder metadata behoudt bestaande einddatum fail-safe');

$nieuwGesloten = ['groepen' => [f282Groep('gearchiveerd', '')]];
$nieuwGeslotenResultaat = groepenPeriodeTransities($nieuwGesloten, ['groepen' => []], '2026-09-12');
f282(($nieuwGeslotenResultaat['groepen'][0]['einddatum'] ?? '') === '2026-09-12', 'nieuw direct gesloten groep krijgt een sluitdatum');
f282(($nieuwGeslotenResultaat['groepen'][0]['einddatum_voor_status'] ?? null) === '', 'nieuw direct gesloten groep kan later naar een lege oorspronkelijke periode herstellen');

$genormaliseerd = groepenNormaliseerDocument(['groepen' => [$archiefGroep]]);
f282(array_key_exists('einddatum_voor_status', $genormaliseerd['groepen'][0]), 'normalisatie bewaart periodeherstelmetadata inclusief lege waarde');

echo "Functioneel #282 groepenperiodeherstel: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
