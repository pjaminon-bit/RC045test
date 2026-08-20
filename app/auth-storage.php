<?php
// ============================================================
// Tenant-aware auth storage
// ============================================================
require_once __DIR__ . '/core/tenant-runtime.php';

/**
 * Bepaalt alle server-only authpaden als één ondeelbaar contract.
 *
 * Standalone/legacy RC045 zonder private_root behoudt tijdelijk de bestaande
 * rootbestanden. Zodra een tenant een expliciete private_root heeft, worden
 * masterconfig, gebruikers, audit, loginpogingen, locks en authbackups alleen
 * nog onder die private root gezocht. Er bestaat dan bewust geen fallback naar
 * de gedeelde projectroot.
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
        ];
    }

    return [
        'tenant_private' => true,
        'config' => $privateRoot . '/auth/master.php',
        'users' => $privateRoot . '/auth/users.json',
        'audit' => $privateRoot . '/audit/log.json',
        'login_attempts' => $privateRoot . '/security/login-attempts.json',
        'login_lock' => $privateRoot . '/security/.login-attempts.lock',
        'backups' => $privateRoot . '/backups/auth',
    ];
}

function authStorageMaakSchrijfmap(string $bestand): bool
{
    $map = dirname($bestand);
    if (is_dir($map)) return true;
    return @mkdir($map, 0750, true) || is_dir($map);
}
