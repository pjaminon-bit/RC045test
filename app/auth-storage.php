<?php
// ============================================================
// Tenant-aware auth storage
// ============================================================
require_once __DIR__ . '/core/tenant-runtime.php';

/**
 * Bepaalt de sessienamespace van een externe tenant. Zowel de cookie-naam als
 * het serverside sessiepad worden tenant-specifiek gemaakt. De hash gebruikt
 * ook de private root, zodat twee verkeerd geconfigureerde tenants met dezelfde
 * zichtbare sleutel nog steeds niet automatisch dezelfde sessienamespace delen.
 */
function authStorageSessieContext(array $siteConfig, string $privateRoot): array
{
    $ruweSleutel = trim((string)($siteConfig['vereniging']['sleutel'] ?? ''));
    $fingerprint = hash('sha256', $privateRoot . "\0" . $ruweSleutel);
    return [
        'name' => 'VST' . substr($fingerprint, 0, 24),
        'path' => $privateRoot . '/sessions',
    ];
}

/**
 * Activeert vóór session_start() de tenant-eigen PHP-session namespace.
 *
 * Dit is een harde securitygrens: als PHP de tenant-eigen save_path niet kan
 * gebruiken, mag een externe tenant niet terugvallen op een gedeelde globale
 * session directory.
 */
function authStorageActiveerSessieIsolatie(array $siteConfig, string $privateRoot): array
{
    $context = authStorageSessieContext($siteConfig, $privateRoot);
    $sessiePad = $context['path'];

    if (!is_dir($sessiePad) && !@mkdir($sessiePad, 0750, true) && !is_dir($sessiePad)) {
        tenantRuntimeConfiguratieFout('Tenant-eigen sessiemap kon niet worden aangemaakt.');
    }
    @chmod($sessiePad, 0750);

    if (session_status() !== PHP_SESSION_NONE) {
        tenantRuntimeConfiguratieFout('Tenant-session namespace moet vóór session_start() worden geactiveerd.');
    }

    $gezet = ini_set('session.save_path', $sessiePad);
    if ($gezet === false || (string)ini_get('session.save_path') !== $sessiePad) {
        tenantRuntimeConfiguratieFout('Tenant-eigen PHP session.save_path kon niet worden geactiveerd.');
    }

    session_name($context['name']);
    if (session_name() !== $context['name']) {
        tenantRuntimeConfiguratieFout('Tenant-eigen sessiecookie kon niet worden geactiveerd.');
    }

    return $context;
}

/**
 * Bepaalt alle server-only authpaden als één ondeelbaar contract.
 *
 * Standalone/legacy RC045 zonder private_root behoudt tijdelijk de bestaande
 * rootbestanden en bestaande PHP-sessioninstellingen. Zodra een tenant een
 * expliciete private_root heeft, worden masterconfig, gebruikers, audit,
 * loginpogingen, locks, authbackups én PHP-sessies alleen tenant-lokaal
 * gebruikt. Er bestaat dan bewust geen fallback naar de gedeelde projectroot.
 */
function authStoragePaden(array $siteConfig, string $projectRoot): array
{
    $privateRoot = tenantRuntimePrivateRoot($siteConfig);
    if ($privateRoot === null) {
        return [
            'tenant_private' => false,
            'config' => $projectRoot . '/beheer-config.php',
            'users' => $projectRoot . '/beheer-users.json',
            'audit' => $projectRoot . '/beheer-log.json',
            'login_attempts' => $projectRoot . '/beheer-login-pogingen.json',
            'login_lock' => $projectRoot . '/data-backups/.login-pogingen.lock',
            'backups' => $projectRoot . '/data-backups',
            'sessions' => null,
            'session_name' => session_name(),
        ];
    }

    $sessieContext = authStorageActiveerSessieIsolatie($siteConfig, $privateRoot);

    return [
        'tenant_private' => true,
        'config' => $privateRoot . '/auth/master.php',
        'users' => $privateRoot . '/auth/users.json',
        'audit' => $privateRoot . '/audit/log.json',
        'login_attempts' => $privateRoot . '/security/login-attempts.json',
        'login_lock' => $privateRoot . '/security/.login-attempts.lock',
        'backups' => $privateRoot . '/backups/auth',
        'sessions' => $sessieContext['path'],
        'session_name' => $sessieContext['name'],
    ];
}

function authStorageMaakSchrijfmap(string $bestand): bool
{
    $map = dirname($bestand);
    if (is_dir($map)) return true;
    return @mkdir($map, 0750, true) || is_dir($map);
}
