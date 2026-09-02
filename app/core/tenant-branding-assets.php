<?php
// ============================================================
// Veilige tenantbranding- en website-assets
// ============================================================
require_once __DIR__ . '/tenant-runtime.php';
require_once __DIR__ . '/tenant-settings.php';
require_once __DIR__ . '/atomic-file-transaction.php';

function tenantBrandingAssetTypes(): array
{
    return ['logo','favicon','hero','about','activity','gallery'];
}

function tenantBrandingAssetRoot(array $config): ?string
{
    $privateRoot = tenantRuntimePrivateRoot($config);
    if ($privateRoot === null) return null;
    return rtrim($privateRoot, '/\\') . DIRECTORY_SEPARATOR . 'public-assets' . DIRECTORY_SEPARATOR . 'branding';
}

/**
 * Brandingbestanden en site.json vormen één logische wijziging. De eerste
 * upload in een request snapshot de brandingmap; tenantSettingsSchrijf()
 * commit of rollbackt deze transactie. Een onverwacht einde van het request
 * rolt standaard terug, zodat een half afgeronde upload nooit live blijft.
 */
function tenantBrandingAssetTransactieBegin(array $config): void
{
    // Branding en site.json vormen één beheerstate. Wanneer een bestaand
    // settingsbestand corrupt/onleesbaar is mag er vóór recovery ook geen
    // brandingmutatie beginnen; anders zou beheer al bytes wijzigen vóór de
    // uiteindelijke settingswrite hard faalt.
    tenantSettingsLees($config);

    $root = tenantBrandingAssetRoot($config);
    if ($root === null) throw new RuntimeException('Brandingtransactie vereist private tenantopslag.');
    $actief = $GLOBALS['tenantBrandingAssetTx'] ?? null;
    if (is_array($actief) && empty($actief['closed'])) {
        if (!hash_equals((string)($actief['branding_root'] ?? ''), $root)) {
            throw new RuntimeException('Meerdere brandingroots in één request zijn niet toegestaan.');
        }
        return;
    }
    $tx = atomicFileTxBegin([$root]);
    $tx['branding_root'] = $root;
    $GLOBALS['tenantBrandingAssetTx'] = $tx;
    if (empty($GLOBALS['tenantBrandingAssetTxShutdown'])) {
        $GLOBALS['tenantBrandingAssetTxShutdown'] = true;
        register_shutdown_function(static function (): void {
            if (!isset($GLOBALS['tenantBrandingAssetTx']) || !is_array($GLOBALS['tenantBrandingAssetTx'])) return;
            $tx =& $GLOBALS['tenantBrandingAssetTx'];
            if (empty($tx['closed']) && !atomicFileTxRollback($tx)) {
                error_log('[platform] brandingtransactie kon bij shutdown niet volledig worden teruggedraaid');
            }
        });
    }
}

function tenantBrandingAssetTransactieAfronden(bool $succes): bool
{
    if (!isset($GLOBALS['tenantBrandingAssetTx']) || !is_array($GLOBALS['tenantBrandingAssetTx'])) return true;
    $tx =& $GLOBALS['tenantBrandingAssetTx'];
    if (!empty($tx['closed'])) return true;
    return $succes ? atomicFileTxCommit($tx) : atomicFileTxRollback($tx);
}

function tenantBrandingAssetNaamGeldig(string $naam): bool
{
    return preg_match('/^(logo|favicon|hero|about|activity|gallery)\.(png|jpe?g|webp)$/D', $naam) === 1;
}

function tenantBrandingAssetMimeVoorExt(string $ext): ?string
{
    return match (strtolower($ext)) {
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        default => null,
    };
}

function tenantBrandingAssetPad(array $config, string $naam): ?string
{
    if (!tenantBrandingAssetNaamGeldig($naam)) return null;
    $root = tenantBrandingAssetRoot($config);
    if ($root === null) return null;
    return $root . DIRECTORY_SEPARATOR . $naam;
}

function tenantBrandingAssetUrl(array $config, string $naam): string
{
    if (!tenantBrandingAssetNaamGeldig($naam)) return '';
    $site = rtrim((string)($config['vereniging']['site_url'] ?? ''), '/');
    $relatief = 'branding-asset.php?name=' . rawurlencode($naam);
    return $site !== '' ? $site . '/' . $relatief : '/' . $relatief;
}

function tenantBrandingAssetVerwijderVarianten(array $config, string $type, string $behoud): void
{
    if (!in_array($type, tenantBrandingAssetTypes(), true)) return;
    $root = tenantBrandingAssetRoot($config);
    if ($root === null) return;
    foreach (['png','jpg','jpeg','webp'] as $ext) {
        $naam = $type . '.' . $ext;
        if ($naam === $behoud) continue;
        $pad = $root . DIRECTORY_SEPARATOR . $naam;
        if (is_file($pad) && !is_link($pad)) @unlink($pad);
    }
}

function tenantBrandingAssetUpload(array $config, array $upload, string $type): string
{
    if (!in_array($type, tenantBrandingAssetTypes(), true)) throw new InvalidArgumentException('Onbekend brandingtype.');
    $fout = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($fout === UPLOAD_ERR_NO_FILE) return '';
    if ($fout !== UPLOAD_ERR_OK) throw new RuntimeException('Upload is niet volledig ontvangen.');

    $tmp = (string)($upload['tmp_name'] ?? '');
    $grootte = (int)($upload['size'] ?? 0);
    $limiet = match ($type) {
        'favicon' => 1024 * 1024,
        'logo' => 5 * 1024 * 1024,
        default => 8 * 1024 * 1024,
    };
    if ($tmp === '' || !is_uploaded_file($tmp) || $grootte < 1 || $grootte > $limiet) {
        throw new RuntimeException($type === 'favicon'
            ? 'Favicon moet een afbeelding van maximaal 1 MB zijn.'
            : ($type === 'logo' ? 'Logo moet een afbeelding van maximaal 5 MB zijn.' : 'Websitebeeld moet een afbeelding van maximaal 8 MB zijn.'));
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);
    $ext = match ($mime) {
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        default => '',
    };
    if ($ext === '') throw new RuntimeException('Gebruik PNG, JPG of WebP voor afbeeldingen.');

    $root = tenantBrandingAssetRoot($config);
    if ($root === null) throw new RuntimeException('Brandinguploads zijn alleen beschikbaar voor tenants met private opslag.');
    tenantBrandingAssetTransactieBegin($config);
    if (is_link($root)) throw new RuntimeException('Brandingopslag is onveilig geconfigureerd.');
    if (!is_dir($root) && !@mkdir($root, 0750, true)) throw new RuntimeException('Brandingmap kon niet worden aangemaakt.');
    clearstatcache(true, $root);
    if (!is_dir($root) || is_link($root)) throw new RuntimeException('Brandingmap is niet veilig beschikbaar.');
    @chmod($root, 0750);

    $naam = $type . '.' . $ext;
    $doel = $root . DIRECTORY_SEPARATOR . $naam;
    if (is_link($doel)) throw new RuntimeException('Brandingdoel mag geen symlink zijn.');
    $tijdelijk = $doel . '.tmp.' . bin2hex(random_bytes(5));
    if (!@move_uploaded_file($tmp, $tijdelijk)) throw new RuntimeException('Upload kon niet veilig worden opgeslagen.');
    @chmod($tijdelijk, 0640);
    if (!@rename($tijdelijk, $doel)) { @unlink($tijdelijk); throw new RuntimeException('Upload kon niet worden geactiveerd.'); }
    @chmod($doel, 0640);
    tenantBrandingAssetVerwijderVarianten($config, $type, $naam);
    return tenantBrandingAssetUrl($config, $naam);
}

function tenantBrandingAssetLeesPad(array $config, string $naam): ?string
{
    $pad = tenantBrandingAssetPad($config, $naam);
    $root = tenantBrandingAssetRoot($config);
    if ($pad === null || $root === null || !is_dir($root) || is_link($root) || !is_file($pad) || is_link($pad) || !is_readable($pad)) return null;
    $rootReal = realpath($root);
    $padReal = realpath($pad);
    if ($rootReal === false || $padReal === false) return null;
    $rootPrefix = rtrim(str_replace('\\','/',$rootReal), '/') . '/';
    $normPad = str_replace('\\','/',$padReal);
    return str_starts_with($normPad, $rootPrefix) ? $padReal : null;
}