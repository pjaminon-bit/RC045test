<?php
// ============================================================
// Private-store migration write guard
// ============================================================
// Voor externe VPS-tenants kan de privileged host-engine een korte migratie-
// window openen in een root-owned storage-runtime map. Normale private writes
// houden een shared flock vast; de JSON→PDO-worker gebruikt exclusive flock.
// Een root-owned active-marker zorgt dat nieuwe writers fail-closed stoppen.
// Standalone installs zonder externe tenantconfig blijven onaangeraakt.
// ============================================================

function privateStoreMigrationRuntimeDir(): ?string
{
    $extern = trim((string)(getenv('VERENIGING_CONFIG_FILE') ?: ''));
    if ($extern === '' || !tenantRuntimeIsAbsoluutPad($extern)) return null;
    return dirname($extern) . DIRECTORY_SEPARATOR . 'storage-runtime';
}

function privateStoreMigrationLockPad(): ?string
{
    $dir = privateStoreMigrationRuntimeDir();
    return $dir === null ? null : $dir . DIRECTORY_SEPARATOR . 'private-store-migration.lock';
}

function privateStoreMigrationMarkerPad(): ?string
{
    $dir = privateStoreMigrationRuntimeDir();
    return $dir === null ? null : $dir . DIRECTORY_SEPARATOR . 'private-store-migration.active';
}

function &privateStoreMigrationGuardContext(): array
{
    static $context = ['depth' => 0, 'handle' => null];
    return $context;
}

/**
 * Houdt voor de hele private-store write/transaction een shared lock vast.
 * Wanneer de host-engine de active-marker heeft geplaatst, worden nieuwe
 * writes vóór hun eerste mutatie geweigerd. Re-entrant calls delen één lock.
 */
function privateStoreMigrationWriteGuardEnter(): void
{
    $lockPad = privateStoreMigrationLockPad();
    if ($lockPad === null || !is_file($lockPad)) return;
    if (is_link($lockPad)) throw new RuntimeException('Private datastore migratielock is onveilig.');

    $context =& privateStoreMigrationGuardContext();
    if ((int)$context['depth'] > 0) {
        $context['depth']++;
        return;
    }

    $handle = @fopen($lockPad, 'r+');
    if (!is_resource($handle)) throw new RuntimeException('Private datastore migratielock kon niet worden geopend.');
    if (!@flock($handle, LOCK_SH)) {
        fclose($handle);
        throw new RuntimeException('Private datastore migratielock kon niet worden verkregen.');
    }

    $marker = privateStoreMigrationMarkerPad();
    if ($marker !== null && file_exists($marker)) {
        @flock($handle, LOCK_UN);
        fclose($handle);
        throw new RuntimeException('Private datastore is tijdelijk alleen-lezen wegens gecontroleerde migratie.');
    }

    $context['handle'] = $handle;
    $context['depth'] = 1;
}

function privateStoreMigrationWriteGuardLeave(): void
{
    $context =& privateStoreMigrationGuardContext();
    if ((int)$context['depth'] <= 0) return;
    $context['depth']--;
    if ((int)$context['depth'] > 0) return;
    $handle = $context['handle'];
    if (is_resource($handle)) {
        @flock($handle, LOCK_UN);
        fclose($handle);
    }
    $context = ['depth' => 0, 'handle' => null];
}

/**
 * Alleen de migratieworker gebruikt deze exclusive lock. De host-engine moet
 * de root-owned active-marker al hebben gezet voordat deze functie start.
 */
function privateStoreMigrationExclusive(callable $callback)
{
    $lockPad = privateStoreMigrationLockPad();
    $marker = privateStoreMigrationMarkerPad();
    if ($lockPad === null || $marker === null || !is_file($lockPad) || !is_file($marker)) {
        throw new RuntimeException('Gecontroleerde private-store migratiewindow is niet actief.');
    }
    if (is_link($lockPad) || is_link($marker)) throw new RuntimeException('Private-store migratiegrens bevat een symlink.');

    $handle = @fopen($lockPad, 'r+');
    if (!is_resource($handle)) throw new RuntimeException('Private-store migratielock kon niet worden geopend.');
    if (!@flock($handle, LOCK_EX)) {
        fclose($handle);
        throw new RuntimeException('Exclusieve private-store migratielock kon niet worden verkregen.');
    }
    try {
        if (!is_file($marker)) throw new RuntimeException('Private-store migratiemarker verdween tijdens lock-acquisitie.');
        return $callback();
    } finally {
        @flock($handle, LOCK_UN);
        fclose($handle);
    }
}
