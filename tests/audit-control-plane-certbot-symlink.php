<?php
$root = dirname(__DIR__);
require_once $root . '/app/deployment/control-plane-admin-executor.php';

$ok = 0;
$fout = 0;
function tlsSymlinkCheck(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) {
        $ok++;
        echo "OK: {$label}\n";
    } else {
        $fout++;
        fwrite(STDERR, "FOUT: {$label}\n");
    }
}
function tlsSymlinkRm(string $pad): void
{
    if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        tlsSymlinkRm($pad . '/' . $item);
    }
    @rmdir($pad);
}

$tmp = sys_get_temp_dir() . '/rc045-certbot-' . bin2hex(random_bytes(5));
try {
    mkdir($tmp . '/live/testcert', 0700, true);
    mkdir($tmp . '/archive/testcert', 0700, true);
    file_put_contents($tmp . '/archive/testcert/fullchain1.pem', "dummy\n");
    symlink('../../archive/testcert/fullchain1.pem', $tmp . '/live/testcert/fullchain.pem');

    $resolved = control58CertbotFullchainBestand('testcert', $tmp . '/live', $tmp . '/archive');
    tlsSymlinkCheck($resolved === realpath($tmp . '/archive/testcert/fullchain1.pem'), 'normale Certbot live-symlink wordt naar eigen archive-lineage opgelost');

    mkdir($tmp . '/live/badcert', 0700, true);
    mkdir($tmp . '/archive/badcert', 0700, true);
    file_put_contents($tmp . '/outside.pem', "dummy\n");
    symlink('../../outside.pem', $tmp . '/live/badcert/fullchain.pem');
    tlsSymlinkCheck(control58CertbotFullchainBestand('badcert', $tmp . '/live', $tmp . '/archive') === null, 'symlink buiten eigen Certbot archive-lineage wordt geweigerd');

    mkdir($tmp . '/live/plaincert', 0700, true);
    mkdir($tmp . '/archive/plaincert', 0700, true);
    file_put_contents($tmp . '/live/plaincert/fullchain.pem', "dummy\n");
    tlsSymlinkCheck(control58CertbotFullchainBestand('plaincert', $tmp . '/live', $tmp . '/archive') === null, 'los regulier bestand in live-map wordt niet als Certbot-lineage vertrouwd');
} finally {
    tlsSymlinkRm($tmp);
}

echo "Control-plane Certbot symlink regression: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
