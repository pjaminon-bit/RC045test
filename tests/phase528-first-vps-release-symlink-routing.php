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
$parentBlock = "<Directory \"/srv/verenigingsplatform\">\n    Options +FollowSymLinks\n    AllowOverride None\n    Require all denied\n</Directory>";
$releaseBlock = "<Directory \"/srv/verenigingsplatform/current\">\n    Options None\n    AllowOverride None\n    Require all denied\n</Directory>";
$docrootBlock = "<Directory \"/srv/verenigingsplatform/current/public\">\n    Options -Indexes -ExecCGI -MultiViews +FollowSymLinks\n    AllowOverride FileInfo Indexes Options\n    Require all granted\n</Directory>";
$phpDenyBlock = "<FilesMatch \"(?i)\\.php$\">\n    <RequireAll>\n        Require all granted\n        Require not expr \"-f '%{REQUEST_FILENAME}'\"\n    </RequireAll>\n</FilesMatch>";
$frontControllerBlock = "<Files \"index.php\">\n    AuthMerging Off\n    Require all granted\n    SetHandler \"proxy:unix:/run/php/vst-test.sock|fcgi://vst-test/\"\n</Files>";

check528(str_contains($fragment, 'DocumentRoot "/srv/verenigingsplatform/current/public"'), 'DocumentRoot volgt de current-symlink uitsluitend naar de public-subdirectory');
check528(str_contains($fragment, $rootBlock), 'filesystemroot blijft fail-closed zonder globale symlinkvrijgave');
check528(str_contains($fragment, $parentBlock), 'release-parent staat alleen current-symlinktraversal toe en blijft inhoudelijk geweigerd');
check528(str_contains($fragment, $releaseBlock), 'volledige current/release-root blijft expliciet geweigerd');
check528(str_contains($fragment, $docrootBlock), 'alleen current/public wordt voor webverkeer vrijgegeven');

$rootPos = strpos($fragment, $rootBlock);
$parentPos = strpos($fragment, $parentBlock);
$releasePos = strpos($fragment, $releaseBlock);
$docrootPos = strpos($fragment, $docrootBlock);
check528(
    is_int($rootPos) && is_int($parentPos) && is_int($releasePos) && is_int($docrootPos)
        && $rootPos < $parentPos && $parentPos < $releasePos && $releasePos < $docrootPos,
    'Apache directorycontext wordt veilig opgebouwd: deny filesystem, traverse-only parent, deny release-root, grant public-root'
);
check528(
    substr_count($parentBlock, 'Require all denied') === 1
        && str_contains($parentBlock, 'Options +FollowSymLinks')
        && !str_contains($parentBlock, 'Require all granted'),
    'symlinktraversal verruimt geen lees- of serveerrechten op de release-parent'
);
check528(
    substr_count($releaseBlock, 'Require all denied') === 1
        && !str_contains($releaseBlock, 'Require all granted'),
    'release-root geeft geen lees- of serveerrechten buiten public/'
);
check528(
    str_contains($fragment, $phpDenyBlock)
        && str_contains($fragment, $frontControllerBlock),
    'fysieke PHP-bestanden worden case-insensitive geweigerd terwijl virtuele routes en exact index.php gecontroleerd mogen doorlopen'
);

if ($fout > 0) {
    fwrite(STDERR, "FASE 5.2.8 MISLUKT: {$fout} fout(en), {$ok} controles groen.\n");
    exit(1);
}

echo "FASE 5.2.8 OK: {$ok} controles groen.\n";
