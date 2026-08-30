<?php
// ============================================================
// Tenant-aware publieke contentopslag
// ============================================================
// Publieke JSON mag inhoudelijk openbaar zijn, maar de opslag ervan is per
// vereniging gescheiden. Een externe tenant mag daarom nooit terugvallen op
// de RC045-bestanden onder /data wanneer zijn eigen bestand ontbreekt.
// ============================================================

require_once dirname(__DIR__) . '/core/site.php';
require_once dirname(__DIR__) . '/storage/tenant-backup-store.php';

function publicContentDefinities(): array
{
    static $definities = null;
    if ($definities !== null) return $definities;

    $definities = [
        'homepage' => 'homepage.json',
        'ontstaan' => 'ontstaan.json',
        'baanreglement' => 'baanreglement.json',
        'aanmelden' => 'aanmelden.json',
        'bedankt' => 'bedankt.json',
        'actueel' => 'actueel.json',
        'agenda' => 'agenda.json',
        'faq' => 'faq.json',
        'sponsors' => 'sponsors.json',
        'contact' => 'contact.json',
        'nieuws' => 'nieuws.json',
        'media' => 'media.json',
        'media-pagina' => 'media-pagina.json',
        'fotoboek' => 'fotoboek.json',
        'fotoboek-pagina' => 'fotoboek-pagina.json',
        'rekentabel' => 'rekentabel.json',
        'lidmaatschapstypen' => 'lidmaatschapstypen.json',
        'changelog' => 'changelog.json',
    ];
    return $definities;
}

function publicContentBestandsnaam(string $sleutel): ?string
{
    $definities = publicContentDefinities();
    return isset($definities[$sleutel]) ? $definities[$sleutel] : null;
}

function publicContentPadVoorVergelijk(string $pad): string
{
    $pad = str_replace('\\', '/', $pad);
    $pad = (string) preg_replace('~/+~', '/', $pad);
    if (DIRECTORY_SEPARATOR === '\\') $pad = strtolower($pad);
    return rtrim($pad, '/');
}

function publicContentLegacyRoot(): string
{
    return siteProjectRoot() . DIRECTORY_SEPARATOR . 'data';
}

function publicContentTenantRoot(): ?string
{
    $config = siteConfig();
    $privateRoot = tenantRuntimePrivateRoot($config);
    if ($privateRoot !== null) {
        return $privateRoot . DIRECTORY_SEPARATOR . 'public-content';
    }

    if (tenantRuntimeExternConfigPad() !== null || tenantRuntimeConfigVerplicht()) {
        tenantRuntimeConfiguratieFout('Externe tenant heeft geen private_root voor publieke content.');
    }
    return null;
}

function publicContentPad(string $sleutel): ?string
{
    $bestand = publicContentBestandsnaam($sleutel);
    if ($bestand === null) return null;

    $tenantRoot = publicContentTenantRoot();
    $root = $tenantRoot ?? publicContentLegacyRoot();
    return $root . DIRECTORY_SEPARATOR . $bestand;
}

function publicContentIsTenantPad(string $pad): bool
{
    $tenantRoot = publicContentTenantRoot();
    if ($tenantRoot === null) return false;
    $pad = publicContentPadVoorVergelijk($pad);
    $root = publicContentPadVoorVergelijk($tenantRoot);
    return $pad === $root || strncmp($pad, $root . '/', strlen($root) + 1) === 0;
}

function publicContentSleutelVoorPad(string $pad): ?string
{
    foreach (publicContentDefinities() as $sleutel => $bestand) {
        $doel = publicContentPad((string) $sleutel);
        if ($doel !== null && publicContentPadVoorVergelijk($doel) === publicContentPadVoorVergelijk($pad)) return (string) $sleutel;
    }
    return null;
}

function publicContentMapLegacyPad(string $pad): string
{
    $legacyRoot = publicContentLegacyRoot();
    foreach (publicContentDefinities() as $sleutel => $bestand) {
        $legacy = $legacyRoot . DIRECTORY_SEPARATOR . $bestand;
        if (publicContentPadVoorVergelijk($pad) === publicContentPadVoorVergelijk($legacy)) {
            return publicContentPad((string) $sleutel) ?? $pad;
        }
    }
    return $pad;
}

function publicContentLees(string $sleutel): ?array
{
    $pad = publicContentPad($sleutel);
    if ($pad === null || !is_file($pad) || !is_readable($pad)) return null;

    $raw = @file_get_contents($pad);
    if ($raw === false) return null;
    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        error_log('[platform] ongeldige publieke content voor dataset ' . $sleutel);
        return null;
    }
    return is_array($data) ? $data : null;
}

function publicContentMaakBackupVoorPad(string $pad): ?string
{
    if (!publicContentIsTenantPad($pad) || !is_file($pad)) return null;
    $sleutel = publicContentSleutelVoorPad($pad);
    if ($sleutel === null) return null;
    $data = publicContentLees($sleutel);
    if ($data === null) return null;
    return tenantBackupMaakArray('public-' . $sleutel, $data);
}

/**
 * Centrale tenantwriter voor restore en nieuwe codepaden. Als er al een
 * huidige versie bestaat is een aantoonbaar opgeslagen pre-write snapshot een
 * harde voorwaarde; een backupfout mag nooit ongemerkt gevolgd worden door de
 * destructieve overschrijving.
 */
function publicContentSchrijfTenant(string $sleutel, array $data, bool $maakBackup = true): bool
{
    $pad = publicContentPad($sleutel);
    if ($pad === null || !publicContentIsTenantPad($pad) || !tenantBackupPadVeilig($pad)) return false;
    if ($maakBackup && is_file($pad)) {
        $snapshot = publicContentMaakBackupVoorPad($pad);
        if ($snapshot === null) {
            error_log('[platform] publieke contentwrite afgebroken: pre-backup faalde voor ' . $sleutel);
            return false;
        }
    }

    $map = dirname($pad);
    if (!is_dir($map) && !@mkdir($map, 0750, true)) return false;
    clearstatcache(true, $map);
    if (!is_dir($map) || is_link($map) || !tenantBackupPadVeilig($map)) return false;
    @chmod($map, 0750);

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    try { $suffix = bin2hex(random_bytes(5)); }
    catch (Throwable $e) { $suffix = substr(hash('sha256', (string) microtime(true)), 0, 10); }
    $tmp = $pad . '.tmp.' . $suffix;
    if (!tenantBackupPadVeilig($tmp) || @file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    @chmod($tmp, 0640);
    if (!tenantBackupPadVeilig($pad) || !@rename($tmp, $pad)) { @unlink($tmp); return false; }
    @chmod($pad, 0640);
    return true;
}
