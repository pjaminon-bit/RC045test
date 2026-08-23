<?php
// ============================================================
// Publieke aanmeld-rate-limit — fail-closed, tenant-private op VPS
// ============================================================

function registrationRateLimitSymlinkInPad(string $pad): ?string
{
    $cursor = rtrim($pad, '/\\');
    if ($cursor === '') return null;
    while (true) {
        if (is_link($cursor)) return $cursor;
        $parent = dirname($cursor);
        if ($parent === $cursor) break;
        $cursor = $parent;
    }
    return null;
}

function registrationRateLimitMap(string $statePad, string $lockPad): string
{
    $stateMap = dirname($statePad);
    $lockMap = dirname($lockPad);
    if ($stateMap !== $lockMap) {
        throw new RuntimeException('Aanmeld-rate-limit state en lock moeten in dezelfde map staan.');
    }
    if (registrationRateLimitSymlinkInPad($stateMap) !== null) {
        throw new RuntimeException('Aanmeld-rate-limit map bevat een symlink.');
    }
    if (!is_dir($stateMap) && !@mkdir($stateMap, 0750, true) && !is_dir($stateMap)) {
        throw new RuntimeException('Aanmeld-rate-limit map kon niet worden aangemaakt.');
    }
    if (is_link($stateMap) || !is_dir($stateMap)) {
        throw new RuntimeException('Aanmeld-rate-limit map is niet veilig.');
    }
    @chmod($stateMap, 0750);
    return $stateMap;
}

function registrationRateLimitLees(string $statePad): array
{
    if (!file_exists($statePad) && !is_link($statePad)) return [];
    if (is_link($statePad) || !is_file($statePad)) {
        throw new RuntimeException('Aanmeld-rate-limit state is niet veilig.');
    }
    $ruw = @file_get_contents($statePad);
    if (!is_string($ruw)) {
        throw new RuntimeException('Aanmeld-rate-limit state kon niet worden gelezen.');
    }
    // Standalone RC045 kan nog het historische PHP-guarded bestand hebben.
    $start = strpos($ruw, '{');
    if ($start === false) return [];
    $data = json_decode(substr($ruw, $start), true);
    if (!is_array($data)) {
        throw new RuntimeException('Aanmeld-rate-limit state bevat ongeldige JSON.');
    }
    return $data;
}

function registrationRateLimitSchrijf(string $statePad, array $data): void
{
    if (is_link($statePad)) {
        throw new RuntimeException('Aanmeld-rate-limit state mag geen symlink zijn.');
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        throw new RuntimeException('Aanmeld-rate-limit state kon niet worden opgebouwd.');
    }
    $inhoud = str_ends_with(strtolower($statePad), '.php') ? "<?php exit; ?>\n" . $json : $json . "\n";
    $tmp = dirname($statePad) . '/.' . basename($statePad) . '.tmp.' . bin2hex(random_bytes(5));
    if (registrationRateLimitSymlinkInPad($tmp) !== null || @file_put_contents($tmp, $inhoud, LOCK_EX) === false) {
        throw new RuntimeException('Aanmeld-rate-limit state kon niet tijdelijk worden geschreven.');
    }
    @chmod($tmp, 0640);
    if (is_link($statePad) || !@rename($tmp, $statePad)) {
        @unlink($tmp);
        throw new RuntimeException('Aanmeld-rate-limit state kon niet atomisch worden geplaatst.');
    }
    @chmod($statePad, 0640);
}

/**
 * Registreert één publieke aanmeldpoging. Retourneert false zodra de limiet
 * al bereikt was. Opslagproblemen gooien bewust een exception: abuse-
 * bescherming mag op productie nooit ongemerkt uitvallen.
 */
function registrationRateLimitToestaan(
    string $statePad,
    string $lockPad,
    string $bronIp,
    int $limiet = 5,
    int $venster = 3600,
    ?int $nu = null
): bool {
    if ($limiet < 1 || $venster < 1) throw new InvalidArgumentException('Ongeldige aanmeld-rate-limit configuratie.');
    registrationRateLimitMap($statePad, $lockPad);
    if (is_link($lockPad)) throw new RuntimeException('Aanmeld-rate-limit lock mag geen symlink zijn.');
    $lock = @fopen($lockPad, 'c+');
    if (!is_resource($lock)) throw new RuntimeException('Aanmeld-rate-limit lock kon niet worden geopend.');
    @chmod($lockPad, 0640);
    if (!flock($lock, LOCK_EX)) {
        fclose($lock);
        throw new RuntimeException('Aanmeld-rate-limit lock kon niet worden verkregen.');
    }

    try {
        $nu = $nu ?? time();
        $pogingen = registrationRateLimitLees($statePad);
        foreach ($pogingen as $sleutel => $tijden) {
            $recent = array_values(array_filter((array)$tijden, static fn($tijd) => is_numeric($tijd) && (int)$tijd > $nu - $venster));
            if ($recent === []) unset($pogingen[$sleutel]);
            else $pogingen[$sleutel] = $recent;
        }

        $ipSleutel = hash('sha256', $bronIp !== '' ? $bronIp : 'onbekend');
        $recent = array_values((array)($pogingen[$ipSleutel] ?? []));
        $toegestaan = count($recent) < $limiet;
        if ($toegestaan) {
            $recent[] = $nu;
            $pogingen[$ipSleutel] = $recent;
        }
        registrationRateLimitSchrijf($statePad, $pogingen);
        return $toegestaan;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
