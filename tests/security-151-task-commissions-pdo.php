<?php
$root = dirname(__DIR__);
$local = $root . '/site-config.local.php';
$db = sys_get_temp_dir() . '/rc045test-151-' . bin2hex(random_bytes(5)) . '.sqlite';
if (is_file($local)) { fwrite(STDERR, "FOUT: site-config.local.php bestaat al; test weigert die te overschrijven.\n"); exit(1); }
if (!extension_loaded('pdo_sqlite')) { fwrite(STDERR, "FOUT: pdo_sqlite ontbreekt.\n"); exit(1); }
$config = "<?php\nreturn " . var_export([
    'vereniging' => ['sleutel' => 'security-151-pdo'],
    'opslag' => ['private_driver' => 'pdo', 'pdo' => ['dsn' => 'sqlite:' . $db, 'user' => '', 'password' => '']],
], true) . ";\n";
file_put_contents($local, $config, LOCK_EX);

try {
    require_once $root . '/app/taken-commissies.php';

    $groepId = 'commissie_stabiel_pdo';
    $groepen = [
        'groepen' => [[
            'id' => $groepId,
            'type' => 'commissie',
            'naam' => 'PDO commissie',
            'status' => 'actief',
        ]],
    ];
    if (!repoGroepenSchrijf($groepen, false)) throw new RuntimeException('PDO groepenwrite gaf false');
    $taken = ['volgnummer' => 1, 'taken' => [[
        'id' => 'taak_pdo',
        'nummer' => 1,
        'omschrijving' => 'PDO relatie',
        'commissie_id' => $groepId,
    ]]];
    if (!repoTakenSchrijf($taken, false)) throw new RuntimeException('PDO takenwrite gaf false');

    $groepenLees = taakCommissieDocument(repoGroepenLees());
    $takenLees = repoTakenLees();
    if (!isset(taakCommissieActieveKeuzes($groepenLees)[$groepId])) throw new RuntimeException('persistente PDO commissie niet selecteerbaar');
    if (($takenLees['taken'][0]['commissie_id'] ?? '') !== $groepId) throw new RuntimeException('PDO roundtrip verloor commissie groep-id');

    // Rename raakt uitsluitend de naam; de taak blijft aan dezelfde stabiele
    // group-id hangen.
    $groepen['groepen'][0]['naam'] = 'PDO commissie hernoemd';
    if (!repoGroepenSchrijf($groepen, false)) throw new RuntimeException('PDO rename write gaf false');
    $groepenLees = taakCommissieDocument(repoGroepenLees());
    $takenLees = repoTakenLees();
    $ctx = taakCommissieContext($groepenLees, ['commissies' => []], (string) $takenLees['taken'][0]['commissie_id']);
    if (($ctx['label'] ?? '') !== 'PDO commissie hernoemd') throw new RuntimeException('rename werd niet via stabiele id gevolgd');

    // Archiveren blokkeert een nieuwe relatie maar laat de bestaande relatie
    // intact en begrijpelijk leesbaar.
    $groepen['groepen'][0]['naam'] = 'PDO commissie hernoemd';
    $groepen['groepen'][0]['status'] = 'gearchiveerd';
    if (!repoGroepenSchrijf($groepen, false)) throw new RuntimeException('PDO archive write gaf false');
    $groepenLees = taakCommissieDocument(repoGroepenLees());
    $takenLees = repoTakenLees();
    if (isset(taakCommissieActieveKeuzes($groepenLees)[$groepId])) throw new RuntimeException('gearchiveerde PDO commissie bleef nieuw selecteerbaar');
    $bestaand = taakCommissieValideerVoorOpslag($groepId, $takenLees['taken'][0], $groepenLees, ['commissies' => []]);
    if (!$bestaand['geldig'] || $bestaand['id'] !== $groepId) throw new RuntimeException('bestaande PDO archiefrelatie werd niet behouden');

    echo "OK: #151 PDO groepen/taken stable-id + rename + archive contract\n";
} finally {
    @unlink($local);
    @unlink($db);
}
