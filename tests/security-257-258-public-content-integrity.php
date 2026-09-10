<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function check257258(bool $cond, string $label): void
{
    global $ok, $fout;
    if ($cond) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

function rr257258(string $pad): void
{
    if (is_link($pad) || is_file($pad)) { @chmod($pad, 0640); @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        rr257258($pad . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($pad);
}

function config257258(string $pad, string $privateRoot): void
{
    $config = [
        'vereniging' => [
            'sleutel' => 'integrity-test',
            'naam' => 'Integrity Test',
            'volledige_naam' => 'Integrity Test',
            'site_url' => 'https://integrity.example',
            'timezone' => 'Europe/Amsterdam',
            'standaard_taal' => 'nl',
        ],
        'opslag' => [
            'private_driver' => 'json',
            'private_root' => $privateRoot,
            'pdo' => ['dsn' => '', 'user' => '', 'password' => ''],
            'backups' => [
                'bewaardagen' => 30,
                'max_per_item' => 5,
                'max_asset_snapshots' => 2,
                'max_asset_mb' => 20,
            ],
        ],
    ];
    file_put_contents($pad, "<?php\nreturn " . var_export($config, true) . ";\n");
}

function run257258(string $worker, string $config, array $args): array
{
    $delen = [escapeshellcmd(PHP_BINARY), escapeshellarg($worker)];
    foreach ($args as $arg) $delen[] = escapeshellarg((string) $arg);
    $cmd = 'VERENIGING_REQUIRE_TENANT_CONFIG=1 VERENIGING_CONFIG_FILE=' . escapeshellarg($config) . ' ' . implode(' ', $delen);
    $out = [];
    exec($cmd . ' 2>/dev/null', $out, $code);
    return [$code, implode("\n", $out)];
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rc045-257-258-' . bin2hex(random_bytes(4));
$private = $tmp . '/private';
$content = $private . '/public-content';
@mkdir($content, 0750, true);
$config = $tmp . '/config.php';
config257258($config, $private);
$worker = $tmp . '/worker.php';

$workerCode = <<<'PHP'
<?php
$root = $argv[1];
$actie = $argv[2] ?? '';
$sleutel = $argv[3] ?? 'agenda';
require_once $root . '/app/content/public-content-store.php';

function geldig257258(string $sleutel, string $marker): array
{
    return match ($sleutel) {
        'agenda' => [['date'=>'2026-09-10','title'=>['nl'=>$marker]]],
        'media' => [['date'=>'2026-09-10','title'=>['nl'=>$marker]]],
        'media-pagina', 'fotoboek-pagina' => ['hero_sub'=>['nl'=>$marker,'en'=>'','de'=>'']],
        'sponsors' => ['updated'=>date('c'),'items'=>[],'cta'=>['nl'=>$marker,'en'=>'','de'=>'']],
        'fotoboek' => ['albums'=>[],'marker'=>$marker],
        'lidmaatschapstypen' => ['types'=>[['id'=>'test','label'=>['nl'=>$marker],'actief'=>true,'jaarbedrag'=>1,'inschrijfgeld'=>0,'pro_rata'=>true]],'updated'=>date('c')],
        default => ['marker'=>$marker],
    };
}

if ($actie === 'result') {
    echo json_encode(publicContentLeesResult($sleutel), JSON_UNESCAPED_SLASHES);
    exit;
}
if ($actie === 'write') {
    $ok = publicContentSchrijfTenant($sleutel, geldig257258($sleutel, $argv[4] ?? 'NEW'), true);
    echo $ok ? 'OK' : 'FAIL';
    exit;
}
if ($actie === 'read') {
    try {
        $data = publicContentLees($sleutel);
        echo json_encode(['ok'=>true,'data'=>$data], JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'class'=>get_class($e)], JSON_UNESCAPED_SLASHES);
    }
    exit;
}
if ($actie === 'endpoint') {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['key'] = $sleutel;
    http_response_code(200);
    register_shutdown_function(static function (): void { echo 'STATUS=' . http_response_code(); });
    include $root . '/public-content.php';
    exit;
}
exit(2);
PHP;
file_put_contents($worker, $workerCode);

$bestanden = [
    'agenda' => 'agenda.json',
    'media' => 'media.json',
    'media-pagina' => 'media-pagina.json',
    'sponsors' => 'sponsors.json',
    'fotoboek' => 'fotoboek.json',
    'fotoboek-pagina' => 'fotoboek-pagina.json',
    'lidmaatschapstypen' => 'lidmaatschapstypen.json',
];

$geldigeStructuren = [
    'agenda' => [['date'=>'2026-01-01']],
    'media' => [['date'=>'2026-01-01']],
    'media-pagina' => ['hero_sub'=>['nl'=>'oud']],
    'sponsors' => ['items'=>[],'cta'=>['nl'=>'oud']],
    'fotoboek' => ['albums'=>[]],
    'fotoboek-pagina' => ['hero_sub'=>['nl'=>'oud']],
    'lidmaatschapstypen' => ['types'=>[['id'=>'oud']]],
];

$ongeldigeStructuren = [
    'agenda' => ['geen'=>'lijst'],
    'media' => ['geen'=>'lijst'],
    'media-pagina' => ['geen_hero_sub'=>[]],
    'sponsors' => ['cta'=>[]],
    'fotoboek' => ['geen_albums'=>[]],
    'fotoboek-pagina' => ['geen_hero_sub'=>[]],
    'lidmaatschapstypen' => ['types'=>'geen-lijst'],
];

try {
    // Source-contract: iedere gespecialiseerde editor moet de centrale writer
    // daadwerkelijk gebruiken; de runtimechecks hieronder bewijzen die grens.
    $sourceContracts = [
        'beheer/agenda.php' => 'function agendaSchrijf',
        'beheer/media.php' => 'function mediaSchrijf',
        'beheer/sponsors.php' => 'function sponsorsSchrijf',
        'beheer/fotoboek-lib.php' => 'function fbSchrijf',
        'app/leden/lidmaatschap.php' => 'function lidmaatschapSchrijf',
    ];
    foreach ($sourceContracts as $bestand => $functie) {
        $bron = (string) file_get_contents($root . '/' . $bestand);
        check257258(str_contains($bron, $functie) && str_contains($bron, 'publicContentSchrijfTenant'), $bestand . ' routeert tenantwrites via centrale public-contentwriter');
    }

    [$cm, $om] = run257258($worker, $config, [$root, 'result', 'agenda']);
    $missing = json_decode($om, true);
    check257258($cm === 0 && ($missing['status'] ?? '') === 'missing' && ($missing['data'] ?? 'x') === null, 'getypeerde reader onderscheidt werkelijk ontbrekende tenantdataset');

    [$ceMissing, $oeMissing] = run257258($worker, $config, [$root, 'endpoint', 'agenda']);
    check257258($ceMissing === 0 && trim($oeMissing) === '[]STATUS=200', 'extern publiek endpoint behoudt HTTP 200 empty-semantiek voor missing dataset');

    foreach ($bestanden as $sleutel => $naam) {
        $pad = $content . '/' . $naam;

        // Nieuwe/missing dataset mag expliciet worden geïnitialiseerd.
        @unlink($pad);
        [$cwNieuw, $owNieuw] = run257258($worker, $config, [$root, 'write', $sleutel, 'INIT']);
        check257258($cwNieuw === 0 && trim($owNieuw) === 'OK' && is_file($pad), $sleutel . ': missing dataset kan veilig worden aangemaakt');

        // Syntactische corruptie is bestaand materiaal en mag nooit worden
        // vervangen door de geldige beheerwrite die volgt.
        $corrupt = "{\"broken\":";
        file_put_contents($pad, $corrupt);
        $hashVoor = hash_file('sha256', $pad);
        [$cr, $or] = run257258($worker, $config, [$root, 'result', $sleutel]);
        $result = json_decode($or, true);
        [$cw, $ow] = run257258($worker, $config, [$root, 'write', $sleutel, 'NEW']);
        $hashNa = hash_file('sha256', $pad);
        check257258($cr === 0 && ($result['status'] ?? '') === 'invalid' && ($result['code'] ?? '') === 'ongeldige_json', $sleutel . ': corrupte bestaande JSON wordt als invalid gedetecteerd');
        check257258($cw === 0 && trim($ow) === 'FAIL' && $hashVoor === $hashNa && file_get_contents($pad) === $corrupt, $sleutel . ': corrupte bestaande bytes blijven byte-identiek na writepoging');

        // Geldige JSON met de verkeerde rootstructuur is eveneens corruptie,
        // niet een lege/default dataset.
        $schemaRaw = json_encode($ongeldigeStructuren[$sleutel], JSON_UNESCAPED_SLASHES);
        file_put_contents($pad, $schemaRaw);
        $schemaVoor = hash_file('sha256', $pad);
        [$cs, $os] = run257258($worker, $config, [$root, 'result', $sleutel]);
        $schemaResult = json_decode($os, true);
        [$csw, $osw] = run257258($worker, $config, [$root, 'write', $sleutel, 'NEW']);
        check257258($cs === 0 && ($schemaResult['status'] ?? '') === 'invalid' && ($schemaResult['code'] ?? '') === 'ongeldig_schema', $sleutel . ': structureel ongeldige bestaande dataset wordt fail-closed herkend');
        check257258($csw === 0 && trim($osw) === 'FAIL' && hash_file('sha256', $pad) === $schemaVoor, $sleutel . ': structureel ongeldige bytes worden niet overschreven');

        // Normale bestaande geldige datasets moeten juist wel kunnen muteren.
        file_put_contents($pad, json_encode($geldigeStructuren[$sleutel], JSON_UNESCAPED_SLASHES));
        $oudeHash = hash_file('sha256', $pad);
        [$cv, $ov] = run257258($worker, $config, [$root, 'write', $sleutel, 'VALID-NEW']);
        $nieuweHash = hash_file('sha256', $pad);
        check257258($cv === 0 && trim($ov) === 'OK' && $nieuweHash !== $oudeHash, $sleutel . ': normale geldige tenantedit blijft werken');
    }

    // Endpointbewijs voor #258: dezelfde corrupte toestand die een writer
    // blokkeert mag publiek niet als een normale lege dataset worden gemaskeerd.
    $agendaPad = $content . '/agenda.json';
    file_put_contents($agendaPad, '{broken');
    [$ceCorrupt, $oeCorrupt] = run257258($worker, $config, [$root, 'endpoint', 'agenda']);
    check257258($ceCorrupt === 0 && str_contains($oeCorrupt, 'STATUS=500') && !str_contains($oeCorrupt, '[]STATUS=200'), 'corrupte externe tenantdataset levert detecteerbare HTTP 500 in plaats van 200-empty');

    // Onleesbaar bestaand bestand mag evenmin als missing worden gerapporteerd.
    file_put_contents($agendaPad, json_encode([['date'=>'2026-01-01']]));
    @chmod($agendaPad, 0000);
    [$cu, $ou] = run257258($worker, $config, [$root, 'result', 'agenda']);
    @chmod($agendaPad, 0640);
    $unreadable = json_decode($ou, true);
    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        check257258(true, 'onleesbaar-bestandtest overgeslagen onder root');
    } else {
        check257258($cu === 0 && ($unreadable['status'] ?? '') === 'invalid' && ($unreadable['code'] ?? '') === 'onleesbaar', 'bestaand onleesbaar bestand is invalid en niet missing');
    }

    // Het centrale contract moet ook nieuw structureel ongeldige output weigeren.
    file_put_contents($agendaPad, json_encode([['date'=>'2026-01-01']]));
    $voor = hash_file('sha256', $agendaPad);
    $invalidWorker = $tmp . '/invalid-write.php';
    file_put_contents($invalidWorker, "<?php\nputenv('VERENIGING_REQUIRE_TENANT_CONFIG=1');\nputenv('VERENIGING_CONFIG_FILE=" . addslashes($config) . "');\nrequire " . var_export($root . '/app/content/public-content-store.php', true) . ";\necho publicContentSchrijfTenant('agenda',['object'=>'geen lijst'],true)?'BAD':'OK';\n");
    $outInvalid=[];$codeInvalid=0;exec(escapeshellcmd(PHP_BINARY).' '.escapeshellarg($invalidWorker).' 2>/dev/null',$outInvalid,$codeInvalid);
    check257258($codeInvalid===0 && implode('', $outInvalid)==='OK' && hash_file('sha256',$agendaPad)===$voor, 'centrale writer weigert ook nieuw ongeldig documentschema vóór mutatie');
} finally {
    rr257258($tmp);
}

echo "Security #257/#258 public content integrity: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
