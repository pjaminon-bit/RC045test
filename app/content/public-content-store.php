<?php
// ============================================================
// Tenant-aware publieke contentopslag
// ============================================================
// Publieke JSON mag inhoudelijk openbaar zijn, maar de opslag ervan is per
// vereniging gescheiden. Een externe tenant mag daarom nooit terugvallen op
// de RC045-bestanden onder /data wanneer zijn eigen bestand ontbreekt.
// ============================================================

require_once dirname(__DIR__) . '/core/site.php';

function publicContentDefinities(): array
{
    static $definities = null;
    if ($definities !== null) return $definities;

    // Alleen expliciet geregistreerde datasets zijn via het publieke endpoint
    // opvraagbaar. Media/fotoboek en binaire uploads volgen bewust in optie 8.
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

/**
 * Geeft voor externe/private-root tenants de eigen contentroot terug.
 * Een expliciete externe tenant zonder private_root faalt bewust hard: in dat
 * scenario terugvallen op /data zou precies de cross-tenant fout opnieuw
 * introduceren die deze laag moet voorkomen.
 */
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

/**
 * Compatibiliteitsadapter voor bestaande beheereditors die nog een absoluut
 * projectpad als /data/contact.json doorgeven. Alleen exact geregistreerde
 * legacybestanden worden omgebogen; willekeurige paden blijven ongemoeid.
 */
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
