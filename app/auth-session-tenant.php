<?php
// ============================================================
// Tenant- en installatiebinding voor beheersessies
// ============================================================
require_once __DIR__ . '/core/tenant-runtime.php';

function authSessionTenantSleutel(array $siteConfig): string
{
    $ruw = trim((string)($siteConfig['vereniging']['sleutel'] ?? ''));
    if ($ruw === '') {
        tenantRuntimeConfiguratieFout('Authsessie kan niet worden gebonden: vereniging.sleutel ontbreekt.');
    }
    return tenantRuntimeVeiligeSleutel($ruw);
}

function authSessionBindingSleutel(string $binding): string
{
    $binding = trim($binding);
    if (preg_match('/^[0-9a-f]{64}$/D', $binding) !== 1) {
        tenantRuntimeConfiguratieFout('Authsessie heeft geen geldige installatiebinding.');
    }
    return $binding;
}

/**
 * Laat een mogelijk vreemde sessie los zonder die op schijf te wijzigen en
 * start daarna een schone sessie voor de actieve tenant/installatie.
 */
function authSessionTenantHerstart(string $tenantKey, string $installatieBinding): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new LogicException('Tenantbewaking vereist een actieve PHP-sessie.');
    }

    $cookieNaam = session_name();
    session_abort();
    if ($cookieNaam !== '' && isset($_COOKIE[$cookieNaam])) unset($_COOKIE[$cookieNaam]);
    session_id('');
    if (!session_start()) throw new RuntimeException('Nieuwe tenantgebonden sessie kon niet worden gestart.');

    $_SESSION = [
        'tenant_key' => $tenantKey,
        'installation_binding' => $installatieBinding,
        'csrf' => bin2hex(random_bytes(32)),
    ];
}

/**
 * Controleert/bindt de actieve sessie aan zowel tenant als installatie.
 * Een login zonder installatiebinding is vanaf deze hardening bewust ongeldig:
 * dit forceert éénmalig opnieuw inloggen en voorkomt dat historische PROD/DEV-
 * sessies ooit over de nieuwe grens heen worden geaccepteerd.
 */
function authSessionTenantBewaak(string $tenantKey, string $installatieBinding, string &$csrfToken): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new LogicException('Tenantbewaking vereist een actieve PHP-sessie.');
    }

    $installatieBinding = authSessionBindingSleutel($installatieBinding);
    $gebondenTenant = $_SESSION['tenant_key'] ?? null;
    $gebondenInstallatie = $_SESSION['installation_binding'] ?? null;
    $heeftAuthState = isset($_SESSION['gebruiker']) || !empty($_SESSION['is_master']);

    $tenantOntbreekt = $gebondenTenant === null || $gebondenTenant === '';
    $installatieOntbreekt = $gebondenInstallatie === null || $gebondenInstallatie === '';

    if ($tenantOntbreekt && $installatieOntbreekt && !$heeftAuthState) {
        $_SESSION['tenant_key'] = $tenantKey;
        $_SESSION['installation_binding'] = $installatieBinding;
        return true;
    }

    if ($tenantOntbreekt || $installatieOntbreekt
        || !is_string($gebondenTenant)
        || !is_string($gebondenInstallatie)
        || !hash_equals($tenantKey, $gebondenTenant)
        || !hash_equals($installatieBinding, $gebondenInstallatie)) {
        authSessionTenantHerstart($tenantKey, $installatieBinding);
        $csrfToken = (string)$_SESSION['csrf'];
        return false;
    }

    return true;
}
