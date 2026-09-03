<?php
// ============================================================
// Taken -> commissie contract
// ------------------------------------------------------------
// Nieuwe taak-koppelingen gebruiken uitsluitend persistente groep-ID's uit
// de groepenrepository. De oude leden['commissies']-map wordt alleen nog
// gelezen om een reeds opgeslagen legacy koppeling begrijpelijk te tonen.
//
// taak.commissie_id is de ENIGE bron voor de primaire commissie van een
// taak. groepen['relaties']['taken'] is het generieke many-to-many
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

function taakCommissieContext(array $groepenDoc, array $ledenData, string $id): array
{
    $id = groepenKort($id, 80);
    if ($id === '') return ['id' => '', 'soort' => 'geen', 'label' => '', 'status' => ''];

    $groepen = taakCommissieGroepen($groepenDoc, true);
    if (isset($groepen[$id])) {
        $groep = $groepen[$id];
        $status = (string) ($groep['status'] ?? 'actief');
        $label = (string) $groep['naam'];
        if ($status === 'gearchiveerd') $label .= ' (gearchiveerd)';
        elseif ($status === 'afgerond') $label .= ' (afgerond)';
        return ['id' => $id, 'soort' => 'groep', 'label' => $label, 'status' => $status];
    }

    $legacy = taakCommissieLegacyNamen($ledenData);
    if (isset($legacy[$id])) {
        return ['id' => $id, 'soort' => 'legacy', 'label' => $legacy[$id] . ' (legacy-koppeling)', 'status' => 'historisch'];
    }

    return ['id' => $id, 'soort' => 'onbekend', 'label' => 'Historische/onbekende commissie · ' . $id, 'status' => 'historisch'];
}

function taakCommissieValideerVoorOpslag(string $gevraagdId, ?array $bestaand, array $groepenDoc, array $ledenData): array
{
    $gevraagdId = groepenKort($gevraagdId, 80);
    if ($gevraagdId === '') return ['geldig' => true, 'id' => '', 'reden' => ''];

    // Iedere nieuwe/gewijzigde relatie moet naar een werkelijk persistente,
    // actieve commissie-groep wijzen.
    $actief = taakCommissieActieveKeuzes($groepenDoc);
    if (isset($actief[$gevraagdId])) return ['geldig' => true, 'id' => $gevraagdId, 'reden' => ''];

    // Historie mag bij een edit niet stil verdwijnen. Een bestaande relatie
    // naar een gearchiveerde groep, legacy-id of onbekende historische id mag
    // ongewijzigd blijven. Dat maakt een oude id NIET selecteerbaar voor een
    // nieuwe taak of voor een andere taak.
    $bestaandId = groepenKort($bestaand['commissie_id'] ?? '', 80);
    if ($bestaand !== null && $bestaandId !== '' && hash_equals($bestaandId, $gevraagdId)) {
        $context = taakCommissieContext($groepenDoc, $ledenData, $bestaandId);
        return ['geldig' => true, 'id' => $bestaandId, 'reden' => $context['soort']];
    }

    return ['geldig' => false, 'id' => '', 'reden' => 'Commissie is niet actief of bestaat niet in het groepenmodel.'];
}
