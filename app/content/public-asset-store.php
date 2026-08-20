<?php
// ============================================================
// Tenant-aware opslag voor publiek serveerbare bestanden
// ============================================================
// Uploads staan voor externe tenants buiten de documentroot en worden alleen
// via public-asset.php teruggeserveerd. De standalone RC045-installatie blijft
// compatibel met de bestaande images/fotoboek en images/sponsors mappen.
// ============================================================

require_once dirname(__DIR__) . '/core/site.php';
require_once dirname(__DIR__) . '/storage/tenant-backup-store.php';

function publicAssetDefinities(): array
{
    static $definities = null;
    if ($definities !== null) return $definities;

    $definities = [
        'fotoboek' => [
            'legacy_map' => 'fotoboek',
            'mimes' => [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'mp4' => 'video/mp4',
            ],
        ],
        'sponsors' => [
            'legacy_map' => 'sponsors',
            'mimes' => [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
            ],
        ],
    ];
    return $definities;
}

function publicAssetDefinitie(string $scope): ?array
{
    $alles = publicAssetDefinities();
    return isset($alles[$scope]) && is_array($alles[$scope]) ? $alles[$scope] : null;
}

function publicAssetPadVoorVergelijk(string $pad): string
{
    $pad = str_replace('\\', '/', $pad);
    $pad = (string) preg_replace('~/+~', '/', $pad);
    if (DIRECTORY_SEPARATOR === '\\') $pad = strtolower($pad);
    return rtrim($pad, '/');
}

function publicAssetPadBinnen(string $pad, string $root): bool
{
    $pad = publicAssetPadVoorVergelijk($pad);
    $root = publicAssetPadVoorVergelijk($root);
    return $pad === $root || strncmp($pad, $root . '/', strlen($root) + 1) === 0;
}

function publicAssetLegacyRoot(): string
{
    return siteProjectRoot() . DIRECTORY_SEPARATOR . 'images';
}

function publicAssetTenantRoot(): ?string
{
    $config = siteConfig();
    $privateRoot = tenantRuntimePrivateRoot($config);
    if ($privateRoot !== null) {
        return $privateRoot . DIRECTORY_SEPARATOR . 'public-assets';
    }

    if (tenantRuntimeExternConfigPad() !== null || tenantRuntimeConfigVerplicht()) {
        tenantRuntimeConfiguratieFout('Externe tenant heeft geen private_root voor publieke assets.');
    }
    return null;
}

/**
 * Defense-in-depth voor alle tenantassetpaden. De geconfigureerde private_root
 * is de harde filesystemgrens. Geen enkele component daaronder mag een
 * symlink zijn, ook public-assets zelf niet. Voor nog niet bestaande doelen
 * wordt de dichtstbijzijnde bestaande ancestor fysiek gecontroleerd.
 */
function publicAssetTenantPadVeilig(string $pad): bool
{
    $tenantRoot = publicAssetTenantRoot();
    if ($tenantRoot === null) return true;

    $privateRoot = tenantRuntimePrivateRoot(siteConfig());
    if ($privateRoot === null || !is_dir($privateRoot) || is_link($privateRoot)) return false;
    if (!publicAssetPadBinnen($pad, $privateRoot)) return false;

    $privateReal = realpath($privateRoot);
    if ($privateReal === false) return false;

    $cursor = rtrim($pad, '/\\');
    if ($cursor === '') return false;
    while (true) {
        if (is_link($cursor)) return false;
        if (publicAssetPadVoorVergelijk($cursor) === publicAssetPadVoorVergelijk($privateRoot)) break;
        $parent = dirname($cursor);
        if ($parent === $cursor || !publicAssetPadBinnen($parent, $privateRoot)) return false;
        $cursor = $parent;
    }

    $bestaand = rtrim($pad, '/\\');
    while (!file_exists($bestaand) && !is_link($bestaand)) {
        $parent = dirname($bestaand);
        if ($parent === $bestaand || !publicAssetPadBinnen($parent, $privateRoot)) return false;
        $bestaand = $parent;
    }
    if (is_link($bestaand)) return false;
    $bestaandReal = realpath($bestaand);
    if ($bestaandReal === false || !publicAssetPadBinnen($bestaandReal, $privateReal)) return false;

    return true;
}

function publicAssetNamespaceRoot(string $scope): ?string
{
    $def = publicAssetDefinitie($scope);
    if ($def === null) return null;
    $map = trim((string) ($def['legacy_map'] ?? ''));
    if ($map === '' || preg_match('/^[a-z0-9-]+$/D', $map) !== 1) return null;

    $tenantRoot = publicAssetTenantRoot();
    $root = $tenantRoot ?? publicAssetLegacyRoot();
    return $root . DIRECTORY_SEPARATOR . $map;
}

/**
 * Normaliseert alleen paden die exact binnen een bekende assetnamespace passen.
 * Geen URL-decoding hier: de webserver/PHP hebben de querywaarde al verwerkt.
 */
function publicAssetRelatiefPad(string $scope, string $relatief): ?string
{
    $def = publicAssetDefinitie($scope);
    if ($def === null) return null;
    if ($relatief === '' || str_contains($relatief, "\0") || str_contains($relatief, '\\')) return null;
    if ($relatief[0] === '/' || str_ends_with($relatief, '/')) return null;

    $delen = explode('/', $relatief);
    foreach ($delen as $deel) {
        if ($deel === '' || $deel === '.' || $deel === '..') return null;
    }

    if ($scope === 'sponsors') {
        if (count($delen) !== 1) return null;
    } elseif ($scope === 'fotoboek') {
        if (count($delen) !== 2 && count($delen) !== 3) return null;
        if (preg_match('/^[a-z0-9][a-z0-9-]*$/D', $delen[0]) !== 1) return null;
        if (count($delen) === 3 && $delen[1] !== 'thumbs') return null;
    } else {
        return null;
    }

    $bestand = $delen[count($delen) - 1];
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,180}$/D', $bestand) !== 1) return null;
    $ext = strtolower((string) pathinfo($bestand, PATHINFO_EXTENSION));
    $mimes = is_array($def['mimes'] ?? null) ? $def['mimes'] : [];
    if ($ext === '' || !isset($mimes[$ext])) return null;

    return implode(DIRECTORY_SEPARATOR, $delen);
}

function publicAssetMime(string $scope, string $relatief): ?string
{
    $genormaliseerd = publicAssetRelatiefPad($scope, $relatief);
    $def = publicAssetDefinitie($scope);
    if ($genormaliseerd === null || $def === null) return null;
    $ext = strtolower((string) pathinfo($genormaliseerd, PATHINFO_EXTENSION));
    $mimes = is_array($def['mimes'] ?? null) ? $def['mimes'] : [];
    return isset($mimes[$ext]) ? (string) $mimes[$ext] : null;
}

function publicAssetPad(string $scope, string $relatief): ?string
{
    $root = publicAssetNamespaceRoot($scope);
    $genormaliseerd = publicAssetRelatiefPad($scope, $relatief);
    if ($root === null || $genormaliseerd === null) return null;
    return $root . DIRECTORY_SEPARATOR . $genormaliseerd;
}

function publicAssetIsTenantPad(string $pad): bool
{
    $tenantRoot = publicAssetTenantRoot();
    if ($tenantRoot === null) return false;
    return publicAssetPadBinnen($pad, $tenantRoot);
}

/**
 * Controleert vóór publiek lezen dat het echte bestand binnen de bedoelde
 * namespace blijft en geen symlinkcomponent bevat. Hiermee kan een lokale
 * symlink nooit als leesbrug naar private tenantdata fungeren.
 */
function publicAssetVeiligLeesPad(string $scope, string $relatief): ?string
{
    $root = publicAssetNamespaceRoot($scope);
    $pad = publicAssetPad($scope, $relatief);
    if ($root === null || $pad === null) return null;
    if (!publicAssetTenantPadVeilig($root) || !publicAssetTenantPadVeilig($pad)) return null;
    if (!is_file($pad) || !is_readable($pad)) return null;

    $rootReal = realpath($root);
    $padReal = realpath($pad);
    if ($rootReal === false || $padReal === false) return null;

    $rootVergelijk = publicAssetPadVoorVergelijk($rootReal);
    $padVergelijk = publicAssetPadVoorVergelijk($padReal);
    if ($padVergelijk === $rootVergelijk || strncmp($padVergelijk, $rootVergelijk . '/', strlen($rootVergelijk) + 1) !== 0) return null;

    $cursor = $pad;
    while (publicAssetPadVoorVergelijk($cursor) !== publicAssetPadVoorVergelijk($root)) {
        if (is_link($cursor)) return null;
        $parent = dirname($cursor);
        if ($parent === $cursor) return null;
        $cursor = $parent;
    }
    if (is_link($root)) return null;
    return $padReal;
}

/** Eén pre-write assetsnapshot per scope per POST-request. */
function publicAssetMaakPreWriteSnapshot(string $scope): void
{
    static $gedaan = [];
    if (($gedaan[$scope] ?? false) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || publicAssetTenantRoot() === null) return;
    $gedaan[$scope] = true;
    $root = publicAssetNamespaceRoot($scope);
    if ($root !== null && is_dir($root)) tenantBackupMaakAssetSnapshot($scope);
}

function publicAssetMaakNamespaceMap(string $scope): ?string
{
    $root = publicAssetNamespaceRoot($scope);
    if ($root === null || !publicAssetTenantPadVeilig($root)) return null;
    $tenant = publicAssetTenantRoot() !== null;
    if ($tenant) publicAssetMaakPreWriteSnapshot($scope);
    $mode = $tenant ? 0750 : 0755;
    if (!is_dir($root) && !@mkdir($root, $mode, true)) return null;
    clearstatcache(true, $root);
    if (!publicAssetTenantPadVeilig($root) || is_link($root) || !is_dir($root)) return null;
    if ($tenant) @chmod($root, 0750);
    return $root;
}

function publicAssetBeveiligBestand(string $pad): void
{
    if (publicAssetIsTenantPad($pad) && publicAssetTenantPadVeilig($pad) && is_file($pad) && !is_link($pad)) @chmod($pad, 0640);
}
