<?php
$root = dirname(__DIR__);
$local = $root . '/site-config.local.php';
$db = sys_get_temp_dir() . '/rc045test-privacy149-pdo-' . bin2hex(random_bytes(6)) . '.sqlite';

if (is_file($local)) {
    fwrite(STDERR, "FOUT: site-config.local.php bestaat al; privacy-erasetest weigert die te overschrijven.\n");
    exit(1);
}
if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "FOUT: pdo_sqlite ontbreekt.\n");
    exit(1);
}

$config = [
    'vereniging' => ['sleutel' => 'privacy149-pdo'],
    'opslag' => [
        'private_driver' => 'pdo',
        'private_root' => '',
        'pdo' => ['dsn' => 'sqlite:' . $db, 'user' => '', 'password' => ''],
    ],
];
file_put_contents($local, "<?php\nreturn " . var_export($config, true) . ";\n", LOCK_EX);

try {
    require __DIR__ . '/support/privacy149-erase-scenario.php';
    p149RunPdo();
    echo "security-privacy-erase-pdo: OK — 9 geforceerde echte writerfouten + rollback + succesvolle echte erase\n";
} finally {
    @unlink($local);
    @unlink($db);
}
