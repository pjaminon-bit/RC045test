<?php
// ============================================================
// Capability-laag voor beheer en verenigingsadministratie
// ============================================================
// Nieuwe autorisaties gebruiken technische capabilities in plaats van UI-
// tabnamen. Bestaande accounts met alleen `tabs` blijven werken: hun oude
// rechten worden hier naar capabilities vertaald. Nieuwe writes bewaren naast
// `capabilities` tijdelijk een afgeleide `tabs`-lijst voor legacycode.
// ============================================================

function authPlatformDefinities(): array
{
    static $platform = null;
    if ($platform === null) {
        $geladen = require __DIR__ . '/core/platform-definities.php';
        $platform = is_array($geladen) ? $geladen : [];
    }
    return $platform;
}

function authCapabilityDefinities(): array
{
    $platform = authPlatformDefinities();
    return isset($platform['capabilities']) && is_array($platform['capabilities'])
        ? $platform['capabilities']
        : [];
}

function authCapabilityLegacyMap(): array
{
    static $map = null;
    if ($map !== null) return $map;
    $map = [];
    foreach (authCapabilityDefinities() as $capability => $def) {
        foreach ((array) ($def['legacy'] ?? []) as $legacy) {
            $legacy = trim((string) $legacy);
            if ($legacy !== '' && !isset($map[$legacy])) $map[$legacy] = (string) $capability;
        }
    }
    return $map;
}

function authCapabilitiesNormaliseer(array $capabilities): array
{
    $geldig = authCapabilityDefinities();
    $resultaat = [];
    foreach ($capabilities as $capability) {
        $capability = trim((string) $capability);
        if ($capability === '' || !isset($geldig[$capability]) || in_array($capability, $resultaat, true)) continue;
        $resultaat[] = $capability;
    }
    sort($resultaat, SORT_STRING);
    return $resultaat;
}

function authCapabilitiesVanTabs(array $tabs): array
{
    $map = authCapabilityLegacyMap();
    $resultaat = [];
    foreach ($tabs as $tab) {
        $tab = trim((string) $tab);
        if ($tab !== '' && isset($map[$tab])) $resultaat[] = $map[$tab];
    }
    return authCapabilitiesNormaliseer($resultaat);
}

function authLegacyTabsVoorCapabilities(array $capabilities): array
{
    $defs = authCapabilityDefinities();
    $tabs = [];
    foreach (authCapabilitiesNormaliseer($capabilities) as $capability) {
        foreach ((array) ($defs[$capability]['legacy'] ?? []) as $legacy) {
            $legacy = trim((string) $legacy);
            if ($legacy !== '' && !in_array($legacy, $tabs, true)) $tabs[] = $legacy;
        }
    }
    sort($tabs, SORT_STRING);
    return $tabs;
}

function authLegacyBredeCapabilities(): array
{
    $resultaat = [];
    foreach (authCapabilityDefinities() as $capability => $def) {
        if (!empty($def['gevoelig'])) continue;
        $resultaat[] = (string) $capability;
    }
    return authCapabilitiesNormaliseer($resultaat);
}

function authGebruikerCapabilities(array $record): array
{
    if (isset($record['capabilities']) && is_array($record['capabilities'])) {
        return authCapabilitiesNormaliseer($record['capabilities']);
    }
    if (array_key_exists('tabs', $record) && is_array($record['tabs'])) {
        return authCapabilitiesVanTabs($record['tabs']);
    }
    return authLegacyBredeCapabilities();
}

function authGebruikerId(array $record): string
{
    $id = trim((string) ($record['id'] ?? ''));
    if (preg_match('/^usr_[a-zA-Z0-9_-]{8,64}$/', $id)) return $id;
    $naam = strtolower(trim((string) ($record['gebruikersnaam'] ?? '')));
    return $naam === '' ? '' : 'usr_legacy_' . substr(hash('sha256', $naam), 0, 16);
}

function authNieuwGebruikerId(): string
{
    return 'usr_' . bin2hex(random_bytes(10));
}

function authGebruikerMigreerRecord(array $record): array
{
    // Legacyaccounts krijgen een deterministische id op basis van hun huidige
    // gebruikersnaam. Daardoor blijft een reeds gelegde member-user-koppeling
    // geldig wanneer de capabilitymigratie pas later wordt opgeslagen.
    if (trim((string) ($record['id'] ?? '')) === '') $record['id'] = authGebruikerId($record);
    $record['capabilities'] = authGebruikerCapabilities($record);
    $record['tabs'] = authLegacyTabsVoorCapabilities($record['capabilities']);
    if (!isset($record['sessie_versie'])) $record['sessie_versie'] = 1;
    if (!array_key_exists('actief', $record)) $record['actief'] = true;
    return $record;
}

function authGebruikerRecordOpNaam(string $naam): ?array
{
    $naam = trim($naam);
    if ($naam === '') return null;
    if (function_exists('authGebruikerRecord') && isset($GLOBALS['huidigeGebruiker'])
        && strcasecmp($naam, (string) $GLOBALS['huidigeGebruiker']) === 0) {
        $record = authGebruikerRecord();
        if (is_array($record)) return $record;
    }
    if (!isset($GLOBALS['usersBestand']) || !function_exists('laadGebruikers')) return null;
    foreach (laadGebruikers($GLOBALS['usersBestand']) as $record) {
        if (is_array($record) && isset($record['gebruikersnaam'])
            && strcasecmp((string) $record['gebruikersnaam'], $naam) === 0) return $record;
    }
    return null;
}

function authHuidigeGebruikerId(): string
{
    if (empty($GLOBALS['ingelogd']) || !empty($GLOBALS['isMaster'])) return '';
    $record = authGebruikerRecordOpNaam((string) ($GLOBALS['huidigeGebruiker'] ?? ''));
    return is_array($record) ? authGebruikerId($record) : '';
}

function authRolCapabilities(): array
{
    if (empty($GLOBALS['ingelogd']) || !empty($GLOBALS['isMaster']) || !function_exists('ledenRolVanGebruiker')) return [];
    $rol = ledenRolVanGebruiker((string) ($GLOBALS['huidigeGebruiker'] ?? ''));
    if (empty($rol['bestuurslid'])) return [];
    $platform = authPlatformDefinities();
    return authCapabilitiesNormaliseer((array) ($platform['rol_capabilities']['bestuur'] ?? []));
}

function authHeeftCapability(string $capability, bool $expliciet = false): bool
{
    if (empty($GLOBALS['ingelogd'])) return false;
    if (!empty($GLOBALS['isMaster'])) return true;
    $record = authGebruikerRecordOpNaam((string) ($GLOBALS['huidigeGebruiker'] ?? ''));
    if (!is_array($record)) return false;
    $caps = authGebruikerCapabilities($record);
    if (in_array($capability, $caps, true)) return true;
    return !$expliciet && in_array($capability, authRolCapabilities(), true);
}

function authCapabilityGevoelig(string $capability): bool
{
    $defs = authCapabilityDefinities();
    return !empty($defs[$capability]['gevoelig']);
}

function authCapabilityGroepen(): array
{
    $groepen = [];
    foreach (authCapabilityDefinities() as $capability => $def) {
        $categorie = (string) ($def['categorie'] ?? 'Overig');
        if (!isset($groepen[$categorie])) $groepen[$categorie] = [];
        $groepen[$categorie][$capability] = (string) ($def['label'] ?? $capability);
    }
    return $groepen;
}
