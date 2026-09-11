<?php
// Read-only gateway voor logo/favicon die buiten de documentroot staan.
$config = require __DIR__ . '/site-config.php';
require_once __DIR__ . '/app/core/tenant-branding-assets.php';
require_once __DIR__ . '/app/core/http-file-validator.php';

function brandingAssetFout(int $status): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    echo 'Bestand niet gevonden.';
    exit;
}

$methode = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($methode, ['GET','HEAD'], true)) {
    header('Allow: GET, HEAD');
    brandingAssetFout(405);
}
$naam = isset($_GET['name']) && is_string($_GET['name']) ? $_GET['name'] : '';
if (!tenantBrandingAssetNaamGeldig($naam)) brandingAssetFout(404);
$pad = tenantBrandingAssetLeesPad($config, $naam);
if ($pad === null) brandingAssetFout(404);
$ext = strtolower((string)pathinfo($naam, PATHINFO_EXTENSION));
$mime = tenantBrandingAssetMimeVoorExt($ext);
if ($mime === null) brandingAssetFout(404);

// Gebruik dezelfde geopende inode voor validator en body. De validator leest
// geen assetbytes en atomische brandingvervanging krijgt altijd een nieuwe
// versie-identiteit, ook bij gelijke grootte en mtime.
$geopend = httpFileOpenMetValidator($pad);
if ($geopend === null) brandingAssetFout(404);
$handle = $geopend['handle'];
$grootte = (int)$geopend['size'];
$etag = (string)$geopend['etag'];

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-site');
header('Cache-Control: public, max-age=3600, stale-while-revalidate=86400');
header('ETag: ' . $etag);
header('Content-Length: ' . $grootte);
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    fclose($handle);
    http_response_code(304);
    exit;
}
if ($methode === 'HEAD') {
    fclose($handle);
    exit;
}
while (!feof($handle)) {
    $blok = fread($handle, 65536);
    if ($blok === false || $blok === '') break;
    echo $blok;
    if (connection_aborted()) break;
}
fclose($handle);
