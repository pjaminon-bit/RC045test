<?php
// ============================================================
// Taken -> commissie contract
// ------------------------------------------------------------
// Nieuwe taak-koppelingen gebruiken uitsluitend persistente groep-ID's uit
// de groepenrepository. De oude leden['commissies']-map wordt alleen nog
// gelezen om een reeds opgeslagen legacy koppeling begrijpelijk te tonen.
//
// taak.commissie_id is de ENIGE bron voor de primaire commissie van een
// taak. commissie_bron bewaart alleen provenance ('groep', 'legacy' of
// 'historisch') zodat een oude legacy-sleutel die toevallig exact gelijk is
// aan een nieuwe groep-ID niet stil van betekenis verandert.
// groepen['relaties']['taken'] is het generieke many-to-many
// groepsrelatiecontract en is bewust niet dezelfde relatie; delete/cascade
// daarvan valt onder auditfinding #152.
// ============================================================
require_once __DIR__ . '/leden/groepen.php';

function taakCommissieDocument(array $doc): array
{
    // Belangrijk: geen groepenLeesDocument(). Die voegt legacy commissies uit
    // leden.json synthetisch toe. Nieuwe taak-koppelingen mogen daar niet van
    // afhangen en gebruiken alleen werkelijk persistente groepen.
    return groepenNormaliseerDocument($doc);
}

function taakCommissieGroepen(array $doc, bool $alleStatussen = true): array
{
    $uit = [];
    foreach ((array) (taakCommissieDocument($doc)['groepen'] ?? []) as $groep) {
        if (!is_array($groep) || ($groep['type'] ?? '') !== 'commissie') continue;
        if (!$alleStatussen && ($groep['status'] ?? 'actief') !== 'actief') continue;
        $uit[(string) $groep['id']] = $groep;
    }
    uasort($uit, static fn($a, $b) => strnatcasecmp((string) $a['naam'], (string) $b['naam']));
    return $uit;
}

function taakCommissieActieveKeuzes(array $doc): array
{
    $uit = [];
    foreach (taakCommissieGroepen($doc, false) as $id => $groep) {
        $uit[$id] = (string) $groep['naam'];
    }
    return $uit;
}

function taakCommissieLegacyNamen(array $ledenData): array
{
    // Expliciete compatibiliteitsreader voor HISTORISCHE taakwaarden. Niet
    // gebruiken om nieuwe keuzes te bouwen of nieuwe relaties te valideren.
    $uit = [];
    foreach ((array) ($ledenData['commissies'] ?? []) as $sleutel => $waarde) {
        $id = ledenCommissieSleutel($sleutel);
        $naam = is_array($waarde) ? ($waarde['naam'] ?? '') : $waarde;
        $naam = ledenKort($naam, 60);
        if ($id !== '' && $naam !== '') $uit[$id] = $naam;
    }
    return $uit;
}

function taakCommissieBron($bron): string
{
    $bron = trim((string) $bron);
    return in_array($bron, ['groep', 'legacy', 'historisch'], true) ? $bron : '';
}

function taakCommissieContext(array $groepenDoc, array $ledenData, string $id, string $bron = ''): array
{
    $id = groepenKort($id, 80);
    $bron = taakCommissieBron($bron);
    if ($id === '') return ['id' => '', 'soort' => 'geen', 'label' => '', 'status' => ''];

    $groepen = taakCommissieGroepen($groepenDoc, true);
    $legacy = taakCommissieLegacyNamen($ledenData);

    if ($bron === 'legacy') {
        $label = $legacy[$id] ?? ('Legacy commissie · ' . $id);
        return ['id' => $id, 'soort' => 'legacy', 'label' => $label . ' (legacy-koppeling)', 'status' => 'historisch'];
    }
    if ($bron === 'historisch') {
        $label = $legacy[$id] ?? ('Historische/onbekende commissie · ' . $id);
        return ['id' => $id, 'soort' => 'historisch', 'label' => $label . ' (historische koppeling)', 'status' => 'historisch'];
    }

    if ($bron === '' && isset($groepen[$id]) && isset($legacy[$id])) {
        return [
            'id' => $id,
            'soort' => 'ambigu',
            'label' => $legacy[$id] . ' (ambigue legacy/groep-koppeling)',
            'status' => 'historisch',
        ];
    }

    if (isset($groepen[$id])) {
        $groep = $groepen[$id];
        $status = (string) ($groep['status'] ?? 'actief');
        $label = (string) $groep['naam'];
        if ($status === 'gearchiveerd') $label .= ' (gearchiveerd)';
        elseif ($status === 'afgerond') $label .= ' (afgerond)';
        return ['id' => $id, 'soort' => 'groep', 'label' => $label, 'status' => $status];
    }

    if (isset($legacy[$id])) {
        return ['id' => $id, 'soort' => 'legacy', 'label' => $legacy[$id] . ' (legacy-koppeling)', 'status' => 'historisch'];
    }

    if ($bron === 'groep') {
        return ['id' => $id, 'soort' => 'groep', 'label' => 'Historische groep · ' . $id, 'status' => 'historisch'];
    }
    return ['id' => $id, 'soort' => 'onbekend', 'label' => 'Historische/onbekende commissie · ' . $id, 'status' => 'historisch'];
}

function taakCommissieValideerVoorOpslag(string $gevraagdId, ?array $bestaand, array $groepenDoc, array $ledenData): array
{
    $rauw = trim($gevraagdId);
    $lengte = function_exists('mb_strlen') ? mb_strlen($rauw, 'UTF-8') : strlen($rauw);
    if ($lengte > 80) return ['geldig' => false, 'id' => '', 'bron' => '', 'reden' => 'Commissie-id is ongeldig.'];
    $gevraagdId = groepenKort($rauw, 80);
    if ($gevraagdId === '') return ['geldig' => true, 'id' => '', 'bron' => '', 'reden' => ''];

    // Een ongewijzigde bestaande relatie wordt eerst beoordeeld. Daardoor
    // kan een pre-#151 legacy-id die exact gelijk is aan een huidige groep-ID
    // nooit stil als groep worden geclaimd.
    $bestaandId = groepenKort($bestaand['commissie_id'] ?? '', 80);
    if ($bestaand !== null && $bestaandId !== '' && hash_equals($bestaandId, $gevraagdId)) {
        $bestaandBron = taakCommissieBron($bestaand['commissie_bron'] ?? '');
        $context = taakCommissieContext($groepenDoc, $ledenData, $bestaandId, $bestaandBron);
        $bron = $bestaandBron;
        if ($bron === '') {
            if ($context['soort'] === 'groep') $bron = 'groep';
            elseif ($context['soort'] === 'legacy') $bron = 'legacy';
            else $bron = 'historisch';
        }
        return ['geldig' => true, 'id' => $bestaandId, 'bron' => $bron, 'reden' => $context['soort']];
    }

    // Iedere nieuwe/gewijzigde relatie moet naar een werkelijk persistente,
    // actieve commissie-groep wijzen. Dit is ook het gecontroleerde
    // migratiepad voor een bestaande legacy-id: de gebruiker kiest expliciet
    // de stabiele groep-ID.
    $actief = taakCommissieActieveKeuzes($groepenDoc);
    if (isset($actief[$gevraagdId])) return ['geldig' => true, 'id' => $gevraagdId, 'bron' => 'groep', 'reden' => ''];

    return ['geldig' => false, 'id' => '', 'bron' => '', 'reden' => 'Commissie is niet actief of bestaat niet in het groepenmodel.'];
}
