<?php
$root = dirname(__DIR__);
$local = $root . '/site-config.local.php';
$db = sys_get_temp_dir() . '/rc045test-153-' . bin2hex(random_bytes(5)) . '.sqlite';
if (is_file($local)) { fwrite(STDERR, "FOUT: site-config.local.php bestaat al; test weigert die te overschrijven.\n"); exit(1); }
if (!extension_loaded('pdo_sqlite')) { fwrite(STDERR, "FOUT: pdo_sqlite ontbreekt.\n"); exit(1); }
$config = "<?php\nreturn " . var_export([
    'vereniging' => ['sleutel' => 'security-153-pdo'],
    'opslag' => ['private_driver' => 'pdo', 'pdo' => ['dsn' => 'sqlite:' . $db, 'user' => '', 'password' => '']],
], true) . ";\n";
file_put_contents($local, $config, LOCK_EX);

try {
    require_once $root . '/app/storage/domein-repositories.php';

    $taak = [
        'id' => 'otaak_pdo', 'nummer' => 1, 'omschrijving' => 'Maandultimo',
        'frequentie' => 'maandelijks', 'zichtbaarheid' => 'leden', 'actief' => true,
        'geschiedenis' => [], 'laatst_uitgevoerd' => '', 'volgende_uitvoering' => '',
    ];
    $taak = otaakMarkeerUitgevoerd($taak, 'tester', '2024-01-31');
    $doc = ['volgnummer' => 1, 'taken' => [$taak]];
    if (!repoOperationeleTakenSchrijf($doc, false)) throw new RuntimeException('PDO operationele-takenwrite gaf false');

    $lees = repoOperationeleTakenLees();
    $opgeslagen = $lees['taken'][0] ?? null;
    if (!is_array($opgeslagen)) throw new RuntimeException('PDO roundtrip verloor operationele taak');
    if (($opgeslagen['kalender_anker_dag'] ?? null) !== 31) throw new RuntimeException('PDO roundtrip verloor kalenderanker');
    if (($opgeslagen['volgende_uitvoering'] ?? '') !== '2024-02-29') throw new RuntimeException('PDO roundtrip verloor kalenderplanning');

    $opgeslagen = otaakMarkeerUitgevoerd($opgeslagen, 'tester', '2024-02-29');
    $lees['taken'][0] = $opgeslagen;
    if (!repoOperationeleTakenSchrijf($lees, false)) throw new RuntimeException('Tweede PDO operationele-takenwrite gaf false');
    $tweede = repoOperationeleTakenLees()['taken'][0] ?? null;
    if (!is_array($tweede) || ($tweede['kalender_anker_dag'] ?? null) !== 31 || ($tweede['volgende_uitvoering'] ?? '') !== '2024-03-31') {
        throw new RuntimeException('PDO opeenvolgende roundtrip veroorzaakte kalenderdrift');
    }

    echo "OK: #153 PDO operationele taken bewaart kalenderanker en driftvrije planning\n";
} finally {
    @unlink($local);
    @unlink($db);
}
