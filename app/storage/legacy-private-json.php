<?php
// ============================================================
// Fail-closed reader voor standalone legacy PHP+JSON-opslag
// ============================================================
// Een ontbrekend runtimebestand is een geldige nieuwe/lege collectie.
// Zodra het pad wél bestaat, is een lees-, parse- of structuurfout een
// storagefout: callers mogen dan nooit met een stil leeg document doorgaan.
// ============================================================

function legacyPrivateJsonLees(string $pad, string $domein, array $vereisteArraySleutels = []): ?array
{
    $domein = preg_replace('/[^a-z0-9_.-]+/i', '_', trim($domein));
    if ($domein === '') $domein = 'onbekend';
    $bestand = basename($pad);

    $bestaat = file_exists($pad) || is_link($pad);
    if (!$bestaat) return null;

    if (!is_file($pad)) {
        error_log('[platform] standalone private JSON is geen regulier bestand: domein=' . $domein . ', bestand=' . $bestand);
        throw new RuntimeException('Standalone private opslag kon niet veilig worden gelezen.');
    }

    $ruw = @file_get_contents($pad);
    if ($ruw === false) {
        error_log('[platform] standalone private JSON onleesbaar: domein=' . $domein . ', bestand=' . $bestand);
        throw new RuntimeException('Standalone private opslag kon niet worden gelezen.');
    }

    // Legacy private databestanden hebben een PHP-voorloopregel. Accepteer
    // het bestaande formaat, maar niet het vroegere "geen accolade = leeg".
    $start = strpos($ruw, '{');
    if ($start === false) {
        error_log('[platform] standalone private JSON mist documentstart: domein=' . $domein . ', bestand=' . $bestand);
        throw new RuntimeException('Standalone private opslag bevat ongeldige data.');
    }

    try {
        $data = json_decode(substr($ruw, $start), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        error_log('[platform] standalone private JSON parsefout: domein=' . $domein . ', bestand=' . $bestand);
        throw new RuntimeException('Standalone private opslag bevat ongeldige data.', 0, $e);
    }

    if (!is_array($data)) {
        error_log('[platform] standalone private JSON heeft geen documentobject: domein=' . $domein . ', bestand=' . $bestand);
        throw new RuntimeException('Standalone private opslag bevat ongeldige data.');
    }

    foreach ($vereisteArraySleutels as $sleutel) {
        $sleutel = (string)$sleutel;
        if ($sleutel === '' || !array_key_exists($sleutel, $data) || !is_array($data[$sleutel])) {
            error_log('[platform] standalone private JSON structuurfout: domein=' . $domein . ', bestand=' . $bestand . ', veld=' . ($sleutel === '' ? 'onbekend' : $sleutel));
            throw new RuntimeException('Standalone private opslag bevat een ongeldige documentstructuur.');
        }
    }

    return $data;
}
