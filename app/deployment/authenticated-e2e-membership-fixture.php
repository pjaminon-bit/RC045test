<?php
// Tijdelijke publieke lidmaatschapstypefixture voor de authenticated VPS-E2E.
//
// De testtenant kan bewust zonder lidmaatschapstypen bestaan. Een echte
// aanmelding moet dan terecht fail-closed. Voor de E2E van issue #159 voegen
// we daarom uitsluitend tijdens `e2e apply` één synthetisch actief type toe.
// `e2e cleanup` herstelt daarna byte-voor-byte de oorspronkelijke tenantinhoud
// (of verwijdert het bestand weer wanneer het oorspronkelijk niet bestond).

function e2e159MembershipFixtureId(): string
{
    return 'e2e-authenticated-test';
}

function e2e159MembershipFixturePaths(string $privateRoot): array
{
    $privateRoot = rtrim($privateRoot, DIRECTORY_SEPARATOR);
    if ($privateRoot === '' || $privateRoot[0] !== DIRECTORY_SEPARATOR || !is_dir($privateRoot) || is_link($privateRoot)) {
        throw new RuntimeException('E2E lidmaatschapstypefixture vereist een veilig absoluut private_root.');
    }
    return [
        'content_dir' => $privateRoot . '/public-content',
        'content' => $privateRoot . '/public-content/lidmaatschapstypen.json',
        'state_dir' => $privateRoot . '/e2e-state',
        'state' => $privateRoot . '/e2e-state/' . e2e510Marker() . '-lidmaatschapstypen.json',
    ];
}

function e2e159MembershipSafeDir(string $dir): void
{
    if (file_exists($dir)) {
        if (!is_dir($dir) || is_link($dir)) throw new RuntimeException('E2E lidmaatschapstypefixture weigert een onveilige map.');
    } else {
        if (!mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('E2E lidmaatschapstypefixture kon map niet aanmaken.');
    }
    @chmod($dir, 0750);
}

function e2e159MembershipReadRegular(string $path): ?string
{
    if (!file_exists($path)) return null;
    if (!is_file($path) || is_link($path) || !is_readable($path)) {
        throw new RuntimeException('E2E lidmaatschapstypefixture weigert een onveilig contentbestand.');
    }
    $raw = file_get_contents($path);
    if (!is_string($raw)) throw new RuntimeException('E2E lidmaatschapstypefixture kon content niet lezen.');
    return $raw;
}

function e2e159MembershipAtomicWrite(string $path, string $raw, int $mode = 0640): void
{
    $dir = dirname($path);
    e2e159MembershipSafeDir($dir);
    if (is_link($path)) throw new RuntimeException('E2E lidmaatschapstypefixture weigert een symlinkdoel.');
    $tmp = $dir . '/.' . basename($path) . '.e2e.' . bin2hex(random_bytes(6)) . '.tmp';
    if (file_put_contents($tmp, $raw, LOCK_EX) === false) throw new RuntimeException('E2E lidmaatschapstypefixture kon tijdelijk bestand niet schrijven.');
    @chmod($tmp, $mode);
    if (is_link($path) || !rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('E2E lidmaatschapstypefixture kon bestand niet atomisch plaatsen.');
    }
    @chmod($path, $mode);
}

function e2e159MembershipDocument(string $raw): array
{
    if (trim($raw) === '') return ['types' => []];
    $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new RuntimeException('Lidmaatschapstypen-content is geen geldig JSON-object.');
    if ($data !== [] && array_is_list($data)) throw new RuntimeException('Lidmaatschapstypen-content heeft een onverwachte JSON-lijstvorm.');
    if (isset($data['types']) && !is_array($data['types'])) throw new RuntimeException('Lidmaatschapstypen-content heeft geen geldige types-lijst.');
    $data['types'] = array_values((array)($data['types'] ?? []));
    return $data;
}

function e2e159MembershipContainsReserved(string $raw): bool
{
    try { $doc = e2e159MembershipDocument($raw); }
    catch (Throwable $e) { throw new RuntimeException('Lidmaatschapstypen-content kan niet veilig op E2E-collisie worden gecontroleerd.', 0, $e); }
    foreach ($doc['types'] as $type) {
        if (!is_array($type)) continue;
        if (hash_equals(e2e159MembershipFixtureId(), trim((string)($type['id'] ?? '')))) return true;
    }
    return false;
}

function e2e159MembershipFixtureApply(string $privateRoot, string $tenant): void
{
    $tenant = tenantRuntimeVeiligeSleutel($tenant);
    if ($tenant === '' || $tenant === 'default') throw new RuntimeException('Concrete tenant-key is verplicht voor de E2E lidmaatschapstypefixture.');
    $paths = e2e159MembershipFixturePaths($privateRoot);
    if (file_exists($paths['state'])) throw new RuntimeException('Er staat nog E2E lidmaatschapstype-herstelstate; cleanup moet eerst slagen.');

    $originalRaw = e2e159MembershipReadRegular($paths['content']);
    $bestond = $originalRaw !== null;
    $originalRaw = $originalRaw ?? '';
    $doc = e2e159MembershipDocument($originalRaw);
    foreach ($doc['types'] as $type) {
        if (is_array($type) && hash_equals(e2e159MembershipFixtureId(), trim((string)($type['id'] ?? '')))) {
            throw new RuntimeException('Gereserveerd E2E-lidmaatschapstype botst met bestaande tenantinhoud.');
        }
    }

    $doc['types'][] = [
        'id' => e2e159MembershipFixtureId(),
        'label' => ['nl' => 'E2E testlid', 'en' => 'E2E test member', 'de' => 'E2E Testmitglied'],
        'actief' => true,
        'leeftijd_min' => 18,
        'leeftijd_max' => null,
        'jaarbedrag' => 1.00,
        'inschrijfgeld' => 0.00,
        'pro_rata' => false,
        'e2e_fixture' => e2e510Marker(),
        'e2e_tenant' => $tenant,
    ];
    $fixtureRaw = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";

    $state = [
        'version' => 1,
        'fixture' => e2e510Marker(),
        'tenant' => $tenant,
        'content_path' => 'public-content/lidmaatschapstypen.json',
        'original_existed' => $bestond,
        'original_sha256' => $bestond ? hash('sha256', $originalRaw) : null,
        'original_raw_b64' => $bestond ? base64_encode($originalRaw) : null,
        'fixture_sha256' => hash('sha256', $fixtureRaw),
    ];
    $stateRaw = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";

    // Herstelstate wordt bewust vóór de mutatie duurzaam geplaatst. Een abrupt
    // afgebroken apply kan daardoor door de pre-cleanup van de volgende run
    // veilig worden teruggedraaid.
    e2e159MembershipAtomicWrite($paths['state'], $stateRaw);
    e2e159MembershipAtomicWrite($paths['content'], $fixtureRaw);
    $verify = e2e159MembershipReadRegular($paths['content']);
    if (!is_string($verify) || !hash_equals($state['fixture_sha256'], hash('sha256', $verify))) {
        throw new RuntimeException('E2E lidmaatschapstypefixture kon niet worden nageverifieerd.');
    }
}

function e2e159MembershipFixtureCleanup(string $privateRoot, string $tenant): void
{
    $tenant = tenantRuntimeVeiligeSleutel($tenant);
    if ($tenant === '' || $tenant === 'default') throw new RuntimeException('Concrete tenant-key is verplicht voor E2E lidmaatschapstype-cleanup.');
    $paths = e2e159MembershipFixturePaths($privateRoot);
    $stateRaw = e2e159MembershipReadRegular($paths['state']);

    if ($stateRaw === null) {
        $current = e2e159MembershipReadRegular($paths['content']);
        if (is_string($current) && e2e159MembershipContainsReserved($current)) {
            throw new RuntimeException('E2E-lidmaatschapstype bestaat zonder herstelstate; cleanup weigert gegevensverlies te riskeren.');
        }
        return;
    }

    $state = json_decode($stateRaw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($state)
        || (int)($state['version'] ?? 0) !== 1
        || !hash_equals(e2e510Marker(), (string)($state['fixture'] ?? ''))
        || !hash_equals($tenant, (string)($state['tenant'] ?? ''))
        || !hash_equals('public-content/lidmaatschapstypen.json', (string)($state['content_path'] ?? ''))
        || preg_match('/^[0-9a-f]{64}$/D', (string)($state['fixture_sha256'] ?? '')) !== 1) {
        throw new RuntimeException('E2E lidmaatschapstype-herstelstate is ongeldig.');
    }

    $bestond = !empty($state['original_existed']);
    $originalRaw = '';
    $originalSha = null;
    if ($bestond) {
        $encoded = $state['original_raw_b64'] ?? null;
        $originalSha = (string)($state['original_sha256'] ?? '');
        if (!is_string($encoded) || preg_match('/^[0-9a-f]{64}$/D', $originalSha) !== 1) throw new RuntimeException('Originele E2E-herstelstate ontbreekt.');
        $decoded = base64_decode($encoded, true);
        if (!is_string($decoded) || !hash_equals($originalSha, hash('sha256', $decoded))) throw new RuntimeException('Originele E2E-herstelstate faalt integriteitscontrole.');
        $originalRaw = $decoded;
    } elseif (($state['original_raw_b64'] ?? null) !== null || ($state['original_sha256'] ?? null) !== null) {
        throw new RuntimeException('E2E-herstelstate voor oorspronkelijk ontbrekend bestand is inconsistent.');
    }

    $current = e2e159MembershipReadRegular($paths['content']);
    $currentSha = is_string($current) ? hash('sha256', $current) : null;
    $fixtureSha = (string)$state['fixture_sha256'];

    // Snapshot kan al bestaan terwijl de apply vóór de contentwrite werd
    // afgebroken. Ook dat is veilig herkenbaar: huidige bytes zijn dan nog
    // exact origineel (of het bestand ontbreekt nog steeds).
    $alreadyOriginal = $bestond
        ? (is_string($currentSha) && is_string($originalSha) && hash_equals($originalSha, $currentSha))
        : $current === null;

    if (!$alreadyOriginal) {
        if (!is_string($currentSha) || !hash_equals($fixtureSha, $currentSha)) {
            throw new RuntimeException('Lidmaatschapstypen wijzigden onverwacht tijdens E2E; cleanup weigert die wijziging te overschrijven.');
        }
        if ($bestond) {
            e2e159MembershipAtomicWrite($paths['content'], $originalRaw);
            $verify = e2e159MembershipReadRegular($paths['content']);
            if (!is_string($verify) || !hash_equals((string)$originalSha, hash('sha256', $verify))) throw new RuntimeException('Herstel van oorspronkelijke lidmaatschapstypen faalde.');
        } else {
            if (is_link($paths['content']) || !unlink($paths['content'])) throw new RuntimeException('Tijdelijk E2E-lidmaatschapstypebestand kon niet worden verwijderd.');
            if (file_exists($paths['content'])) throw new RuntimeException('Tijdelijk E2E-lidmaatschapstypebestand bestaat nog na cleanup.');
        }
    }

    if (is_link($paths['state']) || !unlink($paths['state'])) throw new RuntimeException('E2E lidmaatschapstype-herstelstate kon niet worden verwijderd.');
}

function e2e159MembershipFixtureRegisterShutdown(): void
{
    static $registered = false;
    if ($registered || PHP_SAPI !== 'cli') return;
    $script = basename((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
    if ($script !== 'vps-authenticated-e2e-ephemeral.php') return;
    $registered = true;

    register_shutdown_function(static function (): void {
        global $apply, $cleanup, $privateRoot, $tenant, $authPad;
        if (empty($apply) && empty($cleanup)) return;
        try {
            if (!is_string($privateRoot ?? null) || !is_string($tenant ?? null)) {
                throw new RuntimeException('E2E-runtimecontext ontbreekt voor lidmaatschapstypefixture.');
            }
            if (!empty($cleanup)) {
                e2e159MembershipFixtureCleanup($privateRoot, $tenant);
                return;
            }

            // Alleen een volledig succesvol geschreven hoofdfixture mag een
            // publiek testtype activeren. Zo kan een eerder gefaalde apply niet
            // alsnog tijdens shutdown tenantcontent wijzigen.
            if (!is_string($authPad ?? null)
                || !function_exists('e2e511AuthLees')
                || !function_exists('e2e511CountAll')) {
                throw new RuntimeException('E2E-hoofdfixture kan niet worden nageverifieerd.');
            }
            $users = e2e511AuthLees($authPad);
            $leden = repoLedenLees();
            $contributies = contributiesLees();
            $groepen = groepenLeesDocument();
            $vergaderingen = repoVergaderingenLees();
            $taken = repoTakenLees();
            if (e2e511CountAll($users, $leden, $contributies, $groepen, $vergaderingen, $taken, $tenant) < 7) {
                throw new RuntimeException('E2E-hoofdfixture is niet volledig; publiek testtype wordt niet geplaatst.');
            }
            e2e159MembershipFixtureApply($privateRoot, $tenant);
        } catch (Throwable $e) {
            fwrite(STDERR, 'FOUT: E2E lidmaatschapstypefixture: ' . $e->getMessage() . "\n");
            // Een fixture- of herstelfout moet ook een reeds met exit(0)
            // beëindigde hoofdactie alsnog fail-closed maken.
            exit(91);
        }
    });
}
