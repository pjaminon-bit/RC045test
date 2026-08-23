<?php
// Publieke, read-only assetgateway. Externe tenants bewaren uploads buiten de
// documentroot; alleen expliciet toegestane namespaces/paden worden geserveerd.
require_once __DIR__ . '/app/content/public-asset-store.php';

function publicAssetHttpFout(int $status): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    echo $status === 405 ? 'Methode niet toegestaan.' : 'Bestand niet gevonden.';
    exit;
}

$methode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($methode !== 'GET' && $methode !== 'HEAD') {
    header('Allow: GET, HEAD');
    publicAssetHttpFout(405);
}

$scope = isset($_GET['scope']) && is_string($_GET['scope']) ? trim($_GET['scope']) : '';
$relatief = isset($_GET['path']) && is_string($_GET['path']) ? $_GET['path'] : '';
if (publicAssetDefinitie($scope) === null || publicAssetRelatiefPad($scope, $relatief) === null) {
    publicAssetHttpFout(404);
}

$pad = publicAssetVeiligLeesPad($scope, $relatief);
$mime = publicAssetMime($scope, $relatief);
if ($mime === null) publicAssetHttpFout(404);

// Alleen de standalone/DEV-installatie mag een ontbrekend sponsorlogo vervangen
// door de vaste templateplaceholder. Externe tenants vallen bewust niet terug
// op gedeelde assets: daar blijft een ontbrekende upload een fail-closed 404.
if ($pad === null && $scope === 'sponsors') {
    $placeholder = publicAssetStandaloneSponsorPlaceholder();
    if ($placeholder !== null) {
        $pad = $placeholder;
        $mime = 'image/svg+xml';
    }
}
if ($pad === null) publicAssetHttpFout(404);

$grootte = @filesize($pad);
$mtime = @filemtime($pad);
if ($grootte === false || $grootte < 0) publicAssetHttpFout(404);
$grootte = (int) $grootte;
$mtime = $mtime === false ? 0 : (int) $mtime;
$etag = '"' . dechex($mtime) . '-' . dechex($grootte) . '"';

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-site');
header('Cache-Control: public, max-age=3600, stale-while-revalidate=86400');
header('ETag: ' . $etag);
header('Accept-Ranges: bytes');
header('Content-Disposition: inline; filename="' . basename($pad) . '"');

$ifNoneMatch = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
if ($ifNoneMatch !== '' && hash_equals($etag, $ifNoneMatch)) {
    http_response_code(304);
    exit;
}

$start = 0;
$einde = max(0, $grootte - 1);
$status = 200;
$range = trim((string) ($_SERVER['HTTP_RANGE'] ?? ''));
if ($range !== '') {
    if ($grootte === 0 || preg_match('/^bytes=(\d*)-(\d*)$/D', $range, $m) !== 1 || ($m[1] === '' && $m[2] === '')) {
        header('Content-Range: bytes */' . $grootte);
        http_response_code(416);
        exit;
    }

    if ($m[1] === '') {
        $suffix = (int) $m[2];
        if ($suffix < 1) {
            header('Content-Range: bytes */' . $grootte);
            http_response_code(416);
            exit;
        }
        $start = max(0, $grootte - $suffix);
        $einde = $grootte - 1;
    } else {
        $start = (int) $m[1];
        $einde = $m[2] === '' ? ($grootte - 1) : (int) $m[2];
        if ($start >= $grootte || $einde < $start) {
            header('Content-Range: bytes */' . $grootte);
            http_response_code(416);
            exit;
        }
        $einde = min($einde, $grootte - 1);
    }
    $status = 206;
}

$lengte = $grootte === 0 ? 0 : ($einde - $start + 1);
if ($status === 206) {
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $einde . '/' . $grootte);
}
header('Content-Length: ' . $lengte);
if ($methode === 'HEAD' || $lengte === 0) exit;

$handle = @fopen($pad, 'rb');
if ($handle === false) publicAssetHttpFout(404);
if ($start > 0 && fseek($handle, $start) !== 0) {
    fclose($handle);
    publicAssetHttpFout(404);
}

$resterend = $lengte;
while ($resterend > 0 && !feof($handle)) {
    $blok = fread($handle, min(65536, $resterend));
    if ($blok === false || $blok === '') break;
    echo $blok;
    $resterend -= strlen($blok);
    if (connection_aborted()) break;
}
fclose($handle);
