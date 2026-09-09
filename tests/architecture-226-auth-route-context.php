<?php
$root = dirname(__DIR__);
require_once $root . '/app/web/public-route-contract.php';

$checks = [];
$check = static function (bool $conditie, string $melding) use (&$checks): void {
    $checks[] = [$conditie, $melding];
};

$origineelServer = $_SERVER;
$origineelGet = $_GET;

$gevallen = [
    '/beheer/' => ['target' => 'beheer/index.php', 'script' => '/beheer/index.php'],
    '/leden/' => ['target' => 'leden/index.php', 'script' => '/leden/index.php'],
    '/beheer/rekentabel.html' => ['target' => 'beheer/rekentabel.php', 'script' => '/beheer/rekentabel.php'],
    '/index.html' => ['target' => 'index.php', 'script' => '/index.php'],
];

foreach ($gevallen as $requestUri => $verwacht) {
    $_SERVER = $origineelServer;
    $_GET = [];
    $_SERVER['REQUEST_URI'] = $requestUri;
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $root . '/public/index.php';

    $target = public226Dispatch();
    $verwachtTarget = realpath($root . '/' . $verwacht['target']);

    $check(
        is_string($target) && is_string($verwachtTarget) && $target === $verwachtTarget,
        "{$requestUri} dispatcht naar het verwachte fysieke PHP-target"
    );
    $check(
        ($_SERVER['SCRIPT_NAME'] ?? null) === $verwacht['script'],
        "{$requestUri} behoudt de legacy scriptidentiteit in SCRIPT_NAME"
    );
    $check(
        ($_SERVER['PHP_SELF'] ?? null) === $verwacht['script'],
        "{$requestUri} behoudt de legacy scriptidentiteit in PHP_SELF"
    );
    $check(
        ($_SERVER['SCRIPT_FILENAME'] ?? null) === $verwachtTarget,
        "{$requestUri} bindt SCRIPT_FILENAME aan hetzelfde gevalideerde target"
    );
    $check(
        ($_SERVER['REQUEST_URI'] ?? null) === $requestUri,
        "{$requestUri} laat de zichtbare REQUEST_URI ongemuteerd"
    );
}

// authHuidigePagina() gebruikt basename(SCRIPT_NAME) als same-route PRG-target.
// Voor directoryroutes moet dat daarom index.php zijn, niet de directorynaam.
foreach (['/beheer/' => 'beheer/index.php', '/leden/' => 'leden/index.php'] as $requestUri => $targetPad) {
    $_SERVER = $origineelServer;
    $_GET = [];
    $_SERVER['REQUEST_URI'] = $requestUri;
    public226Dispatch();
    $authRedirect = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $check($authRedirect === 'index.php', "{$requestUri} levert voor auth-PRG index.php op");

    $redirectUri = rtrim($requestUri, '/') . '/' . $authRedirect;
    $redirectRoute = public226Resolve($redirectUri, $root);
    $check(
        is_array($redirectRoute) && ($redirectRoute['type'] ?? '') === 'php' && ($redirectRoute['target'] ?? '') === $targetPad,
        "{$requestUri} auth-redirect blijft binnen dezelfde toegestane publieke route"
    );
}

$_SERVER = $origineelServer;
$_GET = $origineelGet;

$ok = 0;
$fout = 0;
foreach ($checks as [$conditie, $melding]) {
    if ($conditie) {
        $ok++;
        echo "OK: {$melding}\n";
    } else {
        $fout++;
        fwrite(STDERR, "FOUT: {$melding}\n");
    }
}

printf("Architecture #226 auth route context: %d OK, %d fout(en)\n", $ok, $fout);
exit($fout === 0 ? 0 : 1);
