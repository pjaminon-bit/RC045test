<?php
// ============================================================
// Tenantbinding voor beheersessies
// ============================================================
require_once __DIR__ . '/core/tenant-runtime.php';

/**
 * Geeft de technische tenantkey terug die in een sessie wordt vastgelegd.
 * Een lege key is in authcontext een configuratiefout: zonder vaste identiteit
 * kan een sessie niet veilig aan een vereniging worden gebonden.
 */
function authSessionTenantSleutel(array $siteConfig): string
{
    $ruw = trim((string)($siteConfig['vereniging']['sleutel'] ?? ''));
    if ($ruw === '') {
        tenantRuntimeConfiguratieFout('Authsessie kan niet worden gebonden: vereniging.sleutel ontbreekt.');
    }
    return tenantRuntimeVeiligeSleutel($ruw);
}

/**
 * Niet-secret sessiefingerprint van de actuele password_hash van de master.
 * Een password_hash bevat al een willekeurige salt; iedere veilige rotatie
 * levert dus een andere fingerprint op. De plaintext credential wordt nergens
 * voor deze binding gebruikt of opgeslagen.
 */
function authSessionMasterFingerprint($passwordHash): ?string
{
    if (!is_string($passwordHash) || $passwordHash === '') return null;
    $info = password_get_info($passwordHash);
    if (($info['algoName'] ?? 'unknown') === 'unknown') return null;
    return hash('sha256', "master-session-v1\0" . $passwordHash);
}

/**
 * Activeert/bewaakt de fingerprint op een succesvolle masterlogin.
 * Bestaande sessies zonder fingerprint worden niet stil geüpgraded: zij moeten
 * opnieuw authenticeren. Zo trekt invoering of rotatie van de masterhash alle
 * reeds bestaande mastersessies fail-closed in.
 */
function authSessionMasterBewaak(?string $verwacht): bool
{
    if ($verwacht === null) return true; // alleen legacy/plaintext compatibility
    $actueel = $_SESSION['master_credential_fingerprint'] ?? null;
    return is_string($actueel) && hash_equals($verwacht, $actueel);
}

/**
 * Laat een mogelijk vreemde sessie los zonder die op schijf te wijzigen en
 * start daarna een schone sessie voor de actieve tenant.
 *
 * session_abort() is hier bewust belangrijker dan session_destroy(): bij een
 * gestolen/hergebruikte session-id kan het geopende sessiebestand van een
 * andere tenant zijn. Dat bestand verwijderen of overschrijven zou tenant A
 * door een request aan tenant B kunnen uitloggen of beschadigen.
 */
function authSessionTenantHerstart(string $tenantKey): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new LogicException('Tenantbewaking vereist een actieve PHP-sessie.');
    }

    $cookieNaam = session_name();
    session_abort();

    // PHP kan het eerder aangeleverde ID anders opnieuw gebruiken. Verwijder
    // zowel de requestcookie als het actieve ID voordat de schone sessie start.
    if ($cookieNaam !== '' && isset($_COOKIE[$cookieNaam])) {
        unset($_COOKIE[$cookieNaam]);
    }
    session_id('');
    if (!session_start()) {
        throw new RuntimeException('Nieuwe tenantgebonden sessie kon niet worden gestart.');
    }

    $_SESSION = [
        'tenant_key' => $tenantKey,
        'csrf' => bin2hex(random_bytes(32)),
    ];
}

/**
 * Controleert/bindt de actieve sessie aan de huidige tenant.
 *
 * Return true: de bestaande sessie hoort bij deze tenant.
 * Return false: een vreemde of ongebonden geauthenticeerde externe sessie is
 * verworpen en vervangen door een schone sessie.
 *
 * Voor standalone legacy RC045 worden bestaande sessies zonder tenant_key één
 * keer in-place gebonden. Nieuwe/externe tenants zijn strenger: een reeds
 * geauthenticeerde maar nog ongebonden sessie wordt niet vertrouwd.
 */
function authSessionTenantBewaak(string $tenantKey, bool $externeTenant, string &$csrfToken): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new LogicException('Tenantbewaking vereist een actieve PHP-sessie.');
    }

    $gebonden = $_SESSION['tenant_key'] ?? null;
    $heeftAuthState = isset($_SESSION['gebruiker']) || !empty($_SESSION['is_master']);

    if ($gebonden === null || $gebonden === '') {
        if ($externeTenant && $heeftAuthState) {
            authSessionTenantHerstart($tenantKey);
            $csrfToken = (string)$_SESSION['csrf'];
            return false;
        }
        $_SESSION['tenant_key'] = $tenantKey;
        return true;
    }

    if (!is_string($gebonden) || !hash_equals($tenantKey, $gebonden)) {
        authSessionTenantHerstart($tenantKey);
        $csrfToken = (string)$_SESSION['csrf'];
        return false;
    }

    return true;
}
