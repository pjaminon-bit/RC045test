<?php
$root = dirname(__DIR__);
$local = $root . '/site-config.local.php';
$temp = sys_get_temp_dir() . '/rc045test-privacy149-json-' . bin2hex(random_bytes(6));
$privateRoot = $temp . '/private';

if (is_file($local)) {
    fwrite(STDERR, "FOUT: site-config.local.php bestaat al; privacy-erasetest weigert die te overschrijven.\n");
    exit(1);
}
if (!mkdir($privateRoot, 0700, true)) {
    fwrite(STDERR, "FOUT: tijdelijke private_root kon niet worden gemaakt.\n");
    exit(1);
}

$config = [
    'vereniging' => ['sleutel' => 'privacy149-json'],
    'opslag' => ['private_driver' => 'json', 'private_root' => $privateRoot],
];
file_put_contents($local, "<?php\nreturn " . var_export($config, true) . ";\n", LOCK_EX);

function p149JsonVerwijderBoom(string $pad): void
{
    if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach ((array)scandir($pad) as $item) {
        if ($item === '.' || $item === '..') continue;
        p149JsonVerwijderBoom($pad . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($pad);
}

try {
    require __DIR__ . '/support/privacy149-erase-scenario.php';
    p149RunJson();
    echo "security-privacy-erase-json: OK — 9 rollbackgrenzen + rollbackfalen + succesvolle echte erase\n";
} finally {
    @unlink($local);
    p149JsonVerwijderBoom($temp);
}
