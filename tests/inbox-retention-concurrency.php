<?php
$root = dirname(__DIR__);
$local = $root . '/site-config.local.php';

function r115Fail(string $message): void
{
    fwrite(STDERR, "FOUT: {$message}\n");
    exit(1);
}

function r115Rm(string $path): void
{
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    foreach ((array)scandir($path) as $item) {
        if ($item === '.' || $item === '..') continue;
        r115Rm($path . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($path);
}

function r115Ids(array $doc, string $key): array
{
    return array_values(array_map(static fn($row) => is_array($row) ? (string)($row['id'] ?? '') : '', (array)($doc[$key] ?? [])));
}

function r115Worker(string $kind, string $marker): void
{
    global $root;
    require_once $root . '/contactberichten-opslag.php';
    require_once $root . '/aanmeldingen-opslag.php';
    if (@file_put_contents($marker, "ready\n", LOCK_EX) === false) r115Fail('worker kon startmarker niet schrijven');
    $removed = $kind === 'contact'
        ? contactBerichtenOpschonenBewaartermijn()
        : aanmeldingenOpschonenBewaartermijn();
    echo $kind . ':' . $removed . "\n";
}

if (($argv[1] ?? '') === '--worker') {
    $kind = (string)($argv[2] ?? '');
    $marker = (string)($argv[3] ?? '');
    if (!in_array($kind, ['contact', 'aanmeldingen'], true) || $marker === '') r115Fail('ongeldige worker-aanroep');
    r115Worker($kind, $marker);
    exit(0);
}

if (is_file($local)) r115Fail('site-config.local.php bestaat al; test weigert die te overschrijven');
$tmp = sys_get_temp_dir() . '/rc045test-issue115-' . bin2hex(random_bytes(6));
if (!@mkdir($tmp, 0700, true)) r115Fail('tijdelijke private root kon niet worden aangemaakt');
$config = [
    'vereniging' => ['sleutel' => 'issue115'],
    'opslag' => ['private_driver' => 'json', 'private_root' => $tmp],
    'privacy' => ['contactberichten_bewaardagen' => 30, 'aanmeldingen_bewaardagen' => 30],
];
if (@file_put_contents($local, "<?php\nreturn " . var_export($config, true) . ";\n", LOCK_EX) === false) {
    r115Rm($tmp);
    r115Fail('tijdelijke testconfig kon niet worden geschreven');
}

try {
    require_once $root . '/contactberichten-opslag.php';
    require_once $root . '/aanmeldingen-opslag.php';

    // Source guard: iedere productie-writer van beide inboxcollecties is expliciet
    // geïnventariseerd. Een nieuw direct schrijfpad maakt deze test rood totdat het
    // onder hetzelfde data-slotcontract is gebracht.
    $writerFiles = ['contact' => [], 'aanmeldingen' => []];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $info) {
        if (!$info->isFile() || $info->isLink() || strtolower($info->getExtension()) !== 'php') continue;
        $rel = str_replace('\\', '/', substr($info->getPathname(), strlen($root) + 1));
        if (str_starts_with($rel, 'tests/') || str_starts_with($rel, 'vendor/')) continue;
        $raw = (string)file_get_contents($info->getPathname());
        if (str_contains($raw, 'contactBerichtenSchrijf(')) $writerFiles['contact'][] = $rel;
        if (str_contains($raw, 'aanmeldingenSchrijf(')) $writerFiles['aanmeldingen'][] = $rel;
    }
    sort($writerFiles['contact']);
    sort($writerFiles['aanmeldingen']);
    $expectedContact = ['beheer/contactberichten.php', 'contact-ontvangst.php', 'contactberichten-opslag.php'];
    $expectedAanmeldingen = ['aanmelden-ontvangst.php', 'aanmeldingen-opslag.php', 'app/leden/aanmeldingen-service.php', 'app/leden/service.php', 'beheer/aanmeldingen.php'];
    sort($expectedContact);
    sort($expectedAanmeldingen);
    if ($writerFiles['contact'] !== $expectedContact) r115Fail('onverwachte contactinbox-writer(s): ' . json_encode($writerFiles['contact']));
    if ($writerFiles['aanmeldingen'] !== $expectedAanmeldingen) r115Fail('onverwachte aanmeldingen-writer(s): ' . json_encode($writerFiles['aanmeldingen']));

    foreach (['contact-ontvangst.php', 'beheer/contactberichten.php', 'aanmelden-ontvangst.php', 'beheer/aanmeldingen.php', 'app/leden/aanmeldingen-service.php'] as $rel) {
        $raw = (string)file_get_contents($root . '/' . $rel);
        if (!str_contains($raw, 'dataSlotOpen()')) r115Fail("{$rel} mist tenant-data-slot rond inboxmutatie");
    }
    $ledenService = (string)file_get_contents($root . '/app/leden/service.php');
    $ledenController = (string)file_get_contents($root . '/beheer/leden.php');
    if (!str_contains($ledenService, 'aanmeldingenSchrijf($apps)') || !str_contains($ledenController, '$slot=dataSlotOpen()')) {
        r115Fail('gedelegeerde aanmeldingen-purge mist aantoonbare data-slotgrens');
    }

    $contactSource = (string)file_get_contents($root . '/contactberichten-opslag.php');
    $appsSource = (string)file_get_contents($root . '/aanmeldingen-opslag.php');
    foreach ([['contact', $contactSource, 'contactBerichtenOpschonenBewaartermijn', 'contactBerichtenLees'], ['aanmeldingen', $appsSource, 'aanmeldingenOpschonenBewaartermijn', 'aanmeldingenLees']] as [$label, $source, $function, $reader]) {
        $start = strpos($source, 'function ' . $function);
        $end = $start === false ? false : strpos($source, "\n}", $start);
        $body = ($start !== false && $end !== false) ? substr($source, $start, $end - $start + 2) : '';
        if ($body === '' || strpos($body, 'dataSlotOpen()') === false || strpos($body, $reader . '()') === false || strpos($body, 'dataSlotOpen()') > strpos($body, $reader . '()') || !str_contains($body, 'finally') || !str_contains($body, 'dataSlotDicht($slot)')) {
            r115Fail("{$label}-retentiecleanup bewaakt niet de volledige RMW onder data-slot");
        }
    }

    $now = time();
    $old = date('c', $now - 60 * 86400);
    $recent = date('c', $now - 86400);

    $contactSeed = ['berichten' => [
        ['id' => 'contact-old', 'status' => 'nieuw', 'aangemaakt' => $old],
        ['id' => 'contact-recent', 'status' => 'nieuw', 'aangemaakt' => $recent],
    ]];
    $appsSeed = ['aanmeldingen' => [
        ['id' => 'app-old', 'status' => 'nieuw', 'aangemaakt' => $old],
        ['id' => 'app-recent', 'status' => 'nieuw', 'aangemaakt' => $recent],
    ]];
    if (!contactBerichtenSchrijf($contactSeed) || !aanmeldingenSchrijf($appsSeed)) r115Fail('testseed kon niet worden opgeslagen');

    // Leg het oorspronkelijke lost-update-interleaving expliciet vast: een
    // buiten de lock gelezen cleanup-snapshot kent een later geldige write niet.
    $staleContact = contactBerichtenLees();
    contactBerichtenPasRetentieToe($staleContact, $now);
    $slot = dataSlotOpen();
    try {
        $fresh = contactBerichtenLees();
        $fresh['berichten'][] = ['id' => 'contact-hazard-write', 'status' => 'nieuw', 'aangemaakt' => $recent];
        if (!contactBerichtenSchrijf($fresh)) r115Fail('hazard-writer kon niet schrijven');
    } finally { dataSlotDicht($slot); }
    if (in_array('contact-hazard-write', r115Ids($staleContact, 'berichten'), true)) r115Fail('stale snapshotmodel is onverwacht niet stale');
    if (!in_array('contact-hazard-write', r115Ids(contactBerichtenLees(), 'berichten'), true)) r115Fail('geldige hazard-write ontbreekt');

    $runConcurrent = static function (string $kind) use ($root, $tmp, $recent): void {
        $marker = $tmp . '/worker-' . $kind . '.ready';
        $slot = dataSlotOpen();
        $proc = null;
        $pipes = [];
        try {
            $proc = proc_open([PHP_BINARY, __FILE__, '--worker', $kind, $marker], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
            if (!is_resource($proc)) r115Fail("{$kind}-cleanupworker kon niet starten");
            fclose($pipes[0]);
            $deadline = microtime(true) + 5.0;
            while (!is_file($marker) && microtime(true) < $deadline) usleep(10000);
            if (!is_file($marker)) r115Fail("{$kind}-cleanupworker bereikte startmarker niet");

            // Worker staat direct vóór de cleanupcall. Met de fix moet hij nu
            // blokkeren op hetzelfde slot dat deze geldige writer vasthoudt.
            usleep(250000);
            $status = proc_get_status($proc);
            if (empty($status['running'])) r115Fail("{$kind}-retentiecleanup negeert data-slot en kan stale schrijven");

            if ($kind === 'contact') {
                $doc = contactBerichtenLees();
                $doc['berichten'][] = ['id' => 'contact-concurrent-write', 'status' => 'nieuw', 'aangemaakt' => $recent];
                if (!contactBerichtenSchrijf($doc)) r115Fail('concurrent contactwrite faalde');
            } else {
                $doc = aanmeldingenLees();
                $doc['aanmeldingen'][] = ['id' => 'app-concurrent-write', 'status' => 'nieuw', 'aangemaakt' => $recent];
                if (!aanmeldingenSchrijf($doc)) r115Fail('concurrent aanmeldingenwrite faalde');
            }
        } finally {
            dataSlotDicht($slot);
        }

        $stdout = isset($pipes[1]) ? stream_get_contents($pipes[1]) : '';
        $stderr = isset($pipes[2]) ? stream_get_contents($pipes[2]) : '';
        if (isset($pipes[1]) && is_resource($pipes[1])) fclose($pipes[1]);
        if (isset($pipes[2]) && is_resource($pipes[2])) fclose($pipes[2]);
        $exit = is_resource($proc) ? proc_close($proc) : 1;
        if ($exit !== 0) r115Fail("{$kind}-cleanupworker faalde ({$exit}): {$stderr}");
        if (!str_contains((string)$stdout, $kind . ':1')) r115Fail("{$kind}-cleanup verwijderde niet exact het verlopen record: {$stdout}");
    };

    $runConcurrent('contact');
    $runConcurrent('aanmeldingen');

    $contactIds = r115Ids(contactBerichtenLees(), 'berichten');
    if (in_array('contact-old', $contactIds, true)) r115Fail('verlopen contactbericht bleef bestaan');
    foreach (['contact-recent', 'contact-hazard-write', 'contact-concurrent-write'] as $id) {
        if (!in_array($id, $contactIds, true)) r115Fail("geldige contactwrite verloren: {$id}");
    }
    $appIds = r115Ids(aanmeldingenLees(), 'aanmeldingen');
    if (in_array('app-old', $appIds, true)) r115Fail('verlopen aanmelding bleef bestaan');
    foreach (['app-recent', 'app-concurrent-write'] as $id) {
        if (!in_array($id, $appIds, true)) r115Fail("geldige aanmeldingenwrite verloren: {$id}");
    }

    // Corrupte tenant-private state blijft fail-closed: cleanup mag die niet als
    // lege geldige collectie materialiseren. (#145 blijft apart voor legacy readers.)
    $contactPath = $tmp . '/contactberichten.json';
    $before = is_file($contactPath) ? (string)file_get_contents($contactPath) : null;
    if ($before === null) r115Fail('contact private-storebestand ontbreekt voor corruptietest');
    if (@file_put_contents($contactPath, '{broken', LOCK_EX) === false) r115Fail('corruptietest kon bestand niet voorbereiden');
    $thrown = false;
    try { contactBerichtenOpschonenBewaartermijn(); } catch (Throwable $e) { $thrown = true; }
    if (!$thrown || (string)file_get_contents($contactPath) !== '{broken') r115Fail('contactcleanup schreef corrupte tenantstate stilzwijgend terug');
    @file_put_contents($contactPath, $before, LOCK_EX);

    echo "Issue #115 inbox retention concurrency: OK\n";
} finally {
    @unlink($local);
    r115Rm($tmp);
}
