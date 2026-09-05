<?php
// ============================================================
// Ledenimport: tijdelijke server-side previewopslag
// ============================================================
// CSV-importregels bevatten PII en financiële gegevens. Die horen niet in de
// algemene, zeven dagen levende PHP-sessie. Deze store bewaart de payload in
// een installatie-/tenantprivate tempmap en laat de sessie alleen een random
// preview-id onthouden.

const LEDEN_IMPORT_PREVIEW_SCHEMA = 1;
const LEDEN_IMPORT_PREVIEW_TTL = 3600;
const LEDEN_IMPORT_PREVIEW_MAX_RIJEN = 5000;
const LEDEN_IMPORT_PREVIEW_MAX_BYTES = 16 * 1024 * 1024;
const LEDEN_IMPORT_PREVIEW_MAX_BESTANDEN = 200;

function ledenImportPreviewStorePrivateRoot(string $privateRoot): ?string
{
    $privateRoot = rtrim(trim($privateRoot), '/\\');
    if ($privateRoot === '' || !str_starts_with($privateRoot, DIRECTORY_SEPARATOR)) return null;
    return $privateRoot . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'leden-import';
}

function ledenImportPreviewStoreContext(
    array $authPaden,
    string $tenantKey,
    string $gebruiker,
    string $sessionId
): ?array {
    $sessions = rtrim(trim((string)($authPaden['sessions'] ?? '')), '/\\');
    $installatieBinding = trim((string)($authPaden['session_binding'] ?? ''));
    $tenantKey = trim($tenantKey);
    $gebruiker = trim($gebruiker);
    $sessionId = trim($sessionId);

    if ($sessions === '' || $installatieBinding === '' || $tenantKey === '' || $gebruiker === '' || $sessionId === '') {
        return null;
    }

    // Externe tenants houden tempdata rechtstreeks onder private_root. Voor
    // standalone gebruiken we de reeds installatie-geïsoleerde session-root;
    // zo komt de preview ook daar niet in de webroot terecht.
    if (!empty($authPaden['tenant_private'])) {
        $privateRoot = dirname($sessions);
        $root = ledenImportPreviewStorePrivateRoot($privateRoot);
        $boundary = $privateRoot;
    } else {
        $root = $sessions . DIRECTORY_SEPARATOR . 'leden-import-previews';
        $boundary = $sessions;
    }
    if (!is_string($root) || $root === '') return null;

    $ownerBinding = hash(
        'sha256',
        $installatieBinding . "\0" . strtolower($tenantKey) . "\0" . strtolower($gebruiker) . "\0" . $sessionId
    );

    return [
        'root' => $root,
        'boundary' => $boundary,
        'tenant_key' => $tenantKey,
        'owner_binding' => $ownerBinding,
    ];
}

function ledenImportPreviewStorePadBinnenBoundary(string $boundary, string $pad): bool
{
    $boundary = rtrim($boundary, '/\\');
    $pad = rtrim($pad, '/\\');
    if ($boundary === '' || $pad === '' || !str_starts_with($boundary, DIRECTORY_SEPARATOR)) return false;
    return $pad !== $boundary && str_starts_with($pad, $boundary . DIRECTORY_SEPARATOR);
}

function ledenImportPreviewStoreMaakRoot(array $context): bool
{
    $root = rtrim((string)($context['root'] ?? ''), '/\\');
    $boundary = rtrim((string)($context['boundary'] ?? ''), '/\\');
    if (!ledenImportPreviewStorePadBinnenBoundary($boundary, $root)) return false;
    if (!is_dir($boundary) || is_link($boundary)) return false;

    // De store kent maximaal twee eigen submappen. Maak iedere component apart
    // zodat een bestaande symlink in de private tempnamespace nooit door een
    // recursive mkdir wordt gevolgd.
    $rel = substr($root, strlen($boundary) + 1);
    $parts = preg_split('~[/\\\\]+~', $rel, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($parts) || $parts === [] || count($parts) > 2) return false;
    $current = $boundary;
    foreach ($parts as $part) {
        if ($part === '.' || $part === '..') return false;
        $current .= DIRECTORY_SEPARATOR . $part;
        if (is_link($current)) return false;
        if (!is_dir($current) && !@mkdir($current, 0750) && !is_dir($current)) return false;
        @chmod($current, 0750);
        if (!is_dir($current) || is_link($current)) return false;
    }

    return hash_equals($root, $current) && is_writable($root);
}

function ledenImportPreviewStoreIdGeldig(string $id): bool
{
    return preg_match('/^[a-f0-9]{64}$/D', $id) === 1;
}

function ledenImportPreviewStorePad(array $context, string $id): ?string
{
    if (!ledenImportPreviewStoreIdGeldig($id)) return null;
    $root = rtrim((string)($context['root'] ?? ''), '/\\');
    $boundary = rtrim((string)($context['boundary'] ?? ''), '/\\');
    if (!ledenImportPreviewStorePadBinnenBoundary($boundary, $root)) return null;
    return $root . DIRECTORY_SEPARATOR . $id . '.json';
}

function ledenImportPreviewStorePreviewGeldig(array $preview): bool
{
    $resultaten = $preview['resultaten'] ?? null;
    if (!is_array($resultaten) || $resultaten === [] || count($resultaten) > LEDEN_IMPORT_PREVIEW_MAX_RIJEN) {
        return false;
    }
    foreach ($resultaten as $item) {
        if (!is_array($item) || !is_array($item['rij'] ?? null)) return false;
    }
    return true;
}

function ledenImportPreviewStoreVerwijderPad(string $pad): bool
{
    if ($pad === '' || is_dir($pad)) return false;
    if (!file_exists($pad) && !is_link($pad)) return true;
    return @unlink($pad);
}

function ledenImportPreviewStoreCleanupNaam(string $naam): bool
{
    return preg_match('/^[a-f0-9]{64}\.json$/D', $naam) === 1
        || preg_match('/^\.tmp-[a-f0-9]{12}$/D', $naam) === 1;
}

/**
 * Ruimt verlopen previews op voor alle users/sessies binnen dezelfde
 * installatie/tenant. Final previews gebruiken expires_at én mtime; een door
 * procesafbreking achtergebleven .tmp-file heeft nog geen envelope en wordt
 * daarom uitsluitend op mtime na dezelfde TTL verwijderd.
 */
function ledenImportPreviewStoreCleanup(array $context, ?int $nu = null, bool $strict = false): int
{
    $root = rtrim((string)($context['root'] ?? ''), '/\\');
    $boundary = rtrim((string)($context['boundary'] ?? ''), '/\\');
    if (!ledenImportPreviewStorePadBinnenBoundary($boundary, $root)) {
        if ($strict) throw new RuntimeException('Ledenimport-previewroot valt buiten de private boundary.');
        return 0;
    }
    if (!file_exists($root) && !is_link($root)) return 0;
    if (!is_dir($root) || is_link($root)) {
        if ($strict) throw new RuntimeException('Ledenimport-previewroot is geen veilige directory.');
        return 0;
    }
    $realBoundary = realpath($boundary);
    $realRoot = realpath($root);
    if (!is_string($realBoundary) || !is_string($realRoot)
        || !ledenImportPreviewStorePadBinnenBoundary($realBoundary, $realRoot)) {
        if ($strict) throw new RuntimeException('Ledenimport-previewroot verlaat fysiek de private boundary.');
        return 0;
    }

    $nu ??= time();
    $verwijderd = 0;
    $entries = scandir($root);
    if (!is_array($entries)) {
        if ($strict) throw new RuntimeException('Ledenimport-previewroot kon niet worden gelezen.');
        return 0;
    }

    foreach ($entries as $naam) {
        if ($naam === '.' || $naam === '..' || !ledenImportPreviewStoreCleanupNaam($naam)) continue;
        $pad = $root . DIRECTORY_SEPARATOR . $naam;

        // Nooit een symlink volgen. Een symlink in deze private tempmap is
        // geen geldige preview en kan veilig als directory-entry weg.
        if (is_link($pad)) {
            if (@unlink($pad)) {
                $verwijderd++;
            } elseif ($strict) {
                throw new RuntimeException('Onveilige ledenimport-previewsymlink kon niet worden verwijderd.');
            }
            continue;
        }
        if (!is_file($pad)) continue;

        $mtime = @filemtime($pad);
        $verlopen = is_int($mtime) && $mtime > 0 && $mtime <= $nu - LEDEN_IMPORT_PREVIEW_TTL;
        $isTmp = str_starts_with($naam, '.tmp-');
        $grootte = @filesize($pad);
        $raw = null;

        if (!$isTmp && is_int($grootte) && $grootte >= 0 && $grootte <= LEDEN_IMPORT_PREVIEW_MAX_BYTES) {
            $raw = @file_get_contents($pad);
        }
        if (!$isTmp && is_string($raw)) {
            try {
                $envelope = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($envelope) && (int)($envelope['expires_at'] ?? 0) <= $nu) $verlopen = true;
            } catch (JsonException $e) {
                // Een recente corrupte entry laten we staan voor diagnose; na
                // de TTL wordt hij op mtime alsnog verwijderd.
            }
        }

        if ($verlopen) {
            if (@unlink($pad)) {
                $verwijderd++;
            } elseif ($strict) {
                throw new RuntimeException('Verlopen ledenimport-preview kon niet worden verwijderd.');
            }
        }
    }

    return $verwijderd;
}

/**
 * Periodieke productie-GC zonder authsessie. De bestaande VPS-healthtimer
 * roept /healthz.php iedere minuut via tenant-FPM aan; zo wordt verlopen PII
 * rond de één-uursgrens verwijderd zonder repository-PHP als root uit te voeren.
 */
function ledenImportPreviewStoreCleanupPrivateRoot(string $privateRoot, ?int $nu = null): int
{
    $privateRoot = rtrim(trim($privateRoot), '/\\');
    $root = ledenImportPreviewStorePrivateRoot($privateRoot);
    if ($root === null || !is_dir($privateRoot) || is_link($privateRoot)) {
        throw new RuntimeException('Private root voor ledenimport-cleanup is ongeldig.');
    }
    $tmpRoot = $privateRoot . DIRECTORY_SEPARATOR . 'tmp';
    if (is_link($tmpRoot) || is_link($root)) {
        throw new RuntimeException('Ledenimport-tempnamespace mag geen symlink bevatten.');
    }
    return ledenImportPreviewStoreCleanup([
        'root' => $root,
        'boundary' => $privateRoot,
    ], $nu, true);
}

function ledenImportPreviewStoreAantalBestanden(array $context): int
{
    $root = (string)($context['root'] ?? '');
    if ($root === '' || !is_dir($root) || is_link($root)) return 0;
    $aantal = 0;
    foreach (scandir($root) ?: [] as $naam) {
        if (preg_match('/^[a-f0-9]{64}\.json$/D', $naam) === 1) $aantal++;
    }
    return $aantal;
}

function ledenImportPreviewStoreBewaar(array $context, array $preview, ?int $nu = null): ?string
{
    if (!ledenImportPreviewStorePreviewGeldig($preview) || !ledenImportPreviewStoreMaakRoot($context)) return null;
    $nu ??= time();
    ledenImportPreviewStoreCleanup($context, $nu);
    if (ledenImportPreviewStoreAantalBestanden($context) >= LEDEN_IMPORT_PREVIEW_MAX_BESTANDEN) return null;

    $tenantKey = (string)($context['tenant_key'] ?? '');
    $ownerBinding = (string)($context['owner_binding'] ?? '');
    if ($tenantKey === '' || preg_match('/^[a-f0-9]{64}$/D', $ownerBinding) !== 1) return null;

    $envelope = [
        'schema' => LEDEN_IMPORT_PREVIEW_SCHEMA,
        'tenant_key' => $tenantKey,
        'owner_binding' => $ownerBinding,
        'created_at' => $nu,
        'expires_at' => $nu + LEDEN_IMPORT_PREVIEW_TTL,
        'preview' => $preview,
    ];

    try {
        $json = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        return null;
    }
    if (!is_string($json) || strlen($json) > LEDEN_IMPORT_PREVIEW_MAX_BYTES) return null;

    $root = rtrim((string)$context['root'], '/\\');
    for ($poging = 0; $poging < 5; $poging++) {
        try {
            $id = bin2hex(random_bytes(32));
            $suffix = bin2hex(random_bytes(6));
        } catch (Throwable $e) {
            return null;
        }
        $pad = $root . DIRECTORY_SEPARATOR . $id . '.json';
        if (file_exists($pad) || is_link($pad)) continue;
        $tmp = $root . DIRECTORY_SEPARATOR . '.tmp-' . $suffix;
        $h = @fopen($tmp, 'x+b');
        if ($h === false) continue;
        @chmod($tmp, 0640);
        $ok = false;
        try {
            $lengte = strlen($json);
            $offset = 0;
            while ($offset < $lengte) {
                $n = fwrite($h, substr($json, $offset));
                if ($n === false || $n === 0) break;
                $offset += $n;
            }
            if ($offset === $lengte && fflush($h)) {
                if (function_exists('fsync')) @fsync($h);
                $ok = true;
            }
        } finally {
            fclose($h);
        }
        if (!$ok || !@rename($tmp, $pad)) {
            @unlink($tmp);
            continue;
        }
        @chmod($pad, 0640);
        return $id;
    }

    return null;
}

function ledenImportPreviewStoreLees(array $context, string $id, ?string &$status = null, ?int $nu = null): ?array
{
    $status = 'missing';
    $nu ??= time();
    $pad = ledenImportPreviewStorePad($context, $id);
    if ($pad === null || is_link($pad) || !is_file($pad)) return null;

    $grootte = @filesize($pad);
    if (!is_int($grootte) || $grootte < 1 || $grootte > LEDEN_IMPORT_PREVIEW_MAX_BYTES) {
        $status = 'invalid';
        return null;
    }
    $raw = @file_get_contents($pad);
    if (!is_string($raw) || strlen($raw) > LEDEN_IMPORT_PREVIEW_MAX_BYTES) {
        $status = 'invalid';
        return null;
    }

    try {
        $envelope = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        $status = 'invalid';
        return null;
    }
    if (!is_array($envelope)
        || (int)($envelope['schema'] ?? 0) !== LEDEN_IMPORT_PREVIEW_SCHEMA
        || !is_array($envelope['preview'] ?? null)
        || !ledenImportPreviewStorePreviewGeldig($envelope['preview'])) {
        $status = 'invalid';
        return null;
    }

    $expiresAt = (int)($envelope['expires_at'] ?? 0);
    $createdAt = (int)($envelope['created_at'] ?? 0);
    if ($expiresAt <= $nu || $createdAt <= 0 || $expiresAt - $createdAt !== LEDEN_IMPORT_PREVIEW_TTL) {
        $status = 'expired';
        @unlink($pad);
        return null;
    }

    $tenantKey = (string)($context['tenant_key'] ?? '');
    $ownerBinding = (string)($context['owner_binding'] ?? '');
    $storedTenant = (string)($envelope['tenant_key'] ?? '');
    $storedOwner = (string)($envelope['owner_binding'] ?? '');
    if ($tenantKey === '' || $ownerBinding === ''
        || !hash_equals($tenantKey, $storedTenant)
        || !hash_equals($ownerBinding, $storedOwner)) {
        $status = 'forbidden';
        return null;
    }

    $status = 'ok';
    return $envelope['preview'];
}

function ledenImportPreviewStoreVerwijder(array $context, string $id): bool
{
    $status = null;
    $preview = ledenImportPreviewStoreLees($context, $id, $status);
    if ($preview === null) return in_array($status, ['missing', 'expired'], true);
    if ($status !== 'ok') return false;
    $pad = ledenImportPreviewStorePad($context, $id);
    return $pad !== null && ledenImportPreviewStoreVerwijderPad($pad);
}
