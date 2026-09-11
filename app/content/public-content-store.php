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
require_once dirname(__DIR__) . '/storage/private-filesystem.php';

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

function publicContentIsLijst(array $data): bool
{
    return function_exists('array_is_list') ? array_is_list($data) : array_keys($data) === range(0, count($data) - 1);
}

/**
 * Minimale documentschemavalidatie voor datasets waarvoor de rootvorm deel
 * uitmaakt van het storagecontract. Dit voorkomt dat geldige JSON met een
 * onbruikbare root stil als een lege/default dataset wordt behandeld.
 */
function publicContentStructuurGeldig(string $sleutel, array $data): bool
{
    if (in_array($sleutel, ['agenda', 'media'], true)) {
        return publicContentIsLijst($data);
    }
    if (in_array($sleutel, ['media-pagina', 'fotoboek-pagina'], true)) {
        return isset($data['hero_sub']) && is_array($data['hero_sub']);
    }
    if ($sleutel === 'sponsors') {
        return isset($data['items']) && is_array($data['items']);
    }
    if ($sleutel === 'fotoboek') {
        return publicContentIsLijst($data) || (isset($data['albums']) && is_array($data['albums']));
    }
    if ($sleutel === 'lidmaatschapstypen') {
        return isset($data['types']) && is_array($data['types']);
    }
    return true;
}

/**
 * Getypeerde publieke-contentread. Alleen een werkelijk ontbrekend bestand is
 * "missing". Een bestaand maar onleesbaar, syntactisch ongeldig of voor een
 * bekende dataset structureel ongeldig document is "invalid".
 *
 * @return array{status:string,data:?array,code:?string,message:?string}
 */
function publicContentLeesResult(string $sleutel): array
{
    $pad = publicContentPad($sleutel);
    if ($pad === null) {
        return ['status' => 'missing', 'data' => null, 'code' => 'onbekende_dataset', 'message' => null];
    }
    if (!is_file($pad)) {
        return ['status' => 'missing', 'data' => null, 'code' => null, 'message' => null];
    }
    if (!is_readable($pad)) {
        return ['status' => 'invalid', 'data' => null, 'code' => 'onleesbaar', 'message' => 'bestaand bestand is niet leesbaar'];
    }

    $raw = @file_get_contents($pad);
    if ($raw === false) {
        return ['status' => 'invalid', 'data' => null, 'code' => 'leesfout', 'message' => 'bestaand bestand kon niet worden gelezen'];
    }
    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        return ['status' => 'invalid', 'data' => null, 'code' => 'ongeldige_json', 'message' => 'JSON is syntactisch ongeldig'];
    }
    if (!is_array($data)) {
        return ['status' => 'invalid', 'data' => null, 'code' => 'ongeldige_root', 'message' => 'JSON-root is geen object of lijst'];
    }
    if (!publicContentStructuurGeldig($sleutel, $data)) {
        return ['status' => 'invalid', 'data' => null, 'code' => 'ongeldig_schema', 'message' => 'dataset heeft een ongeldige documentstructuur'];
    }
    return ['status' => 'valid', 'data' => $data, 'code' => null, 'message' => null];
}

function publicContentLees(string $sleutel): ?array
{
    $result = publicContentLeesResult($sleutel);
    if (($result['status'] ?? '') === 'missing') return null;
    if (($result['status'] ?? '') !== 'valid') {
        $code = (string) ($result['code'] ?? 'ongeldig');
        error_log('[platform] ongeldige publieke content voor dataset ' . $sleutel . ' (' . $code . ')');
        throw new RuntimeException('Publieke contentdataset ' . $sleutel . ' is ongeldig of onleesbaar.');
    }
    return is_array($result['data'] ?? null) ? $result['data'] : null;
}

function publicContentMaakBackupVoorPad(string $pad): ?string
{
    if (!publicContentIsTenantPad($pad) || !is_file($pad)) return null;
    $sleutel = publicContentSleutelVoorPad($pad);
    if ($sleutel === null) return null;
    $result = publicContentLeesResult($sleutel);
    if (($result['status'] ?? '') !== 'valid' || !is_array($result['data'] ?? null)) return null;
    return tenantBackupMaakArray('public-' . $sleutel, $result['data']);
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
    if (!publicContentStructuurGeldig($sleutel, $data)) return false;
    if ($maakBackup && is_file($pad)) {
        $snapshot = publicContentMaakBackupVoorPad($pad);
        if ($snapshot === null) {
            error_log('[platform] publieke contentwrite afgebroken: pre-backup faalde voor ' . $sleutel);
            return false;
        }
    }

    $map = dirname($pad);
    if (!privateFilesystemBeveiligMap($map, true) || !tenantBackupPadVeilig($map)) return false;

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    if (!tenantBackupPadVeilig($pad)) return false;
    return privateFilesystemAtomischSchrijf($pad, $json, 0640);
}
