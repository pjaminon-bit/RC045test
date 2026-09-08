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
        'app_root' => '/srv/verenigingsplatform/current',
        'document_root' => '/srv/verenigingsplatform/current/public',
    ],
    'php_fpm' => [
        'socket' => '/run/php/vst-test.sock',
        'backend' => 'fcgi://vst-test/',
    ],
];

$fragment = web42HttpsRoutingFragment($plan);
$rootBlock = "<Directory \"/\">\n    Options None\n    AllowOverride None\n    Require all denied\n</Directory>";
$releaseBlock = "<Directory \"/srv/verenigingsplatform/current\">\n    Options None\n    AllowOverride None\n    Require all denied\n</Directory>";
$docrootBlock = "<Directory \"/srv/verenigingsplatform/current/public\">\n    Options -Indexes -ExecCGI -MultiViews +FollowSymLinks\n    AllowOverride FileInfo Indexes Options\n    Require all granted\n</Directory>";

check528(str_contains($fragment, 'DocumentRoot "/srv/verenigingsplatform/current/public"'), 'DocumentRoot volgt de current-symlink uitsluitend naar de public-subdirectory');
check528(str_contains($fragment, $rootBlock), 'filesystemroot blijft fail-closed zonder globale symlinkvrijgave');
check528(str_contains($fragment, $releaseBlock), 'volledige current/release-root blijft expliciet geweigerd');
check528(str_contains($fragment, $docrootBlock), 'alleen current/public wordt voor webverkeer vrijgegeven');

$rootPos = strpos($fragment, $rootBlock);
$releasePos = strpos($fragment, $releaseBlock);
$docrootPos = strpos($fragment, $docrootBlock);
check528(
    is_int($rootPos) && is_int($releasePos) && is_int($docrootPos) && $rootPos < $releasePos && $releasePos < $docrootPos,
    'Apache directorycontext wordt veilig opgebouwd: deny filesystem, deny release-root, grant public-root'
);
check528(substr_count($releaseBlock, 'Require all denied') === 1 && !str_contains($releaseBlock, 'Require all granted'), 'release-root geeft geen lees- of serveerrechten buiten public/');
check528(str_contains($fragment, '<Files "index.php">') && str_contains($fragment, '<FilesMatch "\\.php$">'), 'alleen public frontcontroller kan via FPM worden uitgevoerd');

if ($fout > 0) {
    fwrite(STDERR, "FASE 5.2.8 MISLUKT: {$fout} fout(en), {$ok} controles groen.\n");
    exit(1);
}

echo "FASE 5.2.8 OK: {$ok} controles groen.\n";
