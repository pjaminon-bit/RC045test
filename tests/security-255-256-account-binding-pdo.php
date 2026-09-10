<?php
$root = dirname(__DIR__);
$local = $root . '/site-config.local.php';
$db = sys_get_temp_dir() . '/rc045test-255-256-' . bin2hex(random_bytes(5)) . '.sqlite';
if (is_file($local)) { fwrite(STDERR, "FOUT: site-config.local.php bestaat al; test weigert die te overschrijven.\n"); exit(1); }
if (!extension_loaded('pdo_sqlite')) { fwrite(STDERR, "FOUT: pdo_sqlite ontbreekt.\n"); exit(1); }
$config = "<?php\nreturn " . var_export([
    'vereniging' => ['sleutel' => 'security-255-256-pdo'],
    'opslag' => ['private_driver' => 'pdo', 'pdo' => ['dsn' => 'sqlite:' . $db, 'user' => '', 'password' => '']],
], true) . ";\n";
file_put_contents($local, $config, LOCK_EX);

function s255p(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }

try {
    require_once $root . '/app/leden/service.php';
    s255p(privateStoreDriver() === 'pdo', 'PDO-profiel niet actief');

    $doc = ['volgnummer'=>4, 'leden'=>[
        ['id'=>'lid_fixed','user_id'=>'usr_oudevasteid','beheer_account'=>'hergebruik','bestuursfunctie'=>'bestuurslid','gearchiveerd_op'=>''],
        ['id'=>'lid_legacy','user_id'=>'','beheer_account'=>'legacy','bestuursfunctie'=>'','gearchiveerd_op'=>''],
        ['id'=>'lid_archive','user_id'=>'usr_archive0001','beheer_account'=>'archief','bestuursfunctie'=>'','gearchiveerd_op'=>'2026-01-01T00:00:00+01:00'],
        ['id'=>'lid_other','user_id'=>'usr_onverwant01','beheer_account'=>'ander','bestuursfunctie'=>'','gearchiveerd_op'=>''],
    ]];
    s255p(repoLedenSchrijf($doc, false), 'leden seed naar PDO mislukt');

    s255p(ledenServiceVindVoorAccount('usr_nieuweid22', 'hergebruik') === null, 'PDO username-reuse nam vaste koppeling over');
    $vast = ledenServiceVindVoorAccount('usr_oudevasteid', 'nieuwe-naam');
    s255p(is_array($vast) && ($vast['id']??'') === 'lid_fixed', 'PDO vaste user_id vond gekoppeld lid niet');
    $legacy = ledenServiceVindVoorAccount('usr_legacynew1', 'legacy');
    s255p(is_array($legacy) && ($legacy['id']??'') === 'lid_legacy', 'PDO geldige legacy-koppeling met lege user_id werkt niet');
    s255p(ledenServiceVindVoorAccount('usr_archive0001', 'archief') === null, 'PDO gearchiveerd lid werd als runtime-accountbinding gebruikt');

    $fixedBlok = ledenServiceAccountVerwijderBlokkades('usr_oudevasteid', 'hergebruik');
    s255p(count($fixedBlok) === 1 && ($fixedBlok[0]['lid_id']??'') === 'lid_fixed', 'PDO delete-guard vond vaste koppeling niet');
    $archiveBlok = ledenServiceAccountVerwijderBlokkades('usr_archive0001', 'archief');
    s255p(count($archiveBlok) === 1 && !empty($archiveBlok[0]['gearchiveerd']), 'PDO delete-guard negeerde gearchiveerde koppeling');
    s255p(ledenServiceAccountVerwijderBlokkades('usr_vrij000000', 'vrij') === [], 'PDO ongebonden account werd ten onrechte geblokkeerd');

    echo "security-255-256-account-binding-pdo: OK\n";
} finally {
    @unlink($local);
    @unlink($db);
}
