<?php
$root = dirname(__DIR__);
require_once $root . '/app/deployment/webserver-contract.php';

$ok = 0;
$fout = 0;
function check528(bool $cond, string $label): void
{
    global $ok, $fout;
    if ($cond) {
        $ok++;
        echo "OK: {$label}\n";
        return;
    }
    $fout++;
    fwrite(STDERR, "FOUT: {$label}\n");
}

$plan = [
    'shared_code' => [
        'document_root' => '/srv/verenigingsplatform/current',
    ],
    'php_fpm' => [
        'socket' => '/run/php/vst-test.sock',
        'backend' => 'fcgi://vst-test/',
    ],
];

$fragment = web42HttpsRoutingFragment($plan);
$rootBlock = "<Directory \"/\">\n    Options None\n    AllowOverride None\n    Require all denied\n</Directory>";
$parentBlock = "<Directory \"/srv/verenigingsplatform\">\n    Options +FollowSymLinks\n    AllowOverride None\n    Require all denied\n</Directory>";
$docrootBlock = "<Directory \"/srv/verenigingsplatform/current\">\n    Options -Indexes -ExecCGI +FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>";

check528(str_contains($fragment, $rootBlock), 'filesystemroot blijft fail-closed zonder globale symlinkvrijgave');
check528(str_contains($fragment, $parentBlock), 'release-parent staat FollowSymLinks toe zodat current-symlink vóór DocumentRoot-resolutie kan worden gevolgd');
check528(str_contains($fragment, $docrootBlock), 'alleen de gedeelde DocumentRoot wordt voor webverkeer vrijgegeven');

$rootPos = strpos($fragment, $rootBlock);
$parentPos = strpos($fragment, $parentBlock);
$docrootPos = strpos($fragment, $docrootBlock);
check528(
    is_int($rootPos) && is_int($parentPos) && is_int($docrootPos) && $rootPos < $parentPos && $parentPos < $docrootPos,
    'Apache directorycontext wordt in veilige volgorde opgebouwd: deny root, symlink-traverse parent, grant DocumentRoot'
);
check528(substr_count($parentBlock, 'Require all denied') === 1 && !str_contains($parentBlock, 'Require all granted'), 'release-parent geeft geen lees- of serveerrechten buiten DocumentRoot');

if ($fout > 0) {
    fwrite(STDERR, "FASE 5.2.8 MISLUKT: {$fout} fout(en), {$ok} controles groen.\n");
    exit(1);
}

echo "FASE 5.2.8 OK: {$ok} controles groen.\n";
