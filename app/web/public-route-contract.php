<?php
// ============================================================
// #226 — positieve publieke routegrens voor de minimale public/ webroot
// ============================================================

function public226AppRoot(): string
{
    return dirname(__DIR__, 2);
}

function public226PadBinnen(string $pad, string $root): bool
{
    $pad = rtrim(str_replace('\\', '/', $pad), '/');
    $root = rtrim(str_replace('\\', '/', $root), '/');
    return $pad === $root || str_starts_with($pad, $root . '/');
}

function public226RequestPad(string $requestUri): ?string
{
    $raw = parse_url($requestUri, PHP_URL_PATH);
    if (!is_string($raw) || $raw === '' || $raw[0] !== '/' || str_contains($raw, "\0") || str_contains($raw, '\\')) return null;
    if (preg_match('/%(?![0-9A-Fa-f]{2})/', $raw) === 1) return null;
    $pad = rawurldecode($raw);
    if ($pad === '' || $pad[0] !== '/' || str_contains($pad, "\0") || str_contains($pad, '\\')) return null;
    if (preg_match('/[\x00-\x1f\x7f]/', $pad) === 1) return null;
    $delen = explode('/', $pad);
    foreach ($delen as $deel) if ($deel === '.' || $deel === '..') return null;
    $pad = preg_replace('~/+~', '/', $pad);
    return is_string($pad) ? $pad : null;
}

function public226RootPhpRoutes(): array
{
    return [
        'index.php', 'aanmelden.php', 'aanmelden-ontvangst.php', 'bedankt.php',
        'baanreglement.php', 'beheer.php', 'branding-asset.php', 'contact-ontvangst.php',
        'fotoboek.php', 'healthz.php', 'leden.php', 'media.php', 'ontstaan.php',
        'public-asset.php', 'public-content.php', 'vertaal.php',
    ];
}

function public226BeheerPhpRoutes(string $root): array
{
    $routes = ['index.php', 'groep-relaties.php', 'data-integriteit.php', 'rekentabel.php'];
    $platform = require $root . '/app/core/platform-definities.php';
    foreach ((array)($platform['beheer'] ?? []) as $def) {
        if (!is_array($def)) continue;
        $route = (string)($def['route'] ?? '');
        $pad = parse_url($route, PHP_URL_PATH);
        if (!is_string($pad) || preg_match('/^[a-z0-9][a-z0-9-]*\.php$/D', $pad) !== 1) continue;
        $routes[] = $pad;
    }
    $routes = array_values(array_unique($routes));
    sort($routes, SORT_STRING);
    return $routes;
}

function public226PhpRoute(string $target, array $get = []): array
{
    return ['type' => 'php', 'target' => $target, 'get' => $get];
}

function public226Resolve(string $requestUri, ?string $root = null): ?array
{
    $root ??= public226AppRoot();
    $pad = public226RequestPad($requestUri);
    if ($pad === null) return null;

    if (strcasecmp($pad, '/Over-ons') === 0 || strcasecmp($pad, '/Over-ons/') === 0) {
        return ['type' => 'redirect', 'location' => '/index.html#over-ons', 'status' => 301];
    }
    if (strcasecmp($pad, '/Lidmaatschap') === 0 || strcasecmp($pad, '/Lidmaatschap/') === 0) {
        return ['type' => 'redirect', 'location' => '/#lidmaatschap', 'status' => 301];
    }

    if ($pad === '/') return public226PhpRoute('index.php');

    if (preg_match('~^/data/(homepage|ontstaan|baanreglement|aanmelden|bedankt|actueel|agenda|faq|sponsors|contact|nieuws|media|media-pagina|fotoboek|fotoboek-pagina|rekentabel|lidmaatschapstypen|changelog)\.json$~D', $pad, $m) === 1) {
        return public226PhpRoute('public-content.php', ['key' => $m[1]]);
    }
    if (preg_match('~^/images/sponsors/([A-Za-z0-9][A-Za-z0-9._-]{0,180})$~D', $pad, $m) === 1) {
        return public226PhpRoute('public-asset.php', ['scope' => 'sponsors', 'path' => $m[1]]);
    }
    if (preg_match('~^/images/fotoboek/([a-z0-9][a-z0-9-]*)/(thumbs/)?([A-Za-z0-9][A-Za-z0-9._-]{0,180})$~D', $pad, $m) === 1) {
        return public226PhpRoute('public-asset.php', ['scope' => 'fotoboek', 'path' => $m[1] . '/' . ($m[2] ?? '') . $m[3]]);
    }
    if ($pad === '/fotoboek' || $pad === '/fotoboek/') return public226PhpRoute('fotoboek.php');
    if (preg_match('~^/fotoboek/([a-z0-9][a-z0-9-]*)/?$~D', $pad, $m) === 1) {
        return public226PhpRoute('fotoboek.php', ['album' => $m[1]]);
    }

    if ($pad === '/beheer/' || $pad === '/beheer') return public226PhpRoute('beheer/index.php');
    if (preg_match('~^/beheer/([a-z0-9][a-z0-9-]*)(?:\.php|\.html)$~D', $pad, $m) === 1) {
        $bestand = $m[1] . '.php';
        if (in_array($bestand, public226BeheerPhpRoutes($root), true)) return public226PhpRoute('beheer/' . $bestand);
        return null;
    }

    if ($pad === '/leden/' || $pad === '/leden') return public226PhpRoute('leden/index.php');
    if ($pad === '/leden/index.php' || $pad === '/leden/index.html') return public226PhpRoute('leden/index.php');

    if (preg_match('~^/([A-Za-z0-9][A-Za-z0-9-]*)(?:\.php|\.html)$~D', $pad, $m) === 1) {
        $bestand = $m[1] . '.php';
        if (in_array($bestand, public226RootPhpRoutes(), true)) return public226PhpRoute($bestand);
    }

    if (preg_match('~^/images/(?!sponsors/|fotoboek/)(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9][A-Za-z0-9._-]{0,180}\.(?:jpe?g|png|webp|gif|svg)$~iD', $pad) === 1) {
        return ['type' => 'placeholder'];
    }
    return null;
}

function public226CliServerStatic(string $publicRoot, string $requestUri): bool
{
    if (PHP_SAPI !== 'cli-server') return false;
    $pad = public226RequestPad($requestUri);
    if ($pad === null || $pad === '/' || str_ends_with(strtolower($pad), '.php')) return false;
    $candidate = realpath($publicRoot . $pad);
    $realRoot = realpath($publicRoot);
    return is_string($candidate) && is_string($realRoot) && public226PadBinnen($candidate, $realRoot) && is_file($candidate);
}

function public226Dispatch(?string $requestUri = null): void
{
    $root = public226AppRoot();
    $requestUri ??= (string)($_SERVER['REQUEST_URI'] ?? '/');
    $route = public226Resolve($requestUri, $root);
    if (!is_array($route)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Niet gevonden.\n";
        return;
    }
    if (($route['type'] ?? '') === 'redirect') {
        header('Location: ' . $route['location'], true, (int)$route['status']);
        return;
    }
    if (($route['type'] ?? '') === 'placeholder') {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if (($host === '127.0.0.1' || $host === 'localhost') && str_contains($requestUri, '_asset404=1')) {
            http_response_code(404);
            return;
        }
        header('Content-Type: image/svg+xml');
        readfile($root . '/public/images/template-placeholder.svg');
        return;
    }
    if (($route['type'] ?? '') !== 'php') {
        http_response_code(404);
        return;
    }

    $target = realpath($root . '/' . (string)$route['target']);
    $realRoot = realpath($root);
    $publicRoot = realpath($root . '/public');
    if (!is_string($target) || !is_file($target) || !is_string($realRoot) || !is_string($publicRoot)
        || !public226PadBinnen($target, $realRoot) || public226PadBinnen($target, $publicRoot)) {
        http_response_code(404);
        return;
    }
    foreach ((array)($route['get'] ?? []) as $key => $value) $_GET[(string)$key] = (string)$value;
    $pad = public226RequestPad($requestUri) ?? '/';
    $_SERVER['SCRIPT_NAME'] = $pad;
    $_SERVER['PHP_SELF'] = $pad;
    $_SERVER['SCRIPT_FILENAME'] = $target;
    require $target;
}
